<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;

class SupplierCfdiStatusService
{
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_LINKED = 'linked';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_LEGACY_INVOICED = 'invoiced';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_LEGACY_IMPORTED = 'vigente';

    public static function filterOptions(): array
    {
        return [
            ['code' => self::STATUS_IMPORTED, 'description' => Tools::trans('supplier-cfdi-status-imported')],
            ['code' => self::STATUS_LINKED, 'description' => Tools::trans('supplier-cfdi-status-linked')],
            ['code' => self::STATUS_DRAFT, 'description' => Tools::trans('supplier-cfdi-status-draft')],
            ['code' => self::STATUS_RECEIVED, 'description' => Tools::trans('supplier-cfdi-status-received')],
            ['code' => self::STATUS_LEGACY_INVOICED, 'description' => Tools::trans('supplier-cfdi-status-invoiced')],
            ['code' => self::STATUS_CANCELLED, 'description' => Tools::trans('supplier-cfdi-status-cancelled')],
            ['code' => self::STATUS_LEGACY_IMPORTED, 'description' => Tools::trans('supplier-cfdi-status-imported')],
        ];
    }

    public function markImported(CfdiProveedor $cfdi): bool
    {
        return $this->setStatus($cfdi, self::STATUS_IMPORTED);
    }

    public function markLinked(CfdiProveedor $cfdi): bool
    {
        return $this->setStatus($cfdi, self::STATUS_LINKED);
    }

    public function markInvoiceCreated(CfdiProveedor $cfdi, FacturaProveedor $invoice): bool
    {
        $cfdi->idfactura = $invoice->idfactura;
        $status = $this->isReceivedInvoice($invoice)
            ? self::STATUS_RECEIVED
            : self::STATUS_DRAFT;

        return $this->setStatus($cfdi, $status);
    }

    public function markReceived(CfdiProveedor $cfdi, FacturaProveedor $invoice): bool
    {
        $cfdi->idfactura = $invoice->idfactura;
        return $this->setStatus($cfdi, self::STATUS_RECEIVED);
    }

    public function markCancelled(CfdiProveedor $cfdi): bool
    {
        return $this->setStatus($cfdi, self::STATUS_CANCELLED);
    }

    private function isReceivedInvoice(FacturaProveedor $invoice): bool
    {
        return mb_strtolower(trim($invoice->getStatus()->nombre)) === 'recibida';
    }

    private function setStatus(CfdiProveedor $cfdi, string $status): bool
    {
        if ($cfdi->estado === self::STATUS_CANCELLED && $status !== self::STATUS_CANCELLED) {
            return false;
        }

        if ($cfdi->estado === self::STATUS_RECEIVED && $status !== self::STATUS_RECEIVED) {
            return false;
        }

        if (
            $cfdi->estado === self::STATUS_DRAFT
            && !in_array($status, [self::STATUS_DRAFT, self::STATUS_RECEIVED], true)
        ) {
            return false;
        }

        $cfdi->estado = $status;
        return $cfdi->save();
    }
}
