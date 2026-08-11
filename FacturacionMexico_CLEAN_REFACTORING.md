# Plan de reconstrucción — FacturacionMexico para FacturaScripts

## Objetivo

Reconstruir progresivamente el plugin `FacturacionMexico` para FacturaScripts, simplificando su arquitectura actual y concentrando la lógica de negocio dentro de `Lib/`.

Repositorio de referencia:

`https://github.com/jjosez/FacturacionMexico`

La nueva arquitectura debe priorizar:

- Simplicidad.
- Controladores delgados.
- Bajo boilerplate.
- Integración directa con los modelos y APIs de FacturaScripts cuando no exista una razón real para abstraerlos.
- Interfaces únicamente donde exista una frontera intercambiable o una necesidad clara.
- Compatibilidad y migración segura de instalaciones existentes.
- Código fácil de localizar y mantener.
- Evitar aplicar Clean Architecture, DDD o arquitectura hexagonal de manera estricta.
- Mantener abstracciones donde sí aportan valor, especialmente almacenamiento de XML, proveedores PAC y otras integraciones externas.

## Estado de ejecución

Base actual:

- Branch `clean-refactoring` creado desde `b44bcd9`, anterior a la refactorización previa.
- Wizards, vistas, XMLViews, modelos, tablas y extensiones conservados.
- Tests unitarios e integración local creados; matriz PAC/SAT sigue pendiente.

Completado:

- Estructura principal de `Lib` organizada en `Cfdi`, `Customer`, `Supplier`, `Document`, `Storage`, `Stamp`, `SAT`, `DTO` y `Exception`.
- Resultados movidos de `Adapters` a `DTO`.
- Parser existente movido a `Cfdi/CfdiParser`.
- Builders y validadores movidos a `Document`.
- Catálogos movidos a `SAT/Catalog`.
- Almacenamiento XML separado mediante `CfdiStorageInterface`.
- Creado `CfdiManager` y `CustomerCfdiRepository`.
- Eliminados `LegacyCfdiRepositoryInterface` y sus adaptadores.
- Compatibilidad de lectura con XML antiguos de filesystem conservada.
- `CfdiParser` produce `CfdiParsedData` para flujos de cliente y proveedor.
- `SupplierCfdiImporter` reemplaza el cargador anterior y persiste metadata desde el DTO.
- Lectura XML de vistas proveedor concentrada en `SupplierCfdiPreviewService`.
- Eliminados métodos sin consumidores internos del wizard y edición proveedor.
- Excepciones CFDI mínimas para validación, storage, PAC y configuración.
- Servicios SAT para certificados y consulta de estado, reutilizados por `CfdiManager`.
- Preparación de certificados de construcción y cancelación centralizada en `CertificateService`.
- Pruebas unitarias para parser, storage filesystem y resultados de timbrado.
- Pruebas integración local para storage database, XML filesystem legacy, importación proveedor y caminos exitoso/error de `CfdiManager` con PAC falso.

Pendiente:

- Validación funcional real de timbrado, cancelación, consulta SAT y almacenamiento.
- Revisar lógica XML residual en controladores y adelgazar sus responsabilidades.
- Confirmar compatibilidad de datos, UUID, relaciones y facturas existentes.
- Limpieza final de código muerto y documentación.
- Crear y ejecutar tests unitarios e integración.

---

# FASE 0 — Auditoría del plugin actual

Antes de modificar código, inspeccionar completamente el repositorio actual.

No asumir nombres de tablas, columnas, modelos ni relaciones.

## 0.1 Revisar estructura

Analizar especialmente:

- `Table/`
- `Model/`
- `Lib/`
- `Controller/`
- `Extension/`
- `XMLView/`
- `Init.php`
- migraciones existentes, si las hay.

Identificar también las dependencias externas utilizadas para CFDI, SAT, PAC, XML, certificados y FacturaScripts.

## 0.2 Inventario de modelos y tablas

Leer todas las definiciones existentes en `Table/*.xml`.

Crear una tabla de análisis con:

| Tabla actual | Modelo | Columna actual | Tipo | Uso actual | Nombre propuesto | Acción |
|---|---|---|---|---|---|---|
| ... | ... | ... | ... | ... | ... | KEEP / RENAME / REMOVE / ADD |

No cambiar columnas todavía.

Para cada columna determinar:

