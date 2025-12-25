<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware;

use DateTime;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiCatalogo;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiSettings;

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

        return array_any($catalog->all(), function ($item) use ($invoice) {
            return $invoice->codpago === $item->id;
        });
    }

    public static function validateGlobalInvoiceCustomer(FacturaCliente $invoice): bool
    {
        return $invoice->isGlobalInvoice()
            && CfdiSettings::rfcGenerico() === $invoice->cifnif
            && CfdiSettings::razonSocialGenerico() === $invoice->nombrecliente;
    }

    public static function validateGeneralCustomerLocation(FacturaCliente $invoice): bool
    {
        return CfdiSettings::rfcGenerico() === $invoice->cifnif
            && CfdiSettings::razonSocialGenerico() === $invoice->nombrecliente;
    }

    public static function validateRFC(string $rfc): bool
    {
        $rfc = strtoupper(trim($rfc));

        // Patrón oficial SAT: 3-4 letras, 6 dígitos de fecha, 3 caracteres homoclave
        $pattern = '/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/';

        return preg_match($pattern, $rfc) === 1;
    }
}
