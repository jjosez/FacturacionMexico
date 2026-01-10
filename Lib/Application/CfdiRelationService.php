<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2019-2025 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
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

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Model\RelacionCfdiCliente;

/**
 * Servicio para gestionar las relaciones entre CFDIs
 */
class CfdiRelationService
{
    /**
     * Guarda las relaciones definitivas entre CFDIs después de un timbrado exitoso
     *
     * @param CfdiCliente $cfdi El CFDI principal (recién timbrado)
     * @param array $relations Array con estructura: [['tiporelacion' => '01', 'relacionados' => ['uuid1', 'uuid2']]]
     * @return bool True si se guardaron correctamente, false en caso contrario
     */
    public function saveCfdiRelations(CfdiCliente $cfdi, array $relations): bool
    {
        if (empty($relations)) {
            return true;
        }

        $allSaved = true;

        foreach ($relations as $group) {
            $tipoRelacion = $group['tiporelacion'] ?? '';
            $relacionados = $group['relacionados'] ?? [];

            if (empty($tipoRelacion) || empty($relacionados)) {
                continue;
            }

            foreach ($relacionados as $uuidRelacionado) {
                // Buscar el CFDI relacionado por UUID
                $cfdiRelacionado = new CfdiCliente();
                if (!$cfdiRelacionado->loadFromUuid($uuidRelacionado)) {
                    Tools::log()->warning("No se encontró el CFDI relacionado con UUID: $uuidRelacionado");
                    continue;
                }

                // Crear la relación
                $relacion = new RelacionCfdiCliente();
                $relacion->cfdi_id = $cfdi->id;
                $relacion->cfdi_id_relacionado = $cfdiRelacionado->id;
                $relacion->tipo_relacion = $tipoRelacion;
                $relacion->uuid = $cfdi->uuid;
                $relacion->uuid_relacionado = $uuidRelacionado;

                if (!$relacion->save()) {
                    Tools::log()->error("Error al guardar la relación CFDI: {$cfdi->uuid} -> {$uuidRelacionado}");
                    $allSaved = false;
                }
            }
        }

        return $allSaved;
    }

    /**
     * Elimina las relaciones de un CFDI específico
     * Útil para limpiar si el timbrado falla
     *
     * @param int $cfdiId El ID del CFDI cuyas relaciones se deben eliminar
     * @return bool True si se eliminaron correctamente
     */
    public function deleteCfdiRelations(int $cfdiId): bool
    {
        $relation = new RelacionCfdiCliente();
        $relations = $relation->all([['cfdi_id', '=', $cfdiId]]);

        foreach ($relations as $rel) {
            if (!$rel->delete()) {
                Tools::log()->warning("No se pudo eliminar la relación con ID: {$rel->id}");
                return false;
            }
        }

        return true;
    }

    /**
     * Valida que todos los UUIDs relacionados existen en la base de datos
     * y pertenecen al mismo cliente
     *
     * @param string $codcliente El código del cliente
     * @param array $relations Las relaciones a validar
     * @return bool True si todas las relaciones son válidas
     */
    public function validateRelations(string $codcliente, array $relations): bool
    {
        if (empty($relations)) {
            return true;
        }

        foreach ($relations as $group) {
            $relacionados = $group['relacionados'] ?? [];

            foreach ($relacionados as $uuid) {
                $cfdi = new CfdiCliente();
                if (!$cfdi->loadFromUuid($uuid)) {
                    Tools::log()->warning("CFDI relacionado no encontrado: $uuid");
                    return false;
                }

                if ($cfdi->codcliente !== $codcliente) {
                    Tools::log()->warning("CFDI relacionado no pertenece al mismo cliente: $uuid");
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Valida que las relaciones de un CFDI estén guardadas en la base de datos
     * y las sincroniza si faltan
     *
     * @param CfdiCliente $cfdi El CFDI principal
     * @param array $relations Array con estructura: [['tiporelacion' => '01', 'relacionados' => ['uuid1', 'uuid2']]]
     * @return array Reporte con estado de la validación/sincronización
     */
    public function validateAndSyncRelations(CfdiCliente $cfdi, array $relations): array
    {
        $result = [
            'success' => true,
            'missing' => [],
            'synced' => [],
            'errors' => []
        ];

        if (empty($relations)) {
            return $result;
        }

        foreach ($relations as $group) {
            $tipoRelacion = $group['tiporelacion'] ?? '';
            $relacionados = $group['relacionados'] ?? [];

            if (empty($tipoRelacion) || empty($relacionados)) {
                continue;
            }

            foreach ($relacionados as $uuidRelacionado) {
                // Buscar el CFDI relacionado por UUID
                $cfdiRelacionado = new CfdiCliente();
                if (!$cfdiRelacionado->loadFromUuid($uuidRelacionado)) {
                    $result['success'] = false;
                    $result['errors'][] = "CFDI relacionado no encontrado: $uuidRelacionado";
                    continue;
                }

                // Verificar si la relación ya existe
                if (!$this->relationExists($cfdi->id, $cfdiRelacionado->id, $tipoRelacion)) {
                    $result['missing'][] = [
                        'tipo' => $tipoRelacion,
                        'uuid' => $uuidRelacionado
                    ];

                    // Intentar guardar la relación faltante
                    $relacion = new RelacionCfdiCliente();
                    $relacion->cfdi_id = $cfdi->id;
                    $relacion->cfdi_id_relacionado = $cfdiRelacionado->id;
                    $relacion->tipo_relacion = $tipoRelacion;
                    $relacion->uuid = $cfdi->uuid;
                    $relacion->uuid_relacionado = $uuidRelacionado;

                    if ($relacion->save()) {
                        $result['synced'][] = [
                            'tipo' => $tipoRelacion,
                            'uuid' => $uuidRelacionado
                        ];
                        Tools::log()->info("Relación sincronizada: {$cfdi->uuid} -> {$uuidRelacionado}");
                    } else {
                        $result['success'] = false;
                        $result['errors'][] = "Error al sincronizar relación: {$cfdi->uuid} -> {$uuidRelacionado}";
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Verifica si una relación específica ya existe en la base de datos
     *
     * @param int $cfdiId ID del CFDI principal
     * @param int $cfdiIdRelacionado ID del CFDI relacionado
     * @param string $tipoRelacion Tipo de relación SAT
     * @return bool True si la relación existe
     */
    private function relationExists(int $cfdiId, int $cfdiIdRelacionado, string $tipoRelacion): bool
    {
        $relacion = new RelacionCfdiCliente();
        $where = [
            Where::eq('cfdi_id', $cfdiId),
            Where::eq('cfdi_id_relacionado', $cfdiIdRelacionado),
            Where::eq('tipo_relacion', $tipoRelacion)
        ];

        return $relacion->loadWhere($where);
    }
}
