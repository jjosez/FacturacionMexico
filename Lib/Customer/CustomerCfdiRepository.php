<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Customer;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiData;

final class CustomerCfdiRepository
{
    public function createFromInvoice(FacturaCliente $invoice, CfdiData $data): ?CfdiCliente
    {
        $cfdi = new CfdiCliente();
        $cfdi->codcliente = $invoice->codcliente;
        $cfdi->idfactura = $invoice->idfactura;
        $cfdi->cfdiglobal = $invoice->isGlobalInvoice() ?: null;
        $cfdi->coddivisa = $data->moneda;
        $cfdi->estado = 'Timbrado';
        $cfdi->fecha_emision = $data->fecha;
        $cfdi->fecha_timbrado = $data->fechaTimbrado;
        $cfdi->folio = $data->folio;
        $cfdi->forma_pago = $data->formaPago;
        $cfdi->metodo_pago = $data->metodoPago;
        $cfdi->receptor_nombre = $data->receptor['nombre'];
        $cfdi->receptor_rfc = $data->receptor['rfc'];
        $cfdi->serie = $data->serie;
        $cfdi->tipo = $data->tipoComprobante;
        $cfdi->total = $data->total;
        $cfdi->uuid = $data->uuid;
        $cfdi->version = $data->version;

        return $cfdi->save() ? $cfdi : null;
    }

    public function save(CfdiCliente $cfdi): bool
    {
        return $cfdi->save();
    }

    public function updateStatus(CfdiCliente $cfdi, CfdiStatus $status): bool
    {
        $cfdi->estado = $status->value;
        return $cfdi->save();
    }

    public function updateMailDate(CfdiCliente $cfdi): bool
    {
        $cfdi->updateMailDate();
        return $cfdi->save();
    }
}