- si sigue siendo necesaria;
- si contiene información histórica importante;
- si el nombre es consistente;
- si está siendo utilizada por código PHP;
- si participa en relaciones;
- si está indexada;
- si tiene restricciones;
- si debería conservarse por compatibilidad;
- si conviene renombrarla.

## 0.3 Buscar usos reales

Antes de proponer un rename, buscar el uso de cada columna en todo el plugin:

- modelos;
- controladores;
- `Lib`;
- extensiones;
- vistas;
- XMLViews;
- JavaScript;
- tests;
- consultas SQL;
- filtros;
- exports/imports.

No renombrar una columna basándose únicamente en su definición XML.

## 0.4 Evaluar nomenclatura

Evaluar la conveniencia de estandarizar nombres hacia inglés.

Objetivo preferente para código nuevo:

- `CfdiCustomer`
- `CfdiSupplier`
- `CfdiRelation`
- `CfdiXml`

Tablas preferentes:

- `cfdi_customer`
- `cfdi_supplier`
- `cfdi_relation`
- `cfdi_xml`

Sin embargo, no imponer estos nombres automáticamente.

Primero determinar el costo de migración y compatibilidad.

---

# FASE 1 — Estrategia de compatibilidad y migración

## Inventario inicial de Lib

Este inventario se basa en la auditoría del branch `clean-refactoring`, creado desde `b44bcd9`. La primera iteración no modifica tablas, modelos, vistas ni wizards.

| Ubicación actual | Destino sugerido | Acción | Observación |
|---|---|---|---|
| `Lib/Domain/CfdiFactory.php` | `Lib/Document/` | MOVE | Coordina builders de documentos CFDI. |
| `Lib/Domain/Builder/*` | `Lib/Document/` | MOVE | Mantener un builder por tipo real de documento. |
| `Lib/Domain/Middleware/*` | `Lib/Document/Validation/` | MOVE | Son validadores de construcción; no crear una capa genérica adicional. |
| `Lib/Domain/CfdiSettings.php` | `Lib/CfdiSettings.php` | MOVE | Mantener acceso simple a la configuración de FacturaScripts. |
| `Lib/Domain/Catalogs/*` | `Lib/SAT/Catalog/` | MOVE | Catálogos SAT, sin duplicar consultas existentes. |
| `Lib/Domain/CfdiCatalogo.php` | `Lib/SAT/` | MOVE | Revisar si es fachada de catálogos antes de dividirlo. |
| `Lib/Infrastructure/XML/CfdiQuickReader.php` | `Lib/Cfdi/CfdiParser.php` | MERGE/MOVE | Reutilizar el parser actual; no crear dos lectores XML. |
| `Lib/Infrastructure/StampProviders/FinkokStampProvider.php` | `Lib/Stamp/` | MOVE | Mantener `StampProviderInterface` como frontera externa. |
| `Lib/Infrastructure/Persistence/CfdiFileStorage.php` | `Lib/Storage/FileCfdiStorage.php` | MOVE | Adaptar al contrato pequeño de almacenamiento XML. |
| `Lib/Infrastructure/Persistence/CfdiDatabaseStorage.php` | `Lib/Storage/DatabaseCfdiStorage.php` | MOVE | Confirmar primero si la persistencia database está soportada en instalaciones reales. |
| `Lib/Domain/Contracts/CfdiRepositoryInterface.php` | `Lib/Storage/` | REPLACE | No conservar como interfaz genérica si solo representa almacenamiento XML. |
| `Lib/Adapters/*` | `Lib/DTO/` | MOVE | Solo para resultados que crucen límites importantes. |
| `Lib/Exception/*` | `Lib/Exception/` | KEEP | Revisar si cada excepción sigue siendo necesaria. |
| `Lib/Application/CfdiService.php` y relacionados | `Lib/Customer/` | MOVE | Mantener timbrado, cancelación y relaciones como casos de uso. |
| `Lib/Application/SupplierCfdi*` | `Lib/Supplier/` | MOVE | Mantener carga, importación y estados CFDI proveedor. |
| `Lib/Application/Import/*` | `Lib/Supplier/` | MERGE/MOVE | Factura proveedor forma parte del flujo de CFDI recibido; evitar otro subcontexto artificial. |
| `Lib/Application/DeliveryNoteInvoiceGenerator.php` | `Lib/Document/` | MOVE | Caso de uso de documento, no de CFDI directamente. |

### Elementos conservados inicialmente

