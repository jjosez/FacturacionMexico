<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Model;

use FacturaScripts\Core\Model\Cliente as ParentModel;

class Cliente extends ParentModel
{
    /**
     * @var string
     */
    public $regimenfiscal;

    /**
     * @var string
     */
    public $usocfdi;
}
