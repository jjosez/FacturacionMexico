<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Calculator;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiParsedData;
use FacturaScripts\Plugins\FacturacionMexico\Model\RelacionCfdiProveedor;

class SupplierInvoiceImportService
{
    private SupplierProductLinkService $productImporter;
    private SupplierInvoiceStateService $invoiceStateService;
    private SupplierCfdiStatusService $cfdiStatusService;
    private SupplierProductResolver $productResolver;
    private SupplierInvoiceLineBuilder $lineBuilder;

    public function __construct()
    {
        $this->productImporter = new SupplierProductLinkService();
        $this->invoiceStateService = new SupplierInvoiceStateService();
        $this->cfdiStatusService = new SupplierCfdiStatusService();
        $this->productResolver = new SupplierProductResolver();
        $this->lineBuilder = new SupplierInvoiceLineBuilder();
    }

    public function importSingle(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        ?SupplierInvoiceImportOptions $options = null,
        ?array $submittedConceptos = null
    ): SupplierInvoiceImportResult {
        $options = $options ?? new SupplierInvoiceImportOptions();

        if (!empty($cfdi->idfactura)) {
            $existing = new FacturaProveedor();
            if ($existing->load($cfdi->idfactura)) {
                try {
                    $this->invoiceStateService->assertImportable($existing);
                } catch (Exception $e) {
                    return SupplierInvoiceImportResult::alreadyImported($e->getMessage());
                }
            }
        }

        $db = null;
        try {
            $db = new DataBase();
            $db->beginTransaction();

            $data = $this->getCfdiData($cfdi);
            $conceptos = $submittedConceptos ?? $data->concepts;
            $isEgreso = strtoupper($cfdi->tipo) === 'E';

            $invoice = $this->createOrUpdateInvoice($cfdi, $supplier, $options);
            $this->clearInvoiceLines($invoice);

            $createdProducts = [];
            $linkedProducts = [];

            foreach ($conceptos as $index => $concepto) {
                $result = $this->processConcept(
                    $concepto,
                    $invoice,
                    $supplier,
                    $options,
                    $isEgreso
                );

                if ($result['linked']) {
                    $linkedProducts[] = $result['referencia'];
                } elseif ($result['created']) {
                    $createdProducts[] = $result['referencia'];
                }
            }

            $lines = $invoice->getLines();
            Calculator::calculate($invoice, $lines, true);
            $invoiceSaved = $invoice->save();
            if (!$invoiceSaved) {
                throw new Exception('Error al guardar la factura del proveedor');
            }

            if (!$this->cfdiStatusService->markInvoiceCreated($cfdi, $invoice)) {
                throw new Exception('No se pudo actualizar el CFDI con la factura generada');
            }
            $this->saveCfdiRelations($cfdi, $data->relations);

            $db->commit();

            Tools::log('audit')->notice('supplier-cfdi-invoice-created', [
                '%uuid%' => $cfdi->uuid,
                '%invoice%' => $invoice->idfactura,
                '%type%' => $cfdi->tipo,
                '%supplier%' => $supplier->codproveedor,
            ]);

            return SupplierInvoiceImportResult::success(
                $invoice,
                count($conceptos),
                count($linkedProducts),
                $createdProducts,
                $linkedProducts
            );
        } catch (Exception $e) {
            if ($db !== null) {
                $db->rollBack();
            }

            $error = trim($e->getMessage());
            return SupplierInvoiceImportResult::failure($error !== '' ? $error : Tools::lang()->trans(
                'supplier-cfdi-import-failed'
            ));
        }
    }

