<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019-2025 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use Exception;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\CfdiBuildResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\StampResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiFactory;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\CfdiRepositoryInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts\StampProviderInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Enums\CfdiStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware\RelationValidator;
use PhpCfdi\Credentials\Credential;

class CfdiService
{
    private CfdiRepositoryInterface $repository;
    private StampProviderInterface $stampProvider;

    public function __construct(StampProviderInterface $stampProvider, CfdiRepositoryInterface $storage)
    {
        $this->repository = $storage;
        $this->stampProvider = $stampProvider;
    }

    /**
     * Procesa el timbrado completo de una factura
     *
     * @param FacturaCliente $factura
     * @param array $relations
     * @return CfdiStampResult
     */
    public function stampInvoice(FacturaCliente $factura, array $relations = []): CfdiStampResult
    {
        $buildResult = $this->buildCfdi($factura, $relations);

        if ($buildResult->hasError()) {
            return $this->handleBuildError($buildResult);
        }

        $stampResult = $this->stampProvider->stamp($buildResult->xml());

        if ($stampResult->hasError() && $stampResult->hasPreviousStamp()) {
            $stampResult = $this->handlePreviousStamp($buildResult);
        }

        if ($stampResult->hasError()) {
            return $this->handleStampError($stampResult);
        }

        $savedCfdi = $this->repository->save($factura, $stampResult->getXml());

        if (null === $savedCfdi) {
            return CfdiStampResult::failed($stampResult);
        }

        if (!$this->repository->saveXml($savedCfdi, $stampResult->getXml())) {
            return CfdiStampResult::failed($stampResult);
        }

        $this->updateInvoiceStatus($factura, CfdiStatus::STAMPED);

        return CfdiStampResult::success($savedCfdi, $stampResult);
    }

    /**
     * Construye el XML del CFDI según el tipo de factura
     *
     * @param FacturaCliente $invoice
     * @param array $relations
     * @return CfdiBuildResult
     */
    private function buildCfdi(FacturaCliente $invoice, array $relations): CfdiBuildResult
    {
        if (!RelationValidator::validate($invoice, $relations)) {
            return new CfdiBuildResult('', 'Relaciones de CFDI inválidas', true);
        }

        $serieEgreso = CfdiSettings::serieEgreso();

        if ($invoice->codserie === $serieEgreso) {
            return CfdiFactory::buildCfdiEgreso($invoice, $relations);
        }

        if ($invoice->isGlobalInvoice()) {
            return CfdiFactory::buildCfdiGlobal($invoice, $relations);
        }

        return CfdiFactory::buildCfdiIngreso($invoice, $relations);
    }

    /**
     * Cancela un CFDI ante el SAT
     *
     * @param CfdiCliente $cfdi
     * @return CfdiStampResult
     */
    public function cancelCfdi(FacturaCliente $invoice, CfdiCliente $cfdi): CfdiStampResult
    {
        try {
            $credentials = CfdiSettings::satCredentials($invoice->getCompany());

            $credential = Credential::openFiles(
                $credentials['certificado'],
                $credentials['llave'],
                $credentials['secreto']
            );

            $cancelResult = $this->stampProvider->cancel($cfdi->uuid, $credential);

            if (!$cancelResult->hasError()) {
                $this->updateInvoiceStatus($invoice, CfdiStatus::CANCELLED);
                $this->repository->updateStatus($cfdi, CfdiStatus::CANCELLED);
            }

            return CfdiStampResult::fromStampResult($cancelResult, $cfdi);
        } catch (Exception $e) {
            return CfdiStampResult::failed(
                new StampResult(true, '', '', 'Error al cancelar CFDI: ' . $e->getMessage())
            );
        }
    }

    /**
     * Consulta el estado de un CFDI en el SAT
     *
     * @param CfdiCliente $cfdi
     * @param string $emisorRfc
     * @param string $receptorRfc
     * @return array
     */
    public function checkSatStatus(CfdiCliente $cfdi, string $emisorRfc, string $receptorRfc): array
    {
        $query = [
            'emisor' => $emisorRfc,
            'receptor' => $receptorRfc,
            'uuid' => $cfdi->uuid,
            'total' => $cfdi->total
        ];

        $status = $this->stampProvider->getStatus($query);

        return [
            'cfdi' => $status->cfdiStatus ?? 'desconocido',
            'cancelable' => $status->cancelableStatus ?? 'desconocido',
            'cancellation' => $status->cancellationStatus ?? 'desconocido',
        ];
    }

    /**
     * Actualiza el estado de la factura al timbrar correctamente un CFDI
     */
    private function updateInvoiceStatus(FacturaCliente $factura, CfdiStatus $status): void
    {
        switch ($status) {
            case CfdiStatus::STAMPED:
                $factura->idestado = CfdiSettings::stampedInvoiceStatus($factura->getCompany());
                break;
            case CfdiStatus::CANCELLED:
                $factura->idestado = CfdiSettings::canceledInvoiceStatus($factura->getCompany());
                break;
        }

        $factura->save();
    }

    /**
     * Maneja el error generado al construir el xml del CFDI.
     */
    private function handleBuildError(CfdiBuildResult $result): CfdiStampResult
    {
        $errorMessage = 'Error al construir CFDI: ' . $result->getBuildMessage();

        return CfdiStampResult::failed(
            new StampResult(true, '', '', $errorMessage)
        );
    }

    /**
     * Maneja el error generado al enviar el xml al PAC para su timbrado.
     */
    private function handleStampError(StampResult $result): CfdiStampResult
    {
        $errorMessage = 'Error al timbrar el CFDI: ' . $result->getMessage();

        return CfdiStampResult::failed(
            new StampResult(true, '', '', $errorMessage)
        );
    }

    private function handlePreviousStamp(CfdiBuildResult $result): StampResult
    {
        return $this->stampProvider->getStamped($result->xml());
    }
}
