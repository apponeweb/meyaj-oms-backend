<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateCustomerRequest;
use App\DTO\Request\UpdateCustomerRequest;
use App\DTO\Response\CustomerResponse;
use App\Entity\Customer;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CustomerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomerRepository $customerRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->customerRepository->createQueryBuilder('c')
            ->andWhere('c.active = :active')
            ->setParameter('active', true);

        if ($pagination->search) {
            $qb->andWhere('c.name LIKE :s OR c.phone LIKE :s OR c.email LIKE :s')
                ->setParameter('s', "%{$pagination->search}%");
        }

        if ($pagination->sort === null) {
            $qb->orderBy('c.name', 'ASC');
        }

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (Customer $c) => new CustomerResponse($c),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function search(string $query): array
    {
        $customers = $this->customerRepository->findByNameOrPhone($query);

        return array_map(static fn (Customer $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'email' => $c->getEmail(),
            'phone' => $c->getPhone(),
            'displayText' => $c->getName() . ($c->getPhone() ? ' - ' . $c->getPhone() : ''),
        ], $customers);
    }

    public function show(int $id): CustomerResponse
    {
        $customer = $this->customerRepository->find($id);
        if ($customer === null) {
            throw new NotFoundHttpException(sprintf('Cliente con ID %d no encontrado.', $id));
        }

        return new CustomerResponse($customer);
    }

    public function create(CreateCustomerRequest $request): CustomerResponse
    {
        $customer = new Customer();
        $customer->setName($request->name);
        $customer->setEmail($request->email);
        $customer->setPhone($request->phone);
        $customer->setAddress($request->address);
        $customer->setTaxId($request->taxId);

        $this->em->persist($customer);
        $this->em->flush();

        return new CustomerResponse($customer);
    }

    public function update(int $id, UpdateCustomerRequest $request): CustomerResponse
    {
        $customer = $this->customerRepository->find($id);
        if ($customer === null) {
            throw new NotFoundHttpException(sprintf('Cliente con ID %d no encontrado.', $id));
        }

        if ($request->name !== null) $customer->setName($request->name);
        if ($request->email !== null) $customer->setEmail($request->email);
        if ($request->phone !== null) $customer->setPhone($request->phone);
        if ($request->address !== null) $customer->setAddress($request->address);
        if ($request->taxId !== null) $customer->setTaxId($request->taxId);

        $this->em->flush();

        return new CustomerResponse($customer);
    }

    public function delete(int $id): void
    {
        $customer = $this->customerRepository->find($id);
        if ($customer === null) {
            throw new NotFoundHttpException(sprintf('Cliente con ID %d no encontrado.', $id));
        }

        $customer->setActive(false);
        $this->em->flush();
    }
}
