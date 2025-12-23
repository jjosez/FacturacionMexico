<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
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

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Response;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Contract\CfdiStorageInterface;
use FacturaScripts\Plugins\FacturacionMexico\Contract\StampProviderInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\CfdiBuildResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiFactory;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware\RelationValidator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Middleware\Validator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\PDF\PDFCfdi;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Providers\FinkokStampService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiDatabaseStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiEmailService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiFileStorage;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Services\CfdiQuickReader;

class EditCfdiCliente extends Controller
{
    public CfdiCliente $cfdi;
    public FacturaCliente $factura;
    public CfdiQuickReader $reader;

    public string $xml;

    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'CFDI Cliente';
        $pagedata['icon'] = 'fas fa-sliders-h';
        $pagedata['menu'] = 'CFDI';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $this->cfdi = new CfdiCliente();
        $this->factura = new FacturaCliente();

        $action = $this->request->get('action', '');
        $code = $this->request->query->get('code', '');
        $invoice = $this->request->query->get('invoice', '');

        if (true === $this->execPreviousAction($action)) {
            return;
        }

        if ($code && $this->cfdi->loadFromCode($code)) {
            $this->loadInvoiceFromCode($this->cfdi->primaryColumnValue());
            $this->loadCfdiReader();
        }

        if ($invoice && $this->loadInvoiceFromCode($invoice)) {
            if (true === $this->cfdi->loadFromInvoice($invoice)) {
                $this->loadCfdiReader();
            }
        }

        $template = $this->cfdi->id ? 'CfdiCliente' : 'CfdiClienteWizard';
        $this->setTemplate($template);

