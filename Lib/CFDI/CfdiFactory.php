<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\EgresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\GlobalCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\IngresoCfdiBuilder;
use PhpCfdi\Credentials\PrivateKey;

class CfdiFactory
{

    public static function buildCfdiEgreso($factura, $relacion): string
    {
        $credentials = CfdiSettings::getSatCredentials();
        $privateKey = PrivateKey::openFile($credentials['llave'], $credentials['secreto']);

        $builder = new EgresoCfdiBuilder($factura);
        $builder->setCertificado($credentials['certificado']);
        $builder->setLlavePrivada($privateKey->pem(), $privateKey->passPhrase());
        $builder->setDocumentosRelacionados($relacion['relacionados'], '01');

        return $builder->getXml();
    }

    public static function buildCfdiIngreso($factura, $relacion)
    {
        $credentials = CfdiSettings::getSatCredentials();
        $privateKey = PrivateKey::openFile($credentials['llave'], $credentials['secreto']);

        $builder = new IngresoCfdiBuilder($factura);
        $builder->setCertificado($credentials['certificado']);
        $builder->setLlavePrivada($privateKey->pem(), $privateKey->passPhrase());
        $builder->setDocumentosRelacionados($relacion['relacionados'], $relacion['tiporelacion']);

        return $builder->getXml();
    }

    public static function buildCfdiGlobal($factura): string
    {
        $credentials = CfdiSettings::getSatCredentials();
        $privateKey = PrivateKey::openFile($credentials['llave'], $credentials['secreto']);

        $builder = new GlobalCfdiBuilder($factura);
        $builder->setCertificado($credentials['certificado']);
        $builder->setLlavePrivada($privateKey->pem(), $privateKey->passPhrase());

        return $builder->getXml();
    }
}
