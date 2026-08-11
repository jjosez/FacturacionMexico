<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\SAT;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiSatStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Stamp\StampProviderInterface;

final class SatStatusService
{
    public function check(
        StampProviderInterface $provider,
        CfdiCliente $cfdi,
        string $issuerRfc,
        string $recipientRfc
    ): CfdiSatStatus {
        return $provider->getStatus([
            'emisor' => $issuerRfc,
            'receptor' => $recipientRfc,
            'uuid' => $cfdi->uuid,
            'total' => $cfdi->total,
        ]);
    }
}
