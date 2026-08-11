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
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Extension\Controller\FormaPagoControllerTrait;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Register\CfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\InvoiceImportService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options\ImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Read\CfdiReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Status\StatusService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierInvoiceStateService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierProductLinkService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared\CfdiData;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiProveedor;

class EditCfdiProveedor extends EditController
{
    use FormaPagoControllerTrait;

    protected string $fileName = '';
    protected ?CfdiData $reader = null;
    protected ?Proveedor $supplier = null;
    protected array $conceptosProductMap = [];

    public function getModelClassName(): string
    {
        return 'CfdiProveedor';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'CFDI';
        $data['title'] = 'CFDI Proveedor';
        $data['icon'] = 'fa-solid fa-file-import';
        return $data;
    }

    protected function createViews(): void
    {
        parent::createViews();

        $column = $this->tab('EditCfdiProveedor')->columnForName('forma-pago');
        $widgetClosure = $this->createFormaPagoWidget();
        $widgetClosure($column);

        $this->createViewCfdiSupplier();
        $this->setTabsPosition('top');
    }

    protected function createViewCfdiSupplier($viewName = 'CfdiSupplier'): void
    {
        $this->addHtmlView(
            $viewName,
            'CfdiSupplier',
            'CfdiProveedor',
            'preview',
            'fa-solid fa-file-invoice'
        );
    }

