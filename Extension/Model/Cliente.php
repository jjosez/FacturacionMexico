<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware\Validator;

/**
 * @method getDefaultAddress()
 * @property $cifnif
 */
class Cliente
{
    /**
     * @var string
     */
    public ?string $regimenfiscal;

    /**
     * @var string
     */
    public ?string $usocfdi;

    public function usoCfdi(): Closure
    {
        return function () {
            return $this->usocfdi;
        };
    }

    public function regimenFiscal(): Closure
    {
        return function () {
            return $this->regimenfiscal;
        };
    }

    public function domicilioFiscal(): Closure
    {
        return function () {
            $this->getDefaultAddress()->codpostal;
        };
    }

    public function rfc(): Closure
    {
        return function () {
            return $this->cifnif;
        };
    }

    public function isValidForCfdi(): Closure
    {
        return function () {
            return Validator::validateCustomerForCfdi($this);
        };
    }

    public function save(): Closure
    {
        return function () {
            $result = !Validator::validateCustomerForCfdi($this);

            if (true === $result) {
                Tools::log()->warning('Datos fiscales incorrectos.
                Verificar Constancia de Situación Fiscal para emisión de CFDI.');
            }
        };
    }
}
