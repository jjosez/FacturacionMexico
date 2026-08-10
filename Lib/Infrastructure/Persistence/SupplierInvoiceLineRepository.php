<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\LineaFacturaProveedor;

final class SupplierInvoiceLineRepository
{
    public function createProductLine(FacturaProveedor $invoice, string $reference): LineaFacturaProveedor
    {
        return $invoice->getNewProductLine($reference);
    }

    public function createFreeLine(FacturaProveedor $invoice, array $concepto): LineaFacturaProveedor
    {
        return $invoice->getNewLine($concepto);
    }

    public function save(LineaFacturaProveedor $line): void
    {
        $line->save();
    }
}
