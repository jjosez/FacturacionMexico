<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Model;

use Closure;

/**
 * @property bool $clavesat
 * @property bool $claveunidad
 * @property string $madre
 * @method find($madre)
 */
class Familia
{
    public function clavesat(): Closure
    {
        return function () {
            return empty($this->clavesat) ? '01010101' : $this->clavesat;
        };
    }

    public function claveunidad(): Closure
    {
        return function () {
            return empty($this->claveunidad) ? 'H87' : $this->claveunidad;
        };
    }

    public function clear(): Closure
    {
        return function () {
            if (empty($this->claveunidad)) {
                $this->claveunidad = 'H87';
            }
        };
    }

    public function testBefore(): Closure
    {
        return function () {
            if (empty($this->clavesat)) {
                $madre = null;

                if (!empty($this->madre)) {
                    $madre = $this->find($this->madre);
                }

                $this->clavesat = $madre && !empty($madre->clavesat)
                    ? $madre->clavesat
                    : '01010101';
            }

            return true;
        };
    }
}
