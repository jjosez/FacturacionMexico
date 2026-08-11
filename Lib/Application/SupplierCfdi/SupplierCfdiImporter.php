<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi;

use Exception;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\Result\SupplierCfdiBatchResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\InvoiceImportService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options\ImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Register\CfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiAlreadyRegisteredException;
use Throwable;
use ZipArchive;

final class SupplierCfdiImporter
{
    public const MODE_REGISTER = 'register';
    public const MODE_REGISTER_AND_INVOICE = 'register_and_invoice';
    public const MAX_FILES = 50;
    public const SYNC_MAX_FILES = 10;
    public const MAX_BYTES = 10485760;

    private CfdiImporter $cfdiImporter;
    private InvoiceImportService $invoiceImporter;

    public function __construct(
        ?CfdiImporter $cfdiImporter = null,
        ?InvoiceImportService $invoiceImporter = null
    ) {
        $this->cfdiImporter = $cfdiImporter ?? new CfdiImporter();
        $this->invoiceImporter = $invoiceImporter ?? new InvoiceImportService();
    }

    public function import(
        array $files,
        Empresa $company,
        string $mode = self::MODE_REGISTER,
        ?ImportOptions $options = null,
        int $maxFiles = self::MAX_FILES,
        ?callable $progressCallback = null
    ): SupplierCfdiBatchResult {
        if (!in_array($mode, [self::MODE_REGISTER, self::MODE_REGISTER_AND_INVOICE], true)) {
            throw new Exception('Modo de importación no válido.');
        }

        $start = microtime(true);
        [$preparedFiles, $temporaryFiles] = $this->prepareFiles($files, $maxFiles);
        $result = new SupplierCfdiBatchResult();
        $result->total = count($preparedFiles);
        $options ??= new ImportOptions();

        try {
            foreach ($preparedFiles as $index => $file) {
                $fileName = $file->getClientOriginalName();

                try {
                    $cfdi = $this->cfdiImporter->processUpload($file, $company);

                    if ($mode === self::MODE_REGISTER) {
                        $result->addRegistered($cfdi, $fileName);
                    } else {
                        $invoiceResult = $this->invoiceImporter->importSingle($cfdi, $cfdi->getSupplier(), $options);
                        if ($invoiceResult->success && $invoiceResult->invoice !== null) {
                            $result->addInvoiceCreated($cfdi, (int)$invoiceResult->invoice->idfactura, $fileName);
                        } else {
                            $result->addInvoiceFailure(
                                $cfdi,
                                $fileName,
                                $invoiceResult->error ?? 'El CFDI fue registrado, pero no se pudo crear la factura.'
                            );
                        }
                    }
                } catch (CfdiAlreadyRegisteredException $e) {
                    $result->addFailure($fileName, $e->getMessage(), 'duplicate');
                } catch (Throwable $e) {
                    $result->addFailure($fileName, $e->getMessage());
                }

                if ($progressCallback !== null) {
                    $progressCallback($index + 1, count($preparedFiles));
                }
            }
        } finally {
            foreach ($temporaryFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        $result->elapsedSeconds = microtime(true) - $start;
        return $result;
    }

    private function prepareFiles(array $files, int $maxFiles): array
    {
        if ($files === []) {
            throw new Exception('No se recibieron archivos.');
        }

        $prepared = [];
        $temporary = [];
        $totalBytes = 0;

        try {
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    throw new Exception('Uno de los archivos recibidos no es válido.');
                }

                $totalBytes += (int)$file->size;
                if ($totalBytes > self::MAX_BYTES) {
                    throw new Exception('El lote excede el tamaño máximo de 10 MB.');
                }

                $extension = strtolower($file->extension());
                if ($extension === 'xml') {
                    $prepared[] = $file;
                } elseif ($extension === 'zip') {
                    [$zipFiles, $zipTemporary, $zipBytes] = $this->extractXmlFiles(
                        $file,
                        $maxFiles - count($prepared)
                    );
                    array_push($prepared, ...$zipFiles);
                    array_push($temporary, ...$zipTemporary);
                    $totalBytes += $zipBytes;
                } else {
                    throw new Exception('Solo se permiten archivos XML o ZIP.');
                }

                if (count($prepared) > $maxFiles) {
                    throw new Exception('El lote excede el máximo de ' . $maxFiles . ' CFDI.');
                }

                if ($totalBytes > self::MAX_BYTES) {
                    throw new Exception('El contenido del lote excede el tamaño máximo de 10 MB.');
                }
            }
        } catch (Throwable $e) {
            foreach ($temporary as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            throw $e;
        }

        if ($prepared === []) {
            throw new Exception('No se encontraron archivos XML en el lote.');
        }

        return [$prepared, $temporary];
    }

    private function extractXmlFiles(UploadedFile $file, int $maxFiles): array
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getPathname()) !== true) {
            throw new Exception('No se pudo abrir el archivo ZIP.');
        }

        $files = [];
        $temporary = [];
        $totalBytes = 0;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);
                if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'xml') {
                    continue;
                }

                if (count($files) >= $maxFiles) {
                    throw new Exception('El lote excede el máximo de archivos permitido.');
                }

                $stream = $zip->getStream($entryName);
                if ($stream === false) {
                    throw new Exception('No se pudo leer ' . basename($entryName) . ' del ZIP.');
                }

                $path = tempnam(sys_get_temp_dir(), 'supplier-cfdi-');
                $remainingBytes = self::MAX_BYTES - $totalBytes;
                $content = stream_get_contents($stream, $remainingBytes + 1);
                fclose($stream);

                if ($path === false || $content === false) {
                    throw new Exception('No se pudo preparar un XML del archivo ZIP.');
                }

                $temporary[] = $path;
                if (file_put_contents($path, $content) === false) {
                    throw new Exception('No se pudo preparar un XML del archivo ZIP.');
                }

                $totalBytes += strlen($content);
                if ($totalBytes > self::MAX_BYTES) {
                    throw new Exception('El contenido del ZIP excede el tamaño máximo de 10 MB.');
                }

                $upload = new UploadedFile([
                    'error' => UPLOAD_ERR_OK,
                    'name' => basename($entryName),
                    'size' => strlen($content),
                    'tmp_name' => $path,
                    'type' => 'application/xml',
                ]);
                $upload->test = true;
                $files[] = $upload;
            }
        } catch (Throwable $e) {
            foreach ($temporary as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            throw $e;
        } finally {
            $zip->close();
        }

        return [$files, $temporary, $totalBytes];
    }
}
