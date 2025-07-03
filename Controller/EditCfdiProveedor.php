<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use Exception;
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

    protected string $fileName;
    protected CfdiQuickReader $reader;
    protected Proveedor $supplier;

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

    protected function createViews()
    {
        parent::createViews();
    }

    protected function addProcessButton(string $url)
    {
        $this->addButton('EditCfdiProveedor', [
            'action' => $url,
            'icon' => 'fas fa-plus',
            'label' => 'Procesar',
            'type' => 'link'
        ]);
    }

    public function execPreviousAction($action)
    {
        if ($action === 'insert') {

            if (!$this->processFile() || !$this->loadReader()) {
                return;
            }

            if (!$this->loadSupplier()) {
                Tools::log()->warning('Error al seleccionar el proveedor');
                return;
            }

            if ($this->testCfdiExists()) {
                Tools::log()->warning('El cfdi ya fue registrado');
                return;
            }

            $reader = $this->reader;

            $cfdi = $this->getModel();
            $cfdi->codproveedor = $this->supplier->codproveedor;
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

            $cfdi->filename = $this->fileName;
            $cfdi->save();

            return;
        }

        parent::execPreviousAction($action);
    }

    protected function loadReader(): bool
    {
        try {
            $fileContent = file_get_contents(self::DESTINATION_FOLDER . $this->fileName);
            $this->reader = new CfdiQuickReader($fileContent);

            return true;
        } catch (Exception $e) {
            Tools::log()->warning('Error al cargar el archivo ' . $this->fileName);
            Tools::log()->warning($e->getMessage());
            return false;
        }
    }

    protected function loadSupplier(): bool
    {
        $this->supplier = new Proveedor();

        $where = [
            new DataBaseWhere('cifnif', $this->reader->emisorRfc())
        ];

        if ($this->supplier->loadFromCode('', $where)) {
            return true;
        }

        $this->supplier->cifnif = $this->reader->emisorRfc();
        $this->supplier->nombre = $this->reader->emisorNombre();

        return $this->supplier->save();
    }

    protected function processFile(): bool
    {
        Tools::folderCheckOrCreate(self::DESTINATION_FOLDER);
        $uploadFile = $this->request->files->get('cfdifile');

        if (!$uploadFile || false === $uploadFile->isValid()) {
            return false;
        }

        $destinationName = $uploadFile->getClientOriginalName();
        if (file_exists(self::DESTINATION_FOLDER . $destinationName)) {
            $destinationName = mt_rand(1, 999999) . '_' . $destinationName;
        }

        $moveFile = $uploadFile->move(self::DESTINATION_FOLDER, $destinationName);
        if ($moveFile) {
            $this->fileName = $destinationName;

            return true;
        }

        return false;
    }

    protected function testCfdiExists(): bool
    {
        $cfdi = new CfdiProveedor();

        if ($cfdi->loadFromUuid($this->reader->uuid())) {
            return true;
        }
        return false;
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'EditCfdiProveedor') {
            if ($this->getModel()->primaryColumnValue()) {
                $url = $this->getModel()->url('wizard');

                $this->addProcessButton($url);
            }
        }

    }
}
