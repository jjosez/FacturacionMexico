<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice;

use FacturaScripts\Core\Plugins;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\LineaFacturaProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options\ProductImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options\TaxImportOptions;

class SupplierInvoiceLineBuilder
{
    public function buildProductLine(
        FacturaProveedor $invoice,
        array $concepto,
        Producto $product,
        ProductImportOptions $productOptions,
        TaxImportOptions $taxOptions,
        bool $isEgreso = false
    ): LineaFacturaProveedor {
        $line = $invoice->getNewProductLine($product->referencia);

        if (Plugins::isEnabled('SKU') && !empty($concepto['NoIdentificacion'])) {
            $line->referencia_proveedor = trim((string)$concepto['NoIdentificacion']);
        }

        $line->cantidad = $isEgreso
            ? -abs((float)$concepto['Cantidad'])
            : (float)$concepto['Cantidad'];
        $line->descripcion = $concepto['Descripcion'];
        $line->pvpunitario = $isEgreso
            ? abs((float)$concepto['ValorUnitario'])
            : (float)$concepto['ValorUnitario'];

        $this->applyCommonValues($line, $concepto, $productOptions, $taxOptions);
        $line->save();

        return $line;
    }

    public function buildFreeLine(
        FacturaProveedor $invoice,
        array $concepto,
        ProductImportOptions $productOptions,
        TaxImportOptions $taxOptions,
        bool $isEgreso = false
    ): LineaFacturaProveedor {
        $line = $invoice->getNewLine($concepto);
        $line->cantidad = $isEgreso
            ? -abs((float)$concepto['Cantidad'])
            : (float)$concepto['Cantidad'];
        $line->descripcion = $concepto['Descripcion'];
        $line->pvpunitario = $isEgreso
            ? abs((float)$concepto['ValorUnitario'])
            : (float)$concepto['ValorUnitario'];

        $this->applyCommonValues($line, $concepto, $productOptions, $taxOptions);
        $line->save();

        return $line;
    }

    private function applyCommonValues(
        LineaFacturaProveedor $line,
        array $concepto,
        ProductImportOptions $productOptions,
        TaxImportOptions $taxOptions
    ): void {
        if ($productOptions->shouldUpdatePrices()) {
            $line->pvpunitario *= $productOptions->priceMultiplier;
        }

        $discount = isset($concepto['Descuento']) ? abs((float)$concepto['Descuento']) : 0.0;
        $gross = abs($line->cantidad * $line->pvpunitario);
        $line->dtopor = $discount > 0 && $gross > 0
            ? round(($discount / $gross) * 100, 6)
            : 0.0;

        if ($taxOptions->shouldPreserveTax()) {
            $iva = 0.0;
            foreach ($concepto['Traslados'] ?? [] as $traslado) {
                if (($traslado['Impuesto'] ?? '') === '002') {
                    $iva = (float)$traslado['TasaOCuota'] * 100;
                }
            }
            $line->iva = $iva;
        }
    }
}
