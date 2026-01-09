<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;
use FacturaScripts\Core\Model\Cliente as Customer;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware\CustomerValidator;

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
            return $this->getDefaultAddress()->codpostal;
        };
    }

    public function rfc(): Closure
    {
        return function (): string {
            return $this->cifnif;
        };
    }

    public function isValidForCfdi(): Closure
    {
        return function () {
            return CustomerValidator::isValidForCfdi($this);
        };
    }

    public function test(): Closure
    {
        return function () {
            $this->cifnif = CustomerValidator::cleanFiscalString($this->rfc());
            $this->nombre = CustomerValidator::cleanFiscalString($this->nombre);
            $this->razonsocial = CustomerValidator::cleanFiscalString($this->razonsocial);

            $this->personafisica = CustomerValidator::isPersonaFisica($this->rfc());

            return true;
        };
    }

    public function saveInsertBefore(): Closure
    {
        return function () {
            $customer = new Customer();

            if ($customer->loadWhereEq('cifnif', $this->rfc())
                && !CustomerValidator::isRfcGenerico($this->cifnif)) {
                Tools::log()->warning('El RFC ya se encuentra registrado');

                return false;
            }

            return true;
        };
    }
}
