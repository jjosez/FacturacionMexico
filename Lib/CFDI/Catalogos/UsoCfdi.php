<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Catalogos;

class UsoCfdi extends SatCatalogo
{
    public function __construct(string $catalogName = 'c_UsoCFDI.json')
    {
        parent::__construct($catalogName);
    }
}
