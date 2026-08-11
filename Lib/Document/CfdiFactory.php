<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Document;

use Exception;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiBuildResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Document\CfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Document\EgresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Document\GlobalCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Document\IngresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Document\Validation\GlobalValidator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Document\Validation\IngresoValidator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\SAT\CertificateService;

class CfdiFactory
{
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
        $builderError = false;
        $builderMessage = '';
        $builderXml = '';

        try {
            $credentials = (new CertificateService())->signingCredentials($builder->getEmpresa());
            $builder->setCertificado($credentials['certificado']);
            $builder->setLlavePrivada($credentials['llave'], $credentials['secreto']);
            $builder->setCfdiRelacionados($relations);
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
