import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour la modale d'envoi d'email
 * Permet de personnaliser le message avant envoi
 */
export default class extends Controller {
    static targets = ["modal", "overlay", "form", "recipient", "message", "title"]
    static values = {
        actionUrl: String,
        csrfToken: String,
        recipientEmail: String,
        documentType: String,
        documentNumber: String
    }

    connect() {
        // Vérifier que les targets existent
        if (!this.hasModalTarget || !this.hasOverlayTarget) {
            console.warn('⚠️ Email modal: targets manquants.')
            return
        }

        // Initialiser la modale comme fermée
        this.close()
        
        // Lier la gestion de la touche Escape
        this.boundCloseOnEscape = this.closeOnEscape.bind(this)
        document.addEventListener('keydown', this.boundCloseOnEscape)

        // Écouter les événements personnalisés pour ouvrir la modale
        this.boundOpenModal = this.openModal.bind(this)
        document.addEventListener('open-email-modal', this.boundOpenModal)
    }

    /**
     * Ouvre la modale avec les paramètres fournis
     */
    openModal(event) {
        const { url, csrfToken, recipientEmail, documentType, documentNumber } = event.detail

        this.actionUrlValue = url
        this.csrfTokenValue = csrfToken || ''
        this.recipientEmailValue = recipientEmail || ''
        this.documentTypeValue = documentType || 'document'
        this.documentNumberValue = documentNumber || ''

        // Mettre à jour le titre
        if (this.hasTitleTarget) {
            const typeLabel = this.getDocumentTypeLabel(documentType)
            this.titleTarget.textContent = `Envoyer ${typeLabel}`
        }

        // Mettre à jour le destinataire
        if (this.hasRecipientTarget) {
            this.recipientTarget.textContent = recipientEmail
        }

        // Mettre à jour l'action du formulaire
        if (this.hasFormTarget) {
            this.formTarget.action = url
            
            // S'assurer que le token CSRF est dans le formulaire
            let tokenInput = this.formTarget.querySelector('[name="_token"]')
            if (!tokenInput) {
                tokenInput = document.createElement('input')
                tokenInput.type = 'hidden'
                tokenInput.name = '_token'
                this.formTarget.appendChild(tokenInput)
            }
            tokenInput.value = csrfToken
        }

        // Réinitialiser le message personnalisé
        if (this.hasMessageTarget) {
            this.messageTarget.value = ''
        }

        // Afficher la modale
        this.modalTarget.classList.remove('hidden')
        this.overlayTarget.classList.remove('hidden')
        document.body.style.overflow = 'hidden'

        // Focus sur le textarea
        if (this.hasMessageTarget) {
            setTimeout(() => this.messageTarget.focus(), 100)
        }
    }

    /**
     * Ouvre la modale depuis un élément avec data-action
     */
    open(event) {
        event.preventDefault()

        const trigger = event.currentTarget
        const url = trigger.dataset.emailUrl || trigger.closest('form')?.action
        const token = trigger.dataset.emailCsrfToken || trigger.closest('form')?.querySelector('[name="_token"]')?.value || ''
        const recipientEmail = trigger.dataset.emailRecipient || ''
        const documentType = trigger.dataset.emailDocumentType || 'document'
        const documentNumber = trigger.dataset.emailDocumentNumber || ''

        // Déclencher l'événement personnalisé
        const customEvent = new CustomEvent('open-email-modal', {
            detail: { url, csrfToken: token, recipientEmail, documentType, documentNumber }
        })
        document.dispatchEvent(customEvent)
    }

    /**
     * Ferme la modale
     */
    close() {
        this.modalTarget.classList.add('hidden')
        this.overlayTarget.classList.add('hidden')
        document.body.style.overflow = ''
    }

    /**
     * Soumet le formulaire d'envoi
     */
    submit(event) {
        event.preventDefault()
        
        if (this.hasFormTarget) {
            this.formTarget.submit()
        }
    }

    /**
     * Ferme la modale au clic sur l'overlay
     */
    closeOnOverlay(event) {
        if (event.target === this.overlayTarget) {
            this.close()
        }
    }

    /**
     * Ferme la modale avec la touche Escape
     */
    closeOnEscape(event) {
        if (event.key === 'Escape' && !this.modalTarget.classList.contains('hidden')) {
            this.close()
        }
    }

    /**
     * Obtient le label selon le type de document
     */
    getDocumentTypeLabel(type) {
        const labels = {
            'quote': 'le devis',
            'invoice': 'la facture',
            'amendment': 'l\'avenant',
            'credit_note': 'l\'avoir'
        }
        return labels[type] || 'le document'
    }

    disconnect() {
        if (this.boundCloseOnEscape) {
            document.removeEventListener('keydown', this.boundCloseOnEscape)
        }
        if (this.boundOpenModal) {
            document.removeEventListener('open-email-modal', this.boundOpenModal)
        }
    }
}


