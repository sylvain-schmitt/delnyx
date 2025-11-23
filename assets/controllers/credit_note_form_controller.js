import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le formulaire d'avoir
 * Gère l'affichage dynamique des lignes de la facture sélectionnée
 */
export default class extends Controller {
    currentInvoiceData = null
    linesObserver = null

    disconnect() {
        if (this.invoicePollingInterval) {
            clearInterval(this.invoicePollingInterval)
        }
        if (this.linesObserver) {
            this.linesObserver.disconnect()
        }
    }

    connect() {
        // Observer pour les nouvelles lignes
        const linesContainer = this.element.querySelector('.credit-note-lines-collection') ||
            this.element.querySelector('[data-collection-holder]') ||
            this.element.querySelector('.lines-container') ||
            document.querySelector('#credit_note_lines')

        if (linesContainer) {
            console.log('CreditNoteFormController: Lines container found, attaching observer')
            this.linesObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1) {
                            // Vérifier si le noeud ajouté contient un select sourceLine
                            // Le noeud peut être la ligne elle-même ou un conteneur
                            const select = node.matches('select[name*="[sourceLine]"]') ? node : node.querySelector('select[name*="[sourceLine]"]')

                            if (select && this.currentInvoiceData) {
                                console.log('CreditNoteFormController: New line added, updating select options')
                                this.updateSelectOptions(select, this.currentInvoiceData.lines)
                            }
                        }
                    })
                })
            })
            this.linesObserver.observe(linesContainer, { childList: true, subtree: true })
        } else {
            console.warn('CreditNoteFormController: Lines container NOT found')
        }

        // Attendre un peu que le DOM soit complètement chargé
        setTimeout(() => {
            const invoiceSelect = this.element.querySelector('select[name*="[invoice]"]')

            // Si le select est désactivé, c'est qu'on est en édition ou que l'avoir est verrouillé
            // Dans ce cas, l'affichage statique (Twig) prend le relais
            if (invoiceSelect?.disabled) {
                return
            }

            // Écouter les changements sur le select facture
            // Chercher dans l'élément et dans le document au cas où
            let invoiceSelectForListener = invoiceSelect || this.element.querySelector('select[name*="[invoice]"]')
            if (!invoiceSelectForListener) {
                invoiceSelectForListener = document.querySelector('select[name*="[invoice]"]')
            }

            if (invoiceSelectForListener && !invoiceSelectForListener.disabled) {
                // Écouter les changements sur le select original
                const handleChange = (e) => {
                    const newInvoiceId = e.target.value || invoiceSelectForListener.value
                    if (newInvoiceId !== this.currentInvoiceId) {
                        this.currentInvoiceId = newInvoiceId
                        this.handleInvoiceChange({ target: invoiceSelectForListener })
                    }
                }
                invoiceSelectForListener.addEventListener('change', handleChange)

                // Écouter aussi les clics sur les options dans select-search
                const searchInput = this.element.querySelector('input[data-sync-with*="[invoice]"]')
                if (searchInput) {
                    const optionsContainer = searchInput.parentElement?.querySelector('[id^="options-"]')
                    if (optionsContainer) {
                        optionsContainer.addEventListener('click', (e) => {
                            const optionDiv = e.target.closest('[data-value]')
                            if (optionDiv) {
                                setTimeout(() => {
                                    handleChange({ target: invoiceSelectForListener })
                                }, 50)
                            }
                        })
                    }

                    // Polling léger en secours
                    this.invoicePollingInterval = setInterval(() => {
                        if (invoiceSelectForListener.disabled) {
                            clearInterval(this.invoicePollingInterval)
                            return
                        }
                        const currentValue = invoiceSelectForListener.value
                        if (currentValue !== this.currentInvoiceId) {
                            this.currentInvoiceId = currentValue
                            handleChange({ target: invoiceSelectForListener })
                        }
                    }, 500)
                }

                // Si une facture est déjà sélectionnée, déclencher le changement
                if (invoiceSelectForListener.value) {
                    this.currentInvoiceId = invoiceSelectForListener.value
                    this.handleInvoiceChange({ target: invoiceSelectForListener })
                }
            }
        }, 100)
    }

    /**
     * Affiche les lignes de la facture quand une facture est sélectionnée
     */
    async handleInvoiceChange(event) {
        const invoiceId = event.target.value

        // Supprimer l'affichage existant
        this.removeExistingLinesDisplay()
        this.currentInvoiceData = null

        if (!invoiceId) {
            this.updateAllSourceLineSelects([])
            return
        }

        try {
            // Récupérer les détails de la facture via API
            const response = await fetch(`/admin/credit-note/api/invoice/${invoiceId}`)
            if (!response.ok) {
                return
            }

            const data = await response.json()
            this.currentInvoiceData = data

            // Afficher les lignes de la facture
            if (data.lines && data.lines.length > 0) {
                this.displayInvoiceLines(data)
                // Mettre à jour les selects existants
                this.updateAllSourceLineSelects(data.lines)
            } else {
                this.updateAllSourceLineSelects([])
            }

            // S'assurer qu'un champ caché existe pour la facture
            const invoiceSelect = this.element.querySelector('select[name*="[invoice]"]')
            if (invoiceSelect) {
                const invoiceSelectName = invoiceSelect.name
                const existingHidden = this.element.querySelector(`input[type="hidden"][name="${invoiceSelectName}"]`)
                if (!existingHidden) {
                    const hiddenInput = document.createElement('input')
                    hiddenInput.type = 'hidden'
                    hiddenInput.name = invoiceSelectName
                    hiddenInput.value = invoiceId
                    hiddenInput.id = invoiceSelect.id + '_hidden'
                    invoiceSelect.parentElement.appendChild(hiddenInput)
                } else {
                    existingHidden.value = invoiceId
                }
            }

        } catch (error) {
            console.error('Erreur lors de la récupération de la facture:', error)
        }
    }

    /**
     * Met à jour tous les selects sourceLine existants
     */
    updateAllSourceLineSelects(lines) {
        const selects = this.element.querySelectorAll('select[name*="[sourceLine]"]')
        selects.forEach(select => {
            this.updateSelectOptions(select, lines)
        })
    }

    /**
     * Met à jour les options d'un select sourceLine
     */
    updateSelectOptions(select, lines) {
        // Sauvegarder la valeur actuelle si possible
        const currentValue = select.value

        // Vider le select
        select.innerHTML = ''

        // Option par défaut
        const defaultOption = document.createElement('option')
        defaultOption.value = ''
        defaultOption.textContent = 'Nouvelle ligne (pas de correction)'
        select.appendChild(defaultOption)

        // Ajouter les lignes de la facture
        lines.forEach(line => {
            const option = document.createElement('option')
            option.value = line.id

            // Format: Description - Qté x Prix = Total HT
            // Note: line.unitPrice et line.totalHt sont des chaînes ou nombres
            const unitPrice = parseFloat(line.unitPrice).toFixed(2).replace('.', ',')
            const totalHt = parseFloat(line.totalHt).toFixed(2).replace('.', ',')

            option.textContent = `${line.description} - ${line.quantity} × ${unitPrice} € = ${totalHt} € HT`
            select.appendChild(option)
        })

        // Restaurer la valeur si elle existe toujours dans les nouvelles options
        if (currentValue) {
            // Vérifier si la valeur existe dans les nouvelles options
            const optionExists = Array.from(select.options).some(opt => opt.value === currentValue)
            if (optionExists) {
                select.value = currentValue
            }
        }
    }

    /**
     * Affiche les lignes de la facture en lecture seule
     */
    displayInvoiceLines(invoiceData) {
        // Créer le conteneur
        const container = document.createElement('div')
        container.className = 'space-y-4 mb-6'
        container.setAttribute('data-dynamic-invoice-lines', 'true')

        // Titre
        const title = document.createElement('h3')
        title.className = 'text-lg font-semibold text-white flex items-center gap-2'
        title.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text w-5 h-5"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
            Lignes de la facture d'origine (référence - lecture seule)
        `
        container.appendChild(title)

        // Carte des lignes
        const card = document.createElement('div')
        card.className = 'bg-white/5 border border-white/20 rounded-lg p-4'

        const linesContainer = document.createElement('div')
        linesContainer.className = 'space-y-3'

        // Ajouter chaque ligne
        invoiceData.lines.forEach(line => {
            const lineDiv = document.createElement('div')
            lineDiv.className = 'flex items-center justify-between text-sm py-2 border-b border-white/10 last:border-0'

            const leftDiv = document.createElement('div')
            leftDiv.className = 'flex-1'

            const description = document.createElement('p')
            description.className = 'text-white font-medium'
            description.textContent = line.description

            const details = document.createElement('p')
            details.className = 'text-white/60 text-xs'
            let detailsText = `Qté: ${line.quantity} × ${parseFloat(line.unitPrice).toFixed(2).replace('.', ',')} €`
            if (line.tvaRate) {
                detailsText += ` (TVA ${line.tvaRate}%)`
            }
            details.textContent = detailsText

            leftDiv.appendChild(description)
            leftDiv.appendChild(details)

            const rightDiv = document.createElement('div')
            rightDiv.className = 'text-right'

            const total = document.createElement('p')
            total.className = 'text-white font-semibold'
            total.textContent = `${parseFloat(line.totalHt).toFixed(2).replace('.', ',')} €`

            const htLabel = document.createElement('p')
            htLabel.className = 'text-white/60 text-xs'
            htLabel.textContent = 'HT'

            rightDiv.appendChild(total)
            rightDiv.appendChild(htLabel)

            lineDiv.appendChild(leftDiv)
            lineDiv.appendChild(rightDiv)
            linesContainer.appendChild(lineDiv)
        })

        // Total
        const totalDiv = document.createElement('div')
        totalDiv.className = 'pt-2 border-t border-white/20 mt-2'
        totalDiv.innerHTML = `
            <div class="flex items-center justify-between">
                <span class="text-white font-semibold">Total facture initial</span>
                <span class="text-white font-bold">${invoiceData.montantTTCFormate}</span>
            </div>
        `
        linesContainer.appendChild(totalDiv)

        card.appendChild(linesContainer)
        container.appendChild(card)

        // Insérer après le bloc d'informations générales (qui contient le champ invoice)
        // On cherche le parent du champ invoice, puis on remonte
        const invoiceSelect = this.element.querySelector('select[name*="[invoice]"]')
        const generalInfoBlock = invoiceSelect?.closest('.space-y-4')

        if (generalInfoBlock) {
            generalInfoBlock.parentNode.insertBefore(container, generalInfoBlock.nextSibling)
        } else {
            // Fallback: insérer au début du formulaire
            this.element.insertBefore(container, this.element.firstChild)
        }
    }

    /**
     * Supprime l'affichage dynamique des lignes
     */
    removeExistingLinesDisplay() {
        const existing = this.element.querySelector('[data-dynamic-invoice-lines]')
        if (existing) {
            existing.remove()
        }
    }
}

