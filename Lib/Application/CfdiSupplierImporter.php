<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;

class CfdiSupplierImporter
{
    public const DESTINATION_FOLDER = FS_FOLDER . '/MyFiles/CFDI/supplier/';

    protected string $fileName;
    protected CfdiQuickReader $reader;
    protected Proveedor $supplier;
    protected Empresa $company;

    public function processUpload(?UploadedFile $uploadFile, Empresa $company): ?CfdiProveedor
    {
        if (null === $uploadFile) {
            throw new Exception('No se pudo obtener el archivo. ' . $uploadFile->getClientOriginalName());
        }

        if (!$this->saveUploadedFile($uploadFile)) {
            throw new Exception('Error al guardar el archivo.');
        }

        $this->loadReader();
        $this->loadOrCreateSupplier();
        $this->company = $company;

        if ($this->cfdiExists()) {
            throw new Exception('El CFDI ya fue registrado previamente. ' . $this->reader->uuid());
        }

        return $this->saveCfdi();
    }

    protected function saveUploadedFile(UploadedFile $uploadFile): bool
    {
        Tools::folderCheckOrCreate(self::DESTINATION_FOLDER);

        if (!$uploadFile || false === $uploadFile->isValid()) {
            return false;
        }

        $destinationName = $uploadFile->getClientOriginalName();

        if (file_exists(self::DESTINATION_FOLDER . $destinationName)) {
            $destinationName = date('Ymd_His') . '_' . $destinationName;
        }

        $moveFile = $uploadFile->move(self::DESTINATION_FOLDER, $destinationName);
        if ($moveFile) {
            $this->fileName = $destinationName;
            return true;
        }

        return false;
    }

    protected function loadReader(): void
    {
        $fileContent = file_get_contents(self::DESTINATION_FOLDER . $this->fileName);
        $this->reader = new CfdiQuickReader($fileContent);
    }

    protected function loadOrCreateSupplier(): void
    {
        $this->supplier = new Proveedor();
        $where = [new DataBaseWhere('cifnif', $this->reader->emisorRfc())];

        if (!$this->supplier->loadFromCode('', $where)) {
            $this->supplier->cifnif = $this->reader->emisorRfc();
            $this->supplier->nombre = $this->reader->emisorNombre();

            if (!$this->supplier->save()) {
                throw new Exception('Error al guardar el proveedor.');
            }
        }
    }

    protected function cfdiExists(): bool
    {
        $cfdi = new CfdiProveedor();
        return $cfdi->loadFromUuid($this->reader->uuid());
    }

    protected function saveCfdi(): CfdiProveedor
    {
        $cfdi = new CfdiProveedor();
        $cfdi->codproveedor = $this->supplier->codproveedor;
        $cfdi->coddivisa = "MXN";
        $cfdi->estado = "vigente";
        $cfdi->receptor_rfc = $this->reader->receptorRfc();
        $cfdi->receptor_nombre = $this->reader->receptorNombre();
        $cfdi->emisor_rfc = $this->reader->emisorRfc();
        $cfdi->emisor_nombre = $this->reader->emisorNombre();
        $cfdi->fecha_emision = $this->reader->fechaExpedicion();
        $cfdi->fecha_timbrado = $this->reader->fechaTimbrado();
        $cfdi->folio = $this->reader->folio();
        $cfdi->forma_pago = $this->reader->formaPago();
        $cfdi->idempresa = $this->company->idempresa;
        $cfdi->metodo_pago = $this->reader->metodoPago();
        $cfdi->serie = $this->reader->serie();
        $cfdi->tipo = $this->reader->tipoComprobamte();
        $cfdi->total = $this->reader->total();
        $cfdi->uuid = $this->reader->uuid();
        $cfdi->version = $this->reader->version();
        $cfdi->filename = $this->fileName;

        if (!$cfdi->save()) {
            throw new Exception('Error al guardar el CFDI.');
        }

        return $cfdi;
    }
}
