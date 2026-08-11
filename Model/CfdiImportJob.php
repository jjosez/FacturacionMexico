<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

final class CfdiImportJob extends ModelClass
{
    use ModelTrait;

    public $config;
    public $created_at;
    public $error;
    public $file_path;
    public $id;
    public $idempresa;
    public $lock_token;
    public $nick;
    public $processed_at;
    public $processed_items;
    public $progress;
    public $result;
    public $status;
    public $total_items;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'cfdi_import_jobs';
    }
}
