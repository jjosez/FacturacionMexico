<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests;

use FacturaScripts\Plugins\FacturacionMexico\Lib\Storage\FileCfdiStorage;
use PHPUnit\Framework\TestCase;

final class FileCfdiStorageTest extends TestCase
{
    private const UUID = '5b54f7f9-061d-4dd0-b00f-8d4f88929398';

    public function testSaveGetAndDeleteXml(): void
    {
        $storage = new FileCfdiStorage();
        $xml = '<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" />';

        $key = $storage->save(self::UUID, $xml);

        $this->assertMatchesRegularExpression(
            '#^\d{4}/\d{2}/' . self::UUID . '\.xml$#',
            $key
        );
        $this->assertTrue($storage->exists(self::UUID));
        $this->assertSame($xml, $storage->get(self::UUID));
        $this->assertTrue($storage->delete(self::UUID));
        $this->assertFileDoesNotExist(FS_FOLDER . '/MyFiles/FacturacionMexico/cfdi/' . $key);
    }
}
