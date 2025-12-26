import {CfdiWizard} from './CfdiWizard.js';
import {RelatedController} from './CFDI/controllers/RelatedController.js';

let relatedController = null;

function initRelatedController() {
    if (relatedController) return;

    const relatedTbody = document.getElementById('cfdi:related:list:view');
    const searchTbody = document.getElementById('cfdi:search:list:view');
    if (!relatedTbody || !searchTbody) return;

    relatedController = new RelatedController({
        endpoint: 'EditCfdiCliente',
        action: 'search-related-cfdis',
        fetchDebounceMs: 250,
        toastDelayMs: 3500,
        searchColspan: 7,
    });

    relatedController.connect();
}

document.addEventListener('DOMContentLoaded', () => {
    const wizard = new CfdiWizard('wizardSubmitBtn', 'formCfdiWizard');
    wizard.init('Timbrar');
    initRelatedController();
});
