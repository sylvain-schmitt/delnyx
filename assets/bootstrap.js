import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Désactiver les logs de debug de Stimulus
app.debug = false;

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);

// Import des contrôleurs personnalisés
import PortfolioController from './controllers/portfolio_controller.js';
import AdminFormController from './controllers/admin_form_controller.js';
import ConfirmModalController from './controllers/confirm_modal_controller.js';
import TechnologyPreviewController from './controllers/technology_preview_controller.js';
import TvaSettingsController from './controllers/tva_settings_controller.js';
import InvoiceFormController from './controllers/invoice_form_controller.js';
import CancelModalController from './controllers/cancel_modal_controller.js';
import CancelModalTriggerController from './controllers/cancel_modal_trigger_controller.js';

// Enregistrement des contrôleurs
app.register('portfolio', PortfolioController);
app.register('admin-form', AdminFormController);
app.register('confirm-modal', ConfirmModalController);
app.register('technology-preview', TechnologyPreviewController);
app.register('tva-settings', TvaSettingsController);
app.register('invoice-form', InvoiceFormController);
app.register('cancel-modal', CancelModalController);
app.register('cancel-modal-trigger', CancelModalTriggerController);
