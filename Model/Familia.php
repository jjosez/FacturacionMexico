<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\FacturacionMexico\Model;

use FacturaScripts\Core\Model\Familia as ParentModel;

class Familia extends ParentModel
{
    public $clavesat;
    public $claveunidad;

    public function clear(): void
    {
        parent::clear();

        $madre = $this->get($this->madre);
        $this->clavesat = $madre ? $madre->clavesat : '01010101';
        $this->claveunidad = 'H87';
    }

    public function loadFromData(array $data = [], array $exclude = [], bool $sync = true): void
    {
        parent::loadFromData($data, $exclude, $sync);

        $this->clavesat = empty($this->clavesat) ? '01010101' : $this->clavesat;
        $this->claveunidad = empty($this->claveunidad) ? 'H87' : $this->claveunidad;
    }
}