- `Table/`, `Model/`, `Extension/`, `Controller/`, `View/` y `XMLView/`.
- Nombres de tablas `cfdis_clientes`, `cfdis_proveedores` y sus tablas relacionadas.
- Columnas, UUID, relaciones con empresas, clientes, proveedores y facturas.
- Wizards y sus pasos actuales.

### Sugerencias adoptadas

- `CfdiStorageInterface` reemplaza la interfaz genérica de repositorio para XML.
- Los modelos FacturaScripts se usan directamente desde `Customer/` y `Supplier/` cuando no exista una frontera intercambiable.
- La cola se mantiene dentro de `Supplier/Queue/` solo mientras exista el flujo asíncrono actual.
- No se crean wrappers de compatibilidad salvo que haya consumidores externos reales.

Estado de la migración de Storage:

- `Lib/Storage/CfdiStorageInterface.php` ya define el contrato mínimo de XML.
- `Lib/Storage/FileCfdiStorage.php` implementa el almacenamiento nuevo por UUID.
- `Lib/Cfdi/CfdiParser.php` reutiliza la implementación existente de lectura XML.
- `Lib/Stamp/StampProviderInterface.php` y `Lib/Stamp/FinkokStampProvider.php` concentran la integración PAC.
- El flujo existente de importación proveedor se está concentrando en `Lib/Supplier/`, con la cola en `Lib/Supplier/Queue/`.
- `Lib/Domain/` y `Lib/Application/` quedan vacíos después de la reorganización inicial.
- `Lib/Infrastructure/` se conserva únicamente para integraciones externas, PDF y adaptadores legacy pendientes de reemplazo.
- Los adaptadores anteriores fueron eliminados después de migrar sus consumidores a `CfdiManager`.
- `DatabaseCfdiStorage` implementa el contrato usando `cfdis_clientes_data` y el UUID del metadata CFDI.

Después de la auditoría, presentar una propuesta antes de realizar cambios destructivos.

Clasificar cada cambio como:

### KEEP

Conservar nombre y estructura actual.

Usarlo cuando cambiarlo no aporte suficiente valor.

### RENAME

Renombrar cuando el nombre nuevo mejore significativamente consistencia y mantenibilidad.

Debe incluir migración.

### REMOVE

Eliminar solamente campos demostrablemente obsoletos.

Nunca eliminar directamente información existente sin una estrategia de migración.

### ADD

Agregar nuevos campos requeridos por la arquitectura nueva.

---

# FASE 2 — Migraciones

Si se decide estandarizar nombres de columnas o tablas, crear migraciones explícitas.

La migración debe:

1. Detectar instalaciones existentes.
2. Preservar todos los datos.
3. Renombrar/copiar columnas de forma segura.
4. Mantener UUID y relaciones.
5. Mantener referencias a facturas, clientes, proveedores y empresas.
6. Crear nuevos índices necesarios.
7. Evitar duplicar CFDI.
8. Poder ejecutarse sobre una instalación existente del plugin.

Cuando un rename directo no sea suficientemente seguro, utilizar:

```text
ADD nueva_columna
        ↓
COPY antigua → nueva
        ↓
actualizar código
        ↓
validar
        ↓
REMOVE antigua
```

No realizar migraciones destructivas innecesarias.

---

# FASE 3 — Modelo de persistencia CFDI

Separar claramente:

## Metadata CFDI

Persistida mediante modelos normales de FacturaScripts.

Principalmente:

```text
cfdi_customer
cfdi_supplier
```

y posteriormente:

```text
cfdi_relation
```

## XML CFDI

Persistido mediante una estrategia independiente:

```text
CfdiStorageInterface
```

El modelo CFDI no debe conocer si el XML está almacenado en filesystem o base de datos.

---

# FASE 4 — CfdiStorage

Crear:

```text
Lib/
└── Storage/
    ├── CfdiStorageInterface.php
    ├── CfdiStorage.php
    ├── FileCfdiStorage.php
    └── DatabaseCfdiStorage.php
```

Contrato inicial:

```php
interface CfdiStorageInterface
{
    public function save(string $uuid, string $xml): string;

    public function get(string $uuid): ?string;

    public function exists(string $uuid): bool;

    public function delete(string $uuid): bool;
}
```

Revisar este contrato durante la implementación y mantenerlo pequeño.

No agregar métodos especulativos.

## FileCfdiStorage

Debe encargarse internamente de convertir el UUID/storage key en una ruta física.

Por ejemplo:

```text
MyFiles/FacturacionMexico/cfdi/2026/08/{UUID}.xml
```

