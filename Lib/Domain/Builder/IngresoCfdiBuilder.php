<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Builder;

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
            $productoFamilia = $producto->getFamilia();

            $this->comprobante->addConcepto([
                'ClaveProdServ' => $productoFamilia->clavesat,
                'NoIdentificacion' => $linea->referencia,
                'Cantidad' => $linea->cantidad,
                'ClaveUnidad' => $productoFamilia->claveunidad,
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
