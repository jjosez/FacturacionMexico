<?php
/**
 * This file is part of POS plugin for FacturaScripts
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
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Register\CfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Status\StatusService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\SAT\CfdiCatalogo;

/**
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class ListCfdiProveedor extends ExtendedController\ListController
{

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'CFDI Proveedores';
        $pagedata['icon'] = 'fa-solid fa-truck-field';
        $pagedata['menu'] = 'CFDI';

        return $pagedata;
    }

    protected function execPreviousAction($action)
    {
        if ($action === 'import-cfdi-file') {
            $this->importCfdiAction();
            return true;
        }

        if ($action === 'batch-import-cfdi-file') {
            $this->redirect('CfdiSupplierImport');
            return true;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->createMainView();
    }

    protected function createMainView($viewName = 'ListCfdiProveedor'): void
    {
        $this->addView($viewName, 'CfdiProveedor', 'CFDI Proveedores', 'fas fa-file-invoice');
        $this->addSearchFields($viewName, ['emisor_nombre', 'emisor_rfc', 'uuid']);
        $this->addOrderBy($viewName, ['fecha_emision'], 'Fecha emision', 2);
        $this->addOrderBy($viewName, ['fecha_timbrado'], 'Fecha timbrado', 2);

        $this->addFilterAutocomplete($viewName, 'supplier', 'supplier', 'codproveedor', 'proveedores', 'codproveedor', 'razonsocial');
        $this->addFilterPeriod($viewName, 'date', 'period', 'fecha_emision');
        $this->addFilterSelect($viewName, 'tipo', 'type', 'tipo', CfdiCatalogo::tipoCfdi());
        $this->addFilterSelect($viewName, 'estado', 'state', 'estado', StatusService::filterOptions());

        $this->setSettings($viewName, 'btnNew', false);
        //$this->setSettings($viewName, 'btnDelete', false);
    }

    protected function importCfdiAction(): void
    {
        try {
            $importer = new CfdiImporter();
            $uploadedFile = $this->request->files->get('cfdifile');
            $cfdi = $importer->processUpload($uploadedFile, $this->empresa);

            Tools::log()->info('CFDI importado correctamente: ' . $cfdi->uuid);

            $this->redirect($cfdi->url());
        } catch (Exception $e) {
            Tools::log()->warning($e->getMessage());
        }
    }

    protected function loadData($viewName, $view): void
    {
        if ($viewName === 'ListCfdiProveedor') {
            $where = [
                new DataBaseWhere('tipo', 'P', '!='),
                new DataBaseWhere('idempresa', $this->empresa->idempresa),
            ];

            if ($this->permissions->onlyOwnerData) {
                array_push($where, ...$this->getOwnerFilter($view->model));
            }

            $view->loadData('', $where);
            return;
        }

        parent::loadData($viewName, $view);
    }

}
