<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaProveedor;

class SupplierInvoiceStateService
{
    public function isReceived(FacturaProveedor $invoice): bool
    {
        return mb_strtolower(trim((string)$invoice->getStatus()->nombre)) === 'recibida';
    }

    public function isEditable(FacturaProveedor $invoice): bool
    {
        return (bool)$invoice->editable;
    }

    public function assertImportable(FacturaProveedor $invoice): void
    {
        if ($this->isEditable($invoice)) {
            return;
        }

        $message = $this->isReceived($invoice)
            ? 'La factura de proveedor está marcada como Recibida y no se puede modificar.'
            : Tools::lang()->trans(
                'supplier-cfdi-invoice-not-editable',
                ['%id%' => $invoice->idfactura]
            );

        throw new Exception($message);
    }
}
