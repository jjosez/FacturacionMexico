<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiParsedData;

class SupplierCfdiImporter
{
    public const DESTINATION_FOLDER = FS_FOLDER . '/MyFiles/CFDI/supplier/';

    protected string $fileName;
    protected CfdiParsedData $data;
    protected Proveedor $supplier;
    protected Empresa $company;

    public function processUpload(?UploadedFile $uploadFile, Empresa $company): CfdiProveedor
    {
        if (null === $uploadFile) {
            throw new Exception('No se pudo obtener el archivo.');
        }

        if (!$this->saveUploadedFile($uploadFile)) {
            throw new Exception('Error al guardar el archivo.');
        }

        $this->loadData();
        $this->loadOrCreateSupplier();
        $this->company = $company;

        if ($this->cfdiExists()) {
            throw new Exception('El CFDI ya fue registrado previamente. ' . $this->data->uuid);
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

    protected function loadData(): void
    {
        $fileContent = file_get_contents(self::DESTINATION_FOLDER . $this->fileName);
        if ($fileContent === false || $fileContent === '') {
            throw new Exception('No se pudo leer el archivo XML del CFDI.');
        }

        $this->data = (new CfdiParser($fileContent))->parse();
    }

    protected function loadOrCreateSupplier(): void
    {
        $this->supplier = new Proveedor();
        $where = [new DataBaseWhere('cifnif', $this->data->issuerRfc)];

        if (!$this->supplier->loadFromCode('', $where)) {
            $this->supplier->cifnif = $this->data->issuerRfc;
            $this->supplier->nombre = $this->data->issuerName;

            if (!$this->supplier->save()) {
                throw new Exception('Error al guardar el proveedor.');
            }
        }
    }

    protected function cfdiExists(): bool
    {
        $cfdi = new CfdiProveedor();
        return $cfdi->loadFromUuid($this->data->uuid);
    }

    protected function saveCfdi(): CfdiProveedor
    {
        $cfdi = new CfdiProveedor();
        $cfdi->codproveedor = $this->supplier->codproveedor;
        $cfdi->coddivisa = $this->data->currency;
        $cfdi->estado = SupplierCfdiStatusService::STATUS_IMPORTED;
        $cfdi->receptor_rfc = $this->data->recipientRfc;
        $cfdi->receptor_nombre = $this->data->recipientName;
        $cfdi->emisor_rfc = $this->data->issuerRfc;
        $cfdi->emisor_nombre = $this->data->issuerName;
        $cfdi->fecha_emision = $this->data->issueDate;
        $cfdi->fecha_timbrado = $this->data->stampedAt;
        $cfdi->folio = $this->data->folio;
        $cfdi->forma_pago = $this->data->paymentForm;
        $cfdi->idempresa = $this->company->idempresa;
        $cfdi->metodo_pago = $this->data->paymentMethod;
        $cfdi->serie = $this->data->series;
        $cfdi->tipo = $this->data->type;
        $cfdi->total = $this->data->total;
        $cfdi->uuid = $this->data->uuid;
        $cfdi->version = $this->data->version;
        $cfdi->filename = $this->fileName;

        if (!$cfdi->save()) {
            throw new Exception('Error al guardar el CFDI.');
        }

        return $cfdi;
    }
}
