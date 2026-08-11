<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests\Integration;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Storage\DatabaseCfdiStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiScope;
use FacturaScripts\Core\Tools;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

final class CfdiStorageIntegrationTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    private ?CfdiCliente $cfdi = null;
    private ?Cliente $customer = null;
    private ?FacturaCliente $invoice = null;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    protected function setUp(): void
    {
        $this->customer = $this->getRandomCustomer('DatabaseCfdiStorageIntegrationTest');
        $this->assertTrue($this->customer->save());

        $this->invoice = new FacturaCliente();
        $this->invoice->setSubject($this->customer);
        $this->assertTrue($this->invoice->save());

        $this->cfdi = new CfdiCliente();
        $this->cfdi->codcliente = $this->customer->codcliente;
        $this->cfdi->coddivisa = 'MXN';
        $this->cfdi->estado = 'Timbrado';
        $this->cfdi->fecha_emision = '2026-08-10 12:00:00';
        $this->cfdi->fecha_timbrado = '2026-08-10 12:01:00';
        $this->cfdi->folio = 'TEST';
        $this->cfdi->forma_pago = '01';
        $this->cfdi->idempresa = $this->invoice->idempresa;
        $this->cfdi->idfactura = $this->invoice->idfactura;
        $this->cfdi->metodo_pago = 'PUE';
        $this->cfdi->receptor_nombre = $this->customer->razonsocial;
        $this->cfdi->receptor_rfc = $this->customer->cifnif;
        $this->cfdi->serie = 'T';
        $this->cfdi->tipo = 'I';
        $this->cfdi->total = 1.0;
        $this->cfdi->uuid = $this->uuid();
        $this->cfdi->version = '4.0';
        $this->assertTrue($this->cfdi->save());
    }

    public function testSaveUpdateGetAndDelete(): void
    {
        $storage = new DatabaseCfdiStorage();
        $firstXml = '<cfdi>first</cfdi>';
        $secondXml = '<cfdi>second</cfdi>';

        $this->assertSame($this->cfdi->uuid, $storage->save(CfdiScope::CUSTOMER, $this->cfdi->uuid, $firstXml));
        $this->assertTrue($storage->exists(CfdiScope::CUSTOMER, $this->cfdi->uuid));
        $this->assertSame($firstXml, $storage->get(CfdiScope::CUSTOMER, $this->cfdi->uuid));
        $this->assertSame($firstXml, $this->cfdi->getXml());

        $storage->save(CfdiScope::CUSTOMER, $this->cfdi->uuid, $secondXml);
        $this->assertSame($secondXml, $storage->get(CfdiScope::CUSTOMER, $this->cfdi->uuid));
        $this->assertSame($secondXml, $this->cfdi->getXml());

        $this->assertTrue($storage->delete(CfdiScope::CUSTOMER, $this->cfdi->uuid));
        $this->assertFalse($storage->exists(CfdiScope::CUSTOMER, $this->cfdi->uuid));
        $this->assertNull($storage->get(CfdiScope::CUSTOMER, $this->cfdi->uuid));
    }

    public function testReadsLegacyFile(): void
    {
        $folder = FS_FOLDER . '/MyFiles/CFDI/customer';
        $filename = 'legacy-' . $this->cfdi->uuid . '.xml';
        $xml = '<cfdi>legacy</cfdi>';

        $this->cfdi->filename = $filename;
        $this->assertTrue($this->cfdi->save());
        $this->assertTrue(Tools::folderCheckOrCreate($folder));
        $this->assertNotFalse(file_put_contents($folder . '/' . $filename, $xml));

        $this->assertSame($xml, $this->cfdi->getXml());
        $this->assertTrue(unlink($folder . '/' . $filename));
    }

    protected function tearDown(): void
    {
        if ($this->cfdi !== null) {
            (new DatabaseCfdiStorage())->delete(CfdiScope::CUSTOMER, $this->cfdi->uuid);
            $this->cfdi->delete();
        }

        if ($this->invoice !== null) {
            $this->invoice->delete();
        }

        if ($this->customer !== null) {
            $this->customer->getDefaultAddress()?->delete();
            $this->customer->delete();
        }

        $this->logErrors();
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
}
