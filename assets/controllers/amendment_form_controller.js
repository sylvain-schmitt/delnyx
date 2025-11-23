import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le formulaire d'avenant
 * Gère le chargement dynamique des lignes du devis
 */
export default class extends Controller {
    static targets = ["quoteSelect", "sourceLineSelect"]

    // Stocker les lignes du devis pour les utiliser quand les selects seront disponibles
    quoteLines = null
    currentQuoteId = null

    connect() {
        // Écouter les changements sur le select devis
        // Le select peut être caché par select-search, donc on doit écouter à plusieurs niveaux
        const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
        if (quoteSelect) {
            // Ne pas écouter les changements si le select est déjà disabled (devis verrouillé)
            if (!quoteSelect.disabled) {
                // Écouter les changements sur le select original
                // select-search déclenche déjà un événement 'change' quand une option est sélectionnée
                const handleChange = (e) => {
                    const newQuoteId = e.target.value || quoteSelect.value
                    if (newQuoteId && newQuoteId !== this.currentQuoteId) {
                        this.handleQuoteChange({ target: quoteSelect })
                    }
                }
                quoteSelect.addEventListener('change', handleChange)

                // Écouter aussi les clics sur les options dans select-search
                // select-search crée un conteneur d'options avec des divs cliquables
                const searchInput = this.element.querySelector('input[data-sync-with*="[quote]"]')
                if (searchInput) {
                    // Écouter les clics sur les options (select-search crée des divs avec dataset.value)
                    const optionsContainer = searchInput.parentElement?.querySelector('[id^="options-"]')
                    if (optionsContainer) {
                        optionsContainer.addEventListener('click', (e) => {
                            const optionDiv = e.target.closest('[data-value]')
                            if (optionDiv) {
                                // Attendre un peu pour que select-search ait mis à jour le select
                                setTimeout(() => {
                                    handleChange({ target: quoteSelect })
                                }, 50)
                            }
                        })
                    }

                    // Polling léger en secours (vérifier toutes les 500ms si la valeur a changé)
                    // Seulement si le select n'est pas désactivé
                    this.quotePollingInterval = setInterval(() => {
                        if (quoteSelect.disabled) {
                            clearInterval(this.quotePollingInterval)
                            return
                        }
                        const currentValue = quoteSelect.value
                        if (currentValue && currentValue !== this.currentQuoteId) {
                            handleChange({ target: quoteSelect })
                        }
                    }, 500)
                }
            }

            // Pré-remplir si un devis est déjà sélectionné (mode édition ou création depuis devis)
            const isDisabled = quoteSelect.disabled
            const quoteId = quoteSelect.value || (isDisabled && quoteSelect.dataset.quoteId)

            if (quoteId) {
                // Attendre un peu pour que le DOM soit prêt
                setTimeout(() => {
                    // Si le select est disabled, utiliser directement l'ID depuis le champ caché
                    if (isDisabled) {
                        const hiddenInput = this.element.querySelector(`input[type="hidden"][name*="[quote]"]`)
                        const actualQuoteId = hiddenInput ? hiddenInput.value : quoteId
                        if (actualQuoteId) {
                            // Charger les lignes du devis pour affichage
                            this.loadQuoteLinesForDisplay(actualQuoteId)
                        }
                    } else {
                        this.handleQuoteChange({ target: quoteSelect })
                    }
                }, 200)
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
        if (this.quotePollingInterval) {
            clearInterval(this.quotePollingInterval)
        }
    }

    /**
     * Charge les lignes du devis pour affichage (sans créer de champ caché)
     */
    async loadQuoteLinesForDisplay(quoteId) {
        try {
            const url = `/admin/amendment/api/quote/${quoteId}`
            const response = await fetch(url)
            if (!response.ok) {
                console.error('[AmendmentFormController] Erreur lors du chargement du devis:', response.statusText)
                return
            }

            const data = await response.json()

            // Stocker les lignes pour les utiliser quand les selects seront disponibles
            this.quoteLines = data.lines || []
            this.currentQuoteId = quoteId.toString()

            // Afficher les lignes du devis en lecture seule
            this.displayQuoteLines(data)

            // Mettre à jour les selects sourceLine
            this.updateSourceLineSelects(this.quoteLines)
        } catch (error) {
            console.error('[AmendmentFormController] Erreur lors du chargement du devis:', error)
        }
    }

    /**
     * Charge les lignes du devis sélectionné et affiche les lignes en lecture seule
     */
    async handleQuoteChange(event) {
        const quoteId = event.target.value
        if (!quoteId) {
            // Si aucun devis n'est sélectionné, supprimer l'affichage des lignes
            this.removeQuoteLinesDisplay()
            this.clearSourceLineSelects()
            this.currentQuoteId = null
            this.quoteLines = null
            return
        }

        try {
            // Appeler l'API pour récupérer les infos complètes du devis
            const url = `/admin/amendment/api/quote/${quoteId}`
            const response = await fetch(url)
            if (!response.ok) {
                console.error('[AmendmentFormController] Erreur lors du chargement du devis:', response.statusText)
                return
            }

            const data = await response.json()

            // Le template gère déjà le champ caché, on met juste à jour le champ caché existant si nécessaire
            const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
            if (quoteSelect) {
                const hiddenInput = this.element.querySelector(`input[type="hidden"][name="${quoteSelect.name}"]`)
                if (hiddenInput) {
                    // Mettre à jour la valeur du champ caché existant
                    hiddenInput.value = quoteId
                } else if (quoteSelect.disabled) {
                    // Si le select est désactivé mais qu'il n'y a pas de champ caché, en créer un
                    // (normalement le template devrait l'avoir créé, mais on s'assure)
                    const hidden = document.createElement('input')
                    hidden.type = 'hidden'
                    hidden.name = quoteSelect.name
                    hidden.value = quoteId
                    hidden.id = quoteSelect.id + '_hidden'
                    quoteSelect.parentElement.appendChild(hidden)
                }
            }

            // Stocker les lignes pour les utiliser quand les selects seront disponibles
            this.quoteLines = data.lines || []
            this.currentQuoteId = quoteId.toString()

            // Afficher les lignes du devis en lecture seule
            this.displayQuoteLines(data)

            // Mettre à jour les selects sourceLine
            this.updateSourceLineSelects(this.quoteLines)
        } catch (error) {
            console.error('[AmendmentFormController] Erreur lors du chargement du devis:', error)
        }
    }

    /**
     * Affiche les lignes du devis en lecture seule (comme dans le template Twig)
     */
    displayQuoteLines(data) {
        // Supprimer l'affichage précédent s'il existe (statique ou dynamique)
        this.removeQuoteLinesDisplay()

        // Supprimer aussi l'affichage statique côté serveur s'il existe
        const staticDisplay = this.element.querySelector('[data-static-quote-lines]')
        if (staticDisplay) {
            staticDisplay.style.display = 'none'
        }

        if (!data.lines || data.lines.length === 0) {
            return
        }

        // Trouver où insérer la section (après les informations générales, avant les lignes d'ajustement)
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
        if (!linesContainer) {
            console.warn('[AmendmentFormController] Conteneur de lignes non trouvé')
            return
        }

        // Créer la section des lignes du devis avec le même style que le template
        const quoteLinesSection = document.createElement('div')
        quoteLinesSection.className = 'space-y-4'
        quoteLinesSection.setAttribute('data-quote-lines-display', 'true')

        // Construire le HTML avec affichage adapté selon TVA activée ou non
        const isTvaEnabled = data.isTvaEnabled || false

        let linesHtml = ''
        if (isTvaEnabled) {
            // Affichage avec colonnes HT, TVA, TTC
            linesHtml = `
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-white/10 border-b border-white/20">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-white/80 uppercase tracking-wider">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Qté</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Prix unitaire</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Total HT</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">TVA</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Total TTC</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            ${data.lines.map(line => {
                const tvaRate = line.tvaRate || 0
                const tvaAmount = line.tvaAmount || 0
                const totalTtc = line.totalTtc || line.totalHt || 0
                return `
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3 text-sm text-white/90">
                                            <div class="font-medium">${this.escapeHtml(line.description || 'N/A')}</div>
                                            ${data.usePerLineTva && tvaRate ? `<div class="text-xs text-white/60">TVA ${tvaRate}%</div>` : ''}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-white/90 text-right">${line.quantity || 0}</td>
                                        <td class="px-4 py-3 text-sm text-white/90 text-right">${parseFloat(line.unitPrice || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                                        <td class="px-4 py-3 text-sm text-white/90 text-right">${parseFloat(line.totalHt || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                                        <td class="px-4 py-3 text-sm text-white/90 text-right">${parseFloat(tvaAmount).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                                        <td class="px-4 py-3 text-sm text-white/90 text-right font-medium">${parseFloat(totalTtc).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                                    </tr>
                                `
            }).join('')}
                        </tbody>
                    </table>
                </div>
            `
        } else {
            // Affichage avec tableau (même format que TVA activée, mais sans colonnes HT et TVA)
            linesHtml = `
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-white/10 border-b border-white/20">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-white/80 uppercase tracking-wider">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Qté</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Prix unitaire</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Total TTC</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            ${data.lines.map(line => {
                const totalTtc = line.totalTtc || line.totalHt || 0
                return `
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3 text-sm text-white/90">
                                            <div class="font-medium">${this.escapeHtml(line.description || 'N/A')}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-white/90 text-right">${line.quantity || 0}</td>
                                        <td class="px-4 py-3 text-sm text-white/90 text-right">${parseFloat(line.unitPrice || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                                        <td class="px-4 py-3 text-sm text-white/90 text-right font-medium">${parseFloat(totalTtc).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                                    </tr>
                                `
            }).join('')}
                        </tbody>
                    </table>
                </div>
            `
        }

        quoteLinesSection.innerHTML = `
            <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Lignes du devis d'origine (référence - lecture seule)
            </h3>
            <div class="bg-white/5 border border-white/20 rounded-lg p-4">
                ${linesHtml}
                <div class="pt-2 border-t border-white/20 mt-2">
                    <div class="flex items-center justify-between">
                        <span class="text-white font-semibold">Total devis initial</span>
                        <span class="text-white font-bold">${data.montantTTCFormate || parseFloat(data.montantTTC || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</span>
                    </div>
                </div>
            </div>
        `

        // Insérer avant la section "Lignes d'ajustement"
        // Trouver la section parente qui contient le conteneur de lignes
        let parentSection = linesContainer.closest('.space-y-4')
        if (!parentSection) {
            // Si pas trouvé, chercher le parent direct
            parentSection = linesContainer.parentElement
        }

        if (parentSection && parentSection.parentElement) {
            // Insérer avant la section parente
            parentSection.parentElement.insertBefore(quoteLinesSection, parentSection)
        } else if (linesContainer.parentElement) {
            // Fallback : insérer avant le conteneur
            linesContainer.parentElement.insertBefore(quoteLinesSection, linesContainer)
        }
    }

    /**
     * Échappe le HTML pour éviter les injections XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    /**
     * Supprime l'affichage des lignes du devis
     */
    removeQuoteLinesDisplay() {
        const existingDisplay = this.element.querySelector('[data-quote-lines-display="true"]')
        if (existingDisplay) {
            existingDisplay.remove()
        }
    }

    /**
     * Met à jour tous les selects sourceLine avec les lignes du devis
     */
    updateSourceLineSelects(lines) {
        // Attendre un peu pour que le DOM soit prêt si les selects n'existent pas encore
        let sourceLineSelects = this.element.querySelectorAll('select[name*="[sourceLine]"]')

        // Si aucun select n'est trouvé, attendre un peu et réessayer plusieurs fois
        if (sourceLineSelects.length === 0) {
            let attempts = 0
            const maxAttempts = 10
            const checkInterval = setInterval(() => {
                attempts++
                sourceLineSelects = this.element.querySelectorAll('select[name*="[sourceLine]"]')

                if (sourceLineSelects.length > 0 || attempts >= maxAttempts) {
                    clearInterval(checkInterval)
                    if (sourceLineSelects.length > 0) {
                        this.updateSelectsWithLines(sourceLineSelects, lines)
                    }
                }
            }, 100)
            return
        }

        this.updateSelectsWithLines(sourceLineSelects, lines)
    }

    /**
     * Met à jour les selects avec les lignes
     */
    updateSelectsWithLines(sourceLineSelects, lines) {
        sourceLineSelects.forEach((select) => {
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
        let quoteId = null

        if (quoteSelect) {
            if (quoteSelect.disabled) {
                // Si le select est disabled, récupérer l'ID depuis le hidden input
                const hiddenInput = this.element.querySelector(`input[type="hidden"][name*="[quote]"]`)
                quoteId = hiddenInput ? hiddenInput.value : (quoteSelect.dataset.quoteId || null)
            } else {
                quoteId = quoteSelect.value
            }
        }

        // Si on a déjà les lignes en cache, les utiliser directement
        if (this.quoteLines && this.currentQuoteId === quoteId) {
            this.updateSourceLineSelects(this.quoteLines)
        } else if (quoteId) {
            // Sinon, recharger les lignes
            this.handleQuoteChange({ target: { value: quoteId } })
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

