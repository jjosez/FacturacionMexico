<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiFactory;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\PDF\PDFCfdi;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\StampService\FinkokStampService;
use Symfony\Component\HttpFoundation\Response;

class EditCfdiCliente extends Controller
{
    /**
     * @var CfdiCliente
     */
    public $cfdi;

    /**
     * @var FacturaCliente
     */
    public $factura;

    /**
     * @var CfdiQuickReader
     */
    public $reader;

    /**
     * @var string
     */
    public $xml;

    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'CFDI Cliente';
        $pagedata['icon'] = 'fas fa-sliders-h';
        $pagedata['menu'] = 'CFDI';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $this->cfdi = new CfdiCliente();
        $this->factura = new FacturaCliente();

        $action = $this->request->get('action', '');
        $code = $this->request->query->get('code', '');
        $invoice = $this->request->query->get('invoice', '');

        if (true === $this->execAjaxAction($action)) {
            return;
        }

        if ($code && $this->cfdi->loadFromCode($code)) {
            $this->loadInvoiceFromCode($this->cfdi->idfactura);
            $this->loadCfdiReader();
        }

        if ($invoice && $this->loadInvoiceFromCode($invoice)) {
            if (true === $this->cfdi->loadFromInvoice($invoice)) {
                $this->loadCfdiReader();
            }
        }

        $template = $this->cfdi->idcfdi ? 'CfdiCliente' : 'NuevoCfdiCliente';
        $this->setTemplate($template);

