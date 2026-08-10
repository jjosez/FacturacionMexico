<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Calculator;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\SupplierCfdiStatusService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierInvoiceStateService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\SupplierCfdiReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierInvoiceRepository;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierCfdiRelationRepository;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierInvoiceCatalogRepository;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierTransactionManager;

class SupplierInvoiceImportService
{
    private SupplierProductLinkService $productImporter;
    private SupplierInvoiceStateService $invoiceStateService;
    private SupplierCfdiStatusService $cfdiStatusService;
    private SupplierProductResolver $productResolver;
    private SupplierInvoiceLineBuilder $lineBuilder;
    private SupplierCfdiReader $cfdiReader;
    private SupplierInvoiceRepository $invoiceRepository;
    private SupplierCfdiRelationRepository $relationRepository;
    private SupplierInvoiceCatalogRepository $catalogRepository;
    private SupplierTransactionManager $transactionManager;

    public function __construct(
        ?SupplierProductLinkService $productImporter = null,
        ?SupplierProductResolver $productResolver = null,
        ?SupplierInvoiceLineBuilder $lineBuilder = null,
        ?SupplierInvoiceStateService $invoiceStateService = null,
        ?SupplierCfdiStatusService $cfdiStatusService = null,
        ?SupplierCfdiReader $cfdiReader = null,
        ?SupplierInvoiceRepository $invoiceRepository = null,
        ?SupplierCfdiRelationRepository $relationRepository = null,
        ?SupplierInvoiceCatalogRepository $catalogRepository = null,
        ?SupplierTransactionManager $transactionManager = null
    )
    {
        $this->productImporter = $productImporter ?? new SupplierProductLinkService();
        $this->invoiceStateService = $invoiceStateService ?? new SupplierInvoiceStateService();
        $this->cfdiStatusService = $cfdiStatusService ?? new SupplierCfdiStatusService();
        $this->productResolver = $productResolver ?? new SupplierProductResolver();
        $this->lineBuilder = $lineBuilder ?? new SupplierInvoiceLineBuilder();
        $this->cfdiReader = $cfdiReader ?? new SupplierCfdiReader();
        $this->invoiceRepository = $invoiceRepository ?? new SupplierInvoiceRepository();
        $this->relationRepository = $relationRepository ?? new SupplierCfdiRelationRepository();
        $this->catalogRepository = $catalogRepository ?? new SupplierInvoiceCatalogRepository();
        $this->transactionManager = $transactionManager ?? new SupplierTransactionManager();
    }

    public function importSingle(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        ?SupplierInvoiceImportOptions $options = null,
        ?array $submittedConceptos = null
    ): SupplierInvoiceImportResult {
        $options = $options ?? new SupplierInvoiceImportOptions();

        if (!empty($cfdi->idfactura)) {
            $existing = $this->invoiceRepository->findById((string)$cfdi->idfactura);
            if ($existing !== null) {
                try {
                    $this->invoiceStateService->assertImportable($existing);
                } catch (Exception $e) {
                    return SupplierInvoiceImportResult::alreadyImported($e->getMessage());
                }
            }
        }

        $transactionStarted = false;
        try {
            $this->transactionManager->begin();
            $transactionStarted = true;

            $reader = $this->cfdiReader->read($cfdi);
            $conceptos = $submittedConceptos ?? $reader->getConceptos();
            $isEgreso = strtoupper($cfdi->tipo) === 'E';

            $invoice = $this->createOrUpdateInvoice($cfdi, $supplier, $reader, $options);
            $this->invoiceRepository->clearLines($invoice);

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
            $this->invoiceRepository->save($invoice);

            if (!$this->cfdiStatusService->markInvoiceCreated($cfdi, $invoice)) {
                throw new Exception('No se pudo actualizar el CFDI con la factura generada');
            }
            $this->relationRepository->saveRelations($cfdi, $reader);

            $this->transactionManager->commit();
            $transactionStarted = false;

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
            if ($transactionStarted) {
                $this->transactionManager->rollback();
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

    private function createOrUpdateInvoice(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        CfdiQuickReader $reader,
        SupplierInvoiceImportOptions $options
    ): FacturaProveedor {
        $invoice = $this->invoiceRepository->findByNumberAndSupplier(
            $cfdi->invoiceNumber(),
            $supplier->codproveedor
        );

        if ($invoice !== null) {
            $this->invoiceStateService->assertImportable($invoice);
            return $invoice;
        }

        $invoice = $this->invoiceRepository->create();

        $invoice->setSubject($supplier);
        $invoice->numproveedor = $cfdi->invoiceNumber();
        $invoice->codpago = $this->catalogRepository->findPaymentCode($cfdi->forma_pago);
        $invoice->setDate($cfdi->emissionDate(), $cfdi->emissionTime());

        if (strtoupper($cfdi->tipo) === 'E') {
            $invoice->codserie = $this->catalogRepository->findRectifyingSeries($options->codserie);
        }

        $this->invoiceRepository->save($invoice, 'Error al crear la factura del proveedor');

        return $invoice;
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

}
