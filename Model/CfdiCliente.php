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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\FacturacionMexico\Model\Base\CfdiTrait;

class CfdiCliente extends Base\ModelClass
{
    use Base\ModelTrait;
    use CfdiTrait;

    public $codcliente;

    public $idfactura;

    /**
     * @var string
     */
    public $cfdiglobal;

    public function clear()
    {
        parent::clear();

        $this->created_at = date(self::DATETIME_STYLE);
        $this->nick = Session::user()->nick;
        $this->cfdiglobal = false;
    }

    public function getXml()
    {
        if (!$this->id) {
            return "";
        }

        return CfdiData::getXmlFromCfdi($this->id);
    }

    public static function tableName(): string
    {
        return 'cfdis_clientes';
    }

    public static function searchRelated(string $codcliente, string $tipoComprobante, string $fromDate = null, string $toDate = null): array
    {
        $where = [
            Where::eq('codcliente', $codcliente)
        ];

        if (!empty($tipoComprobante)) {
            $where[] = Where::eq('tipocfdi', $tipoComprobante);
        }

        return self::table()
            ->where($where)
            ->limit(15)
            ->orderBy('fecha', 'DESC')
            ->get();
    }

    public function test(): bool
    {
        $this->last_nick = Session::user()->nick;
        $this->updated_at = date(self::DATETIME_STYLE);

        return parent::test();
    }
}
