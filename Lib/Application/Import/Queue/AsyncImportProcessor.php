<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import\Queue;

use Exception;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\CfdiSupplierImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import\BatchImportResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import\ImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import\SupplierCfdiImportService;

class AsyncImportProcessor
{
    private CfdiImportQueue $queue;
    private SupplierCfdiImportService $importService;
    private CfdiSupplierImporter $cfdiImporter;
    private bool $shouldStop = false;

    public function __construct(
        ?SupplierCfdiImportService $importService = null,
        ?CfdiImportQueue $queue = null
    ) {
        $this->importService = $importService ?? new SupplierCfdiImportService();
        $this->queue = $queue ?? new CfdiImportQueue();
        $this->cfdiImporter = new CfdiSupplierImporter();
    }

    public function process(string $jobId): BatchImportResult
    {
        $job = $this->queue->get($jobId);

        if ($job === null) {
            throw new Exception('Job no encontrado: ' . $jobId);
        }

        if (!$job->isPending()) {
            throw new Exception('Job no está en estado pending: ' . $job->status);
        }

        $this->queue->markAsProcessing($jobId);

        $result = new BatchImportResult();

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
                    $this->processFile($filePath, $job->companyId, $options, $result);
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

    public function processNext(): ?BatchImportResult
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
        ImportOptions $options,
        BatchImportResult $result
    ): void {
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

            $this->importService->importSingle($cfdi, $cfdi->getSupplier(), $options);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function getOptionsFromJob(CfdiImportJob $job): ImportOptions
    {
        if (empty($job->result)) {
            return new ImportOptions();
        }

        $data = json_decode($job->result, true);

        if (!isset($data['options'])) {
            return new ImportOptions();
        }

        return ImportOptions::fromArray($data['options']);
    }
}