La estructura exacta debe adaptarse a las convenciones disponibles en FacturaScripts.

## DatabaseCfdiStorage

Crear una tabla independiente si resulta necesaria:

```text
cfdi_xml
```

Posible estructura:

```text
id
uuid
xml
created_at
updated_at
```

Revisar los tipos de datos apropiados para FacturaScripts/MySQL antes de implementarla.

No almacenar innecesariamente XML grandes directamente dentro de `cfdi_customer` o `cfdi_supplier`.

---

# FASE 5 — Estructura nueva de Lib

Objetivo aproximado:

```text
Lib/
├── Cfdi/
│   ├── CfdiManager.php
│   ├── CfdiBuilder.php
│   ├── CfdiParser.php
│   └── CfdiValidator.php
│
├── Document/
│   ├── InvoiceCfdiBuilder.php
│   ├── CreditNoteCfdiBuilder.php
│   ├── GlobalInvoiceCfdiBuilder.php
│   └── PaymentCfdiBuilder.php
│
├── Storage/
│   ├── CfdiStorageInterface.php
│   ├── CfdiStorage.php
│   ├── FileCfdiStorage.php
│   └── DatabaseCfdiStorage.php
│
├── Stamp/
│   ├── StampProviderInterface.php
│   ├── StampProvider.php
│   └── FinkokStampProvider.php
│
├── SAT/
│   ├── SatStatusService.php
│   ├── CertificateService.php
│   └── Catalog/
│
├── Customer/
│   └── CustomerCfdiService.php
│
├── Supplier/
│   └── SupplierCfdiImporter.php
│
├── DTO/
│   ├── CfdiData.php
│   ├── CfdiBuildResult.php
│   ├── CfdiStampResult.php
│   ├── CfdiImportResult.php
│   └── StampResult.php
│
├── Exception/
│   ├── CfdiException.php
│   ├── CfdiValidationException.php
│   ├── CfdiStorageException.php
│   └── CfdiStampException.php
│
└── CfdiSettings.php
```

Esta estructura es orientativa.

No crear archivos vacíos únicamente para satisfacer la estructura.

Crear una clase solamente cuando exista una responsabilidad real.

---

# FASE 6 — Reducir arquitectura actual

Analizar las carpetas actuales:

```text
Application/
Domain/
Infrastructure/
Adapters/
```

y migrar gradualmente sus responsabilidades.

No hacer un borrado masivo.

Para cada clase actual:

1. determinar qué hace;
2. determinar quién la utiliza;
3. decidir si:
   - se conserva;
   - se mueve;
   - se simplifica;
   - se fusiona;
   - se reemplaza;
   - se elimina;
4. migrar consumidores;
5. eliminar la clase antigua solamente cuando ya no tenga referencias.

Evitar mantener dos arquitecturas funcionando indefinidamente.

---

# FASE 7 — Parser CFDI

Crear una responsabilidad clara:

```text
CfdiParser
```

Entrada:

```php
string $xml
```

Salida:

```text
CfdiData
```

`CfdiParser` solamente interpreta XML.

No debe:

- guardar modelos;
- crear proveedores;
- escribir archivos;
- llamar al PAC;
- modificar facturas.

`CfdiData` debe representar los datos relevantes encontrados en el comprobante.

Antes de definir sus propiedades definitivas, revisar qué información ya almacenan las tablas actuales.

---

# FASE 8 — Importación de CFDI recibidos

Crear:

```text
SupplierCfdiImporter
```

Flujo:

```text
XML
 ↓
CfdiParser
 ↓
CfdiValidator
 ↓
comprobar UUID
 ↓
localizar/crear proveedor
 ↓
CfdiStorage
 ↓
CfdiSupplier
```

Debe aprovechar directamente los modelos de FacturaScripts.

No crear `SupplierRepositoryInterface` salvo que aparezca una necesidad real.

---

# FASE 9 — Construcción de CFDI emitidos

Separar construcción por tipo de documento cuando exista suficiente diferencia entre ellos.

Posibles builders:

```text
InvoiceCfdiBuilder
CreditNoteCfdiBuilder
GlobalInvoiceCfdiBuilder
PaymentCfdiBuilder
```

No crear builders artificiales para tipos que todavía no estén implementados.

El builder debe producir un resultado como:

```text
CfdiBuildResult
```

El builder construye.

No timbra.

No almacena.

No actualiza directamente el estado del CFDI.

