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
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\CfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\InvoiceImportService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\Options\ImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\Result\BatchResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Queue\AsyncImportProcessor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Queue\ImportQueue;
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
            $this->batchImportCfdiAction();
            return true;
        }

        if ($action === 'batch-import-process') {
            $this->batchImportProcessAction();
            return true;
        }

        if ($action === 'async-import') {
            $this->asyncImportAction();
            return true;
        }

        if ($action === 'import-status') {
            $this->importStatusAction();
            return true;
        }

        if ($action === 'process-import-queue') {
            $this->processImportQueueAction();
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
            Tools::log('CFDI')->warning($e->getMessage());
        }
    }

    private function batchImportCfdiAction()
    {
        $this->setTemplate('BatchImportModal');
    }

    protected function batchImportProcessAction(): void
    {
        $this->setTemplate(false);

        $files = $this->request->files->get('xmlfiles');
        if (empty($files)) {
            $this->response->setContent(json_encode([
                'success' => false,
                'error' => 'No se recibieron archivos'
            ]));
            return;
        }

        $files = is_array($files) ? $files : [$files];

        $importer = new CfdiImporter();
        $service = new InvoiceImportService();

        $options = ImportOptions::fromArray([
            'product_action' => $this->request->get('product_action', 'auto'),
            'tax_mode' => $this->request->get('tax_mode', 'preserve'),
            'update_supplier_prices' => $this->requestBoolean('update_supplier_prices'),
            'auto_match_products' => $this->requestBoolean('auto_match_products'),
            'price_multiplier' => (float)$this->request->input('price_multiplier', 1.0),
        ]);

        $result = new BatchResult();
        $result->setTotal(count($files));

        $importedCfdis = [];
        $uploadErrors = [];

        foreach ($files as $file) {
            try {
                $cfdi = $importer->processUpload($file, $this->empresa);
                $importedCfdis[] = $cfdi;
            } catch (Exception $e) {
                $uploadErrors[] = $e->getMessage();
            }
        }

        $createInvoices = $this->requestBoolean('create_invoices');

        if ($createInvoices && !empty($importedCfdis)) {
            $invoiceResult = $service->importBatch($importedCfdis, $options);
            $result = $invoiceResult;
        } else {
            foreach ($importedCfdis as $cfdi) {
                $result->addSuccess($cfdi->uuid, $cfdi->id);
            }
        }

        foreach ($uploadErrors as $error) {
            $result->addFailure('', $error);
        }

        $result->setTotal(count($files));

        $result->setElapsedTime(0);

        $this->response->setContent(json_encode([
            'success' => $result->success > 0,
            'results' => $result->toArray()
        ]));
    }

    protected function loadData($viewName, $view): void
    {
        if ($viewName === 'ListCfdiProveedor') {
            $where = [new DataBaseWhere('tipo', 'P', '!=')];

            $view->loadData('', $where);
        }

        parent::loadData($viewName, $view);
    }

    protected function asyncImportAction(): void
    {
        $this->setTemplate(false);

        $files = $this->request->files->get('xmlfiles');

        if (empty($files)) {
            $this->response->setContent(json_encode([
                'success' => false,
                'error' => 'No se recibieron archivos'
            ]));
            return;
        }

        $files = is_array($files) ? $files : [$files];

        $user = $this->user;

        try {
            $queue = new ImportQueue();
            $options = ImportOptions::fromArray([
                'product_action' => $this->request->get('product_action', 'auto'),
                'tax_mode' => $this->request->get('tax_mode', 'preserve'),
                'update_supplier_prices' => $this->requestBoolean('update_supplier_prices'),
                'auto_match_products' => $this->requestBoolean('auto_match_products', true),
                'price_multiplier' => (float)$this->request->input('price_multiplier', 1.0),
            ]);
            $jobId = $queue->enqueue($files, $this->empresa->idempresa, (int)($user->id ?? 0), $options->toArray());

            $this->response->setContent(json_encode([
                'success' => true,
                'job_id' => $jobId,
                'message' => 'Trabajo agregado a la cola de procesamiento'
            ]));
        } catch (Exception $e) {
            $this->response->setContent(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    protected function importStatusAction(): void
    {
        $this->setTemplate(false);

        $jobId = $this->request->get('job_id');

        if (empty($jobId)) {
            $this->response->setContent(json_encode([
                'success' => false,
                'error' => 'Job ID requerido'
            ]));
            return;
        }

        $queue = new ImportQueue();
        $status = $queue->getStatus($jobId);

        if ($status === null) {
            $this->response->setContent(json_encode([
                'success' => false,
                'error' => 'Job no encontrado'
            ]));
            return;
        }

        $this->response->setContent(json_encode([
            'success' => true,
            'status' => $status
        ]));
    }

    protected function processImportQueueAction(): void
    {
        $this->setTemplate(false);

        $processor = new AsyncImportProcessor();
        $processed = $processor->processAll(10);

        $this->response->setContent(json_encode([
            'success' => true,
            'processed' => $processed
        ]));
    }

    protected function requestBoolean(string $field, bool $default = false): bool
    {
        $value = $this->request->input($field);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
