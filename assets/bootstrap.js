import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Désactiver les logs de debug de Stimulus
app.debug = false;

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);

// Import des contrôleurs personnalisés
import PortfolioController from './controllers/portfolio_controller.js';
import AdminFormController from './controllers/admin_form_controller.js';

// Enregistrement des contrôleurs
app.register('portfolio', PortfolioController);
app.register('admin-form', AdminFormController);
