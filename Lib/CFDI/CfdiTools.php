<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\EgresoCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\GlobalCfdiBuilder;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder\IngresoCfdiBuilder;

class CfdiTools
{
    public static function buildCfdiEgreso($factura, $empresa, $uso)
    {
        $builder = new EgresoCfdiBuilder($factura, $empresa, $uso);
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

    public static function saveCfdi(string $xml, $codliente, $idfactura)
    {
        $cfdi = new CfdiCliente();
        $reader = new CfdiQuickReader($xml);

        $cfdi->codcliente = $codliente;
        $cfdi->idfactura = $idfactura;

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
            return $cfdiXml->save();
        }

        return false;
    }
}
