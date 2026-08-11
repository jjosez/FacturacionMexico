<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Register;

use Exception;
use Throwable;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiScope;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\Storage\CfdiStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\Storage\CfdiStorageInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Status\StatusService;

class CfdiImporter
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

        $this->company = $company;
        if (
            $this->cfdiExists()
            || $this->storage->exists(CfdiScope::SUPPLIER, $this->data->uuid)
        ) {
            throw new Exception('El CFDI ya fue registrado previamente. ' . $this->data->uuid);
        }

        $dataBase = new DataBase();
        if ($dataBase->inTransaction()) {
            throw new Exception('No se puede registrar un CFDI de proveedor dentro de otra transacción.');
        }

        $storageSaved = false;
        $cfdi = null;

        try {
            if (!$dataBase->beginTransaction()) {
                throw new Exception('No se pudo iniciar la transacción del CFDI de proveedor.');
            }

            $this->loadOrCreateSupplier();
            $cfdi = $this->saveCfdi();
            $this->storage->save(CfdiScope::SUPPLIER, $cfdi->uuid, $this->xml);
            $storageSaved = true;

            if (!$dataBase->commit()) {
                throw new Exception('No se pudo confirmar la transacción del CFDI de proveedor.');
            }
        } catch (Throwable $e) {
            if ($dataBase->inTransaction()) {
                $dataBase->rollback();
            }

            if ($storageSaved && $cfdi !== null) {
                try {
                    $this->storage->delete(CfdiScope::SUPPLIER, $cfdi->uuid);
                } catch (Throwable) {
                    // Preserve the original transaction failure.
                }
            }

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
        $where = [new DataBaseWhere('cifnif', $this->data->emisorRfc)];

        if (!$this->supplier->loadFromCode('', $where)) {
            $this->supplier->cifnif = $this->data->emisorRfc;
            $this->supplier->nombre = $this->data->emisorNombre;

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
        $cfdi->estado = StatusService::STATUS_IMPORTED;
        $cfdi->receptor_rfc = $this->data->receptorRfc;
        $cfdi->receptor_nombre = $this->data->receptorNombre;
        $cfdi->emisor_rfc = $this->data->emisorRfc;
        $cfdi->emisor_nombre = $this->data->emisorNombre;
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
