import { Controller } from "@hotwired/stimulus"

/**
 * Controller simple pour déclencher l'ouverture de la modal d'email
 * Utilisé sur les boutons d'envoi d'email
 */
export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        recipient: String,
        documentType: String,
        documentNumber: String
    }

    /**
     * Déclenche l'événement d'ouverture de la modal
     */
    open(event) {
        event.preventDefault()

        const customEvent = new CustomEvent('open-email-modal', {
            detail: {
                url: this.urlValue,
                csrfToken: this.csrfTokenValue,
                recipientEmail: this.recipientValue,
                documentType: this.documentTypeValue,
                documentNumber: this.documentNumberValue
            }
        })
        
        document.dispatchEvent(customEvent)
    }
}

