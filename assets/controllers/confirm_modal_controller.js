import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour les modales de confirmation
 * Utilisable partout dans l'admin pour confirmer des actions destructives
 */
export default class extends Controller {
    static targets = ["modal", "overlay", "title", "message", "confirmButton", "cancelButton"]
    static values = {
        actionUrl: String,
        actionMethod: { type: String, default: "POST" },
        csrfToken: String,
        itemName: String
    }

    connect() {
        // Initialiser la modale comme fermée
        this.close()
        // Lier la gestion de la touche Escape
        this.boundCloseOnEscape = this.closeOnEscape.bind(this)
        document.addEventListener('keydown', this.boundCloseOnEscape)
    }

    /**
     * Ouvre la modale avec les paramètres fournis
     */
    open(event) {
        event.preventDefault()
        
        // Récupérer les données depuis l'élément qui déclenche l'action
        const trigger = event.currentTarget
        const url = trigger.dataset.url || trigger.getAttribute('href') || trigger.closest('form')?.action
        const method = trigger.dataset.method || trigger.closest('form')?.method?.toUpperCase() || 'POST'
        const token = trigger.dataset.csrfToken || trigger.closest('form')?.querySelector('[name="_token"]')?.value || ''
        const name = trigger.dataset.itemName || 'cet élément'
        const message = trigger.dataset.message || `Êtes-vous sûr de vouloir supprimer ${name} ? Cette action est irréversible.`

        // Configurer les valeurs
        this.actionUrlValue = url
        this.actionMethodValue = method
        this.csrfTokenValue = token
        this.itemNameValue = name

        // Mettre à jour le message
        if (this.hasMessageTarget) {
            this.messageTarget.textContent = message
        }

        // Afficher la modale
        this.modalTarget.classList.remove('hidden')
        this.overlayTarget.classList.remove('hidden')
        document.body.style.overflow = 'hidden'
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
     * Confirme l'action et soumet le formulaire
     */
    confirm() {
        // Créer un formulaire temporaire pour soumettre l'action
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = this.actionUrlValue

        // Ajouter le token CSRF si disponible
        if (this.csrfTokenValue) {
            const csrfInput = document.createElement('input')
            csrfInput.type = 'hidden'
            csrfInput.name = '_token'
            csrfInput.value = this.csrfTokenValue
            form.appendChild(csrfInput)
        }

        // Ajouter le body
        document.body.appendChild(form)
        form.submit()
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

    disconnect() {
        if (this.boundCloseOnEscape) {
            document.removeEventListener('keydown', this.boundCloseOnEscape)
        }
    }
}

