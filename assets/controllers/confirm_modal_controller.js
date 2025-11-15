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
        const { url, method, csrfToken, itemName, message } = event.detail

        this.actionUrlValue = url
        this.actionMethodValue = method || 'POST'
        this.csrfTokenValue = csrfToken || ''
        this.itemNameValue = itemName || 'cet élément'

        if (this.hasMessageTarget) {
            this.messageTarget.textContent = message || `Êtes-vous sûr de vouloir supprimer ${this.itemNameValue} ? Cette action est irréversible.`
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
        const message = trigger.dataset.message || `Êtes-vous sûr de vouloir supprimer ${name} ? Cette action est irréversible.`

        // Déclencher l'événement personnalisé
        const customEvent = new CustomEvent('open-confirm-modal', {
            detail: { url, method, csrfToken: token, itemName: name, message }
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

        // Créer un FormData pour la soumission
        const formData = new FormData()
        
        // Ajouter le token CSRF si disponible
        if (this.csrfTokenValue) {
            formData.append('_token', this.csrfTokenValue)
        }

        // Soumettre via fetch pour avoir un contrôle total
        fetch(this.actionUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            // Si redirection, suivre la redirection
            if (response.redirected) {
                window.location.href = response.url
            } else if (response.ok) {
                // Si pas de redirection mais succès, recharger la page
                window.location.reload()
            } else {
                // En cas d'erreur, recharger quand même pour voir les messages flash
                window.location.reload()
            }
        })
        .catch(error => {
            console.error('Erreur lors de la soumission:', error)
            // En cas d'erreur, recharger la page
            window.location.reload()
        })
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

