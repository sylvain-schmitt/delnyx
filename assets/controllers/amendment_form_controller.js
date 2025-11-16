import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le formulaire d'avenant
 * Gère le chargement dynamique des lignes du devis
 */
export default class extends Controller {
    static targets = ["quoteSelect", "sourceLineSelect"]

    connect() {
        // Écouter les changements sur le select devis
        const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
        if (quoteSelect) {
            quoteSelect.addEventListener('change', (e) => this.handleQuoteChange(e))
            // Pré-remplir si un devis est déjà sélectionné (mode édition)
            if (quoteSelect.value) {
                this.handleQuoteChange({ target: quoteSelect })
            }
        }

        // Observer les changements dans le DOM pour détecter l'ajout de nouvelles lignes
        const linesContainer = this.element.querySelector('[class*="amendment-lines-collection"]') || 
                               this.element.querySelector('[class*="lines"]')
        if (linesContainer) {
            this.observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1 && node.querySelector('select[name*="[sourceLine]"]')) {
                            // Une nouvelle ligne a été ajoutée, mettre à jour son select
                            this.handleNewLineAdded()
                        }
                    })
                })
            })
            this.observer.observe(linesContainer, { childList: true, subtree: true })
        }
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect()
        }
    }

    /**
     * Charge les lignes du devis sélectionné et met à jour les selects sourceLine
     */
    async handleQuoteChange(event) {
        const quoteId = event.target.value
        if (!quoteId) {
            // Si aucun devis n'est sélectionné, vider tous les selects sourceLine
            this.clearSourceLineSelects()
            return
        }

        try {
            const response = await fetch(`/admin/amendment/api/quote/${quoteId}/lines`)
            if (!response.ok) {
                console.error('Erreur lors du chargement des lignes:', response.statusText)
                return
            }

            const lines = await response.json()
            this.updateSourceLineSelects(lines)
        } catch (error) {
            console.error('Erreur lors du chargement des lignes:', error)
        }
    }

    /**
     * Met à jour tous les selects sourceLine avec les lignes du devis
     */
    updateSourceLineSelects(lines) {
        const sourceLineSelects = this.element.querySelectorAll('select[name*="[sourceLine]"]')
        sourceLineSelects.forEach(select => {
            // Sauvegarder la valeur actuelle
            const currentValue = select.value

            // Vider le select (garder le placeholder)
            select.innerHTML = '<option value="">Nouvelle ligne (pas de modification)</option>'

            // Ajouter les lignes
            lines.forEach(line => {
                const option = document.createElement('option')
                option.value = line.id
                option.textContent = line.label
                select.appendChild(option)
            })

            // Restaurer la valeur si elle existe toujours
            if (currentValue && Array.from(select.options).some(opt => opt.value === currentValue)) {
                select.value = currentValue
            }
        })
    }

    /**
     * Méthode appelée quand une nouvelle ligne est ajoutée
     * Met à jour le select sourceLine de la nouvelle ligne
     */
    handleNewLineAdded() {
        const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
        if (quoteSelect && quoteSelect.value) {
            // Recharger les lignes pour mettre à jour le nouveau select
            this.handleQuoteChange({ target: quoteSelect })
        }
    }

    /**
     * Vide tous les selects sourceLine
     */
    clearSourceLineSelects() {
        const sourceLineSelects = this.element.querySelectorAll('select[name*="[sourceLine]"]')
        sourceLineSelects.forEach(select => {
            select.innerHTML = '<option value="">Nouvelle ligne (pas de modification)</option>'
        })
    }
}

