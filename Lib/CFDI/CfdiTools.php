<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\EgresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\GlobalCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\IngresoCfdiBuilder;

class CfdiTools
{
    public static function buildCfdiEgreso($factura, $empresa, $uso, $relacion)
    {
        $builder = new EgresoCfdiBuilder($factura, $empresa, $uso);
        $builder->setDocumentosRelacionados($relacion['relacionados'], $relacion['tiporelacion']);
        return $builder->getXml();
    }

    public static function buildCfdiIngreso($factura, $empresa, $uso, $relacion)
    {
        $builder = new IngresoCfdiBuilder($factura, $empresa, $uso);
        $builder->setDocumentosRelacionados($relacion['relacionados'], $relacion['tiporelacion']);
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
            $factura->idestado = 11;
            $factura->save();

            $cfdiData = new CfdiData();

            $cfdiData->idcfdi = $cfdi->idcfdi;
            $cfdiData->uuid = $cfdi->uuid;
            $cfdiData->xml = $xml;

            return $cfdiData->save();
        }

        return false;
    }
}
