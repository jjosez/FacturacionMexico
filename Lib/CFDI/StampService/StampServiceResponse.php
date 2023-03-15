<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\StampService;

use Cassandra\Exception\TruncateException;

class StampServiceResponse
{
    private $message;
    /**
     * @var string
     */
    private $messageDetail;
    private $response;
    private $xml;
    private $uuid;
    private $messageErrorCode;

    /**
     * @var bool
     */
    private $previousSign;

    public function __construct()
    {
        $this->previousSign = false;
    }

    /**
     * @param mixed $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function hasError()
    {
        $result = ($this->response === 'error') ? true : false;

        return $result;
    }

    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getMessageDetail()
    {
        return $this->messageDetail;
    }

    /**
     * @param mixed $messageDetail
     */
    public function setMessageDetail($messageDetail)
    {
        $this->messageDetail = $messageDetail;
    }

    public function getXml()
    {
        return $this->xml;
    }

    /**
     * @param mixed $xml
     */
    public function setXml($xml)
    {
        $this->xml = $xml;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getMessageErrorCode(): string
    {
        return $this->messageErrorCode;
    }

    /**
     * @param string $messageErrorCode
     */
    public function setMessageErrorCode(string $messageErrorCode): void
    {
        $this->messageErrorCode = $messageErrorCode;
    }

    public function hasPreviousSign(): bool
    {
        return $this->previousSign;
    }

    /**
     * @param bool $previousSign
     */
    public function setPreviousSign(bool $previousSign): void
    {
        $this->previousSign = $previousSign;
    }
}
