<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder;


use FacturaScripts\Dinamic\Model\FacturaCliente;

class IngresoCfdiBuilder extends CfdiBuilder
{
    public function __construct(FacturaCliente $factura)
    {
        parent::__construct($factura, 'I');
    }

    protected function getAtributosComprobante(): array
    {
        return [
            "Serie" => $this->factura->codserie,
            'Folio' => $this->factura->codigo,
            'Fecha' => $this->getFechaFactura($this->factura->fecha, $this->factura->hora),
            'FormaPago' => $this->factura->codpago,
            'Moneda' => $this->factura->coddivisa,
            'TipoCambio' => ($this->factura->coddivisa === 'MXN') ? '1' : $this->factura->tasaconv,
            'TipoDeComprobante' => $this->tipoComprobante,
            'MetodoPago' => 'PUE',
            'Exportacion' => '01',
            'LugarExpedicion' => $this->empresa->codpostal,
            'Descuento' => 0.00,
        ];
    }

    protected function setConceptos()
    {
        foreach ($this->factura->getLines() as $linea) {
            $producto = $linea->getProducto();

            $this->comprobante->addConcepto([
                'ClaveProdServ' => $producto->getFamilia()->clavesat,
                'NoIdentificacion' => $linea->referencia,
                'Cantidad' => $linea->cantidad,
                'ClaveUnidad' => $producto->getFamilia()->claveunidad,
                'ObjetoImp' => '02',
                'Unidad' => 'PIEZA',
                'Descripcion' => $linea->descripcion,
                'ValorUnitario' => $linea->pvpunitario,
                'Importe' => $linea->pvpsindto,
                'Descuento' => $linea->dtopor
            ])->addTraslado($this->buildConceptoTraslado($linea));
        }

        $this->creator->addSumasConceptos(null, 2);
    }
}
