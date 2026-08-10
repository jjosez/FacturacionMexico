<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice;

class SupplierInvoiceImportOptions
{
    public const PRODUCT_ACTION_SKIP = 'skip';
    public const PRODUCT_ACTION_AUTO = 'auto';
    public const PRODUCT_ACTION_CREATE = 'create';

    public const TAX_MODE_PRESERVE = 'preserve';
    public const TAX_MODE_COMPANY = 'company';

    public string $productAction = self::PRODUCT_ACTION_AUTO;
    public bool $updateSupplierPrices = false;
    public bool $createMissingProducts = false;
    public bool $autoMatchProducts = true;
    public float $priceMultiplier = 1.0;
    public string $taxMode = self::TAX_MODE_PRESERVE;
    public ?int $codalmacen = null;
    public ?string $codserie = null;
    public bool $linkOnlyExisting = true;

    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public static function fromArray(array $data): self
    {
        return new self([
            'productAction' => $data['product_action'] ?? $data['productAction'] ?? self::PRODUCT_ACTION_AUTO,
            'updateSupplierPrices' => $data['update_supplier_prices'] ?? $data['updateSupplierPrices'] ?? false,
            'createMissingProducts' => $data['create_missing_products'] ?? $data['createMissingProducts'] ?? false,
            'autoMatchProducts' => $data['auto_match_products'] ?? $data['autoMatchProducts'] ?? true,
            'priceMultiplier' => isset($data['price_multiplier']) ? (float)$data['price_multiplier'] : (isset($data['priceMultiplier']) ? (float)$data['priceMultiplier'] : 1.0),
            'taxMode' => $data['tax_mode'] ?? $data['taxMode'] ?? self::TAX_MODE_PRESERVE,
            'codalmacen' => isset($data['codalmacen']) ? (int)$data['codalmacen'] : null,
            'codserie' => isset($data['codserie']) ? (string)$data['codserie'] : null,
        ]);
    }

    public function toArray(): array
    {
        return [
            'productAction' => $this->productAction,
            'updateSupplierPrices' => $this->updateSupplierPrices,
            'createMissingProducts' => $this->createMissingProducts,
            'autoMatchProducts' => $this->autoMatchProducts,
            'priceMultiplier' => $this->priceMultiplier,
            'taxMode' => $this->taxMode,
            'codalmacen' => $this->codalmacen,
            'codserie' => $this->codserie,
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

    public function shouldPreserveTax(): bool
    {
        return $this->taxMode === self::TAX_MODE_PRESERVE;
    }
}
