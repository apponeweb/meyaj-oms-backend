<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateSaleRequest;
use App\DTO\Response\SaleDetailResponse;
use App\DTO\Response\SaleResponse;
use App\Entity\Customer;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class SaleService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SaleRepository $saleRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination, ?string $status = null, ?string $startDate = null, ?string $endDate = null): PaginatedResponse
    {
        $qb = $this->saleRepository->createQueryBuilder('s')
            ->leftJoin('s.customer', 'c')
            ->addSelect('c')
            ->orderBy('s.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }

        if ($startDate) {
            $qb->andWhere('s.createdAt >= :startDate')
                ->setParameter('startDate', new \DateTimeImmutable($startDate));
        }

        if ($endDate) {
            $qb->andWhere('s.createdAt <= :endDate')
                ->setParameter('endDate', new \DateTimeImmutable($endDate . ' 23:59:59'));
        }

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (Sale $sale) => new SaleResponse($sale),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): SaleDetailResponse
    {
        $sale = $this->saleRepository->createQueryBuilder('s')
            ->leftJoin('s.customer', 'c')
            ->leftJoin('s.saleItems', 'si')
            ->leftJoin('si.product', 'p')
            ->leftJoin('s.payments', 'pay')
            ->addSelect('c', 'si', 'p', 'pay')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($sale === null) {
            throw new NotFoundHttpException(sprintf('Venta con ID %d no encontrada.', $id));
        }

        return new SaleDetailResponse($sale);
    }

    public function createSale(CreateSaleRequest $request): SaleResponse
    {
        $this->em->beginTransaction();

        try {
            $sale = new Sale();

            if ($request->customerId !== null) {
                $customer = $this->em->find(Customer::class, $request->customerId);
                if ($customer === null) {
                    throw new NotFoundHttpException(sprintf('Cliente con ID %d no encontrado.', $request->customerId));
                }
                $sale->setCustomer($customer);
            }

            $sale->setPaymentMethod($request->paymentMethod);
            $sale->setNotes($request->notes);

            $subtotal = '0.00';
            $totalTax = '0.00';
            $totalDiscount = '0.00';

            foreach ($request->items as $itemRequest) {
                $product = $this->em->find(Product::class, $itemRequest->productId);
                if ($product === null) {
                    throw new NotFoundHttpException(sprintf('Producto con ID %d no encontrado.', $itemRequest->productId));
                }

                if ($product->getStock() < $itemRequest->quantity) {
                    throw new BadRequestHttpException(sprintf('Stock insuficiente para el producto: %s', $product->getName()));
                }

                $unitPrice = $product->getPrice();
                $itemTotal = bcmul($unitPrice, (string) $itemRequest->quantity, 2);
                $itemDiscount = $itemRequest->discountAmount;
                $itemTax = '0.00';

                $saleItem = new SaleItem();
                $saleItem->setProduct($product);
                $saleItem->setQuantity($itemRequest->quantity);
                $saleItem->setUnitPrice($unitPrice);
                $saleItem->setTotalPrice($itemTotal);
                $saleItem->setDiscountAmount($itemDiscount);
                $saleItem->setTaxAmount($itemTax);

                $sale->addSaleItem($saleItem);

                $product->setStock($product->getStock() - $itemRequest->quantity);
                $this->em->persist($product);

                $subtotal = bcadd($subtotal, $itemTotal, 2);
                $totalTax = bcadd($totalTax, $itemTax, 2);
                $totalDiscount = bcadd($totalDiscount, $itemDiscount, 2);
            }

            if ($request->discountAmount !== null) {
                $totalDiscount = bcadd($totalDiscount, $request->discountAmount, 2);
            }

            $total = bcadd(bcsub($subtotal, $totalDiscount, 2), $totalTax, 2);

            $sale->setSubtotal($subtotal);
            $sale->setTax($totalTax);
            $sale->setDiscount($totalDiscount);
            $sale->setTotal($total);

            foreach ($request->payments as $paymentRequest) {
                $payment = new Payment();
                $payment->setAmount($paymentRequest->amount);
                $payment->setMethod($paymentRequest->method);
                $payment->setTransactionId($paymentRequest->transactionId);
                $payment->setStatus(Payment::STATUS_COMPLETED);
                $payment->setNotes($paymentRequest->notes);

                $sale->addPayment($payment);
            }

            $sale->setStatus(Sale::STATUS_COMPLETED);

            $this->em->persist($sale);
            $this->em->flush();
            $this->em->commit();

            return new SaleResponse($sale);

        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function cancelSale(int $id): SaleResponse
    {
        $sale = $this->saleRepository->find($id);
        if ($sale === null) {
            throw new NotFoundHttpException(sprintf('Venta con ID %d no encontrada.', $id));
        }

        if ($sale->getStatus() !== Sale::STATUS_COMPLETED) {
            throw new BadRequestHttpException('Solo se pueden cancelar ventas completadas.');
        }

        $this->em->beginTransaction();

        try {
            foreach ($sale->getSaleItems() as $saleItem) {
                $product = $saleItem->getProduct();
                $product->setStock($product->getStock() + $saleItem->getQuantity());
                $this->em->persist($product);
            }

            $sale->setStatus(Sale::STATUS_CANCELLED);

            foreach ($sale->getPayments() as $payment) {
                if ($payment->getStatus() === Payment::STATUS_COMPLETED) {
                    $payment->setStatus(Payment::STATUS_REFUNDED);
                }
            }

            $this->em->flush();
            $this->em->commit();

            return new SaleResponse($sale);

        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function dailyReport(?string $date = null): array
    {
        $reportDate = $date ? new \DateTimeImmutable($date) : new \DateTimeImmutable();
        $total = $this->saleRepository->getDailySalesTotal($reportDate);
        $sales = $this->saleRepository->findTodaySales();

        return [
            'date' => $reportDate->format('Y-m-d'),
            'total' => $total,
            'sales_count' => count($sales),
            'sales' => array_map(static fn (Sale $sale) => [
                'id' => $sale->getId(),
                'total' => $sale->getTotal(),
                'paymentMethod' => $sale->getPaymentMethod(),
                'customer' => $sale->getCustomer() ? $sale->getCustomer()->getName() : 'Walk-in',
                'createdAt' => $sale->getCreatedAt()->format('H:i:s'),
            ], $sales),
        ];
    }

    public function monthlyReport(?string $date = null): array
    {
        $reportDate = $date ? new \DateTimeImmutable($date) : new \DateTimeImmutable();
        $total = $this->saleRepository->getMonthlySalesTotal($reportDate);
        $startDate = $reportDate->modify('first day of this month');
        $endDate = $reportDate->modify('last day of this month');

        $topProducts = $this->saleRepository->getTopSellingProducts($startDate, $endDate, 5);
        $salesByMethod = $this->saleRepository->getSalesByPaymentMethod($startDate, $endDate);

        return [
            'date' => $reportDate->format('Y-m'),
            'total' => $total,
            'top_products' => $topProducts,
            'sales_by_method' => $salesByMethod,
        ];
    }
}
