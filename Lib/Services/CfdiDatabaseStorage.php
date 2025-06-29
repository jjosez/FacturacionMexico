<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Services;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Contract\CfdiStorageInterface;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiData;

class CfdiDatabaseStorage implements CfdiStorageInterface
{
    protected CfdiQuickReader $reader;

    public function __construct()
    {
    }

    public function save(FacturaCliente $invoice, string $xmlContent): ?CfdiCliente
    {
        $this->reader = new CfdiQuickReader($xmlContent);

        $cfdi = new CfdiCliente();
        $cfdi->codcliente = $invoice->codcliente;
        $cfdi->idfactura = $invoice->idfactura;
        $cfdi->coddivisa = $this->reader->moneda();
        $cfdi->estado = 'Timbrado';
        $cfdi->folio = $this->reader->folio();
        $cfdi->formapago = $this->reader->formaPago();
        $cfdi->metodopago = $this->reader->metodoPago();
        $cfdi->razonreceptor = $this->reader->receptorNombre();
        $cfdi->rfcreceptor = $this->reader->receptorRfc();
        $cfdi->serie = $this->reader->serie();
        $cfdi->tipocfdi = $this->reader->tipoComprobamte();
        $cfdi->totaldto = 0;
        $cfdi->total = $this->reader->total();
        $cfdi->uuid = $this->reader->uuid();
        $cfdi->version = $this->reader->version();

        return $cfdi->save() ? $cfdi : null;
    }

    public function saveXml(CfdiCliente $cfdi, string $xmlContent): bool
    {
        $cfdiData = new CfdiData();
        $cfdiData->idcfdi = $cfdi->idcfdi;
        $cfdiData->uuid = $cfdi->uuid;
        $cfdiData->xml = $xmlContent;

        return $cfdiData->save();
    }

    public function updateMailDate(CfdiCliente $cfdi): bool
    {
        $cfdi->updateMailDate();
        return $cfdi->save();
    }

    public function updateStatus(CfdiCliente $cfdi, string $status): bool
    {
        $cfdi->estado = $status;
        return $cfdi->save();
    }

    public function deleteCfdi(CfdiCliente $cfdi): bool
    {
        $cfdiData = new CfdiData();
        if ($cfdiData->loadFromUuid($cfdi->uuid)) {
            $cfdiData->delete();
        }

        return $cfdi->delete();
    }

    public function getReader(): CfdiQuickReader
    {
        return $this->reader;
    }
}
