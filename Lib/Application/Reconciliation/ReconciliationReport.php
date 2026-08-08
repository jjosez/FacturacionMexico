<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Reconciliation;

use DateTime;

class ReconciliationReport
{
    public ?DateTime $fromDate = null;
    public ?DateTime $toDate = null;
    public int $companyId = 0;
    public string $companyName = '';
    public array $items = [];

    public int $totalCfdis = 0;
    public int $matchedCount = 0;
    public int $partialCount = 0;
    public int $unmatchedCount = 0;

    public float $totalCfdisAmount = 0.0;
    public float $totalMatchedAmount = 0.0;
    public float $totalDifference = 0.0;

    public float $elapsedSeconds = 0.0;

    public function __construct()
    {
        $this->items = [];
    }

    public function addItem(ReconciliationItem $item): void
    {
        $this->items[] = $item;
        $this->recalculateStats();
    }

    public function recalculateStats(): void
    {
        $this->totalCfdis = count($this->items);
        $this->matchedCount = 0;
        $this->partialCount = 0;
        $this->unmatchedCount = 0;
        $this->totalCfdisAmount = 0.0;
        $this->totalMatchedAmount = 0.0;
        $this->totalDifference = 0.0;

        foreach ($this->items as $item) {
            $this->totalCfdisAmount += $item->total;

            switch ($item->estado) {
                case ReconciliationItem::STATUS_MATCHED:
                    $this->matchedCount++;
                    $this->totalMatchedAmount += $item->invoiceTotal ?? $item->total;
                    break;
                case ReconciliationItem::STATUS_PARTIAL:
                    $this->partialCount++;
                    break;
                case ReconciliationItem::STATUS_UNMATCHED:
                    $this->unmatchedCount++;
                    break;
            }

            if ($item->difference !== null) {
                $this->totalDifference += abs($item->difference);
            }
        }
    }

    public function getMatchRate(): float
    {
        if ($this->totalCfdis === 0) {
            return 0.0;
        }
        return ($this->matchedCount / $this->totalCfdis) * 100;
    }

    public function getPartialRate(): float
    {
        if ($this->totalCfdis === 0) {
            return 0.0;
        }
        return ($this->partialCount / $this->totalCfdis) * 100;
    }

    public function getUnmatchedRate(): float
    {
        if ($this->totalCfdis === 0) {
            return 0.0;
        }
        return ($this->unmatchedCount / $this->totalCfdis) * 100;
    }

    public function getGroupedBySupplier(): array
    {
        $grouped = [];

        foreach ($this->items as $item) {
            $key = $item->proveedorCod;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'proveedor_cod' => $item->proveedorCod,
                    'proveedor_nombre' => $item->proveedorNombre,
                    'total_cfdis' => 0,
                    'matched' => 0,
                    'partial' => 0,
                    'unmatched' => 0,
                    'total_amount' => 0.0,
                    'items' => [],
                ];
            }

            $grouped[$key]['items'][] = $item;
            $grouped[$key]['total_cfdis']++;
            $grouped[$key]['total_amount'] += $item->total;

            switch ($item->estado) {
                case ReconciliationItem::STATUS_MATCHED:
                    $grouped[$key]['matched']++;
                    break;
                case ReconciliationItem::STATUS_PARTIAL:
                    $grouped[$key]['partial']++;
                    break;
                case ReconciliationItem::STATUS_UNMATCHED:
                    $grouped[$key]['unmatched']++;
                    break;
            }
        }

        return array_values($grouped);
    }

    public function getGroupedByMonth(): array
    {
        $grouped = [];

        foreach ($this->items as $item) {
            $month = date('Y-m', strtotime($item->fecha));

            if (!isset($grouped[$month])) {
                $grouped[$month] = [
                    'month' => $month,
                    'total_cfdis' => 0,
                    'total_amount' => 0.0,
                    'items' => [],
                ];
            }

            $grouped[$month]['items'][] = $item;
            $grouped[$month]['total_cfdis']++;
            $grouped[$month]['total_amount'] += $item->total;
        }

        ksort($grouped);

        return array_values($grouped);
    }

    public function getUnmatchedItems(): array
    {
        return array_filter($this->items, fn($item) => $item->isUnmatched());
    }

    public function getPartialItems(): array
    {
        return array_filter($this->items, fn($item) => $item->isPartial());
    }

    public function getMatchedItems(): array
    {
        return array_filter($this->items, fn($item) => $item->isMatched());
    }

    public function getTopDifferences(int $limit = 10): array
    {
        $sorted = $this->items;
        usort($sorted, fn($a, $b) => $b->getDifferenceAbs() <=> $a->getDifferenceAbs());
        return array_slice($sorted, 0, $limit);
    }

    public function toArray(): array
    {
        return [
            'from_date' => $this->fromDate?->format('Y-m-d'),
            'to_date' => $this->toDate?->format('Y-m-d'),
            'company_id' => $this->companyId,
            'company_name' => $this->companyName,
            'stats' => [
                'total_cfdis' => $this->totalCfdis,
                'matched_count' => $this->matchedCount,
                'partial_count' => $this->partialCount,
                'unmatched_count' => $this->unmatchedCount,
                'match_rate' => $this->getMatchRate(),
                'partial_rate' => $this->getPartialRate(),
                'unmatched_rate' => $this->getUnmatchedRate(),
                'total_cfdis_amount' => $this->totalCfdisAmount,
                'total_matched_amount' => $this->totalMatchedAmount,
                'total_difference' => $this->totalDifference,
            ],
            'elapsed_seconds' => $this->elapsedSeconds,
            'items' => array_map(fn($item) => $item->toArray(), $this->items),
            'grouped_by_supplier' => $this->getGroupedBySupplier(),
            'grouped_by_month' => $this->getGroupedByMonth(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $report = new self();

        if (isset($data['from_date'])) {
            $report->fromDate = new DateTime($data['from_date']);
        }
        if (isset($data['to_date'])) {
            $report->toDate = new DateTime($data['to_date']);
        }
        if (isset($data['company_id'])) {
            $report->companyId = (int)$data['company_id'];
        }
        if (isset($data['company_name'])) {
            $report->companyName = $data['company_name'];
        }

        return $report;
    }
}
