<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\Queue;

use Exception;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierCfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierInvoiceBatchResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierInvoiceImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierInvoiceImportService;

class SupplierCfdiAsyncImportProcessor
{
    private SupplierCfdiImportQueue $queue;
    private SupplierInvoiceImportService $importService;
    private SupplierCfdiImporter $cfdiImporter;
    private bool $shouldStop = false;

    public function __construct(
        ?SupplierInvoiceImportService $importService = null,
        ?SupplierCfdiImportQueue $queue = null
    ) {
        $this->importService = $importService ?? new SupplierInvoiceImportService();
        $this->queue = $queue ?? new SupplierCfdiImportQueue();
        $this->cfdiImporter = new SupplierCfdiImporter();
    }

    public function process(string $jobId): SupplierInvoiceBatchResult
    {
        $job = $this->queue->get($jobId);

        if ($job === null) {
            throw new Exception('Job no encontrado: ' . $jobId);
        }

        if (!$job->isPending()) {
            throw new Exception('Job no está en estado pending: ' . $job->status);
        }

        $this->queue->markAsProcessing($jobId);

        $result = new SupplierInvoiceBatchResult();

        try {
            $xmlFiles = $this->queue->extractZip($job->filePath);
            $result->setTotal(count($xmlFiles));

            $options = $this->getOptionsFromJob($job);

            foreach ($xmlFiles as $index => $filePath) {
                if ($this->shouldStop) {
                    $this->queue->fail($jobId, 'Procesamiento detenido');
                    break;
                }

                try {
                    $importResult = $this->processFile($filePath, $job->companyId, $options, $result);
                    if (!$importResult->success) {
                        throw new Exception($importResult->error ?? 'No se pudo importar el CFDI');
                    }
                    $result->addSuccess(basename($filePath), 0);
                } catch (Exception $e) {
                    $result->addFailure(basename($filePath), $e->getMessage());
                }

                $this->queue->updateProgress($jobId, $index + 1, count($xmlFiles));
            }

            if (!$result->hasErrors()) {
                $this->queue->complete($jobId, $result->toArray());
            } else {
                $this->queue->complete($jobId, $result->toArray());
            }
        } catch (Exception $e) {
            $this->queue->fail($jobId, $e->getMessage());
            $result->addFailure('', $e->getMessage());
        }

        return $result;
    }

    public function processNext(): ?SupplierInvoiceBatchResult
    {
        $job = $this->queue->dequeue();

        if ($job === null) {
            return null;
        }

        return $this->process((string)$job->id);
    }

    public function processAll(int $maxIterations = 100): int
    {
        $processed = 0;

        for ($i = 0; $i < $maxIterations; $i++) {
            $job = $this->queue->dequeue();

            if ($job === null) {
                break;
            }

            try {
                $this->process((string)$job->id);
                $processed++;
            } catch (Exception $e) {
                continue;
            }
        }

        return $processed;
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function getStats(): array
    {
        return $this->queue->getStats();
    }

    private function processFile(
        string $filePath,
        int $companyId,
        SupplierInvoiceImportOptions $options,
        SupplierInvoiceBatchResult $result
    ): \FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierInvoiceImportResult {
        $xmlContent = file_get_contents($filePath);

        if ($xmlContent === false) {
            throw new Exception('No se pudo leer el archivo: ' . $filePath);
        }

        $tempFile = sys_get_temp_dir() . '/' . basename($filePath);
        file_put_contents($tempFile, $xmlContent);

        $uploadedFile = new \FacturaScripts\Core\UploadedFile($tempFile, basename($filePath));

        try {
            $company = new \FacturaScripts\Dinamic\Model\Empresa();
            $company->load($companyId);

            $cfdi = $this->cfdiImporter->processUpload($uploadedFile, $company);

            return $this->importService->importSingle($cfdi, $cfdi->getSupplier(), $options);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function getOptionsFromJob(SupplierCfdiImportJob $job): SupplierInvoiceImportOptions
    {
        if (empty($job->result)) {
            return new SupplierInvoiceImportOptions();
        }

        $data = json_decode($job->result, true);

        if (!isset($data['options'])) {
            return new SupplierInvoiceImportOptions();
        }

        return SupplierInvoiceImportOptions::fromArray($data['options']);
    }
}
