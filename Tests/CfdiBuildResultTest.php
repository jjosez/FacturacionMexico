<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests;

use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiBuildResult;
use PHPUnit\Framework\TestCase;

final class CfdiBuildResultTest extends TestCase
{
    public function testReturnsOneBuildMessagePerLine(): void
    {
        $result = new CfdiBuildResult('', 'Primer error' . PHP_EOL . 'Segundo error', true);

        $this->assertSame(['Primer error', 'Segundo error'], $result->getBuildMessages());
    }

    public function testReturnsNoMessagesWhenEmpty(): void
    {
        $result = new CfdiBuildResult('<xml />', '', false);

        $this->assertSame([], $result->getBuildMessages());
    }
}
