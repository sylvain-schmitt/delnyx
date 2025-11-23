import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le formulaire de facture
 * Gère le pré-remplissage automatique depuis un devis sélectionné
 */
export default class extends Controller {
    currentQuoteId = null
    quotePollingInterval = null

    disconnect() {
        if (this.quotePollingInterval) {
            clearInterval(this.quotePollingInterval)
        }
    }

    connect() {
        // Attendre un peu que le DOM soit complètement chargé
        setTimeout(() => {
            // Vérifier si un devis est déjà associé (lignes en lecture seule)
            const dataHasQuote = this.element.querySelector('[data-has-quote]') !== null
            const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
            const quoteSelectDisabled = quoteSelect?.disabled === true
            let hasQuote = dataHasQuote || quoteSelectDisabled

            // Vérifier aussi si le select a une valeur (même s'il n'est pas désactivé)
            const quoteSelectHasValue = quoteSelect?.value && quoteSelect.value !== ''

            // Si le select a une valeur, considérer qu'un devis est associé
            if (quoteSelectHasValue && !hasQuote) {
                hasQuote = true
            }

            // Si un devis est déjà associé, ne pas activer le pré-remplissage automatique
            // Les lignes sont déjà pré-remplies côté serveur et ne doivent pas être modifiées
            if (hasQuote) {
                this.isServerLocked = true
                // Appliquer readonly aux champs de paiement pré-remplis côté serveur
                this.lockPaymentFieldsFromQuote()
                // Verrouiller également les lignes (cache les boutons d'ajout et de TVA)
                this.lockInvoiceLinesFromQuote()
                return
            } else {
                this.isServerLocked = false
            }

            // Écouter les changements sur le select devis
            // Chercher dans l'élément et dans le document au cas où
            let quoteSelectForListener = quoteSelect || this.element.querySelector('select[name*="[quote]"]')
            if (!quoteSelectForListener) {
                quoteSelectForListener = document.querySelector('select[name*="[quote]"]')
            }

            if (quoteSelectForListener && !quoteSelectForListener.disabled) {
                // Écouter les changements sur le select original
                const handleChange = (e) => {
                    const newQuoteId = e.target.value || quoteSelectForListener.value
                    if (newQuoteId !== this.currentQuoteId) {
                        this.currentQuoteId = newQuoteId
                        this.handleQuoteChange({ target: quoteSelectForListener })
                    }
                }
                quoteSelectForListener.addEventListener('change', handleChange)

                // Écouter aussi les clics sur les options dans select-search
                const searchInput = this.element.querySelector('input[data-sync-with*="[quote]"]')
                if (searchInput) {
                    const optionsContainer = searchInput.parentElement?.querySelector('[id^="options-"]')
                    if (optionsContainer) {
                        optionsContainer.addEventListener('click', (e) => {
                            const optionDiv = e.target.closest('[data-value]')
                            if (optionDiv) {
                                setTimeout(() => {
                                    handleChange({ target: quoteSelectForListener })
                                }, 50)
                            }
                        })
                    }

                    // Polling léger en secours (vérifier toutes les 500ms si la valeur a changé)
                    this.quotePollingInterval = setInterval(() => {
                        if (quoteSelectForListener.disabled) {
                            clearInterval(this.quotePollingInterval)
                            return
                        }
                        const currentValue = quoteSelectForListener.value
                        if (currentValue !== this.currentQuoteId) {
                            this.currentQuoteId = currentValue
                            handleChange({ target: quoteSelectForListener })
                        }
                    }, 500)
                }

                // Si un devis est déjà sélectionné (mais pas encore associé), déclencher le changement
                if (quoteSelectForListener.value && !hasQuote) {
                    this.currentQuoteId = quoteSelectForListener.value
                    this.handleQuoteChange({ target: quoteSelectForListener })
                }
            }
        }, 100)
    }

