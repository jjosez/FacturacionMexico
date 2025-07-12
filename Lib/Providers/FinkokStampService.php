<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Providers;

use FacturaScripts\Plugins\FacturacionMexico\Contract\StampProviderInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\CfdiStatus;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters\StampResult;
use FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\CfdiSettings;
use PhpCfdi\Credentials\Credential;
use PhpCfdi\Finkok\FinkokEnvironment;
use PhpCfdi\Finkok\FinkokSettings;
use PhpCfdi\Finkok\QuickFinkok;
use PhpCfdi\XmlCancelacion\Models\CancelDocument;

class FinkokStampService implements StampProviderInterface
{
    protected QuickFinkok $quickFinkok;

    public function __construct(string $username, string $password, bool $testMode = true)
    {
        $settings = new FinkokSettings(
            $username,
            $password,
            $testMode ? FinkokEnvironment::makeDevelopment() : FinkokEnvironment::makeProduction()
        );

        $this->quickFinkok = new QuickFinkok($settings);
    }

    public function stamp(string $xml): StampResult
    {
        $response = $this->quickFinkok->stamp($xml);
        $hasPreviousStamp = false;
        $hasError = false;
        $uuid = '';
        $messages = [];

        if ($response->hasAlerts()) {
            $messages[] = 'Error al timbrar el documento';

            foreach ($response->alerts() as $alert) {
                $messages[] = $alert->errorCode() . ': ' . $alert->message();

                if ('307' === $alert->errorCode() && $alert->workProcessId()) {
                    $uuid = $alert->workProcessId();
                    $hasPreviousStamp = true;
                }
            }
            $hasError = true;
        } else {
            $uuid = $response->uuid();
            $messages[] = "Factura timbrada correctamente. UUID: $uuid";
        }

        return new StampResult(
            $hasError,
            $uuid,
            $response->xml(),
            implode(PHP_EOL, $messages),
            $response->faultCode(),
            $response->faultString(),
            $hasPreviousStamp
        );
    }

    public function cancel(string $uuid): StampResult
    {
        $credentials = CfdiSettings::satCredentials();

        $credential = Credential::openFiles(
            $credentials['certificado'],
            $credentials['llave'],
            $credentials['secreto']
        );
        $cancelDocument = CancelDocument::newWithErrorsUnrelated($uuid);

        $response = $this->quickFinkok->cancel($credential, $cancelDocument);
        $responseDocument = $response->documents()->first();

        if ($responseDocument->documentStatus() == 202 || $responseDocument->documentStatus() == 201) {
            $message = $responseDocument->documentStatus() . ' Estado:' . $responseDocument->documentStatus();

            return new StampResult(false, $responseDocument->uuid(), '', $message);
        } elseif ($responseDocument->documentStatus() == 205) {
            $message = "El cfdi $uuid no se ha encontrado en el SAT.";

            return new StampResult(true, '', '', $message);
        }

        return new StampResult(true, '', '', 'Error al cancelar el cfdi.');
    }

    public function getStamped(string $xml): StampResult
    {
        $response = $this->quickFinkok->stamped($xml);
        $hasError = false;
        $messages = [];

        if ($response->hasAlerts()) {
            $messages[] = 'Error al timbrar el documento';;

            foreach ($response->alerts() as $alert) {
                $messages[] = $alert->errorCode() . ': ' . $alert->message();
            }
            $hasError = true;
        }

        return new StampResult(
            $hasError,
            $response->uuid(),
            $response->xml(),
            implode(PHP_EOL, $messages)
        );
    }

    public function getStatus(array $query): CfdiStatus
    {
        $emisor = $query['emisor'] ?? '';
        $uuid = $query['uuid'] ?? '';
        $receptor = $query['receptor'] ?? '';
        $total = $query['total'] ?? '';

        $response = $this->quickFinkok->satStatus($emisor, $receptor, $uuid, $total);

        return new CfdiStatus(
            $response->cfdi(),
            $response->cancellable(),
            $response->cancellation(),
            $response->query(),
            $response->rawData()
        );
    }
}
