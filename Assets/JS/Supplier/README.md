# Módulo Supplier - Wizard CFDI Proveedor

## Arquitectura

Este módulo implementa el patrón **MVC (Model-View-Controller)** con **separación de responsabilidades** clara para el wizard de importación de CFDI de proveedor.

## Estructura de Directorios

```
Supplier/
├── services/          # Capa de datos (API calls)
│   └── ProductSearchService.js
├── models/            # Capa de estado (Store)
│   └── ProductLinkStore.js
├── views/             # Capa de presentación (UI)
│   ├── ProductSearchView.js
│   ├── ProductLinkView.js
│   └── FileInputView.js
└── controllers/       # Capa de lógica de negocio
    ├── ProductLinkController.js
    └── SupplierWizardController.js
```

## Componentes

### Services (Servicios)

#### ProductSearchService
**Responsabilidad**: Búsqueda de productos vía API

- Realiza peticiones HTTP al endpoint del servidor
- Maneja cancelación de peticiones con AbortController
- Gestiona errores y timeouts
- Devuelve datos limpios al controlador

**Métodos principales**:
- `searchProducts(query)`: Busca productos por texto
- `abort()`: Cancela búsqueda en curso

---

### Models (Modelos/Estado)

#### ProductLinkStore
**Responsabilidad**: Almacenar estado de vinculaciones concepto ↔ producto

- Mantiene un Map de vinculaciones
- Previene duplicados
- Proporciona métodos para agregar/remover/consultar
- Puede hidratarse desde el DOM existente

**Métodos principales**:
- `setLink(index, referencia)`: Vincula producto a concepto
- `removeLink(index)`: Desvincula producto
- `getLink(index)`: Obtiene vinculación
- `hasLink(index)`: Verifica si existe vinculación
- `hydrateFromDOM()`: Carga estado inicial desde inputs hidden

---

### Views (Vistas)

#### ProductSearchView
**Responsabilidad**: UI del modal de búsqueda y tabla de resultados

- Renderiza tabla de productos
- Gestiona estados: loading, error, empty, success
- Controla apertura/cierre del modal
- No contiene lógica de negocio, solo presentación

**Métodos principales**:
- `openModal()` / `closeModal()`: Control del modal
- `renderProductsTable(productos)`: Renderiza resultados
- `renderLoadingState()`: Muestra spinner
- `renderErrorState()`: Muestra error

#### ProductLinkView
**Responsabilidad**: UI de la tabla de conceptos y vinculaciones

- Actualiza celdas de referencia visible
- Actualiza inputs hidden del formulario
- Proporciona feedback visual (success/error)
- Gestiona estado de botones (vincular/desvincular)

**Métodos principales**:
- `updateReferenceCell(index, referencia)`: Actualiza celda
- `clearReferenceCell(index)`: Limpia celda
- `updateHiddenInput(index, referencia)`: Actualiza input
- `showSuccessFeedback(index)`: Feedback visual
- `updateButtonsState(index, hasLink)`: Actualiza botones

#### FileInputView
**Responsabilidad**: UI de inputs de tipo archivo

- Gestiona custom file inputs
- Actualiza labels con nombre de archivo
- Proporciona feedback visual
- Emite eventos de selección

**Métodos principales**:
- `connect()`: Inicializa event listeners
- `handleFileChange(event)`: Maneja cambio de archivo
- `resetAll()`: Resetea todos los inputs

---

### Controllers (Controladores)

#### ProductLinkController
**Responsabilidad**: Orquestación de búsqueda y vinculación de productos

- Coordina Service → Store → View
- Maneja eventos de usuario (vincular, desvincular, buscar)
- Implementa debouncing para búsqueda
- Valida acciones antes de ejecutarlas

**Flujo de vinculación**:
1. Usuario hace clic en "Vincular" → `onProductLinkOpen()`
2. Se abre modal y limpia búsqueda anterior
3. Usuario escribe en input → `onSearchInput()` (debounced)
4. Service busca productos → View renderiza resultados
5. Usuario selecciona producto → `onProductSelect()`
6. Store guarda vinculación
7. View actualiza UI (celda + input + botones)
8. Modal se cierra

**Métodos principales**:
- `connect()`: Registra eventos
- `onProductLinkOpen(el)`: Abre modal
- `onProductSelect(el)`: Vincula producto
- `onProductUnlink(el)`: Desvincula producto
- `performSearch()`: Ejecuta búsqueda

#### SupplierWizardController
**Responsabilidad**: Orquestación general del wizard

- Inicializa WizardController base
- Inicializa ProductLinkController
- Inicializa FileInputView
- Registra eventos globales
- Coordina entre todos los módulos

**Métodos principales**:
- `init()`: Inicializa todo el sistema
- `getState()`: Obtiene estado completo
- `reset()`: Resetea wizard
- `destroy()`: Limpia recursos

---

## Flujo de Datos

```
User Action (DOM)
    ↓
EventDispatcher (captura data-action)
    ↓
Controller (lógica de negocio)
    ↓
Service (petición HTTP)
    ↓
Controller (procesa respuesta)
    ↓
Store (actualiza estado)
    ↓
View (renderiza UI)
    ↓
DOM (actualización visual)
```

## Eventos

### Eventos del Wizard
- `wizard:initialized`: Wizard inicializado
- `wizard:step:changed`: Cambió de paso
- `wizard:validation:failed`: Validación fallida
- `wizard:submitted`: Formulario enviado
- `wizard:submit:error`: Error al enviar

### Eventos de Productos
- `product:linked`: Producto vinculado
- `product:unlinked`: Producto desvinculado
- `products:searched`: Búsqueda realizada

### Eventos de Archivos
- `file:selected`: Archivo seleccionado

## Acciones del EventDispatcher

Las siguientes acciones se capturan vía `data-action`:

- `wizard:step:next`: Siguiente paso
- `wizard:step:previous`: Paso anterior
- `wizard:submit`: Enviar formulario
- `product:link:open`: Abrir modal de vinculación
- `product:unlink`: Desvincular producto
- `product:select`: Seleccionar producto

## Ejemplo de Uso

```javascript
import { SupplierWizardController } from './Supplier/controllers/SupplierWizardController.js';

const wizard = new SupplierWizardController({
    totalSteps: 3,
    formId: 'formCfdiWizard',
    submitLabel: 'Importar',
    validationRules: { /* ... */ }
});

wizard.init();

// Obtener estado
const state = wizard.getState();
console.log('Productos vinculados:', state.productLinks.total);

// Resetear
wizard.reset();
```

## Ventajas de esta Arquitectura

✅ **Separación de Responsabilidades**: Cada clase tiene un propósito único y claro

✅ **Testeable**: Cada componente puede testearse de forma aislada

✅ **Reutilizable**: Los servicios y vistas pueden usarse en otros contextos

✅ **Mantenible**: Fácil localizar y modificar funcionalidad específica

✅ **Escalable**: Agregar features no afecta el código existente

✅ **Legible**: Código limpio y autodocumentado

✅ **Bajo Acoplamiento**: Los módulos se comunican vía interfaces claras

✅ **Alta Cohesión**: Código relacionado agrupado

## Comparación

### Antes (SupplierCfdiWizard.js monolítico)
- 260 líneas en un solo archivo
- Funciones globales mezcladas
- Difícil de testear
- Lógica de negocio + UI juntas

### Después (Arquitectura modular)
- 56 líneas en archivo principal
- 7 módulos especializados
- Cada módulo < 200 líneas
- Fácil de testear y mantener
- Separación clara de responsabilidades
