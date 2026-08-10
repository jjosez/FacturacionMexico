<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi;

use Exception;
use FacturaScripts\Core\UploadedFile;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierCfdiRepository;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierRepository;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Filesystem\SupplierCfdiFileStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;

class SupplierCfdiUploadService
{
    protected string $fileName;
    protected CfdiQuickReader $reader;
    protected Proveedor $supplier;
    protected Empresa $company;
    private SupplierCfdiRepository $cfdiRepository;
    private SupplierRepository $supplierRepository;
    private SupplierCfdiFileStorage $fileStorage;

    public function __construct(
        ?SupplierCfdiRepository $cfdiRepository = null,
        ?SupplierRepository $supplierRepository = null,
        ?SupplierCfdiFileStorage $fileStorage = null
    )
    {
        $this->cfdiRepository = $cfdiRepository ?? new SupplierCfdiRepository();
        $this->supplierRepository = $supplierRepository ?? new SupplierRepository();
        $this->fileStorage = $fileStorage ?? new SupplierCfdiFileStorage();
    }

    public function processUpload(?UploadedFile $uploadFile, Empresa $company): ?CfdiProveedor
    {
        if (null === $uploadFile) {
            throw new Exception('No se pudo obtener el archivo. ' . $uploadFile->getClientOriginalName());
        }

        $this->fileName = $this->fileStorage->store($uploadFile) ?? '';
        if ($this->fileName === '') {
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

    protected function loadReader(): void
    {
        $this->reader = new CfdiQuickReader($this->fileStorage->read($this->fileName));
    }

    protected function loadOrCreateSupplier(): void
    {
        $this->supplier = $this->supplierRepository->findByRfc($this->reader->emisorRfc())
            ?? $this->supplierRepository->create();

        if (empty($this->supplier->codproveedor)) {
            $this->supplier->cifnif = $this->reader->emisorRfc();
            $this->supplier->nombre = $this->reader->emisorNombre();

            if (!$this->supplierRepository->save($this->supplier)) {
                throw new Exception('Error al guardar el proveedor.');
            }
        }
    }

    protected function cfdiExists(): bool
    {
        return $this->cfdiRepository->existsByUuid($this->reader->uuid());
    }

    protected function saveCfdi(): CfdiProveedor
    {
        $cfdi = $this->cfdiRepository->create();
        $cfdi->codproveedor = $this->supplier->codproveedor;
        $cfdi->coddivisa = "MXN";
        $cfdi->estado = SupplierCfdiStatusService::STATUS_IMPORTED;
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

        if (!$this->cfdiRepository->save($cfdi)) {
            throw new Exception('Error al guardar el CFDI.');
        }

        return $cfdi;
    }
}
