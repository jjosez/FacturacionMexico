<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Queue;

use DateTime;

class ImportJob
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public int $id = 0;
    public ?string $lockToken = null;
    public int $companyId = 0;
    public string $userNick = '';
    public string $status = self::STATUS_PENDING;
    public string $filePath = '';
    public ?string $config = null;
    public ?string $result = null;
    public ?string $error = null;
    public int $progress = 0;
    public int $totalItems = 0;
    public int $processedItems = 0;
    public ?DateTime $createdAt = null;
    public ?DateTime $processedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markAsProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
    }

    public function markAsCompleted(string $result): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->result = $result;
        $this->processedAt = new DateTime();
        $this->progress = 100;
    }

    public function markAsFailed(string $error): void
    {
        $this->status = self::STATUS_FAILED;
        $this->error = $error;
        $this->processedAt = new DateTime();
    }

    public function updateProgress(int $processed, int $total): void
    {
        $this->processedItems = $processed;
        $this->totalItems = $total;
        $this->progress = $total > 0 ? (int)(($processed / $total) * 100) : 0;
    }

    public function getElapsedSeconds(): float
    {
        if ($this->createdAt === null || $this->processedAt === null) {
            return 0.0;
        }

        return $this->processedAt->getTimestamp() - $this->createdAt->getTimestamp();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'lock_token' => $this->lockToken,
            'idempresa' => $this->companyId,
            'nick' => $this->userNick,
            'status' => $this->status,
            'file_path' => $this->filePath,
            'config' => $this->config,
            'result' => $this->result,
            'error' => $this->error,
            'progress' => $this->progress,
            'total_items' => $this->totalItems,
            'processed_items' => $this->processedItems,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'processed_at' => $this->processedAt?->format('Y-m-d H:i:s'),
            'elapsed_seconds' => $this->getElapsedSeconds(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $job = new self();

        $job->id = (int)($data['id'] ?? 0);
        $job->lockToken = $data['lock_token'] ?? null;
        $job->companyId = (int)($data['idempresa'] ?? 0);
        $job->userNick = (string)($data['nick'] ?? '');
        $job->status = $data['status'] ?? self::STATUS_PENDING;
        $job->filePath = $data['file_path'] ?? '';
        $job->config = $data['config'] ?? null;
        $job->result = $data['result'] ?? null;
        $job->error = $data['error'] ?? null;
        $job->progress = (int)($data['progress'] ?? 0);
        $job->totalItems = (int)($data['total_items'] ?? 0);
        $job->processedItems = (int)($data['processed_items'] ?? 0);

        if (isset($data['created_at'])) {
            $job->createdAt = new DateTime($data['created_at']);
        }

        if (isset($data['processed_at'])) {
            $job->processedAt = new DateTime($data['processed_at']);
        }

        return $job;
    }
}
