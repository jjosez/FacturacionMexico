<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\DTO;

final class CfdiParsedData
{
    public function __construct(
        public readonly string $currency,
        public readonly string $issueDate,
        public readonly string $stampedAt,
        public readonly string $folio,
        public readonly string $paymentForm,
        public readonly string $paymentMethod,
        public readonly string $recipientName,
        public readonly string $recipientRfc,
        public readonly string $issuerName,
        public readonly string $issuerRfc,
        public readonly string $series,
        public readonly string $type,
        public readonly string $total,
        public readonly string $uuid,
        public readonly string $version,
        public readonly array $concepts,
        public readonly array $relations
    ) {
    }
}
