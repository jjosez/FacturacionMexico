<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\FacturaProveedor;

final class SupplierInvoiceRepository
{
    public function findById(string $id): ?FacturaProveedor
    {
        $invoice = new FacturaProveedor();
        return $invoice->load($id) ? $invoice : null;
    }

    public function findByNumberAndSupplier(string $number, string $supplierCode): ?FacturaProveedor
    {
        $invoice = new FacturaProveedor();
        $where = [
            Where::eq('numproveedor', $number),
            Where::eq('codproveedor', $supplierCode),
        ];

        return $invoice->loadWhere($where) ? $invoice : null;
    }

    public function create(): FacturaProveedor
    {
        return new FacturaProveedor();
    }

    public function clearLines(FacturaProveedor $invoice): void
    {
        foreach ($invoice->getLines() as $line) {
            $line->delete();
        }
    }

    public function save(
        FacturaProveedor $invoice,
        string $errorMessage = 'Error al guardar la factura del proveedor'
    ): void
    {
        if (!$invoice->save()) {
            throw new \Exception($errorMessage);
        }
    }
}
