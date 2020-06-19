<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI\Builder;


use FacturaScripts\Core\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class GlobalCfdiBuilder extends CfdiBuilder
{

    /**
     * GlobalCfdiBuilder constructor.
     *
     * @param FacturaCliente $factura
     * @param Empresa $empresa
     * @param string $uso set as SAT requirement 'P01' por definir.
     */
    public function __construct(FacturaCliente $factura, Empresa $empresa, string $uso = 'P01')
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
        foreach ($this->factura->parentDocuments() as $parent) {
            $this->comprobante->addConcepto([
                'ClaveProdServ' => '01010101',
                'NoIdentificacion' => $parent->primaryDescription(),
                'Cantidad' => 1,
                'ClaveUnidad' => 'ACT',
                'Descripcion' => 'Venta',
                'ValorUnitario' => $parent->netosindto,
                'Importe' => $parent->netosindto,
                'Descuento' => 0
            ])->addTraslado([
                'Impuesto' => '002',
                'Base' => $parent->netosindto,
                'TipoFactor' => 'Tasa',
                'TasaOCuota' =>  $this->getTasaValue(16),
                'Importe' => $parent->totaliva,
            ]);
        }

        $this->creator->addSumasConceptos(null, 2);
    }
}