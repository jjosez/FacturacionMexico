<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\Result;

use FacturaScripts\Dinamic\Model\CfdiProveedor;

final class SupplierCfdiBatchResult
{
    public int $total = 0;
    public int $registered = 0;
    public int $invoicesCreated = 0;
    public int $failed = 0;
    public int $partial = 0;
    public float $elapsedSeconds = 0.0;
    public array $items = [];

    public function addRegistered(CfdiProveedor $cfdi, string $fileName): void
    {
        $this->registered++;
        $this->items[] = $this->item($fileName, 'registered', $cfdi, null, 'CFDI registrado correctamente.');
    }

    public function addInvoiceCreated(CfdiProveedor $cfdi, int $invoiceId, string $fileName): void
    {
        $this->registered++;
        $this->invoicesCreated++;
        $this->items[] = $this->item(
            $fileName,
            'invoice_created',
            $cfdi,
            $invoiceId,
            'CFDI registrado y factura creada correctamente.'
        );
    }

    public function addInvoiceFailure(CfdiProveedor $cfdi, string $fileName, string $message): void
    {
        $this->registered++;
        $this->partial++;
        $this->items[] = $this->item($fileName, 'invoice_failed', $cfdi, null, $message);
    }

    public function addFailure(string $fileName, string $message, string $status = 'registration_failed'): void
    {
        $this->failed++;
        $this->items[] = [
            'file' => $fileName,
            'status' => $status,
            'uuid' => null,
            'cfdi_id' => null,
            'invoice_id' => null,
            'message' => $message,
        ];
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'registered' => $this->registered,
            'invoices_created' => $this->invoicesCreated,
            'failed' => $this->failed,
            'partial' => $this->partial,
            'elapsed_seconds' => $this->elapsedSeconds,
            'items' => $this->items,
        ];
    }

    private function item(
        string $fileName,
        string $status,
        CfdiProveedor $cfdi,
        ?int $invoiceId,
        string $message
    ): array {
        return [
            'file' => $fileName,
            'status' => $status,
            'uuid' => $cfdi->uuid,
            'cfdi_id' => (int)$cfdi->id,
            'invoice_id' => $invoiceId,
            'message' => $message,
        ];
    }
}
