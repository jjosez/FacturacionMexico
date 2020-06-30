<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder;


use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class EgresoCfdiBuilder extends CfdiBuilder
{
    public function __construct(FacturaCliente $factura, Empresa $empresa, string $uso)
    {
        parent::__construct($factura, $empresa, 'E', $uso);
    }

    protected function getAtributosComprobante()
    {
        return [
            "Serie" => $this->factura->codserie,
            'Folio' => $this->factura->codigo,
            'Fecha' => $this->getFechaFactura($this->factura->fecha, $this->factura->hora),
            'FormaPago' => $this->factura->codpago,
            'Moneda' => $this->factura->coddivisa,
            'TipoCambio' => '1',
            'TipoDeComprobante' => $this->tipo,
            'MetodoPago' => 'PUE',
            'LugarExpedicion' => $this->empresa->codpostal,
            'Descuento' => 0.00,
        ];
    }

    protected function setConceptos()
    {
        $this->comprobante->addConcepto([
            'ClaveProdServ' => '84111506',
            'NoIdentificacion' => '00',
            'Cantidad' => 1,
            'ClaveUnidad' => 'ACT',
            'Unidad' => 'Actividad',
            'Descripcion' => 'Devolucion de mercancias',
            'ValorUnitario' => $this->getAbsoluteValue($this->factura->neto),
            'Importe' => $this->getAbsoluteValue($this->factura->neto),
            'Descuento' => 0,
        ])->addTraslado([
            'Impuesto' => '002',
            'Base' => $this->getAbsoluteValue($this->factura->neto),
            'TipoFactor' => 'Tasa',
            'TasaOCuota' => $this->getTasaValue(16),
            'Importe' => $this->getAbsoluteValue($this->factura->totaliva),
        ]);

        $this->creator->addSumasConceptos(null, 2);
    }
}