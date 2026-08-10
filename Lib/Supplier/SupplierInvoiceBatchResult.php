<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier;

class SupplierInvoiceBatchResult
{
    public int $total = 0;
    public int $success = 0;
    public int $failed = 0;
    public float $elapsedSeconds = 0.0;
    public array $errors = [];
    public array $imported = [];
    public array $warnings = [];

    public function addSuccess(string $uuid, int $cfdiId, ?int $invoiceId = null): void
    {
        $this->success++;
        $this->imported[] = [
            'uuid' => $uuid,
            'cfdi_id' => $cfdiId,
            'invoice_id' => $invoiceId,
        ];
    }

    public function addFailure(string $uuid, string $error): void
    {
        $this->failed++;
        $this->errors[] = [
            'uuid' => $uuid,
            'error' => $error,
        ];
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function setElapsedTime(float $seconds): void
    {
        $this->elapsedSeconds = $seconds;
    }

    public function getSuccessRate(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }
        return $this->success / $this->total;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'success' => $this->success,
            'failed' => $this->failed,
            'success_rate' => $this->getSuccessRate(),
            'elapsed_seconds' => $this->elapsedSeconds,
            'errors' => $this->errors,
            'imported' => $this->imported,
            'warnings' => $this->warnings,
        ];
    }
}
