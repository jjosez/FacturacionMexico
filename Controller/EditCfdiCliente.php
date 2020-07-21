<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Lib\EmailTools;
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

    private function findCfdiRequest()
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

    public function loadCfdiFromUUID($uuid)
    {
        $cfdi = new CfdiCliente();
        if ($cfdi->loadFromUUID($uuid)) {
            return $cfdi;
        }

        return false;
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
                $this->findCfdiRequest();
                return false;

            case 'enviar-email':
                $this->enviarCfdiEmail();
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
            $relacion['tiporelacion'] = $this->request->request->get('tiporelacion', false);
            $relacion['relacionados'] = $this->request->request->get('relacionados', []);

            if (false === $this->testFacturaEgresoParents($relacion['relacionados'])) return false;

            return $this->xml = CfdiTools::buildCfdiEgreso($this->factura, $this->empresa, 'G02', $relacion);
        }

        $global = $this->request->request->get('globalinvoice', false);
        if ($global) {
            return $this->xml = CfdiTools::buildCfdiGlobal($this->factura, $this->empresa);
        } else {
            $uso = $this->request->request->get('usocfdi', 'G03');
            $relacion['tiporelacion'] = $this->request->request->get('tiporelacion', false);
            $relacion['relacionados'] = $this->request->request->get('relacionados', []);

            return $this->xml = CfdiTools::buildCfdiIngreso($this->factura, $this->empresa, $uso, $relacion);
        }
    }

    private function timbrarCfdi()
    {
        $service = $this->stampServiceProvider();
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
        $cerfile = CFDI_CERT_DIR . DIRECTORY_SEPARATOR . $this->toolBox()::appSettings()::get('cfdi', 'cerfile');
        $keyfile = CFDI_CERT_DIR . DIRECTORY_SEPARATOR . $this->toolBox()::appSettings()::get('cfdi', 'keyfile');
        $passphrase = $this->toolBox()::appSettings()::get('cfdi', 'passphrase');

        $service = $this->stampServiceProvider();

        if ($service->cancelar($this->cfdi->uuid, $cerfile, $keyfile, $passphrase)) {
            $this->toolBox()::log()->notice('Cfdi cancelado correctamente');
        } else {
            $this->toolBox()::log()->error('No se pudo cancelar el cfdi');
        }
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

    private function statusCfdi()
    {
        $service = $this->stampServiceProvider();

        $status = $service->getSatStatus($this->empresa->cifnif, $this->cfdi->rfcreceptor, $this->cfdi->uuid, $this->cfdi->total);
        $this->toolBox()::log()->warning('Estatus del comprobante: ' . $status->query());
        $this->toolBox()::log()->warning('Es cancelable: ' . $status->cancellable());
        $this->toolBox()::log()->warning('Estado de la cancelación: ' . $status->cancellation());
    }

    public function isFacturaEgreso()
    {
        $serieEgreso =  $this->toolBox()::appSettings()::get('default', 'codserierec', 'R');

        if ($serieEgreso === $this->factura->codserie) {
            return true;
        }
        return false;
    }

    private function testFacturaEgresoParents(array $parents)
    {
        if (true === empty($parents)) {
            $this->toolBox()::log()->warning('Se debe relacionar con un cfdi de ingreso');
            return false;
        }

        $parentCfdi = new CfdiCliente();
        foreach ($parents as $parent) {
            if (false === $parentCfdi->loadFromUUID($parent)) {
                $this->toolBox()::log()->warning('Cfdi relacionado no encontrado');
                return false;
            } elseif ($this->factura->codcliente !== $parentCfdi->codcliente) {
                $this->toolBox()::log()->warning('Cfdi relacionado no coincide receptor');
                return false;
            }

            if (empty($this->factura->codigorect) || empty($this->factura->idfacturarect)) {
                $parentInvoice = new FacturaCliente();

                if ($parentInvoice->loadFromCode($parentCfdi->idfactura)) {
                    $this->factura->codigorect = $parentInvoice->codigo;
                    $this->factura->idfacturarect = $parentInvoice->idfactura;

                    return $this->factura->save();
                }
            }
        }
        return true;
    }

    private function stampServiceProvider()
    {
        $username = $this->toolBox()::appSettings()::get('cfdi', 'stampuser');
        $testmode = $this->toolBox()::appSettings()::get('cfdi', 'testmode', true);
        $token = $this->toolBox()::appSettings()::get('cfdi', 'stamptoken');

        return new FinkokStampService($username, $token, $testmode);
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

    private function enviarCfdiEmail()
    {
        $filesPath = FS_FOLDER . '/MyFiles/';
        $cliente = $this->factura->getSubject();

        if (!$cliente->email) {
            $this->toolBox()::log()->warning('El cliente no tiene asignado algun Email');
            return;
        }

        $filename = $this->cfdi->uuid;

        $pdf = (new PDFCfdi($this->reader))->getPdfBuffer();
        file_put_contents($filesPath . $filename . '.pdf', $pdf);
        file_put_contents($filesPath . $filename . '.xml', $this->xml);

        $emailTools = new EmailTools();
        $mail = $emailTools->newMail();
        $mail->Subject = 'Facturacion - ' . $this->empresa->nombrecorto;

        $emailTools->addEmails($mail, $cliente->email);

        $emailTools->addAttachment($mail, $filename . '.pdf');
        $emailTools->addAttachment($mail, $filename . '.xml');

        $data = [
            'company' => $this->empresa->nombre,
            'body' => 'En este correo se anexa su comprobante fiscal.'
        ];

        $body = $emailTools->getTemplateHtml($data);
        $mail->msgHTML($body);

        if ($emailTools->send($mail)) {
            $this->toolBox()::i18nLog()->info('send-mail-ok');
        }

        if (file_exists($filesPath . $filename . '.pdf')) {
            unlink($filesPath . $filename . '.pdf');
        }

        if (file_exists($filesPath . $filename . '.xml')) {
            unlink($filesPath . $filename . '.xml');
        }
    }
}
