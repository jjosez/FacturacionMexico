<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use Exception;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\CfdiBuildResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\CfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\EgresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\GlobalCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\IngresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware\GlobalValidator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware\IngresoValidator;
use PhpCfdi\Credentials\PrivateKey;

class CfdiFactory
{
    private static function buildCredentials(): array
    {
        $credentials = CfdiSettings::satCredentials();
        $privateKey = PrivateKey::openFile($credentials['llave'], $credentials['secreto']);

        return [
            'certificado' => $credentials['certificado'],
            'llave' => $privateKey->pem(),
            'secreto' => $privateKey->passPhrase(),
        ];
    }

    public static function buildCfdiEgreso(FacturaCliente $invoice, array $relations = []): CfdiBuildResult
    {
        return self::buildCfdiDocument(new EgresoCfdiBuilder($invoice), $relations);
    }

    public static function buildCfdiIngreso(FacturaCliente $invoice, array $relations = []): CfdiBuildResult
    {
        $validator = IngresoValidator::validate($invoice);

        if (!$validator->isValid()) {
            return new CfdiBuildResult('', $validator->getMessagesAsString(), true);
        }

        return self::buildCfdiDocument(new IngresoCfdiBuilder($invoice), $relations);
    }

    public static function buildCfdiGlobal(FacturaCliente $invoice, array $relations = []): CfdiBuildResult
    {
        $validator = GlobalValidator::validate($invoice);

        if (!$validator->isValid()) {
            return new CfdiBuildResult('', $validator->getMessagesAsString(), true);
        }

        return self::buildCfdiDocument(new GlobalCfdiBuilder($invoice), $relations);
    }

    private static function buildCfdiDocument(CfdiBuilder $builder, array $relations): CfdiBuildResult
    {
        $credentials = self::buildCredentials();
        $builder->setCertificado($credentials['certificado']);
        $builder->setLlavePrivada($credentials['llave'], $credentials['secreto']);

        // Añadir relaciones agrupadas
        $builder->setCfdiRelacionados($relations);

        $builderError = false;
        $builderMessage = '';
        $builderXml = '';

        try {
            $builderXml = $builder->getXml();
        } catch (Exception $e) {
            $builderMessage = $e->getMessage();
            $builderError = true;
        }

        return new CfdiBuildResult(
            $builderXml,
            $builderMessage,
            $builderError
        );
    }
}
