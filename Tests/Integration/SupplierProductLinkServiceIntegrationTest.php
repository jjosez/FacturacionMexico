<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests\Integration;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Session;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierProductLinkService;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

final class SupplierProductLinkServiceIntegrationTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    private ?Producto $product = null;
    private ?ProductoProveedor $supplierProduct = null;
    private ?Proveedor $supplier = null;
    private array $families = [];

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
    }

    protected function setUp(): void
    {
        $users = User::all([], [], 0, 1);
        $this->assertNotEmpty($users);
        $users[0]->idempresa = Empresas::default()->idempresa;
        Session::set('user', $users[0]);

        $this->supplier = $this->getRandomSupplier();
        $this->assertTrue($this->supplier->save());

        $family = $this->createFamilyTree();

        $this->product = $this->getRandomProduct();
        $this->product->codfamilia = $family->codfamilia;
        $this->product->idempresa = Empresas::default()->idempresa;
        $this->assertTrue($this->product->save());
    }

    public function testLinkWithoutPriceUpdatePreservesExistingPriceDateAndDiscounts(): void
    {
        $originalDate = Tools::dateTime('-2 days');
        $this->supplierProduct = $this->createSupplierProduct(25.0, 5.0, $originalDate);

        $result = (new SupplierProductLinkService())->vincular(
            $this->product->referencia,
            $this->supplier->codproveedor,
            'REF-PROVEEDOR-NUEVA',
            100.0,
            0.0,
            0.0,
            0.0,
            false,
            $this->supplierProduct->coddivisa,
            Tools::dateTime('-1 day')
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($this->supplierProduct->load($this->supplierProduct->id));
        $this->assertSame(25.0, (float)$this->supplierProduct->precio);
        $this->assertSame(5.0, (float)$this->supplierProduct->dtopor);
        $this->assertSame(strtotime($originalDate), strtotime($this->supplierProduct->actualizado));
        $this->assertSame('REF-PROVEEDOR-NUEVA', $this->supplierProduct->refproveedor);
    }

    public function testOlderDocumentDoesNotReplaceANewerSupplierPrice(): void
    {
        $originalDate = Tools::dateTime('-1 day');
        $this->supplierProduct = $this->createSupplierProduct(25.0, 5.0, $originalDate);

        $result = (new SupplierProductLinkService())->vincular(
            $this->product->referencia,
            $this->supplier->codproveedor,
            $this->supplierProduct->refproveedor,
            100.0,
            0.0,
            0.0,
            0.0,
            true,
            $this->supplierProduct->coddivisa,
            Tools::dateTime('-2 days')
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($this->supplierProduct->load($this->supplierProduct->id));
        $this->assertSame(25.0, (float)$this->supplierProduct->precio);
        $this->assertSame(5.0, (float)$this->supplierProduct->dtopor);
        $this->assertSame(strtotime($originalDate), strtotime($this->supplierProduct->actualizado));
    }

    public function testNewLinkUsesDocumentDateSoWorkerCanProcessTheInvoice(): void
    {
        $documentDate = Tools::dateTime('-2 days');
        $result = (new SupplierProductLinkService())->vincular(
            $this->product->referencia,
            $this->supplier->codproveedor,
            'REF-PROVEEDOR',
            100.0,
            0.0,
            0.0,
            0.0,
            false,
            Tools::settings('default', 'coddivisa'),
            $documentDate
        );

        $this->assertTrue($result['ok']);
        $this->supplierProduct = $result['data'];
        $this->assertSame($this->product->referencia, $this->supplierProduct->referencia);
        $this->assertSame('REF-PROVEEDOR', $this->supplierProduct->refproveedor);
        $this->assertSame(0.0, (float)$this->supplierProduct->precio);
        $this->assertSame(strtotime($documentDate), strtotime($this->supplierProduct->actualizado));
    }

    protected function tearDown(): void
    {
        $this->supplierProduct?->delete();
        $this->product?->delete();
        foreach (array_reverse($this->families) as $family) {
            $family->delete();
        }
        $this->supplier?->getDefaultAddress()?->delete();
        $this->supplier?->delete();
        Session::clear();
        $this->logErrors();
    }

    private function createSupplierProduct(float $price, float $discount, string $date): ProductoProveedor
    {
        $supplierProduct = new ProductoProveedor();
        $supplierProduct->actualizado = $date;
        $supplierProduct->coddivisa = Tools::settings('default', 'coddivisa');
        $supplierProduct->codproveedor = $this->supplier->codproveedor;
        $supplierProduct->dtopor = $discount;
        $supplierProduct->idproducto = $this->product->idproducto;
        $supplierProduct->precio = $price;
        $supplierProduct->referencia = $this->product->referencia;
        $supplierProduct->refproveedor = 'REF-PROVEEDOR';
        $this->assertTrue($supplierProduct->save());
        return $supplierProduct;
    }

    private function createFamilyTree(): Familia
    {
        $parent = null;
        $seed = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        for ($level = 1; $level <= 3; $level++) {
            $family = new Familia();
            $family->descripcion = 'Familia CFDI ' . $seed . ' ' . $level;
            $family->madre = $parent?->codfamilia;
            $family->sku_key = substr($seed, ($level - 1) * 2, 2);
            $this->assertTrue($family->save());
            $this->families[] = $family;
            $parent = $family;
        }

        return $parent;
    }
}
