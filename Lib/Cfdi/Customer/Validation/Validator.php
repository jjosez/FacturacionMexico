<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\Validation;

use DateTime;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\SAT\CfdiCatalogo;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CfdiSettings;

class Validator
{
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
     * @deprecated Usar CustomerValidator::validateRfcFormat() en su lugar
     */
    public static function validateRFC(string $rfc): bool
    {
        return CustomerValidator::validateRfcFormat($rfc);
    }

    /**
     * @deprecated Usar CustomerValidator::isValidForCfdi() en su lugar
     */
    public static function validateCustomerForCfdi(Cliente $cliente): bool
    {
        return CustomerValidator::isValidForCfdi($cliente);
    }
}
