<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use Exception;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiQuickReader;
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

    protected function searchProduct(): void
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
}

