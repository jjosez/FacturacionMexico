<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Queue;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\SupplierCfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiImportJob;
use ZipArchive;

class ImportQueue
{
    public const TABLE = 'cfdi_import_jobs';
    public const DEFAULT_TIMEOUT = 300;

    private DataBase $db;

    public function __construct()
    {
        $this->db = new DataBase();
        new CfdiImportJob();
    }

    public function enqueue(array $files, int $companyId, string $userNick, array $options = []): string
    {
        $zipPath = $this->createZipFromFiles($files);

        if ($zipPath === null) {
            throw new \Exception('Error al crear archivo ZIP para el job');
        }

        $job = new ImportJob();
        $job->companyId = $companyId;
        $job->userNick = $userNick;
        $job->filePath = $zipPath;
        $job->status = ImportJob::STATUS_PENDING;
        $job->config = json_encode($options);
        $job->createdAt = new \DateTime();

        if (!$this->save($job)) {
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
            throw new \Exception('No se pudo guardar el trabajo de importación.');
        }

        return (string)$job->id;
    }

    public function dequeue(): ?ImportJob
    {
        $sql = "SELECT * FROM " . self::TABLE
            . " WHERE status = " . $this->quote(ImportJob::STATUS_PENDING)
            . " ORDER BY created_at ASC LIMIT 1";

        $result = $this->db->select($sql);

        if (empty($result)) {
            return null;
        }

        return ImportJob::fromArray($result[0]);
    }

    public function get(string $jobId): ?ImportJob
    {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE id = " . (int)$jobId;

        $result = $this->db->select($sql);

        if (empty($result)) {
            return null;
        }

        return ImportJob::fromArray($result[0]);
    }

    public function getByUser(string $userNick, int $limit = 10): array
    {
        $sql = "SELECT * FROM " . self::TABLE
            . " WHERE nick = " . $this->quote($userNick)
            . " ORDER BY created_at DESC LIMIT " . max(1, $limit);

        $result = $this->db->select($sql);

        return array_map(fn($row) => ImportJob::fromArray($row), $result);
    }

    public function getStatus(string $jobId): ?array
    {
        $job = $this->get($jobId);

        if ($job === null) {
            return null;
        }

        return [
            'id' => $job->id,
            'status' => $job->status,
            'progress' => $job->progress,
            'total_items' => $job->totalItems,
            'processed_items' => $job->processedItems,
            'error' => $job->error,
            'result' => $job->result ? json_decode($job->result, true) : null,
            'created_at' => $job->createdAt?->format('c'),
            'processed_at' => $job->processedAt?->format('c'),
        ];
    }

    public function save(ImportJob $job): bool
    {
        if ($job->id > 0) {
            return $this->update($job);
        }

        return $this->insert($job);
    }

    public function updateProgress(string $jobId, int $processed, int $total): void
    {
        $progress = $total > 0 ? (int)(($processed / $total) * 100) : 0;

        $sql = "UPDATE " . self::TABLE
            . " SET progress = " . $progress
            . ", processed_items = " . $processed
            . ", total_items = " . $total
            . " WHERE id = " . (int)$jobId;

        $this->db->exec($sql);
    }

    public function markAsProcessing(string $jobId): bool
    {
        $lockToken = bin2hex(random_bytes(20));
        $sql = "UPDATE " . self::TABLE
            . " SET status = " . $this->quote(ImportJob::STATUS_PROCESSING)
            . ", lock_token = " . $this->quote($lockToken)
            . " WHERE id = " . (int)$jobId
            . " AND status = " . $this->quote(ImportJob::STATUS_PENDING);

        if (!$this->db->exec($sql)) {
            return false;
        }

        return $this->get($jobId)?->lockToken === $lockToken;
    }

    public function complete(string $jobId, array $result): void
    {
        $sql = "UPDATE " . self::TABLE
            . " SET status = " . $this->quote(ImportJob::STATUS_COMPLETED)
            . ", result = " . $this->quote(json_encode($result))
            . ", lock_token = NULL, progress = 100, processed_at = NOW()"
            . " WHERE id = " . (int)$jobId;

        $this->db->exec($sql);
    }

    public function fail(string $jobId, string $error): void
    {
        $sql = "UPDATE " . self::TABLE
            . " SET status = " . $this->quote(ImportJob::STATUS_FAILED)
            . ", error = " . $this->quote($error)
            . ", lock_token = NULL, processed_at = NOW()"
            . " WHERE id = " . (int)$jobId;

        $this->db->exec($sql);
    }

    public function delete(int $jobId): bool
    {
        $job = $this->get((string)$jobId);

        if ($job !== null && !empty($job->filePath) && file_exists($job->filePath)) {
            unlink($job->filePath);
        }

        $sql = "DELETE FROM " . self::TABLE . " WHERE id = " . $jobId;

        return $this->db->exec($sql);
    }

    public function cleanupOld(int $days = 7): int
    {
        $where = " WHERE status IN (" . $this->quote(ImportJob::STATUS_COMPLETED)
            . ", " . $this->quote(ImportJob::STATUS_FAILED) . ")"
            . " AND created_at < DATE_SUB(NOW(), INTERVAL " . max(1, $days) . " DAY)";
        $count = $this->db->select("SELECT COUNT(*) AS total FROM " . self::TABLE . $where);

        $this->db->exec("DELETE FROM " . self::TABLE . $where);

        return (int)($count[0]['total'] ?? 0);
    }

