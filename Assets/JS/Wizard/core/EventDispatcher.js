/**
 * EventDispatcher — Control centralizado de eventos basado en `data-action`.
 * Maneja eventos del DOM mediante delegación de eventos.
 */
class EventDispatcher {
    constructor() {
        this.listeners = {};
        this._listening = false;
        this.debug = false;
    }

    /**
     * Registra una acción y su función asociada
     * @param {string} action - Nombre de la acción (ej. "wizard:step:next")
     * @param {Function} handler - Función que se ejecuta al hacer clic
     */
    register(action, handler) {
        if (!this.listeners[action]) {
            this.listeners[action] = [];
        }
        if (!this.listeners[action].includes(handler)) {
            this.listeners[action].push(handler);
        }
    }

    /**
     * Despacha una acción a todos los handlers registrados
     * @param {string} action - Nombre de la acción
     * @param {HTMLElement} el - Elemento que disparó el evento
     */
    dispatch(action, el) {
        if (this.listeners[action]) {
            this.listeners[action].forEach(handler => handler(el));
        } else {
            if (this.debug) console.warn(`⚠️ No handler registered for action: ${action}`);
        }
    }

    /**
     * Inicializa el listener global (una sola vez)
     */
    listen() {
        if (this._listening) return;
        this._listening = true;

        document.addEventListener('click', (event) => {
            const action = event.target.dataset.action;
            if (action) {
                this.dispatch(action, event.target);
            }
        });
    }

    /**
     * Elimina un handler específico de una acción
     * @param {string} action - Nombre de la acción
     * @param {Function} handler - Handler a eliminar
     */
    unregister(action, handler) {
        if (!this.listeners[action]) return;
        this.listeners[action] = this.listeners[action].filter(h => h !== handler);
    }

    /**
     * Limpia todos los listeners de una acción o todos si no se especifica
     * @param {string} [action] - Acción específica a limpiar
     */
    clear(action) {
        if (action) {
            delete this.listeners[action];
        } else {
            this.listeners = {};
        }
    }
}

const dispatcher = new EventDispatcher();
dispatcher.debug = false;
export default dispatcher;