    public function importBatch(
        array $cfdis,
        ?SupplierInvoiceImportOptions $options = null,
        ?callable $progressCallback = null
    ): SupplierInvoiceBatchResult {
        $options = $options ?? new SupplierInvoiceImportOptions();
        $result = new SupplierInvoiceBatchResult();
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

    private function getCfdiData(CfdiProveedor $cfdi): CfdiParsedData
    {
        $xml = $cfdi->localFileContent();
        if (empty($xml)) {
            throw new Exception('No se pudo leer el archivo XML del CFDI');
        }
        return (new CfdiParser($xml))->parse();
    }

    private function createOrUpdateInvoice(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        SupplierInvoiceImportOptions $options
    ): FacturaProveedor {
        $invoice = new FacturaProveedor();
        $where = [
            Where::eq('numproveedor', $cfdi->invoiceNumber()),
            Where::eq('codproveedor', $supplier->codproveedor)
        ];

        if ($invoice->loadWhere($where)) {
            $this->invoiceStateService->assertImportable($invoice);
            return $invoice;
        }

        $invoice->setSubject($supplier);
        $invoice->numproveedor = $cfdi->invoiceNumber();
        $invoice->codpago = $this->getFormaPagoFromCfdi($cfdi);
        $invoice->setDate($cfdi->emissionDate(), $cfdi->emissionTime());

        if (strtoupper($cfdi->tipo) === 'E') {
            $invoice->codserie = $this->getEgresoSerie($options);
        }

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
        SupplierInvoiceImportOptions $options,
        bool $isEgreso = false
    ): array {
        $result = [
            'linked' => false,
            'created' => false,
            'referencia' => null,
        ];

        $resolution = $this->productResolver->resolve($concepto, $supplier, $options);
        $product = $resolution['product'];
        $result['created'] = $resolution['created'];
        $result['linked'] = $resolution['linked'];
        $result['referencia'] = $product?->referencia;

        if ($product !== null) {
            $linkResult = $this->productImporter->vincular(
                $product->referencia,
                $supplier->codproveedor,
                $concepto['NoIdentificacion'] ?? '',
                (float)($concepto['ValorUnitario'] ?? 0),
                0.0,
                0.0,
                0.0,
                $options->shouldUpdatePrices(),
                $invoice->coddivisa ?? ''
            );
            if (!$linkResult['ok']) {
                throw new Exception($linkResult['message']);
            }
            $this->lineBuilder->buildProductLine($invoice, $concepto, $product, $options, $isEgreso);
        } else {
            $this->lineBuilder->buildFreeLine($invoice, $concepto, $options, $isEgreso);
        }

        return $result;
    }

    private function getEgresoSerie(SupplierInvoiceImportOptions $options): string
    {
        $serieCode = $options->codserie ?? CfdiSettings::serieEgresoProveedor();
        $serie = new Serie();

        if (!empty($serieCode) && $serie->load($serieCode) && $serie->tipo === 'R') {
            return $serie->codserie;
        }

        $series = Serie::all([Where::eq('tipo', 'R')], ['codserie' => 'ASC'], 0, 1);
        if (!empty($series)) {
            return $series[0]->codserie;
        }

        throw new Exception(Tools::lang()->trans('supplier-cfdi-rectifying-series-missing'));
    }

    private function saveCfdiRelations(CfdiProveedor $cfdi, array $relations): void
    {
        foreach ($relations as $group) {
            $tipoRelacion = $group['tiporelacion'] ?? '';
            foreach ($group['relacionados'] ?? [] as $uuidRelacionado) {
                $related = new CfdiProveedor();
                if (!$related->loadFromUuid($uuidRelacionado)) {
                    continue;
                }

                $relation = new RelacionCfdiProveedor();
                $exists = $relation->loadWhere([
                    Where::eq('cfdi_id', $cfdi->id),
                    Where::eq('cfdi_id_relacionado', $related->id),
                    Where::eq('tipo_relacion', $tipoRelacion),
                ]);

                if ($exists) {
                    continue;
                }

                $relation->cfdi_id = $cfdi->id;
                $relation->cfdi_id_relacionado = $related->id;
                $relation->tipo_relacion = $tipoRelacion;
                $relation->uuid = $cfdi->uuid;
                $relation->uuid_relacionado = $uuidRelacionado;

                if (!$relation->save()) {
                    throw new Exception('No se pudo guardar la relación del CFDI de egreso');
                }
            }
        }
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
