<?php
/**
 * This file is part of FacturacionMexico plugin for FacturaScripts
 * Copyright (C) 2024 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
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
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Servicio para gestionar la vinculación de productos con proveedores
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class CfdiSupplierProductImporter
{
    /**
     * Vincula un producto con un proveedor
     *
     * @param string $referencia Referencia del producto
     * @param string $codproveedor Código del proveedor
     * @param string $refproveedor Referencia del proveedor para este producto
     * @param float $precio Precio de compra
     * @param float $stock Stock disponible (opcional)
     * @param float $dtopor Descuento porcentual (opcional)
     * @param float $dtopor2 Segundo descuento porcentual (opcional)
     * @return array ['ok' => bool, 'message' => string, 'data' => ProductoProveedor|null]
     */
    public function vincular(
        string $referencia,
        string $codproveedor,
        string $refproveedor,
        float $precio,
        float $stock = 0.0,
        float $dtopor = 0.0,
        float $dtopor2 = 0.0
    ): array {
        // Verificar que el producto existe
        $variante = new Variante();
        if (!$variante->loadWhereEq('referencia', $referencia)) {
            return [
                'ok' => false,
                'message' => 'Producto no encontrado: ' . $referencia,
                'data' => null
            ];
        }

        // Verificar que la referencia del proveedor no esté vacía
        if (empty($refproveedor)) {
            $refproveedor = $referencia;
        }

        // Buscar si ya existe la vinculación
        $productoProveedor = $this->buscarVinculacion($referencia, $codproveedor);

        if ($productoProveedor) {
            // Actualizar vinculación existente
            $result = $this->actualizarVinculacion($productoProveedor, $refproveedor, $precio, $stock, $dtopor, $dtopor2);
        } else {
            // Crear nueva vinculación
            $result = $this->crearVinculacion($referencia, $codproveedor, $refproveedor, $precio, $stock, $dtopor, $dtopor2, $variante->idproducto);
        }

        return $result;
    }

    /**
     * Busca una vinculación existente entre producto y proveedor
     *
     * @param string $referencia
     * @param string $codproveedor
     * @return ProductoProveedor|null
     */
    protected function buscarVinculacion(string $referencia, string $codproveedor): ?ProductoProveedor
    {
        $productoProveedor = new ProductoProveedor();
        $where = [
            Where::eq('referencia', $referencia),
            Where::eq('codproveedor', $codproveedor),
        ];

        if ($productoProveedor->loadWhere($where)) {
            return $productoProveedor;
        }

        return null;
    }

    /**
     * Actualiza una vinculación existente
     *
     * @param ProductoProveedor $productoProveedor
     * @param string $refproveedor
     * @param float $precio
     * @param float $stock
     * @param float $dtopor
     * @param float $dtopor2
     * @return array
     */
    protected function actualizarVinculacion(
        ProductoProveedor $productoProveedor,
        string $refproveedor,
        float $precio,
        float $stock,
        float $dtopor,
        float $dtopor2
    ): array {
        $productoProveedor->refproveedor = $refproveedor;
        $productoProveedor->precio = $precio;
        $productoProveedor->stock = $stock;
        $productoProveedor->dtopor = $dtopor;
        $productoProveedor->dtopor2 = $dtopor2;
        $productoProveedor->actualizado = Tools::dateTime();

        if ($productoProveedor->save()) {
            return [
                'ok' => true,
                'message' => 'Vinculación actualizada correctamente',
                'data' => $productoProveedor
            ];
        }

        return [
            'ok' => false,
            'message' => 'Error al actualizar la vinculación',
            'data' => null
        ];
    }

    /**
     * Crea una nueva vinculación
     *
     * @param string $referencia
     * @param string $codproveedor
     * @param string $refproveedor
     * @param float $precio
     * @param float $stock
     * @param float $dtopor
     * @param float $dtopor2
     * @param int $idproducto
     * @return array
     */
    protected function crearVinculacion(
        string $referencia,
        string $codproveedor,
        string $refproveedor,
        float $precio,
        float $stock,
        float $dtopor,
        float $dtopor2,
        int $idproducto
    ): array {
        $productoProveedor = new ProductoProveedor();
        $productoProveedor->referencia = $referencia;
        $productoProveedor->codproveedor = $codproveedor;
        $productoProveedor->refproveedor = $refproveedor;
        $productoProveedor->precio = $precio;
        $productoProveedor->stock = $stock;
        $productoProveedor->dtopor = $dtopor;
        $productoProveedor->dtopor2 = $dtopor2;
        $productoProveedor->idproducto = $idproducto;

        if ($productoProveedor->save()) {
            return [
                'ok' => true,
                'message' => 'Producto vinculado correctamente',
                'data' => $productoProveedor
            ];
        }

        return [
            'ok' => false,
            'message' => 'Error al crear la vinculación',
            'data' => null
        ];
    }

    /**
     * Obtiene todas las vinculaciones de un proveedor
     *
     * @param string $codproveedor
     * @return ProductoProveedor[]
     */
    public function obtenerVinculacionesProveedor(string $codproveedor): array
    {
        return ProductoProveedor::all(
            [ Where::eq('codproveedor', $codproveedor)]
        );
    }

    /**
     * Obtiene todas las vinculaciones de un producto
     *
     * @param string $referencia
     * @return ProductoProveedor[]
     */
    public function obtenerVinculacionesProducto(string $referencia): array
    {
        return ProductoProveedor::all(
            [Where::eq('referencia', $referencia)]
        );
    }

    /**
     * Elimina una vinculación
     *
     * @param string $referencia
     * @param string $codproveedor
     * @return array
     */
    public function eliminarVinculacion(string $referencia, string $codproveedor): array
    {
        $productoProveedor = $this->buscarVinculacion($referencia, $codproveedor);

        if (!$productoProveedor) {
            return [
                'ok' => false,
                'message' => 'Vinculación no encontrada',
                'data' => null
            ];
        }

        if ($productoProveedor->delete()) {
            return [
                'ok' => true,
                'message' => 'Vinculación eliminada correctamente',
                'data' => null
            ];
        }

        return [
            'ok' => false,
            'message' => 'Error al eliminar la vinculación',
            'data' => null
        ];
    }

    /**
     * Verifica si existe una vinculación
     *
     * @param string $referencia
     * @param string $codproveedor
     * @return bool
     */
    public function existeVinculacion(string $referencia, string $codproveedor): bool
    {
        return $this->buscarVinculacion($referencia, $codproveedor) !== null;
    }
}
