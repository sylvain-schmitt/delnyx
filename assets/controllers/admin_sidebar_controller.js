import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour la sidebar admin
 * Basé sur le navbar_controller pour la cohérence
 */
export default class extends Controller {
    static targets = ["sidebar", "overlay"]
    static values = { open: Boolean }

    connect() {
        console.log("Admin sidebar controller connected")
        this.openValue = false
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
        
        // Fermer la sidebar lors du clic en dehors
        this.clickHandler = this.handleClickOutside.bind(this)
        document.addEventListener('click', this.clickHandler)
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
        if (this.clickHandler) {
            document.removeEventListener('click', this.clickHandler)
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
     * Gère le clic en dehors
     */
    handleClickOutside(event) {
        if (this.openValue && !this.element.contains(event.target)) {
            this.close()
        }
    }

    /**
     * Toggle la sidebar (mobile)
     */
    toggle() {
        this.openValue = !this.openValue
        this.updateSidebar()
    }

    /**
     * Ferme la sidebar
     */
    close() {
        this.openValue = false
        this.updateSidebar()
    }

    /**
     * Met à jour l'état de la sidebar
     */
    updateSidebar() {
        if (this.openValue) {
            this.open()
        } else {
            this.closeSidebar()
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
    closeSidebar() {
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
}
