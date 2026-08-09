<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import\Queue;

use FacturaScripts\Core\Base\DataBase;
use ZipArchive;

class SupplierCfdiImportQueue
{
    public const TABLE = 'cfdi_import_jobs';
    public const DEFAULT_TIMEOUT = 300;

    private DataBase $db;

    public function __construct()
    {
        $this->db = new DataBase();
    }

    public function enqueue(array $files, int $companyId, int $userId, array $options = []): string
    {
        $zipPath = $this->createZipFromFiles($files);

        if ($zipPath === null) {
            throw new \Exception('Error al crear archivo ZIP para el job');
        }

        $job = new SupplierCfdiImportJob();
        $job->companyId = $companyId;
        $job->userId = $userId;
        $job->filePath = $zipPath;
        $job->status = SupplierCfdiImportJob::STATUS_PENDING;
        $job->result = json_encode(['options' => $options]);
        $job->createdAt = new \DateTime();

        $this->save($job);

        return (string)$job->id;
    }

    public function dequeue(): ?SupplierCfdiImportJob
    {
        $sql = "SELECT * FROM " . self::TABLE
            . " WHERE status = ? ORDER BY created_at ASC LIMIT 1";

        $result = $this->db->select($sql, [SupplierCfdiImportJob::STATUS_PENDING]);

        if (empty($result)) {
            return null;
        }

        return SupplierCfdiImportJob::fromArray($result[0]);
    }

    public function get(string $jobId): ?SupplierCfdiImportJob
    {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE id = ?";

        $result = $this->db->select($sql, [(int)$jobId]);

        if (empty($result)) {
            return null;
        }

        return SupplierCfdiImportJob::fromArray($result[0]);
    }

    public function getByUser(int $userId, int $limit = 10): array
    {
        $sql = "SELECT * FROM " . self::TABLE
            . " WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";

        $result = $this->db->select($sql, [$userId, $limit]);

        return array_map(fn($row) => SupplierCfdiImportJob::fromArray($row), $result);
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

    public function save(SupplierCfdiImportJob $job): bool
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
            . " SET progress = ?, processed_items = ?, total_items = ?"
            . " WHERE id = ?";

        $this->db->exec($sql, [$progress, $processed, $total, (int)$jobId]);
    }

    public function markAsProcessing(string $jobId): void
    {
        $sql = "UPDATE " . self::TABLE
            . " SET status = ? WHERE id = ? AND status = ?";

        $this->db->exec($sql, [SupplierCfdiImportJob::STATUS_PROCESSING, (int)$jobId, SupplierCfdiImportJob::STATUS_PENDING]);
    }

    public function complete(string $jobId, array $result): void
    {
        $sql = "UPDATE " . self::TABLE
            . " SET status = ?, result = ?, progress = 100, processed_at = NOW()"
            . " WHERE id = ?";

        $this->db->exec($sql, [
            SupplierCfdiImportJob::STATUS_COMPLETED,
            json_encode($result),
            (int)$jobId
        ]);
    }

    public function fail(string $jobId, string $error): void
    {
        $sql = "UPDATE " . self::TABLE
            . " SET status = ?, error = ?, processed_at = NOW()"
            . " WHERE id = ?";

        $this->db->exec($sql, [SupplierCfdiImportJob::STATUS_FAILED, $error, (int)$jobId]);
    }

    public function delete(int $jobId): bool
    {
        $job = $this->get((string)$jobId);

        if ($job !== null && !empty($job->filePath) && file_exists($job->filePath)) {
            unlink($job->filePath);
        }

        $sql = "DELETE FROM " . self::TABLE . " WHERE id = ?";

        return $this->db->exec($sql, [(int)$jobId]);
    }

    public function cleanupOld(int $days = 7): int
    {
        $sql = "DELETE FROM " . self::TABLE
            . " WHERE status IN (?, ?)"
            . " AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $this->db->exec($sql, [
            SupplierCfdiImportJob::STATUS_COMPLETED,
            SupplierCfdiImportJob::STATUS_FAILED,
            $days
        ]);

        return $this->db->getAffectedRows();
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

    private function insert(SupplierCfdiImportJob $job): bool
    {
        $sql = "INSERT INTO " . self::TABLE
            . " (company_id, user_id, status, file_path, progress, created_at)"
            . " VALUES (?, ?, ?, ?, ?, NOW())";

        $result = $this->db->exec($sql, [
            $job->companyId,
            $job->userId,
            $job->status,
            $job->filePath,
            $job->progress,
        ]);

        if ($result) {
            $job->id = $this->db->getLastIdentity();
        }

        return $result;
    }

    private function update(SupplierCfdiImportJob $job): bool
    {
        $sql = "UPDATE " . self::TABLE
            . " SET status = ?, result = ?, error = ?, progress = ?,"
            . " total_items = ?, processed_items = ?, processed_at = ?"
            . " WHERE id = ?";

        return $this->db->exec($sql, [
            $job->status,
            $job->result,
            $job->error,
            $job->progress,
            $job->totalItems,
            $job->processedItems,
            $job->processedAt?->format('Y-m-d H:i:s'),
            $job->id,
        ]);
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

        foreach ($files as $file) {
            if ($file instanceof \FacturaScripts\Core\UploadedFile) {
                if ($file->isValid()) {
                    $zip->addFile($file->getPathname(), $file->getClientOriginalName());
                }
            } elseif (is_array($file)) {
                foreach ($file as $f) {
                    if ($f instanceof \FacturaScripts\Core\UploadedFile && $f->isValid()) {
                        $zip->addFile($f->getPathname(), $f->getClientOriginalName());
                    }
                }
            }
        }

        $zip->close();

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
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'xml') {
                $zip->extractTo($extractDir, $filename);
                $xmlFiles[] = $extractDir . '/' . $filename;
            }
        }

        $zip->close();

        return $xmlFiles;
    }
}
