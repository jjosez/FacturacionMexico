<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests;

use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiValidationException;
use PHPUnit\Framework\TestCase;

final class CfdiParserTest extends TestCase
{
    public function testParseReturnsApplicationData(): void
    {
        $data = (new CfdiParser($this->xml()))->parse();

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $data->uuid);
        $this->assertSame('AAA010101AAA', $data->issuerRfc);
        $this->assertSame('BBB010101BBB', $data->recipientRfc);
        $this->assertSame('MXN', $data->currency);
        $this->assertSame('I', $data->type);
        $this->assertCount(1, $data->concepts);
        $this->assertSame('Producto de prueba', $data->concepts[0]['Descripcion']);
    }

    public function testRejectsInvalidXml(): void
    {
        $this->expectException(CfdiValidationException::class);

        new CfdiParser('<cfdi:Comprobante>');
    }

    private function xml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-08-10T12:00:00" Moneda="MXN" TipoDeComprobante="I" SubTotal="100.00" Total="116.00" LugarExpedicion="01000" FormaPago="01" MetodoPago="PUE" Serie="A" Folio="123">
    <cfdi:Emisor Rfc="AAA010101AAA" Nombre="Emisor de prueba" RegimenFiscal="601" />
    <cfdi:Receptor Rfc="BBB010101BBB" Nombre="Receptor de prueba" DomicilioFiscalReceptor="01000" RegimenFiscalReceptor="601" UsoCFDI="G03" />
    <cfdi:Conceptos>
        <cfdi:Concepto ClaveProdServ="01010101" Cantidad="1" ClaveUnidad="H87" Descripcion="Producto de prueba" ValorUnitario="100.00" Importe="100.00" />
    </cfdi:Conceptos>
    <cfdi:Complemento>
        <tfd:TimbreFiscalDigital Version="1.1" UUID="550e8400-e29b-41d4-a716-446655440000" FechaTimbrado="2026-08-10T12:01:00" RfcProvCertif="AAA010101AAA" SelloCFD="abc" NoCertificadoSAT="00001000000504465028" SelloSAT="def" />
    </cfdi:Complemento>
</cfdi:Comprobante>
XML;
    }
}
