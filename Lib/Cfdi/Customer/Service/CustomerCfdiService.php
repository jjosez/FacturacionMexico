<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019-2025 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\Service;

use Exception;
use Closure;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\CfdiBuildResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\DTO\StampResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiStampException;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\Build\CfdiFactory;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\CfdiRelationService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\CfdiStampResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\CustomerCfdiRepository;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Storage\CfdiStorageInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Stamp\StampProviderInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiScope;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\Validation\RelationValidator;
use FacturaScripts\Plugins\FacturacionMexico\Lib\SAT\CertificateService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\SAT\SatStatusService;

class CustomerCfdiService
{
    private CfdiStorageInterface $storage;
    private CustomerCfdiRepository $cfdiRepository;
    private StampProviderInterface $stampProvider;
    private CfdiRelationService $relationService;
    private DataBase $dataBase;
    private ?Closure $buildCallback;
    private CertificateService $certificates;
    private SatStatusService $satStatus;

    public function __construct(
        StampProviderInterface $stampProvider,
        CfdiStorageInterface $storage,
        CfdiRelationService $relationService,
        ?CustomerCfdiRepository $cfdiRepository = null,
        ?Closure $buildCallback = null,
        ?CertificateService $certificates = null,
        ?SatStatusService $satStatus = null
    ) {
        $this->storage = $storage;
        $this->stampProvider = $stampProvider;
        $this->relationService = $relationService;
        $this->cfdiRepository = $cfdiRepository ?? new CustomerCfdiRepository();
        $this->dataBase = new DataBase();
        $this->buildCallback = $buildCallback;
        $this->certificates = $certificates ?? new CertificateService();
        $this->satStatus = $satStatus ?? new SatStatusService();
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
        // Validar relaciones adicionales (además de la validación en buildCfdi)
        if (!empty($relations) && !$this->relationService->validateRelations($factura->codcliente, $relations)) {
            return CfdiStampResult::failed(
                new StampResult(true, '', '', 'Las relaciones de CFDI no son válidas')
            );
        }

        $buildResult = $this->buildCfdi($factura, $relations);

        if ($buildResult->hasError()) {
            return $this->handleBuildError($buildResult);
        }

        try {
            $stampResult = $this->stampProvider->stamp($buildResult->xml());
        } catch (CfdiStampException $e) {
            return CfdiStampResult::failed(new StampResult(true, '', '', $e->getMessage()));
        }

        if ($stampResult->hasError() && $stampResult->hasPreviousStamp()) {
            $stampResult = $this->handlePreviousStamp($buildResult);
        }

        if ($stampResult->hasError()) {
            return $this->handleStampError($stampResult);
        }

        $storageSaved = false;
        $savedCfdi = null;
        try {
            if (!$this->dataBase->beginTransaction()) {
                throw new Exception('No se pudo iniciar la transacción del CFDI.');
            }

            $data = (new CfdiParser($stampResult->getXml()))->parse();
            if ($data->uuid === null) {
                throw new Exception('El XML timbrado no contiene UUID.');
            }

            $savedCfdi = $this->cfdiRepository->createFromInvoice($factura, $data);
            if ($savedCfdi === null) {
                throw new Exception('No se pudo guardar el metadata del CFDI.');
            }

            $this->storage->save(CfdiScope::CUSTOMER, $savedCfdi->uuid, $stampResult->getXml());
            $storageSaved = true;

            if (!empty($relations) && !$this->relationService->saveCfdiRelations($savedCfdi, $relations)) {
                throw new Exception('No se pudieron guardar las relaciones del CFDI.');
            }

            if (!$this->updateInvoiceStatus($factura, CfdiStatus::STAMPED)) {
                throw new Exception('No se pudo actualizar el estado de la factura.');
            }

            if (!$this->dataBase->commit()) {
                throw new Exception('No se pudo confirmar la transacción del CFDI.');
            }
        } catch (Exception $e) {
            if ($this->dataBase->inTransaction()) {
                $this->dataBase->rollback();
            }

            if ($storageSaved && $savedCfdi !== null) {
                $this->storage->delete(CfdiScope::CUSTOMER, $savedCfdi->uuid);
            }

            return $this->persistenceFailure($stampResult, $e->getMessage());
        }

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
        if ($this->buildCallback !== null) {
            return ($this->buildCallback)($invoice, $relations);
        }

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
            $cancelResult = $this->stampProvider->cancel(
                $cfdi->uuid,
                $this->certificates->open($invoice->getCompany())
            );

            if (!$cancelResult->hasError()) {
                $this->updateInvoiceStatus($invoice, CfdiStatus::CANCELLED);
                $this->cfdiRepository->updateStatus($cfdi, CfdiStatus::CANCELLED);
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
        $status = $this->satStatus->check($this->stampProvider, $cfdi, $emisorRfc, $receptorRfc);

        return [
            'cfdi' => $status->cfdiStatus ?? 'desconocido',
            'cancelable' => $status->cancelableStatus ?? 'desconocido',
            'cancellation' => $status->cancellationStatus ?? 'desconocido',
        ];
    }

    public function getXml(CfdiCliente $cfdi): ?string
    {
        return $this->storage->get(CfdiScope::CUSTOMER, $cfdi->uuid);
    }

    public function updateMailDate(CfdiCliente $cfdi): bool
    {
        return $this->cfdiRepository->updateMailDate($cfdi);
    }

    /**
     * Actualiza el estado de la factura al timbrar correctamente un CFDI
     */
    private function updateInvoiceStatus(FacturaCliente $factura, CfdiStatus $status): bool
    {
        switch ($status) {
            case CfdiStatus::STAMPED:
                $factura->idestado = CfdiSettings::stampedInvoiceStatus($factura->getCompany());
                break;
            case CfdiStatus::CANCELLED:
                $factura->idestado = CfdiSettings::canceledInvoiceStatus($factura->getCompany());
                break;
        }

        return $factura->save();
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

    private function persistenceFailure(StampResult $stampResult, string $reason): CfdiStampResult
    {
        return CfdiStampResult::failed(new StampResult(
            true,
            $stampResult->getUuid(),
            $stampResult->getXml(),
            'El CFDI fue timbrado, pero no se pudo guardar localmente: ' . $reason
        ));
    }
}
