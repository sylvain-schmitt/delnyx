import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        config: String,
        id: String
    }

    connect() {
        // Charger le script particles.js si pas déjà présent
        this.loadParticlesScript(() => {
            this.initParticles()
        })
    }

    disconnect() {
        // Nettoyer l'instance particles si elle existe
        if (window.pJSDom && window.pJSDom.length > 0) {
            const particleInstance = window.pJSDom.find(instance =>
                instance.pJS.canvas.el.id === this.idValue
            )
            if (particleInstance) {
                particleInstance.pJS.fn.vendors.destroypJS()
            }
        }
    }

    loadParticlesScript(callback) {
        // Si particles.js est déjà chargé, exécuter directement le callback
        if (window.particlesJS) {
            callback()
            return
        }

        // Vérifier si le script est déjà en cours de chargement
        if (document.querySelector('script[src*="particles.min.js"]')) {
            // Attendre que le script soit chargé
            const checkLoaded = () => {
                if (window.particlesJS) {
                    callback()
                } else {
                    setTimeout(checkLoaded, 100)
                }
            }
            checkLoaded()
            return
        }

        // Charger le script
        const script = document.createElement('script')
        script.src = '/components/particles.min.js'
        script.onload = callback
        script.onerror = () => {
            console.error('Erreur lors du chargement de particles.js')
        }
        document.body.appendChild(script)
    }

    initParticles() {
        const configs = {
            'particles-cyan': {
                particles: {
                    number: { value: 70, density: { enable: true, value_area: 300 } },
                    color: { value: "#3b82f6" },
                    shape: { type: "circle" },
                    opacity: { value: 1, random: true, anim: { enable: true, speed: 1.5, opacity_min: 0.4, sync: false } },
                    size: { value: 5, random: true, anim: { enable: true, speed: 3, size_min: 2, sync: false } },
                    line_linked: { enable: true, distance: 120, color: "#3b82f6", opacity: 0.8, width: 2 },
                    move: { enable: true, speed: 1.5, direction: "none", random: false, straight: false, out_mode: "bounce", bounce: true }
                },
                interactivity: {
                    events: { onhover: { enable: true, mode: "grab" }, onclick: { enable: true, mode: "push" } },
                    modes: { grab: { distance: 200, line_linked: { opacity: 1 } }, push: { particles_nb: 3 } }
                }
            },
            'particles-purple': {
                particles: {
                    number: { value: 60, density: { enable: true, value_area: 300 } },
                    color: { value: "#8b5cf6" },
                    shape: { type: "circle" },
                    opacity: { value: 0.9, random: true, anim: { enable: true, speed: 1.2, opacity_min: 0.3, sync: false } },
                    size: { value: 6, random: true, anim: { enable: true, speed: 2.5, size_min: 3, sync: false } },
                    line_linked: { enable: true, distance: 130, color: "#8b5cf6", opacity: 0.7, width: 2.5 },
                    move: { enable: true, speed: 1.2, direction: "none", random: false, straight: false, out_mode: "bounce", bounce: true }
                },
                interactivity: {
                    events: { onhover: { enable: true, mode: "grab" }, onclick: { enable: true, mode: "repulse" } },
                    modes: { grab: { distance: 180, line_linked: { opacity: 1 } }, repulse: { distance: 120 } }
                }
            }
        }

        const configKey = this.configValue || this.idValue
        const config = configs[configKey]

        if (config && window.particlesJS) {
            window.particlesJS(this.idValue, config)
        } else {
            console.warn(`Configuration particles non trouvée pour: ${configKey}`)
        }
    }
}
