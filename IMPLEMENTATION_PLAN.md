# Plan de Implementación - FacturacionMexico

## Resumen Ejecutivo

Este plan detalla las mejoras de mantenimiento y escalabilidad para el plugin FacturacionMexico, organizadas en 4 fases incrementales. La duración estimada total es de 6-8 semanas, priorizando el ProductMatchingService (mayor impacto en usabilidad).

---

## FASE 1: Fundamentos y Product Matching

**Duración estimada**: 2 semanas
**Prioridad**: P1 - Crítico
**Objetivo**: Sistema de conciliación automática de productos

### 1.1 Refactorizar CfdiQuickReader

**Archivos a modificar**:
- `Lib/Infrastructure/XML/CfdiQuickReader.php`

**Tareas**:
- [ ] Eliminar método `conceptos()` raw (líneas 101-106)
- [ ] Crear método `getConceptos(): array` que siempre normalice
- [ ] Agregar `getImpuestos()` para obtener todos los impuestos del comprobante
- [ ] Agregar `getAddenda(): array` para capturar addendas
- [ ] Implementar cache en memoria (propiedad privada `$cache = []`)
- [ ] Agregar método `clearCache()`

**Criterio de aceptación**: Solo existe `conceptosNormalized()`, sin acceso a nodos internos

```php
// Ejemplo del cambio esperado
public function getConceptos(): array
{
    return $this->conceptosNormalized();
}

public function getImpuestos(): array
{
    $traslados = [];
    $retenciones = [];

    if (isset($this->comprobante->impuestos)) {
        foreach (($this->comprobante->impuestos->retenciones)() as $retencion) {
            $retenciones[] = [
                'Impuesto' => $retencion['Impuesto'],
                'Importe' => $retencion['Importe'],
            ];
        }
        foreach (($this->comprobante->impuestos->traslados)() as $traslado) {
            $traslados[] = [
                'Impuesto' => $traslado['Impuesto'],
                'Base' => $traslado['Base'],
                'TipoFactor' => $traslado['TipoFactor'],
                'TasaOCuota' => $traslado['TasaOCuota'],
                'Importe' => $traslado['Importe'],
            ];
        }
    }

    return [
        'traslados' => $traslados,
        'retenciones' => $retenciones,
        'totalTrasladados' => $this->comprobante->impuestos['TotalImpuestosTrasladados'],
        'totalRetenidos' => $this->comprobante->impuestos['TotalImpuestosRetenidos'],
    ];
}
```

---

### 1.2 Crear ProductMatchingService

**Archivos a crear**:
- `Lib/Application/Matching/MatchResult.php`
- `Lib/Application/Matching/ProductMatchingService.php`
- `Lib/Application/Matching/Matchers/ReferenceMatcher.php`
- `Lib/Application/Matching/Matchers/FuzzyDescriptionMatcher.php`
- `Lib/Application/Matching/Matchers/SatCatalogMatcher.php`
- `Lib/Application/Matching/Matchers/SupplierLinkMatcher.php`

**Estructura de archivos**:

```
Lib/Application/Matching/
├── MatchResult.php              # Value object con resultado del match
├── ProductMatchingService.php   # Servicio principal
└── Matchers/
    ├── MatcherInterface.php     # Contrato para matchers
    ├── ReferenceMatcher.php     # Match por referencia exacta
    ├── FuzzyDescriptionMatcher.php # Match por similitud de descripción
    ├── SatCatalogMatcher.php    # Match por clave SAT
    └── SupplierLinkMatcher.php  # Match por vínculo proveedor-producto
```

**Tareas**:

#### MatchResult.php
```php
class MatchResult
{
    public const CONFIDENCE_EXACT = 1.0;
    public const CONFIDENCE_HIGH = 0.85;
    public const CONFIDENCE_MEDIUM = 0.70;
    public const CONFIDENCE_LOW = 0.50;
    public const CONFIDENCE_NONE = 0.0;

    public float $confidence;
    public ?string $referencia;
    public ?Producto $product;
    public string $matchMethod;
    public ?string $matchDescription;
    public bool $isLinked; // ya existe ProductoProveedor

    public static function noMatch(): self;
    public static function exactMatch(Producto $product, string $method): self;
    public static function suggestion(Producto $product, float $confidence, string $method): self;
    public function isUsable(): bool; // confidence >= CONFIDENCE_MEDIUM
}
```

