<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Infrastructure\Persistence;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiSettings;

final class SupplierInvoiceCatalogRepository
{
    public function findPaymentCode(string $satCode): string
    {
        $result = FormaPago::table()->whereEq('clavesat', $satCode)->first();
        return $result && !empty($result['codpago']) ? $result['codpago'] : 'CONTADO';
    }

    public function findRectifyingSeries(?string $requestedCode = null): string
    {
        $series = new Serie();
        $seriesCode = $requestedCode ?? CfdiSettings::serieEgresoProveedor();

        if (!empty($seriesCode) && $series->load($seriesCode) && $series->tipo === 'R') {
            return $series->codserie;
        }

        $available = Serie::all([Where::eq('tipo', 'R')], ['codserie' => 'ASC'], 0, 1);
        if (!empty($available)) {
            return $available[0]->codserie;
        }

        throw new Exception(Tools::lang()->trans('supplier-cfdi-rectifying-series-missing'));
    }
}