    /**
     * Pré-remplit les champs de la facture quand un devis est sélectionné
     * Met à jour en temps réel : supprime les lignes existantes et les remplace
     */
    async handleQuoteChange(event) {
        const quoteId = event.target.value

        // Si un devis est déjà associé côté serveur (data-has-quote ou select désactivé), ne pas pré-remplir
        // Les lignes sont déjà pré-remplies côté serveur
        if (this.isServerLocked) {
            return
        }

        // Si aucun devis sélectionné, supprimer les lignes existantes et réinitialiser les champs
        if (!quoteId) {
            this.clearQuoteData()
            return
        }

        // Supprimer les lignes existantes et le tableau si présent (mise à jour en temps réel)
        this.removeExistingLines()

        try {
            const response = await fetch(`/admin/invoice/api/quote/${quoteId}`)
            if (!response.ok) {
                return
            }

            const data = await response.json()

            // S'assurer qu'un champ caché existe pour le devis (pour que la valeur soit soumise même si le select est désactivé)
            const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
            if (quoteSelect) {
                const quoteSelectName = quoteSelect.name
                const existingHidden = this.element.querySelector(`input[type="hidden"][name="${quoteSelectName}"]`)
                if (!existingHidden) {
                    const hiddenInput = document.createElement('input')
                    hiddenInput.type = 'hidden'
                    hiddenInput.name = quoteSelectName
                    hiddenInput.value = quoteId
                    hiddenInput.id = quoteSelect.id + '_hidden'
                    quoteSelect.parentElement.appendChild(hiddenInput)
                } else {
                    // Mettre à jour la valeur si le champ caché existe déjà
                    existingHidden.value = quoteId
                }
            }

            // Pré-remplir le client
            const clientSelect = this.element.querySelector('select[name*="[client]"]')
            if (data.clientId && clientSelect) {
                clientSelect.value = data.clientId
                clientSelect.dispatchEvent(new Event('change', { bubbles: true }))
            }

            // Pré-remplir les conditions de paiement (seulement si une valeur existe)
            if (data.conditionsPaiement && data.conditionsPaiement.trim() !== '') {
                const conditionsInput = this.element.querySelector('textarea[name*="[conditionsPaiement]"]')
                if (conditionsInput) {
                    conditionsInput.value = data.conditionsPaiement
                    conditionsInput.dispatchEvent(new Event('input', { bubbles: true }))
                    // Rendre en lecture seule seulement si une valeur a été pré-remplie
                    conditionsInput.setAttribute('readonly', 'readonly')
                    conditionsInput.disabled = false // Utiliser readonly au lieu de disabled pour que la valeur soit soumise
                }
            }

            // Pré-remplir le montant d'accompte (seulement si une valeur existe)
            if (data.montantAcompte && parseFloat(data.montantAcompte) > 0) {
                const acompteInput = this.element.querySelector('input[name*="[montantAcompte]"]')
                if (acompteInput) {
                    acompteInput.value = data.montantAcompte
                    acompteInput.dispatchEvent(new Event('input', { bubbles: true }))
                    // Rendre en lecture seule seulement si une valeur a été pré-remplie
                    acompteInput.setAttribute('readonly', 'readonly')
                    acompteInput.disabled = false // Utiliser readonly au lieu de disabled pour que la valeur soit soumise
                }
            }

            // Pré-remplir le délai de paiement (seulement si une valeur existe)
            if (data.delaiPaiement !== null && data.delaiPaiement !== undefined && data.delaiPaiement !== '') {
                const delaiInput = this.element.querySelector('input[name*="[delaiPaiement]"]')
                if (delaiInput) {
                    delaiInput.value = data.delaiPaiement
                    delaiInput.dispatchEvent(new Event('input', { bubbles: true }))
                    // Rendre en lecture seule seulement si une valeur a été pré-remplie
                    delaiInput.setAttribute('readonly', 'readonly')
                    delaiInput.disabled = false // Utiliser readonly au lieu de disabled pour que la valeur soit soumise
                }
            }

            // Pré-remplir les lignes de la facture
            if (data.lines && data.lines.length > 0) {
                this.populateInvoiceLines(data.lines)
            }


        } catch (error) {
            // Erreur silencieuse
        }
    }



