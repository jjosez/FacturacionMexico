<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use Exception;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader;
use FacturaScripts\Plugins\FacturacionMexico\Model\RelacionCfdiProveedor;

final class SupplierCfdiRelationRepository
{
    public function saveRelations(CfdiProveedor $cfdi, CfdiQuickReader $reader): void
    {
        foreach ($reader->relacionados() as $group) {
            $tipoRelacion = $group['tiporelacion'] ?? '';
            foreach ($group['relacionados'] ?? [] as $uuidRelacionado) {
                $related = new CfdiProveedor();
                if (!$related->loadFromUuid($uuidRelacionado)) {
                    continue;
                }

                $relation = new RelacionCfdiProveedor();
                $exists = $relation->loadWhere([
                    Where::eq('cfdi_id', $cfdi->id),
                    Where::eq('cfdi_id_relacionado', $related->id),
                    Where::eq('tipo_relacion', $tipoRelacion),
                ]);

                if ($exists) {
                    continue;
                }

                $relation->cfdi_id = $cfdi->id;
                $relation->cfdi_id_relacionado = $related->id;
                $relation->tipo_relacion = $tipoRelacion;
                $relation->uuid = $cfdi->uuid;
                $relation->uuid_relacionado = $uuidRelacionado;

                if (!$relation->save()) {
                    throw new Exception('No se pudo guardar la relación del CFDI de egreso');
                }
            }
        }
    }
}
