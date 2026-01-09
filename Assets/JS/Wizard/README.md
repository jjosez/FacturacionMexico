# Wizard Framework - Arquitectura MVC

Sistema de wizard modular basado en la arquitectura del plugin POS, con patrón MVC, gestión de eventos y validación configurable.

## 📁 Estructura

```
Wizard/
├── core/
│   ├── EventDispatcher.js   # Manejo de eventos DOM (data-action)
│   └── EventManager.js       # Sistema pub/sub para eventos personalizados
├── models/
│   └── WizardModel.js        # Lógica de negocio y estado
├── views/
│   ├── TemplateManager.js    # Renderizado de plantillas con Eta
│   └── WizardView.js         # Gestión de UI y feedback visual
└── controllers/
    └── WizardController.js   # Coordinador entre modelo y vista
```

## 🚀 Uso Básico

### 1. Importar dependencias

```javascript
import WizardController from './Wizard/controllers/WizardController.js';
import eventDispatcher from './Wizard/core/EventDispatcher.js';
import eventManager from './Wizard/core/EventManager.js';
```

### 2. Configurar reglas de validación

```javascript
const validationRules = {
    1: [
        {
            field: 'codcliente',
            required: true,
            message: 'El cliente es obligatorio'
        }
    ],
    2: [
        {
            field: 'email',
            required: true,
            message: 'El email es obligatorio',
            validator: (value) => {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return {
                    valid: emailRegex.test(value),
                    message: 'Email inválido'
                };
            }
        }
    ]
};
```

### 3. Inicializar el wizard

```javascript
const wizard = new WizardController({
    totalSteps: 3,
    formId: 'formCfdiWizard',
    submitLabel: 'Timbrar',
    validationRules: validationRules,
    viewConfig: {
        tabsSelector: '#wizardTabs .nav-link',
        panesSelector: '.tab-pane',
        prevBtnId: 'prevBtn',
        nextBtnId: 'nextBtn',
        submitBtnId: 'wizardSubmitBtn'
    }
});

// Activar EventDispatcher
eventDispatcher.listen();

// Inicializar
wizard.init();
```

## 🎯 Nomenclatura de Eventos

### Eventos DOM (data-action)

Usa el atributo `data-action` en tus elementos HTML:

```html
<!-- Navegación -->
<button data-action="wizard:step:next">Siguiente</button>
<button data-action="wizard:step:previous">Anterior</button>
<button data-action="wizard:step:goto" data-step="2">Ir al paso 2</button>
<button data-action="wizard:submit" data-confirm-message="¿Enviar?">Enviar</button>
<button data-action="wizard:reset">Reiniciar</button>

<!-- Productos -->
<button data-action="product:select" data-referencia="REF001">Seleccionar</button>
<button data-action="product:link:open" data-index="1">Vincular</button>
<button data-action="product:unlink" data-index="1">Desvincular</button>

<!-- Campos del formulario -->
<input data-action="wizard:field:update" data-field="codcliente" />
```

### Eventos personalizados (EventManager)

Escucha eventos del sistema:

```javascript
// Wizard inicializado
eventManager.on('wizard:initialized', (model) => {
    console.log('Wizard listo', model);
});

// Cambio de paso
eventManager.on('wizard:step:changed', (data) => {
    console.log('Nuevo paso:', data.step, 'Dirección:', data.direction);
});

// Validación fallida
eventManager.on('wizard:validation:failed', (result) => {
    console.warn('Errores:', result.errors);
});

// Formulario enviado
eventManager.on('wizard:submitted', (data) => {
    console.log('Datos enviados:', data);
});

// Error al enviar
eventManager.on('wizard:submit:error', (error) => {
    console.error('Error:', error);
});

// Datos actualizados
eventManager.on('wizard:data:updated', (data) => {
    console.log('Campo actualizado:', data.key, data.value);
});
```

## 🎨 Uso de TemplateManager

### 1. Definir plantillas en HTML

```html
<script type="text/template" id="product:list:template">
<% it.products.forEach(product => { %>
    <div class="product-item">
        <h3><%= product.name %></h3>
        <button data-action="product:select" data-code="<%= product.code %>">
            Seleccionar
        </button>
    </div>
<% }); %>
</script>
```

### 2. Inicializar TemplateManager

```javascript
import templateManager from './Wizard/views/TemplateManager.js';
import {Eta} from '../path/to/eta.js';

// Inicializar motor Eta
await templateManager.initEngine(Eta);

// Cargar plantillas del DOM
templateManager.preloadTemplatesFromDOM();
```

### 3. Renderizar plantillas

```javascript
// Renderizar en un contenedor
templateManager.render('product:list:template', {
    products: [
        {name: 'Producto 1', code: 'P001'},
        {name: 'Producto 2', code: 'P002'}
    ]
}, 'product-list-container');

// Obtener HTML como string
const html = templateManager.renderToString('product:list:template', {
    products: []
});
```

## 🔧 Características Avanzadas

### Validación personalizada

```javascript
{
    field: 'password',
    required: true,
    validator: (value, element) => {
        if (value.length < 8) {
            return {valid: false, message: 'Mínimo 8 caracteres'};
        }
        return {valid: true};
    }
}
```

### Callback personalizado de envío

```javascript
const wizard = new WizardController({
    // ... configuración
    onSubmit: async (data, form) => {
        // Lógica personalizada antes de enviar
        const result = await myCustomSubmit(data);
        if (result.success) {
            window.location.href = '/success';
        }
    }
});
```

### Registrar eventos personalizados

```javascript
eventDispatcher.register('cart:product:add', (el) => {
    const {code, description} = el.dataset;
    // Lógica personalizada
});
```

### Emitir eventos personalizados

```javascript
eventManager.emit('custom:event', {data: 'value'});
```

## 📝 Ejemplos Completos

Ver archivos de implementación:
- `CustomerCfdiWizard.js` - Wizard para CFDIs de cliente
- `SupplierCfdiWizard.js` - Wizard para CFDIs de proveedor con búsqueda de productos

## 🔍 Debugging

Activa el modo debug para ver logs en consola:

```javascript
import eventDispatcher from './Wizard/core/EventDispatcher.js';
import eventManager from './Wizard/core/EventManager.js';

eventDispatcher.debug = true;
eventManager.debug = true;
```

## 🎯 Patrón de nombres

Sigue la convención de nomenclatura del plugin POS:

- **Namespace:Entidad:Acción** para eventos DOM
  - `wizard:step:next`
  - `cart:product:add`
  - `product:select`

- **entidad:acción** para eventos personalizados
  - `wizard:initialized`
  - `products:searched`
  - `file:selected`

## 🚨 Notas Importantes

1. **EventDispatcher.listen()** debe llamarse solo UNA vez
2. Las plantillas deben tener `type="text/template"` para no renderizarse
3. Los IDs de botones deben coincidir con la configuración del viewConfig
4. Bootstrap 5 es requerido para tabs y modales
5. Eta es opcional, solo si usas TemplateManager
