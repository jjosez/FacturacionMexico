<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\StampService;

class StampServiceResponse
{
    private $message;
    private $messageDetail;
    private $response;
    private $xml;
    private $uuid;

    public function __construct()
    {
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

    public function setUUID($uuid)
    {
        $this->uuid = $uuid;
    }

    public function uuid()
    {
        return $this->uuid;
    }
}