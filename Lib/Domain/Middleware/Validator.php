<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware;

use DateTime;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiCatalogo;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiSettings;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware\CustomerValidator;

class Validator
{
    /**
     * Valida que la fecha de creación no rebase 3 días respecto a la fecha de certificación
     *
     * @param FacturaCliente $invoice
     * @return bool True si está dentro del límite de 3 días
     */
    public static function validateCreationToStampDate(FacturaCliente $invoice): bool
    {
        $creationDateTime = new DateTime($invoice->fecha . ' ' . $invoice->hora);
        $currentDateTime = new DateTime();

        $interval = $creationDateTime->diff($currentDateTime);

        return $interval->days <= 3;
    }

    public static function validateFormaPago(FacturaCliente $invoice): bool
    {
        $catalog = CfdiCatalogo::formaPago();

        return array_any(array: $catalog->all(), callback: function ($item) use ($invoice) {
            return $invoice->codpago === $item->id;
        });
    }

    public static function validateGlobalInvoice(FacturaCliente $invoice): bool
    {
        return $invoice->isGlobalInvoice()
            && CfdiSettings::rfcGenerico() === $invoice->cifnif
            && CfdiSettings::razonSocialGenerico() === $invoice->nombrecliente;
    }

    public static function validateGlobalInvoiceCustomer(FacturaCliente $invoice)
    {
        return CfdiSettings::rfcGenerico() === $invoice->cifnif
            && CfdiSettings::razonSocialGenerico() === $invoice->nombrecliente;
    }

    public static function validateGeneralCustomerLocation(FacturaCliente $invoice): bool
    {
        return CfdiSettings::rfcGenerico() === $invoice->cifnif
            && CfdiSettings::razonSocialGenerico() === $invoice->nombrecliente;
    }

    /**
     * Valida el formato del RFC
     * @deprecated Usar CustomerValidator::validateRfcFormat() en su lugar
     */
    public static function validateRFC(string $rfc): bool
    {
        return CustomerValidator::validateRfcFormat($rfc);
    }

    /**
     * Valida que el cliente cumpla con las condiciones mínimas para recibir CFDI
     * @deprecated Usar CustomerValidator::isValidForCfdi() en su lugar
     *
     * @param Cliente $cliente
     * @return bool True si cumple con todos los requisitos
     */
    public static function validateCustomerForCfdi(Cliente $cliente): bool
    {
        return CustomerValidator::isValidForCfdi($cliente);
    }
}
