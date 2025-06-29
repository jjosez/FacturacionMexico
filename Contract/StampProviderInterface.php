<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Contract;

use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\CfdiStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\StampResult;

interface StampProviderInterface
{
    public function stamp(string $xml): StampResult;
    public function cancel(string $uuid): StampResult;
    public function getStamped(string $xml): StampResult;
    public function getStatus(array $query): CfdiStatus;
}
