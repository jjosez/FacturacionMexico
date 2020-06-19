<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos\RegimenFiscal;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos\UsoCfdi;
use XmlResourceRetriever\XsltRetriever;

class CfdiSettings extends Controller
{
    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'cfdi-settings';
        $pagedata['icon'] = 'fas fa-sliders-h';
        $pagedata['menu'] = 'CFDI';

        return $pagedata;
    }

    public function getRegimenFiscal()
    {
        return new RegimenFiscal();
    }

    public function getUsoCfdi()
    {
        return new UsoCfdi();
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        // Get any operations that have to be performed
        $action = $this->request->request->get('action', '');
        if (false === $this->execAction($action)) return;

        /** Set view template*/
        $template = 'CfdiSettings';
        $this->setTemplate($template);
    }

    private function execAction($action)
    {
        switch ($action) {
            case 'download-resources':
                $this->downloadResources();
                return false;

            case 'save-settings':
                $this->saveSettings();
                return true;

            default:
                return true;
        }
    }

    private function downloadResources()
    {
        $xsltResources = new XsltRetriever(CFDI_XSLT_DIR);
        $local = $xsltResources->retrieve(CFDI_XSLT_URL);

        $this->toolBox()::appSettings()->set('cfdi', 'cachefiles', '1');
        $this->toolBox()::appSettings()->save();

        $this->response->setContent($local);
    }

    private function saveSettings()
    {
        $regimen = $this->request->request->get('regimenfiscal', '');
        $this->toolBox()::appSettings()->set('cfdi', 'regimen', $regimen);

        $uso = $this->request->request->get('usocfdi', '');
        $this->toolBox()::appSettings()->set('cfdi', 'uso', $uso);

        $this->toolBox()::appSettings()->save();

        $this->toolBox()::log()->notice('Confoiguracion actualizada');
    }
}