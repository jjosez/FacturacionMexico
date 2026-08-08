<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use DateTime;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Reconciliation\CfdiReconciliationService;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Reconciliation\ReconciliationReport;

class ReconciliationDashboard extends Controller
{
    public ?ReconciliationReport $report = null;
    public array $supplierList = [];
    public int $selectedYear = 0;
    public int $selectedMonth = 0;

    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'Conciliación CFDI';
        $pagedata['icon'] = 'fas fa-balance-scale';
        $pagedata['menu'] = 'CFDI';
        $pagedata['showonmenu'] = true;

        return $pagedata;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->input('action', '');

        if ($action === 'generate-report') {
            $this->generateReport();
            return;
        }

        if ($action === 'export-csv') {
            $this->exportCsv();
            return;
        }

        $this->initDefaults();
        $this->loadSupplierList();
        $this->setTemplate('ReconciliationDashboard');
    }

    protected function initDefaults(): void
    {
        $this->selectedYear = (int)date('Y');
        $this->selectedMonth = (int)date('m');
    }

    protected function loadSupplierList(): void
    {
        $service = new CfdiReconciliationService();
        $this->supplierList = $service->getSupplierList($this->empresa->idempresa);
    }

    protected function generateReport(): void
    {
        $this->setTemplate(false);

        $year = $this->request->input('year');
        $month = $this->request->input('month');
        $supplierId = $this->request->input('supplier_id');

        if (empty($year) || empty($month)) {
            $this->response->setContent(json_encode([
                'success' => false,
                'error' => 'Año y mes son requeridos'
            ]));
            return;
        }

        $fromDate = new DateTime(sprintf('%d-%02d-01', $year, $month));
        $toDate = new DateTime(sprintf('%d-%02d-01', $year, $month));
        $toDate->modify('last day of this month');

        $service = new CfdiReconciliationService();
        $report = $service->reconcile(
            $this->empresa->idempresa,
            $fromDate,
            $toDate,
            $supplierId ?: null
        );

        $this->selectedYear = (int)$year;
        $this->selectedMonth = (int)$month;

        $this->response->setContent(json_encode([
            'success' => true,
            'report' => $report->toArray()
        ]));
    }

    protected function exportCsv(): void
    {
        $this->setTemplate(false);

        $year = $this->request->input('year');
        $month = $this->request->input('month');

        if (empty($year) || empty($month)) {
            $this->response->setContent('Año y mes son requeridos');
            return;
        }

        $fromDate = new DateTime(sprintf('%d-%02d-01', $year, $month));
        $toDate = new DateTime(sprintf('%d-%02d-01', $year, $month));
        $toDate->modify('last day of this month');

        $service = new CfdiReconciliationService();
        $report = $service->reconcile(
            $this->empresa->idempresa,
            $fromDate,
            $toDate
        );

        $csv = $this->generateCsv($report);

        $filename = sprintf('conciliacion_%d_%02d.csv', $year, $month);

        $this->response->headers->set('Content-Type', 'text/csv');
        $this->response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");
        $this->response->setContent($csv);
    }

    protected function generateCsv(ReconciliationReport $report): string
    {
        $lines = [];

        $lines[] = 'UUID,Folio,Fecha,Proveedor,Tipo,Total CFDI,Estado,Factura#,Total Factura,Diferencia';

        foreach ($report->items as $item) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s",%.2f,"%s","%s",%.2f,%.2f',
                $item->uuid,
                $item->numeroFactura,
                $item->fecha,
                $item->proveedorNombre,
                $item->tipo,
                $item->total,
                $item->estado,
                $item->invoiceNumber ?? '',
                $item->invoiceTotal ?? 0,
                $item->difference ?? 0
            );
        }

        return implode("\n", $lines);
    }

    public function getAvailableYears(): array
    {
        $currentYear = (int)date('Y');
        $years = [];

        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
            $years[] = $y;
        }

        return $years;
    }

    public function getAvailableMonths(): array
    {
        return [
            ['value' => 1, 'label' => 'Enero'],
            ['value' => 2, 'label' => 'Febrero'],
            ['value' => 3, 'label' => 'Marzo'],
            ['value' => 4, 'label' => 'Abril'],
            ['value' => 5, 'label' => 'Mayo'],
            ['value' => 6, 'label' => 'Junio'],
            ['value' => 7, 'label' => 'Julio'],
            ['value' => 8, 'label' => 'Agosto'],
            ['value' => 9, 'label' => 'Septiembre'],
            ['value' => 10, 'label' => 'Octubre'],
            ['value' => 11, 'label' => 'Noviembre'],
            ['value' => 12, 'label' => 'Diciembre'],
        ];
    }
}
