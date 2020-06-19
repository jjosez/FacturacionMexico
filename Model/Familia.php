<?php
namespace FacturaScripts\Plugins\FacturacionMexico\Model;

use FacturaScripts\Core\Model\Familia as ParentModel;

class Familia extends ParentModel
{
    public $clavesat;
    public $claveunidad;

    public function clear()
    {
        parent::clear();

        $madre = $this->get($this->madre);
        $this->clavesat = ($madre) ? $this->madre->clavesat : '01010101';
        $this->claveunidad = 'Pieza';
    } 
}
