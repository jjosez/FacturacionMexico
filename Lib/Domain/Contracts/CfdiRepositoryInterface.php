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

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Contracts;

use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Enums\CfdiStatus;

interface CfdiRepositoryInterface
{
    /**
     * Guarda un nuevo CFDI en base de datos, a partir de una factura y contenido XML.
     *
     * @param FacturaCliente $invoice La factura asociada al CFDI.
     * @param string $xmlContent El contenido XML del CFDI.
     * @return CfdiCliente|null El CFDI guardado o null si falló.
     */
    public function save(FacturaCliente $invoice, string $xmlContent): ?CfdiCliente;

    /**
     * Guarda el contenido XML de un CFDI en el medio de almacenamiento (por ejemplo, en un archivo).
     *
     * @param CfdiCliente $cfdi El CFDI al que corresponde el XML.
     * @param string $xmlContent El contenido XML a guardar.
     * @return bool True si se guardó correctamente, false en caso contrario.
     */
    public function saveXml(CfdiCliente $cfdi, string $xmlContent): bool;

    /**
     * Actualiza la fecha de envío por correo electrónico del CFDI.
     *
     * @param CfdiCliente $cfdi El CFDI que se está actualizando.
     * @return bool True si se guardó correctamente, false si falló.
     */
    public function updateMailDate(CfdiCliente $cfdi): bool;

    /**
     * Actualiza el estado del CFDI en base de datos.
     *
     * @param CfdiCliente $cfdi El CFDI a actualizar.
     * @param string $status El nuevo estado (por ejemplo, Timbrado, Cancelado).
     * @return bool True si se actualizó correctamente, false si falló.
     */
    public function updateStatus(CfdiCliente $cfdi, CfdiStatus $status): bool;

    /**
     * Elimina el CFDI del medio de almacenamiento (por ejemplo, elimina el archivo XML).
     *
     * @param CfdiCliente $cfdi El CFDI cuyo archivo se desea eliminar.
     * @return bool True si se eliminó correctamente, false si falló.
     */
    public function deleteCfdi(CfdiCliente $cfdi): bool;

    /**
     * Obtiene el contenido XML del CFDI desde el almacenamiento (archivo, base de datos, etc.).
     *
     * @param CfdiCliente $cfdi El CFDI del que se desea recuperar el XML.
     * @return string|null El contenido XML si existe, null si no se encuentra.
     */
    public function getXml(CfdiCliente $cfdi): ?string;

    /**
     * Obtiene el contenido XML del CFDI desde el almacenamiento (archivo, base de datos, etc.).
     *
     * @param CfdiCliente $cfdi El CFDI del que se desea recuperar el XML.
     * @return string|null El contenido XML si existe, null si no se encuentra.
     */
    public function cfdiFilePath(CfdiCliente $cfdi): ?string;

    public function findByUuid(string $uuid): ?CfdiCliente;
}
