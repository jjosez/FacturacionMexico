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
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Extension\Controller\FormaPagoControllerTrait;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\CfdiSupplierImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\CfdiSupplierInvoiceImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\CfdiSupplierProductImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Model\CfdiProveedor;

class EditCfdiProveedor extends EditController
{
    use FormaPagoControllerTrait;

    const string DESTINATION_FOLDER = FS_FOLDER . '/MyFiles/CFDI/supplier/';

    protected string $fileName;
    protected CfdiQuickReader $reader;
    protected Proveedor $supplier;
    protected array $conceptosProductMap = [];

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
                $this->addButton($viewName, [
                    'action' => 'import-cfdi-to-invoice',
                    'color' => 'info',
                    'icon' => 'fa-solid fa-file-invoice',
                    'label' => 'Generar factura',
                    'type' => 'action'
                ]);

                $this->fileName = $this->getModel()->filename;
                $this->loadReader();
                $this->loadSupplier();
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

        parent::execPreviousAction($action);
    }

    protected function execAfterAction($action): void
    {
        parent::execAfterAction($action);

        if ($action === 'import-cfdi-to-invoice' && $this->fileName !== '') {
            try {
                $conceptos = $this->mapConceptosToInvoice();

                $importer = new CfdiSupplierInvoiceImporter();
                $invoice = $importer->import(
                    $this->getModel(),
                    $this->supplier,
                    $conceptos
                );

                $this->redirect($invoice->url());
            } catch (Exception $e) {
                Tools::log()->warning('Error al generar la factura:. ' . $e->getMessage());
            }
        }
    }

    protected function importCfdiAction(): void
    {
        $uploadedFile = $this->request->files->get('cfdifile');

        try {
            $importer = new CfdiSupplierImporter();
            $cfdi = $importer->processUpload($uploadedFile, $this->empresa);

            Tools::log()->info('CFDI importado correctamente: ' . $cfdi->uuid);
        } catch (Exception $e) {
            Tools::log()->warning($e->getMessage());
        }
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
        $precio = (float)$this->request->input('precio', 0);
        $codproveedor = $this->request->input('codproveedor', '');

        $service = new CfdiSupplierProductImporter();
        $result = $service->vincular(
            $referencia,
            $codproveedor,
            $refproveedor,
            $precio
        );

        $this->response()->json($result);
        $this->response()->send();
    }

    public function getReader(): ?CfdiQuickReader
    {
        return $this->reader;
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

        $conceptos = $this->reader->conceptosNormalized();
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

            if ($model->estado !== 'Vinculado') {
                $model->estado = 'Vinculado';
                if ($model->save()) {
                    Tools::log()->notice('El CFDI se marcó cómo VINCULADO.');
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

        $conceptos = $this->reader->conceptosNormalized();
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
