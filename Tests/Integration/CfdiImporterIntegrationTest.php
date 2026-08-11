<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests\Integration;

use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Register\CfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\SupplierCfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\InvoiceImportService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options\ImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Result\InvoiceImportResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Queue\ImportQueue;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Queue\AsyncImportProcessor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiScope;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\Storage\CfdiStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\Storage\CfdiStorageInterface;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class CfdiImporterIntegrationTest extends TestCase
{
    use LogErrorsTrait;

    private ?CfdiProveedor $cfdi = null;
    private ?Proveedor $supplier = null;
    private string $supplierRfc;
    private string $uuid;
    private string $storageType;

    protected function setUp(): void
    {
        $this->storageType = Tools::settings('cfdi', 'storage-type', 'file');
        Tools::settingsSet('cfdi', 'storage-type', 'database');
    }

    public function testImportsMetadataAndXml(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $company = Empresas::default();
        $this->assertTrue($company->exists());

        $this->cfdi = (new CfdiImporter())->processUpload(
            $this->upload($this->uuid),
            $company
        );

        $this->assertTrue($this->cfdi->exists());
        $this->assertSame(strtoupper($this->uuid), $this->cfdi->uuid);
        $this->assertSame('MXN', $this->cfdi->coddivisa);
        $this->assertSame('I', $this->cfdi->tipo);
        $this->assertEmpty($this->cfdi->filename);
        $this->assertNotSame('', $this->cfdi->localFileContent());
        $this->assertNotNull(CfdiStorage::get()->get(CfdiScope::SUPPLIER, $this->cfdi->uuid));

        $this->supplier = $this->cfdi->getSupplier();
        $this->assertTrue($this->supplier->exists());
        $this->assertSame($this->supplierRfc, $this->supplier->cifnif);
    }

    public function testStorageFailureRollsBackCfdiAndNewSupplier(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $company = Empresas::default();

        try {
            (new CfdiImporter(new SupplierFailingStorage()))->processUpload(
                $this->upload($this->uuid),
                $company
            );
            $this->fail('La importación debía fallar al guardar el XML.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Storage test failure.', $e->getMessage());
        }

        $cfdi = new CfdiProveedor();
        $this->assertFalse($cfdi->loadFromUuid(strtoupper($this->uuid)));

        $supplier = new Proveedor();
        $this->assertFalse($supplier->loadFromCode('', [
            new DataBaseWhere('cifnif', $this->supplierRfc),
        ]));
    }

    public function testImportDoesNotCommitAnOuterTransaction(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $dataBase = new DataBase();
        $this->assertTrue($dataBase->beginTransaction());

        try {
            (new CfdiImporter(new SupplierFailingStorage()))->processUpload(
                $this->upload($this->uuid),
                Empresas::default()
            );
            $this->fail('La importación debía rechazar la transacción externa.');
        } catch (\Exception $e) {
            $this->assertSame(
                'No se puede registrar un CFDI de proveedor dentro de otra transacción.',
                $e->getMessage()
            );
            $this->assertTrue($dataBase->inTransaction());
        } finally {
            if ($dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }
    }

    public function testBatchReportsDuplicateSeparately(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $company = Empresas::default();
        $importer = new SupplierCfdiImporter();

        $first = $importer->import([$this->upload($this->uuid)], $company);
        $second = $importer->import([$this->upload($this->uuid)], $company);

        $this->assertSame('registered', $first->items[0]['status']);
        $this->assertSame('duplicate', $second->items[0]['status']);
        $this->assertSame(1, $second->failed);

        $this->cfdi = new CfdiProveedor();
        $this->assertTrue($this->cfdi->loadFromUuid(strtoupper($this->uuid)));
        $this->supplier = $this->cfdi->getSupplier();
    }

    public function testBatchKeepsRegisteredCfdiWhenInvoiceCreationFails(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $company = Empresas::default();
        $invoiceImporter = new class extends InvoiceImportService {
            public function __construct()
            {
            }

            public function importSingle(
                CfdiProveedor $cfdi,
                Proveedor $supplier,
                ?ImportOptions $options = null,
                ?array $submittedConceptos = null
            ): InvoiceImportResult {
                return InvoiceImportResult::failure('Fallo controlado al crear factura.');
            }
        };
        $importer = new SupplierCfdiImporter(null, $invoiceImporter);

        $result = $importer->import(
            [$this->upload($this->uuid)],
            $company,
            SupplierCfdiImporter::MODE_REGISTER_AND_INVOICE
        );

        $this->assertSame(1, $result->registered);
        $this->assertSame(1, $result->partial);
        $this->assertSame(0, $result->failed);
        $this->assertSame('invoice_failed', $result->items[0]['status']);

        $this->cfdi = new CfdiProveedor();
        $this->assertTrue($this->cfdi->loadFromUuid(strtoupper($this->uuid)));
        $this->assertEmpty($this->cfdi->idfactura);
        $this->supplier = $this->cfdi->getSupplier();
    }

    public function testQueuePersistsOwnerModeAndOptions(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $company = Empresas::default();
        $queue = new ImportQueue();
        $users = User::all([], [], 0, 1);
        $this->assertNotEmpty($users);
        $nick = (string)$users[0]->nick;
        $config = [
            'mode' => SupplierCfdiImporter::MODE_REGISTER_AND_INVOICE,
            'options' => ['product' => ['product_action' => 'auto']],
        ];

        $jobId = $queue->enqueue(
            [$this->upload($this->uuid)],
            (int)$company->idempresa,
            $nick,
            $config
        );

        try {
            $job = $queue->get($jobId);
            $this->assertNotNull($job);
            $this->assertSame($nick, $job->userNick);
            $this->assertSame((int)$company->idempresa, $job->companyId);
            $this->assertSame($config, json_decode($job->config, true));
        } finally {
            $queue->delete((int)$jobId);
        }
    }

    public function testBatchImportsXmlFromZip(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $upload = $this->zipUpload($this->uuid);

        try {
            $result = (new SupplierCfdiImporter())->import([$upload], Empresas::default());
            $this->assertSame(1, $result->registered);
            $this->assertSame('registered', $result->items[0]['status']);
        } finally {
            if (is_file($upload->getPathname())) {
                unlink($upload->getPathname());
            }
        }

        $this->cfdi = new CfdiProveedor();
        $this->assertTrue($this->cfdi->loadFromUuid(strtoupper($this->uuid)));
        $this->supplier = $this->cfdi->getSupplier();
    }

    public function testQueuedBatchUsesTheSameRegistrationFlow(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $company = Empresas::default();
        $users = User::all([], [], 0, 1);
        $queue = new ImportQueue();
        $jobId = $queue->enqueue(
            [$this->upload($this->uuid)],
            (int)$company->idempresa,
            (string)$users[0]->nick,
            ['mode' => SupplierCfdiImporter::MODE_REGISTER, 'options' => []]
        );

        try {
            $result = (new AsyncImportProcessor(null, $queue))->process($jobId);
            $this->assertSame(1, $result->registered);
            $this->assertTrue($queue->get($jobId)->isCompleted());
        } finally {
            $queue->delete((int)$jobId);
        }

        $this->cfdi = new CfdiProveedor();
        $this->assertTrue($this->cfdi->loadFromUuid(strtoupper($this->uuid)));
        $this->supplier = $this->cfdi->getSupplier();
    }

    protected function tearDown(): void
    {
        if ($this->cfdi !== null) {
            CfdiStorage::get()->delete(CfdiScope::SUPPLIER, $this->cfdi->uuid);
            $this->cfdi->delete();
        }

        if ($this->supplier !== null) {
            $this->supplier->delete();
        }

        Tools::settingsSet('cfdi', 'storage-type', $this->storageType);

        $this->logErrors();
    }

    private function upload(string $uuid): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cfdi-');
        file_put_contents($path, $this->xml($uuid));

        $upload = new UploadedFile([
            'error' => UPLOAD_ERR_OK,
            'name' => 'supplier-' . $uuid . '.xml',
            'size' => filesize($path),
            'tmp_name' => $path,
        ]);
        $upload->test = true;

        return $upload;
    }

    private function zipUpload(string $uuid): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cfdi-zip-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('supplier-' . $uuid . '.xml', $this->xml($uuid));
        $zip->close();

        $upload = new UploadedFile([
            'error' => UPLOAD_ERR_OK,
            'name' => 'supplier-cfdi.zip',
            'size' => filesize($path),
            'tmp_name' => $path,
            'type' => 'application/zip',
        ]);
        $upload->test = true;
        return $upload;
    }

    private function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20);
    }

    private function xml(string $uuid): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-08-10T12:00:00" Moneda="MXN" TipoDeComprobante="I" SubTotal="100.00" Total="116.00" LugarExpedicion="01000" FormaPago="01" MetodoPago="PUE" Serie="A" Folio="123">
    <cfdi:Emisor Rfc="$this->supplierRfc" Nombre="Proveedor de prueba" RegimenFiscal="601" />
    <cfdi:Receptor Rfc="BBB010101BBB" Nombre="Receptor de prueba" DomicilioFiscalReceptor="01000" RegimenFiscalReceptor="601" UsoCFDI="G03" />
    <cfdi:Conceptos><cfdi:Concepto ClaveProdServ="01010101" Cantidad="1" ClaveUnidad="H87" Descripcion="Producto de prueba" ValorUnitario="100.00" Importe="100.00" /></cfdi:Conceptos>
    <cfdi:Complemento><tfd:TimbreFiscalDigital Version="1.1" UUID="$uuid" FechaTimbrado="2026-08-10T12:01:00" RfcProvCertif="$this->supplierRfc" SelloCFD="abc" NoCertificadoSAT="00001000000504465028" SelloSAT="def" /></cfdi:Complemento>
</cfdi:Comprobante>
XML;
    }
}

final class SupplierFailingStorage implements CfdiStorageInterface
{
    public function save(CfdiScope $scope, string $uuid, string $xml): string
    {
        throw new \RuntimeException('Storage test failure.');
    }

    public function get(CfdiScope $scope, string $uuid): ?string
    {
        return null;
    }

    public function exists(CfdiScope $scope, string $uuid): bool
    {
        return false;
    }

    public function delete(CfdiScope $scope, string $uuid): bool
    {
        return true;
    }
}
