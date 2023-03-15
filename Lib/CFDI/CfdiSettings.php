<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Core\App\App;
use FacturaScripts\Core\App\AppSettings;

class CfdiSettings
{
    public static function getRfcGenerico($nacional = true): string
    {
        return $nacional ? 'XAXX010101000' : 'XEXX010101000';
    }

    public static function getSerieEgreso(): string
    {
        return AppSettings::get('default', 'codserierec', 'R');
    }

    public static function getSatCredentials(): array
    {
        return [
            'certificado' => CFDI_CERT_DIR . DIRECTORY_SEPARATOR . AppSettings::get('cfdi', 'cerfile'),
            'llave' => CFDI_CERT_DIR . DIRECTORY_SEPARATOR . AppSettings::get('cfdi', 'keyfile'),
            'secreto' => AppSettings::get('cfdi', 'passphrase')
        ];
    }

}
