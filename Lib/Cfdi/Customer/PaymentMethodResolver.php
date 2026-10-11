<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer;

use FacturaScripts\Dinamic\Model\FacturaCliente;

final class PaymentMethodResolver
{
    public static function resolve(FacturaCliente $invoice): array
    {
        if (!$invoice->paid()) {
            return [
                'FormaPago' => '99',
                'MetodoPago' => 'PPD',
            ];
        }

        return [
            'FormaPago' => (string)($invoice->getPaymentMethod()->clavesat ?? ''),
            'MetodoPago' => 'PUE',
        ];
    }
}
