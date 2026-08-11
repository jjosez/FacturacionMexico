<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests;

use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\CfdiParser;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Exception\CfdiValidationException;
use PHPUnit\Framework\TestCase;

final class CfdiParserTest extends TestCase
{
    public function testParseReturnsNormalizedData(): void
    {
        $data = (new CfdiParser($this->xml()))->parse();

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $data->uuid);
        $this->assertSame('AAA010101AAA', $data->emisor['rfc']);
        $this->assertSame('BBB010101BBB', $data->receptor['rfc']);
        $this->assertSame('G03', $data->receptor['usoCfdi']);
        $this->assertSame('MXN', $data->moneda);
        $this->assertSame('I', $data->tipoComprobante);
        $this->assertCount(1, $data->conceptos);
        $this->assertSame('Producto de prueba', $data->conceptos[0]['Descripcion']);
        $this->assertSame('002', $data->conceptos[0]['Traslados'][0]['Impuesto']);
        $this->assertSame('001', $data->conceptos[0]['Retenciones'][0]['Impuesto']);
        $this->assertSame('16.00', $data->impuestos['totalTrasladados']);
        $this->assertSame('10.00', $data->impuestos['totalRetenidos']);
        $this->assertSame('01', $data->relacionados[0]['tiporelacion']);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $data->relacionados[0]['relacionados'][0]);
    }

    public function testParsesUnstampedCfdi(): void
    {
        $data = (new CfdiParser(str_replace($this->stamp(), '', $this->xml())))->parse();

        $this->assertNull($data->uuid);
        $this->assertNull($data->fechaTimbrado);
    }

    public function testParsesEgresoAndMultipleRelationGroups(): void
    {
        $xml = str_replace(
            'TipoDeComprobante="I"',
            'TipoDeComprobante="E"',
            str_replace(
                '</cfdi:Comprobante>',
                '<cfdi:CfdiRelacionados TipoRelacion="04"><cfdi:CfdiRelacionado UUID="22222222-2222-2222-2222-222222222222" /></cfdi:CfdiRelacionados></cfdi:Comprobante>',
                $this->xml()
            )
        );

        $data = (new CfdiParser($xml))->parse();

        $this->assertSame('E', $data->tipoComprobante);
        $this->assertCount(2, $data->relacionados);
        $this->assertSame('04', $data->relacionados[1]['tiporelacion']);
    }

    public function testRejectsInvalidXml(): void
    {
        $this->expectException(CfdiValidationException::class);

        new CfdiParser('<cfdi:Comprobante>');
    }

    public function testRejectsNonCfdiXml(): void
    {
        $this->expectException(CfdiValidationException::class);

        new CfdiParser('<root />');
    }

    private function xml(): string
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-08-10T12:00:00" Moneda="MXN" TipoDeComprobante="I" SubTotal="100.00" Total="116.00" LugarExpedicion="01000" FormaPago="01" MetodoPago="PUE" Serie="A" Folio="123">
    <cfdi:Emisor Rfc="AAA010101AAA" Nombre="Emisor de prueba" RegimenFiscal="601" />
    <cfdi:Receptor Rfc="BBB010101BBB" Nombre="Receptor de prueba" DomicilioFiscalReceptor="01000" RegimenFiscalReceptor="601" UsoCFDI="G03" />
    <cfdi:Conceptos>
        <cfdi:Concepto ClaveProdServ="01010101" Cantidad="1" ClaveUnidad="H87" Descripcion="Producto de prueba" ValorUnitario="100.00" Importe="100.00" ObjetoImp="02"><cfdi:Impuestos><cfdi:Traslados><cfdi:Traslado Base="100.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="16.00" /></cfdi:Traslados><cfdi:Retenciones><cfdi:Retencion Base="100.00" Impuesto="001" TipoFactor="Tasa" TasaOCuota="0.100000" Importe="10.00" /></cfdi:Retenciones></cfdi:Impuestos></cfdi:Concepto>
    </cfdi:Conceptos>
    <cfdi:Impuestos TotalImpuestosTrasladados="16.00" TotalImpuestosRetenidos="10.00"><cfdi:Traslados><cfdi:Traslado Base="100.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="16.00" /></cfdi:Traslados><cfdi:Retenciones><cfdi:Retencion Impuesto="001" Importe="10.00" /></cfdi:Retenciones></cfdi:Impuestos>
    <cfdi:CfdiRelacionados TipoRelacion="01"><cfdi:CfdiRelacionado UUID="11111111-1111-1111-1111-111111111111" /></cfdi:CfdiRelacionados>
    <cfdi:Complemento>
        {STAMP}
    </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        return str_replace('{STAMP}', $this->stamp(), $xml);
    }

    private function stamp(): string
    {
        return '<tfd:TimbreFiscalDigital Version="1.1" UUID="550e8400-e29b-41d4-a716-446655440000" FechaTimbrado="2026-08-10T12:01:00" RfcProvCertif="AAA010101AAA" SelloCFD="abc" NoCertificadoSAT="00001000000504465028" SelloSAT="def" />';
    }
}
