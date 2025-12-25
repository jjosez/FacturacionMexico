<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Middleware;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiSettings;

class RelationValidator
{
    private static function isSerieEgreso(FacturaCliente $invoice): bool
    {
        return $invoice->codserie === CfdiSettings::serieEgreso();
    }

    public static function validate(FacturaCliente $factura, array $relations): bool
    {
        $isNotaCreditoRelation = false;

        foreach ($relations as $group) {
            $tipoRelacion = $group['tiporelacion'] ?? '';
            $relacionados = $group['relacionados'] ?? [];

            if (empty($relacionados)) {
                Tools::log()->warning("No hay UUIDs en la relación tipo $tipoRelacion.");
                continue;
            }

            if ('01' === $tipoRelacion) {
                $isNotaCreditoRelation = true;
            }

            foreach ($relacionados as $uuid) {
                $parentCfdi = new CfdiCliente();

                if (!$parentCfdi->loadFromUuid($uuid)) {
                    Tools::log()->warning("CFDI relacionado no encontrado: $uuid");
                    continue;
                }

                if ($factura->codcliente !== $parentCfdi->codcliente) {
                    Tools::log()->warning("CFDI relacionado no coincide receptor: $uuid");
                    continue;
                }

                if (self::isSerieEgreso($factura) && $isNotaCreditoRelation) {
                    if (empty($factura->codigorect) || empty($factura->idfacturarect)) {
                        $parentInvoice = new FacturaCliente();

                        if ($parentInvoice->loadFromCode($parentCfdi->idfactura)) {
                            $factura->codigorect = $parentInvoice->codigo;
                            $factura->idfacturarect = $parentInvoice->idfactura;
                            $factura->save();
                        }
                    }
                }
            }
        }

        // Si es egreso debe tener tipo 01
        if (self::isSerieEgreso($factura)) {
            if (empty($relations)) {
                Tools::log()->warning('Un CFDI de egreso debe relacionarse al menos con un CFDI.');
                return false;
            }

            if (!$isNotaCreditoRelation) {
                Tools::log()->warning('Un CFDI de egreso debe tener al menos un tipo de relación 01 (nota de crédito).');
                return false;
            }
        }

        return true;
    }
}
