<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiCliente;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiData;

class CfdiStorage
{
    public static function saveCfdi(FacturaCliente $factura, CfdiCliente $cfdi, string $xmlContent): bool
    {
        $reader = new CfdiQuickReader($xmlContent);

        $cfdi->codcliente = $factura->codcliente;
        $cfdi->idfactura = $factura->idfactura;

        $cfdi->coddivisa = $reader->moneda();
        $cfdi->estado = 'Timbrado';
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
        $cfdi->version = $reader->version();

        return $cfdi->save();
    }

    public static function saveCfdiXml(CfdiCliente $cfdi, string $xmlContent): bool
    {
        $cfdiData = new CfdiData();

        $cfdiData->idcfdi = $cfdi->idcfdi;
        $cfdiData->uuid = $cfdi->uuid;
        $cfdiData->xml = $xmlContent;

        return $cfdiData->save();
    }

    public static function updateCfdiMailDate(CfdiCliente $cfdi): bool
    {
        $cfdi->updateMailDate();
        return $cfdi->save();
    }

    public static function updateCfdiStatus(CfdiCliente $cfdi, string $status)
    {
        $cfdi->estado = $status;
        $cfdi->save();
    }
}
