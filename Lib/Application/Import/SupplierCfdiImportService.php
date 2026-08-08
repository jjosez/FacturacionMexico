<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\LineaFacturaProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatchResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\ProductMatchingService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\ProductMappingStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;

class SupplierCfdiImportService
{
    private ProductMatchingService $matchingService;
    private ProductMappingStorage $mappingStorage;

    public function __construct(
        ?ProductMatchingService $matchingService = null,
        ?ProductMappingStorage $mappingStorage = null
    ) {
        $this->matchingService = $matchingService ?? new ProductMatchingService();
        $this->mappingStorage = $mappingStorage ?? new ProductMappingStorage();
    }

    public function importSingle(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        ?ImportOptions $options = null,
        ?array $submittedConceptos = null
    ): ImportResult {
        $options = $options ?? new ImportOptions();

        try {
            $db = new DataBase();
            $db->beginTransaction();

            $reader = $this->getCfdiReader($cfdi);
            $conceptos = $submittedConceptos ?? $reader->getConceptos();

            $invoice = $this->createOrUpdateInvoice($cfdi, $supplier, $reader);
            $this->clearInvoiceLines($invoice);

            $createdProducts = [];
            $linkedProducts = [];

            foreach ($conceptos as $index => $concepto) {
                $result = $this->processConcept(
                    $concepto,
                    $invoice,
                    $supplier,
                    $cfdi,
                    $options
                );

                if ($result['linked']) {
                    $linkedProducts[] = $result['referencia'];
                } elseif ($result['created']) {
                    $createdProducts[] = $result['referencia'];
                }
            }

            $lines = $invoice->getLines();
            Calculator::calculate($invoice, $lines, true);
            $invoice->save();

            $db->commit();

            return ImportResult::success(
                $invoice,
                count($conceptos),
                count($linkedProducts),
                $createdProducts,
                $linkedProducts
            );
        } catch (Exception $e) {
            $db->rollBack();
            return ImportResult::failure($e->getMessage());
        }
    }

