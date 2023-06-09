<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\CFDI;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\CompanyRelationTrait;

trait CfdiTrait
{
    use CompanyRelationTrait;

    /**
     * @var string
     */
    public $cfdirelacionado;

    /**
     * @var string
     */
    public $coddivisa;

    /**
     * @var string
     */
    public $estado;

    /**
     * @var string
     */
    public $fecha;

    /**
     * @var string
     */
    public $hora;

    /**
     * @var string
     */
    public $fechamod;

    /**
     * @var string
     */
    public $fechaemail;

    /**
     * @var string
     */
    public $formapago;

    /**
     * @var int
     */
    public $idcfdi;

    /**
     * @var int
     */
    public $idfactura;

    /**
     * @var string
     */
    public $metodopago;

    /**
     * @var string
     */
    public $razonreceptor;

    /**
     * @var string
     */
    public $rfcreceptor;

    /**
     * @var string
     */
    public $tipocfdi;

    /**
     * @var string
     */
    public $tiporelacion;

    /**
     * @var
     */
    public $total;

    /**
     * @var string
     */
    public $uuid;

    /**
     * @var string
     */
    public $uuidrelacionado;


    /**
     * @var string
     */
    public $version;

    public function loadFromInvoice($code): bool
    {
        $where = [new DataBaseWhere('idfactura', $code)];
        return $this->loadFromCode('', $where);
    }

    public function loadFromUuid($uuid): bool
    {
        $where = [new DataBaseWhere('uuid', $uuid)];
        return $this->loadFromCode('', $where);
    }

    public static function primaryColumn(): string
    {
        return 'idcfdi';
    }

    public function updateMailDate(): void
    {
        $this->fechaemail = date(self::DATE_STYLE);
    }
}