    /**
     * Remplit les lignes de la facture avec les lignes du devis
     * Amélioré pour ajouter toutes les lignes correctement
     */
    populateInvoiceLines(lines) {
        // Si un devis est déjà associé côté serveur, ne pas pré-remplir (les lignes sont déjà là)
        if (this.isServerLocked) {
            return
        }

        // Le template utilise quote-form pour les lignes, chercher ce conteneur
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
        if (!linesContainer) {
            return
        }

        // Trouver le bouton d'ajout de ligne (utilise quote-form)
        const addButton = this.element.querySelector('[data-action*="quote-form#addLine"]')
        if (!addButton || addButton.style.display === 'none' || addButton.disabled) {
            return
        }

        // Trouver le bouton de soumission pour le désactiver pendant le chargement
        const submitButton = this.element.querySelector('[data-admin-form-target="submit"]')
        let originalSubmitText = ''
        if (submitButton) {
            submitButton.disabled = true
            originalSubmitText = submitButton.innerHTML
            submitButton.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Chargement des lignes...
            `
        }

        // Supprimer toutes les lignes existantes avant d'ajouter les nouvelles
        const existingLines = linesContainer.querySelectorAll('[data-line-index]')
        existingLines.forEach(line => line.remove())

        // Utiliser une fonction récursive pour ajouter les lignes une par une
        const addLine = (lineIndex) => {
            if (lineIndex >= lines.length) {
                // Toutes les lignes ont été ajoutées
                setTimeout(() => {
                    this.lockInvoiceLinesFromQuote()
                    this.convertLinesToTable()

                    // Réactiver le bouton de soumission
                    if (submitButton) {
                        submitButton.disabled = false
                        submitButton.innerHTML = originalSubmitText
                    }
                }, 100)
                return
            }

            const currentLineData = lines[lineIndex]

            // Déclencher le clic sur le bouton "Ajouter une ligne"
            addButton.click()

            // Attendre que la ligne soit ajoutée
            setTimeout(() => {
                const newLines = linesContainer.querySelectorAll('[data-line-index]')
                const newLine = newLines[newLines.length - 1]

                if (newLine) {
                    // Remplir les champs de la ligne
                    const descriptionInput = newLine.querySelector('input[name*="[description]"]')
                    const quantityInput = newLine.querySelector('input[name*="[quantity]"]')
                    const unitPriceInput = newLine.querySelector('input[name*="[unitPrice]"]')
                    const tvaRateSelect = newLine.querySelector('select[name*="[tvaRate]"]')
                    const tariffSelect = newLine.querySelector('select[name*="[tariff]"]')

                    if (descriptionInput) {
                        descriptionInput.value = currentLineData.description || ''
                        descriptionInput.dispatchEvent(new Event('input', { bubbles: true }))
                    }

                    if (quantityInput) {
                        quantityInput.value = currentLineData.quantity || 1
                        quantityInput.dispatchEvent(new Event('input', { bubbles: true }))
                    }

                    if (unitPriceInput) {
                        unitPriceInput.value = currentLineData.unitPrice || '0.00'
                        unitPriceInput.dispatchEvent(new Event('input', { bubbles: true }))
                    }

                    if (tvaRateSelect && currentLineData.tvaRate) {
                        tvaRateSelect.value = currentLineData.tvaRate
                        tvaRateSelect.dispatchEvent(new Event('change', { bubbles: true }))
                    }

                    if (tariffSelect && currentLineData.tariffId) {
                        tariffSelect.value = currentLineData.tariffId
                        tariffSelect.dispatchEvent(new Event('change', { bubbles: true }))
                    }

                    // Déclencher le recalcul du total HT
                    if (unitPriceInput && quantityInput) {
                        setTimeout(() => {
                            unitPriceInput.dispatchEvent(new Event('blur', { bubbles: true }))
                            quantityInput.dispatchEvent(new Event('blur', { bubbles: true }))
                        }, 50)
                    }
                }

                // Ajouter la ligne suivante
                setTimeout(() => addLine(lineIndex + 1), 150)
            }, 100)
        }

        // Démarrer l'ajout des lignes
        if (lines.length > 0) {
            addLine(0)
        } else {
            // Si aucune ligne, réactiver le bouton tout de suite
            if (submitButton) {
                submitButton.disabled = false
                submitButton.innerHTML = originalSubmitText
            }
        }
    }

    /**
     * Désactive les lignes pré-remplies depuis un devis et supprime les boutons d'ajout/suppression
     */
    lockInvoiceLinesFromQuote() {
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
        if (!linesContainer) {
            return
        }

        const lines = linesContainer.querySelectorAll('[data-line-index]')

        // Cacher le bouton "Ajouter une ligne"
        const addButton = this.element.querySelector('[data-invoice-form-target="addLineButton"]') || this.element.querySelector('[data-action*="quote-form#addLine"]')
        if (addButton) {
            addButton.classList.add('!hidden')
        }

        // Cacher le bouton "Appliquer la TVA par ligne"
        const tvaButton = this.element.querySelector('[data-invoice-form-target="tvaPerLineButton"]')
        if (tvaButton) {
            tvaButton.classList.add('!hidden')
        }

        // Supprimer les boutons "Supprimer" de chaque ligne
        lines.forEach((line) => {
            const removeButton = line.querySelector('[data-action*="quote-form#removeLine"]')
            if (removeButton) {
                removeButton.remove()
            }
        })

        // IMPORTANT: Ne pas utiliser 'disabled' car cela empêche la soumission du formulaire
        // Utiliser seulement 'readonly' pour que les valeurs soient soumises
        lines.forEach((line) => {
            const inputs = line.querySelectorAll('input, select, textarea')
            inputs.forEach(input => {
                // Utiliser readonly au lieu de disabled pour que la valeur soit soumise
                input.disabled = false
                input.setAttribute('readonly', 'readonly')
                // Pour les selects, readonly ne fonctionne pas, donc on doit les désactiver
                // mais créer des champs cachés avec les valeurs
                if (input.tagName === 'SELECT') {
                    input.disabled = true
                    // Créer un champ caché avec la valeur du select pour la soumission
                    const hiddenInput = document.createElement('input')
                    hiddenInput.type = 'hidden'
                    hiddenInput.name = input.name
                    hiddenInput.value = input.value
                    input.parentElement.appendChild(hiddenInput)
                }
            })
        })

        // Verrouiller aussi les champs de paiement
        this.lockPaymentFieldsFromQuote()
    }

    /**
     * Verrouille les champs de paiement (montant d'accompte, conditions, délai) en lecture seule
     * Seulement si ils ont une valeur pré-remplie depuis le devis
     */
    lockPaymentFieldsFromQuote() {
        // Montant d'accompte - rendre readonly seulement si une valeur existe
        const acompteInput = this.element.querySelector('input[name*="[montantAcompte]"]')
        if (acompteInput && acompteInput.value && parseFloat(acompteInput.value) > 0) {
            acompteInput.setAttribute('readonly', 'readonly')
            acompteInput.disabled = false // Utiliser readonly au lieu de disabled pour que la valeur soit soumise
        } else if (acompteInput) {
            // Si pas de valeur, retirer readonly au cas où il aurait été ajouté
            acompteInput.removeAttribute('readonly')
        }

        // Conditions de paiement - rendre readonly seulement si une valeur existe
        const conditionsInput = this.element.querySelector('textarea[name*="[conditionsPaiement]"]')
        if (conditionsInput && conditionsInput.value && conditionsInput.value.trim() !== '') {
            conditionsInput.setAttribute('readonly', 'readonly')
            conditionsInput.disabled = false // Utiliser readonly au lieu de disabled pour que la valeur soit soumise
        } else if (conditionsInput) {
            // Si pas de valeur, retirer readonly au cas où il aurait été ajouté
            conditionsInput.removeAttribute('readonly')
        }

        // Délai de paiement - rendre readonly seulement si une valeur existe
        const delaiInput = this.element.querySelector('input[name*="[delaiPaiement]"]')
        if (delaiInput && delaiInput.value && delaiInput.value.trim() !== '') {
            delaiInput.setAttribute('readonly', 'readonly')
            delaiInput.disabled = false // Utiliser readonly au lieu de disabled pour que la valeur soit soumise
        } else if (delaiInput) {
            // Si pas de valeur, retirer readonly au cas où il aurait été ajouté
            delaiInput.removeAttribute('readonly')
        }
    }

    /**
     * Convertit les lignes de formulaire en tableau élégant (quand un devis est sélectionné via JavaScript)
     */
    convertLinesToTable() {
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
        if (!linesContainer) {
            return
        }

        const lines = linesContainer.querySelectorAll('[data-line-index]')
        if (lines.length === 0) {
            return
        }

        // Vérifier si un tableau existe déjà
        if (linesContainer.querySelector('table')) {
            return
        }

        // Créer le tableau
        const tableWrapper = document.createElement('div')
        tableWrapper.className = 'bg-white/5 border border-white/10 rounded-lg overflow-hidden'

        const tableContainer = document.createElement('div')
        tableContainer.className = 'overflow-x-auto'

        const table = document.createElement('table')
        table.className = 'w-full'

        // Créer l'en-tête
        const thead = document.createElement('thead')
        thead.className = 'bg-white/10 border-b border-white/20'
        thead.innerHTML = `
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-white/80 uppercase tracking-wider">Description</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Quantité</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Prix unitaire HT</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Total HT</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Total TTC</th>
            </tr>
        `

        // Créer le corps du tableau
        const tbody = document.createElement('tbody')
        tbody.className = 'divide-y divide-white/10'

        // Vérifier si la TVA est activée et si on utilise la TVA par ligne
        const formDiv = this.element.closest('[data-controller*="invoice-form"]') || this.element
        const isTvaEnabled = formDiv?.dataset.tvaEnabled === 'true'
        const usePerLineTva = formDiv?.dataset.usePerLineTva === 'true'
        const showTvaColumn = isTvaEnabled && usePerLineTva

        // Mettre à jour l'en-tête si la TVA est activée
        if (showTvaColumn) {
            thead.innerHTML = `
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-white/80 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Quantité</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Prix unitaire HT</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">TVA</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Total HT</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-white/80 uppercase tracking-wider">Total TTC</th>
                </tr>
            `
        }

        lines.forEach((line) => {
            const descriptionInput = line.querySelector('input[name*="[description]"]')
            const quantityInput = line.querySelector('input[name*="[quantity]"]')
            const unitPriceInput = line.querySelector('input[name*="[unitPrice]"]')
            const totalHtInput = line.querySelector('input[name*="[totalHt]"]')
            const tvaRateSelect = line.querySelector('select[name*="[tvaRate]"]')

            const description = descriptionInput?.value || 'N/A'
            const quantity = parseFloat(quantityInput?.value || 0)
            // Les valeurs dans les inputs sont déjà en euros (pas en centimes)
            const unitPrice = parseFloat(unitPriceInput?.value || 0)
            // Si totalHtInput n'existe pas, calculer à partir de unitPrice * quantity
            let totalHt = parseFloat(totalHtInput?.value || 0)
            if (totalHt === 0 && unitPrice > 0 && quantity > 0) {
                totalHt = unitPrice * quantity
            }
            const tvaRate = tvaRateSelect?.value ? parseFloat(tvaRateSelect.value) : 0

            // Calculer le total TTC en fonction du taux de TVA
            let totalTtc = totalHt
            if (tvaRate > 0) {
                const tvaAmount = totalHt * (tvaRate / 100)
                totalTtc = totalHt + tvaAmount
            }

            const tr = document.createElement('tr')
            tr.className = 'hover:bg-white/5 transition-colors'

            let rowContent = `
                <td class="px-4 py-3 text-sm text-white/90">
                    <div class="font-medium">${description}</div>
                </td>
                <td class="px-4 py-3 text-sm text-white/90 text-right">${quantity}</td>
                <td class="px-4 py-3 text-sm text-white/90 text-right">${unitPrice.toFixed(2).replace('.', ',')} €</td>
            `

            if (showTvaColumn) {
                rowContent += `<td class="px-4 py-3 text-sm text-white/90 text-right">${tvaRate.toFixed(2).replace('.', ',')}%</td>`
            }

            rowContent += `
                <td class="px-4 py-3 text-sm text-white/90 text-right font-medium">${totalHt.toFixed(2).replace('.', ',')} €</td>
                <td class="px-4 py-3 text-sm text-white/90 text-right font-semibold">${totalTtc.toFixed(2).replace('.', ',')} €</td>
            `

            tr.innerHTML = rowContent
            tbody.appendChild(tr)
        })

        table.appendChild(thead)
        table.appendChild(tbody)
        tableContainer.appendChild(table)
        tableWrapper.appendChild(tableContainer)

        // IMPORTANT: Ne pas vider le conteneur, mais plutôt cacher les lignes existantes
        // et ajouter le tableau par-dessus, pour garder les champs du formulaire fonctionnels
        lines.forEach((line) => {
            // Cacher la ligne au lieu de la supprimer pour garder les champs du formulaire
            line.style.display = 'none'

            // S'assurer que tous les champs de la ligne sont bien soumis
            // (ne pas utiliser disabled, utiliser readonly pour les inputs)
            const inputs = line.querySelectorAll('input[type="text"], input[type="number"], textarea')
            inputs.forEach(input => {
                input.disabled = false
                input.setAttribute('readonly', 'readonly')
            })

            // Pour les selects, créer des champs cachés avec les valeurs
            const selects = line.querySelectorAll('select')
            selects.forEach(select => {
                // Vérifier si un champ caché existe déjà
                const existingHidden = line.querySelector(`input[type="hidden"][name="${select.name}"]`)
                if (!existingHidden) {
                    const hiddenInput = document.createElement('input')
                    hiddenInput.type = 'hidden'
                    hiddenInput.name = select.name
                    hiddenInput.value = select.value || ''
                    select.parentElement.appendChild(hiddenInput)
                }
                select.disabled = true
            })
        })

        // Ajouter le tableau dans le conteneur (sans vider)
        linesContainer.insertBefore(tableWrapper, linesContainer.firstChild)
    }

    /**
     * Supprime les lignes existantes et le tableau si présent
     */
    removeExistingLines() {
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
        if (!linesContainer) {
            return
        }

        // Vider complètement le conteneur
        linesContainer.innerHTML = ''

        // Réafficher le bouton "Ajouter une ligne" s'il était caché
        const addButton = this.element.querySelector('[data-invoice-form-target="addLineButton"]') || this.element.querySelector('[data-action*="quote-form#addLine"]')
        if (addButton) {
            addButton.classList.remove('hidden')
            addButton.classList.remove('!hidden')
            addButton.disabled = false
            // S'assurer que le style display n'est pas resté (si mélangé avec l'ancienne méthode)
            addButton.style.display = ''
        }

        // Réafficher le bouton "Appliquer la TVA par ligne" s'il était caché
        const tvaButton = this.element.querySelector('[data-invoice-form-target="tvaPerLineButton"]')
        if (tvaButton) {
            tvaButton.classList.remove('hidden')
            tvaButton.classList.remove('!hidden')
            // S'assurer que le style display n'est pas resté
            tvaButton.style.display = ''
        }
    }

    /**
     * Réinitialise les champs liés au devis quand aucun devis n'est sélectionné
     */
    clearQuoteData() {
        // Réinitialiser le client (optionnel, on peut le laisser)
        // const clientSelect = this.element.querySelector('select[name*="[client]"]')
        // if (clientSelect) {
        //     clientSelect.value = ''
        // }

        // Réinitialiser les conditions de paiement
        const conditionsInput = this.element.querySelector('textarea[name*="[conditionsPaiement]"]')
        if (conditionsInput) {
            conditionsInput.value = ''
            conditionsInput.removeAttribute('readonly')
        }

        // Réinitialiser le montant d'accompte
        const acompteInput = this.element.querySelector('input[name*="[montantAcompte]"]')
        if (acompteInput) {
            acompteInput.value = ''
            acompteInput.removeAttribute('readonly')
        }

        // Réinitialiser le délai de paiement
        const delaiInput = this.element.querySelector('input[name*="[delaiPaiement]"]')
        if (delaiInput) {
            delaiInput.value = ''
            delaiInput.removeAttribute('readonly')
        }

        // Supprimer les lignes
        this.removeExistingLines()
    }
}

