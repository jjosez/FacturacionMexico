<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Services;

use Exception;
use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\LineaFacturaProveedor;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;

class CfdiSupplierInvoiceImporter
{
    public function import(CfdiProveedor $cfdi, Proveedor $supplier, array $conceptos): FacturaProveedor
    {
        $invoice = $this->loadOrCreateInvoice($cfdi, $supplier);

        $db = new DataBase();
        $db->beginTransaction();

        try {
            $this->clearInvoiceLines($invoice);
            $lines = $this->createInvoiceLines($invoice, $conceptos, $supplier);

            Calculator::calculate($invoice, $lines, true);

            $db->commit();
            return $invoice;

        } catch (Exception $e) {
            $db->rollBack();
            Tools::log()->error($e->getMessage());
            throw $e;
        }
    }

    protected function loadOrCreateInvoice(CfdiProveedor $cfdi, Proveedor $supplier): FacturaProveedor
    {
        $invoice = new FacturaProveedor();
        $where = [
            new DataBaseWhere('numproveedor', $cfdi->invoiceNumber()),
            new DataBaseWhere('codproveedor', $supplier->codproveedor)
        ];

        if ($invoice->loadFromCode('', $where)) {
            return $invoice;
        }

        $invoice->setSubject($supplier);
        $invoice->numproveedor = $cfdi->invoiceNumber();
        $invoice->codpago = $this->getFormaPagoForInvoice($cfdi->forma_pago);
        $invoice->save();

        return $invoice;
    }

    protected function clearInvoiceLines(FacturaProveedor $invoice): void
    {
        foreach ($invoice->getLines() as $line) {
            $line->delete();
        }
    }

    protected function createInvoiceLines(FacturaProveedor $invoice, array $conceptos, Proveedor $supplier): array
    {
        $lines = [];

        foreach ($conceptos as $concepto) {
            $line = !empty($concepto['referencia'])
                ? $invoice->getNewProductLine($concepto['referencia'])
                : $invoice->getNewLine($concepto);

            $line->cantidad = $concepto['cantidad'];
            $line->descripcion = $concepto['descripcion'];
            $line->pvpunitario = $concepto['valorunitario'];
            $line->pvpsindto = $concepto['importe'];

            $this->setLineDiscount($line, $concepto);
            $this->setLineTax($line, $concepto);
            $this->linkSupplierProduct($concepto, $supplier);

            if (!$line->save()) {
                throw new Exception('Error guardando línea de factura');
            }

            $lines[] = $line;
        }

        return $lines;
    }

    protected function setLineDiscount(LineaFacturaProveedor $linea, array $concepto): void
    {
        $importeBruto = (float)$concepto['importe'];
        $descuentoNeto = isset($concepto['descuento']) ? (float)$concepto['descuento'] : 0.0;

        $linea->dtopor = ($importeBruto > 0)
            ? round(($descuentoNeto / $importeBruto) * 100, 2)
            : 0.0;
    }

    protected function setLineTax(LineaFacturaProveedor $linea, array $concepto): void
    {
        $iva = 0.0;
        foreach ($concepto['traslados'] as $traslado) {
            if ('002' === $traslado['impuesto']) {
                $iva = (float)$traslado['tasa'] * 100;
            }
        }
        $linea->iva = $iva;
    }

    protected function linkSupplierProduct(array $concepto, Proveedor $supplier): void
    {
        if (empty($concepto['referencia']) || empty($concepto['referencia_proveedor'])) {
            return;
        }

        $product = new ProductoProveedor();
        $where = [
            new DataBaseWhere('refproveedor', $concepto['referencia_proveedor'])
        ];

        if ($product->loadFromCode('', $where)) {
            return;
        }

        $product->codproveedor = $supplier->codproveedor;
        $product->refproveedor = $concepto['referencia_proveedor'];
        $product->referencia = $concepto['referencia'];

        if (!$product->save()) {
            throw new Exception('Error guardando vínculo producto-proveedor');
        }
    }

    protected function getFormaPagoForInvoice(string $clavesat): string
    {
        $result = FormaPago::table()->whereEq('clavesat', $clavesat)->get();

        return $result[0]['clavesat'] ?? '99';
    }
}
