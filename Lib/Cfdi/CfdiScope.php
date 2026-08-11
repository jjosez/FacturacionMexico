<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi;

enum CfdiScope: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';
}
