<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Queue;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\Result\SupplierCfdiBatchResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\SupplierCfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options\ImportOptions;
use Throwable;

class AsyncImportProcessor
{
    private ImportQueue $queue;
    private SupplierCfdiImporter $importer;
    private bool $shouldStop = false;

    public function __construct(
        ?SupplierCfdiImporter $importer = null,
        ?ImportQueue $queue = null
    ) {
        $this->importer = $importer ?? new SupplierCfdiImporter();
        $this->queue = $queue ?? new ImportQueue();
    }

    public function process(string $jobId): SupplierCfdiBatchResult
    {
        $job = $this->queue->get($jobId);

        if ($job === null) {
            throw new Exception('Job no encontrado: ' . $jobId);
        }

        if (!$job->isPending()) {
            throw new Exception('Job no está en estado pending: ' . $job->status);
        }

        if (!$this->queue->markAsProcessing($jobId)) {
            throw new Exception('El trabajo ya está siendo procesado.');
        }

        $result = new SupplierCfdiBatchResult();
        $xmlFiles = [];

        try {
            $xmlFiles = $this->queue->extractZip($job->filePath);
            if ($xmlFiles === []) {
                throw new Exception('El trabajo no contiene archivos XML.');
            }

            $company = new Empresa();
            if (!$company->load($job->companyId)) {
                throw new Exception('No se encontró la empresa del trabajo.');
            }

            $uploads = array_map([$this, 'uploadedFile'], $xmlFiles);
            $config = $this->getConfigFromJob($job);
            $result = $this->importer->import(
                $uploads,
                $company,
                $config['mode'],
                $config['options'],
                SupplierCfdiImporter::MAX_FILES,
                function (int $processed, int $total) use ($jobId): void {
                    if ($this->shouldStop) {
                        throw new Exception('Procesamiento detenido.');
                    }
                    $this->queue->updateProgress($jobId, $processed, $total);
                }
            );
            $this->queue->complete($jobId, $result->toArray());
        } catch (Throwable $e) {
            $this->queue->fail($jobId, $e->getMessage());
            $result->addFailure('', $e->getMessage());
        } finally {
            if ($xmlFiles !== []) {
                Tools::folderDelete(dirname($xmlFiles[0]));
            }
            if (is_file($job->filePath)) {
                unlink($job->filePath);
            }
        }

        return $result;
    }

    public function processNext(): ?SupplierCfdiBatchResult
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
            } catch (Throwable $e) {
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

    private function getConfigFromJob(ImportJob $job): array
    {
        if (empty($job->config)) {
            return [
                'mode' => SupplierCfdiImporter::MODE_REGISTER,
                'options' => new ImportOptions(),
            ];
        }

        $data = json_decode($job->config, true);
        return [
            'mode' => $data['mode'] ?? SupplierCfdiImporter::MODE_REGISTER,
            'options' => ImportOptions::fromArray($data['options'] ?? []),
        ];
    }

    private function uploadedFile(string $path): UploadedFile
    {
        $file = new UploadedFile([
            'error' => UPLOAD_ERR_OK,
            'name' => basename($path),
            'size' => filesize($path),
            'tmp_name' => $path,
            'type' => 'application/xml',
        ]);
        $file->test = true;
        return $file;
    }
}
