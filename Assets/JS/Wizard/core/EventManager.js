/**
 * EventManager — Sistema de eventos personalizados (pub/sub).
 * Permite comunicación entre componentes sin acoplamiento directo.
 */
class EventManager {
    constructor() {
        this.events = {};
        this.debug = false;
    }

    /**
     * Registra un listener para un evento
     * @param {string} event - Nombre del evento
     * @param {Function} listener - Función callback
     */
    on(event, listener) {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        if (!this.events[event].includes(listener)) {
            this.events[event].push(listener);
        }
    }

    /**
     * Emite un evento con argumentos
     * @param {string} event - Nombre del evento
     * @param {...*} args - Argumentos a pasar al listener
     */
    emit(event, ...args) {
        if (!this.events[event]) return;
        if (this.debug) {
            console.log(`📢 Emitting: ${event}`, ...args);
            console.trace();
        }
        this.events[event].forEach(listener => listener(...args));
    }

    /**
     * Elimina un listener específico de un evento
     * @param {string} event - Nombre del evento
     * @param {Function} listener - Listener a eliminar
     */
    off(event, listener) {
        if (!this.events[event]) return;
        this.events[event] = this.events[event].filter(l => l !== listener);
    }

    /**
     * Limpia todos los listeners de un evento o todos si no se especifica
     * @param {string} [event] - Evento específico a limpiar
     */
    clear(event) {
        if (event) {
            delete this.events[event];
        } else {
            this.events = {};
        }
    }

    /**
     * Registra un listener que solo se ejecuta una vez
     * @param {string} event - Nombre del evento
     * @param {Function} listener - Función callback
     */
    once(event, listener) {
        const onceWrapper = (...args) => {
            listener(...args);
            this.off(event, onceWrapper);
        };
        this.on(event, onceWrapper);
    }
}

const eventManager = new EventManager();
eventManager.debug = false;
export default eventManager;
