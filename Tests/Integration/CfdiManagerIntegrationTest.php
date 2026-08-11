<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests\Integration;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Customer\CfdiManager;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Customer\CfdiRelationService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiBuildResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiSatStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\StampResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Storage\CfdiStorageInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiScope;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Stamp\StampProviderInterface;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PhpCfdi\Credentials\Credential;
use PHPUnit\Framework\TestCase;

final class CfdiManagerIntegrationTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    private ?Cliente $customer = null;
    private ?CfdiCliente $createdCfdi = null;
    private ?Empresa $company = null;
    private ?FacturaCliente $invoice = null;
    private ?string $originalStampedStatus = null;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    protected function setUp(): void
    {
        $this->customer = $this->getRandomCustomer('CfdiManagerIntegrationTest');
        $this->assertTrue($this->customer->save());

        $this->invoice = new FacturaCliente();
        $this->invoice->setSubject($this->customer);
        $this->assertTrue($this->invoice->save());

        $this->company = $this->invoice->getCompany();
        $this->originalStampedStatus = $this->company->cfdi_stamped_status;
        $this->company->cfdi_stamped_status = (string) $this->invoice->idestado;
        $this->assertTrue($this->company->save());
    }

    public function testStorageFailureRollsBackMetadataAndInvoice(): void
    {
        $uuid = $this->uuid();
        $initialState = $this->invoice->idestado;
        $xml = $this->xml($uuid);
        $manager = new CfdiManager(
            new TestStampProvider($uuid, $xml),
            new FailingStorage(),
            new CfdiRelationService(),
            null,
            static fn(): CfdiBuildResult => new CfdiBuildResult('<cfdi />', '', false)
        );

        $result = $manager->stampInvoice($this->invoice);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('no se pudo guardar localmente', strtolower($result->getMessage()));

        $cfdi = new CfdiCliente();
        $this->assertFalse($cfdi->loadFromUuid(strtoupper($uuid)));

        $reloadedInvoice = new FacturaCliente();
        $this->assertTrue($reloadedInvoice->load($this->invoice->idfactura));
        $this->assertSame($initialState, $reloadedInvoice->idestado);
    }

    public function testSuccessfulStampPersistsMetadataAndXml(): void
    {
        $uuid = $this->uuid();
        $xml = $this->xml($uuid);
        $storage = new MemoryStorage();
        $manager = new CfdiManager(
            new TestStampProvider($uuid, $xml),
            $storage,
            new CfdiRelationService(),
            null,
            static fn(): CfdiBuildResult => new CfdiBuildResult('<cfdi />', '', false)
        );

        $result = $manager->stampInvoice($this->invoice);

        $this->assertTrue($result->isSuccess(), $result->getMessage());
        $this->createdCfdi = $result->getCfdi();
        $this->assertNotNull($this->createdCfdi);
        $this->assertSame(strtoupper($uuid), $this->createdCfdi->uuid);
        $this->assertSame($xml, $storage->get(CfdiScope::CUSTOMER, $this->createdCfdi->uuid));
        $this->assertSame($xml, $manager->getXml($this->createdCfdi));
    }

    protected function tearDown(): void
    {
        if ($this->createdCfdi !== null) {
            $this->createdCfdi->delete();
        }

        if ($this->invoice !== null) {
            $this->invoice->delete();
        }

        if ($this->company !== null) {
            $this->company->cfdi_stamped_status = $this->originalStampedStatus;
            $this->company->save();
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
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private function xml(string $uuid): string
    {
        return <<<XML
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-08-10T12:00:00" Moneda="MXN" TipoDeComprobante="I" SubTotal="100.00" Total="116.00" LugarExpedicion="01000" FormaPago="01" MetodoPago="PUE" Serie="A" Folio="123"><cfdi:Emisor Rfc="AAA010101AAA" Nombre="Emisor" RegimenFiscal="601" /><cfdi:Receptor Rfc="BBB010101BBB" Nombre="Receptor" DomicilioFiscalReceptor="01000" RegimenFiscalReceptor="601" UsoCFDI="G03" /><cfdi:Conceptos><cfdi:Concepto ClaveProdServ="01010101" Cantidad="1" ClaveUnidad="H87" Descripcion="Producto" ValorUnitario="100.00" Importe="100.00" /></cfdi:Conceptos><cfdi:Complemento><tfd:TimbreFiscalDigital Version="1.1" UUID="$uuid" FechaTimbrado="2026-08-10T12:01:00" RfcProvCertif="AAA010101AAA" SelloCFD="abc" NoCertificadoSAT="00001000000504465028" SelloSAT="def" /></cfdi:Complemento></cfdi:Comprobante>
XML;
    }
}

final class FailingStorage implements CfdiStorageInterface
{
    public function save(CfdiScope $scope, string $uuid, string $xml): string { throw new \RuntimeException('Storage test failure.'); }
    public function get(CfdiScope $scope, string $uuid): ?string { return null; }
    public function exists(CfdiScope $scope, string $uuid): bool { return false; }
    public function delete(CfdiScope $scope, string $uuid): bool { return true; }
}

final class MemoryStorage implements CfdiStorageInterface
{
    private array $xml = [];

    public function save(CfdiScope $scope, string $uuid, string $xml): string { $this->xml[$scope->value][$uuid] = $xml; return $uuid; }
    public function get(CfdiScope $scope, string $uuid): ?string { return $this->xml[$scope->value][$uuid] ?? null; }
    public function exists(CfdiScope $scope, string $uuid): bool { return isset($this->xml[$scope->value][$uuid]); }
    public function delete(CfdiScope $scope, string $uuid): bool { unset($this->xml[$scope->value][$uuid]); return true; }
}

final class TestStampProvider implements StampProviderInterface
{
    public function __construct(private string $uuid, private string $xml) {}
    public function stamp(string $xml): StampResult { return new StampResult(false, $this->uuid, $this->xml); }
    public function cancel(string $uuid, Credential $credential): StampResult { throw new \LogicException('Not used.'); }
    public function getStamped(string $xml): StampResult { throw new \LogicException('Not used.'); }
    public function getStatus(array $query): CfdiSatStatus { throw new \LogicException('Not used.'); }
}
