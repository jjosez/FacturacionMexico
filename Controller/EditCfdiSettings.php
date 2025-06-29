<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use CfdiUtils\CfdiCreator40;
use CfdiUtils\CfdiValidator40;
use CfdiUtils\XmlResolver\XmlResolver;
use Eclipxe\XmlResourceRetriever\XsltRetriever;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\EstadoDocumento;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;

class EditCfdiSettings extends Controller
{
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'Configuracion';
        $pagedata['icon'] = 'fas fa-sliders-h';
        $pagedata['menu'] = 'CFDI';

        return $pagedata;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $action = $this->request->request->get('action', '');
        if (false === $this->execAction($action)) return;

        $this->setTemplate('EditCfdiSettings');
    }

    public function getCatalogoSat(): CfdiCatalogo
    {
        return new CfdiCatalogo();
    }

    public function getInvoiceStatus(): array
    {
        $estados = new EstadoDocumento();

        $where = [new DataBaseWhere('tipodoc', 'FacturaCliente')];

        return $estados->all($where);
    }

    private function execAction($action): bool
    {
        switch ($action) {
            case 'download-resources':
                $this->downloadResources();
                return false;

            case 'save-cfdi-settings':
                $this->saveCfdiSettings();
                return true;

            case 'save-sat-credentials':
                $this->saveSatCredentials();
                return true;

            case 'save-stamp-settings':
                $this->saveStampServiceCredentials();
                return true;

            default:
                return true;
        }
    }

    private function downloadResourcesNew(): void
    {
        $myLocalResourcePath = '/Cache/SAT';
        $myResolver = new XmlResolver($myLocalResourcePath);

        $cfdiCreator = new CfdiCreator40();
        $cfdiCreator->setXmlResolver($myResolver);

        $cfdiValidator = new CfdiValidator40($myResolver);
    }

    private function downloadResources(): void
    {
        $xsltResources = new XsltRetriever(CFDI_XSLT_DIR);
        $local = $xsltResources->retrieve(CFDI_XSLT_URL);

        Tools::settingsSet('cfdi', 'cachefiles', '1');
        Tools::settingsSave();

        $this->response->setContent($local);
    }

    private function saveCfdiSettings()
    {
        $regimen = $this->request->request->get('regimenfiscal', '');
        Tools::settingsSet('cfdi', 'regimen', $regimen);

        $uso = $this->request->request->get('usocfdi', '');
        Tools::settingsSet('cfdi', 'cfdi-usage', $uso);

        $estado = $this->request->request->get('estadotimbrada', '');
        Tools::settingsSet('cfdi', 'stamped-status', $estado);

        $estado = $this->request->request->get('estadocancelada', '');
        Tools::settingsSet('cfdi', 'canceled-status', $estado);

        Tools::settingsSave();

        Tools::log()->notice('Configuracion actualizada');
    }

    private function saveSatCredentials()
    {
        $passphrase = $this->request->request->get('passphrase', '');
        Tools::settingsSet('cfdi', 'passphrase', $passphrase);

        $cerfile = $this->request->files->get('cerfile', false);
        $keyfile = $this->request->files->get('keyfile', false);

        if ($this->isValidFileUpload($cerfile)) {
            $name = $this->empresa->cifnif . '.cer.pem';
            $cerfile->move(CFDI_CERT_DIR, $name);

            Tools::settingsSet('cfdi', 'cerfile', $name);
            self::toolBox()::log()->notice('El archivo .cer se guardo correctamente');
        }

        if ($this->isValidFileUpload($keyfile)) {
            $name = $this->empresa->cifnif . '.key.pem';
            $keyfile->move(CFDI_CERT_DIR, $name);

            Tools::settingsSet('cfdi', 'keyfile', $name);
            Tools::log()->notice('El archivo .key se guardo correctamente');
        }

        Tools::settingsSave();
    }

    private function saveStampServiceCredentials()
    {
        $user = $this->request->request->get('stampuser', '');
        Tools::settingsSet('cfdi', 'stamp-user', $user);

        $token = $this->request->request->get('stamptoken', '');
        Tools::settingsSet('cfdi', 'stamp-token', $token);

        $testmode = $this->request->request->get('testmode') === 'on';
        Tools::settingsSet('cfdi', 'test-mode', $testmode);

        Tools::settingsSave();
        Tools::log()->notice('Configuracion actualizada');
    }

    private function isValidFileUpload($file): bool
    {
        if (is_null($file) || false === $file->isValid()) {
            return false;
        }

        return true;
    }
}
