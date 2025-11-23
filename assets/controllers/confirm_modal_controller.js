import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour les modales de confirmation
 * Utilisable partout dans l'admin pour confirmer des actions destructives
 */
export default class extends Controller {
    static targets = ["modal", "overlay", "title", "message", "confirmButton", "cancelButton", "buttonText"]
    static values = {
        actionUrl: String,
        actionMethod: { type: String, default: "POST" },
        csrfToken: String,
        itemName: String,
        modalTitle: String,
        buttonText: String
    }

    connect() {
        // Vérifier que les targets existent avant de les utiliser
        if (!this.hasModalTarget || !this.hasOverlayTarget) {
            console.warn('⚠️ Confirm modal: targets manquants. Vérifiez que la modale est bien incluse dans le template.')
            return
        }

        // Initialiser la modale comme fermée
        this.close()
        // Lier la gestion de la touche Escape
        this.boundCloseOnEscape = this.closeOnEscape.bind(this)
        document.addEventListener('keydown', this.boundCloseOnEscape)

        // Écouter les événements personnalisés pour ouvrir la modale (depuis n'importe où dans le DOM)
        this.boundOpenModal = this.openModal.bind(this)
        document.addEventListener('open-confirm-modal', this.boundOpenModal)
    }

    /**
     * Gère l'ouverture via événement personnalisé
     */
    openModal(event) {
        const { url, method, csrfToken, itemName, message, title, buttonText } = event.detail

        this.actionUrlValue = url
        this.actionMethodValue = method || 'POST'
        this.csrfTokenValue = csrfToken || ''
        this.itemNameValue = itemName || 'cet élément'

        // Mettre à jour le titre de la modale
        if (this.hasTitleTarget) {
            this.titleTarget.textContent = title || 'Confirmer l\'action'
        }

        // Mettre à jour le message de la modale
        if (this.hasMessageTarget) {
            if (message) {
                this.messageTarget.textContent = message
            } else {
                this.messageTarget.textContent = `Êtes-vous sûr de vouloir effectuer cette action ?`
            }
        }

        // Mettre à jour le texte du bouton de confirmation
        if (this.hasButtonTextTarget) {
            this.buttonTextTarget.textContent = buttonText || 'Confirmer'
        }

        this.modalTarget.classList.remove('hidden')
        this.overlayTarget.classList.remove('hidden')
        document.body.style.overflow = 'hidden'
    }

    /**
     * Ouvre la modale avec les paramètres fournis (depuis un élément avec data-action)
     */
    open(event) {
        event.preventDefault()

        // Récupérer les données depuis l'élément qui déclenche l'action
        const trigger = event.currentTarget
        const url = trigger.dataset.url || trigger.getAttribute('href') || trigger.closest('form')?.action
        const method = trigger.dataset.method || trigger.closest('form')?.method?.toUpperCase() || 'POST'
        const token = trigger.dataset.csrfToken || trigger.closest('form')?.querySelector('[name="_token"]')?.value || ''
        const name = trigger.dataset.itemName || 'cet élément'

        // Récupérer le message, titre et texte du bouton personnalisés
        const message = trigger.dataset.message || trigger.dataset.confirmModalMessage || null
        const title = trigger.dataset.confirmModalTitle || null
        const buttonText = trigger.dataset.confirmModalButton || null

        // Déclencher l'événement personnalisé
        const customEvent = new CustomEvent('open-confirm-modal', {
            detail: { url, method, csrfToken: token, itemName: name, message, title, buttonText }
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
     * Confirme l'action et soumet le formulaire
     */
    confirm() {
        // Fermer la modale avant la soumission
        this.close()

        // Créer un formulaire temporaire pour la soumission native
        // Cela permet au navigateur de gérer la redirection et les messages flash correctement
        const form = document.createElement('form')
        form.method = this.actionMethodValue
        form.action = this.actionUrlValue
        form.style.display = 'none'

        // Ajouter le token CSRF si disponible
        if (this.csrfTokenValue) {
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = '_token'
            input.value = this.csrfTokenValue
            form.appendChild(input)
        }

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
        if (this.boundOpenModal) {
            document.removeEventListener('open-confirm-modal', this.boundOpenModal)
        }
    }
}

