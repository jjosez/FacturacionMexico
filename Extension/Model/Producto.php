<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

class Producto
{
    public function clavesat()
    {
        return function () {
            return $this->clavesat ?? null;
        };
    }

    public function claveunidad()
    {
        return function () {
            return $this->claveunidad ?? null;
        };
    }
}
