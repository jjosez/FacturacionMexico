<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Reconciliation;

use DateTime;
use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\CfdiProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;

class CfdiReconciliationService
{
    private const TOLERANCE_AMOUNT = 0.01;
    private const TOLERANCE_DATE_DAYS = 1;

    private DataBase $db;

    public function __construct()
    {
        $this->db = new DataBase();
    }

    public function reconcile(
        int $companyId,
        DateTime $fromDate,
        DateTime $toDate,
        ?string $supplierId = null
    ): ReconciliationReport {
        $startTime = microtime(true);

        $report = new ReconciliationReport();
        $report->fromDate = $fromDate;
        $report->toDate = $toDate;
        $report->companyId = $companyId;

        $company = new \FacturaScripts\Dinamic\Model\Empresa();
        if ($company->load($companyId)) {
            $report->companyName = $company->nombre;
        }

        $cfdis = $this->getCfdisForReconciliation($companyId, $fromDate, $toDate, $supplierId);

        foreach ($cfdis as $cfdi) {
            $item = $this->createReconciliationItem($cfdi);
            $this->findMatchingInvoice($item, $companyId);
            $item->calculateDifference();
            $report->addItem($item);
        }

        $report->elapsedSeconds = round(microtime(true) - $startTime, 2);

        return $report;
    }

    private function getCfdisForReconciliation(
        int $companyId,
        DateTime $fromDate,
        DateTime $toDate,
        ?string $supplierId = null
    ): array {
        $where = [
            new DataBaseWhere('idempresa', $companyId),
            new DataBaseWhere('fecha_emision', $fromDate->format('Y-m-d'), '>='),
            new DataBaseWhere('fecha_emision', $toDate->format('Y-m-d'), '<='),
            new DataBaseWhere('estado', 'vigente'),
        ];

        if ($supplierId !== null) {
            $where[] = new DataBaseWhere('codproveedor', $supplierId);
        }

        $cfdi = new CfdiProveedor();
        return $cfdi->all($where, ['fecha_emision' => 'DESC'], 0, 1000);
    }

    private function createReconciliationItem(CfdiProveedor $cfdi): ReconciliationItem
    {
        $item = new ReconciliationItem();
        $item->uuid = $cfdi->uuid;
        $item->tipo = $cfdi->tipo;
        $item->numeroFactura = $cfdi->invoiceNumber();
        $item->fecha = $cfdi->fecha_emision;
        $item->total = (float)$cfdi->total;
        $item->proveedorCod = $cfdi->codproveedor;

        $supplier = $cfdi->getSupplier();
        if ($supplier !== null) {
            $item->proveedorNombre = $supplier->nombre;
        }

        $reader = $this->getCfdiReader($cfdi);
        if ($reader !== null) {
            $item->conceptosCfdi = $reader->getConceptos();
        }

        return $item;
    }

    private function findMatchingInvoice(ReconciliationItem $item, int $companyId): void
    {
        $invoice = $this->findInvoiceByNumber($item->numeroFactura, $item->proveedorCod);

        if ($invoice === null) {
            $invoice = $this->findInvoiceByAmount($item->total, $item->fecha, $item->proveedorCod);
        }

        if ($invoice === null && !empty($item->uuid)) {
            $invoice = $this->findInvoiceByUuid($item->uuid);
        }

        if ($invoice === null) {
            $item->estado = ReconciliationItem::STATUS_UNMATCHED;
            return;
        }

        $item->invoiceId = $invoice->idfactura;
        $item->invoiceNumber = $invoice->numproveedor;
        $item->invoiceTotal = (float)$invoice->total;

        $this->compareItems($item, $invoice);
    }

    private function findInvoiceByNumber(string $numproveedor, string $codproveedor): ?FacturaProveedor
    {
        if (empty($numproveedor)) {
            return null;
        }

        $invoice = new FacturaProveedor();
        $where = [
            new DataBaseWhere('numproveedor', $numproveedor),
            new DataBaseWhere('codproveedor', $codproveedor),
        ];

        if ($invoice->loadWhere($where)) {
            return $invoice;
        }

        return null;
    }

