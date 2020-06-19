<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder;


use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class CustomerCfdiBuilder extends CfdiBuilder
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
            'TipoCambio' => '1',
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
                'ClaveProdServ' => $producto->getFamilia()->clavesat,
                'NoIdentificacion' => $linea->referencia,
                'Cantidad' => $linea->cantidad,
                'ClaveUnidad' => $producto->getUnidadMedida(),
                'Unidad' => 'PIEZA',
                'Descripcion' => $linea->descripcion,
                'ValorUnitario' => $linea->pvpunitario,
                'Importe' => $linea->pvpsindto,
                'Descuento' => $linea->dtopor
            ])->addTraslado([
                'Impuesto' => $linea->codimpuesto,
                'Base' => $linea->pvptotal,
                'TipoFactor' => 'Tasa',
                'TasaOCuota' =>  $this->getTasaValue($linea->iva),
                'Importe' => $this->getIvaFromValue($linea->pvptotal, $linea->iva)
            ]);
        }

        $this->creator->addSumasConceptos(null, 2);
    }
}