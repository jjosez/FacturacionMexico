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

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

class CfdiData extends ModelClass
{
    use ModelTrait;

    public $idcfdi;
    public $xml;
    public $uuid;

    /**
     * @param string $code id from cfdi
     * @return string
     */
    public static function getXmlFromCfdi(string $code): string
    {
        $result = self::table()->whereEq('idcfdi', $code)->first();

        return $result['xml'] ?? "";
    }

    public static function primaryColumn(): string
    {
        return 'uuid';
    }

    public static function tableName(): string
    {
        return 'cfdis_data';
    }
}
