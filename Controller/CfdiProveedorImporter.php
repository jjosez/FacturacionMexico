<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiSupplierInvoice;

class CfdiProveedorImporter extends Controller
{
    protected Proveedor $supplier;

    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'Importador CFDI Proveedor';
        $pagedata['icon'] = 'fas fa-file-import';
        $pagedata['menu'] = 'CFDI';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $action = $this->request->request->get('action', '');

        if ($this->execPreviousAction($action)) {
            return;
        }

        $this->execAction($action);
        $this->setTemplate('CfdiProveedorImporter');
    }

    protected function execAction(string $action)
    {
        switch ($action) {
            case 'read-cfdi':
                $this->readUploadedCfdiAction();
                break;

            case 'import-supplier-cfdi':
                $this->importCfdiAction();
                break;

            default:
                break;
        }
    }

    protected function execPreviousAction(string $action)
    {
        switch ($action) {
            case 'search-own-product':
                $this->searchProduct();
                return true;
            default:
                return false;
        }
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

    protected function importCfdiAction()
    {
        if (false === $this->importSupplier()) {
            return;
        }

        $invoice = $this->loadSupplierInvoice();

        $conceptos = $this->request->request->get('conceptos', []);

        $dataBase = new DataBase();
        $dataBase->beginTransaction();

        $invoice->save();
        foreach ($conceptos as $concepto) {
            $line = $invoice->getNewLine($concepto);

            if (!$this->linkSupplierProduct($concepto) || !$line->save()) {
                $dataBase->rollBack();
            }
        }

        $dataBase->commit();
    }

    protected function linkSupplierProduct($concepto)
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

    protected function loadSupplierInvoice(): FacturaProveedor
    {
        $supplierInvoiceNumber = $this->request->request->get('numproveedor', '');

        return CfdiSupplierInvoice::load($this->supplier, $supplierInvoiceNumber);
    }

    protected function setDocumentLines()
    {
        foreach ($this->document->getLines() as $line) {
            $line->delete();
        }

        foreach ($this->products as $product) {
            if (true === empty($product)) {
                continue;
            }

            if (true === isset($product['cantidad'])) {
                $this->documentLines[] = $this->document->getNewLine($product);
                continue;
            }

            $newLine = $this->document->getNewProductLine($product['referencia']);

            if (isset($product['thumbnail'])) {
                $newLine->thumbnail = $product['thumbnail'];
            }

            $this->documentLines[] = $newLine;
        }
    }

    protected function importSupplier()
    {
        $rfc = $this->request->request->get('emisorrfc', '');
        $razonSocial = $this->request->request->get('emisorazonsocial', '');

        if (empty($rfc) || empty($razonSocial)) {
            return false;
        }

        $where = [new DataBaseWhere('cifnif', $rfc)];
        $this->supplier = new Proveedor();

        if ($this->supplier->loadFromCode('', $where)) {
            Tools::log()->notice('Proveedor cargado con exito');
            return true;
        }

        $this->supplier->cifnif = $rfc;
        $this->supplier->nombre = $razonSocial;

        if ($this->supplier->save()) {
            Tools::log()->notice('Proveedor registrado con exito');
        }

        return false;
    }

    /**
     * @return void
     */
    public function readUploadedCfdiAction(): void
    {
        $file = $this->request->files->get('xmlfile');

        if (false === $file->isValid()) {
            Tools::log()->error($file->getErrorMessage());
        }

        $fileContent = file_get_contents($file->getPathname());
        $this->reader = new CfdiQuickReader($fileContent);
        //$this->setTemplate('Block/Ajax/CfdiImportGeneral');
    }
}

