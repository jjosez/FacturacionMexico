export class CfdiWizard {
    /**
     * @param {string} wizardSubmitBtn - ID del botón final (por defecto 'finalStepBtn')
     * @param {string} wizardFormId - ID del formulario principal del wizard
     */
    constructor(wizardSubmitBtn = 'wizardSubmitBtn', wizardFormId = 'formCfdiWizard') {
        this.tabs = document.querySelectorAll('#wizardTabs .nav-link');
        this.panes = document.querySelectorAll('.tab-pane');
        this.prevBtn = document.getElementById('prevBtn');
        this.nextBtn = document.getElementById('nextBtn');
        this.finalBtn = document.getElementById(wizardSubmitBtn);
        this.currentStep = 1;
        this.totalSteps = this.tabs.length;
        this.wizardFormId = wizardFormId;
        this.finalAction = null;
    }

    /*init(finalLabel = 'Finalizar', finalCallback = null) {
        this.showStep(this.currentStep);

        this.prevBtn.addEventListener('click', () => this.prev());
        this.nextBtn.addEventListener('click', () => this.next());

        if (this.finalBtn) {
            this.finalBtn.textContent = finalLabel;
            this.finalAction = finalCallback;

            // Clonar botón para evitar handlers duplicados
            const newFinalBtn = this.finalBtn.cloneNode(true);
            this.finalBtn.parentNode.replaceChild(newFinalBtn, this.finalBtn);
            this.finalBtn = newFinalBtn;

            if (this.finalAction) {
                this.finalBtn.addEventListener('click', () => {
                    this.finalAction();
                });
            }
        }

        // Solo asocia confirmación al form principal
        const wizardForm = document.getElementById(this.wizardFormId);
        if (wizardForm) {
            wizardForm.addEventListener('submit', e => this.confirmSubmit(e, wizardForm));
        }

        // Bloquea navegación directa por los tabs
        this.tabs.forEach(tab => {
            tab.addEventListener('click', e => e.preventDefault());
        });
    }*/
    init(finalLabel = 'Finalizar') {
        this.showStep(this.currentStep);

        this.prevBtn.addEventListener('click', () => this.prev());
        this.nextBtn.addEventListener('click', () => this.next());

        if (this.finalBtn) {
            this.finalBtn.textContent = finalLabel;

            // Clonar botón para evitar handlers duplicados
            const newFinalBtn = this.finalBtn.cloneNode(true);
            this.finalBtn.parentNode.replaceChild(newFinalBtn, this.finalBtn);
            this.finalBtn = newFinalBtn;

            this.finalBtn.addEventListener('click', () => {
                const form = document.getElementById(this.wizardFormId);
                this.confirmSubmit(new Event('submit'), form);
            });
        }

        // Bloquea navegación directa por los tabs
        this.tabs.forEach(tab => {
            tab.addEventListener('click', e => e.preventDefault());
        });
    }

    showStep(step) {
        this.tabs.forEach((tab, i) => {
            tab.classList.toggle('active', i === step - 1);
        });
        this.panes.forEach((pane, i) => {
            pane.classList.toggle('show', i === step - 1);
            pane.classList.toggle('active', i === step - 1);
        });

        this.prevBtn.disabled = (step === 1);
        this.nextBtn.classList.toggle('d-none', step === this.totalSteps);
        if (this.finalBtn) {
            this.finalBtn.classList.toggle('d-none', step !== this.totalSteps);
        }
    }

    next() {
        if (this.validateStep(this.currentStep)) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.showStep(this.currentStep);
            }
        }
    }

    prev() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }

    validateStep(step) {
        if (step === 1) {
            const codcliente = document.getElementById('codcliente');
            if (codcliente && codcliente.value.trim() === '') {
                alert('El cliente es obligatorio');
                return false;
            }
        }
        // Agrega más validaciones si lo deseas
        return true;
    }

    confirmSubmit(event, form) {
        event.preventDefault();
        this.showBootstrapConfirm(
            'Esta acción necesita confirmación. ¿Está seguro de que desea continuar?',
            () => form.submit()
        );
    }

    showBootstrapConfirm(message, onConfirm) {
        const confirmModal = $('#confirmModal');
        const confirmMessage = document.getElementById('confirmModalMessage');
        const confirmOk = document.getElementById('confirmModalOk');

        if (!confirmModal.length || !confirmMessage || !confirmOk) {
            console.warn('Modal de confirmación no encontrado, ejecutando directamente.');
            onConfirm();
            return;
        }

        confirmMessage.textContent = message;

        const okHandler = () => {
            confirmOk.removeEventListener('click', okHandler);
            confirmModal.modal('hide');
            onConfirm();
        };

        confirmOk.addEventListener('click', okHandler);
        confirmModal.modal('show');
    }
}
