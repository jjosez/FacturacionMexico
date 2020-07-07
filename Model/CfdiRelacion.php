<?php
/**
 * This file is part of POS plugin for FacturaScripts
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

use FacturaScripts\Core\Model\Base;

/**
 * Relacion entre cefdi con su tipo de relacion.
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class CfdiRelacion extends Base\ModelClass
{
    use Base\ModelTrait;

    public $idrelacion;
    public $idcfdi;
    public $tiporelacion;
    public $uuid;

    public static function primaryColumn()
    {
        return 'idrelacion';
    }

    public static function tableName()
    {
        return 'cfdisrelacionados';
    }
}
