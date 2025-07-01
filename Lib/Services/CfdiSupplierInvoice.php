<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Services;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;

class CfdiSupplierInvoice
{
    public static function load(Proveedor $supplier, string $invoiceNumber): FacturaProveedor
    {
        $invoice = new FacturaProveedor();
        $where = [
            new DataBaseWhere('numproveedor', $invoiceNumber),
            new DataBaseWhere('codproveedor', $supplier->codproveedor)
        ];

        if ($invoice->loadFromCode('', $where)) {
            return $invoice;
        }

        $invoice->setSubject($supplier);
        $invoice->numproveedor = $invoiceNumber;
        $invoice->save();

        return $invoice;
    }

}
