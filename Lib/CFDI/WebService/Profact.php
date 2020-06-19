<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\WebService;

use SoapClient;

ini_set("soap.wsdl_cache_enabled", 1);
class Profact
{
    const WS_TEST_URL = "https://cfdi33-pruebas.buzoncfdi.mx:1443/Timbrado.asmx?wsdl";
    const WS_URL = "https://timbracfdi33.mx:1443/Timbrado.asmx?wsdl";

    private $response;

    public function __construct($test = false)
    {
        switch ($test) {
            case true:
                $this->url = self::WS_TEST_URL;
                break;
            case false:
                $this->url = self::WS_URL;
                break;
        }

        $context = $this->buildContext($test);
        $this->options = $this->getOptions($context);
    }

    public function timbrar(array $params, $xml)
    {
        libxml_disable_entity_loader(false);

        $parametros['xmlComprobanteBase64'] = base64_encode($xml);
        $parametros = array_merge($parametros, $params);

        $cliente = new SoapClient($this->url, $this->options);
        $this->response = $cliente->__soapCall('TimbraCFDI', array('parameters' => $parametros));

        if (!is_soap_fault($this->response)) {
            $xml = $this->response->TimbraCFDIResult->anyType[3];

            if ('' !== $xml) return $xml;

            return false;
        } else {
            $this->errorLog("ERROR:\t" . $this->response->faultcode . " \t" . $this->response->faultstring);
            $this->error = $this->response->faultstring;
            $this->codigo_error = $this->response->faultcode;
        }

        return false;
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