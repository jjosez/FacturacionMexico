<?php


namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\Builder;


use CfdiUtils\Nodes\Node;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class GlobalCfdiBuilder extends CfdiBuilder
{

    /**
     * GlobalCfdiBuilder constructor.
     *
     * @param FacturaCliente $factura
     */
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
            'TipoCambio' => '1',
            'TipoDeComprobante' => $this->tipoComprobante,
            'Exportacion' => '01',
            'MetodoPago' => 'PUE',
            'LugarExpedicion' => $this->empresa->codpostal,
            'Descuento' => 0.00,
        ];
    }

    protected function setReceptor(): void
    {
        $customer = $this->factura->getSubject();

        // UsoCFDI set as SAT requirement 'S01' Sin efectos fiscales.
        $receptor = [
            'Rfc' => $customer->rfc(),
            'Nombre' => 'PUBLICO EN GENERAL',
            'UsoCFDI' => $customer->usoCfdi(),
            'RegimenFiscalReceptor' => $customer->regimenFiscal(),
            'DomicilioFiscalReceptor' => '97780',
        ];

        $this->comprobante->addReceptor($receptor);
    }

    protected function setConceptos(): void
    {
        foreach ($this->factura->parentDocuments() as $parent) {
            $this->comprobante->addConcepto([
                'ClaveProdServ' => '01010101',
                'NoIdentificacion' => $parent->primaryDescription(),
                'Cantidad' => 1,
                'ClaveUnidad' => 'ACT',
                'ObjetoImp' => '02',
                'Descripcion' => 'Venta',
                'ValorUnitario' => $parent->netosindto,
                'Importe' => $parent->netosindto,
                'Descuento' => 0
            ])->addTraslado([
                'Impuesto' => '002',
                'Base' => $parent->netosindto,
                'TipoFactor' => 'Tasa',
                'TasaOCuota' => $this->getTasaValue(16),
                'Importe' => $parent->totaliva,
            ]);
        }

        $this->creator->addSumasConceptos();
    }

    protected function setInformacionGlobal()
    {
        $nodo = new Node('cfdi:InformacionGlobal', [
            'Periodicidad' => '01',
            'Meses' => date('m', strtotime($this->factura->fecha)),
            'Año' => date('Y', strtotime($this->factura->fecha))
        ]);
        $this->comprobante->addChild($nodo);
    }


    protected function buildComprobante(): void
    {
        $this->setInformacionGlobal();
        parent::buildComprobante();
    }
}
