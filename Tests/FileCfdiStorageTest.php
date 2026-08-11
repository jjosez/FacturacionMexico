<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests;

use FacturaScripts\Plugins\FacturacionMexico\Lib\Storage\FileCfdiStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiScope;
use PHPUnit\Framework\TestCase;

final class FileCfdiStorageTest extends TestCase
{
    private const UUID = '5b54f7f9-061d-4dd0-b00f-8d4f88929398';

    public function testSaveGetAndDeleteXml(): void
    {
        $storage = new FileCfdiStorage();
        $customerXml = '<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" />';
        $supplierXml = '<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4">supplier</cfdi:Comprobante>';

        $key = $storage->save(CfdiScope::CUSTOMER, self::UUID, $customerXml);
        $storage->save(CfdiScope::SUPPLIER, self::UUID, $supplierXml);

        $this->assertSame(self::UUID, $key);
        $this->assertTrue($storage->exists(CfdiScope::CUSTOMER, self::UUID));
        $this->assertTrue($storage->exists(CfdiScope::SUPPLIER, self::UUID));
        $this->assertSame($customerXml, $storage->get(CfdiScope::CUSTOMER, self::UUID));
        $this->assertSame($supplierXml, $storage->get(CfdiScope::SUPPLIER, self::UUID));
        $this->assertTrue($storage->delete(CfdiScope::CUSTOMER, self::UUID));
        $this->assertFalse($storage->exists(CfdiScope::CUSTOMER, self::UUID));
        $this->assertTrue($storage->delete(CfdiScope::SUPPLIER, self::UUID));
    }
}
