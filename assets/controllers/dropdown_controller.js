import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour les dropdowns
 * Gère l'ouverture/fermeture des menus déroulants
 */
export default class extends Controller {
    static targets = ["menu"]

    connect() {
        console.log("Dropdown controller connected")
        this.setupEventListeners()
    }

    disconnect() {
        this.removeEventListeners()
    }

    /**
     * Configure les écouteurs d'événements
     */
    setupEventListeners() {
        // Fermer le dropdown lors du clic en dehors
        this.clickHandler = this.handleClickOutside.bind(this)
        document.addEventListener('click', this.clickHandler)
        
        // Fermer le dropdown lors de la navigation Turbo
        this.turboHandler = this.handleTurboNavigation.bind(this)
        document.addEventListener('turbo:before-visit', this.turboHandler)
    }

    /**
     * Supprime les écouteurs d'événements
     */
    removeEventListeners() {
        if (this.clickHandler) {
            document.removeEventListener('click', this.clickHandler)
        }
        if (this.turboHandler) {
            document.removeEventListener('turbo:before-visit', this.turboHandler)
        }
    }

    /**
     * Gère le clic en dehors du dropdown
     */
    handleClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.close()
        }
    }

    /**
     * Gère la navigation Turbo
     */
    handleTurboNavigation() {
        this.close()
    }

    /**
     * Toggle le dropdown
     */
    toggle() {
        if (this.isOpen()) {
            this.close()
        } else {
            this.open()
        }
    }

    /**
     * Ouvre le dropdown
     */
    open() {
        if (this.hasMenuTarget) {
            this.menuTarget.classList.remove('hidden')
            this.menuTarget.classList.add('block')
        }
    }

    /**
     * Ferme le dropdown
     */
    close() {
        if (this.hasMenuTarget) {
            this.menuTarget.classList.add('hidden')
            this.menuTarget.classList.remove('block')
        }
    }

    /**
     * Vérifie si le dropdown est ouvert
     */
    isOpen() {
        return this.hasMenuTarget && !this.menuTarget.classList.contains('hidden')
    }
}
