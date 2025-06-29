<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\StampService;

use PhpCfdi\Credentials\Credential;
use PhpCfdi\Finkok\FinkokEnvironment;
use PhpCfdi\Finkok\FinkokSettings;
use PhpCfdi\Finkok\QuickFinkok;
use PhpCfdi\XmlCancelacion\Models\CancelDocument;
use PhpCfdi\XmlCancelacion\Models\CancelReason;
use PhpCfdi\XmlCancelacion\Models\Uuid;

class FinkokStampService
{
    private $finkokSettings;

    private $cancellationDocument;

    public function __construct($username, $password, $test = false)
    {
        if ($test) {
            $this->finkokSettings = new FinkokSettings($username, $password, FinkokEnvironment::makeDevelopment());
        } else {
            $this->finkokSettings = new FinkokSettings($username, $password, FinkokEnvironment::makeProduction());
        }
    }

    public function timbrar(string $precfdi) : StampServiceResponse
    {
        $response = new StampServiceResponse();
        $finkok = new QuickFinkok($this->finkokSettings);
        $stampResult = $finkok->stamp($precfdi);

        if ($stampResult->hasAlerts()) {
            $response->setResponse('error');
            foreach ($stampResult->alerts() as $alert) {
                $response->setMessage('Error al timbrar el documento');
                $response->setMessageDetail($alert->message());
                $response->setMessageErrorCode($alert->errorCode());

                if ('307' === $alert->errorCode() && $alert->workProcessId()) {
                    $response->setUuid($alert->workProcessId());
                    $response->setPreviousSign(true);
                }
            }
        } else {
            $response->setResponse('success');
            $response->setMessage('Factura timbrada correctamente');
            $response->setXml($stampResult->xml());
            $response->setUuid($stampResult->uuid());
        }

        return $response;
    }

    public function getTimbradoPrevio(string $precfdi) : StampServiceResponse
    {
        $response = new StampServiceResponse();
        $finkok = new QuickFinkok($this->finkokSettings);
        $stampResult = $finkok->stamped($precfdi);

        if ($stampResult->hasAlerts()) {
            $response->setResponse('error');
            foreach ($stampResult->alerts() as $alert) {
                $response->setMessage('Error al obtener el documento');
                $response->setMessageDetail($alert->message());
                $response->setMessageErrorCode($alert->errorCode());
            }
        } else {
            $response->setResponse('success');
            $response->setMessage('CFDI obtenido correctamente');
            $response->setXml($stampResult->xml());
            $response->setUuid($stampResult->uuid());
        }

        return $response;
    }

    public function cancelar(string $uuid, array $credentials, string $substitute = ''): bool
    {
        $credential = Credential::openFiles(
            $credentials['certificado'],
            $credentials['llave'],
            $credentials['secreto']
        );
        $document = CancelDocument::newWithErrorsUnrelated($uuid);

        $finkok = new QuickFinkok($this->finkokSettings);
        $result = $finkok->cancel($credential, $document);
        $documentInfo = $result->documents()->first();

        if ($documentInfo->documentStatus() == 202 || $documentInfo->documentStatus() == 201) {
            return true;
        } elseif ($documentInfo->documentStatus() == 205) {
            $this->errorLog('UUID: ' . $uuid . ' No encontrado en el SAT');
        }
        $this->errorLog($documentInfo->cancellationStatus());

        return false;
    }

    public function getSatStatus(array $query)
    {
        $finkok = new QuickFinkok($this->finkokSettings);

        return $finkok->satStatus($query['emisor'], $query['receptor'], $query['uuid'], $query['total']);
    }

    private function errorLog($string): void
    {
        $logFile = CFDI_DIR . DIRECTORY_SEPARATOR . 'Log' . DIRECTORY_SEPARATOR . 'log.txt';
        $file = fopen($logFile, 'a');
        fwrite($file, date('c') . "\t" . $string . "\n\n");
        fclose($file);
    }
}
