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
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierInvoiceImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\SupplierInvoiceImportService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierProductLinkRepository;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence\SupplierProductSearchRepository;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;

class CfdiSupplierWizard extends Controller
{
    public CfdiProveedor $cfdi;
    public CfdiQuickReader $reader;
    public Proveedor $supplier;
    public array $conceptMatchResults = [];
    public array $matchStats = [];
    public string $importError = '';
    public string $wizardError = '';
    private ?SupplierProductLinkRepository $linkRepository = null;
    private ?SupplierProductSearchRepository $searchRepository = null;
    private ?SupplierInvoiceImportService $importService = null;

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

        $action = $this->request->inputOrQuery('action', '');

        if ($this->execPreviousAction($action)) {
            return;
        }

        if (!$this->initWizard()) {
            $this->setTemplate('CfdiSupplierWizardError');
            return;
        }

        $this->execAction($action);
        $this->setTemplate('CfdiSupplierWizard');
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

    protected function initWizard(): bool
    {
        $code = $this->request->inputOrQuery('code');

        if (empty($code)) {
            $this->wizardError = 'No se recibió el identificador del CFDI. Abra el wizard desde un CFDI de proveedor.';
            return false;
        }

        $this->cfdi = new CfdiProveedor();
        if (!$this->cfdi->load($code)) {
            $this->wizardError = 'El CFDI solicitado no existe o ya no está disponible.';
            return false;
        }

        $this->loadSupplier();
        if (empty($this->supplier->codproveedor)) {
            $this->wizardError = 'No se pudo identificar el proveedor del CFDI.';
            return false;
        }

        if (!$this->loadCfdiReader()) {
            $this->wizardError = 'No se pudo leer el XML asociado al CFDI.';
            return false;
        }

        $this->loadMatchResults();
        return true;
    }

    protected function loadMatchResults(): void
    {
        $conceptos = $this->reader->getConceptos();
        $linkedProducts = $this->getIndexedSupplierProducts($this->supplier->codproveedor);
        $this->conceptMatchResults = [];

        foreach ($conceptos as $index => $concepto) {
            $refproveedor = $concepto['NoIdentificacion'] ?? '';
            $productSupplier = $linkedProducts[$refproveedor] ?? null;
            $product = null;

            if ($productSupplier !== null) {
                $product = $productSupplier->getProducto();
                if (empty($product->primaryColumnValue())) {
                    $product = null;
                }
            }

            $this->conceptMatchResults[$index] = [
                'confidence' => $product !== null ? 1.0 : 0.0,
                'referencia' => $product?->referencia,
                'matchMethod' => $product !== null ? 'supplier_link' : 'none',
                'matchDescription' => $product !== null
                    ? 'Producto vinculado al proveedor'
                    : 'Sin coincidencia',
                'isLinked' => $product !== null,
            ];
        }

        $total = count($this->conceptMatchResults);
        $linked = count(array_filter(
            $this->conceptMatchResults,
            static fn(array $result): bool => $result['isLinked']
        ));
        $this->matchStats = [
            'total' => $total,
            'exactLinked' => $linked,
            'exactUnlinked' => 0,
            'suggestions' => 0,
            'unmatched' => $total - $linked,
            'matchRate' => $total > 0 ? $linked / $total : 0.0,
            'autoMatchRate' => $total > 0 ? $linked / $total : 0.0,
        ];
    }

    protected function getIndexedSupplierProducts(string $codproveedor): array
    {
        $products = $this->getLinkRepository()->findBySupplier($codproveedor);
        $indexed = [];

        foreach ($products as $product) {
            $indexed[$product->refproveedor] = $product;
        }

        return $indexed;
    }

    private function getLinkRepository(): SupplierProductLinkRepository
    {
        return $this->linkRepository ??= new SupplierProductLinkRepository();
    }

    protected function searchProduct(): void
    {
        $query = $this->request->input('query');

        if (empty($query) || mb_strlen($query, 'UTF-8') < 2) {
            $this->response->setContent(json_encode([]));
            return;
        }

        $query = mb_substr($query, 0, 100);

        $codproveedor = '';
        if (isset($this->supplier) && !empty($this->supplier->codproveedor)) {
            $codproveedor = $this->supplier->codproveedor;
        }

        $result = !empty($codproveedor)
            ? $this->searchProductsWithSupplierPriority($query, $codproveedor)
            : $this->searchProductsStandard($query);

        $this->response->setContent(json_encode($result));
    }

    protected function searchProductsWithSupplierPriority(string $query, string $codproveedor): array
    {
        $result = $this->getSearchRepository()->searchWithSupplierPriority($query, $codproveedor);

        return $this->formatProductSearchResults($result, $query);
    }

    private function getSearchRepository(): SupplierProductSearchRepository
    {
        return $this->searchRepository ??= new SupplierProductSearchRepository();
    }

    protected function searchProductsStandard(string $query): array
    {
        $results = [];
        foreach ($this->getSearchRepository()->searchStandard($query, Plugins::isEnabled('SKU')) as $product) {
            $results[] = $product->toArray(true);
        }

        return $results;
    }