#### MatcherInterface.php
```php
interface MatcherInterface
{
    public function match(array $concepto, Proveedor $supplier): ?MatchResult;
    public function getName(): string;
    public function getPriority(): int; // menor = más prioridad
}
```

#### ReferenceMatcher.php (prioridad 1)
- Busca por `NoIdentificacion` == `referencia_fabricante` (plugin SKU)
- Fallback: `NoIdentificacion` == `referencia`
- Confidence: 1.0 si match exacto, 0.0 si no

#### SupplierLinkMatcher.php (prioridad 2)
- Busca en `productos_proveedores` donde `refproveedor` == `NoIdentificacion`
- Confidence: 1.0 si encuentra, 0.0 si no

#### SatCatalogMatcher.php (prioridad 3)
- Busca producto con misma `ClaveProdServ`
- Acepta solo productos con confidence >= 0.80
- Requiere índice en `clave_sat` en tabla productos

#### FuzzyDescriptionMatcher.php (prioridad 4)
- Usa similar_text() o levenshtein para comparar `Descripcion` con `descripcion` del producto
- Configurable threshold (default 0.85)
- Costo: O(n) por concepto, requiere limit

#### ProductMatchingService.php
```php
class ProductMatchingService
{
    /** @var MatcherInterface[] */
    private array $matchers = [];

    public function registerMatcher(MatcherInterface $matcher): void;
    public function matchConcept(array $concepto, Proveedor $supplier): MatchResult;
    public function matchAll(array $conceptos, Proveedor $supplier): array; // index => MatchResult
    public function getStats(array $results): MatchingStats; // {total, matched, suggestions, unmatched}
}
```

**Dependencias**: 1.1 (CfdiQuickReader refactorizado)
**Criterio de aceptación**: 
- Al menos 80% de productos se matchean automáticamente en un dataset de prueba
- Tiempo de match < 100ms por concepto

---

### 1.3 Crear ProductMappingStorage

**Archivos a crear**:
- `Lib/Infrastructure/Persistence/ProductMappingStorage.php`

**Propósito**: Persistir y cachear mappings entre productos CFDI y productos internos

```php
class ProductMappingStorage
{
    public const TABLE = 'cfdi_product_mappings';

    public function __construct() { }

    public function getMapping(int $companyId, string $emisorRfc, string $noIdentificacion): ?ProductMapping;
    public function saveMapping(ProductMapping $mapping): bool;
    public function deleteMapping(int $mappingId): bool;
    public function getMappingsByCompany(int $companyId): array;
    public function getMappingsBySupplier(string $codproveedor): array;

    // Cache en memoria por request
    public function getCachedMapping(...): ?ProductMapping;
    public function cacheMapping(...): void;
}
```

**Tabla SQL requerida**:
```sql
CREATE TABLE cfdi_product_mappings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codproveedor VARCHAR(6) NOT NULL,
    idempresa INTEGER NOT NULL,
    emisor_rfc VARCHAR(13) NOT NULL,
    cfdi_referencia VARCHAR(50) NOT NULL,  -- NoIdentificacion del CFDI
    referencia VARCHAR(50) NOT NULL,        -- referencia del producto interno
    match_method VARCHAR(20) NOT NULL,      -- 'exact', 'fuzzy', 'sat', 'supplier_link'
    confidence REAL NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE(idempresa, emisor_rfc, cfdi_referencia)
);

CREATE INDEX idx_cfdi_mapping_supplier ON cfdi_product_mappings(codproveedor);
CREATE INDEX idx_cfdi_mapping_reference ON cfdi_product_mappings(cfdi_referencia);
```

**Dependencias**: 1.2 (ProductMatchingService)
**Criterio de aceptación**: Mappings se persistren y se reutilizan en futuras importaciones

---

### 1.4 Actualizar CfdiSupplierWizard - Paso de Productos

**Archivos a modificar**:
- `Controller/CfdiSupplierWizard.php`
- `View/Block/CfdiSupplierWizard-2.html.twig`
- `Assets/JS/Supplier/controllers/ProductLinkController.js`
- `Assets/JS/Supplier/views/ProductLinkView.js`
- `Assets/JS/Supplier/models/ProductLinkStore.js`

**Tareas**:

