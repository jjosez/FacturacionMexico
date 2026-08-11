<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Controller;

use Exception;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierCfdi\SupplierCfdiImporter;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Import\Options\ImportOptions;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Queue\AsyncImportProcessor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\SupplierInvoice\Queue\ImportQueue;
use Throwable;

class CfdiSupplierImport extends Controller
{
    public function maxDirectUploadFiles(): int
    {
        return max(1, (int)ini_get('max_file_uploads'));
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'Importación masiva de CFDI';
        $data['icon'] = 'fas fa-file-import';
        $data['menu'] = 'CFDI';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->inputOrQuery('action', '');
        if ($action !== '') {
            $this->setTemplate(false);

            if ($action !== 'status' && (
                !$this->request->isMethod('POST')
                || !$this->permissions->allowUpdate
                || !$this->validateFormToken()
            )) {
                $this->json(['success' => false, 'error' => 'No tiene permisos para realizar esta acción.'], 403);
                return;
            }

            $this->execAction($action);
            return;
        }

        $this->setTemplate('CfdiSupplierImport');
    }

    private function execAction(string $action): void
    {
        switch ($action) {
            case 'process':
                $this->processAction();
                break;
            case 'enqueue':
                $this->enqueueAction();
                break;
            case 'process-job':
                $this->processJobAction();
                break;
            case 'status':
                $this->statusAction();
                break;
            default:
                $this->json(['success' => false, 'error' => 'Acción no válida.'], 400);
        }
    }

    private function processAction(): void
    {
        try {
            $result = (new SupplierCfdiImporter())->import(
                $this->uploadedFiles(),
                $this->empresa,
                $this->mode(),
                $this->options(),
                SupplierCfdiImporter::SYNC_MAX_FILES
            );

            $this->json([
                'success' => true,
                'results' => $result->toArray(),
            ]);
        } catch (Throwable $e) {
            Tools::log('CFDI')->warning($e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function enqueueAction(): void
    {
        try {
            $jobId = (new ImportQueue())->enqueue(
                $this->uploadedFiles(),
                (int)$this->empresa->idempresa,
                (string)$this->user->nick,
                [
                    'mode' => $this->mode(),
                    'options' => $this->options()->toArray(),
                ]
            );

            $this->json(['success' => true, 'job_id' => $jobId]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function processJobAction(): void
    {
        $jobId = $this->request->input('job_id', '');
        if (!$this->ownsJob($jobId)) {
            $this->json(['success' => false, 'error' => 'Trabajo no encontrado.'], 404);
            return;
        }

        try {
            $result = (new AsyncImportProcessor())->process($jobId);
            $job = (new ImportQueue())->get($jobId);
            $this->json([
                'success' => $job !== null && !$job->isFailed(),
                'results' => $result->toArray(),
                'error' => $job?->error,
            ], $job !== null && $job->isFailed() ? 400 : 200);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function statusAction(): void
    {
        $jobId = $this->request->inputOrQuery('job_id', '');
        if (!$this->ownsJob($jobId)) {
            $this->json(['success' => false, 'error' => 'Trabajo no encontrado.'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'status' => (new ImportQueue())->getStatus($jobId),
        ]);
    }

    private function ownsJob(string $jobId): bool
    {
        if ($jobId === '') {
            return false;
        }

        $job = (new ImportQueue())->get($jobId);
        return $job !== null
            && $job->companyId === (int)$this->empresa->idempresa
            && $job->userNick === (string)$this->user->nick;
    }

    private function mode(): string
    {
        return $this->request->input('mode', SupplierCfdiImporter::MODE_REGISTER);
    }

    private function options(): ImportOptions
    {
        return ImportOptions::fromArray([
            'product_action' => $this->request->input('product_action', 'auto'),
            'tax_mode' => $this->request->input('tax_mode', 'preserve'),
            'update_supplier_prices' => $this->requestBoolean('update_supplier_prices'),
            'auto_match_products' => $this->requestBoolean('auto_match_products', true),
            'price_multiplier' => (float)$this->request->input('price_multiplier', 1.0),
        ]);
    }

    private function uploadedFiles(): array
    {
        $files = $this->request->files->getArray('xmlfiles');
        if ($files !== []) {
            return $files;
        }

        $file = $this->request->files->get('xmlfiles');
        if ($file !== null) {
            return [$file];
        }

        throw new Exception('No se recibieron archivos.');
    }

    private function requestBoolean(string $field, bool $default = false): bool
    {
        $value = $this->request->input($field);
        return $value === null || $value === ''
            ? $default
            : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function json(array $data, int $status = 200): void
    {
        $this->response->setHttpCode($status);
        $this->response()->json($data);
    }
}
