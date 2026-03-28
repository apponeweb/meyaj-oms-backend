<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/customers')]
class CustomerController extends AbstractController
{
    private CustomerRepository $customerRepository;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    public function __construct(
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $this->customerRepository = $customerRepository;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);
        $search = $request->query->get('search', '');

        $offset = ($page - 1) * $limit;

        if ($search) {
            $customers = $this->customerRepository->findByNameOrPhone($search);
        } else {
            $customers = $this->customerRepository->findActiveCustomers();
        }

        $total = count($customers);
        $customers = array_slice($customers, $offset, $limit);

        $data = array_map(function (Customer $customer) {
            return [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'address' => $customer->getAddress(),
                'taxId' => $customer->getTaxId(),
                'createdAt' => $customer->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $customer->getUpdatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $customers);

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

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json(['data' => []]);
        }

        $customers = $this->customerRepository->findByNameOrPhone($query);

        $data = array_map(function (Customer $customer) {
            return [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'displayText' => $customer->getName() . ($customer->getPhone() ? ' - ' . $customer->getPhone() : ''),
            ];
        }, $customers);

        return $this->json(['data' => $data]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $customer = new Customer();
        $customer->setName($data['name']);
        $customer->setEmail($data['email'] ?? null);
        $customer->setPhone($data['phone'] ?? null);
        $customer->setAddress($data['address'] ?? null);
        $customer->setTaxId($data['taxId'] ?? null);

        $errors = $this->validator->validate($customer);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $this->json([
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'taxId' => $customer->getTaxId(),
            'createdAt' => $customer->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $customer->getUpdatedAt()->format('Y-m-d H:i:s'),
        ], 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $customer = $this->customerRepository->find($id);

        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }

        return $this->json([
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'taxId' => $customer->getTaxId(),
            'createdAt' => $customer->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $customer->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $customer = $this->customerRepository->find($id);

        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        if (isset($data['name'])) {
            $customer->setName($data['name']);
        }
        if (isset($data['email'])) {
            $customer->setEmail($data['email']);
        }
        if (isset($data['phone'])) {
            $customer->setPhone($data['phone']);
        }
        if (isset($data['address'])) {
            $customer->setAddress($data['address']);
        }
        if (isset($data['taxId'])) {
            $customer->setTaxId($data['taxId']);
        }

        $errors = $this->validator->validate($customer);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'taxId' => $customer->getTaxId(),
            'createdAt' => $customer->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $customer->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $customer = $this->customerRepository->find($id);

        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }

        $customer->setActive(false);
        $this->entityManager->flush();

        return $this->json(null, 204);
    }
}
