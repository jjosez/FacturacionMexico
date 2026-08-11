<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Shared;

enum CfdiScope: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';
}
