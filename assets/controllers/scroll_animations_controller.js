import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["animate", "light"]
    static values = {
        threshold: { type: Number, default: 0.2 },
        rootMargin: { type: String, default: '0px 0px -50px 0px' },
        animationClass: { type: String, default: 'animate-fade-up' },
        visibleClass: { type: String, default: 'visible' }
    }

    connect() {
        this.setupMainObserver()
        this.setupLightObserver()
        this.observeElements()
    }

    disconnect() {
        // Nettoyer les observers
        if (this.mainObserver) {
            this.mainObserver.disconnect()
        }
        if (this.lightObserver) {
            this.lightObserver.disconnect()
        }
    }

    setupMainObserver() {
        const options = {
            root: null,
            rootMargin: this.rootMarginValue,
            threshold: this.thresholdValue
        }

        this.mainObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Ajouter les classes d'animation
                    entry.target.classList.add(this.animationClassValue)
                    entry.target.classList.add(this.visibleClassValue)

                    // Supprimer la classe scroll-animate
                    entry.target.classList.remove("scroll-animate")

                    // Arrêter d'observer cet élément pour optimiser les performances
                    this.mainObserver.unobserve(entry.target)
                }
            })
        }, options)
    }

    setupLightObserver() {
        const lightOptions = {
            root: null,
            rootMargin: '100px',
            threshold: 0
        }

        this.lightObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Activer l'animation des lumières
                    entry.target.style.animationPlayState = 'running'
                } else {
                    // Pause l'animation pour économiser les ressources
                    entry.target.style.animationPlayState = 'paused'
                }
            })
        }, lightOptions)
    }

    observeElements() {
        // Observer tous les éléments avec la classe scroll-animate dans le scope du controller
        const animateElements = this.element.querySelectorAll('.scroll-animate')
        animateElements.forEach(element => {
            this.mainObserver.observe(element)
        })

        // Observer les lumières d'ambiance
        const lightElements = this.element.querySelectorAll('.light-ambient')
        lightElements.forEach(element => {
            this.lightObserver.observe(element)
        })
    }

    // Méthode pour ajouter dynamiquement de nouveaux éléments à observer
    addElement(element) {
        if (element.classList.contains('scroll-animate')) {
            this.mainObserver.observe(element)
        }
        if (element.classList.contains('light-ambient')) {
            this.lightObserver.observe(element)
        }
    }

    // Méthode pour réinitialiser les animations (utile pour le développement)
    reset() {
        const elements = this.element.querySelectorAll('.visible, .animate-fade-up')
        elements.forEach(element => {
            element.classList.remove(this.visibleClassValue, this.animationClassValue)
            element.classList.add('scroll-animate')
        })
        this.observeElements()
    }
}
