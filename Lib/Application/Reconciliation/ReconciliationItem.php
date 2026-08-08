<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Reconciliation;

class ReconciliationItem
{
    public const STATUS_MATCHED = 'matched';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_UNMATCHED = 'unmatched';

    public string $uuid;
    public string $tipo;
    public string $numeroFactura;
    public string $fecha;
    public float $total;
    public string $proveedorNombre;
    public string $proveedorCod;
    public string $estado;
    public ?int $invoiceId = null;
    public ?string $invoiceNumber = null;
    public ?float $invoiceTotal = null;
    public ?float $difference = null;
    public array $conceptosCfdi = [];
    public array $conceptosInvoice = [];

    public function __construct()
    {
        $this->uuid = '';
        $this->tipo = '';
        $this->numeroFactura = '';
        $this->fecha = '';
        $this->total = 0.0;
        $this->proveedorNombre = '';
        $this->proveedorCod = '';
        $this->estado = self::STATUS_UNMATCHED;
    }

    public function isMatched(): bool
    {
        return $this->estado === self::STATUS_MATCHED;
    }

    public function isPartial(): bool
    {
        return $this->estado === self::STATUS_PARTIAL;
    }

    public function isUnmatched(): bool
    {
        return $this->estado === self::STATUS_UNMATCHED;
    }

    public function calculateDifference(): void
    {
        if ($this->invoiceTotal !== null) {
            $this->difference = round($this->total - $this->invoiceTotal, 2);
        }
    }

    public function getDifferenceAbs(): float
    {
        return abs($this->difference ?? 0.0);
    }

    public function getMatchPercentage(): float
    {
        if ($this->total == 0) {
            return 0.0;
        }

        $matched = $this->total - $this->getDifferenceAbs();
        return max(0, ($matched / $this->total) * 100);
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'tipo' => $this->tipo,
            'numero_factura' => $this->numeroFactura,
            'fecha' => $this->fecha,
            'total' => $this->total,
            'proveedor_nombre' => $this->proveedorNombre,
            'proveedor_cod' => $this->proveedorCod,
            'estado' => $this->estado,
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'invoice_total' => $this->invoiceTotal,
            'difference' => $this->difference,
            'difference_abs' => $this->getDifferenceAbs(),
            'match_percentage' => $this->getMatchPercentage(),
            'conceptos_cfdi_count' => count($this->conceptosCfdi),
            'conceptos_invoice_count' => count($this->conceptosInvoice),
        ];
    }
}
