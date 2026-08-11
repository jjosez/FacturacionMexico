<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options;

final class ProductImportOptions
{
    public const PRODUCT_ACTION_SKIP = 'skip';
    public const PRODUCT_ACTION_AUTO = 'auto';
    public const PRODUCT_ACTION_CREATE = 'create';

    public function __construct(
        public string $productAction = self::PRODUCT_ACTION_AUTO,
        public bool $updateSupplierPrices = false,
        public bool $autoMatchProducts = true,
        public float $priceMultiplier = 1.0
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productAction: (string)($data['product_action'] ?? $data['productAction'] ?? self::PRODUCT_ACTION_AUTO),
            updateSupplierPrices: self::boolValue(
                $data['update_supplier_prices'] ?? $data['updateSupplierPrices'] ?? false,
                false
            ),
            autoMatchProducts: self::boolValue(
                $data['auto_match_products'] ?? $data['autoMatchProducts'] ?? true,
                true
            ),
            priceMultiplier: isset($data['price_multiplier'])
                ? (float)$data['price_multiplier']
                : (float)($data['priceMultiplier'] ?? 1.0)
        );
    }

    public function toArray(): array
    {
        return [
            'product_action' => $this->productAction,
            'update_supplier_prices' => $this->updateSupplierPrices,
            'auto_match_products' => $this->autoMatchProducts,
            'price_multiplier' => $this->priceMultiplier,
        ];
    }

    public function shouldAutoMatch(): bool
    {
        return $this->autoMatchProducts;
    }

    public function shouldCreateProducts(): bool
    {
        return $this->productAction === self::PRODUCT_ACTION_CREATE;
    }

    public function shouldUpdatePrices(): bool
    {
        return $this->updateSupplierPrices;
    }

    private static function boolValue(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