    public function getStats(): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM " . self::TABLE;

        $result = $this->db->select($sql);

        return $result[0] ?? [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
    }

    private function insert(ImportJob $job): bool
    {
        $sql = "INSERT INTO " . self::TABLE
            . " (idempresa, nick, status, file_path, config, progress, created_at)"
            . " VALUES (" . $job->companyId
            . ", " . $this->quote($job->userNick)
            . ", " . $this->quote($job->status)
            . ", " . $this->quote($job->filePath)
            . ", " . $this->quote($job->config)
            . ", " . $job->progress
            . ", NOW())";

        $result = $this->db->exec($sql);

        if ($result) {
            $job->id = (int)$this->db->lastval();
        }

        return $result;
    }

    private function update(ImportJob $job): bool
    {
        $sql = "UPDATE " . self::TABLE
            . " SET status = " . $this->quote($job->status)
            . ", config = " . $this->quote($job->config)
            . ", result = " . $this->quote($job->result)
            . ", error = " . $this->quote($job->error)
            . ", lock_token = " . $this->quote($job->lockToken)
            . ", progress = " . $job->progress
            . ", total_items = " . $job->totalItems
            . ", processed_items = " . $job->processedItems
            . ", processed_at = " . $this->quote($job->processedAt?->format('Y-m-d H:i:s'))
            . " WHERE id = " . $job->id;

        return $this->db->exec($sql);
    }

    private function createZipFromFiles(array $files): ?string
    {
        if (empty($files)) {
            return null;
        }

        $zipDir = FS_FOLDER . '/MyFiles/CFDI/jobs/';
        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zipName = 'import_' . date('Ymd_His') . '_' . uniqid() . '.zip';
        $zipPath = $zipDir . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return null;
        }

        $count = 0;
        $totalBytes = 0;
        try {
            foreach ($files as $file) {
                $uploads = is_array($file) ? $file : [$file];
                foreach ($uploads as $upload) {
                    if (!$upload instanceof \FacturaScripts\Core\UploadedFile) {
                        continue;
                    }

                    [$added, $bytes] = $this->addUploadToZip(
                        $zip,
                        $upload,
                        SupplierCfdiImporter::MAX_FILES - $count,
                        SupplierCfdiImporter::MAX_BYTES - $totalBytes
                    );
                    $count += $added;
                    $totalBytes += $bytes;
                }
            }
        } catch (\Throwable $e) {
            $zip->close();
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
            throw $e;
        }

        $zip->close();

        if ($count === 0) {
            unlink($zipPath);
            return null;
        }

        return $zipPath;
    }

    public function extractZip(string $zipPath): array
    {
        $extractDir = sys_get_temp_dir() . '/cfdi_import_' . uniqid();
        mkdir($extractDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [];
        }

        $xmlFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'xml') {
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    continue;
                }

                $path = $extractDir . '/' . uniqid() . '_' . basename($filename);
                if (file_put_contents($path, $content) !== false) {
                    $xmlFiles[] = $path;
                }
            }
        }

        $zip->close();

        return $xmlFiles;
    }

    private function addUploadToZip(
        ZipArchive $target,
        \FacturaScripts\Core\UploadedFile $file,
        int $maxFiles,
        int $maxBytes
    ): array
    {
        if (!$file->isValid()) {
            throw new \Exception('Uno de los archivos recibidos no es válido.');
        }

        if (strtolower($file->extension()) === 'xml') {
            if ($maxFiles < 1 || (int)$file->size > $maxBytes) {
                throw new \Exception('El lote excede los límites permitidos.');
            }
            $target->addFile($file->getPathname(), uniqid() . '_' . $file->getClientOriginalName());
            return [1, (int)$file->size];
        }

        if (strtolower($file->extension()) !== 'zip') {
            throw new \Exception('Solo se permiten archivos XML o ZIP.');
        }

        $source = new ZipArchive();
        if ($source->open($file->getPathname()) !== true) {
            throw new \Exception('No se pudo abrir uno de los archivos ZIP.');
        }

        $count = 0;
        $totalBytes = 0;
        try {
            for ($index = 0; $index < $source->numFiles; $index++) {
                $name = $source->getNameIndex($index);
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xml') {
                    continue;
                }

                $stat = $source->statIndex($index);
                $size = (int)($stat['size'] ?? 0);
                if ($count >= $maxFiles || $totalBytes + $size > $maxBytes) {
                    throw new \Exception('El contenido del ZIP excede los límites permitidos.');
                }

                $content = $source->getFromIndex($index);
                if ($content !== false) {
                    $target->addFromString(uniqid() . '_' . basename($name), $content);
                    $count++;
                    $totalBytes += strlen($content);
                }
            }
        } finally {
            $source->close();
        }

        return [$count, $totalBytes];
    }

    private function quote(?string $value): string
    {
        return $value === null ? 'NULL' : "'" . $this->db->escapeString($value) . "'";
    }
}
