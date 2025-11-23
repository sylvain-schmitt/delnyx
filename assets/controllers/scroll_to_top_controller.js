import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le bouton Scroll to Top
 * Compatible avec Symfony UX Turbo
 */
export default class extends Controller {
    static targets = ["button"]
    static values = {
        threshold: { type: Number, default: 300 }
    }

    connect() {
        this.initializeButton()
        this.bindScrollListener()
    }

    disconnect() {
        this.unbindScrollListener()
    }

    /**
     * Initialise le bouton et ses styles
     */
    initializeButton() {
        if (!this.hasButtonTarget) return

        // Styles initiaux
        this.buttonTarget.style.opacity = '0'
        this.buttonTarget.style.pointerEvents = 'none'
        this.buttonTarget.style.transform = 'translateY(20px)'
        this.buttonTarget.style.transition = 'opacity 0.3s ease, transform 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease'

        // Vérifier l'état initial
        this.toggleButton()
    }

    /**
     * Lie l'événement de scroll
     */
    bindScrollListener() {
        this.scrollHandler = this.handleScroll.bind(this)
        window.addEventListener('scroll', this.scrollHandler, { passive: true })
    }

    /**
     * Délie l'événement de scroll
     */
    unbindScrollListener() {
        if (this.scrollHandler) {
            window.removeEventListener('scroll', this.scrollHandler)
        }
    }

    /**
     * Gère l'événement de scroll
     */
    handleScroll() {
        this.toggleButton()
    }

    /**
     * Affiche ou masque le bouton selon la position de scroll
     */
    toggleButton() {
        if (!this.hasButtonTarget) return

        const scrollY = window.scrollY

        if (scrollY > this.thresholdValue) {
            this.showButton()
        } else {
            this.hideButton()
        }
    }

    /**
     * Affiche le bouton avec animation
     */
    showButton() {
        this.buttonTarget.style.opacity = '1'
        this.buttonTarget.style.pointerEvents = 'auto'
        this.buttonTarget.style.transform = 'translateY(0)'
    }

    /**
     * Masque le bouton avec animation
     */
    hideButton() {
        this.buttonTarget.style.opacity = '0'
        this.buttonTarget.style.pointerEvents = 'none'
        this.buttonTarget.style.transform = 'translateY(20px)'
    }

    /**
     * Scroll fluide vers le haut de la page
     */
    scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        })
    }
}
