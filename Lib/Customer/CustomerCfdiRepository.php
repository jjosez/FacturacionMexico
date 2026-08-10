<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Customer;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;

final class CustomerCfdiRepository
{
    public function createFromInvoice(FacturaCliente $invoice, CfdiParser $parser): ?CfdiCliente
    {
        $cfdi = new CfdiCliente();
        $cfdi->codcliente = $invoice->codcliente;
        $cfdi->idfactura = $invoice->idfactura;
        $cfdi->cfdiglobal = $invoice->isGlobalInvoice() ?: null;
        $cfdi->coddivisa = $parser->moneda();
        $cfdi->estado = 'Timbrado';
        $cfdi->fecha_emision = $parser->fechaExpedicion();
        $cfdi->fecha_timbrado = $parser->fechaTimbrado();
        $cfdi->folio = $parser->folio();
        $cfdi->forma_pago = $parser->formaPago();
        $cfdi->metodo_pago = $parser->metodoPago();
        $cfdi->receptor_nombre = $parser->receptorNombre();
        $cfdi->receptor_rfc = $parser->receptorRfc();
        $cfdi->serie = $parser->serie();
        $cfdi->tipo = $parser->tipoComprobamte();
        $cfdi->total = $parser->total();
        $cfdi->uuid = $parser->uuid();
        $cfdi->version = $parser->version();

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
