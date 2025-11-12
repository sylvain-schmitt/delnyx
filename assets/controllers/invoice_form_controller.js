import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le formulaire de facture
 * Gère le pré-remplissage automatique depuis un devis sélectionné
 */
export default class extends Controller {
    connect() {
        // Attendre un peu que le DOM soit complètement chargé
        setTimeout(() => {
            // Écouter les changements sur le select devis
            // Chercher dans l'élément et dans le document au cas où
            let quoteSelect = this.element.querySelector('select[name*="[quote]"]')
            if (!quoteSelect) {
                quoteSelect = document.querySelector('select[name*="[quote]"]')
            }
            
            if (quoteSelect) {
                quoteSelect.addEventListener('change', (e) => this.handleQuoteChange(e))
                
                // Si un devis est déjà sélectionné (mode édition avec devis pré-rempli), déclencher le changement
                if (quoteSelect.value) {
                    this.handleQuoteChange({ target: quoteSelect })
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

        try {
            const response = await fetch(`/admin/invoice/api/quote/${quoteId}`)
            if (!response.ok) {
                return
            }

            const data = await response.json()

            // Pré-remplir le client
            const clientSelect = this.element.querySelector('select[name*="[client]"]')
            if (data.clientId && clientSelect) {
                clientSelect.value = data.clientId
                clientSelect.dispatchEvent(new Event('change', { bubbles: true }))
            }

            // Pré-remplir les conditions de paiement
            if (data.conditionsPaiement) {
                const conditionsInput = this.element.querySelector('textarea[name*="[conditionsPaiement]"]')
                if (conditionsInput) {
                    conditionsInput.value = data.conditionsPaiement
                    conditionsInput.dispatchEvent(new Event('input', { bubbles: true }))
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
     */
    populateInvoiceLines(lines) {
        // Le template utilise quote-form pour les lignes, chercher ce conteneur
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
        if (!linesContainer) {
            return
        }

        // Vider les lignes existantes
        const existingLines = linesContainer.querySelectorAll('[data-line-index]')
        existingLines.forEach(line => line.remove())

        // Trouver le bouton d'ajout de ligne (utilise quote-form)
        const addButton = this.element.querySelector('[data-action*="quote-form#addLine"]')
        if (!addButton) {
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
    }
}

