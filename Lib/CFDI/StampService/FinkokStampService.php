<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\StampService;

use PhpCfdi\Credentials\Credential;
use PhpCfdi\Finkok\FinkokEnvironment;
use PhpCfdi\Finkok\FinkokSettings;
use PhpCfdi\Finkok\QuickFinkok;

class FinkokStampService
{
    private $finkokSettings;

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
            }
        } else {
            $response->setResponse('success');
            $response->setMessage('Factura timbrada correctamente');
            $response->setXml($stampResult->xml());
            $response->setUUID($stampResult->uuid());
        }

        return $response;
    }

    public function cancelar($uuid, $cerfile, $keyfile, $password)
    {
        $credential = Credential::openFiles($cerfile, $keyfile, $password);
        $finkok = new QuickFinkok($this->finkokSettings);

        $result = $finkok->cancel($credential, $uuid);
        $documentInfo = $result->documents()->first();

        if ($documentInfo->documentStatus() == 202 || $documentInfo->documentStatus() == 201) {
            return true;
        } elseif ($documentInfo->documentStatus() == 205) {
            $descripcion =  'UUID: ' . $uuid . ' No encontrado en el SAT';
            $this->errorLog($descripcion);
        }
        $this->errorLog($documentInfo->cancellationSatatus());

        return false;
    }

    public function getSatStatus($emisor, $receptor, $uuid, $total)
    {
        $finkok = new QuickFinkok($this->finkokSettings);

        return $finkok->satStatus($emisor, $receptor, $uuid, $total);
    }

    private function errorLog($string)
    {
        $logFile = CFDI_DIR . DIRECTORY_SEPARATOR . 'Log' . DIRECTORY_SEPARATOR . 'log.txt';
        $file = fopen($logFile, 'a');
        fwrite($file, date('c') . "\t" . $string . "\n\n");
        fclose($file);
    }
}