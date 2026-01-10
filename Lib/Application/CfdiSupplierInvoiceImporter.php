<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use Exception;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\LineaFacturaProveedor;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;

class CfdiSupplierInvoiceImporter
{
    /**
     * @throws Exception
     */
    public function import(CfdiProveedor $cfdi, Proveedor $supplier, array $conceptos): FacturaProveedor
    {
        $db = new DataBase();
        $db->beginTransaction();

        try {
            $invoice = $this->loadOrCreateInvoice($cfdi, $supplier);

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

    /**
     * @throws Exception
     */
    protected function loadOrCreateInvoice(CfdiProveedor $cfdi, Proveedor $supplier): FacturaProveedor
    {
        $invoice = new FacturaProveedor();
        $where = [
            new DataBaseWhere('numproveedor', $cfdi->invoiceNumber()),
            new DataBaseWhere('codproveedor', $supplier->codproveedor)
        ];

        if ($invoice->loadWhere($where)) {
            return $invoice;
        }

        $invoice->setSubject($supplier);
        $invoice->numproveedor = $cfdi->invoiceNumber();
        $invoice->codpago = $this->formaPagoFromCfdi($cfdi);
        $invoice->setDate($cfdi->emissionDate(), $cfdi->emissionTime());
        $invoice->save();

        return $invoice;
    }

    protected function clearInvoiceLines(FacturaProveedor $invoice): void
    {
        foreach ($invoice->getLines() as $line) {
            $line->delete();
        }
    }

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
    protected function linkSupplierProduct(array $concepto, Proveedor $supplier): void
    {
        if (empty($concepto['referencia']) || empty($concepto['referencia_proveedor'])) {
            return;
        }

        $product = new ProductoProveedor();

        if ($product->loadWhereEq('refproveedor', $concepto['referencia_proveedor'])) {
            return;
        }

        $product->codproveedor = $supplier->codproveedor;
        $product->refproveedor = $concepto['referencia_proveedor'];
        $product->referencia = $concepto['referencia'];

        if (!$product->save()) {
            throw new Exception('Error guardando vínculo producto-proveedor');
        }
    }

    /**
     * @throws Exception
     */
    protected function formaPagoFromCfdi(CfdiProveedor $cfdi): string
    {
        $result = FormaPago::table()->whereEq('clavesat', $cfdi->forma_pago)->first();

        if ($result['codpago']) {
            return $result['codpago'];
        }

        throw new Exception('Forma de pago invalida: ' . $cfdi->forma_pago . $result['codpago']);
    }
}
