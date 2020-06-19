<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Lib\CFDI\Catalogos\UsoCfdi;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\CfdiData;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Lib\CFDI\Builder\CustomerCfdiBuilder;
use FacturaScripts\Dinamic\Lib\CFDI\Builder\GlobalCfdiBuilder;
use FacturaScripts\Dinamic\Lib\CFDI\CfdiReader;
use FacturaScripts\Dinamic\Lib\CFDI\WebService\Profact;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\PDF\CfdiPdf;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiQuickReader;

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

    public function getUsoCfdi($key = false)
    {
        $result = new UsoCfdi();

        if (false === $key) {
            return $result->all();
        }
        return $result->get($key);
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

        /// Get any operations that have to be performed
        $action = $this->request->request->get('action', '');
        if (false === $this->execAction($action)) return;

        /// Set view template
        $this->setTemplate($template);
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

            case 'timbrar':
                $xml = $this->generarPreCfcdi();
                if (false === $xml) {
                    return true;
                }

                if ($this->timbrarPreCfdi($xml)) {
                    $this->guardarCfdi($xml);
                }
                return true;

            default:
                return true;
        }
    }

    private function generarPreCfcdi()
    {
        $code = $this->request->request->get('invoice', '');
        if (false === $this->factura->loadFromCode($code)) return false;

        $uso = $this->request->request->get('usocfdi', 'G01');
        $isGlobal = $this->request->request->get('globalinvoice', false);

        if ($isGlobal) {
            $builder = new GlobalCfdiBuilder($this->factura, $this->empresa);
        } else {
            $builder = new CustomerCfdiBuilder($this->factura, $this->empresa, $uso);
        }

        $this->xml = $builder->getXml();

        return $this->xml;
    }

    private function timbrarPreCfdi(&$xml)
    {
        $parametros = [
            'idComprobante' => $this->factura->codigo,
            'usuarioIntegrador' => 'mvpNUXmQfK8='
        ];

        $ws = new Profact(true);
        $result = $ws->timbrar($parametros, $xml);

        if (false === $result) {
            $response = $ws->getResponse();
            $this->toolBox()::log()->warning(print_r($response, true));
        } else {
            $xml = $result;

            return true;
        }
        return false;
    }

    private function mensajeFacturaNoEncontrada()
    {
        $this->toolBox()::log()->warning('Factura no encontrada');
    }

    private function mensajeCfdiTimbrado()
    {
        $this->toolBox()::log()->notice('CFDI timbrado correctamente');
    }

    private function guardarCfdi($xml)
    {
        $reader = new CfdiReader($xml);

        $this->cfdi->razonreceptor = $this->factura->nombrecliente;
        $this->cfdi->codcliente = $this->factura->codcliente;
        $this->cfdi->coddivisa = $this->factura->coddivisa;
        $this->cfdi->estado = 'TIMBRADO';
        $this->cfdi->folio = $this->factura->codigo;
        $this->cfdi->formapago = 'CONTADO';
        $this->cfdi->metodopago = 'EFECTIVO';
        $this->cfdi->idfactura = $this->factura->idfactura;
        $this->cfdi->serie = $this->factura->codserie;
        $this->cfdi->tipocfdi = 'INGRESO';
        $this->cfdi->totaldto = 0;
        $this->cfdi->total = $this->factura->total;
        $this->cfdi->rfcreceptor = $this->factura->cifnif;
        $this->cfdi->uuid = $reader->getUUID();

        if ($this->cfdi->save()) {
            $cfdiXml = new CfdiData();

            $cfdiXml->idcfdi = $this->cfdi->idcfdi;
            $cfdiXml->uuid = $this->cfdi->uuid;
            $cfdiXml->xml = $xml;
            $cfdiXml->save();
        }
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
        $pdf = new CfdiPdf($this->reader);

        $pdf->testPdf();
    }
}