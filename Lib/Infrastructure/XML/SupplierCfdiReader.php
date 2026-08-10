<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML;

use Exception;
use FacturaScripts\Dinamic\Model\CfdiProveedor;

final class SupplierCfdiReader
{
    public function read(CfdiProveedor $cfdi): CfdiQuickReader
    {
        $xml = $cfdi->localFileContent();
        if (empty($xml)) {
            throw new Exception('No se pudo leer el archivo XML del CFDI');
        }

        return new CfdiQuickReader($xml);
    }
}