        $this->execAction($action);
    }

    public function getCatalogoSat(): CfdiCatalogo
    {
        return new CfdiCatalogo();
    }

    public function getCfdisRelacionados(): array
    {
        $result = [];
        $relacionado = new CfdiCliente();

        foreach ($this->factura->parentDocuments() as $parent) {
            if ($parent->modelClassName() !== 'FacturaCliente') {
                continue;
            }

            if ($relacionado->loadFromInvoice($parent->primaryColumnValue())) {
                $result[] = $relacionado;
            }
        }
        return $result;
    }

    public function getCustomerUsoCfdi(string $codcliente): string
    {
        $cliente = new Cliente();
        $cliente->loadFromCode($codcliente);

        return $cliente->usocfdi ?? self::toolBox()::appSettings()::get('cfdi', 'uso', 'P01');
    }

    private function findCfdiRequest()
    {
        $uuid = $this->request->request->get('uuid', false);
        $codcliente = $this->request->request->get('codcliente', false);

        $cfdi = new CfdiCliente();
        if ($cfdi->loadFromUuid($uuid) && $cfdi->codcliente === $codcliente) {
            $this->response->setContent(json_encode($cfdi));
            return;
        }

        echo 'CFDI no encontrado o pertenece a otro cliente';
    }

    public function loadCfdiFromUUID($uuid)
    {
        $cfdi = new CfdiCliente();

        return $cfdi->loadFromUuid($uuid) ? $cfdi : false;
    }

    private function execAjaxAction(string $action): bool
    {
        switch ($action) {
            case 'cfdi-relacionado':
                $this->findCfdiRequest();
                return true;
            default:
                return false;
        }
    }

    private function execAction($action): void
    {
        switch ($action) {
            case 'albaran':
                $code = $this->request->get('code');
                $remision = new AlbaranCliente();
                $remision->loadFromCode($code);

                $remision->idestado = 8;
                $remision->save();
                return;
            case 'download-xml':
                $this->downloadInvoiceXML();
                return;

            case 'download-pdf':
                $this->downloadInvoicePDF();
                return;

            case 'enviar-email':
                $this->cfdiSendEmailRequest();
                return;

            case 'timbrar':
                $this->xml = $this->cfdiBuildRequest();
                if ($this->xml && $this->cfdiStampRequest()) {
                    $this->saveCfdiStampResponse();

                    $this->reader = new CfdiQuickReader($this->xml);
                    $this->setTemplate('CfdiCliente');
                }
                return;

            case 'cancelar':
                $this->cfdiCancelRequest();
                return;

            case 'status':
                $this->cfdiStatusRequest();
                return;

            default:
        }
    }

    protected function cfdiBuildRequest(): string
    {
        if (false === $this->factura->exists()) return '';

        if (true === $this->isGlobalInvoice()) {
            return CfdiFactory::buildCfdiGlobal($this->factura);
        }

        $relation = [
            'tiporelacion' => $this->request->request->get('tiporelacion', false),
            'relacionados' => $this->request->request->get('relacionados', [])
        ];

        if (true === $this->isEgresolInvoice()) {
            if (false === $this->hasEgresoInvoiceParents($relation['relacionados'])) {
                return '';
            }

            return CfdiFactory::buildCfdiEgreso($this->factura, $relation);
        }

        return CfdiFactory::buildCfdiIngreso($this->factura, $relation);
    }

    protected function cfdiCancelRequest(): void
    {
        $service = $this->stampServiceProvider();

        if ($service->cancelar($this->cfdi->uuid, CfdiSettings::getSatCredentials())) {
            self::toolBox()::log()->notice('Cfdi cancelado correctamente');
            CfdiStorage::updateCfdiStatus($this->cfdi, 'Cancelado');
            return;
        }

        $this->toolBox()::log()->error('No se pudo cancelar el cfdi');
    }

    protected function cfdiSendEmailRequest()
    {
        $cliente = $this->factura->getSubject();

        if (!$cliente->email) {
            $this->toolBox()::log()->warning('El cliente no tiene asignado algun Email');
            return;
        }

        $filename = $this->cfdi->uuid;
        $filesPathBase = FS_FOLDER . '/MyFiles/' . $filename;

        $pdf = (new PDFCfdi($this->reader))->getPdfBuffer();
        file_put_contents($filesPathBase . '.pdf', $pdf);
        file_put_contents($filesPathBase . '.xml', $this->xml);

        $email = new NewMail();

        $email->addAddress($cliente->email, $cliente->nombre);
        $email->title = 'Facturacion - ' . $this->empresa->nombrecorto;
        $email->text = 'Envio de su comprobante fiscal digital.<br/>Gracias por su preferencia. &#128663;';

        $email->addAttachment($filesPathBase . '.pdf', $filename . '.pdf');
        $email->addAttachment($filesPathBase . '.xml', $filename . '.xml');

        if (true === $email->send()) {
            CfdiStorage::updateCfdiMailDate($this->cfdi);
            $this->toolBox()::i18nLog()->notice('send-mail-ok');
        }

        if (file_exists($filesPathBase . '.pdf')) {
            unlink($filesPathBase . '.pdf');
        }

        if (file_exists($filesPathBase . '.xml')) {
            unlink($filesPathBase . '.xml');
        }
    }

    protected function cfdiStampRequest(): bool
    {
        $service = $this->stampServiceProvider();
        $response = $service->timbrar($this->xml);

        if (true === $response->hasError()) {
            self::toolBox()::log()->warning($response->getMessage());
            self::toolBox()::log()->warning($response->getMessageErrorCode() . $response->getMessageDetail());

            if ($response->hasPreviousSign()) {
                self::toolBox()::log()->notice('Obtenido timbre previo');
                $response = $service->getTimbradoPrevio($this->xml);

                if (false === $response->hasError()) {
                    self::toolBox()::log()->notice($response->getMessage());
                    $this->xml = $response->getXml();
                    return true;
                }
            }

            return false;
        }

        self::toolBox()::log()->notice($response->getMessage());
        $this->xml = $response->getXml();
        return true;
    }

    protected function cfdiStatusRequest()
    {
        $service = $this->stampServiceProvider();
        $query = [
            'emisor' => $this->empresa->cifnif,
            'receptor' => $this->cfdi->rfcreceptor,
            'uuid' => $this->cfdi->uuid,
            'total' => $this->cfdi->total
        ];

        $status = $service->getSatStatus($query);

        self::toolBox()::log()->warning('Estatus del comprobante: ' . $status->query());
        self::toolBox()::log()->warning('Es cancelable: ' . $status->cancellable());
        self::toolBox()::log()->warning('Estado de la cancelación: ' . $status->cancellation());
    }

    protected function downloadInvoiceXML(): Response
    {
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'text/xml');
        $this->response->headers->set('Content-Disposition', 'attachment; filename=' . $this->cfdi->uuid . '.xml');
        $this->response->setContent($this->xml);

        return $this->response;
    }

    protected function downloadInvoicePDF(): void
    {
        $reader = new CfdiQuickReader($this->xml);
        $pdf = new PDFCfdi($reader);

        $pdf->downloadPDF();
    }

    protected function isEgresolInvoice(): bool
    {
        return CfdiSettings::getSerieEgreso() === $this->factura->codserie;
    }

    protected function isGlobalInvoice(): bool
    {
        $checkbox = $this->request->request->get('globalinvoice', false);

        return $checkbox && CfdiSettings::getRfcGenerico() === $this->factura->cifnif;
    }

    protected function loadCfdiReader()
    {
        $this->xml = $this->cfdi->getXml();
        $this->reader = new CfdiQuickReader($this->xml);
    }

    protected function loadInvoiceFromCode(string $code): bool
    {
        if (true === $this->factura->loadFromCode($code)) {
            return true;
        }

        self::toolBox()::log()->warning('Factura no encontrada');
        return false;
    }

    protected function saveCfdiStampResponse(): void
    {
        if (false === CfdiStorage::saveCfdi($this->factura, $this->cfdi, $this->xml)) {
            self::toolBox()::log()->warning('Error al guardar el CFDI');
            return;
        }

        if (false === CfdiStorage::saveCfdiXml($this->cfdi, $this->xml)) {
            self::toolBox()::log()->warning('Error al guardar el XML');
            return;
        }

        self::toolBox()::log()->notice('CFDI guardado correctamente');
        $this->updateStampedInvoiceStatus();
    }

    protected function stampServiceProvider(): FinkokStampService
    {
        $username = self::toolBox()::appSettings()::get('cfdi', 'stampuser');
        $testmode = self::toolBox()::appSettings()::get('cfdi', 'testmode', true);
        $token = self::toolBox()::appSettings()::get('cfdi', 'stamptoken');

        return new FinkokStampService($username, $token, $testmode);
    }

    private function hasEgresoInvoiceParents(array $parents): bool
    {
        if (true === empty($parents)) {
            $this->toolBox()::log()->warning('Se debe relacionar con un cfdi de ingreso');
            return false;
        }

        $parentCfdi = new CfdiCliente();
        foreach ($parents as $parent) {
            if (false === $parentCfdi->loadFromUuid($parent)) {
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

    protected function updateStampedInvoiceStatus(): void
    {
        $this->factura->idestado = self::toolBox()::appSettings()::get('cfdi', 'estadotimbrada');

        if (true === $this->factura->save()) {
            self::toolBox()::log()->notice('Factura actualizada correctamente');
        }
    }

    public function getPendingInvoices(): array
    {
        $invoice = new FacturaCliente();
        $stampedState = self::toolBox()::appSettings()::get('cfdi', 'estadotimbrada');

        $where = [new DataBaseWhere('idestado', $stampedState, '!=')];

        return $invoice->all($where);
    }

    public function url(): string
    {
        return parent::url() . '?code=' . $this->cfdi->idcfdi;
    }
}