#### Modificar ProductLinkStore
```javascript
// Agregar a ProductLinkStore.js
class ProductLinkStore {
    // ... métodos existentes ...

    // NUEVOS
    setMatchResult(index, matchResult) {
        // Almacena objeto completo {referencia, confidence, method}
        this.matchResults.set(String(index), matchResult);
    }

    getMatchResult(index) {
        return this.matchResults.get(String(index));
    }

    getStats() {
        return {
            total: this.links.size,
            linked: Array.from(this.links.keys()).length,
            suggested: Array.from(this.matchResults.keys())
                .filter(k => !this.links.has(k)).length
        };
    }
}
```

#### Modificar ProductLinkView
```javascript
// Agregar en ProductLinkView.js
updateMatchIndicator(index, matchResult) {
    const cell = document.getElementById(`referencia-${index}`);
    if (!cell) return;

    // Remover clases anteriores
    cell.classList.remove('match-exact', 'match-high', 'match-medium', 'match-low', 'match-none');

    if (matchResult.isLinked) {
        cell.classList.add('match-exact');
        cell.innerHTML = `${matchResult.referencia} <i class="fas fa-link text-success"></i>`;
    } else if (matchResult.confidence >= 0.85) {
        cell.classList.add('match-high');
        cell.innerHTML = `${matchResult.referencia || '—'} (${Math.round(matchResult.confidence * 100)}%)`;
    } else if (matchResult.confidence >= 0.70) {
        cell.classList.add('match-medium');
        cell.innerHTML = `${matchResult.referencia || '—'} <span class="badge bg-warning">Sugerido</span>`;
    } else {
        cell.classList.add('match-none');
        cell.innerHTML = '—';
    }
}

showMatchConfidenceBadge(index, confidence) {
    const badge = document.getElementById(`match-badge-${index}`);
    if (!badge) return;

    const colors = {
        'match-exact': 'bg-success',
        'match-high': 'bg-success',
        'match-medium': 'bg-warning',
        'match-low': 'bg-secondary'
    };

    badge.className = `badge ${colors[matchType]} ms-2`;
    badge.textContent = `${Math.round(confidence * 100)}%`;
}
```

#### Modificar CfdiSupplierWizard-2.html.twig
```twig
{# Agregar columna de coincidencia y badges #}
<th>Coincidencia</th>
<th>Acciones</th>

{# En cada fila #}
<td>
    <span id="match-badge-{{ loop.index0 }}" class="badge bg-secondary">—</span>
    <small id="match-method-{{ loop.index0 }}" class="text-muted d-block"></small>
</td>

{# Cambiar botones #}
<td>
    {% if concepto.matched %}
        <button type="button" class="btn btn-sm btn-success">
            <i class="fas fa-check"></i> Vinculado
        </button>
    {% elseif concepto.suggested %}
        <button type="button" class="btn btn-sm btn-warning sugerir-btn"
                data-action="product:apply:suggestion" data-index="{{ loop.index0 }}">
            <i class="fas fa-lightbulb"></i> Aplicar sugerencia
        </button>
    {% endif %}
    <button type="button" class="btn btn-sm btn-info vincular-btn"
            data-action="product:link:open" data-index="{{ loop.index0 }}">
        Buscar
    </button>
    {# ... resto de botones ... #}
</td>
```

#### Modificar CfdiSupplierWizard.php
```php
// Agregar al inicio del controller
public function getMatchedConcepts(): array
{
    $matchingService = new ProductMatchingService();
    $conceptos = $this->reader->conceptosNormalized();

    return $matchingService->matchAll($conceptos, $this->supplier);
}
```

**Dependencias**: 1.2, 1.3
**Criterio de aceptación**:
- Productos con match automático muestran badge verde
- Productos sin match muestran opción de búsqueda
- Stats muestran % de vinculación

---

## FASE 2: Importación Masiva y Configuración

**Duración estimada**: 2 semanas
**Prioridad**: P1 - Crítico
**Objetivo**: Batch import y configuración de importación

### 2.1 Crear SupplierCfdiImportService

**Archivos a crear**:
- `Lib/Application/Import/SupplierCfdiImportService.php`
- `Lib/Application/Import/ConceptoMapperInterface.php`
- `Lib/Application/Import/ExactConceptMapper.php`
- `Lib/Application/Import/FacturaProveedorMapper.php`

**Tareas**:

