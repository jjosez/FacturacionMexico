<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;
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

    public function catalogoSat()
    {
        return new CfdiCatalogo();
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $action = $this->request->request->get('action', '');
        if (false === $this->execAction($action)) return;

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

            case 'save-stamp-settings':
                $this->saveStampServiceCredentials();
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

        $passphrase = $this->request->request->get('passphrase', '');
        $this->toolBox()::appSettings()->set('cfdi', 'passphrase', $passphrase);
        $this->toolBox()::appSettings()->save();

        $this->saveCertsFiles();
        $this->toolBox()::log()->notice('Configuracion actualizada');
    }

    private function saveStampServiceCredentials()
    {
        $user = $this->request->request->get('finkokuser', '');
        $this->toolBox()::appSettings()->set('cfdi', 'finkokuser', $user);

        $token = $this->request->request->get('finkoktoken', '');
        $this->toolBox()::appSettings()->set('cfdi', 'finkoktoken', $token);

        $testmode = ($this->request->request->get('testmode') === 'on') ? true : false;
        $this->toolBox()::appSettings()->set('cfdi', 'testmode', $testmode);

        $this->toolBox()::appSettings()->save();
        $this->toolBox()::log()->notice('Configuracion actualizada');
    }

    private function saveCertsFiles()
    {
        $cerfile = $this->request->files->get('cerfile', false);
        $keyfile = $this->request->files->get('keyfile', false);

        if ($this->isValidFileUpload($cerfile)) {
            $name = $this->empresa->cifnif . '.cer.pem';
            $cerfile->move(CFDI_CERT_DIR, $name);

            $this->toolBox()::appSettings()->set('cfdi', 'cerfile', $name);
            $this->toolBox()::log()->warning('El archivo .cer se guardo correctamente');
        }

        if ($this->isValidFileUpload($keyfile)) {
            $name = $this->empresa->cifnif . '.key.pem';
            $keyfile->move(CFDI_CERT_DIR, $name);

            $this->toolBox()::appSettings()->set('cfdi', 'keyfile', $name);
            $this->toolBox()::log()->warning('El archivo .key se guardo correctamente');
        }

        $this->toolBox()::appSettings()->save();
    }

    private function isValidFileUpload($file)
    {
        if (is_null($file) || false === $file->isValid()) {
            return false;
        }

        return true;
    }
}