    private function findInvoiceByAmount(float $total, string $fecha, string $codproveedor): ?FacturaProveedor
    {
        $fromDate = date('Y-m-d', strtotime($fecha) - (self::TOLERANCE_DATE_DAYS * 86400));
        $toDate = date('Y-m-d', strtotime($fecha) + (self::TOLERANCE_DATE_DAYS * 86400));

        $sql = "SELECT * FROM facturasprov
                WHERE codproveedor = ?
                AND ABS(total - ?) <= ?
                AND fecha >= ? AND fecha <= ?
                ORDER BY ABS(total - ?) ASC
                LIMIT 1";

        $result = $this->db->select($sql, [
            $codproveedor,
            $total,
            self::TOLERANCE_AMOUNT,
            $fromDate,
            $toDate,
            $total
        ]);

        if (empty($result)) {
            return null;
        }

        $invoice = new FacturaProveedor();
        $invoice->loadFromData($result[0]);

        return $invoice;
    }

    private function findInvoiceByUuid(string $uuid): ?FacturaProveedor
    {
        $cfdi = new CfdiProveedor();
        $where = [new DataBaseWhere('uuid', $uuid)];

        if (!$cfdi->loadWhere($where)) {
            return null;
        }

        $sql = "SELECT fp.* FROM facturasprov fp
                INNER JOIN cfdis_proveedores cp ON fp.numproveedor = cp.serie || cp.folio
                WHERE cp.uuid = ? AND fp.codproveedor = cp.codproveedor
                LIMIT 1";

        $result = $this->db->select($sql, [$uuid]);

        if (empty($result)) {
            return null;
        }

        $invoice = new FacturaProveedor();
        $invoice->loadFromData($result[0]);

        return $invoice;
    }

    private function compareItems(ReconciliationItem $item, FacturaProveedor $invoice): void
    {
        $totalDiff = abs($item->total - $item->invoiceTotal);

        if ($totalDiff <= self::TOLERANCE_AMOUNT) {
            $item->estado = ReconciliationItem::STATUS_MATCHED;
            return;
        }

        $rate = $totalDiff / ($item->total > 0 ? $item->total : 1);

        if ($rate <= 0.05) {
            $item->estado = ReconciliationItem::STATUS_PARTIAL;
            return;
        }

        $item->estado = ReconciliationItem::STATUS_PARTIAL;
    }

    private function getCfdiReader(CfdiProveedor $cfdi): ?\FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader
    {
        try {
            $xml = $cfdi->localFileContent();
            if (empty($xml)) {
                return null;
            }
            return new \FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\XML\CfdiQuickReader($xml);
        } catch (Exception $e) {
            return null;
        }
    }

    public function getSupplierList(int $companyId): array
    {
        $sql = "SELECT DISTINCT p.codproveedor, p.nombre
                FROM cfdis_proveedores c
                INNER JOIN proveedores p ON c.codproveedor = p.codproveedor
                WHERE c.idempresa = ?
                ORDER BY p.nombre";

        $result = $this->db->select($sql, [$companyId]);

        return array_map(fn($row) => [
            'codproveedor' => $row['codproveedor'],
            'nombre' => $row['nombre'],
        ], $result);
    }

    public function getReconciliationSummary(int $companyId, int $year, int $month): array
    {
        $fromDate = sprintf('%d-%02d-01', $year, $month);
        $toDate = date('Y-m-t', strtotime($fromDate));

        $sql = "SELECT
                    COUNT(*) as total_cfdis,
                    SUM(CASE WHEN f.idfactura IS NOT NULL AND ABS(c.total - f.total) <= 0.01 THEN 1 ELSE 0 END) as matched,
                    SUM(CASE WHEN f.idfactura IS NOT NULL AND ABS(c.total - f.total) > 0.01 THEN 1 ELSE 0 END) as partial,
                    SUM(CASE WHEN f.idfactura IS NULL THEN 1 ELSE 0 END) as unmatched,
                    SUM(c.total) as total_amount
                FROM cfdis_proveedores c
                LEFT JOIN facturasprov f ON c.codproveedor = f.codproveedor
                    AND c.serie || c.folio = f.numproveedor
                WHERE c.idempresa = ?
                AND c.fecha_emision >= ? AND c.fecha_emision <= ?
                AND c.estado = 'vigente'";

        $result = $this->db->select($sql, [$companyId, $fromDate, $toDate]);

        if (empty($result)) {
            return [
                'total_cfdis' => 0,
                'matched' => 0,
                'partial' => 0,
                'unmatched' => 0,
                'total_amount' => 0.0,
            ];
        }

        return [
            'total_cfdis' => (int)$result[0]['total_cfdis'],
            'matched' => (int)$result[0]['matched'],
            'partial' => (int)$result[0]['partial'],
            'unmatched' => (int)$result[0]['unmatched'],
            'total_amount' => (float)$result[0]['total_amount'],
        ];
    }
}
