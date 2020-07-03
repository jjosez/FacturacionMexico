<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiTools;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\PDF\PDFCfdi;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\StampService\FinkokStampService;

class EditCfdiCliente extends Controller
{
    public $cfdi;
    public $factura;
    public $reader;
    public $xml;

    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'edit-customer-cfdi';
        $pagedata['icon'] = 'fas fa-sliders-h';
        $pagedata['menu'] = 'CFDI';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $template = 'CfdiCliente';

        $this->cfdi = new CfdiCliente();
        $this->factura = new FacturaCliente();

        $invoice = $this->request->query->get('invoice', '');
        $code = $this->request->query->get('code', '');

        if ($code) {
            if (true === $this->cfdi->loadFromCode($code)) {
                $this->factura->loadFromCode($this->cfdi->idfactura);

                $this->xml = $this->cfdi->getXml();
                $this->reader = new CfdiQuickReader($this->xml);
            }
        } elseif ($invoice) {
            if (false === $this->factura->loadFromCode($invoice)) {
                $this->mensajeFacturaNoEncontrada();
            }

            if (true === $this->cfdi->loadFromInvoice($invoice)) {
                $this->xml = $this->cfdi->getXml();
                $this->reader = new CfdiQuickReader($this->xml);
            } else {
                $template = 'NewCfdiCliente';
            }
        }

        /// Set view template
        $this->setTemplate($template);

        /// Get any operations that have to be performed
        $action = $this->request->request->get('action', '');
        $this->execAction($action);
    }

    public function catalogoSat()
    {
        return new CfdiCatalogo();
    }

    public function getCfdisRelacionados()
    {
        $result = [];
        $relacionado = new CfdiCliente();

        foreach ($this->factura->parentDocuments() as $parent){
            if ($relacionado->loadFromInvoice($parent->idfactura)) {
                $result[] = $relacionado;
            }
            continue;
        }
        return $result;
    }

    public function getCfdiFromUUID()
    {
        $this->setTemplate(false);
        $uuid = $this->request->request->get('uuid', false);
        $codcliente = $this->request->request->get('codcliente', false);

        $cfdi = new CfdiCliente();
        if ($cfdi->loadFromUUID($uuid) && $cfdi->codcliente === $codcliente) {
            $this->response->setContent(json_encode($cfdi));
            return;
        }

        echo 'CFDI no encontrado o pertenece a otro cliente';
    }

    private function execAction($action)
    {
        switch ($action) {
            case 'download-xml':
                $this->downloadXmlAction();
                return false;

            case 'download-pdf':
                $this->downloadPdfAction();
                return false;

            case 'cfdi-relacionado':
                $this->getCfdiFromUUID();
                return false;

            case 'timbrar':
                if ($this->generarCfdi() && $this->timbrarCfdi()) {
                    $this->guardarCfdi();

                    $this->reader = new CfdiQuickReader($this->xml);
                    $this->setTemplate('CfdiCliente');
                }
                return true;

            case 'cancelar':
                $this->cancelarCfdi();
                return true;

            default:
                return true;
        }
    }

    private function generarCfdi()
    {
        if (false === $this->factura) return false;

        if ($this->isFacturaEgreso()) {
            if (false === $this->hasParentsFacturaEgreso()) return false;

            return $this->xml = CfdiTools::buildCfdiEgreso($this->factura, $this->empresa, 'G02');
        }

        $global = $this->request->request->get('globalinvoice', false);
        if ($global) {
            return $this->xml = CfdiTools::buildCfdiGlobal($this->factura, $this->empresa);
        } else {
            $uso = $this->request->request->get('usocfdi', 'G03');
            return $this->xml = CfdiTools::buildCfdiIngreso($this->factura, $this->empresa, $uso);
        }
    }

    private function timbrarCfdi()
    {
        $username = $this->toolBox()::appSettings()::get('cfdi', 'finkokuser');
        $testmode = $this->toolBox()::appSettings()::get('cfdi', 'testmode', true);
        $token = $this->toolBox()::appSettings()::get('cfdi', 'finkoktoken');

        $service = new FinkokStampService($username, $token, $testmode);
        $response = $service->timbrar($this->xml);

        if ($response->hasError()) {
            $this->toolBox()::log()->warning($response->getMessage());
            $this->toolBox()::log()->warning($response->getMessageDetail());
        } else {
            $this->toolBox()::log()->notice($response->getMessage());
            $this->xml = $response->getXml();
            return true;
        }

        return false;
    }

    private function cancelarCfdi()
    {
        $username = $this->toolBox()::appSettings()::get('cfdi', 'finkokuser');
        $testmode = $this->toolBox()::appSettings()::get('cfdi', 'testmode', true);
        $token = $this->toolBox()::appSettings()::get('cfdi', 'finkoktoken');

        $cerfile = CFDI_CERT_DIR . DIRECTORY_SEPARATOR . $this->toolBox()::appSettings()::get('cfdi', 'cerfile');
        $keyfile = CFDI_CERT_DIR . DIRECTORY_SEPARATOR . $this->toolBox()::appSettings()::get('cfdi', 'keyfile');
        $passphrase = $this->toolBox()::appSettings()::get('cfdi', 'passphrase');

        $service = new FinkokStampService($username, $token, $testmode);
        $service->cancelar($this->cfdi->uuid, $cerfile, $keyfile, $passphrase);

        $status = $service->getSatStatus($this->empresa->cifnif, $this->cfdi->rfcreceptor, $this->cfdi->uuid, $this->cfdi->total);
        $this->toolBox()::log()->warning('Estatus del comprobante: ' . $status->query());
        $this->toolBox()::log()->warning('Es cancelable: ' . $status->cancellable());
        $this->toolBox()::log()->warning('Estado de la cancelación: ' . $status->cancellation());
    }

    private function mensajeFacturaNoEncontrada()
    {
        $this->toolBox()::log()->warning('Factura no encontrada');
    }

    private function guardarCfdi()
    {
        if (CfdiTools::saveCfdi($this->xml, $this->factura)) {
            $this->toolBox()::log()->notice('CFDI guardado correctamente');
        } else {
            $this->toolBox()::log()->warning('Error al guardar el CFDI');
        }
    }

    public function isFacturaEgreso()
    {
        $serieEgreso =  $this->toolBox()::appSettings()::get('default', 'codserierec', 'R');

        if ($serieEgreso === $this->factura->codserie) {
            return true;
        }
        return false;
    }

    private function hasParentsFacturaEgreso()
    {
        if (true === empty($this->factura->parentDocuments())) {
            $this->toolBox()::log()->warning('No tiene relacion con alguna factura de ingreso');
            return false;
        }

        foreach ($this->factura->parentDocuments() as $document) {
            if ($document->getStatus()->nombre !== 'Timbrado') {
                $this->toolBox()::log()->warning('Primero se debe generar el cfdi de la factura relacionada');
                return false;
            }
        }

        return true;
    }

    private function downloadXmlAction()
    {
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'text/xml');
        $this->response->headers->set('Content-Disposition', 'attachment; filename=' . $this->cfdi->uuid . '.xml');
        $this->response->setContent($this->xml);

        return $this->response;
    }

    private function downloadPdfAction()
    {
        $reader = new CfdiQuickReader($this->xml);
        $pdf = new PDFCfdi($reader);

        $pdf->getPdf();
    }
}