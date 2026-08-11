<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import;

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
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\Options\ImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\Options\InvoiceImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\Result\BatchResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\Result\InvoiceImportResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierInvoiceLineBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierInvoiceStateService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierProductLinkService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierProductResolver;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Status\StatusService;
use FacturaScripts\Plugins\FacturacionMexico\Model\RelacionCfdiProveedor;

class InvoiceImportService
{
    private SupplierProductLinkService $productImporter;
    private SupplierInvoiceStateService $invoiceStateService;
    private StatusService $cfdiStatusService;
    private SupplierProductResolver $productResolver;
    private SupplierInvoiceLineBuilder $lineBuilder;

    public function __construct()
    {
        $this->productImporter = new SupplierProductLinkService();
        $this->invoiceStateService = new SupplierInvoiceStateService();
        $this->cfdiStatusService = new StatusService();
        $this->productResolver = new SupplierProductResolver();
        $this->lineBuilder = new SupplierInvoiceLineBuilder();
    }

    public function importSingle(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        ?ImportOptions $options = null,
        ?array $submittedConceptos = null
    ): InvoiceImportResult {
        $options = $options ?? new ImportOptions();

        if (!empty($cfdi->idfactura)) {
            $existing = new FacturaProveedor();
            if ($existing->load($cfdi->idfactura)) {
                try {
                    $this->invoiceStateService->assertImportable($existing);
                } catch (Exception $e) {
                    return InvoiceImportResult::alreadyImported($e->getMessage());
                }
            }
        }

        $db = null;
        try {
            $db = new DataBase();
            $db->beginTransaction();

            $data = $this->getCfdiData($cfdi);
            $conceptos = $submittedConceptos ?? $data->conceptos;
            $isEgreso = strtoupper($cfdi->tipo) === 'E';

            $invoice = $this->createOrUpdateInvoice($cfdi, $supplier, $options);
            $this->clearInvoiceLines($invoice);

            $createdProducts = [];
            $linkedProducts = [];

            foreach ($conceptos as $concepto) {
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
            $this->saveCfdiRelations($cfdi, $data->relacionados);

            $db->commit();

            Tools::log('audit')->notice('supplier-cfdi-invoice-created', [
                '%uuid%' => $cfdi->uuid,
                '%invoice%' => $invoice->idfactura,
                '%type%' => $cfdi->tipo,
                '%supplier%' => $supplier->codproveedor,
            ]);

            return InvoiceImportResult::success(
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
            return InvoiceImportResult::failure($error !== '' ? $error : Tools::lang()->trans(
                'supplier-cfdi-import-failed'
            ));
        }
    }

    public function importBatch(
        array $cfdis,
        ?ImportOptions $options = null,
        ?callable $progressCallback = null
    ): BatchResult {
        $options = $options ?? new ImportOptions();
        $result = new BatchResult();
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

    private function getCfdiData(CfdiProveedor $cfdi): CfdiData
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
        ImportOptions $options
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
        $invoice->codpago = $this->getFormaPagoFromCfdi($cfdi, $options->invoice->codpago);
        $invoice->setDate($cfdi->emissionDate(), $cfdi->emissionTime());

        if (strtoupper($cfdi->tipo) === 'E') {
            $invoice->codserie = $this->getEgresoSerie($options->invoice);
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
        ImportOptions $options,
        bool $isEgreso = false
    ): array {
        $result = [
            'linked' => false,
            'created' => false,
            'referencia' => null,
        ];

        $resolution = $this->productResolver->resolve($concepto, $supplier, $options->product);
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
                $options->product->shouldUpdatePrices(),
                $invoice->coddivisa ?? ''
            );
            if (!$linkResult['ok']) {
                throw new Exception($linkResult['message']);
            }
            $this->lineBuilder->buildProductLine($invoice, $concepto, $product, $options->product, $options->tax, $isEgreso);
        } else {
            $this->lineBuilder->buildFreeLine($invoice, $concepto, $options->product, $options->tax, $isEgreso);
        }

        return $result;
    }

    private function getEgresoSerie(InvoiceImportOptions $options): string
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

    private function getFormaPagoFromCfdi(CfdiProveedor $cfdi, ?string $selectedCode = null): string
    {
        if (!empty($selectedCode)) {
            $result = FormaPago::table()->whereEq('codpago', $selectedCode)->first();
            if ($result && !empty($result['codpago'])) {
                return $result['codpago'];
            }

            throw new Exception('La forma de pago seleccionada no existe.');
        }

        $result = FormaPago::table()->whereEq('clavesat', $cfdi->forma_pago)->first();

        if ($result && !empty($result['codpago'])) {
            return $result['codpago'];
        }

        $result = FormaPago::table()->whereEq('codpago', $cfdi->forma_pago)->first();
        if ($result && !empty($result['codpago'])) {
            return $result['codpago'];
        }

        $paymentMethods = FormaPago::all([Where::eq('activa', true)], ['codpago' => 'ASC'], 0, 1);
        if (!empty($paymentMethods)) {
            return $paymentMethods[0]->codpago;
        }

        throw new Exception('No hay formas de pago activas para crear la factura del proveedor.');
    }
}
