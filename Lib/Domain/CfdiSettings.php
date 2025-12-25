<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;

class CfdiSettings
{
    public static function rfcGenerico($nacional = true): string
    {
        return $nacional ? 'XAXX010101000' : 'XEXX010101000';
    }

    public static function razonSocialGenerico($nacional = true): string
    {
        return $nacional ? 'PUBLICO EN GENERAL' : 'CLIENTE EXTRANJERO';
    }

    public static function serieEgreso(): string
    {
        return Tools::settings('default', 'codserierec', '');
    }

    public static function satCredentials(Empresa $company): array
    {
        return [
            'certificado' => CFDI_CERT_DIR . DIRECTORY_SEPARATOR . $company->cfdi_cert_filename,
            'llave' => CFDI_CERT_DIR . DIRECTORY_SEPARATOR . $company->cfdi_key_filename,
            'secreto' => base64_decode($company->cfdi_key_password ?? '')
        ];
    }

    public static function stampedInvoiceStatus(Empresa $company): string
    {
        return $company->cfdi_stamped_status ?? '';
    }

    public static function canceledInvoiceStatus(Empresa $company): string
    {
        return $company->cfdi_canceled_status ?? '';
    }

    public static function cfdiUsage(): string
    {
        return Tools::settings('cfdi', 'cfdi-usage', '');
    }

    public static function taxRegime(Empresa $company): string
    {
        return $company->cfdi_tax_regime ?? '';
    }

    public static function pacCredentials(Empresa $company): array
    {
        return [
            'user' => $company->cfdi_pac_user ?? '',
            'token' => $company->cfdi_pac_token ?? '',
            'test_mode' => (bool)$company->cfdi_pac_test
        ];
    }

    /**
     * Obtiene el tipo de almacenamiento configurado para los CFDIs
     *
     * @return string 'file' o 'database'
     */
    public static function storageType(): string
    {
        return Tools::settings('cfdi', 'storage-type', 'file');
    }
}
