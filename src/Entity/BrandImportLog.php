<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BrandImportLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BrandImportLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class BrandImportLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['import_log:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['import_log:read'])]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 255)]
    #[Groups(['import_log:read'])]
    private ?string $filename = null;

    #[ORM\Column]
    #[Groups(['import_log:read'])]
    private ?int $totalRows = null;

    #[ORM\Column]
    #[Groups(['import_log:read'])]
    private ?int $processedRows = 0;

    #[ORM\Column]
    #[Groups(['import_log:read'])]
    private ?int $createdCount = 0;

    #[ORM\Column]
    #[Groups(['import_log:read'])]
    private ?int $updatedCount = 0;

    #[ORM\Column]
    #[Groups(['import_log:read'])]
    private ?int $errorCount = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['import_log:read'])]
    private ?array $errors = [];

    #[ORM\Column(length: 20)]
    #[Groups(['import_log:read'])]
    private ?string $status = 'pending'; // pending, processing, completed, failed

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['import_log:read'])]
    private ?User $user = null;

    #[ORM\Column]
    #[Groups(['import_log:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['import_log:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getTotalRows(): ?int
    {
        return $this->totalRows;
    }

    public function setTotalRows(int $totalRows): static
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    public function getProcessedRows(): ?int
    {
        return $this->processedRows;
    }

    public function setProcessedRows(int $processedRows): static
    {
        $this->processedRows = $processedRows;

        return $this;
    }

    public function getCreatedCount(): ?int
    {
        return $this->createdCount;
    }

    public function setCreatedCount(int $createdCount): static
    {
        $this->createdCount = $createdCount;

        return $this;
    }

    public function getUpdatedCount(): ?int
    {
        return $this->updatedCount;
    }

    public function setUpdatedCount(int $updatedCount): static
    {
        $this->updatedCount = $updatedCount;

        return $this;
    }

    public function getErrorCount(): ?int
    {
        return $this->errorCount;
    }

    public function setErrorCount(int $errorCount): static
    {
        $this->errorCount = $errorCount;

        return $this;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function setErrors(?array $errors): static
    {
        $this->errors = $errors;

        return $this;
    }

    public function addError(string $error): static
    {
        $this->errors[] = $error;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
