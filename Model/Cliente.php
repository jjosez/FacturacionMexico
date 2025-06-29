<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Model;

use FacturaScripts\Core\Model\Cliente as ParentModel;
use FacturaScripts\Core\Tools;

class Cliente extends ParentModel
{
    /**
     * @var string
     */
    public ?string $regimenfiscal;

    /**
     * @var string
     */
    public ?string $usocfdi;

    public function getCfdiUsage(): string
    {
        return $this->usocfdi ?? Tools::settings('cfdi', 'uso', 'P01');
    }
}
