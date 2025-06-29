<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Core\Tools;

class CfdiSettings
{
    public static function rfcGenerico($nacional = true): string
    {
        return $nacional ? 'XAXX010101000' : 'XEXX010101000';
    }

    public static function serieEgreso(): string
    {
        return Tools::settings('default', 'codserierec', '');
    }

    public static function satCredentials(): array
    {
        return [
            'certificado' => CFDI_CERT_DIR . DIRECTORY_SEPARATOR . Tools::settings('cfdi', 'cerfile'),
            'llave' => CFDI_CERT_DIR . DIRECTORY_SEPARATOR . Tools::settings('cfdi', 'keyfile'),
            'secreto' => Tools::settings('cfdi', 'passphrase')
        ];
    }

    public static function stampedInvoiceStatus(): string
    {
        return Tools::settings('cfdi', 'stamped-status');
    }

    public static function canceledInvoiceStatus(): string
    {
        return Tools::settings('cfdi', 'canceled-status');
    }

    public static function cfdiUsage(): string
    {
        return Tools::settings('cfdi', 'cfdi-usage');
    }
}