    protected function loadData($viewName, $view): void
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'EditCfdiProveedor') {
            if ($this->getModel()->primaryColumnValue()) {
                $this->tab($viewName)->setReadOnly(true);

                $canImport = true;
                if (!empty($this->getModel()->idfactura)) {
                    $invoice = new FacturaProveedor();
                    $canImport = $invoice->load($this->getModel()->idfactura)
                        && (new SupplierInvoiceStateService())->isEditable($invoice);
                }

                if ($canImport) {
                    $view->addButton([
                        'action' => 'cfdi-to-invoice-wizard',
                        'color' => 'success',
                        'icon' => 'fa-solid fa-magic',
                        'label' => 'Importar Factura',
                        'type' => 'action'
                    ]);
                }

                $this->fileName = $this->getModel()->filename;
                $this->reader = (new CfdiReader())->data($this->getModel());
                $this->supplier = $this->getModel()->getSupplier();
            }

            return;
        }

        if ($viewName === 'CfdiSupplier') {
            $this->conceptosProductMap = $this->mapConceptosToProductos();
        }
    }

    public function execPreviousAction($action): void
    {
        if ($action === 'import-cfdi-file') {
            $this->importCfdiAction();
            return;
        }

        if ($action === 'search-products') {
            $this->searchProductsAction();
            return;
        }

        if ($action === 'link-product') {
            $this->linkProductAction();
            return;
        }

        if ($action === 'cfdi-to-invoice-wizard') {
            $this->openWizardAction();
            return;
        }

        parent::execPreviousAction($action);
    }

    protected function execAfterAction($action): void
    {
        parent::execAfterAction($action);

        if ($action === 'import-cfdi-to-invoice' && $this->fileName !== '' && $this->supplier !== null) {
            try {
                $conceptos = $this->mapConceptosToInvoice();

                $result = (new InvoiceImportService())->importSingle(
                    $this->getModel(),
                    $this->supplier,
                    new ImportOptions([
                        'productAction' => ImportOptions::PRODUCT_ACTION_SKIP,
                        'autoMatchProducts' => true,
                    ]),
                    $conceptos
                );

                if (!$result->success || $result->invoice === null) {
                    throw new Exception($result->error ?? 'No se pudo generar la factura del proveedor');
                }

                $this->redirect($result->invoice->url());
            } catch (Exception $e) {
                Tools::log('CFDI')->warning('Error al generar la factura:. ' . $e->getMessage());
            }
        }
    }

    protected function importCfdiAction(): void
    {
        $uploadedFile = $this->request->files->get('cfdifile');

        try {
            $importer = new CfdiImporter();
            $cfdi = $importer->processUpload($uploadedFile, $this->empresa);

            Tools::log()->info('CFDI importado correctamente: ' . $cfdi->uuid);
        } catch (Exception $e) {
            Tools::log('CFDI')->warning($e->getMessage());
        }
    }

    protected function searchProductsAction(): void
    {
        $query = $this->request->input('query');

        $where = [
            Where::orLike('referencia', $query),
            Where::orLike('descripcion', $query),
        ];

        if (Plugins::isEnabled('SKU')) {
            array_unshift($where, Where::orLike('referencia_fabricante', $query));
        }

        $result = [];
        foreach (Producto::all($where, [], 0, 20) as $product) {
            $result[] = $product->toArray(true);
        }

        $this->response()->json(['products' => $result]);
        $this->response()->send();
    }

    protected function linkProductAction(): void
    {
        $referencia = $this->request->input('referencia');
        $refproveedor = $this->request->input('refproveedor');
        $index = (int)$this->request->input('index', -1);
        $precio = (float)$this->request->input('precio', 0);
        $codproveedor = $this->request->input('codproveedor', '');

        if (empty($refproveedor) && $index >= 0 && isset($this->reader)) {
            $conceptos = $this->reader->conceptos;
            $refproveedor = $conceptos[$index]['NoIdentificacion'] ?? '';
        }

        $service = new SupplierProductLinkService();
        $result = $service->vincular(
            $referencia,
            $codproveedor,
            $refproveedor,
            $precio
        );

        $this->response()->json($result);
        $this->response()->send();
    }

    protected function openWizardAction(): void
    {
        $code = $this->request->get('code');

        if (empty($code)) {
            Tools::log('CFDI')->warning('No se ha seleccionado un CFDI');
            $this->redirect($this->getModel()->url());
            return;
        }

        $cfdi = new CfdiProveedor();
        $cfdi->load($code);

        if (empty($cfdi->primaryColumnValue())) {
            Tools::log('CFDI')->warning('No se pudo cargar el CFDI');
            return;
        }

        $wizardUrl = 'CfdiSupplierWizard?type=' . rawurlencode($cfdi->tipo)
            . '&code=' . rawurlencode($cfdi->primaryColumnValue());

        $this->redirect($wizardUrl);
    }

    /**
     * Mapea los conceptos del CFDI con productos del proveedor ya vinculados
     * Agrega el campo 'referencia_vinculada' a cada concepto
     *
     * @return array
     */
    public function mapConceptosToProductos(): array
    {
        if (!$this->reader || !$this->supplier) {
            return [];
        }

        $conceptos = $this->reader->conceptos;
        $codproveedor = $this->supplier->codproveedor;

        $productosProveedor = $this->getIndexedSupplierProducts($codproveedor);

        $countConceptos = count($conceptos);
        $countLinked = 0;
        foreach ($conceptos as &$concepto) {
            $refproveedor = $concepto['NoIdentificacion'] ?? '';

            // Buscar si existe una vinculación por refproveedor
            if (isset($productosProveedor[$refproveedor])) {
                $concepto['referencia_vinculada'] = $productosProveedor[$refproveedor]->referencia;
                $countLinked++;
            } else {
                $concepto['referencia_vinculada'] = '';
            }
        }

        if ($countConceptos === $countLinked) {
            /** @var CfdiProveedor $model */
            $model = $this->getModel();

            if ($model->estado !== StatusService::STATUS_LINKED) {
                if ((new StatusService())->markLinked($model)) {
                    Tools::log('CFDI')->notice('El CFDI se marcó cómo VINCULADO.');
                }
            }
        }

        return $conceptos;
    }

    public function mapConceptosToInvoice(): array
    {
        if (!$this->reader || !$this->supplier) {
            return [];
        }

        $codproveedor = $this->supplier->codproveedor;
        $productosProveedor = $this->getIndexedSupplierProducts($codproveedor);

        $conceptos = $this->reader->conceptos;
        foreach ($conceptos as &$concepto) {
            $refproveedor = $concepto['NoIdentificacion'] ?? '';

            if (isset($productosProveedor[$refproveedor])) {
                $producto = $productosProveedor[$refproveedor]->getProducto();
                $concepto['referencia'] = $producto->referencia;
                $concepto['referencia_proveedor'] = $productosProveedor[$refproveedor]->refproveedor;
            } else {
                $concepto['referencia_proveedor'] = '';
            }
        }

        return $conceptos;
    }

    /**
     * Obtiene los productos del proveedor indexados por refproveedor
     *
     * @param string $codproveedor
     * @return array [refproveedor => ProductoProveedor]
     */
    protected function getIndexedSupplierProducts(string $codproveedor): array
    {
        $productoProveedor = new ProductoProveedor();
        $where = [Where::eq('codproveedor', $codproveedor)];
        $productosProveedor = $productoProveedor->all($where);

        $indexados = [];
        foreach ($productosProveedor as $pp) {
            $indexados[$pp->refproveedor] = $pp;
        }

        return $indexados;
    }

    public function buildNewProductUrl($code, $description)
    {
        $referenceColumn = 'referencia';

        if (Plugins::isEnabled('SKU')) {
            $referenceColumn = 'referencia_fabricante';
        }

        $code = trim($code);
        $description = trim($description);

        $format = 'EditProducto?%s=%s&descripcion=%s';

        return sprintf($format, $referenceColumn, rawurlencode($code), rawurlencode($description));
    }

    public function getConceptosProductMap(): array
    {
        return $this->conceptosProductMap;
    }
}
