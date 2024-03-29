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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiTrait;

/**
 * Operaciones realizadas terminales POS.
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class CfdiCliente extends Base\ModelClass
{
    use Base\ModelTrait;
    use CfdiTrait;

    public $codcliente;

    public function clear()
    {
        parent::clear();

        $this->fecha = date(self::DATE_STYLE);
        $this->hora = date(self::HOUR_STYLE);
    }

    public function getXml()
    {
        $data = new CfdiData();
        $where = [new DataBaseWhere('idcfdi', $this->idcfdi)];

        if ($data->loadFromCode('', $where)) {
            return $data->xml;
        }
        return false;
    }

    public static function tableName(): string
    {
        return 'cfdis_clientes';
    }
}
