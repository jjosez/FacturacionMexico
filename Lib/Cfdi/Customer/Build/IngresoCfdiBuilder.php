<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\Build;

use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\PaymentMethodResolver;

class IngresoCfdiBuilder extends CfdiBuilder
{
    public function __construct(FacturaCliente $factura)
    {
        parent::__construct($factura, 'I');
    }

    protected function getAtributosComprobante(): array
    {
        $payment = PaymentMethodResolver::resolve($this->factura);

        return [
            "Serie" => $this->factura->codserie,
            'Folio' => $this->factura->codigo,
            'Fecha' => $this->getFechaFactura(),
            'FormaPago' => $payment['FormaPago'],
            'Moneda' => $this->factura->coddivisa,
            'TipoCambio' => ($this->factura->coddivisa === 'MXN') ? '1' : $this->factura->tasaconv,
            'TipoDeComprobante' => $this->tipoComprobante,
            'MetodoPago' => $payment['MetodoPago'],
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
                'ClaveProdServ' => $producto->clavesat() ?? $productoFamilia->clavesat(),
                'NoIdentificacion' => $linea->referencia,
                'Cantidad' => $linea->cantidad,
                'ClaveUnidad' => $producto->claveunidad() ?? $productoFamilia->claveunidad(),
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
