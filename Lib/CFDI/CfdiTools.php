<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\EgresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\GlobalCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\IngresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiRelacion;

class CfdiTools
{
    public static function buildCfdiEgreso($factura, $empresa, $uso, $relacion)
    {
        $builder = new EgresoCfdiBuilder($factura, $empresa, $uso);
        $builder->setDocumentosRelacionados($relacion['relacionados'], $relacion['tiporelacion']);
        return $builder->getXml();
    }

    public static function buildCfdiIngreso($factura, $empresa, $uso)
    {
        $builder = new IngresoCfdiBuilder($factura, $empresa, $uso);
        return $builder->getXml();
    }

    public static function buildCfdiGlobal($factura, $empresa)
    {
        $builder = new GlobalCfdiBuilder($factura, $empresa);
        return $builder->getXml();
    }

    public static function saveCfdi(string $xml, $factura)
    {
        $cfdi = new CfdiCliente();
        $reader = new CfdiQuickReader($xml);

        $cfdi->codcliente = $factura->codcliente;
        $cfdi->idfactura = $factura->idfactura;

        $cfdi->coddivisa = $reader->moneda();
        $cfdi->estado = 'TIMBRADO';
        $cfdi->folio = $reader->folio();
        $cfdi->formapago = $reader->formaPago();
        $cfdi->metodopago = $reader->metodoPago();
        $cfdi->razonreceptor = $reader->receptorNombre();
        $cfdi->rfcreceptor = $reader->receptorRfc();
        $cfdi->serie = $reader->serie();
        $cfdi->tipocfdi = $reader->tipoComprobamte();
        $cfdi->totaldto = 0;
        $cfdi->total = $reader->total();
        $cfdi->uuid = $reader->uuid();

        if ($cfdi->save()) {
            $cfdiXml = new CfdiData();

            $cfdiXml->idcfdi = $cfdi->idcfdi;
            $cfdiXml->uuid = $cfdi->uuid;
            $cfdiXml->xml = $xml;

            if ($cfdiXml->save()) {
                $relacionados = $reader->cfdiRelacionado();

                foreach ($relacionados['relacionados'] as $relacionado) {
                    $cfdiRelacionado = new CfdiCliente();

                    if ($cfdiRelacionado->loadFromUUID($relacionado)) {
                        $cfdiRelacionado->cfdirelacionado = $cfdi->idcfdi;
                        $cfdiRelacionado->tiporelacion = $relacionados['tiporelacion'];
                        $cfdiRelacionado->uuidrelacionado = $cfdi->uuid;

                        $cfdiRelacionado->save();
                    }
                }
                return true;
            }
        }

        return false;
    }
}
