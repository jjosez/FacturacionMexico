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

/**
 * Operaciones realizadas terminales POS.
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class CfdiCliente extends Base\ModelClass
{
    use Base\ModelTrait;

    public $cfdirelacionado;
    public $codcliente;
    public $coddivisa;
    public $estado;
    public $fecha;
    public $hora;
    public $fechamod;   
    public $fechaemail;
    public $formapago;
    public $idcfdi;
    public $idempresa;
    public $idfactura;
    public $metodopago;
    public $razonreceptor;
    public $rfcreceptor;
    public $tipocfdi;
    public $tiporelacion;
    public $total;
    public $uuid;
    public $uuidrelacionado;
    public $version;

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

    public function loadFromInvoice($code)
    {
        $where = [new DataBaseWhere('idfactura', $code)];
        return $this->loadFromCode('', $where);
    }

    public function loadFromUuid($uuid)
    {
        $where = [new DataBaseWhere('uuid', $uuid)];
        return $this->loadFromCode('', $where);
    }

    public static function primaryColumn(): string
    {
        return 'idcfdi';
    }

    public static function tableName(): string
    {
        return 'cfdis_clientes';
    }
}
