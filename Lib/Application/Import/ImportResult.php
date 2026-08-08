<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Import;

use FacturaScripts\Dinamic\Model\FacturaProveedor;

class ImportResult
{
    public bool $success;
    public ?FacturaProveedor $invoice = null;
    public ?string $error = null;
    public array $warnings = [];
    public array $appliedMappings = [];
    public array $createdProducts = [];
    public array $linkedProducts = [];
    public int $conceptsProcessed = 0;
    public int $conceptsLinked = 0;
    public int $conceptsUnmatched = 0;

    public static function success(
        FacturaProveedor $invoice,
        int $conceptsProcessed = 0,
        int $conceptsLinked = 0,
        array $createdProducts = [],
        array $linkedProducts = []
    ): self {
        $result = new self();
        $result->success = true;
        $result->invoice = $invoice;
        $result->conceptsProcessed = $conceptsProcessed;
        $result->conceptsLinked = $conceptsLinked;
        $result->createdProducts = $createdProducts;
        $result->linkedProducts = $linkedProducts;

        return $result;
    }

    public static function failure(string $error, array $warnings = []): self
    {
        $result = new self();
        $result->success = false;
        $result->error = $error;
        $result->warnings = $warnings;

        return $result;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function addMapping(string $cfdiReferencia, string $productoReferencia, string $method): void
    {
        $this->appliedMappings[] = [
            'cfdi_referencia' => $cfdiReferencia,
            'producto_referencia' => $productoReferencia,
            'method' => $method,
        ];
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'invoice_id' => $this->invoice?->idfactura,
            'invoice_url' => $this->invoice?->url(),
            'error' => $this->error,
            'warnings' => $this->warnings,
            'applied_mappings' => $this->appliedMappings,
            'created_products' => $this->createdProducts,
            'linked_products' => $this->linkedProducts,
            'concepts_processed' => $this->conceptsProcessed,
            'concepts_linked' => $this->conceptsLinked,
            'concepts_unmatched' => $this->conceptsUnmatched,
        ];
    }
}
