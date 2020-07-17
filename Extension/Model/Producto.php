<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use FacturaScripts\Dinamic\Model\Familia;

class Producto
{
    public function familia()
    {
        return function () {
            $familia = new Familia();
            $familia->loadFromCode($this->codfamilia);

            return $familia;
        };
    }

    public function getUnidadMedida()
    {
        return function () {
            return 'H87';
        };
    }
}
