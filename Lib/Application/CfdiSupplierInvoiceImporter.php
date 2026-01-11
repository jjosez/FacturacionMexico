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
     * Crea las líneas de factura desde los conceptos del CFDI
     *
     * IMPORTANTE: Solo establecemos cantidad, pvpunitario y descuentos.
     * El Calculator se encarga de calcular pvpsindto y pvptotal automáticamente
     * para evitar errores de redondeo.
     *
     * @throws Exception
     */
    protected function createInvoiceLines(FacturaProveedor $invoice, array $conceptos, Proveedor $supplier): array
    {
        $lines = [];

        foreach ($conceptos as $concepto) {
            $line = !empty($concepto['referencia'])
                ? $invoice->getNewProductLine($concepto['referencia'])
                : $invoice->getNewLine($concepto);

            // Establecer datos básicos de la línea
            $line->cantidad = (float)$concepto['Cantidad'];
            $line->descripcion = $concepto['Descripcion'];
            $line->pvpunitario = (float)$concepto['ValorUnitario'];

            // Calcular descuentos e impuestos
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

    /**
     * Calcula el descuento porcentual desde el descuento neto del CFDI
     *
     * Según el estándar CFDI 4.0:
     * - Importe = Cantidad × ValorUnitario (antes de descuento)
     * - Descuento = monto del descuento aplicado
     * - ImporteNeto = Importe - Descuento
     *
     * Calculamos el porcentaje con alta precisión (6 decimales) para minimizar
     * errores de redondeo cuando el Calculator recalcule los totales.
     *
     * @param LineaFacturaProveedor $linea
     * @param array $concepto
     */
    protected function setLineDiscount(LineaFacturaProveedor $linea, array $concepto): void
    {
        $descuentoNeto = isset($concepto['Descuento']) ? (float)$concepto['Descuento'] : 0.0;

        // Si no hay descuento, salir
        if ($descuentoNeto <= 0) {
            $linea->dtopor = 0.0;
            return;
        }

        // Calcular el importe bruto real: cantidad × pvpunitario
        $importeBruto = $linea->cantidad * $linea->pvpunitario;

        // Evitar división por cero
        if ($importeBruto <= 0) {
            $linea->dtopor = 0.0;
            return;
        }

        // Calcular porcentaje de descuento con alta precisión
        $dtopor = ($descuentoNeto / $importeBruto) * 100;

        // Redondear a 6 decimales para mantener precisión
        $linea->dtopor = round($dtopor, 6);
    }

    protected function setLineTax(LineaFacturaProveedor $linea, array $concepto): void
    {
        $iva = 0.0;
        foreach ($concepto['Traslados'] as $traslado) {
            if ('002' === $traslado['Impuesto']) {
                $iva = (float)$traslado['TasaOCuota'] * 100;
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
