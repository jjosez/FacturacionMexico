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
use FacturaScripts\Core\Session;

trait CfdiTrait
{
    use CompanyRelationTrait;

    /**
     * @var string
     * Código de divisa del CFDI
     */
    public $coddivisa;

    /**
     * @var string
     * Estado del CFDI (por ejemplo: timbrado, cancelado)
     */
    public $estado;

    /**
     * @var string
     * Fecha en que se envió el CFDI por correo electrónico
     */
    public $mail_at;

    /**
     * @var int
     * Identificador interno del CFDI
     */
    public $id;

    /**
     * @var string
     * RFC del emisor del CFDI
     */
    public $emisor_rfc;

    /**
     * @var string
     * Nombre del emisor del CFDI
     */
    public $emisor_nombre;

    /**
     * @var string
     * Fecha de emisión del CFDI
     */
    public $fecha_emision;

    /**
     * @var string
     * Fecha de timbrado del CFDI (cuando fue sellado digitalmente)
     */
    public $fecha_timbrado;

    /**
     * @var string nombre del archivo xml.
     */
    public $filename;

    /**
     * @var string
     * Folio del CFDI
     */
    public $folio;

    /**
     * @var string
     * Forma de pago del CFDI
     */
    public $forma_pago;

    /**
     * @var string
     * Método de pago del CFDI
     */
    public $metodo_pago;

    /**
     * @var string
     * RFC del receptor del CFDI
     */
    public $receptor_rfc;

    /**
     * @var string
     * Nombre del receptor del CFDI
     */
    public $receptor_nombre;

    /**
     * @var string
     * Serie del CFDI
     */
    public $serie;

    /**
     * @var string
     * Tipo de comprobante (por ejemplo: I = Ingreso, E = Egreso)
     */
    public $tipo;

    /**
     * @var float|int
     * Monto total del CFDI
     */
    public $total;

    /**
     * @var string
     * UUID del CFDI (folio fiscal asignado por el SAT)
     */
    public $uuid;

    /**
     * @var string
     * Versión del CFDI (por ejemplo: 4.0)
     */
    public $version;

    /**
     * @var string
     * Fecha de creación del registro en el sistema
     */
    public $created_at;

    /**
     * @var string
     * Fecha de actualización del registro en el sistema
     */
    public $updated_at;

    /** @var string Usuario que generó el CFDI */
    public $nick;

    /** @var string Último usuario que modificó el CFDI */
    public $last_nick;

    /**
     * Carga los datos del CFDI asociados a un código de factura (idfactura).
     *
     * @param mixed $code Código de la factura.
     * @return bool True si se cargó correctamente, false en caso contrario.
     */
    public function loadFromInvoice($code): bool
    {
        $where = [new DataBaseWhere('idfactura', $code)];
        return $this->loadFromCode('', $where);
    }

    /**
     * Carga los datos del CFDI asociados a un UUID.
     *
     * @param string $uuid UUID del CFDI.
     * @return bool True si se cargó correctamente, false en caso contrario.
     */
    public function loadFromUuid($uuid): bool
    {
        $where = [new DataBaseWhere('uuid', $uuid)];
        return $this->loadFromCode('', $where);
    }

    /**
     * Retorna el nombre de la columna primaria del modelo.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Actualiza la fecha de envío por correo electrónico.
     */
    public function updateMailDate(): void
    {
        $this->mail_at = date(self::DATETIME_STYLE);
    }
}