#### ConceptoMapperInterface.php
```php
interface ConceptoMapperInterface
{
    public function map(
        array $concepto,
        Proveedor $supplier,
        ?Producto $product = null,
        ImportOptions $options = null
    ): LineaFacturaProveedor;

    public function supports(array $concepto): bool;
}
```

#### ExactConceptMapper.php
- Map 1:1 concepto CFDI → línea factura
- Aplica descuentos
- Calcula impuestos desde CFDI

#### FacturaProveedorMapper.php
```php
class FacturaProveedorMapper
{
    public function mapFromCfdi(
        CfdiProveedor $cfdi,
        Proveedor $supplier,
        CfdiQuickReader $reader,
        ImportOptions $options
    ): FacturaProveedor {
        // Crea o carga factura
        // Itera conceptos y crea líneas
        // Calcula totales
        // Aplica opciones (update prices, etc)
    }
}
```

#### SupplierCfdiImportService.php
```php
class SupplierCfdiImportService
{
    public function __construct(
        ConceptoMapperInterface $conceptoMapper,
        ProductMappingStorage $mappingStorage,
        ProductMatchingService $matchingService
    ) { }

    public function importSingle(
        CfdiProveedor $cfdi,
        ImportOptions $options = null
    ): ImportResult { }

    public function importBatch(
        array $cfdis,
        ImportOptions $options = null,
        callable $progressCallback = null
    ): BatchImportResult { }

    public function createInvoiceFromCfdi(
        CfdiProveedor $cfdi,
        ImportOptions $options = null
    ): FacturaProveedor { }
}

class ImportOptions
{
    public bool $updatePrices = false;
    public bool $createMissingProducts = false;
    public bool $autoMatchProducts = true;
    public float $priceMultiplier = 1.0;
    public string $taxMode = 'preserve'; // 'preserve' | 'company_default'
    public ?int $codalmacen = null;
    public ?int $codserie = null;
}

class ImportResult
{
    public bool $success;
    public ?FacturaProveedor $invoice;
    public ?string $error;
    public array $warnings = [];
    public array $appliedMappings = [];
}

class BatchImportResult
{
    public int $total;
    public int $success;
    public int $failed;
    public array $errors; // [{uuid, error}]
    public array $invoices; // [{uuid, invoiceId}]
    public float $elapsedSeconds;
}
```

**Dependencias**: 1.2, 1.3
**Criterio de aceptación**: Una llamada `importBatch()` procesa 100 CFDIs en < 30 segundos

---

### 2.2 Batch Import UI

**Archivos a modificar**:
- `Controller/ListCfdiProveedor.php`
- `View/ListCfdiProveedor.xml`

**Tareas**:

#### ListCfdiProveedor.php - Agregar acciones batch
```php
public function privateCore(&$response, $user, $permissions): void
{
    parent::privateCore($response, $user, $permissions);

    $action = $this->request->input('action', '');

    switch ($action) {
        case 'batch-import':
            $this->batchImportAction();
            break;
        case 'download-template':
            $this->downloadTemplateAction();
            break;
    }
}

protected function batchImportAction(): void
{
    $uploadFile = $this->request->files->get('xmlfiles');
    $company = $this->company;

    $service = $this->getImportService();

    $result = $service->importZip(
        $uploadFile,
        $company,
        function (int $current, int $total) {
            // Progress callback paraAJAX polling
            $this->updateProgress($current, $total);
        }
    );

    $this->setTemplate(false);
    $this->response->setContent(json_encode($result));
}
```

#### ListCfdiProveedor.xml - Agregar botón batch
```xml
<widget prefix="filters" name="batch_actions" type="actions">
    <values title="batch-import" label="Importación Masiva">
        <option action="batch-import">Subir ZIP</option>
    </values>
</widget>
```

