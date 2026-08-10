<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\Queue;

use Exception;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierInvoiceImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierInvoiceImportResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierInvoiceImportService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\SupplierCfdiUploadService;

final class SupplierCfdiQueuedFileImporter
{
    private SupplierInvoiceImportService $importService;
    private SupplierCfdiUploadService $cfdiImporter;

    public function __construct(
        ?SupplierInvoiceImportService $importService = null,
        ?SupplierCfdiUploadService $cfdiImporter = null
    ) {
        $this->importService = $importService ?? new SupplierInvoiceImportService();
        $this->cfdiImporter = $cfdiImporter ?? new SupplierCfdiUploadService();
    }

    public function import(
        string $filePath,
        int $companyId,
        SupplierInvoiceImportOptions $options
    ): SupplierInvoiceImportResult {
        $xmlContent = file_get_contents($filePath);
        if ($xmlContent === false) {
            throw new Exception('No se pudo leer el archivo: ' . $filePath);
        }

        $tempFile = sys_get_temp_dir() . '/' . basename($filePath);
        file_put_contents($tempFile, $xmlContent);
        $uploadedFile = new UploadedFile($tempFile, basename($filePath));

        try {
            $company = new Empresa();
            $company->load($companyId);
            $cfdi = $this->cfdiImporter->processUpload($uploadedFile, $company);

            return $this->importService->importSingle($cfdi, $cfdi->getSupplier(), $options);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
