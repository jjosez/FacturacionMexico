import {CfdiWizard} from './CfdiWizard.js';
import {CfdiRelated} from './CfdiRelated.js';

document.addEventListener('DOMContentLoaded', () => {
    const wizard = new CfdiWizard('wizardSubmitBtn', 'formCfdiWizard');
    wizard.init('Timbrar');

    const relacionados = new CfdiRelated();
    relacionados.init();
});
