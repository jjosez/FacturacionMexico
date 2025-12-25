<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019-2025 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\StampResult;

/**
 * Resultado de operaciones CFDI (timbrado y cancelación)
 * Combina el modelo (CfdiCliente) con el resultado del PAC (StampResult)
 */
class CfdiStampResult
{
    private ?CfdiCliente $cfdi;
    private StampResult $stampResult;

    private function __construct(?CfdiCliente $cfdi, StampResult $stampResult)
    {
        $this->cfdi = $cfdi;
        $this->stampResult = $stampResult;
    }

    /**
     * Crea un resultado exitoso con CFDI guardado
     */
    public static function success(CfdiCliente $cfdi, StampResult $stampResult): self
    {
        return new self($cfdi, $stampResult);
    }

    /**
     * Crea un resultado fallido sin CFDI
     */
    public static function failed(StampResult $stampResult): self
    {
        return new self(null, $stampResult);
    }

    /**
     * Crea resultado para operaciones que no modifican el CFDI (ej: cancelación)
     */
    public static function fromStampResult(StampResult $stampResult, ?CfdiCliente $cfdi = null): self
    {
        return new self($cfdi, $stampResult);
    }

    public function isSuccess(): bool
    {
        return !$this->stampResult->hasError();
    }

    public function getCfdi(): ?CfdiCliente
    {
        return $this->cfdi;
    }

    public function getStampResult(): StampResult
    {
        return $this->stampResult;
    }

    // ========== Métodos delegados para acceso directo ==========

    public function getXml(): string
    {
        return $this->stampResult->getXml();
    }

    public function getUuid(): string
    {
        return $this->stampResult->getUuid();
    }

    public function getMessage(): string
    {
        return $this->stampResult->getMessage();
    }

    public function getCode(): string
    {
        return $this->stampResult->getCode();
    }

    public function getDetail(): string
    {
        return $this->stampResult->getDetail();
    }

    public function hasError(): bool
    {
        return $this->stampResult->hasError();
    }

    public function hasPreviousStamp(): bool
    {
        return $this->stampResult->hasPreviousStamp();
    }
}
