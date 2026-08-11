<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Read;

use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiParser;

final class CfdiReader
{
    public function data(CfdiProveedor $cfdi): ?CfdiData
    {
        $xml = $cfdi->localFileContent();

        return $xml === '' ? null : (new CfdiParser($xml))->parse();
    }
}
