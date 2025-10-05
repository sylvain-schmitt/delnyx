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
        console.log("Has sidebar target:", this.hasSidebarTarget)
        console.log("Has overlay target:", this.hasOverlayTarget)
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
        console.log("Toggle called, current openValue:", this.openValue)
        this.openValue = !this.openValue
        console.log("New openValue:", this.openValue)
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
        console.log("UpdateSidebar called, openValue:", this.openValue)
        if (this.openValue) {
            console.log("Opening sidebar...")
            this.open()
        } else {
            console.log("Closing sidebar...")
            this.closeSidebar()
        }
    }

    /**
     * Ouvre la sidebar
     */
    open() {
        console.log("Open method called")
        if (this.hasSidebarTarget) {
            console.log("Sidebar target found, applying classes")
            this.sidebarTarget.classList.remove('-translate-x-full')
            this.sidebarTarget.classList.add('translate-x-0')
        } else {
            console.log("No sidebar target found!")
        }
        
        // Afficher l'overlay sur mobile
        if (this.hasOverlayTarget) {
            console.log("Overlay target found, showing overlay")
            this.overlayTarget.classList.remove('hidden')
        } else {
            console.log("No overlay target found!")
        }
        
        // Empêcher le scroll du body
        document.body.classList.add('overflow-hidden')
    }

    /**
     * Ferme la sidebar
     */
    closeSidebar() {
        console.log("CloseSidebar method called")
        if (this.hasSidebarTarget) {
            console.log("Sidebar target found, applying close classes")
            this.sidebarTarget.classList.add('-translate-x-full')
            this.sidebarTarget.classList.remove('translate-x-0')
        } else {
            console.log("No sidebar target found!")
        }
        
        // Masquer l'overlay
        if (this.hasOverlayTarget) {
            console.log("Overlay target found, hiding overlay")
            this.overlayTarget.classList.add('hidden')
        } else {
            console.log("No overlay target found!")
        }
        
        // Restaurer le scroll du body
        document.body.classList.remove('overflow-hidden')
    }
}