    protected function formatProductSearchResults(array $dbResults, string $query): array
    {
        $results = [];

        foreach ($dbResults as $row) {
            $item = [
                'referencia' => $row['referencia'],
                'descripcion' => $row['descripcion'],
                'tipoventa' => $row['tipoventa'],
                'codfamilia' => $row['codfamilia'],
                'preciocoste' => $row['preciocoste'],
                'pvp' => $row['pvp'],
                'stockfis' => $row['stockfis'],
                'controlstock' => $row['controlstock'],
                'referencia_fabricante' => $row['referencia_fabricante'],
                'refproveedor' => $row['refproveedor'],
                'precio_proveedor' => $row['precio_proveedor'],
                'is_linked' => (int)$row['is_linked'] === 1,
            ];

            if (!empty($row['refproveedor'])) {
                $item['match_field'] = 'refproveedor';
                $item['match_value'] = $row['refproveedor'];
            } elseif (!empty($row['referencia_fabricante']) && stripos($row['referencia_fabricante'], $query) !== false) {
                $item['match_field'] = 'referencia_fabricante';
                $item['match_value'] = $row['referencia_fabricante'];
            } elseif (stripos($row['referencia'], $query) !== false) {
                $item['match_field'] = 'referencia';
                $item['match_value'] = $row['referencia'];
            } else {
                $item['match_field'] = 'descripcion';
                $item['match_value'] = $row['descripcion'];
            }

            $results[] = $item;
        }

        return $results;
    }

    protected function formatSingleProduct(Producto $product): array
    {
        return [
            'referencia' => $product->referencia,
            'descripcion' => $product->descripcion,
            'tipoventa' => $product->tipoventa,
            'codfamilia' => $product->codfamilia,
            'preciocoste' => $product->preciocoste,
            'pvp' => $product->pvp,
            'stockfis' => $product->stockfis,
            'controlstock' => $product->controlstock,
            'referencia_fabricante' => $product->referencia_fabricante ?? null,
            'is_linked' => false,
        ];
    }

    protected function importCfdiAction(): void
    {
        try {
            $options = $this->getImportOptions();
            $result = $this->getImportService()->importSingle(
                $this->cfdi,
                $this->supplier,
                $options,
                $this->getImportConcepts()
            );

            if ($result->success && $result->invoice) {
                $this->redirect($result->invoice->url());
                return;
            }

            $this->importError = $result->error ?: Tools::lang()->trans('supplier-cfdi-import-failed');
            Tools::log('CFDI')->warning('supplier-cfdi-import-failed', [
                '%uuid%' => $this->cfdi->uuid,
                '%error%' => $this->importError,
            ]);
        } catch (Exception $e) {
            $this->importError = 'Error al importar: ' . $e->getMessage();
            Tools::log('CFDI')->error('supplier-cfdi-import-exception', [
                '%uuid%' => $this->cfdi->uuid ?? '',
                '%error%' => $e->getMessage(),
            ]);
        }
    }

    private function getImportService(): SupplierInvoiceImportService
    {
        return $this->importService ??= new SupplierInvoiceImportService();
    }

    protected function getImportOptions(): SupplierInvoiceImportOptions
    {
        return SupplierInvoiceImportOptions::fromArray([
            'product_action' => $this->request->get('product_action', 'auto'),
            'tax_mode' => $this->request->get('tax_mode', 'preserve'),
            'update_supplier_prices' => $this->requestBoolean('update_supplier_prices'),
            'auto_match_products' => true,
            'price_multiplier' => (float)$this->request->input('price_multiplier', 1.0),
            'codserie' => $this->request->get('codserie'),
        ]);
    }

    protected function requestBoolean(string $field, bool $default = false): bool
    {
        $value = $this->request->input($field);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function getImportConcepts(): array
    {
        $conceptos = $this->reader->getConceptos();
        $submitted = $this->request->getArray('conceptos');

        foreach ($conceptos as $index => &$concepto) {
            $referencia = $submitted[$index]['referencia'] ?? '';
            $concepto['referencia'] = is_string($referencia) ? trim($referencia) : '';

            $referenciaProveedor = $submitted[$index]['referencia_proveedor'] ?? '';
            if (is_string($referenciaProveedor) && trim($referenciaProveedor) !== '') {
                $concepto['NoIdentificacion'] = trim($referenciaProveedor);
            }

        }

        unset($concepto);

        return $conceptos;
    }

    protected function loadSupplier(): void
    {
        $this->supplier = $this->cfdi->getSupplier();
    }

    protected function loadCfdiReader(): bool
    {
        try {
            $fileContent = $this->cfdi->localFileContent();
            if (empty($fileContent)) {
                return false;
            }

            $this->reader = new CfdiQuickReader($fileContent);
            return true;
        } catch (Exception $e) {
            Tools::log('CFDI')->error($e->getMessage());
            return false;
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

    /**
     * @return FormaPago[]
     */
    public function cfdiToInvoicePaymentMethods(): array
    {
        $where = [
            Where::isNotNull('clavesat')
        ];

        return FormaPago::all($where);
    }

    public function getMatchedConcept(int $index): ?array
    {
        return $this->conceptMatchResults[$index] ?? null;
    }

    public function getMatchStatsArray(): array
    {
        return $this->matchStats;
    }
}
