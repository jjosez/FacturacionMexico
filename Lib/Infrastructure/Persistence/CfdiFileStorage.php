<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\CfdiRepositoryInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Enums\CfdiStatus;

class CfdiFileStorage implements CfdiRepositoryInterface
{
    public const DESTINATION_FOLDER = FS_FOLDER . '/MyFiles/CFDI/customer/';

    protected CfdiQuickReader $reader;

    public function save(FacturaCliente $invoice, string $xmlContent): ?CfdiCliente
    {
        $this->reader = new CfdiQuickReader($xmlContent);

        $cfdi = new CfdiCliente();
        $cfdi->codcliente = $invoice->codcliente;
        $cfdi->idfactura = $invoice->idfactura;
        $cfdi->cfdiglobal = $invoice->isGlobalInvoice() ?: null;
        $cfdi->coddivisa = $this->reader->moneda();
        $cfdi->estado = 'Timbrado';
        $cfdi->fecha_emision = $this->reader->fechaExpedicion();
        $cfdi->fecha_timbrado = $this->reader->fechaTimbrado();
        $cfdi->folio = $this->reader->folio();
        $cfdi->forma_pago = $this->reader->formaPago();
        $cfdi->metodo_pago = $this->reader->metodoPago();
        $cfdi->receptor_nombre = $this->reader->receptorNombre();
        $cfdi->receptor_rfc = $this->reader->receptorRfc();
        $cfdi->serie = $this->reader->serie();
        $cfdi->tipo = $this->reader->tipoComprobamte();
        $cfdi->total = $this->reader->total();
        $cfdi->uuid = $this->reader->uuid();
        $cfdi->version = $this->reader->version();

        return $cfdi->save() ? $cfdi : null;
    }

    public function saveXml(CfdiCliente $cfdi, string $xmlContent): bool
    {
        $subFolder = date('Y') . '/' . date('m') . '/';
        $fullPath = self::DESTINATION_FOLDER . $subFolder;

        if (!Tools::folderCheckOrCreate($fullPath)) {
            Tools::log()->warning('No se pudo crear la carpeta para el CFDI: ' . $fullPath);
            return false;
        }

        $destinationName = date('Ymd_His') . '_' . $cfdi->uuid . '.xml';
        $filePath = $fullPath . $destinationName;

        if (false === file_put_contents($filePath, $xmlContent)) {
            Tools::log()->warning('No se pudo guardar el archivo CFDI: ' . $filePath);
            return false;
        }

        $cfdi->filename = $subFolder . $destinationName;
        return $cfdi->save();
    }

    public function updateMailDate(CfdiCliente $cfdi): bool
    {
        $cfdi->updateMailDate();
        return $cfdi->save();
    }

    public function updateStatus(CfdiCliente $cfdi, CfdiStatus $status): bool
    {
        $cfdi->estado = $status->value;
        return $cfdi->save();
    }

    public function deleteCfdi(CfdiCliente $cfdi): bool
    {
        $fileDeleted = true;

        if (is_file(self::DESTINATION_FOLDER . $cfdi->filename)) {
            $fileDeleted = unlink(self::DESTINATION_FOLDER . $cfdi->filename);
        }

        if ($fileDeleted) {
            $cfdi->estado = 'eliminado';
            $cfdi->filename = null;
            return $cfdi->save();
        }

        return false;
    }

    public function getXml(CfdiCliente $cfdi): ?string
    {
        $filePath = self::DESTINATION_FOLDER . $cfdi->filename;

        if (is_file($filePath)) {
            return file_get_contents($filePath) ?: null;
        }

        Tools::log()->warning('Archivo XML no encontrado: ' . $filePath);
        return null;
    }

    public function cfdiFilePath(CfdiCliente $cfdi): ?string
    {
        $filePath = self::DESTINATION_FOLDER . $cfdi->filename;

        if (is_file($filePath)) {
            return $filePath;
        }

        return null;
    }

    public function findByUuid(string $uuid): ?CfdiCliente
    {
        $cfdi = new CfdiCliente();
        $cfdi->loadFromUuid($uuid);

        return $cfdi;
    }
}
