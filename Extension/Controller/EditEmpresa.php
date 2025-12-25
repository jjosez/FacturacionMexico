<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Extension\Controller;

use Closure;
use Eclipxe\XmlResourceRetriever\XsltRetriever;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Core\Model\Empresa;
use FacturaScripts\Core\Request;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Domain\CfdiCatalogo;
use Matrix\Exception;

/**
 * @method addEditView(string $viewName, string $model, string $title, string $icon)
 * @method addButton(string $viewName, string[] $array)
 * @method getMainViewName()
 * @method ModelClass getModel()
 * @method tab($viewName)
 * @method Request request()
 * @method validateFormToken()
 * @property ModelClass $model
 */
class EditEmpresa
{
    public function createViews(): Closure
    {
        return function () {
            $viewName = 'EditCfdiSettings';

            $this->addEditView($viewName, 'Empresa', 'CFDI', 'fa-solid fa-stamp')
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false);
        };
    }

    public function execAfterAction(): Closure
    {
        return function ($action) {
            if ($action === 'download-sat-resources') {
                $result = $this->downloadSatResources();
            }
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            $code = $this->request()->inputOrQuery('code');

            $company = new Empresa();
            if (false === $this->validateCfdiSettingsRequest($action) || !$company->load($code)) return;

            switch ($action) {
                case 'edit':
                    $result = $this->saveCompanyCfdiSettings($company);
                    break;
                case 'cfdi-credentials-data':
                    $result = $this->saveCompanyCertificate($company);
                    break;
                case 'clear-cfdi-certificates':
                    $result = $this->clearCompanyCertificates($company);
                    break;
                case 'pac-credentials-data':
                    $result = $this->saveCompanyStampProviderCredentials($company);
                    break;
            };
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {

            $mainModel = $this->getModel();

            if ($viewName === 'EditCfdiSettings') {
                if (!$mainModel->exists()) return;

                $view->model = $mainModel;

                if ($mainModel->cfdi_pac_test) {
                    Tools::log()->warning('CFDI: Timbrado modo prueba activo.');
                }

                $invoiceStatus = $this->loadInvoiceCfdiStatus($viewName);
                $taxRegime = $this->loadTaxRegimeValues($viewName);

                /*$this->addButton($viewName, [
                    'action' => 'cfdi-credentials-data',
                    'color' => 'info',
                    'icon' => 'fa-solid fa-certificate',
                    'label' => 'Credenciales del SAT',
                    'type' => 'modal'
                ]);

                $this->addButton($viewName, [
                    'action' => 'pac-credentials-data',
                    'color' => 'info',
                    'icon' => 'fa-solid fa-user',
                    'label' => 'Credenciales del PAC',
                    'type' => 'modal'
                ]);*/
            }
        };
    }

    public function downloadSatResources(): Closure
    {
        return function () {
            try {
                $xsltResources = new XsltRetriever(CFDI_XSLT_DIR);
                $local = $xsltResources->retrieve(CFDI_XSLT_URL);

                $resourceTimeStamp = Tools::dateTime();
                Tools::settingsSet('cfdi', 'sat_sources_cached', $resourceTimeStamp);
                if (Tools::settingsSave()) {
                    Tools::log()->notice('Recursos guardados correctamente. ' . $resourceTimeStamp);
                    return;
                }

                throw new Exception('Error al descargar los recursos del SAT.');
            } catch (Exception $exception) {
                Tools::log()->warning($exception->getMessage());
            }
        };
    }

    public function saveCompanyCfdiSettings(): Closure
    {
        return function (Empresa $company) {
            $companyTaxRegime = $this->request()->input('cfdi_tax_regime');
            $invoiceStampedStatus = $this->request()->input('cfdi_stamped_status');
            $invoiceCanceledStatus = $this->request()->input('cfdi_canceled_status');

            $company->cfdi_tax_regime = $companyTaxRegime;
            $company->cfdi_stamped_status = $invoiceStampedStatus;
            $company->cfdi_canceled_status = $invoiceCanceledStatus;

            if (false === $company->save()) {
                Tools::log()->warning('Error guardando los datos.');
                return;
            }

            Tools::log()->notice('Ajustes guardados correctamente.');
        };
    }

    public function saveCompanyStampProviderCredentials(): Closure
    {
        return function (Empresa $company) {
            $userName = $this->request()->input('cfdi_pac_user');
            $token = $this->request()->input('pac_token');
            $testMode = $this->request()->request->getBool('cfdi_pac_test');

            $company->cfdi_pac_user = $userName;
            $company->cfdi_pac_token = $token;
            $company->cfdi_pac_test = $testMode;

            if (false === $company->save()) {
                Tools::log()->warning('Error guardando los datos.');
                return;
            }

            Tools::log()->notice('Credenciales del PAC registradas correctamente.');
        };
    }

    public function saveCompanyCertificate()
    {
        return function (Empresa $company) {
            $cerfile = $this->request()->file('cfdi_cert_file');
            $keyfile = $this->request()->file('cfdi_key_file');
            $password = $this->request()->input('cfdi_password');

            try {
                if (false === $this->validateCfdiCertificateFolder()) {
                    throw new Exception('Error al crear el directorio de certificados');
                }

                if ($cerfile && $cerfile->isValid()) {
                    $name = $company->cifnif . '.cer.pem';
                    if ($cerfile->move(CFDI_CERT_DIR, $name)) {
                        $company->cfdi_cert_filename = $name;
                    } else {
                        throw new Exception('Error al guardar el archivo: certificado.');
                    }
                }

                if ($keyfile && $keyfile->isValid()) {
                    $name = $company->cifnif . '.key.pem';
                    if ($keyfile->move(CFDI_CERT_DIR, $name)) {
                        $company->cfdi_key_filename = $name;
                    } else {
                        throw new Exception('Error al guardar el archivo: llave privada.');
                    }
                }

                $company->cfdi_key_password = base64_encode($password);
                if ($company->save()) {
                    Tools::log()->notice('Certificado y Llave privada guardados correctamente.');
                    return;
                }

                throw new Exception('Error al guardar en la base de datos.');
            } catch (Exception $exception) {
                Tools::log()->warning($exception->getMessage());
            }
        };
    }

    public function loadTaxRegimeValues(): Closure
    {
        return function (string $viewName) {
            $column = $this->tab($viewName)->columnForName('cfdi-tax-regime');

            if ($column && $column->widget->getType() === 'select') {
                $catalogItems = CfdiCatalogo::regimenFiscal()->all();

                $values = array_map(static function ($item) {
                    return [
                        'value' => $item->id,
                        'title' => "$item->id - $item->descripcion"
                    ];
                }, $catalogItems);

                $column->widget->setValuesFromArray($values, false, true);
            }
        };
    }

    public function loadCfdiUsageValues(): Closure
    {
        return function () {
        };
    }

    protected function loadInvoiceCfdiStatus(): Closure
    {
        return function (string $viewName) {
            $values = CodeModel::all(
                'estados_documentos',
                'idestado',
                'nombre',
                true,
                [Where::eq('tipodoc', 'FacturaCliente')]);

            $column = $this->tab($viewName)->columnForName('cfdi-stamped-status');
            if ($column && $column->widget->getType() === 'select') {
                $column->widget->setValuesFromCodeModel($values);
            }

            $column = $this->tab($viewName)->columnForName('cfdi-cenceled-status');
            if ($column && $column->widget->getType() === 'select') {
                $column->widget->setValuesFromCodeModel($values);
            }
        };
    }

    protected function clearCompanyCertificates(): Closure
    {
        return function (Empresa $company) {
            if (is_dir(CFDI_CERT_DIR)) {
                $files = glob(CFDI_CERT_DIR . '/*');
                $hiddenFiles = glob(CFDI_CERT_DIR . '/.*');
                $allFiles = array_merge($files ?: [], $hiddenFiles ?: []);

                foreach ($allFiles as $file) {
                    if (is_file($file) && !in_array(basename($file), ['.', '..'])) {
                        unlink($file);
                    }
                }
            }

            $company->cfdi_cert_filename = null;
            $company->cfdi_key_filename = null;
            $company->cfdi_key_password = null;

            if ($company->save()) {
                Tools::log()->notice('Certificados eliminados físicamente y ajustes reseteados en la base de datos.');
                return;
            }

            Tools::log()->warning('Hubo un error al actualizar el modelo de empresa.');
        };
    }

    protected function validateCfdiSettingsRequest(): Closure
    {
        return function (string $action) {
            $activeTab = $this->request()->inputOrQuery('activetab');

            if ($action === 'download-sat-resources') return true;

            if ($activeTab !== 'EditCfdiSettings') return false;

            return $this->validateFormToken();
        };
    }

    protected function validateCfdiCertificateFolder(): Closure
    {
        return function () {
            if (Tools::folderCheckOrCreate(CFDI_CERT_DIR)) {
                $htaccessPath = CFDI_CERT_DIR . DIRECTORY_SEPARATOR . '.htaccess';
                if (false === file_exists($htaccessPath)) {
                    file_put_contents($htaccessPath, 'deny from all');
                }

                return true;
            } else {
                Tools::log()->error('No se pudo crear el directorio: ' . CFDI_CERT_DIR);
                return false;
            }
        };
    }
}
