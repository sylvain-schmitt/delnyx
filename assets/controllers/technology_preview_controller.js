import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour l'aperçu en temps réel des technologies
 * Met à jour l'aperçu quand l'utilisateur modifie le nom, la couleur ou l'icône
 */
export default class extends Controller {
    static targets = ["icon", "name", "colorCode", "nomField", "couleurField", "iconeField", "iconName"]

    connect() {
        // Debounce pour limiter les requêtes pendant la saisie
        this.debounceTimeout = null
        this.debounceDelay = 300 // ms
        
        // Trouver les champs du formulaire par leur ID
        const nomFieldId = this.nomFieldTarget.value
        const couleurFieldId = this.couleurFieldTarget.value
        const iconeFieldId = this.iconeFieldTarget.value
        
        this.nomInput = document.getElementById(nomFieldId)
        this.couleurInput = document.getElementById(couleurFieldId)
        this.iconeInput = document.getElementById(iconeFieldId)
        
        // Écouter les changements sur les champs avec debounce pour l'icône
        if (this.nomInput) {
            this.nomInput.addEventListener('input', () => this.updatePreview())
        }
        if (this.couleurInput) {
            this.couleurInput.addEventListener('input', () => this.updatePreview())
        }
        if (this.iconeInput) {
            // Debounce pour l'icône pour éviter trop de requêtes
            this.iconeInput.addEventListener('input', () => {
                clearTimeout(this.debounceTimeout)
                this.debounceTimeout = setTimeout(() => {
                    this.updatePreview()
                }, this.debounceDelay)
            })
        }
        
        // Mettre à jour l'aperçu au chargement (avec un petit délai pour s'assurer que tout est chargé)
        setTimeout(() => {
            this.updatePreview()
        }, 100)
    }
    
    disconnect() {
        // Nettoyer le timeout si le contrôleur est déconnecté
        if (this.debounceTimeout) {
            clearTimeout(this.debounceTimeout)
        }
    }

    async updatePreview() {
        const nom = this.nomInput ? this.nomInput.value : ''
        const couleur = this.couleurInput ? this.couleurInput.value : '#3b82f6'
        let icone = this.iconeInput ? this.iconeInput.value.trim() : ''

        // Nettoyer la valeur de l'icône (enlever le code Twig si présent)
        if (icone && (icone.includes('{{') || icone.includes('ux_icon') || icone.includes('~'))) {
            icone = ''
        }

        // Mettre à jour le nom
        if (this.hasNameTarget) {
            this.nameTarget.textContent = nom || 'Nom de la technologie'
        }

        // Mettre à jour le code couleur
        if (this.hasColorCodeTarget) {
            this.colorCodeTarget.textContent = couleur
        }

        // Mettre à jour le nom de l'icône
        if (this.hasIconNameTarget) {
            this.iconNameTarget.textContent = icone || 'Aucune icône'
        }

        // Mettre à jour l'icône (couleur de fond et SVG dynamique)
        if (this.hasIconTarget) {
            // Mettre à jour la couleur de fond
            this.iconTarget.style.backgroundColor = couleur + '20'

            // Déterminer quelle icône charger
            let iconToLoad = 'lucide:code' // Par défaut
            
            if (icone && icone.length > 0) {
                // Si l'icône contient déjà un préfixe (ex: lucide:code, flowbite:user)
                if (icone.includes(':')) {
                    iconToLoad = icone
                } else {
                    // Sinon, on ajoute lucide: par défaut
                    iconToLoad = 'lucide:' + icone
                }
            }

            // Charger l'icône dynamiquement via l'endpoint Symfony
            await this.loadIcon(iconToLoad, couleur)
        }
    }

    async loadIcon(iconName, color) {
        try {
            // Nettoyer le nom de l'icône avant l'envoi
            iconName = iconName.trim()
            
            // Ignorer les noms trop courts (probablement en cours de saisie)
            if (iconName.length < 3) {
                this.loadDefaultIcon(color)
                return
            }
            
            // Construire l'URL avec les paramètres
            const url = `/admin/technology/preview-icon?icon=${encodeURIComponent(iconName)}&color=${encodeURIComponent(color)}`

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const iconHtml = await response.text()
                
                // Remplacer le contenu de l'icône avec le nouveau HTML
                // On vide d'abord le conteneur pour éviter les doublons
                this.iconTarget.innerHTML = ''
                
                // Créer un élément temporaire pour parser le HTML
                const tempDiv = document.createElement('div')
                tempDiv.innerHTML = iconHtml.trim()
                
                // Ajouter le contenu parsé au target
                while (tempDiv.firstChild) {
                    this.iconTarget.appendChild(tempDiv.firstChild)
                }
            } else {
                // En cas d'erreur (404, 500, etc.), utiliser l'icône par défaut
                this.loadDefaultIcon(color)
            }
        } catch (error) {
            // En cas d'erreur réseau, utiliser l'icône par défaut
            this.loadDefaultIcon(color)
        }
    }

    loadDefaultIcon(color) {
        // Icône par défaut (lucide:code)
        const defaultIcon = `<svg class="w-6 h-6" style="color: ${color};" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>`
        this.iconTarget.innerHTML = defaultIcon
    }
}