#### Crear Modal para batch import
```twig
{# View/Modal/BatchImportModal.html.twig #}
<div class="modal fade" id="batchImportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="batchImportForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Importación Masiva de CFDIs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Archivos XML o ZIP</label>
                        <input type="file" name="xmlfiles[]" multiple accept=".xml,.zip" class="form-control">
                        <small class="text-muted">Máximo 50 archivos o 10MB</small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="create_invoices" id="createInvoices" checked class="form-check-input">
                            <label class="form-check-label" for="createInvoices">
                                Generar facturas automáticamente
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opciones de productos</label>
                        <select name="product_action" class="form-select">
                            <option value="skip">Solo vincular existentes</option>
                            <option value="auto">Vincular automáticamente</option>
                            <option value="create">Crear productos faltantes</option>
                        </select>
                    </div>
                    <div class="progress d-none" id="batchProgress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

**Dependencias**: 2.1
**Criterio de aceptación**:
- Usuario puede subir ZIP con hasta 50 CFDIs
- Progress bar muestra avance
- Reporte final con errores/success

---

### 2.3 Wizard Paso 4 - Configuración

**Archivos a crear**:
- `View/Block/CfdiSupplierWizard-4.html.twig`
- `Lib/Application/Import/ImportOptionsFactory.php`

**Tareas**:

#### CfdiSupplierWizard.php - Agregar paso 4
```php
protected function execAction(string $action): void
{
    switch ($action) {
        case 'import-supplier-cfdi':
            $this->importCfdiAction();
            break;
        case 'save-configuration':  // NUEVO
            $this->saveConfigurationAction();
            break;
        default:
            break;
    }
}

protected function saveConfigurationAction(): void
{
    $options = ImportOptionsFactory::fromRequest($this->request);

    // Guardar en sesión para usar en import
    $this->session->set('cfdi_import_options', $options->toArray());

    $this->importCfdiAction($options);
}
```

#### CfdiSupplierWizard-4.html.twig
```twig
<div class="card">
    <div class="card-header">Configuración de Importación</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Acción con productos</label>
                    <select name="product_action" class="form-select">
                        <option value="skip">Solo vincular productos existentes</option>
                        <option value="auto" selected>Vincular automáticamente</option>
                        <option value="create">Crear productos no encontrados</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Modo de impuestos</label>
                    <select name="tax_mode" class="form-select">
                        <option value="preserve" selected>Mantener del CFDI</option>
                        <option value="company">Usar configuración de empresa</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="update_supplier_prices" value="1" class="form-check-input">
                        <label class="form-check-label">Actualizar precios de proveedor</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Multiplicador de precios</label>
                    <input type="number" name="price_multiplier" value="1.0" step="0.01" min="0.01" class="form-control">
                    <small class="text-muted">Ej: 1.12 para agregar 12%</small>
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle"></i> Resumen</h5>
            <p id="importSummary">0 conceptos listos para importar</p>
        </div>
    </div>
</div>
```

#### ImportOptionsFactory.php
```php
class ImportOptionsFactory
{
    public static function fromRequest(Request $request): ImportOptions
    {
        return new ImportOptions([
            'productAction' => $request->get('product_action', 'auto'),
            'taxMode' => $request->get('tax_mode', 'preserve'),
            'updateSupplierPrices' => $request->bool('update_supplier_prices'),
            'priceMultiplier' => $request->float('price_multiplier', 1.0),
        ]);
    }

    public static function fromSession(Session $session): ImportOptions
    {
        $data = $session->get('cfdi_import_options', []);
        return new ImportOptions($data);
    }
}
```

**Dependencias**: 2.1
**Criterio de aceptación**:
- Wizard tiene 4 pasos visibles
- Configuración se aplica a la importación
- Resumen muestra estadísticas antes de importar

---

## FASE 3: Conciliación y Reportes

**Duración estimada**: 1.5 semanas
**Prioridad**: P2 - Alto
**Objetivo**: Reportes de conciliación y dashboard

### 3.1 CfdiReconciliationService

**Archivos a crear**:
- `Lib/Application/Reconciliation/CfdiReconciliationService.php`
- `Lib/Application/Reconciliation/ReconciliationReport.php`
- `Lib/Application/Reconciliation/ReconciliationItem.php`

**Tareas**:

#### ReconciliationItem.php
```php
class ReconciliationItem
{
    public string $uuid;
    public string $tipo; // 'ingreso' | 'egreso'
    public string $numeroFactura;
    public string $fecha;
    public float $total;
    public string $proveedorNombre;
    public string $estado; // 'matched' | 'partial' | 'unmatched'
    public ?int $invoiceId;
    public ?float $invoiceTotal;
    public ?float $difference;
}
```

#### ReconciliationReport.php
```php
class ReconciliationReport
{
    public DateTime $fromDate;
    public DateTime $toDate;
    public int $companyId;
    public array $items = [];

    public int $totalCfdis;
    public int $matchedCount;
    public int $partialCount;
    public int $unmatchedCount;

