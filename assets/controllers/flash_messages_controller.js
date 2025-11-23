import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour gérer les messages flash
 * - Disparition automatique après 5 secondes
 * - Animation de sortie fluide
 * - Fermeture manuelle possible
 */
export default class extends Controller {
    static targets = ["message"]
    static values = {
        autoHide: { type: Boolean, default: true },
        delay: { type: Number, default: 5000 } // 5 secondes
    }

    connect() {
        if (this.autoHideValue) {
            this.scheduleAutoHide()
        }

        // Ajouter un bouton de fermeture à chaque message
        this.messageTargets.forEach(message => {
            this.addCloseButton(message)
        })

        // Écouter les événements Turbo pour recharger les messages après une navigation
        this.turboLoadHandler = () => {
            // Après un chargement Turbo, réinitialiser les messages
            if (this.autoHideValue) {
                // Annuler le timeout précédent s'il existe
                if (this.timeout) {
                    clearTimeout(this.timeout)
                }
                // Programmer un nouveau timeout pour les nouveaux messages
                this.scheduleAutoHide()
            }

            // Ajouter les boutons de fermeture aux nouveaux messages
            this.messageTargets.forEach(message => {
                // Vérifier si le bouton existe déjà
                const existingButton = message.querySelector('[data-action*="flash-messages#hideMessage"]')
                if (!existingButton) {
                    this.addCloseButton(message)
                }
            })
        }

        // Écouter turbo:load pour les chargements de page complets
        document.addEventListener('turbo:load', this.turboLoadHandler)
        // Écouter aussi turbo:frame-load pour les chargements de frames
        document.addEventListener('turbo:frame-load', this.turboLoadHandler)
    }

    disconnect() {
        if (this.timeout) {
            clearTimeout(this.timeout)
        }
        
        // Retirer les écouteurs Turbo
        if (this.turboLoadHandler) {
            document.removeEventListener('turbo:load', this.turboLoadHandler)
            document.removeEventListener('turbo:frame-load', this.turboLoadHandler)
        }
    }

    /**
     * Programme la disparition automatique
     */
    scheduleAutoHide() {
        this.timeout = setTimeout(() => {
            this.hideAllMessages()
        }, this.delayValue)
    }

    /**
     * Ajoute un bouton de fermeture au message
     */
    addCloseButton(messageElement) {
        const closeButton = document.createElement('button')
        closeButton.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        `
        closeButton.className = "ml-auto text-gray-400 hover:text-white transition-colors duration-200 opacity-70 hover:opacity-100"
        closeButton.setAttribute('data-action', 'click->flash-messages#hideMessage')
        closeButton.setAttribute('data-flash-messages-message-param', messageElement.dataset.flashMessagesMessageParam || '0')

        // Ajouter le bouton au message
        const contentDiv = messageElement.querySelector('.flex')
        if (contentDiv) {
            contentDiv.appendChild(closeButton)
        }
    }

    /**
     * Cache un message spécifique avec animation
     */
    hideMessage(event) {
        const messageElement = event.target.closest('[data-flash-messages-target="message"]')
        if (messageElement) {
            this.animateOut(messageElement)
        }
    }

    /**
     * Cache tous les messages
     */
    hideAllMessages() {
        this.messageTargets.forEach(message => {
            this.animateOut(message)
        })
    }

    /**
     * Animation de sortie fluide
     */
    animateOut(element) {
        // Animation de sortie
        element.style.transition = 'all 0.3s ease-out'
        element.style.transform = 'translateX(100%)'
        element.style.opacity = '0'

        // Supprimer l'élément après l'animation
        setTimeout(() => {
            if (element.parentNode) {
                element.parentNode.removeChild(element)
            }
        }, 300)
    }

    /**
     * Pause l'auto-hide quand on survole
     */
    pauseAutoHide() {
        if (this.timeout) {
            clearTimeout(this.timeout)
        }
    }

    /**
     * Reprend l'auto-hide quand on quitte le survol
     */
    resumeAutoHide() {
        if (this.autoHideValue) {
            this.scheduleAutoHide()
        }
    }
}
