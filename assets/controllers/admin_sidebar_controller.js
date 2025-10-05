import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour la sidebar admin
 * Gère l'ouverture/fermeture de la sidebar sur mobile
 */
export default class extends Controller {
    static targets = ["sidebar", "overlay"]

    connect() {
        console.log("Admin sidebar controller connected")
        this.setupEventListeners()
    }

    disconnect() {
        this.removeEventListeners()
    }

    /**
     * Configure les écouteurs d'événements
     */
    setupEventListeners() {
        // Fermer la sidebar lors du redimensionnement vers desktop
        this.resizeHandler = this.handleResize.bind(this)
        window.addEventListener('resize', this.resizeHandler)

        // Fermer la sidebar lors de la navigation Turbo
        this.turboHandler = this.handleTurboNavigation.bind(this)
        document.addEventListener('turbo:before-visit', this.turboHandler)
    }

    /**
     * Supprime les écouteurs d'événements
     */
    removeEventListeners() {
        if (this.resizeHandler) {
            window.removeEventListener('resize', this.resizeHandler)
        }
        if (this.turboHandler) {
            document.removeEventListener('turbo:before-visit', this.turboHandler)
        }
    }

    /**
     * Gère le redimensionnement de la fenêtre
     */
    handleResize() {
        if (window.innerWidth >= 1024) { // lg breakpoint
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
     * Toggle la sidebar (mobile)
     */
    toggle() {
        if (this.isOpen()) {
            this.close()
        } else {
            this.open()
        }
    }

    /**
     * Ouvre la sidebar
     */
    open() {
        if (this.hasSidebarTarget) {
            this.sidebarTarget.classList.remove('-translate-x-full')
            this.sidebarTarget.classList.add('translate-x-0')
        }
        
        // Afficher l'overlay sur mobile
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.remove('hidden')
        }
        
        // Empêcher le scroll du body
        document.body.classList.add('overflow-hidden')
    }

    /**
     * Ferme la sidebar
     */
    close() {
        if (this.hasSidebarTarget) {
            this.sidebarTarget.classList.add('-translate-x-full')
            this.sidebarTarget.classList.remove('translate-x-0')
        }
        
        // Masquer l'overlay
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.add('hidden')
        }
        
        // Restaurer le scroll du body
        document.body.classList.remove('overflow-hidden')
    }

    /**
     * Vérifie si la sidebar est ouverte
     */
    isOpen() {
        return this.hasSidebarTarget && !this.sidebarTarget.classList.contains('-translate-x-full')
    }
}