    public float $totalCfdisAmount;
    public float $totalMatchedAmount;
    public float $totalDifference;

    public function getMatchRate(): float; // porcentaje
    public function getGroupedBySupplier(): array;
    public function toArray(): array;
}
```

#### CfdiReconciliationService.php
```php
class CfdiReconciliationService
{
    public function __construct(
        CfdiSupplierRepository $supplierRepository,
        FacturaProveedorRepository $invoiceRepository
    ) { }

    public function reconcile(
        int $companyId,
        DateTime $fromDate,
        DateTime $toDate,
        ?string $supplierId = null
    ): ReconciliationReport { }

    /**
     * Compara CFDI vs FacturaProveedor:
     * - Mismo proveedor
     * - Mismo total (con tolerancia de 0.01)
     * - Misma fecha (mismo día)
     * - Mismos conceptos (count y montos)
     */
    protected function matchCfdiWithInvoice(
        CfdiProveedor $cfdi,
        FacturaProveedor $invoice
    ): MatchQuality { }

    protected function findPotentialMatches(CfdiProveedor $cfdi): array;
}
```

**Dependencias**: 2.1
**Criterio de aceptación**:
- Reporte agrupa CFDIs por estado de match
- Muestra diferencia en pesos entre CFDI y factura

---

### 3.2 Dashboard de Conciliación

**Archivos a crear**:
- `Controller/ReconciliationDashboard.php`
- `View/ReconciliationDashboard.html.twig`

**Tareas**:

#### ReconciliationDashboard.php
```php
class ReconciliationDashboard extends Controller
{
    public function getPageData(): array
    {
        return [
            'title' => 'Conciliación CFDI',
            'icon' => 'fas fa-balance-scale',
            'menu' => 'CFDI',
        ];
    }

    public function privateCore(...): void
    {
        parent::privateCore(...);

        $action = $this->request->input('action', '');

        if ($action === 'generate-report') {
            $this->generateReport();
            return;
        }

        $this->setTemplate('ReconciliationDashboard');
    }

    protected function generateReport(): void
    {
        $company = $this->company;
        $fromDate = $this->request->date('from_date');
        $toDate = $this->request->date('to_date');

        $service = new CfdiReconciliationService();
        $report = $service->reconcile(
            $company->idempresa,
            $fromDate,
            $toDate
        );

        $this->views = []; // clear
        $this->report = $report;
        $this->setTemplate('ReconciliationDashboard');
    }
}
```

#### ReconciliationDashboard.html.twig
```twig
{% extends 'Master/MenuBgTemplate.html.twig' %}

