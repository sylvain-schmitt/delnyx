import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour déclencher l'ouverture du modal d'annulation
 * 
 * Usage sur le bouton:
 * data-controller="cancel-modal-trigger"
 * data-action="click->cancel-modal-trigger#open"
 * data-cancel-modal-trigger-url-value="..."
 * data-cancel-modal-trigger-csrf-token-value="..."
 * data-cancel-modal-trigger-document-type-value="..."
 * data-cancel-modal-trigger-document-number-value="..."
 */
export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        documentType: String,
        documentNumber: String,
    };

    /**
     * Déclenche l'ouverture du modal via un événement personnalisé
     */
    open(event) {
        event.preventDefault();
        event.stopPropagation();
        
        // Déclencher un événement personnalisé pour ouvrir le modal
        const openEvent = new CustomEvent('cancel-modal:open', {
            detail: {
                url: this.urlValue,
                csrfToken: this.csrfTokenValue,
                documentType: this.documentTypeValue,
                documentNumber: this.documentNumberValue
            }
        });
        
        document.dispatchEvent(openEvent);
    }
}

