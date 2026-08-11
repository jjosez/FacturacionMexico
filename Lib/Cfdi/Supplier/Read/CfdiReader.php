<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Read;

use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiData;

final class CfdiReader
{
    public function data(CfdiProveedor $cfdi): ?CfdiData
    {
        $xml = $cfdi->localFileContent();

        return $xml === '' ? null : (new CfdiParser($xml))->parse();
    }
}
