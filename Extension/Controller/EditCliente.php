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

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

use Closure;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiCatalogo;

/**
 * @method addButton(string $string, string[] $array)
 */
class EditCliente
{
    public function createViews(): Closure
    {
        return function () {
            $viewName = $this->getMainViewName();
            $column = $this->views[$viewName]->columnForName('regimenfiscal');
            if ($column && $column->widget->getType() === 'select') {

                foreach (CfdiCatalogo::regimenFiscal()->all() as $item) {
                    $regimenValues[] = ['value' => $item->id, 'title' => $item->id . ' - ' . $item->descripcion];
                }
                $column->widget->setValuesFromArray($regimenValues, false, true);
            }

            $column = $this->views[$viewName]->columnForName('usocfdi');
            if ($column && $column->widget->getType() === 'select') {

                foreach (CfdiCatalogo::usoCfdi()->all() as $item) {
                    $usoValues[] = ['value' => $item->id, 'title' => $item->id . ' - ' . $item->descripcion];
                }
                $column->widget->setValuesFromArray($usoValues, false, true);
            }
        };
    }
}
