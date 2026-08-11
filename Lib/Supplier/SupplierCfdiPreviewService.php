<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier;

use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;

final class SupplierCfdiPreviewService
{
    public function reader(CfdiProveedor $cfdi): ?CfdiParser
    {
        $xml = $cfdi->localFileContent();

        return $xml === '' ? null : new CfdiParser($xml);
    }
}
