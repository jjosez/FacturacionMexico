<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder;


use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class IngresoCfdiBuilder extends CfdiBuilder
{
    public function __construct(FacturaCliente $factura, Empresa $empresa, string $uso)
    {
        parent::__construct($factura, $empresa, 'I', $uso);
    }

    protected function getAtributosComprobante()
    {
        return [
            "Serie" => $this->factura->codserie,
            'Folio' => $this->factura->codigo,
            'Fecha' => $this->getFechaFactura($this->factura->fecha, $this->factura->hora),
            'FormaPago' => $this->factura->codpago,
            'Moneda' => $this->factura->coddivisa,
            'TipoCambio' => ($this->factura->coddivisa === 'MXN') ? '1' : $this->factura->tasaconv,
            'TipoDeComprobante' => $this->tipo,
            'MetodoPago' => 'PUE',
            'LugarExpedicion' => $this->empresa->codpostal,
            'Descuento' => 0.00,
        ];
    }

    protected function setConceptos()
    {
        foreach ($this->factura->getLines() as $linea) {
            $producto = $linea->getProducto();

            $this->comprobante->addConcepto([
                'ClaveProdServ' => $producto->familia()->clavesat,
                'NoIdentificacion' => $linea->referencia,
                'Cantidad' => $linea->cantidad,
                'ClaveUnidad' => $producto->familia()->claveunidad,
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