<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use Exception;
use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\LineaFacturaProveedor;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiSupplierInvoice;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiSupplierInvoiceImporter;

class CfdiProveedorImporter extends Controller
{
    public CfdiProveedor $cfdi;
    public CfdiQuickReader $reader;
    public Proveedor $supplier;

    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'Importador CFDI Proveedor';
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

    protected function searchProduct()
    {
        $query = $this->request->request->get('query');

        $where = [
            Where::like('referencia', $query)
        ];

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
        } catch (Exception $e) {
        }
    }

    protected function processInvoiceLines(FacturaProveedor $invoice, array $conceptos): array
    {
        foreach ($invoice->getLines() as $line) {
            $line->delete();
        }

        $lines = [];

        foreach ($conceptos as $concepto) {
            $line = !empty($concepto['referencia'])
                ? $invoice->getNewProductLine($concepto['referencia'])
                : $invoice->getNewLine($concepto);

            $line->cantidad = $concepto['cantidad'];
            $line->descripcion = $concepto['descripcion'];
            $line->pvpunitario = $concepto['valorunitario'];
            $line->pvpsindto = $concepto['importe'];

            $this->setLineDiscount($line, $concepto);
            $this->setLineTax($line, $concepto);

            if (!$line->save()) {
                throw new Exception('Error guardando línea');
            }

            $lines[] = $line;
        }

        return $lines;
    }

    protected function setLineDiscount(LineaFacturaProveedor $linea, array $concepto): void
    {
        $importeBruto = (float)$concepto['importe'];
        $descuentoNeto = isset($concepto['descuento']) ? (float)$concepto['descuento'] : 0.0;

        if ($importeBruto > 0) {
            $discountPercent = ($descuentoNeto / $importeBruto) * 100;
        } else {
            $discountPercent = 0.0;
        }

        $linea->dtopor = round($discountPercent, 2);
    }

    protected function setLineTax(LineaFacturaProveedor $linea, array $concepto): void
    {
        $iva = 0.0;

        foreach ($concepto['traslados'] as $traslado) {
            if ('002' === $traslado['impuesto']) {
                $iva = (float)$traslado['tasa'] * 100;
            }
            // Aquí podrías capturar IEPS o recargo si aplicara
        }

        $linea->iva = $iva;
    }

    protected function linkSupplierProduct($concepto): bool
    {
        $supplierCode = $concepto['referencia_proveedor'];
        $code = $concepto['referencia'];

        $product = new ProductoProveedor();

        $where = [
            new DataBaseWhere('refproveedor', $supplierCode)
        ];

        if ($product->loadFromCode('', $where)) {
            return true;
        }

        $product->codproveedor = $this->supplier->codproveedor;
        $product->refproveedor = $supplierCode;
        $product->referencia = $code;

        return $product->save();
    }

    protected function loadSupplier(): void
    {
        $this->supplier = $this->cfdi->getSupplier();
    }

    protected function loadSupplierInvoice(): FacturaProveedor
    {
        return CfdiSupplierInvoice::load($this->supplier, $this->cfdi->invoiceNumber());
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
}

