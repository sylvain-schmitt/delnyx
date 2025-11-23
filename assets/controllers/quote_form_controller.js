import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le formulaire de devis
 * Gère le remplissage automatique du SIREN et la gestion des lignes
 */
export default class extends Controller {
    static targets = ["linesContainer", "lineTemplate"]

    connect() {
        // Écouter les changements sur le select client
        const clientSelect = this.element.querySelector('select[name*="[client]"]')
        if (clientSelect) {
            clientSelect.addEventListener('change', (e) => this.handleClientChange(e))
            // Pré-remplir si un client est déjà sélectionné (mode édition)
            if (clientSelect.value) {
                this.handleClientChange({ target: clientSelect })
            }
        }

        // Note: La gestion de l'affichage/masquage des champs tvaRate est maintenant gérée par le contrôleur tva-per-line
        // On garde cette méthode pour compatibilité mais elle ne sera plus utilisée si tva-per-line est présent
    }

    /**
     * Remplit automatiquement le SIREN et l'adresse quand un client est sélectionné
     */
    async handleClientChange(event) {
        const clientId = event.target.value
        const sirenInput = this.element.querySelector('input[name*="[sirenClient]"]')
        const adresseInput = this.element.querySelector('textarea[name*="[adresseLivraison]"]')

        // TOUJOURS vider les champs d'abord pour éviter de garder les anciennes valeurs
        if (sirenInput) {
            sirenInput.value = ''
        }
        if (adresseInput) {
            adresseInput.value = ''
        }

        if (!clientId) {
            return
        }

        try {
            const response = await fetch(`/admin/quote/api/client/${clientId}`)
            if (!response.ok) {
                return
            }

            const data = await response.json()

            // Pré-remplir le SIREN (seulement si le client en a un)
            if (data.siren && sirenInput) {
                sirenInput.value = data.siren
                sirenInput.dispatchEvent(new Event('input'))
            }

            // Pré-remplir l'adresse de livraison (seulement si le client en a une)
            if (data.adresseLivraison && adresseInput) {
                adresseInput.value = data.adresseLivraison
                adresseInput.dispatchEvent(new Event('input'))
            }
        } catch (error) {
            // Erreur silencieuse
        }
    }

    /**
     * Ajoute une nouvelle ligne au devis
     */
    addLine(event) {
        event.preventDefault()

        // Vérifier si le bouton est désactivé
        const button = event.target.closest('button') || event.target
        if (button && (button.disabled || button.style.display === 'none' || !button.hasAttribute('data-action'))) {
            return
        }

        // Vérifier si un devis est associé (pour les factures) - empêcher l'ajout de lignes
        const dataHasQuoteInElement = this.element.querySelector('[data-has-quote]') !== null
        const dataHasQuoteInParent = this.element.closest('[data-has-quote]') !== null
        const hasQuote = dataHasQuoteInElement || dataHasQuoteInParent

        if (hasQuote) {
            return
        }

        if (!this.hasLinesContainerTarget || !this.hasLineTemplateTarget) {
            return
        }

        const template = this.lineTemplateTarget
        const newLine = template.content.cloneNode(true)

        // Générer un index unique pour la nouvelle ligne
        const index = this.linesContainerTarget.children.length
        const newLineElement = newLine.querySelector('[data-line-index]')

        if (newLineElement) {
            // Remplacer les placeholders dans les noms des champs
            const inputs = newLineElement.querySelectorAll('input, select, textarea')
            inputs.forEach(input => {
                if (input.name) {
                    input.name = input.name.replace('__name__', index)
                    input.id = input.id.replace('__name__', index)
                }
                // Ajouter l'attribut pour la validation Stimulus
                input.setAttribute('data-admin-form-target', 'field')
            })

            // Remplacer les attributs for des labels
            const labels = newLineElement.querySelectorAll('label')
            labels.forEach(label => {
                if (label.getAttribute('for')) {
                    label.setAttribute('for', label.getAttribute('for').replace('__name__', index))
                }
            })
        }

        this.linesContainerTarget.appendChild(newLine)

        // Connecter les nouveaux champs au contrôleur de validation
        this.connectValidationToNewFields(newLineElement)

        // Note: L'état de usePerLineTva est maintenant géré par le contrôleur tva-per-line
        // qui observe les changements dans le DOM et met à jour automatiquement les nouvelles lignes
    }

    /**
     * Connecte les nouveaux champs à la validation Stimulus
     */
    connectValidationToNewFields(lineElement) {
        if (!lineElement) return

        // Trouver le contrôleur admin-form
        const form = this.element.querySelector('[data-controller*="admin-form"]')
        if (!form) return

        // Utiliser Stimulus pour obtenir le contrôleur
        const application = this.application
        const formController = application.getControllerForElementAndIdentifier(form, 'admin-form')

        if (formController) {
            // Les nouveaux champs ont déjà l'attribut data-admin-form-target="field"
            // Mais Stimulus ne les détecte pas automatiquement car ils sont ajoutés après le connect()
            // On doit ajouter les écouteurs manuellement
            const fields = lineElement.querySelectorAll('input, select, textarea')
            fields.forEach(field => {
                // S'assurer que l'attribut target est présent
                if (!field.hasAttribute('data-admin-form-target')) {
                    field.setAttribute('data-admin-form-target', 'field')
                }

                // Ajouter les écouteurs d'événements manuellement (comme dans setupFormValidation)
                field.addEventListener('blur', () => {
                    if (formController.validateField) {
                        formController.validateField(field)
                    }
                })

                // Nettoyage des erreurs pendant la saisie
                if (!field.type || field.type !== 'file') {
                    field.addEventListener('input', () => {
                        if (field.classList.contains('is-invalid')) {
                            if (formController.validateField) {
                                formController.validateField(field)
                            }
                        } else {
                            if (formController.clearFieldError) {
                                formController.clearFieldError(field)
                            }
                        }
                    })
                }
            })
        }
    }

    /**
     * Supprime une ligne du devis
     */
    removeLine(event) {
        event.preventDefault()

        // Vérifier si un devis est associé (pour les factures) - empêcher la suppression de lignes
        const dataHasQuoteInElement = this.element.querySelector('[data-has-quote]') !== null
        const dataHasQuoteInParent = this.element.closest('[data-has-quote]') !== null
        const hasQuote = dataHasQuoteInElement || dataHasQuoteInParent

        if (hasQuote) {
            return
        }

        const lineElement = event.target.closest('[data-line-index]')
        if (lineElement) {
            lineElement.remove()
        }
    }

    /**
     * Affiche ou masque les champs tvaRate dans les lignes selon usePerLineTva
     */
    toggleTvaRateFields() {
        const usePerLineTvaCheckbox = this.element.querySelector('input[name*="[usePerLineTva]"]')
        if (!usePerLineTvaCheckbox) return

        const enabled = usePerLineTvaCheckbox.checked === true
        const tvaRateWrappers = this.element.querySelectorAll('[data-tva-settings-target="rateWrapper"]')

        tvaRateWrappers.forEach(wrapper => {
            if (enabled) {
                wrapper.classList.remove('hidden')
                wrapper.style.display = ''
            } else {
                wrapper.classList.add('hidden')
                wrapper.style.display = 'none'
            }
        })
    }
}

