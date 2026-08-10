<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice;

use FacturaScripts\Core\Plugins;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\LineaFacturaProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierInvoiceLineRepository;

class SupplierInvoiceLineBuilder
{
    private SupplierInvoiceLineRepository $lineRepository;

    public function __construct(?SupplierInvoiceLineRepository $lineRepository = null)
    {
        $this->lineRepository = $lineRepository ?? new SupplierInvoiceLineRepository();
    }

    public function buildProductLine(
        FacturaProveedor $invoice,
        array $concepto,
        Producto $product,
        SupplierInvoiceImportOptions $options,
        bool $isEgreso = false
    ): LineaFacturaProveedor {
        $line = $this->lineRepository->createProductLine($invoice, $product->referencia);

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

        $this->applyCommonValues($line, $concepto, $options);
        $this->lineRepository->save($line);

        return $line;
    }

    public function buildFreeLine(
        FacturaProveedor $invoice,
        array $concepto,
        SupplierInvoiceImportOptions $options,
        bool $isEgreso = false
    ): LineaFacturaProveedor {
        $line = $this->lineRepository->createFreeLine($invoice, $concepto);
        $line->cantidad = $isEgreso
            ? -abs((float)$concepto['Cantidad'])
            : (float)$concepto['Cantidad'];
        $line->descripcion = $concepto['Descripcion'];
        $line->pvpunitario = $isEgreso
            ? abs((float)$concepto['ValorUnitario'])
            : (float)$concepto['ValorUnitario'];

        $this->applyCommonValues($line, $concepto, $options);
        $this->lineRepository->save($line);

        return $line;
    }

    private function applyCommonValues(
        LineaFacturaProveedor $line,
        array $concepto,
        SupplierInvoiceImportOptions $options
    ): void {
        if ($options->shouldUpdatePrices()) {
            $line->pvpunitario *= $options->priceMultiplier;
        }

        $discount = isset($concepto['Descuento']) ? abs((float)$concepto['Descuento']) : 0.0;
        $gross = abs($line->cantidad * $line->pvpunitario);
        $line->dtopor = $discount > 0 && $gross > 0
            ? round(($discount / $gross) * 100, 6)
            : 0.0;

        if ($options->shouldPreserveTax()) {
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
