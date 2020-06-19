<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiCliente;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiData;

class FacturaCliente
{
    public function save()
    {
        return function () {
            $cfdi = new CfdiCliente();

            $cfdi->razonreceptor = $this->nombrecliente;
            $cfdi->codcliente = $this->codcliente;
            $cfdi->coddivisa = $this->coddivisa;
            $cfdi->estado = 'TIMBRADO';
            $cfdi->folio = $this->codigo;
            $cfdi->formapago = 'CONTADO';
            $cfdi->metodopago = 'EFECTIVO';
            $cfdi->idfactura = $this->idfactura;
            $cfdi->serie = $this->codserie;
            $cfdi->tipocfdi = 'INGRESO';
            $cfdi->totaldto = 10.0;
            $cfdi->total = $this->total;
            $cfdi->rfcreceptor = $this->cifnif;
            $cfdi->uuid = $this->uuid();

            if ($cfdi->save()) {
                $xml = new CfdiData();

                $xml->idcfdi = $cfdi->idcfdi;
                $xml->uuid = $cfdi->uuid;
                $xml->save();
            }
        };
    }


    public function uuid()
    {
        return function ()
        {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        };
    }
}