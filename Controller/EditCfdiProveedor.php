<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use Exception;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Extension\Controller\FormaPagoControllerTrait;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\CfdiSupplierImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiProveedor;

class EditCfdiProveedor extends EditController
{
    use FormaPagoControllerTrait;

    const string DESTINATION_FOLDER = FS_FOLDER . '/MyFiles/CFDI/supplier/';

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
        $data['icon'] = 'fa-solid fa-file-import';
        return $data;
    }

    protected function createViews(): void
    {
        parent::createViews();

        $column = $this->tab('EditCfdiProveedor')->columnForName('forma-pago');
        $widgetClosure = $this->createFormaPagoWidget();
        $widgetClosure($column);
    }

    protected function addImportWizardButton(string $url): void
    {
        $this->addButton('EditCfdiProveedor', [
            'action' => $url,
            'color' => 'info',
            'icon' => 'fa-solid fa-truck-field',
            'label' => 'Asistente de importación',
            'type' => 'link'
        ]);
    }

    public function execPreviousAction($action): void
    {
        if ($action === 'import-cfdi-file') {
            $this->importCfdiAction();

            return;
        }

        parent::execPreviousAction($action);
    }

    protected function importCfdiAction(): void
    {
        try {
            $importer = new CfdiSupplierImporter();
            $uploadedFile = $this->request->files->get('cfdifile');
            $cfdi = $importer->processUpload($uploadedFile, $this->empresa);

            Tools::log()->info('CFDI importado correctamente: ' . $cfdi->uuid);
        } catch (Exception $e) {
            Tools::log()->warning($e->getMessage());
        }
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

        if ($this->supplier->loadWhereEq('cifnif', $this->reader->emisorRfc())) {
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

    protected function loadData($viewName, $view): void
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'EditCfdiProveedor') {
            if ($this->getModel()->primaryColumnValue()) {
                $url = $this->getModel()->url('wizard');

                $this->addImportWizardButton($url);
            }
        }
    }
}
