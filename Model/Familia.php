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
        $this->clavesat = $madre ? $madre->clavesat : '01010101';
        $this->claveunidad = 'H87';
    }

    public function loadFromData(array $data = [], array $exclude = [])
    {
        parent::loadFromData($data, $exclude);

        $this->clavesat = empty($this->clavesat) ? '01010101' : $this->clavesat;
        $this->claveunidad = empty($this->claveunidad) ? 'H87' : $this->claveunidad;
    }
}
