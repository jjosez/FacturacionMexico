<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests;

use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\CfdiStampResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\StampResult;
use PHPUnit\Framework\TestCase;

final class CfdiStampResultTest extends TestCase
{
    public function testFailedResultIsNotSuccessful(): void
    {
        $result = CfdiStampResult::failed(new StampResult(
            true,
            '5b54f7f9-061d-4dd0-b00f-8d4f88929398',
            '<xml />',
            'No se pudo guardar el CFDI.'
        ));

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->hasError());
        $this->assertSame('No se pudo guardar el CFDI.', $result->getMessage());
    }
}
