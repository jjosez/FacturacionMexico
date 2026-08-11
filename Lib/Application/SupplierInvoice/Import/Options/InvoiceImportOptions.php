<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options;

final class InvoiceImportOptions
{
    public function __construct(
        public ?string $codpago = null,
        public ?string $codserie = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            codpago: $data['codpago'] ?? $data['codPago'] ?? null,
            codserie: isset($data['codserie']) ? (string)$data['codserie'] : ($data['codSerie'] ?? null)
        );
    }

    public function toArray(): array
    {
        return [
            'codpago' => $this->codpago,
            'codserie' => $this->codserie,
        ];
    }
}
