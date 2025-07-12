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

use FacturaScripts\Core\Model\Base;

class RelacionCfdiCliente extends Base\ModelClass
{
    use Base\ModelTrait;

    /**
     * ID interno de la relación
     * @var int
     */
    public $id;

    /**
     * ID del CFDI principal
     * @var int
     */
    public $cfdi_id;

    /**
     * ID del CFDI relacionado
     * @var int
     */
    public $cfdi_id_relacionado;

    /**
     * Tipo de relación SAT (por ejemplo 04, 07)
     * @var string
     */
    public $tipo_relacion;

    /**
     * @var string
     */
    public $uuid;

    /**
     * @var string
     */
    public $uuid_relacionado;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cfdis_clientes_relacion';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'uuid';
    }
}