---

# FASE 10 — PAC / timbrado

Mantener una frontera:

```text
StampProviderInterface
```

porque representa una integración externa potencialmente intercambiable.

Ejemplo:

```text
StampProviderInterface
        │
        ├── FinkokStampProvider
        └── futuro PAC
```

Evitar que código específico de Finkok se filtre hacia `CfdiManager`, builders o controladores.

El resultado debe normalizarse mediante:

```text
StampResult
```

o equivalente.

---

# FASE 11 — CfdiManager

Crear `CfdiManager` como fachada/orquestador de las operaciones CFDI emitidas.

Responsabilidades posibles:

```php
stamp(...)
cancel(...)
status(...)
getXml(...)
```

`CfdiManager` coordina.

No debe implementar internamente toda la lógica.

Flujo conceptual:

```text
FacturaCliente
      ↓
CfdiManager
      ↓
CfdiBuilder
      ↓
XML sin timbrar
      ↓
StampProvider
      ↓
XML timbrado
      ↓
CfdiStorage
      ↓
CfdiCustomer
```

El código del flujo debe permanecer fácil de seguir.

---

# FASE 12 — Controladores delgados

Los controladores deben limitarse principalmente a:

- recibir/verificar request;
- obtener modelos FacturaScripts;
- llamar clases de `Lib`;
- transformar resultado en respuesta;
- mostrar mensajes;
- redireccionar.

Evitar:

```text
Controller
 ├── parse XML
 ├── construir CFDI
 ├── validar SAT
 ├── llamar PAC
 ├── guardar XML
 ├── crear modelos
 └── resolver relaciones
```

Preferir:

```php
$result = (new CfdiManager())->stamp($invoice);
```

y manejar el resultado.

---

# FASE 13 — Política de interfaces

No crear interfaces por defecto.

Crear una interfaz solamente cuando:

1. existan múltiples implementaciones reales;
2. represente una integración externa;
3. sea una frontera que realmente necesite intercambiarse.

Interfaces inicialmente justificadas:

```text
CfdiStorageInterface
StampProviderInterface
```

Posiblemente:

```text
CfdiBuilderInterface
```

pero solamente si los builders necesitan realmente intercambiarse mediante un contrato común.

Evitar inicialmente:

```text
CfdiRepositoryInterface
CustomerRepositoryInterface
SupplierRepositoryInterface
CfdiParserInterface
CfdiValidatorInterface
CfdiSettingsInterface
```

Los modelos FacturaScripts pueden utilizarse directamente.

---

# FASE 14 — Resultados y DTO

Conservar DTO pequeños cuando eviten arrays ambiguos.

Ejemplos:

```text
CfdiData
CfdiBuildResult
CfdiStampResult
CfdiImportResult
StampResult
CfdiSatStatus
ValidationResult
```

No convertir cada conjunto de dos valores en un DTO.

Usarlos principalmente en límites entre operaciones importantes.

---

# FASE 15 — Excepciones

Mantener pocas excepciones y con significado claro:

```text
CfdiException
CfdiValidationException
CfdiStorageException
CfdiStampException
```

No crear una jerarquía excesiva.

Errores esperables del negocio pueden representarse mediante objetos Result cuando resulte más práctico.

Excepciones deben reservarse principalmente para situaciones excepcionales o fallos de infraestructura.

---

# FASE 16 — Settings

Centralizar acceso a configuración relevante en:

```text
CfdiSettings
```

pero mantenerlo simple.

Ejemplos:

```php
CfdiSettings::storage();
CfdiSettings::stampProvider();
CfdiSettings::creditNoteSerie();
```

Debe encapsular las claves reales de configuración para evitar strings duplicados por todo el plugin.

No convertirlo en un sistema de configuración independiente de FacturaScripts.

---

# FASE 17 — Pruebas y validación durante la migración

Después de cada bloque funcional comprobar:

### CFDI emitidos

- factura normal;
- nota de crédito;
- factura global;
- relaciones CFDI;
- impuestos;
- descuentos;
- timbrado;
- recuperación XML;
- cancelación;
- consulta SAT.

### CFDI recibidos

- importación XML;
- UUID duplicado;
- proveedor existente;
- proveedor nuevo;
- XML inválido;
- CFDI cancelado;
- almacenamiento filesystem;
- almacenamiento database.

### Compatibilidad

Especialmente comprobar instalaciones que ya tengan datos.

Verificar que:

