<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Sale;
use App\Repository\SaleRepository;
use App\Service\SaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/sales')]
class SaleController extends AbstractController
{
    private SaleRepository $saleRepository;
    private SaleService $saleService;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    public function __construct(
        SaleRepository $saleRepository,
        SaleService $saleService,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $this->saleRepository = $saleRepository;
        $this->saleService = $saleService;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);
        $status = $request->query->get('status');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        $qb = $this->saleRepository->createQueryBuilder('s')
            ->leftJoin('s.customer', 'c')
            ->addSelect('c')
            ->orderBy('s.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('s.status = :status')
               ->setParameter('status', $status);
        }

        if ($startDate) {
            $qb->andWhere('s.createdAt >= :startDate')
               ->setParameter('startDate', new \DateTimeImmutable($startDate));
        }

        if ($endDate) {
            $qb->andWhere('s.createdAt <= :endDate')
               ->setParameter('endDate', new \DateTimeImmutable($endDate . ' 23:59:59'));
        }

        $total = count($qb->getQuery()->getResult());
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)->setMaxResults($limit);

        $sales = $qb->getQuery()->getResult();

        $data = array_map(function (Sale $sale) {
            return [
                'id' => $sale->getId(),
                'customer' => $sale->getCustomer() ? [
                    'id' => $sale->getCustomer()->getId(),
                    'name' => $sale->getCustomer()->getName(),
                    'email' => $sale->getCustomer()->getEmail(),
                    'phone' => $sale->getCustomer()->getPhone(),
                ] : null,
                'subtotal' => $sale->getSubtotal(),
                'tax' => $sale->getTax(),
                'discount' => $sale->getDiscount(),
                'total' => $sale->getTotal(),
                'status' => $sale->getStatus(),
                'paymentMethod' => $sale->getPaymentMethod(),
                'notes' => $sale->getNotes(),
                'createdAt' => $sale->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $sale->getUpdatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $sales);

        return $this->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $page,
                'lastPage' => ceil($total / $limit),
                'perPage' => $limit,
                'total' => $total,
            ],
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        // Validate required fields
        $requiredFields = ['payment_method', 'items', 'payments'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json(['error' => "Field '$field' is required"], 400);
            }
        }

        try {
            $sale = $this->saleService->createSale($data);

            return $this->json([
                'id' => $sale->getId(),
                'customer' => $sale->getCustomer() ? [
                    'id' => $sale->getCustomer()->getId(),
                    'name' => $sale->getCustomer()->getName(),
                ] : null,
                'subtotal' => $sale->getSubtotal(),
                'tax' => $sale->getTax(),
                'discount' => $sale->getDiscount(),
                'total' => $sale->getTotal(),
                'status' => $sale->getStatus(),
                'paymentMethod' => $sale->getPaymentMethod(),
                'notes' => $sale->getNotes(),
                'createdAt' => $sale->getCreatedAt()->format('Y-m-d H:i:s'),
            ], 201);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
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

        if (!$sale) {
            return $this->json(['error' => 'Sale not found'], 404);
        }

        $saleItems = array_map(function ($saleItem) {
            return [
                'id' => $saleItem->getId(),
                'product' => [
                    'id' => $saleItem->getProduct()->getId(),
                    'name' => $saleItem->getProduct()->getName(),
                    'price' => $saleItem->getProduct()->getPrice(),
                ],
                'quantity' => $saleItem->getQuantity(),
                'unitPrice' => $saleItem->getUnitPrice(),
                'totalPrice' => $saleItem->getTotalPrice(),
                'taxAmount' => $saleItem->getTaxAmount(),
                'discountAmount' => $saleItem->getDiscountAmount(),
            ];
        }, $sale->getSaleItems()->toArray());

        $payments = array_map(function ($payment) {
            return [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'method' => $payment->getMethod(),
                'status' => $payment->getStatus(),
                'transactionId' => $payment->getTransactionId(),
                'notes' => $payment->getNotes(),
                'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $sale->getPayments()->toArray());

        return $this->json([
            'id' => $sale->getId(),
            'customer' => $sale->getCustomer() ? [
                'id' => $sale->getCustomer()->getId(),
                'name' => $sale->getCustomer()->getName(),
                'email' => $sale->getCustomer()->getEmail(),
                'phone' => $sale->getCustomer()->getPhone(),
            ] : null,
            'subtotal' => $sale->getSubtotal(),
            'tax' => $sale->getTax(),
            'discount' => $sale->getDiscount(),
            'total' => $sale->getTotal(),
            'status' => $sale->getStatus(),
            'paymentMethod' => $sale->getPaymentMethod(),
            'notes' => $sale->getNotes(),
            'saleItems' => $saleItems,
            'payments' => $payments,
            'createdAt' => $sale->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $sale->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        try {
            $sale = $this->saleService->cancelSale($id);

            return $this->json([
                'id' => $sale->getId(),
                'status' => $sale->getStatus(),
                'updatedAt' => $sale->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/reports/daily', methods: ['GET'])]
    public function dailyReport(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        $reportDate = $date ? new \DateTimeImmutable($date) : new \DateTimeImmutable();

        $total = $this->saleRepository->getDailySalesTotal($reportDate);
        $sales = $this->saleRepository->findTodaySales();

        return $this->json([
            'date' => $reportDate->format('Y-m-d'),
            'total' => $total,
            'sales_count' => count($sales),
            'sales' => array_map(function (Sale $sale) {
                return [
                    'id' => $sale->getId(),
                    'total' => $sale->getTotal(),
                    'paymentMethod' => $sale->getPaymentMethod(),
                    'customer' => $sale->getCustomer() ? $sale->getCustomer()->getName() : 'Walk-in',
                    'createdAt' => $sale->getCreatedAt()->format('H:i:s'),
                ];
            }, $sales),
        ]);
    }

    #[Route('/reports/monthly', methods: ['GET'])]
    public function monthlyReport(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        $reportDate = $date ? new \DateTimeImmutable($date) : new \DateTimeImmutable();

        $total = $this->saleRepository->getMonthlySalesTotal($reportDate);
        $startDate = $reportDate->modify('first day of this month');
        $endDate = $reportDate->modify('last day of this month');

        $topProducts = $this->saleRepository->getTopSellingProducts($startDate, $endDate, 5);
        $salesByMethod = $this->saleRepository->getSalesByPaymentMethod($startDate, $endDate);

        return $this->json([
            'date' => $reportDate->format('Y-m'),
            'total' => $total,
            'top_products' => $topProducts,
            'sales_by_method' => $salesByMethod,
        ]);
    }
}
