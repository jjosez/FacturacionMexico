<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Adapters;

class CfdiStatus
{
    public string $cfdiStatus;
    public string $cancelableStatus;
    public string $cancellationStatus;
    public string $statusCode;
    public string $statusMessage;

    public function __construct(
        string $cfdiStatus,
        string $cancelableStatus,
        string $cancellationStatus,
        string $statusCode = '',
        string $statusMessage = ''
    ) {
        $this->cfdiStatus = $cfdiStatus;
        $this->cancelableStatus = $cancelableStatus;
        $this->cancellationStatus = $cancellationStatus;
        $this->statusCode = $statusCode;
        $this->statusMessage = $statusMessage;
    }
}
