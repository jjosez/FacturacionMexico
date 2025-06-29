<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Service;

use FacturaScripts\Plugins\FacturacionMexico\Contract\StampProviderInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\StampService\StampServiceResponse;

class CfdiStampService
{
    protected StampProviderInterface $provider;

    public function __construct(StampProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function stamp(string $xml): StampServiceResponse
    {
        return $this->provider->stamp($xml);
    }

    public function cancel(string $uuid): bool
    {
        return $this->provider->cancel($uuid);
    }

    public function queryStatus(array $query): array
    {
        return $this->provider->queryStatus($query);
    }
}

