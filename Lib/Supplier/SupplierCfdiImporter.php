<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Supplier;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiScope;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Storage\CfdiStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Storage\CfdiStorageInterface;

class SupplierCfdiImporter
{
    protected string $xml;
    protected CfdiData $data;
    protected Proveedor $supplier;
    protected Empresa $company;
    private CfdiStorageInterface $storage;

    public function __construct(?CfdiStorageInterface $storage = null)
    {
        $this->storage = $storage ?? CfdiStorage::get();
    }

    public function processUpload(?UploadedFile $uploadFile, Empresa $company): CfdiProveedor
    {
        if (null === $uploadFile) {
            throw new Exception('No se pudo obtener el archivo.');
        }

        $this->loadData($uploadFile);
        if ($this->data->uuid === null) {
            throw new Exception('El XML no contiene un TimbreFiscalDigital.');
        }

        $this->loadOrCreateSupplier();
        $this->company = $company;

        if ($this->cfdiExists()) {
            throw new Exception('El CFDI ya fue registrado previamente. ' . $this->data->uuid);
        }

        $cfdi = $this->saveCfdi();
        try {
            $this->storage->save(CfdiScope::SUPPLIER, $cfdi->uuid, $this->xml);
        } catch (Exception $e) {
            $cfdi->delete();
            throw $e;
        }

        return $cfdi;
    }

    protected function loadData(UploadedFile $uploadFile): void
    {
        if (!$uploadFile->isValid()) {
            throw new Exception('Error al leer el archivo.');
        }

        $xml = file_get_contents($uploadFile->getPathname());
        if ($xml === false || $xml === '') {
            throw new Exception('No se pudo leer el archivo XML del CFDI.');
        }

        $this->xml = $xml;
        $this->data = (new CfdiParser($this->xml))->parse();
    }

    protected function loadOrCreateSupplier(): void
    {
        $this->supplier = new Proveedor();
        $where = [new DataBaseWhere('cifnif', $this->data->emisor['rfc'])];

        if (!$this->supplier->loadFromCode('', $where)) {
            $this->supplier->cifnif = $this->data->emisor['rfc'];
            $this->supplier->nombre = $this->data->emisor['nombre'];

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
        $cfdi->coddivisa = $this->data->moneda;
        $cfdi->estado = SupplierCfdiStatusService::STATUS_IMPORTED;
        $cfdi->receptor_rfc = $this->data->receptor['rfc'];
        $cfdi->receptor_nombre = $this->data->receptor['nombre'];
        $cfdi->emisor_rfc = $this->data->emisor['rfc'];
        $cfdi->emisor_nombre = $this->data->emisor['nombre'];
        $cfdi->fecha_emision = $this->data->fecha;
        $cfdi->fecha_timbrado = $this->data->fechaTimbrado;
        $cfdi->filename = '';
        $cfdi->folio = $this->data->folio;
        $cfdi->forma_pago = $this->data->formaPago;
        $cfdi->idempresa = $this->company->idempresa;
        $cfdi->metodo_pago = $this->data->metodoPago;
        $cfdi->serie = $this->data->serie;
        $cfdi->tipo = $this->data->tipoComprobante;
        $cfdi->total = $this->data->total;
        $cfdi->uuid = $this->data->uuid;
        $cfdi->version = $this->data->version;
        if (!$cfdi->save()) {
            throw new Exception('Error al guardar el CFDI.');
        }

        return $cfdi;
    }
}
