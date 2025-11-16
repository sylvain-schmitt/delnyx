import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le formulaire de facture
 * Gère le pré-remplissage automatique depuis un devis sélectionné
 */
export default class extends Controller {
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
                // Appliquer readonly aux champs de paiement pré-remplis côté serveur
                this.lockPaymentFieldsFromQuote()
                return
            }

            // Écouter les changements sur le select devis
            // Chercher dans l'élément et dans le document au cas où
            let quoteSelectForListener = quoteSelect || this.element.querySelector('select[name*="[quote]"]')
            if (!quoteSelectForListener) {
                quoteSelectForListener = document.querySelector('select[name*="[quote]"]')
            }

            if (quoteSelectForListener && !quoteSelectForListener.disabled) {
                quoteSelectForListener.addEventListener('change', (e) => this.handleQuoteChange(e))

                // Si un devis est déjà sélectionné (mais pas encore associé), déclencher le changement
                if (quoteSelectForListener.value && !hasQuote) {
                    this.handleQuoteChange({ target: quoteSelectForListener })
                }
            }
        }, 100)
    }

    /**
     * Pré-remplit les champs de la facture quand un devis est sélectionné
     */
    async handleQuoteChange(event) {
        const quoteId = event.target.value

        if (!quoteId) {
            // Si aucun devis sélectionné, ne rien faire
            return
        }

        // Vérifier si un devis est déjà associé (lignes en lecture seule)
        const dataHasQuote = this.element.querySelector('[data-has-quote]') !== null
        const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
        const quoteSelectDisabled = quoteSelect?.disabled === true

        // Vérifier s'il y a déjà des lignes dans le conteneur
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
        const existingLines = linesContainer?.querySelectorAll('[data-line-index]') || []
        const hasExistingLines = existingLines.length > 0

        // Si le select est désactivé OU si data-has-quote est présent, c'est que le devis est déjà associé côté serveur
        // Dans ce cas, les lignes sont déjà pré-remplies côté serveur
        const hasQuoteFromServer = dataHasQuote || quoteSelectDisabled

        // Si un devis est déjà associé côté serveur (data-has-quote ou select désactivé), ne pas pré-remplir
        // Les lignes sont déjà pré-remplies côté serveur
        if (hasQuoteFromServer) {
            return
        }

        // Si des lignes existent déjà, ne pas les supprimer et ne pas pré-remplir
        if (hasExistingLines) {
            return
        }

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

            // Pré-remplir les lignes de la facture SEULEMENT si aucune ligne n'existe déjà
            const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
            const existingLines = linesContainer?.querySelectorAll('[data-line-index]') || []

            if (existingLines.length === 0 && data.lines && data.lines.length > 0) {
                this.populateInvoiceLines(data.lines)
            }
        } catch (error) {
            // Erreur silencieuse
        }
    }

    /**
     * Remplit les lignes de la facture avec les lignes du devis
     */
    populateInvoiceLines(lines) {
        // Vérifier si un devis est déjà associé côté serveur (lignes en lecture seule)
        const dataHasQuote = this.element.querySelector('[data-has-quote]') !== null
        const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
        const quoteSelectDisabled = quoteSelect?.disabled === true

        // Si data-has-quote est présent OU si le select est désactivé, c'est que le devis est déjà associé côté serveur
        // Dans ce cas, les lignes sont déjà pré-remplies côté serveur et on ne doit pas les toucher
        const hasQuoteFromServer = dataHasQuote || quoteSelectDisabled

        // Si un devis est déjà associé côté serveur, ne pas pré-remplir (les lignes sont déjà là)
        if (hasQuoteFromServer) {
            return
        }

        // Le template utilise quote-form pour les lignes, chercher ce conteneur
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
        if (!linesContainer) {
            return
        }

        // Vérifier s'il y a déjà des lignes (pré-remplies côté serveur)
        const existingLines = linesContainer.querySelectorAll('[data-line-index]')
        if (existingLines.length > 0) {
            return
        }

        // Vider les lignes existantes seulement si aucune ligne n'existe déjà
        existingLines.forEach(line => line.remove())

        // Trouver le bouton d'ajout de ligne (utilise quote-form)
        const addButton = this.element.querySelector('[data-action*="quote-form#addLine"]')
        if (!addButton || addButton.style.display === 'none' || addButton.disabled) {
            return
        }

        // Ajouter chaque ligne du devis
        lines.forEach((lineData, index) => {
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
                        descriptionInput.value = lineData.description || ''
                        descriptionInput.dispatchEvent(new Event('input', { bubbles: true }))
                    }

                    if (quantityInput) {
                        quantityInput.value = lineData.quantity || 1
                        quantityInput.dispatchEvent(new Event('input', { bubbles: true }))
                    }

                    if (unitPriceInput) {
                        unitPriceInput.value = lineData.unitPrice || '0.00'
                        unitPriceInput.dispatchEvent(new Event('input', { bubbles: true }))
                    }

                    if (tvaRateSelect && lineData.tvaRate) {
                        tvaRateSelect.value = lineData.tvaRate
                        tvaRateSelect.dispatchEvent(new Event('change', { bubbles: true }))
                    }

                    if (tariffSelect && lineData.tariffId) {
                        tariffSelect.value = lineData.tariffId
                        tariffSelect.dispatchEvent(new Event('change', { bubbles: true }))
                    }

                    // Déclencher le recalcul du total HT via le contrôleur admin-form
                    if (unitPriceInput && quantityInput) {
                        setTimeout(() => {
                            unitPriceInput.dispatchEvent(new Event('blur', { bubbles: true }))
                            quantityInput.dispatchEvent(new Event('blur', { bubbles: true }))
                        }, 100)
                    }
                }
            }, 200 * (index + 1)) // Délai progressif pour chaque ligne
        })

        // Après avoir ajouté toutes les lignes, les transformer en tableau et les verrouiller
        setTimeout(() => {
            this.lockInvoiceLinesFromQuote()
            // Transformer les lignes en tableau si nécessaire
            this.convertLinesToTable()
        }, 200 * (lines.length + 1))
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

        // Supprimer complètement le bouton "Ajouter une ligne" au lieu de le cacher
        const addButton = this.element.querySelector('[data-action*="quote-form#addLine"]')
        if (addButton) {
            addButton.remove()
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

        // Ajouter un attribut data-has-quote au formulaire principal pour que quote_form_controller détecte qu'un devis est associé
        // Chercher le div principal du formulaire (celui qui contient data-controller="invoice-form")
        const formDiv = this.element.closest('[data-controller*="invoice-form"]') || this.element
        if (formDiv) {
            formDiv.setAttribute('data-has-quote', 'true')
        }

        // Ajouter aussi l'attribut au conteneur des lignes pour être sûr
        if (linesContainer) {
            linesContainer.setAttribute('data-has-quote', 'true')
            linesContainer.closest('[data-controller*="quote-form"]')?.setAttribute('data-has-quote', 'true')
        }

        // Ajouter l'attribut à tous les parents jusqu'à trouver le contrôleur quote-form
        let current = linesContainer
        while (current && current !== document.body) {
            if (current.hasAttribute('data-controller') && current.getAttribute('data-controller').includes('quote-form')) {
                current.setAttribute('data-has-quote', 'true')
                break
            }
            current = current.parentElement
        }

        // Désactiver le select du devis pour indiquer qu'il est verrouillé
        const quoteSelect = this.element.querySelector('select[name*="[quote]"]')
        if (quoteSelect) {
            quoteSelect.disabled = true
        }

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
}

