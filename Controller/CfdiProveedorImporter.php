<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiQuickReader;

class CfdiProveedorImporter extends Controller
{
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

        if ($this->execAjaxAction($action)) {
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

            default:
                break;
        }
    }

    protected function execAjaxAction(string $action)
    {
        switch ($action) {
            default:
                return false;
        }
    }

    /**
     * @return void
     */
    public function readUploadedCfdiAction(): void
    {
        $file = $this->request->files->get('xmlfile');

        if (false === $file->isValid()) {
            $this->toolBox()->log()->error($file->getErrorMessage());
        }

        $fileContent = file_get_contents($file->getPathname());
        $this->reader = new CfdiQuickReader($fileContent);
        //$this->setTemplate('Block/Ajax/CfdiImportGeneral');
    }
}