{% block body %}
<div class="container-fluid">
    <h1>Conciliación de CFDIs</h1>

    <form method="post" class="mb-4">
        <input type="hidden" name="action" value="generate-report">
        <div class="row">
            <div class="col-md-3">
                <label>Desde</label>
                <input type="date" name="from_date" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label>Hasta</label>
                <input type="date" name="to_date" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label>Proveedor (opcional)</label>
                <select name="supplier_id" class="form-select">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Generar Reporte</button>
            </div>
        </div>
    </form>

    {% if fsc.report %}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Coincidencias</h5>
                    <p class="display-4">{{ fsc.report.matchedCount }}</p>
                    <p>{{ fsc.report.totalMatchedAmount | currency }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Parciales</h5>
                    <p class="display-4">{{ fsc.report.partialCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Sin Conciliar</h5>
                    <p class="display-4">{{ fsc.report.unmatchedCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Tasa de Match</h5>
                    <p class="display-4">{{ (fsc.report.matchRate * 100) | number_format(1) }}%</p>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>UUID</th>
                    <th>Proveedor</th>
                    <th>Factura</th>
                    <th>Fecha</th>
                    <th>Total CFDI</th>
                    <th>Total Factura</th>
                    <th>Diferencia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            {% for item in fsc.report.items %}
                <tr>
                    <td><code>{{ item.uuid }}</code></td>
                    <td>{{ item.proveedorNombre }}</td>
                    <td>{{ item.numeroFactura }}</td>
                    <td>{{ item.fecha }}</td>
                    <td>{{ item.total | currency }}</td>
                    <td>{{ item.invoiceTotal | currency('—') }}</td>
                    <td>{{ item.difference | currency }}</td>
                    <td>
                        <span class="badge bg-{{ item.estado == 'matched' ? 'success' : (item.estado == 'partial' ? 'warning' : 'danger') }}">
                            {{ item.estado }}
                        </span>
                    </td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
    </div>
    {% endif %}
</div>
{% endblock %}
```

**Dependencias**: 3.1
**Criterio de aceptación**:
- Dashboard accesible desde menú CFDI
- Filtros por fecha y proveedor
- Estadísticas visuales

---

## FASE 4: Performance y Escalabilidad

**Duración estimada**: 1.5 semanas
**Prioridad**: P3 - Medio
**Objetivo**: Cola asíncrona y optimizaciones

### 4.1 Async Import Queue

**Archivos a crear**:
- `Lib/Application/Import/Queue/CfdiImportJob.php`
- `Lib/Application/Import/Queue/CfdiImportQueue.php`
- `Lib/Application/Import/Queue/AsyncImportProcessor.php`

**Tareas**:

#### CfdiImportJob.php
```php
class CfdiImportJob
{
    public int $id;
    public int $companyId;
    public int $userId;
    public string $status; // 'pending' | 'processing' | 'completed' | 'failed'
    public string $filePath;
    public ?string $result; // JSON
    public ?string $error;
    public int $progress; // 0-100
    public DateTime $createdAt;
    public DateTime $processedAt;
}
```

#### CfdiImportQueue.php
```php
class CfdiImportQueue
{
    public function enqueue(array $files, int $companyId, int $userId): string; // returns jobId
    public function dequeue(): ?CfdiImportJob;
    public function getStatus(string $jobId): CfdiImportJob;
    public function updateProgress(string $jobId, int $progress): void;
    public function complete(string $jobId, array $result): void;
    public function fail(string $jobId, string $error): void;
}
```

#### AsyncImportProcessor.php
```php
class AsyncImportProcessor
{
    public function __construct(
        SupplierCfdiImportService $importService,
        CfdiImportQueue $queue
    ) { }

    public function process(string $jobId): void
    {
        $job = $this->queue->dequeue($jobId);
        $job->status = 'processing';
        $this->queue->save($job);

        try {
            $result = $this->importService->importBatchFromFiles(
                $job->filePath,
                function ($current, $total) use ($job) {
                    $this->queue->updateProgress($job->id, ($current / $total) * 100);
                }
            );

            $this->queue->complete($job->id, $result);
        } catch (Exception $e) {
            $this->queue->fail($job->id, $e->getMessage());
        }
    }
}
```

**Tabla SQL**:
```sql
CREATE TABLE cfdi_import_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    file_path VARCHAR(255) NOT NULL,
    result TEXT,
    error TEXT,
    progress INTEGER DEFAULT 0,
    created_at DATETIME NOT NULL,
    processed_at DATETIME,
    UNIQUE(id)
);
```

**Dependencias**: 2.1
**Criterio de aceptación**:
- Jobs se procesan en background
- AJAX polling para status
- Timeout configurable

---

### 4.2 AJAX Progress Endpoint

**Archivos a modificar**:
- `Controller/ListCfdiProveedor.php`

```php
public function privateCore(...): void
{
    // ... existing code ...

    if ($action === 'import-status') {
        $this->importStatusAction();
        return;
    }
}

protected function importStatusAction(): void
{
    $jobId = $this->request->get('job_id');

    $queue = new CfdiImportQueue();
    $job = $queue->getStatus($jobId);

    $this->setTemplate(false);
    $this->response->setContent(json_encode([
        'status' => $job->status,
        'progress' => $job->progress,
        'result' => $job->result,
        'error' => $job->error
    ]));
}
```

---

### 4.3 Cache de Catálogos SAT

**Archivos a modificar**:
- `Lib/Domain/Catalogs/SatCatalogo.php`
- `Lib/Domain/Catalogs/SatCatalogBase.php`

```php
class SatCatalogBase
{
    private static ?array $cache = null;
    private static ?DateTime $cacheTime = null;
    private const CACHE_TTL_SECONDS = 3600; // 1 hour

    public static function getAll(): array
    {
        if (self::isCacheValid()) {
            return self::$cache;
        }

        self::$cache = self::loadFromFile();
        self::$cacheTime = new DateTime();

        return self::$cache;
    }

    private static function isCacheValid(): bool
    {
        if (self::$cache === null || self::$cacheTime === null) {
            return false;
        }

        $now = new DateTime();
        $diff = $now->getTimestamp() - self::$cacheTime->getTimestamp();

        return $diff < self::CACHE_TTL_SECONDS;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
        self::$cacheTime = null;
    }
}
```

**Dependencias**: Ninguna
**Criterio de aceptación**: Catálogos se cargan 1 vez por hora máximo

---

### 4.4 Product Search Optimization

**Archivos a modificar**:
- `Controller/CfdiSupplierWizard.php`
- `Assets/JS/Supplier/services/ProductSearchService.js`

```php
// CfdiSupplierWizard.php - Agregar índice de búsqueda
protected function searchProduct(): void
{
    $query = $this->request->input('query');
    $codproveedor = $this->request->input('codproveedor');

    $where = [
        Where::orLike('referencia', $query),
        Where::orLike('descripcion', $query),
    ];

    // Búsqueda priorizada: primero productos ya vinculados al proveedor
    $sql = '
        SELECT p.*,
            CASE WHEN pp.refproveedor IS NOT NULL THEN 1 ELSE 0 END as is_linked
        FROM productos p
        LEFT JOIN productos_proveedores pp ON p.referencia = pp.referencia AND pp.codproveedor = ?
        WHERE p.referencia LIKE ? OR p.descripcion LIKE ?
        ORDER BY is_linked DESC, p.descripcion ASC
        LIMIT 50
    ';

    $params = [$codproveedor, "%{$query}%", "%{$query}%"];
    $products = $this->dataBase->select($sql, $params);

    // ... rest of method
}
```

**Dependencias**: Ninguna
**Criterio de aceptación**: Búsqueda devuelve primero productos ya vinculados al proveedor actual

---

## Resumen de Entregables

| Fase | Archivos Nuevos | Archivos Modificados |
|------|-----------------|---------------------|
| 1 | 10 | 4 |
| 2 | 4 | 3 |
| 3 | 5 | 0 |
| 4 | 3 | 3 |
| **Total** | **22** | **10** |

---

## Tabla de Dependencias

```
FASE 1 ─────────────────────────────────────────┐
  1.1 CfdiQuickReader refactor ──────────────────┼──┐
  1.2 ProductMatchingService ─────────────────────┼──┼──┐
  1.3 ProductMappingStorage ─────────────────────┼──┼──┼──┐
  1.4 Wizard Update ──────────────────────────────┘  │  │  │
                                                    │  │  │
FASE 2 ─────────────────────────────────────────────┼──┘  │
  2.1 SupplierCfdiImportService ────────────────────┼─────┼───┐
  2.2 Batch Import UI ──────────────────────────────┼─────┼───┤
  2.3 Wizard Paso 4 ───────────────────────────────┘     │   │
                                                          │   │
FASE 3 ───────────────────────────────────────────────────┘   │
  3.1 ReconciliationService ─────────────────────────────────┼───┐
  3.2 Dashboard ──────────────────────────────────────────────┘   │
                                                              │   │
FASE 4 ─────────────────────────────────────────────────────────┘
  4.1 Async Queue ───────────────────────────────────────────────┐
  4.2 Progress Endpoint ────────────────────────────────────────┤
  4.3 SAT Catalog Cache ─────────────────────────────────────────┤
  4.4 Search Optimization ──────────────────────────────────────┘
```

---

## Estimación de Esfuerzo

| Fase |Historia| Puntos |
|------|--------|--------|
| 1 | Fundamentos y Product Matching | 13 |
| 2 | Importación Masiva y Configuración | 8 |
| 3 | Conciliación y Reportes | 5 |
| 4 | Performance y Escalabilidad | 5 |
| **Total** | | **31** |

**Sprint的建议**:
- Sprint 1: Fases 1.1 - 1.4 (2 semanas)
- Sprint 2: Fases 2.1 - 2.3 (2 semanas)
- Sprint 3: Fases 3.1 - 3.2 (1.5 semanas)
- Sprint 4: Fases 4.1 - 4.4 (1.5 semanas)

---

## Criterios de Aceptación Globales

1. **Tests**: Cada servicio nuevo tiene tests unitarios con >80% coverage
2. **Documentación**: PHPDoc completo en todas las clases públicas
3. **Backwards Compatibility**: No romper API existente de CfdiSupplierImporter
4. **Performance**: Import batch de 50 CFDIs < 30 segundos
5. **UX**: Wizard navegable con teclado, feedback visual en todos los pasos
