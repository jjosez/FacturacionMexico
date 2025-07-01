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
namespace FacturaScripts\Plugins\FacturacionMexico\Model\Base;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\CompanyRelationTrait;

trait CfdiTrait
{
    use CompanyRelationTrait;

    /**
     * @var string
     */
    public $coddivisa;

    /**
     * @var string
     */
    public $estado;

    /**
     * @var string
     */
    public $fecha;

    /**
     * @var string
     */
    public $hora;

    /**
     * @var string
     */
    public $fechaemail;

    /**
     * @var string
     */
    public $formapago;

    /**
     * @var int
     */
    public $idcfdi;

    /**
     * @var int
     */
    public $idfactura;

    /**
     * @var string
     */
    public $metodopago;

    /**
     * @var string
     */
    public $razonreceptor;

    /**
     * @var string
     */
    public $rfcreceptor;

    /**
     * @var string
     */
    public $tipocfdi;

    /**
     * @var
     */
    public $total;

    /**
     * @var string
     */
    public $uuid;

    /**
     * @var string
     */
    public $version;

    public function loadFromInvoice($code): bool
    {
        $where = [new DataBaseWhere('idfactura', $code)];
        return $this->loadFromCode('', $where);
    }

    public function loadFromUuid($uuid): bool
    {
        $where = [new DataBaseWhere('uuid', $uuid)];
        return $this->loadFromCode('', $where);
    }

    public static function primaryColumn(): string
    {
        return 'idcfdi';
    }

    public function updateMailDate(): void
    {
        $this->fechaemail = date(self::DATE_STYLE);
    }
}