        $this->execAction($action);
    }

    public function getCatalogoSat(): CfdiCatalogo
    {
        return new CfdiCatalogo();
    }

    public function getCfdiUsageCatalog(): array
    {
        $catalog = CfdiCatalogo::usoCfdi();

        return $catalog->all();
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

    public function getCustomerCfdiUsage(string $codcliente): string
    {
        if ($this->isEgresoInvoice())
            return 'G02';

        $cliente = new Cliente();
        $cliente->loadFromCode($codcliente);

        return $cliente->getCfdiUsage();
    }

    public function getCfdiRelation(): string
    {
        return $this->isEgresoInvoice() ? '01' : '';
    }

    private function findCfdiRequest(): void
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

    private function searchRelatedCfdis(): array
    {
        $codcliente = $this->request->request->get('codcliente', '');
        $tipo = $this->request->request->get('tipo', '');
        $desde = $this->request->request->get('desde', '');
        $hasta = $this->request->request->get('hasta', '');

        $result = CfdiCliente::searchRelated($codcliente, $tipo, $desde, $hasta);
        $this->response->setContent(json_encode($result));

        return $result;
    }

    public function loadCfdiFromUUID($uuid): bool|CfdiCliente
    {
        $cfdi = new CfdiCliente();

        return $cfdi->loadFromUuid($uuid) ? $cfdi : false;
    }

    private function execPreviousAction(string $action): bool
    {
        switch ($action) {
            case 'cfdi-relacionado':
                $this->findCfdiRequest();
                return true;
            case 'search-related-cfdis':
                $this->searchRelatedCfdis();
                return true;
            default:
                return false;
        }
    }

    private function execAction($action): void
    {
        switch ($action) {
            case 'download-xml':
                $this->downloadInvoiceXML();
                return;

            case 'download-pdf':
                $this->downloadInvoicePDF();
                return;

            case 'enviar-email':
                $this->sendInvoiceEmail();
                return;

            case 'timbrar':
                if ($this->stampInvoice()) {
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

    protected function cfdiCancelRequest(): void
    {
        $service = $this->stampServiceProvider();

        if ($service->cancel($this->cfdi->uuid)) {
            Tools::log()->notice('Cfdi cancelado correctamente');

            $storage = $this->storageServiceProvider();
            $storage->updateStatus($this->cfdi, 'Cancelado');
            return;
        }

        Tools::log()->error('No se pudo cancelar el cfdi');
    }

    protected function sendInvoiceEmail(): void
    {
        $emailService = new CfdiEmailService();

        $emailService->send($this->cfdi, $this->factura, $this->xml);
    }

    protected function stampInvoice(): bool
    {
        if (false === $this->factura->exists()) {
            Tools::log()->warning('La factura no existe');
            return false;
        }

        $buildResult = $this->buildCfdi();
        if ($buildResult->hasError()) {
            Tools::log()->error('Error al construir el cfdi.');
            Tools::log()->error($buildResult->getBuildMessage());
            return false;
        }

        $this->xml = $buildResult->getXml();
        $xmlFinal = $this->stampCfdi($this->xml);
        if (null === $xmlFinal) {
            return false;
        }

        $cfdi = $this->storeCfdi($xmlFinal);

        if (null === $cfdi) {
            Tools::log()->error('Error al guardar el cfdi');
            return false;
        }

        $this->cfdi = $cfdi;
        $this->loadCfdiReader();

        $this->updateStampedInvoiceStatus();
        return true;
    }

    protected function buildCfdi(): CfdiBuildResult
    {
        $relations = $this->processCfdiRelacionadosRequest();

        if (!RelationValidator::validate($this->factura, $relations)) {
            return new CfdiBuildResult('', 'Error al validar los CFDI relacionados.', true);
        }

        if ($this->isEgresoInvoice()) {
            return CfdiFactory::buildCfdiEgreso($this->factura, $relations);
        }

        if ($this->isGlobalInvoice()) {
            return CfdiFactory::buildCfdiGlobal($this->factura, $relations);
        }

        return CfdiFactory::buildCfdiIngreso($this->factura, $relations);
    }

    protected function stampCfdi(string $xml): ?string
    {
        $provider = $this->stampServiceProvider();
        $stampResult = $provider->stamp($xml);

        if (!$stampResult->hasError()) {
            return $stampResult->getXml();
        }

        Tools::log()->error($stampResult->getMessage());

        if (!$stampResult->hasPreviousStamp()) {
            return null;
        }

        Tools::log()->notice('Obtenido timbre previo');
        $previousResult = $provider->getStamped($xml);

        if ($previousResult->hasError()) {
            Tools::log()->error($previousResult->getMessage());
            return null;
        }

        return $previousResult->getXml();
    }

    protected function storeCfdi(string $xml): ?CfdiCliente
    {
        $storage = $this->storageServiceProvider();
        $cfdi = $storage->save($this->factura, $xml);

        if ($cfdi && $storage->saveXml($cfdi, $xml)) {
            if ($this->isGlobalInvoice()) {
                $cfdi->cfdiglobal = true;
                $cfdi->save();
            }

            return $cfdi;
        }

        Tools::log()->error('Error al guardar el cfdi');
        return null;
    }

    protected function cfdiStatusRequest(): void
    {
        $service = $this->stampServiceProvider();
        $query = [
            'emisor' => $this->empresa->cifnif,
            'receptor' => $this->cfdi->receptor_rfc,
            'uuid' => $this->cfdi->uuid,
            'total' => $this->cfdi->total
        ];

        $status = $service->getStatus($query);

        Tools::log()->warning('Estatus del comprobante: ' . $status->cfdiStatus);
        Tools::log()->warning('Es cancelable: ' . $status->cancelableStatus);
        Tools::log()->warning('Estado de la cancelación: ' . $status->cancellationStatus);
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
        $logoID = $this->factura->getCompany()->idlogo;
        $pdf = new PDFCfdi($this->reader, $logoID);

        $pdf->downloadPDF();
    }

    protected function isGlobalInvoice(): bool
    {
        $globalInvoiceRequest = $this->request->request->get('globalinvoice', false);

        return $globalInvoiceRequest && $this->isGlobalInvoiceCustomer();
    }

    protected function loadCfdiReader(): void
    {
        $storage = $this->storageServiceProvider();
        $this->xml = $storage->getXml($this->cfdi);

        $this->reader = new CfdiQuickReader($this->xml);
    }

    protected function loadInvoiceFromCode(string $code): bool
    {
        if (true === $this->factura->loadFromCode($code)) {
            return true;
        }

        Tools::log()->warning('Factura no encontrada');
        return false;
    }

    protected function stampServiceProvider(): StampProviderInterface
    {
        $username = Tools::settings('cfdi', 'stamp-user');
        $token = Tools::settings('cfdi', 'stamp-token');
        $testMode = Tools::settings('cfdi', 'test-mode', true);

        return new FinkokStampService($username, $token, $testMode);
    }

    protected function storageServiceProvider(): CfdiStorageInterface
    {
        $storageType = Tools::settings('cfdi', 'storage-type', 'file');

        return match ($storageType) {
            'database' => new CfdiDatabaseStorage(),
            default => new CfdiFileStorage(),
        };
    }

    private function processCfdiRelacionadosRequest(): array
    {
        $relationsRequest = $this->request->request->get('relacionados', []);
        $relations = [];

        foreach ($relationsRequest as $tipo => $uuids) {
            if (!empty($tipo) && !empty($uuids)) {
                $relations[] = [
                    'tiporelacion' => $tipo,
                    'relacionados' => $uuids
                ];
            }
        }

        return $relations;
    }

    protected function updateStampedInvoiceStatus(): void
    {
        $this->factura->idestado = CfdiSettings::stampedInvoiceStatus();

        if (true === $this->factura->save()) {
            Tools::log()->notice('Factura actualizada correctamente');
        }
    }

    public function getPendingInvoices(): array
    {
        $invoice = new FacturaCliente();
        $stampedState = CfdiSettings::stampedInvoiceStatus();
        $canceledState = CfdiSettings::canceledInvoiceStatus();

        $where = [
            new DataBaseWhere('idestado', $stampedState, '!='),
            new DataBaseWhere('idestado', $canceledState, '!='),
        ];

        return $invoice->all($where);
    }

    public function isEgresoInvoice(): bool
    {
        return CfdiSettings::serieEgreso() === $this->factura->codserie;
    }

    public function isGlobalInvoiceCustomer(): bool
    {
        return Validator::validateGlobalInvoiceCustomer($this->factura);
    }

    public function url(): string
    {
        return parent::url() . '?code=' . $this->cfdi->id;
    }
}
