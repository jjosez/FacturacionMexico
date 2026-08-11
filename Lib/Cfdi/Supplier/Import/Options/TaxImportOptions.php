<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\Options;

final class TaxImportOptions
{
    public const TAX_MODE_PRESERVE = 'preserve';
    public const TAX_MODE_COMPANY = 'company';

    public function __construct(
        public string $taxMode = self::TAX_MODE_PRESERVE
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            taxMode: (string)($data['tax_mode'] ?? $data['taxMode'] ?? self::TAX_MODE_PRESERVE)
        );
    }

    public function toArray(): array
    {
        return [
            'tax_mode' => $this->taxMode,
        ];
    }

    public function shouldPreserveTax(): bool
    {
        return $this->taxMode === self::TAX_MODE_PRESERVE;
    }
}
