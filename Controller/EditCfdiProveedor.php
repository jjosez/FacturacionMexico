<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiProveedor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

/**
 * Este es un controlador específico para ediciones. Permite una o varias pestañas.
 * Cada una con un xml y modelo diferente, puede ser de tipo edición, listado, html o panel.
 * Además, hace uso de archivos de XMLView para definir qué columnas mostrar y cómo.
 *
 * https://facturascripts.com/publicaciones/editcontroller-642
 */
class EditCfdiProveedor extends EditController
{
    const DESTINATION_FOLDER = FS_FOLDER . '/MyFiles/CFDI/supplier/';

    public function getModelClassName(): string
    {
        return 'CfdiProveedor';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'CFDI';
        $data['title'] = 'EditCfdiProveedor';
        $data['icon'] = 'fa-solid fa-search';
        return $data;
    }

    public function execPreviousAction($action)
    {
        if ($action === 'insert') {

            $fileName = $this->processFile();

            if (empty($fileName)) {
                return;
            }

            $fileContent = file_get_contents(self::DESTINATION_FOLDER . $fileName);
            $reader = new CfdiQuickReader($fileContent);
            $supplier = $this->loadSupplier($reader);

            $cfdi = $this->getModel();
            $cfdi->codproveedor = $supplier->codproveedor;
            $cfdi->coddivisa = "MXN";
            $cfdi->estado = "vigente";
            $cfdi->receptor_rfc = $reader->receptorRfc();
            $cfdi->receptor_nombre = $reader->receptorRfc();
            $cfdi->emisor_rfc = $reader->receptorRfc();
            $cfdi->emisor_nombre = $reader->receptorNombre();
            $cfdi->emisor_rfc = $reader->emisorRfc();
            $cfdi->emisor_nombre = $reader->emisorNombre();
            $cfdi->fecha_emision = $reader->fechaExpedicion();
            $cfdi->fecha_timbrado = $reader->fechaTimbrado();
            $cfdi->folio = $reader->folio();
            $cfdi->forma_pago = $reader->formaPago();
            $cfdi->idempresa = $this->empresa->idempresa;
            $cfdi->metodo_pago = $reader->metodoPago();
            $cfdi->serie = $reader->serie();
            $cfdi->tipo = $reader->tipoComprobamte();
            $cfdi->uuid = $reader->uuid();
            $cfdi->version = $reader->version();

            $cfdi->filename = $fileName;
            $cfdi->save();

            return;
        }

        parent::execPreviousAction($action);
    }

    protected function loadSupplier(CfdiQuickReader $reader): Proveedor
    {
        $supplier = new Proveedor();

        $where = [
            new DataBaseWhere('cifnif', $reader->emisorRfc())
        ];

        if ($supplier->loadFromCode('', $where)) {
            return $supplier;
        }

        $supplier->cifnif = $reader->emisorRfc();
        $supplier->nombre = $reader->emisorNombre();

        $supplier->save();

        return $supplier;
    }

    public function processFile(): string
    {
        Tools::folderCheckOrCreate(self::DESTINATION_FOLDER);
        $uploadFile = $this->request->files->get('cfdifile');

        if (!$uploadFile || false === $uploadFile->isValid()) {
            return '';
        }

        $destinationName = $uploadFile->getClientOriginalName();
        if (file_exists(self::DESTINATION_FOLDER . $destinationName)) {
            $destinationName = mt_rand(1, 999999) . '_' . $destinationName;
        }

        Tools::log()->warning('Move file: ' . $destinationName);
        $moveFile = $uploadFile->move(self::DESTINATION_FOLDER, $destinationName);

        if ($moveFile) {
            return $destinationName;
        }

        return '';
    }
}
