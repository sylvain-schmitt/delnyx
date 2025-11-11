import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour rendre un select recherchable
 * Transforme un select standard en select avec recherche
 */
export default class extends Controller {
    connect() {
        // Trouver le select dans l'élément (peut être directement l'élément ou un enfant)
        let select = this.element.tagName === 'SELECT' ? this.element : this.element.querySelector('select')
        
        if (!select) {
            return
        }
        
        // Créer un conteneur pour le select et l'input de recherche
        const wrapper = document.createElement('div')
        wrapper.className = 'relative'
        
        // Créer l'input de recherche
        const searchInput = document.createElement('input')
        searchInput.type = 'text'
        searchInput.className = 'form-input w-full'
        searchInput.placeholder = 'Rechercher un client...'
        searchInput.autocomplete = 'off'
        // Ajouter l'attribut data-admin-form-target pour la validation
        searchInput.setAttribute('data-admin-form-target', 'field')
        // Synchroniser la valeur de l'input avec le select pour la validation
        searchInput.setAttribute('data-sync-with', select.name || select.id)
        
        // Créer un conteneur pour les options filtrées
        const optionsContainer = document.createElement('div')
        optionsContainer.className = 'absolute z-50 w-full mt-1 bg-slate-800 border border-white/20 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden'
        optionsContainer.id = `options-${select.id || 'client'}`
        
        // Récupérer toutes les options du select
        const allOptions = Array.from(select.options)
        
        // Fonction pour filtrer et afficher les options
        const filterOptions = (searchTerm, showAll = false) => {
            const term = searchTerm ? searchTerm.toLowerCase().trim() : ''
            optionsContainer.innerHTML = ''
            
            // Si showAll est true ou si le terme est vide, afficher toutes les options (sauf l'option vide)
            const filtered = allOptions.filter(option => {
                if (option.value === '') return false // Ignorer l'option vide
                if (showAll || term === '') {
                    return true // Afficher toutes les options
                }
                return option.text.toLowerCase().includes(term)
            })
            
            if (filtered.length === 0) {
                const noResult = document.createElement('div')
                noResult.className = 'px-4 py-2 text-white/60 text-sm'
                noResult.textContent = 'Aucun résultat'
                optionsContainer.appendChild(noResult)
            } else {
                filtered.forEach(option => {
                    const optionDiv = document.createElement('div')
                    optionDiv.className = 'px-4 py-2 text-white hover:bg-white/10 cursor-pointer transition-colors'
                    optionDiv.textContent = option.text
                    optionDiv.dataset.value = option.value
                    
                    optionDiv.addEventListener('click', () => {
                        select.value = option.value
                        searchInput.value = option.text
                        optionsContainer.classList.add('hidden')
                        // Déclencher l'événement change sur le select pour la validation
                        select.dispatchEvent(new Event('change', { bubbles: true }))
                        // Déclencher aussi un événement input pour forcer la validation
                        select.dispatchEvent(new Event('input', { bubbles: true }))
                        // Déclencher un événement blur pour forcer la validation
                        select.dispatchEvent(new Event('blur', { bubbles: true }))
                    })
                    
                    optionsContainer.appendChild(optionDiv)
                })
            }
            
            optionsContainer.classList.remove('hidden')
        }
        
        // Écouter la saisie dans l'input de recherche
        searchInput.addEventListener('input', (e) => {
            filterOptions(e.target.value, false)
        })
        
        // Ouvrir le menu au focus (afficher toutes les options)
        searchInput.addEventListener('focus', () => {
            filterOptions('', true)
        })
        
        // Ouvrir le menu au clic (afficher toutes les options si vide)
        searchInput.addEventListener('click', () => {
            if (searchInput.value === '' || searchInput.value === searchInput.placeholder) {
                filterOptions('', true)
            }
        })
        
        // Fermer le menu si on clique en dehors
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) {
                optionsContainer.classList.add('hidden')
            }
        })
        
        // Afficher le select original mais le rendre invisible (mais toujours accessible pour la validation)
        // S'assurer que le select garde son attribut data-admin-form-target pour la validation
        if (!select.hasAttribute('data-admin-form-target')) {
            select.setAttribute('data-admin-form-target', 'field')
        }
        select.style.position = 'absolute'
        select.style.opacity = '0'
        select.style.width = '1px'
        select.style.height = '1px'
        select.style.pointerEvents = 'none'
        // Garder le select dans le DOM pour la validation
        
        // Afficher la valeur sélectionnée dans l'input de recherche
        const updateSearchInput = () => {
            const selectedOption = allOptions.find(opt => opt.value === select.value)
            if (selectedOption && select.value && select.value !== '') {
                searchInput.value = selectedOption.text
            } else {
                // Si aucune valeur sélectionnée, afficher le placeholder
                searchInput.value = ''
                searchInput.placeholder = 'Rechercher un client...'
            }
        }
        
        // Mettre à jour l'input quand le select change
        select.addEventListener('change', () => {
            updateSearchInput()
            // Déclencher la validation quand le select change
            select.dispatchEvent(new Event('blur', { bubbles: true }))
        })
        
        // Écouter les changements sur l'input de recherche pour vider le select si l'input est vidé
        searchInput.addEventListener('input', (e) => {
            const inputValue = e.target.value.trim()
            // Si l'input est vidé, vider aussi le select
            if (inputValue === '') {
                select.value = ''
                select.dispatchEvent(new Event('change', { bubbles: true }))
                select.dispatchEvent(new Event('blur', { bubbles: true }))
                // Déclencher la validation
                setTimeout(() => {
                    const formController = document.querySelector('[data-controller*="admin-form"]')
                    if (formController) {
                        // Utiliser Stimulus pour obtenir le contrôleur
                        const application = window.Stimulus || window.application
                        if (application) {
                            const controller = application.getControllerForElementAndIdentifier(formController, 'admin-form')
                            if (controller && controller.validateField) {
                                controller.validateField(select)
                            }
                        }
                    }
                }, 100)
            }
        })
        
        // Écouter le blur sur l'input de recherche pour valider
        searchInput.addEventListener('blur', () => {
            // Déclencher la validation du select associé
            select.dispatchEvent(new Event('blur', { bubbles: true }))
        })
        
        // Initialiser l'input avec la valeur actuelle
        updateSearchInput()
        
        // Si aucune valeur n'est sélectionnée au démarrage, s'assurer que le placeholder est visible
        if (!select.value || select.value === '') {
            searchInput.value = ''
            searchInput.placeholder = 'Rechercher un client...'
        }
        
        // Insérer les éléments dans le wrapper
        wrapper.appendChild(searchInput)
        wrapper.appendChild(optionsContainer)
        
        // Remplacer le select par le wrapper (en gardant le select caché)
        select.parentNode.insertBefore(wrapper, select)
        wrapper.appendChild(select)
        
        // Stocker les références pour le nettoyage
        this.wrapper = wrapper
        this.searchInput = searchInput
        this.optionsContainer = optionsContainer
        this.select = select
    }

    disconnect() {
        if (this.wrapper && this.select) {
            // Restaurer le select original
            this.select.style.display = ''
            if (this.wrapper.parentNode) {
                this.wrapper.parentNode.insertBefore(this.select, this.wrapper)
                this.wrapper.remove()
            }
        }
    }
}

