<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019-2025 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\CfdiService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\CfdiServiceFactory;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiCatalogo;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\CfdiRepositoryInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware\Validator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiConfigurationException;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\CfdiEmailService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\PDF\PDFCfdi;

class EditCfdiCliente extends Controller
{
    public CfdiCliente $cfdi;
    public FacturaCliente $factura;
    public ?CfdiQuickReader $reader = null;
    public string $xml = '';

    private CfdiService $cfdiService;
    private CfdiRepositoryInterface $storage;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'CFDI Cliente';
        $pageData['icon'] = 'fas fa-sliders-h';
        $pageData['menu'] = 'CFDI';
        $pageData['showonmenu'] = false;

        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $this->cfdi = new CfdiCliente();
        $this->factura = new FacturaCliente();

        $this->cfdiService = CfdiServiceFactory::createCfdiService($this->empresa);
        $this->storage = CfdiServiceFactory::createStorageProvider();

        $action = $this->request->queryOrInput('action', '');
        $code = $this->request->queryOrInput('code', '');
        $invoice = $this->request->queryOrInput('invoice', '');

        if ($this->handleAsyncActions($action)) {
            return;
        }

        $this->loadData($code, $invoice);
        $this->handleAction($action);

        $template = $this->cfdi->id ? 'CfdiCliente' : 'CfdiClienteWizard';
        $this->setTemplate($template);
    }

    /**
     * Carga los datos del CFDI y factura
     */
    private function loadData(string $code, string $invoice): void
    {
        if ($code && $this->cfdi->load($code)) {
            $this->factura->load($this->cfdi->idfactura);
            $this->attachXmlReader();
        }

        if ($invoice && $this->factura->load($invoice)) {
            if ($this->cfdi->loadFromInvoice($invoice)) {
                $this->attachXmlReader();
            }
        }

        $this->renderQrCode();
    }

    /**
     * Carga el XML del CFDI y crea el reader
     */
    private function attachXmlReader(): void
    {
        $this->xml = $this->storage->getXml($this->cfdi) ?? '';

        if ($this->xml) {
            $this->reader = new CfdiQuickReader($this->xml);
        }
    }

    /**
     * Ejecuta acciones previas (AJAX requests)
     */
    private function handleAsyncActions(string $action): bool
    {
        switch ($action) {
            case 'cfdi-relacionado':
                $this->apiFindCfdi();
                return true;

            case 'search-related-cfdis':
                $this->apiSearchRelated();
                return true;

            default:
                return false;
        }
    }

    /**
     * Ejecuta la acción principal
     */
    private function handleAction(string $action): void
    {
        switch ($action) {
            case 'download-xml':
                $this->exportXml();
                break;

            case 'download-pdf':
                $this->exportPdf();
                break;

            case 'enviar-email':
                $this->sendEmail();
                break;

            case 'timbrar':
                $this->stamp();
                break;

            case 'cancelar':
                $this->cancel();
                break;

            case 'status':
                $this->checkSatStatus();
                break;
        }
    }

    /**
     * Acción: Timbrar factura
     */
    private function stamp(): void
    {
        if (!$this->factura->exists()) {
            Tools::log()->warning('La factura no existe');
            return;
        }

        $relations = $this->mapRelatedCfdisFromRequest();
        $result = $this->cfdiService->stampInvoice($this->factura, $relations);

        if ($result->isSuccess()) {
            $this->cfdi = $result->getCfdi();
            $this->xml = $result->getXml();
            $this->attachXmlReader();
            Tools::log()->notice('CFDI timbrado correctamente');
        } else {
            Tools::log()->error($result->getMessage());
        }
    }

    /**
     * Acción: Cancelar CFDI
     */
    private function cancel(): void
    {
        $result = $this->cfdiService->cancelCfdi($this->factura, $this->cfdi);

        if ($result->isSuccess()) {
            Tools::log()->notice('CFDI cancelado correctamente');
        } else {
            Tools::log()->error($result->getMessage());
        }
    }

    /**
     * Acción: Consultar estado en SAT
     */
    private function checkSatStatus(): void
    {
        $emisorRfc = $this->factura->getCompany()->cifnif;
        $receptorRfc = $this->cfdi->receptor_rfc;

        $status = $this->cfdiService->checkSatStatus($this->cfdi, $emisorRfc, $receptorRfc);

        Tools::log()->notice('Estatus del comprobante: ' . $status['cfdi']);
        Tools::log()->notice('Es cancelable: ' . $status['cancelable']);
        Tools::log()->notice('Estado de la cancelación: ' . $status['cancellation']);
    }

    /**
     * Acción: Descargar XML
     */
    private function exportXml(): void
    {
        $this->setTemplate(false);

        $xmlLocataion = $this->storage->cfdiFilePath($this->cfdi);

        if (null === $xmlLocataion) {
            Tools::log()->warning('Error al cargar el archivo xml.');
            return;
        }

        $this->response->download($xmlLocataion, $this->cfdi->filename);
        $this->response->send();
    }

    /**
     * Acción: Descargar PDF
     */
    private function exportPdf(): void
    {
        $xml = $this->storage->getXml($this->cfdi);
        $reader = new CfdiQuickReader($xml);

        $logoID = $this->factura->getCompany()->idlogo;
        $pdf = new PDFCfdi($reader, $logoID);

        $this->response->pdf($pdf->getPdfBuffer(), $this->factura->codigo);
        $this->response->send();
    }

    /**
     * Acción: Enviar email
     */
    private function sendEmail(): void
    {
        $xml = $this->storage->getXml($this->cfdi);

        if (!$xml) {
            Tools::log()->error('No se pudo obtener el XML del CFDI para enviar por email');
            return;
        }

        $emailService = new CfdiEmailService();

        if ($emailService->send($this->cfdi, $this->factura, $xml)) {
            $this->storage->updateMailDate($this->cfdi);
            Tools::log()->notice('CFDI enviado por email correctamente');
        } else {
            Tools::log()->warning('No se pudo enviar el CFDI por email');
        }
    }

    // ========== Métodos auxiliares para la vista ==========

    public function getCatalogoSat(): CfdiCatalogo
    {
        return new CfdiCatalogo();
    }

    public function getCfdiUsageCatalog(): array
    {
        return CfdiCatalogo::usoCfdi()->all();
    }

    public function getCustomerCfdiUsage(): string
    {
        if ($this->isEgresoInvoice()) {
            return 'G02';
        }

        $cliente = $this->factura->getSubject();
        return $cliente->usoCfdi();
    }

    public function getCfdiRelation(): string
    {
        return $this->isEgresoInvoice() ? '01' : '';
    }

    public function getPendingInvoices(): array
    {
        $invoice = new FacturaCliente();
        $stampedState = CfdiSettings::stampedInvoiceStatus($this->empresa);
        $canceledState = CfdiSettings::canceledInvoiceStatus($this->empresa);

        $where = [
            new DataBaseWhere('idestado', $stampedState, '!='),
            new DataBaseWhere('idestado', $canceledState, '!='),
        ];

        return $invoice->all($where);
    }

    public function getRelatedCfdis(): array
    {
        $relatedCfdis = [];

        foreach ($this->factura->parentDocuments() as $parent) {
            if ($parent->modelClassName() !== 'FacturaCliente') {
                continue;
            }

            $cfdi = new CfdiCliente();
            if ($cfdi->loadFromInvoice($parent->id())) {
                $relatedCfdis[] = $cfdi;
            }
        }

        return $relatedCfdis;
    }

    public function isEgresoInvoice(): bool
    {
        return CfdiSettings::serieEgreso() === $this->factura->codserie;
    }

    public function isGlobalInvoiceCustomer(): bool
    {
        return Validator::validateGlobalInvoice($this->factura);
    }

    public function url(): string
    {
        return parent::url() . '?code=' . $this->cfdi->id;
    }

    /**
     * Verifica si la configuración de CFDI está completa
     * Útil para mostrar advertencias en la vista
     *
     * @return array Array con 'valid' (bool) y 'errors' (array)
     */
    public function checkConfiguration(): array
    {
        $result = ['valid' => true, 'errors' => []];

        try {
            CfdiServiceFactory::createStampProvider($this->empresa);
        } catch (CfdiConfigurationException $e) {
            $result['valid'] = false;
            $result['errors'] = array_values($e->getMissingSettings());
        }

        return $result;
    }

    protected function renderQrCode(): void
    {
        if (!$this->cfdi->id) return;

        $qrFile = CFDI_DIR . DIRECTORY_SEPARATOR . 'qrcode.png';
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_Q,
            'quietzoneSize' => 3
        ]);

        $qrcode = new QRCode($options);
        $qrcode->render($this->reader->qrCodeUrl(), $qrFile);
    }

    // ========== Métodos privados auxiliares ==========

    /**
     * Extrae y formatea los CFDI relacionados desde la petición.
     *
     * @return array<int, array{tiporelacion: string, relacionados: array}>
     */
    private function mapRelatedCfdisFromRequest(): array
    {
        $input = $this->request()->input('relacionados');

        if (!is_array($input)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($uuids, $tipo) {
            if (empty($tipo) || empty($uuids)) {
                return null;
            }

            return [
                'tiporelacion' => (string)$tipo,
                'relacionados' => (array)$uuids
            ];
        }, $input, array_keys($input))));
    }

    private function apiFindCfdi(): void
    {
        $uuid = $this->request()->request->getString('uuid', '');
        $customerCode = $this->request()->request->getString('codcliente', '');

        $cfdi = new CfdiCliente();
        $isFound = $cfdi->loadFromUuid($uuid);

        if ($isFound && $cfdi->codcliente === $customerCode) {
            $this->jsonResponse($cfdi);
            return;
        }

        $this->jsonResponse([
            'error' => 'CFDI no encontrado o pertenece a otro cliente'
        ], 404);
    }

    private function apiSearchRelated(): void
    {
        $customerCode = $this->request()->input('codcliente', '');
        $type = $this->request()->input('tipo', '');
        $dateFrom = $this->request()->input('desde');
        $dateTo = $this->request()->input('hasta');

        $results = CfdiCliente::searchRelated($customerCode, $type, $dateFrom, $dateTo);

        $this->jsonResponse($results);
    }

    private function jsonResponse(mixed $data, int $status = 200): void
    {
        $this->setTemplate(false);
        $this->response->setHttpCode($status);
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setContent(json_encode($data));
    }
}
