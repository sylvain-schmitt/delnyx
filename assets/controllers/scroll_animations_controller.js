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
        
        // Déclencher l'animation des éléments déjà visibles après un court délai
        // Cela gère le cas où on charge directement en mobile et les éléments sont déjà dans le viewport
        setTimeout(() => {
            this.triggerVisibleElements()
        }, 100)
        
        // Ajouter un écouteur de redimensionnement pour réobserver les éléments
        // Cela permet de gérer les éléments qui sont cachés au chargement (ex: mobile cards avec md:hidden)
        this.handleResize = this.debounce(() => {
            // Réobserver tous les éléments qui ne sont pas encore animés
            this.observeElements()
            
            // Forcer l'animation immédiate des éléments déjà visibles dans le viewport
            this.triggerVisibleElements()
        }, 250)
        
        window.addEventListener('resize', this.handleResize)
    }

    disconnect() {
        // Nettoyer les observers
        if (this.mainObserver) {
            this.mainObserver.disconnect()
        }
        if (this.lightObserver) {
            this.lightObserver.disconnect()
        }
        
        // Nettoyer l'écouteur de resize
        if (this.handleResize) {
            window.removeEventListener('resize', this.handleResize)
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
            // Ne pas réobserver si l'élément est déjà visible ou en cours d'animation
            if (!element.classList.contains('visible') && !element.classList.contains('animate-fade-up')) {
                this.mainObserver.observe(element)
            }
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

    // Forcer l'animation des éléments déjà visibles dans le viewport
    // Utile lors du redimensionnement pour éviter que les éléments restent cachés
    triggerVisibleElements() {
        const animateElements = this.element.querySelectorAll('.scroll-animate')
        animateElements.forEach(element => {
            // Vérifier si l'élément est visible (pas display:none) et dans le viewport
            const rect = element.getBoundingClientRect()
            const isVisible = rect.width > 0 && rect.height > 0
            const isInViewport = (
                rect.top < window.innerHeight &&
                rect.bottom > 0
            )
            
            if (isVisible && isInViewport) {
                // Animer immédiatement
                element.classList.add(this.animationClassValue)
                element.classList.add(this.visibleClassValue)
                element.classList.remove("scroll-animate")
            }
        })
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
    
    // Fonction debounce pour limiter le nombre d'appels lors du resize
    debounce(func, wait) {
        let timeout
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout)
                func(...args)
            }
            clearTimeout(timeout)
            timeout = setTimeout(later, wait)
        }
    }
}
