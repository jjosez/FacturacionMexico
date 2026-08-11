<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Supplier\Import\Options;

class ImportOptions
{
    public const PRODUCT_ACTION_SKIP = ProductImportOptions::PRODUCT_ACTION_SKIP;
    public const PRODUCT_ACTION_AUTO = ProductImportOptions::PRODUCT_ACTION_AUTO;
    public const PRODUCT_ACTION_CREATE = ProductImportOptions::PRODUCT_ACTION_CREATE;

    public const TAX_MODE_PRESERVE = TaxImportOptions::TAX_MODE_PRESERVE;
    public const TAX_MODE_COMPANY = TaxImportOptions::TAX_MODE_COMPANY;

    public ProductImportOptions $product;
    public InvoiceImportOptions $invoice;
    public TaxImportOptions $tax;

    public function __construct(array $options = [])
    {
        $this->product = $this->buildProductOptions($options);
        $this->invoice = $this->buildInvoiceOptions($options);
        $this->tax = $this->buildTaxOptions($options);
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return [
            'product' => $this->product->toArray(),
            'invoice' => $this->invoice->toArray(),
            'tax' => $this->tax->toArray(),
        ];
    }

    public function shouldAutoMatch(): bool
    {
        return $this->product->shouldAutoMatch();
    }

    public function shouldCreateProducts(): bool
    {
        return $this->product->shouldCreateProducts();
    }

    public function shouldUpdatePrices(): bool
    {
        return $this->product->shouldUpdatePrices();
    }

    public function shouldPreserveTax(): bool
    {
        return $this->tax->shouldPreserveTax();
    }

    private function buildProductOptions(array $options): ProductImportOptions
    {
        $data = $options['product'] ?? $options['product_options'] ?? $options;
        if ($data instanceof ProductImportOptions) {
            return $data;
        }

        return ProductImportOptions::fromArray(is_array($data) ? $data : []);
    }

    private function buildInvoiceOptions(array $options): InvoiceImportOptions
    {
        $data = $options['invoice'] ?? $options['invoice_options'] ?? $options;
        if ($data instanceof InvoiceImportOptions) {
            return $data;
        }

        return InvoiceImportOptions::fromArray(is_array($data) ? $data : []);
    }

    private function buildTaxOptions(array $options): TaxImportOptions
    {
        $data = $options['tax'] ?? $options['tax_options'] ?? $options;
        if ($data instanceof TaxImportOptions) {
            return $data;
        }

        return TaxImportOptions::fromArray(is_array($data) ? $data : []);
    }
}
