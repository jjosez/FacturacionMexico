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

use FacturaScripts\Core\Session;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Model\Base\CfdiTrait;

class CfdiProveedor extends ModelClass
{
    public const SUPPLIER_CFDI_BASEPATH = FS_FOLDER . '/MyFiles/CFDI/supplier/';

    use ModelTrait;
    use CfdiTrait;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $codproveedor;

    public function clear(): void
    {
        parent::clear();

        $this->created_at = Tools::dateTime();
        $this->nick = Session::user()->nick;
    }

    public function getSupplier(): Proveedor
    {
        $proveedor = new Proveedor();
        $proveedor->loadFromCode($this->codproveedor);

        return $proveedor;
    }

    public function invoiceNumber(): string
    {
        return $this->serie . $this->folio;
    }

    public function validateFile(): string
    {
        return is_file(self::SUPPLIER_CFDI_BASEPATH . $this->filename);
    }

    public function localFileContent(): string
    {
        if ($this->validateFile()) {
            return file_get_contents(self::SUPPLIER_CFDI_BASEPATH . $this->filename);
        }

        return '';
    }

    public static function tableName(): string
    {
        return 'cfdis_proveedores';
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if ($type === 'wizard') {
            $value = $this->id();

            return 'CfdiSupplierWizard?type=' . rawurlencode($this->tipo) . '&code=' . rawurlencode($value);
        }

        return parent::url($type, $list);
    }

    public function test(): bool
    {
        $this->last_nick = Session::user()->nick;
        $this->updated_at = Tools::dateTime();

        return parent::test();
    }
}
