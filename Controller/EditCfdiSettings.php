<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use CfdiUtils\CfdiCreator40;
use CfdiUtils\CfdiValidator40;
use CfdiUtils\XmlResolver\XmlResolver;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\EstadoDocumento;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;
use XmlResourceRetriever\XsltRetriever;

class EditCfdiSettings extends Controller
{
    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'Configuracion';
        $pagedata['icon'] = 'fas fa-sliders-h';
        $pagedata['menu'] = 'CFDI';

        return $pagedata;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $action = $this->request->request->get('action', '');
        if (false === $this->execAction($action)) return;

        $this->setTemplate('EditCfdiSettings');
    }

    public function getCatalogoSat()
    {
        return new CfdiCatalogo();
    }

    public function getInvoiceStatus(): array
    {
        $estados = new EstadoDocumento();

        $where = [new DataBaseWhere('tipodoc', 'FacturaCliente')];
        print_r($estados->all($where));
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

    private function downloadResources()
    {
        $myLocalResourcePath = '/tmp/sat';
        $myResolver = new XmlResolver($myLocalResourcePath);

        $cfdiCreator = new CfdiCreator40();
        $cfdiCreator->setXmlResolver($myResolver);

        $cfdiValidator = new CfdiValidator40($myResolver);
    }

    private function downloadResourcesOld()
    {
        $xsltResources = new XsltRetriever(CFDI_XSLT_DIR);
        $local = $xsltResources->retrieve(CFDI_XSLT_URL);

        $this->toolBox()::appSettings()->set('cfdi', 'cachefiles', '1');
        $this->toolBox()::appSettings()->save();

        $this->response->setContent($local);
    }

    private function saveCfdiSettings()
    {
        $regimen = $this->request->request->get('regimenfiscal', '');
        self::toolBox()::appSettings()->set('cfdi', 'regimen', $regimen);

        $uso = $this->request->request->get('usocfdi', '');
        self::toolBox()::appSettings()->set('cfdi', 'uso', $uso);

        $estado = $this->request->request->get('estadotimbrada', '');
        self::toolBox()::appSettings()->set('cfdi', 'estadotimbrada', $estado);

        $estado = $this->request->request->get('estadocancelada', '');
        self::toolBox()::appSettings()->set('cfdi', 'estadocancelada', $estado);

        self::toolBox()::appSettings()->save();

        self::toolBox()::log()->notice('Configuracion actualizada');
    }

    private function saveSatCredentials()
    {
        $passphrase = $this->request->request->get('passphrase', '');
        self::toolBox()::appSettings()->set('cfdi', 'passphrase', $passphrase);

        $cerfile = $this->request->files->get('cerfile', false);
        $keyfile = $this->request->files->get('keyfile', false);

        if ($this->isValidFileUpload($cerfile)) {
            $name = $this->empresa->cifnif . '.cer.pem';
            $cerfile->move(CFDI_CERT_DIR, $name);

            self::toolBox()::appSettings()->set('cfdi', 'cerfile', $name);
            self::toolBox()::log()->notice('El archivo .cer se guardo correctamente');
        }

        if ($this->isValidFileUpload($keyfile)) {
            $name = $this->empresa->cifnif . '.key.pem';
            $keyfile->move(CFDI_CERT_DIR, $name);

            self::toolBox()::appSettings()->set('cfdi', 'keyfile', $name);
            self::toolBox()::log()->notice('El archivo .key se guardo correctamente');
        }

        self::toolBox()::appSettings()->save();
    }

    private function saveStampServiceCredentials()
    {
        $user = $this->request->request->get('stampuser', '');
        self::toolBox()::appSettings()->set('cfdi', 'stampuser', $user);

        $token = $this->request->request->get('stamptoken', '');
        self::toolBox()::appSettings()->set('cfdi', 'stamptoken', $token);

        $testmode = $this->request->request->get('testmode') === 'on';
        self::toolBox()::appSettings()->set('cfdi', 'testmode', $testmode);

        $this->toolBox()::appSettings()->save();
        self::toolBox()::log()->notice('Configuracion actualizada');
    }

    private function isValidFileUpload($file): bool
    {
        if (is_null($file) || false === $file->isValid()) {
            return false;
        }

        return true;
    }
}
