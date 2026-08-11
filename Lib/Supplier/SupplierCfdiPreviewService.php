<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier;

use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiData;

final class SupplierCfdiPreviewService
{
    public function data(CfdiProveedor $cfdi): ?CfdiData
    {
        $xml = $cfdi->localFileContent();

        return $xml === '' ? null : (new CfdiParser($xml))->parse();
    }
}
