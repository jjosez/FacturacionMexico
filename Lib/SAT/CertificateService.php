<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\SAT;

use FacturaScripts\Core\Model\Empresa;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiConfigurationException;
use PhpCfdi\Credentials\Credential;
use PhpCfdi\Credentials\PrivateKey;

final class CertificateService
{
    public function missing(Empresa $company): array
    {
        $credentials = CfdiSettings::satCredentials($company);
        $missing = [];

        if (empty($credentials['certificado']) || !is_file($credentials['certificado'])) {
            $missing['certificado'] = 'Certificado SAT (.cer) no configurado o no existe';
        }

        if (empty($credentials['llave']) || !is_file($credentials['llave'])) {
            $missing['llave'] = 'Llave privada SAT (.key) no configurada o no existe';
        }

        if (empty($credentials['secreto'])) {
            $missing['secreto'] = 'Contraseña de la llave privada SAT';
        }

        return $missing;
    }

    public function open(Empresa $company): Credential
    {
        $missing = $this->missing($company);
        if (!empty($missing)) {
            throw new CfdiConfigurationException('Configuración de certificados SAT incompleta', $missing);
        }

        $credentials = CfdiSettings::satCredentials($company);

        return Credential::openFiles(
            $credentials['certificado'],
            $credentials['llave'],
            $credentials['secreto']
        );
    }

    public function signingCredentials(Empresa $company): array
    {
        $missing = $this->missing($company);
        if (!empty($missing)) {
            throw new CfdiConfigurationException('Configuración de certificados SAT incompleta', $missing);
        }

        $credentials = CfdiSettings::satCredentials($company);
        $privateKey = PrivateKey::openFile($credentials['llave'], $credentials['secreto']);

        return [
            'certificado' => $credentials['certificado'],
            'llave' => $privateKey->pem(),
            'secreto' => $privateKey->passPhrase(),
        ];
    }
}
