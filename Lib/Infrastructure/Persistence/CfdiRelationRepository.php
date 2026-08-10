<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\CfdiCliente;
use FacturaScripts\Plugins\FacturacionMexico\Model\RelacionCfdiCliente;

final class CfdiRelationRepository
{
    public function findCfdiByUuid(string $uuid): ?CfdiCliente
    {
        $cfdi = new CfdiCliente();
        return $cfdi->loadFromUuid($uuid) ? $cfdi : null;
    }

    public function create(): RelacionCfdiCliente
    {
        return new RelacionCfdiCliente();
    }

    /** @return RelacionCfdiCliente[] */
    public function findByCfdiId(int $cfdiId): array
    {
        return (new RelacionCfdiCliente())->all([Where::eq('cfdi_id', $cfdiId)]);
    }

    public function exists(int $cfdiId, int $relatedCfdiId, string $relationType): bool
    {
        $relation = new RelacionCfdiCliente();
        return $relation->loadWhere([
            Where::eq('cfdi_id', $cfdiId),
            Where::eq('cfdi_id_relacionado', $relatedCfdiId),
            Where::eq('tipo_relacion', $relationType),
        ]);
    }

    public function save(RelacionCfdiCliente $relation): bool
    {
        return $relation->save();
    }

    public function delete(RelacionCfdiCliente $relation): bool
    {
        return $relation->delete();
    }
}
