<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Enums;

enum CfdiStatus: string
{
    case DRAFT = 'borrador';
    case STAMPED = 'timbrado';
    case CANCELLED = 'cancelado';
    case ERROR = 'error';

    public function canBeCancelled(): bool
    {
        return $this === self::STAMPED;
    }
}
