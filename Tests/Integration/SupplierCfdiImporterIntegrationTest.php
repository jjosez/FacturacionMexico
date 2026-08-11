<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests\Integration;

use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier\SupplierCfdiImporter;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class SupplierCfdiImporterIntegrationTest extends TestCase
{
    use LogErrorsTrait;

    private ?CfdiProveedor $cfdi = null;
    private ?Proveedor $supplier = null;
    private string $supplierRfc;
    private string $uuid;

    public function testImportsMetadataAndXml(): void
    {
        $this->uuid = $this->uuid();
        $this->supplierRfc = 'X' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $company = Empresas::default();
        $this->assertTrue($company->exists());

        $this->cfdi = (new SupplierCfdiImporter())->processUpload(
            $this->upload($this->uuid),
            $company
        );

        $this->assertTrue($this->cfdi->exists());
        $this->assertSame(strtoupper($this->uuid), $this->cfdi->uuid);
        $this->assertSame('MXN', $this->cfdi->coddivisa);
        $this->assertSame('I', $this->cfdi->tipo);
        $this->assertNotEmpty($this->cfdi->filename);
        $this->assertNotSame('', $this->cfdi->localFileContent());

        $this->supplier = $this->cfdi->getSupplier();
        $this->assertTrue($this->supplier->exists());
        $this->assertSame($this->supplierRfc, $this->supplier->cifnif);
    }

    protected function tearDown(): void
    {
        if ($this->cfdi !== null) {
            @unlink(SupplierCfdiImporter::DESTINATION_FOLDER . $this->cfdi->filename);
            $this->cfdi->delete();
        }

        if ($this->supplier !== null) {
            $this->supplier->delete();
        }

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
