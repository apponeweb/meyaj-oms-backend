<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Payment;
use App\Repository\ProductRepository;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;

class SaleService
{
    private EntityManagerInterface $entityManager;
    private ProductRepository $productRepository;
    private SaleRepository $saleRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        SaleRepository $saleRepository
    ) {
        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
        $this->saleRepository = $saleRepository;
    }

    public function createSale(array $saleData): Sale
    {
        $this->entityManager->beginTransaction();

        try {
            $sale = new Sale();
            
            // Set customer
            if (isset($saleData['customer_id'])) {
                $customer = $this->entityManager->find(Customer::class, $saleData['customer_id']);
                $sale->setCustomer($customer);
            }

            $sale->setPaymentMethod($saleData['payment_method']);
            $sale->setNotes($saleData['notes'] ?? null);

            $subtotal = '0.00';
            $totalTax = '0.00';
            $totalDiscount = '0.00';

            // Create sale items
            foreach ($saleData['items'] as $itemData) {
                $product = $this->entityManager->find(Product::class, $itemData['product_id']);
                
                if (!$product) {
                    throw new \Exception("Product with ID {$itemData['product_id']} not found");
                }

                if ($product->getStock() < $itemData['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->getName()}");
                }

                $unitPrice = $product->getPrice();
                $quantity = $itemData['quantity'];
                $itemTotal = bcmul($unitPrice, (string)$quantity, 2);
                $itemDiscount = $itemData['discount_amount'] ?? '0.00';
                $itemTax = '0.00'; // TODO: Calculate tax based on product tax rate

                $saleItem = new SaleItem();
                $saleItem->setProduct($product);
                $saleItem->setQuantity($quantity);
                $saleItem->setUnitPrice($unitPrice);
                $saleItem->setTotalPrice($itemTotal);
                $saleItem->setDiscountAmount($itemDiscount);
                $saleItem->setTaxAmount($itemTax);

                $sale->addSaleItem($saleItem);

                // Update product stock
                $product->setStock($product->getStock() - $quantity);
                $this->entityManager->persist($product);

                // Calculate totals
                $subtotal = bcadd($subtotal, $itemTotal, 2);
                $totalTax = bcadd($totalTax, $itemTax, 2);
                $totalDiscount = bcadd($totalDiscount, $itemDiscount, 2);
            }

            // Apply global discount if provided
            if (isset($saleData['discount_amount'])) {
                $totalDiscount = bcadd($totalDiscount, $saleData['discount_amount'], 2);
            }

            $total = bcadd(bcsub($subtotal, $totalDiscount, 2), $totalTax, 2);

            $sale->setSubtotal($subtotal);
            $sale->setTax($totalTax);
            $sale->setDiscount($totalDiscount);
            $sale->setTotal($total);

            // Create payments
            foreach ($saleData['payments'] as $paymentData) {
                $payment = new Payment();
                $payment->setAmount($paymentData['amount']);
                $payment->setMethod($paymentData['method']);
                $payment->setTransactionId($paymentData['transaction_id'] ?? null);
                $payment->setStatus(Payment::STATUS_COMPLETED);
                $payment->setNotes($paymentData['notes'] ?? null);

                $sale->addPayment($payment);
            }

            $sale->setStatus(Sale::STATUS_COMPLETED);

            $this->entityManager->persist($sale);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $sale;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function cancelSale(int $saleId): Sale
    {
        $sale = $this->saleRepository->find($saleId);
        
        if (!$sale) {
            throw new \Exception("Sale not found");
        }

        if ($sale->getStatus() !== Sale::STATUS_COMPLETED) {
            throw new \Exception("Only completed sales can be cancelled");
        }

        $this->entityManager->beginTransaction();

        try {
            // Restore product stock
            foreach ($sale->getSaleItems() as $saleItem) {
                $product = $saleItem->getProduct();
                $product->setStock($product->getStock() + $saleItem->getQuantity());
                $this->entityManager->persist($product);
            }

            // Update sale status
            $sale->setStatus(Sale::STATUS_CANCELLED);

            // Refund payments
            foreach ($sale->getPayments() as $payment) {
                if ($payment->getStatus() === Payment::STATUS_COMPLETED) {
                    $payment->setStatus(Payment::STATUS_REFUNDED);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $sale;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function getSalesReport(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $sales = $this->saleRepository->findByDateRange($startDate, $endDate);
        
        $totalSales = count($sales);
        $totalRevenue = '0.00';
        $totalTax = '0.00';
        $totalDiscount = '0.00';

        foreach ($sales as $sale) {
            $totalRevenue = bcadd($totalRevenue, $sale->getTotal(), 2);
            $totalTax = bcadd($totalTax, $sale->getTax(), 2);
            $totalDiscount = bcadd($totalDiscount, $sale->getDiscount(), 2);
        }

        return [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'total_tax' => $totalTax,
            'total_discount' => $totalDiscount,
            'sales' => $sales,
        ];
    }
}
