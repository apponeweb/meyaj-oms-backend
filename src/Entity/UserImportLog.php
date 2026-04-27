<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserImportLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserImportLogRepository::class)]
#[ORM\Table(name: 'user_import_log')]
class UserImportLog
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $filename = '';

    #[ORM\Column(length: 255)]
    private string $originalFilename = '';

    #[ORM\Column]
    private int $totalRows = 0;

    #[ORM\Column]
    private int $processedRows = 0;

    #[ORM\Column]
    private int $createdCount = 0;

    #[ORM\Column]
    private int $updatedCount = 0;

    #[ORM\Column]
    private int $errorCount = 0;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $errors = [];

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $originalFilename): static { $this->originalFilename = $originalFilename; return $this; }
    public function getTotalRows(): int { return $this->totalRows; }
    public function setTotalRows(int $totalRows): static { $this->totalRows = $totalRows; return $this; }
    public function getProcessedRows(): int { return $this->processedRows; }
    public function setProcessedRows(int $processedRows): static { $this->processedRows = $processedRows; return $this; }
    public function getCreatedCount(): int { return $this->createdCount; }
    public function setCreatedCount(int $createdCount): static { $this->createdCount = $createdCount; return $this; }
    public function getUpdatedCount(): int { return $this->updatedCount; }
    public function setUpdatedCount(int $updatedCount): static { $this->updatedCount = $updatedCount; return $this; }
    public function getErrorCount(): int { return $this->errorCount; }
    public function setErrorCount(int $errorCount): static { $this->errorCount = $errorCount; return $this; }
    public function getErrors(): array { return $this->errors; }
    public function setErrors(array $errors): static { $this->errors = $errors; return $this; }
    public function addError(string $error): static { $this->errors[] = $error; $this->errorCount = \count($this->errors); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeImmutable $completedAt): static { $this->completedAt = $completedAt; return $this; }
}
