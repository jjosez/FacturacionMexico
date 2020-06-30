<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\StampService;

use SoapClient;

ini_set("soap.wsdl_cache_enabled", 1);
class ProfactStampService
{
    const SERVICE_URL_DEV = "https://cfdi33-pruebas.buzoncfdi.mx:1443/Timbrado.asmx?wsdl";
    const SERVICE_URL = "https://timbracfdi33.mx:1443/Timbrado.asmx?wsdl";

    private $response;

    public function __construct($test = false)
    {
        switch ($test) {
            case true:
                $this->url = self::SERVICE_URL_DEV;
                break;
            case false:
                $this->url = self::SERVICE_URL;
                break;
        }

        $context = $this->buildContext($test);
        $this->options = $this->getOptions($context);
    }

    public function timbrar(array $params, $xml) : StampServiceResponse
    {
        libxml_disable_entity_loader(false);
        $response = new StampServiceResponse();

        $parametros['xmlComprobanteBase64'] = base64_encode($xml);
        $parametros = array_merge($parametros, $params);

        $cliente = new SoapClient($this->url, $this->options);
        $this->response = $cliente->__soapCall('TimbraCFDI', array('parameters' => $parametros));

        if (!is_soap_fault($this->response)) {
            $result = $this->response->TimbraCFDIResult;

            if ('' != $result->anyType[3]) {
                $response->setResponse('success');
                $response->setMessage('Factura timbrada correctamente');
                $response->setXml($result->anyType[3]);

                return $response;
            }

            $response->setResponse('error');
            $response->setMessage($result->anyType[0]);
            $detail = $result->anyType[1] . ': ' . $result->anyType[2];
            $response->setMessageDetail($detail);
        } else {
            $this->errorLog("ERROR:\t" . $this->response->faultcode . " \t" . $this->response->faultstring);
            $this->error = $this->response->faultstring;
            $this->codigo_error = $this->response->faultcode;

            $response->setResponse('error');
            $response->setMessage($this->response->faultcode);
            $response->setMessageDetail($this->response->faultstring);
        }

        return $response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    private function buildContext(bool $test)
    {
        $context = stream_context_create(
            [
                'ssl' => [
                    // set some SSL/TLS specific options
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => $test
                ],
                'http' => [
                    'user_agent' => 'PHPSoapClient'
                ],
            ]
        );
        return $context;
    }

    private function getOptions($context)
    {
        return [
            'stream_context' => $context,
            'cache_wsdl' => WSDL_CACHE_MEMORY,
            'trace' => true,
            'exceptions' => 0,
        ];
    }

    private function errorLog($string)
    {
        $f = fopen('log.txt', 'a');
        fwrite($f, date('c') . "\t" . $string . "\n\n");
        fclose($f);
    }
}