    public function importBatch(
        array $cfdis,
        ?ImportOptions $options = null,
        ?callable $progressCallback = null
    ): BatchImportResult {
        $options = $options ?? new ImportOptions();
        $result = new BatchImportResult();
        $startTime = microtime(true);

        $result->setTotal(count($cfdis));

        foreach ($cfdis as $index => $cfdi) {
            try {
                $supplier = $cfdi->getSupplier();
                $importResult = $this->importSingle($cfdi, $supplier, $options);

                if ($importResult->success) {
                    $result->addSuccess(
                        $cfdi->uuid,
                        $cfdi->id,
                        $importResult->invoice?->idfactura
                    );
                } else {
                    $result->addFailure($cfdi->uuid, $importResult->error ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $result->addFailure($cfdi->uuid, $e->getMessage());
            }

            if ($progressCallback !== null) {
                $progressCallback($index + 1, count($cfdis));
            }
        }

        $result->setElapsedTime(microtime(true) - $startTime);

        return $result;
    }

    public function createInvoiceFromCfdi(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        ?ImportOptions $options = null
    ): FacturaProveedor {
        $reader = $this->getCfdiReader($cfdi);
        return $this->createOrUpdateInvoice($cfdi, $supplier, $reader);
    }

    private function getCfdiReader(CfdiProveedor $cfdi): CfdiQuickReader
    {
        $xml = $cfdi->localFileContent();
        if (empty($xml)) {
            throw new Exception('No se pudo leer el archivo XML del CFDI');
        }
        return new CfdiQuickReader($xml);
    }

    private function createOrUpdateInvoice(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        CfdiQuickReader $reader
    ): FacturaProveedor {
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
        $invoice->codpago = $this->getFormaPagoFromCfdi($cfdi);
        $invoice->setDate($cfdi->fecha_emision, $cfdi->getTime() ?? '12:00:00');

        if (!$invoice->save()) {
            throw new Exception('Error al crear la factura del proveedor');
        }

        return $invoice;
    }

    private function clearInvoiceLines(FacturaProveedor $invoice): void
    {
        foreach ($invoice->getLines() as $line) {
            $line->delete();
        }
    }

    private function processConcept(
        array $concepto,
        FacturaProveedor $invoice,
        Proveedor $supplier,
        CfdiProveedor $cfdi,
        ImportOptions $options
    ): array {
        $result = [
            'linked' => false,
            'created' => false,
            'referencia' => null,
        ];

        $product = null;
        $matchResult = null;

        if (!empty($concepto['referencia'])) {
            $product = $this->loadManualProduct($concepto['referencia']);

            if ($product !== null) {
                $result['referencia'] = $product->referencia;
                $result['linked'] = true;
                $matchResult = MatchResult::exactMatch($product, 'manual');
                $this->saveMapping($cfdi, $supplier, $concepto, $product, $matchResult);
            }
        }

        if ($product === null && $options->shouldAutoMatch()) {
            $matchResult = $this->matchingService->matchConcept($concepto, $supplier);

            if ($matchResult->isUsable()) {
                $product = $matchResult->product;
                $result['referencia'] = $product->referencia;
                $result['linked'] = true;

                $this->saveMapping($cfdi, $supplier, $concepto, $product, $matchResult);
            }
        }

        if ($product === null && $options->shouldCreateProducts()) {
            $product = $this->createProductFromConcept($concepto);
            $result['created'] = true;
            $result['referencia'] = $product->referencia;
        }

        if ($product !== null) {
            $this->createInvoiceLine($invoice, $concepto, $product, $options);
            $this->linkSupplierProduct($concepto, $supplier, $product);
        } else {
            $this->createInvoiceLineFromConcept($invoice, $concepto, $options);
        }

        return $result;
    }

    private function loadManualProduct(string $referencia): ?Producto
    {
        $product = new Producto();

        return $product->load($referencia) ? $product : null;
    }

    private function createInvoiceLine(
        FacturaProveedor $invoice,
        array $concepto,
        Producto $product,
        ImportOptions $options
    ): LineaFacturaProveedor {
        $line = $invoice->getNewProductLine($product->referencia);

        $line->cantidad = (float)$concepto['Cantidad'];
        $line->descripcion = $concepto['Descripcion'];
        $line->pvpunitario = (float)$concepto['ValorUnitario'];

        if ($options->shouldUpdatePrices()) {
            $line->pvpunitario *= $options->priceMultiplier;
        }

        $this->setLineDiscount($line, $concepto);
        $this->setLineTax($line, $concepto, $options);

        $line->save();

        return $line;
    }

    private function createInvoiceLineFromConcept(
        FacturaProveedor $invoice,
        array $concepto,
        ImportOptions $options
    ): LineaFacturaProveedor {
        $line = $invoice->getNewLine($concepto);

        $line->cantidad = (float)$concepto['Cantidad'];
        $line->descripcion = $concepto['Descripcion'];
        $line->pvpunitario = (float)$concepto['ValorUnitario'];

        $this->setLineDiscount($line, $concepto);
        $this->setLineTax($line, $concepto, $options);

        $line->save();

        return $line;
    }

    private function setLineDiscount(LineaFacturaProveedor $linea, array $concepto): void
    {
        $descuentoNeto = isset($concepto['Descuento']) ? (float)$concepto['Descuento'] : 0.0;

        if ($descuentoNeto <= 0) {
            $linea->dtopor = 0.0;
            return;
        }

        $importeBruto = $linea->cantidad * $linea->pvpunitario;

        if ($importeBruto <= 0) {
            $linea->dtopor = 0.0;
            return;
        }

        $dtopor = ($descuentoNeto / $importeBruto) * 100;
        $linea->dtopor = round($dtopor, 6);
    }

    private function setLineTax(LineaFacturaProveedor $linea, array $concepto, ImportOptions $options): void
    {
        if ($options->shouldPreserveTax()) {
            $iva = 0.0;
            foreach ($concepto['Traslados'] as $traslado) {
                if ('002' === $traslado['Impuesto']) {
                    $iva = (float)$traslado['TasaOCuota'] * 100;
                }
            }
            $linea->iva = $iva;
        }
    }

    private function createProductFromConcept(array $concepto): Producto
    {
        $product = new Producto();
        $product->descripcion = $concepto['Descripcion'];

        if (!empty($concepto['NoIdentificacion'])) {
            $product->referencia = $this->generateUniqueReference($concepto['NoIdentificacion']);
        } else {
            $product->referencia = $this->generateUniqueReference(substr($concepto['Descripcion'], 0, 20));
        }

        if (!empty($concepto['ClaveProdServ'])) {
            $product->clave_sat = $concepto['ClaveProdServ'];
        }

        $product->tipoventa = 'unidad';
        $product->setPrice((float)$concepto['ValorUnitario']);

        if (!$product->save()) {
            throw new Exception('Error al crear el producto: ' . $product->descripcion);
        }

        return $product;
    }

    private function generateUniqueReference(string $base): string
    {
        $reference = substr(preg_replace('/[^a-zA-Z0-9]/', '', $base), 0, 20);
        $product = new Producto();

        if (!$product->load($reference)) {
            return $reference;
        }

        $counter = 1;
        do {
            $newRef = $reference . '_' . $counter;
            if (!$product->load($newRef)) {
                return $newRef;
            }
            $counter++;
        } while ($counter < 100);

        return $reference . '_' . time();
    }

    private function linkSupplierProduct(array $concepto, Proveedor $supplier, Producto $product): void
    {
        if (empty($concepto['NoIdentificacion'])) {
            return;
        }

        $productSupplier = new ProductoProveedor();
        $where = [
            new DataBaseWhere('refproveedor', $concepto['NoIdentificacion']),
            new DataBaseWhere('codproveedor', $supplier->codproveedor)
        ];

        if ($productSupplier->loadWhere($where)) {
            return;
        }

        $productSupplier->codproveedor = $supplier->codproveedor;
        $productSupplier->refproveedor = $concepto['NoIdentificacion'];
        $productSupplier->referencia = $product->referencia;
        $productSupplier->precio = (float)$concepto['ValorUnitario'];

        $productSupplier->save();
    }

    private function saveMapping(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        array $concepto,
        Producto $product,
        MatchResult $matchResult
    ): void {
        if (empty($concepto['NoIdentificacion'])) {
            return;
        }

        $mapping = new ProductMapping();
        $mapping->codproveedor = $supplier->codproveedor;
        $mapping->idempresa = $cfdi->idempresa;
        $mapping->emisor_rfc = $cfdi->emisor_rfc;
        $mapping->cfdi_referencia = $concepto['NoIdentificacion'] ?? '';
        $mapping->referencia = $product->referencia;
        $mapping->match_method = $matchResult->matchMethod;
        $mapping->confidence = $matchResult->confidence;

        $this->mappingStorage->saveMapping($mapping);
    }

    private function getFormaPagoFromCfdi(CfdiProveedor $cfdi): string
    {
        $result = FormaPago::table()->whereEq('clavesat', $cfdi->forma_pago)->first();

        if ($result && !empty($result['codpago'])) {
            return $result['codpago'];
        }

        return 'CONTADO';
    }
}
