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
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiSupplierInvoiceImporter;

class CfdiSupplierWizard extends Controller
{
    public CfdiProveedor $cfdi;
    public CfdiQuickReader $reader;
    public Proveedor $supplier;

    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'Importar Proveedor CFDI';
        $pagedata['icon'] = 'fas fa-file-import';
        $pagedata['menu'] = 'CFDI';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $action = $this->request->get('action', '');

        if ($this->execPreviousAction($action)) {
            return;
        }

        $this->initWizard();

        $this->execAction($action);
        $this->setTemplate('CfdiProveedorImporter');
    }

    protected function execAction(string $action): void
    {
        switch ($action) {
            case 'import-supplier-cfdi':
                $this->importCfdiAction();
                break;

            default:
                break;
        }
    }

    protected function execPreviousAction(string $action): bool
    {
        switch ($action) {
            case 'search-own-product':
                $this->searchProduct();
                return true;
            default:
                return false;
        }
    }

    protected function initWizard(): void
    {
        $code = $this->request->get('code');

        $this->cfdi = new CfdiProveedor();
        $this->cfdi->loadFromCode($code);

        $this->loadSupplier();
        $this->loadCfdiReader();
    }

    protected function searchProduct(): void
    {
        $query = $this->request->request->get('query');

        $where = [
            Where::like('referencia', $query),
            Where::orLike('descripcion', $query),
        ];

        if (Plugins::isEnabled('SKU')) {
            array_unshift($where, Where::orLike('referencia_fabricante', $query));
        }

        $result = json_encode(Producto::table()->where($where)->get());
        $this->response->setContent($result);
    }

    protected function importCfdiAction(): void
    {
        try {
            $importer = new CfdiSupplierInvoiceImporter();
            $invoice = $importer->import(
                $this->cfdi,
                $this->supplier,
                $this->request->request->get('conceptos', [])
            );

            $this->redirect($invoice->url());
        } catch (Exception $e) {
        }
    }

    protected function loadSupplier(): void
    {
        $this->supplier = $this->cfdi->getSupplier();
    }

    protected function loadCfdiReader(): void
    {
        try {
            $fileContent = $this->cfdi->localFileContent();
            $this->reader = new CfdiQuickReader($fileContent);
        } catch (Exception $e) {
            Tools::log()->error($e->getMessage());
        }
    }

    public function buildNewProductUrl($code, $description): string
    {
        $referenceColumn = $this->getReferenceColumn();
        $format = 'EditProducto?%s=%s&descripcion=%s';

        return sprintf($format, $referenceColumn, rawurlencode($code), rawurlencode($description));
    }

    protected function getReferenceColumn(): string
    {
        if (Plugins::isEnabled('SKU')) {
            return 'referencia_fabricante';
        }

        return 'referencia';
    }
}