```text
UUID anterior == UUID posterior
factura relacionada permanece relacionada
cliente permanece relacionado
proveedor permanece relacionado
XML continúa recuperable
```

---

# FASE 18 — Limpieza final

Cuando la arquitectura nueva esté funcionando:

- buscar referencias a clases antiguas;
- eliminar factories innecesarias;
- eliminar interfaces sin múltiples implementaciones;
- eliminar namespaces antiguos;
- eliminar código muerto;
- actualizar `use`;
- actualizar documentación;
- comprobar autoload;
- comprobar estándares FacturaScripts;
- ejecutar tests disponibles.

No dejar wrappers que únicamente redirijan a otra clase salvo que sean necesarios temporalmente para compatibilidad.

---

# Reglas arquitectónicas

Durante toda la reconstrucción aplicar estas reglas:

### Regla 1

FacturaScripts es una dependencia deliberada.

No intentar abstraer FacturaScripts fuera del plugin.

### Regla 2

Preferir:

```php
$cfdi = new CfdiCustomer();
```

antes que crear:

```text
CfdiRepositoryInterface
    ↓
FacturaScriptsCfdiRepository
    ↓
CfdiCustomer
```

si no existe una necesidad real.

### Regla 3

Una clase debe justificar su existencia por responsabilidad, no por patrón arquitectónico.

### Regla 4

Evitar factories salvo que exista realmente lógica de selección de implementaciones.

### Regla 5

Mantener métodos pequeños y flujos legibles.

### Regla 6

No crear boilerplate anticipando requisitos futuros hipotéticos.

### Regla 7

Las integraciones externas sí deben mantenerse desacopladas.

Especialmente:

```text
Storage
PAC
SAT
Email
```

cuando corresponda.

### Regla 8

Nunca realizar un rename de tablas/columnas sin analizar datos existentes y consumidores.

### Regla 9

No cambiar simultáneamente arquitectura, schema y comportamiento funcional cuando pueda hacerse incrementalmente.

### Regla 10

Priorizar código idiomático de FacturaScripts sobre patrones genéricos de frameworks externos.

---

# Orden recomendado de implementación

Trabajar en este orden:

```text
1. Auditoría
        ↓
2. Inventario Table/Model
        ↓
3. Mapa de columnas actuales → propuestas
        ↓
4. Decisión KEEP/RENAME/REMOVE/ADD
        ↓
5. Plan de migración
        ↓
6. CfdiCustomer / CfdiSupplier
        ↓
7. CfdiStorageInterface
        ↓
8. FileCfdiStorage
        ↓
9. DatabaseCfdiStorage
        ↓
10. CfdiParser + CfdiData
        ↓
11. SupplierCfdiImporter
        ↓
12. Builders CFDI emitidos
        ↓
13. StampProvider
        ↓
14. CfdiManager
        ↓
15. Relaciones CFDI
        ↓
16. SAT / cancelación
        ↓
17. Migración de controladores
        ↓
18. Eliminación arquitectura antigua
        ↓
19. Tests
        ↓
20. Limpieza final
```

# Primera tarea para GPT-5.6

NO comenzar escribiendo la nueva arquitectura.

Primero realizar exclusivamente la auditoría.

1. Leer `Table/*.xml`.
2. Leer los modelos correspondientes.
3. Identificar todas las tablas propias del plugin.
4. Documentar todos sus campos.
5. Buscar dónde se utiliza cada campo.
6. Comparar los nombres actuales con la nomenclatura propuesta.
7. Identificar relaciones con modelos nativos de FacturaScripts.
8. Identificar dónde y cómo se almacena actualmente el XML.
9. Identificar las clases actuales de `Lib` responsables de:
   - construcción;
   - timbrado;
   - almacenamiento;
   - importación;
   - cancelación;
   - SAT;
   - certificados;
   - relaciones.
10. Proponer el mapa de migración.

Entregar antes de modificar código:

```text
A. Estado actual
B. Tablas/modelos actuales
C. Mapa de columnas
D. Dependencias entre clases
E. Problemas encontrados
F. Elementos que merece la pena conservar
G. Elementos que deberían simplificarse
H. Propuesta de schema final
I. Tabla KEEP / RENAME / REMOVE / ADD
J. Plan de migración de datos
K. Riesgos de compatibilidad
L. Primera fase concreta de implementación
```

No realizar todavía renames ni eliminar clases.

Esperar aprobación del plan de migración antes de comenzar cambios destructivos.
