import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour la validation des formulaires admin
 * Basé sur le contact_controller pour la cohérence
 */
export default class extends Controller {
    static targets = ["form", "field", "submit"]
    static values = {
        maxLength: { type: Number, default: 1000 }
    }

    connect() {
        this.isSubmitting = false
        this.setupFormValidation()
        this.setupFieldAnimations()
        this.setupTurboListeners()
    }

    disconnect() {
        this.isSubmitting = false
        this.removeTurboListeners()
    }

    setupTurboListeners() {
        // Écouter les événements Turbo pour restaurer l'état du bouton en cas d'erreur
        this.turboSubmitEndHandler = (event) => {
            // Si la soumission échoue (pas de redirection), restaurer l'état du bouton
            // Turbo attend une redirection, si on reste sur la même page, c'est une erreur
            if (event.detail && event.detail.success === false) {
                this.resetSubmitButton()
            } else if (event.detail && event.detail.fetchResponse) {
                // Vérifier si la réponse est une redirection
                const response = event.detail.fetchResponse
                // Si c'est une redirection (status 302, 303, 307, 308) ou si redirected est true, c'est un succès
                const isRedirect = response.redirected || 
                                   (response.status >= 300 && response.status < 400) ||
                                   (response.headers && response.headers.get('Location'))
                
                if (!isRedirect && response.status === 200) {
                    // Pas de redirection = erreur de validation, restaurer le bouton
                    setTimeout(() => this.resetSubmitButton(), 100)
                } else if (isRedirect) {
                    // Redirection détectée = succès, le bouton sera restauré par la navigation
                    // Mais on peut aussi le restaurer immédiatement pour éviter le délai
                    setTimeout(() => this.resetSubmitButton(), 50)
                }
            }
        }

        this.turboBeforeFetchResponseHandler = (event) => {
            // Vérifier si la réponse est une erreur
            const response = event.detail.fetchResponse
            if (response && response.status >= 400) {
                this.resetSubmitButton()
            } else if (response && response.status === 200) {
                // Vérifier si c'est une redirection en regardant les headers
                const location = response.headers && response.headers.get('Location')
                const isRedirect = response.redirected || location || (response.status >= 300 && response.status < 400)
                
                if (!isRedirect) {
                    // Pas de redirection = erreur de validation, restaurer le bouton après un court délai
                    setTimeout(() => this.resetSubmitButton(), 100)
                } else {
                    // Redirection détectée = succès, restaurer le bouton immédiatement
                    setTimeout(() => this.resetSubmitButton(), 50)
                }
            }
        }

        // Écouter aussi l'événement turbo:before-visit pour détecter les redirections
        this.turboBeforeVisitHandler = () => {
            // Si une navigation est déclenchée, c'est probablement une redirection après succès
            // Restaurer le bouton immédiatement
            this.resetSubmitButton()
        }

        // Écouter l'événement turbo:frame-load pour détecter les chargements de frame (redirections)
        this.turboFrameLoadHandler = () => {
            // Si une frame se charge, c'est peut-être une redirection
            // Restaurer le bouton au cas où
            setTimeout(() => this.resetSubmitButton(), 100)
        }

        // Écouter sur le formulaire lui-même
        const formElement = this.formTarget || this.element
        if (formElement) {
            formElement.addEventListener('turbo:submit-end', this.turboSubmitEndHandler)
            formElement.addEventListener('turbo:before-fetch-response', this.turboBeforeFetchResponseHandler)
        }
        
        // Écouter sur le document pour les redirections
        document.addEventListener('turbo:before-visit', this.turboBeforeVisitHandler)
        document.addEventListener('turbo:frame-load', this.turboFrameLoadHandler)
        
        // Écouter aussi les erreurs globales Turbo pour restaurer le bouton même en cas d'erreur
        this.turboErrorHandler = () => {
            // En cas d'erreur Turbo, restaurer le bouton après un court délai
            setTimeout(() => this.resetSubmitButton(), 200)
        }
        
        // Écouter les erreurs de soumission de formulaire
        document.addEventListener('turbo:submit-end', (event) => {
            // Si la soumission échoue avec une erreur, restaurer le bouton
            if (event.detail && event.detail.success === false) {
                this.resetSubmitButton()
            }
        })
    }

    removeTurboListeners() {
        const formElement = this.formTarget || this.element
        if (formElement) {
            if (this.turboSubmitEndHandler) {
                formElement.removeEventListener('turbo:submit-end', this.turboSubmitEndHandler)
            }
            if (this.turboBeforeFetchResponseHandler) {
                formElement.removeEventListener('turbo:before-fetch-response', this.turboBeforeFetchResponseHandler)
            }
        }
        
        // Retirer aussi l'écouteur sur le document
        if (this.turboBeforeVisitHandler) {
            document.removeEventListener('turbo:before-visit', this.turboBeforeVisitHandler)
        }
        if (this.turboFrameLoadHandler) {
            document.removeEventListener('turbo:frame-load', this.turboFrameLoadHandler)
        }
        if (this.turboErrorHandler) {
            // Note: on ne peut pas retirer un écouteur anonyme, mais ce n'est pas grave
            // car il sera supprimé avec le contrôleur
        }
    }

    resetSubmitButton() {
        this.isSubmitting = false
        
        // Annuler le timeout de sécurité si le bouton est restauré normalement
        if (this.safetyTimeout) {
            clearTimeout(this.safetyTimeout)
            this.safetyTimeout = null
        }
        
        if (this.submitTarget) {
            this.submitTarget.classList.remove('btn-loading')
            this.submitTarget.disabled = false
            if (this.originalSubmitText) {
                this.submitTarget.textContent = this.originalSubmitText
            }
        }
    }

    setupFormValidation() {
        // Validation en temps réel des champs
        this.fieldTargets.forEach(field => {
            // Validation au blur (quand l'utilisateur quitte le champ)
            field.addEventListener('blur', () => this.validateField(field))

            // Nettoyage des erreurs pendant la saisie (sauf pour les mots de passe)
            if (!field.name || !field.name.includes('plainPassword')) {
                field.addEventListener('input', () => {
                    // Si le champ était invalide, on le revalide en temps réel
                    if (field.classList.contains('is-invalid')) {
                        this.validateField(field)
                    } else {
                        this.clearFieldError(field)
                    }
                })
            } else {
                // Pour les mots de passe, valider en temps réel pour vérifier la correspondance
                field.addEventListener('input', () => {
                    this.validateField(field)
                })
            }
        })
    }

    setupFieldAnimations() {
        // Animations sur focus/blur des champs
        this.fieldTargets.forEach(field => {
            field.addEventListener('focus', () => {
                field.classList.add('scale-[1.02]', 'shadow-lg', 'shadow-blue-500/25')
            })

            field.addEventListener('blur', () => {
                field.classList.remove('scale-[1.02]', 'shadow-lg', 'shadow-blue-500/25')
            })
        })
    }

    validateField(field) {
        const fieldName = field.name
        const value = field.type === 'file' ? field.files.length > 0 : (field.value ? field.value.trim() : '')
        // Pour les selects, vérifier aussi si c'est un champ client (toujours obligatoire)
        // Pour les textarea, vérifier aussi si c'est adresseLivraison (obligatoire)
        const isRequired = field.hasAttribute('required') || field.getAttribute('required') === 'required' ||
            (field.tagName === 'SELECT' && fieldName && fieldName.includes('client')) ||
            (field.tagName === 'TEXTAREA' && fieldName && fieldName.includes('adresseLivraison'))

        // Suppression des erreurs existantes
        this.clearFieldError(field)

        // Validation des champs obligatoires
        if (isRequired) {
            // Pour les checkboxes, on vérifie checked
            if (field.type === 'checkbox') {
                // Les checkboxes ne sont généralement pas obligatoires dans nos formulaires
                // Mais si required, on vérifie checked
                if (!field.checked) {
                    return this.setFieldInvalid(field, 'Ce champ est obligatoire')
                }
            }
            // Pour les selects, vérifier si une valeur est sélectionnée (pas de placeholder)
            else if (field.tagName === 'SELECT') {
                // Vérifier si le select a une valeur valide (pas vide, pas null, pas '0', pas undefined)
                const selectValue = field.value
                if (!selectValue || selectValue === '' || selectValue === null || selectValue === '0' || selectValue === undefined) {
                    // Message personnalisé pour le client
                    const errorMsg = fieldName && fieldName.includes('client') ? 'Le client est obligatoire' : 'Ce champ est obligatoire'
                    return this.setFieldInvalid(field, errorMsg)
                }
            }
            // Pour les fichiers
            else if (field.type === 'file') {
                if (!value) {
                    return this.setFieldInvalid(field, 'Ce champ est obligatoire')
                }
            }
            // Pour les autres champs (input, textarea)
            else if (!value) {
                return this.setFieldInvalid(field, 'Ce champ est obligatoire')
            }
        }

        // Validation selon le type de champ
        let isValid = true
        let errorMessage = ''

        switch (true) {
            case fieldName.includes('nom'):
            case fieldName.includes('prenom'):
                if (!value) {
                    isValid = false
                    errorMessage = 'Ce champ est obligatoire'
                } else if (value.length < 2) {
                    isValid = false
                    errorMessage = 'Au moins 2 caractères requis'
                } else if (value.length > 100) {
                    isValid = false
                    errorMessage = 'Maximum 100 caractères'
                }
                break

            case fieldName.includes('email'):
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
                // Vérifier si le champ est required (pas readonly)
                const isReadonly = field.hasAttribute('readonly')
                // Pour CompanySettings, l'email est optionnel (utilise celui du User en fallback)
                const isRequired = field.hasAttribute('required')

                if (isRequired && !isReadonly && !value) {
                    isValid = false
                    errorMessage = 'L\'email est obligatoire'
                } else if (value && !emailRegex.test(value)) {
                    isValid = false
                    errorMessage = 'Format d\'email invalide'
                } else if (value && value.length > 255) {
                    isValid = false
                    errorMessage = 'Maximum 255 caractères'
                }
                break

            case fieldName.includes('telephone'):
                if (value && value.length > 20) {
                    isValid = false
                    errorMessage = 'Maximum 20 caractères'
                }
                break

            case fieldName.includes('siren'):
                if (value && value.length !== 9) {
                    isValid = false
                    errorMessage = 'Le SIREN doit contenir exactement 9 caractères'
                } else if (value && !/^[0-9]{9}$/.test(value)) {
                    isValid = false
                    errorMessage = 'Le SIREN ne peut contenir que des chiffres'
                }
                break

            case fieldName.includes('siret'):
                if (value && value.length !== 14) {
                    isValid = false
                    errorMessage = 'Le SIRET doit contenir exactement 14 caractères'
                } else if (value && !/^[0-9]{14}$/.test(value)) {
                    isValid = false
                    errorMessage = 'Le SIRET ne peut contenir que des chiffres'
                }
                break

            case fieldName.includes('raisonSociale'):
            case fieldName.includes('raison_sociale'):
                if (!value) {
                    isValid = false
                    errorMessage = 'La raison sociale est obligatoire'
                } else if (value.length < 2) {
                    isValid = false
                    errorMessage = 'Au moins 2 caractères requis'
                } else if (value.length > 255) {
                    isValid = false
                    errorMessage = 'Maximum 255 caractères'
                }
                break

            case fieldName.includes('adresse'):
                // Validation pour toutes les adresses (y compris adresseLivraison)
                if (!value) {
                    isValid = false
                    const fieldLabel = fieldName.includes('adresseLivraison') ? 'L\'adresse de livraison' : 'L\'adresse'
                    errorMessage = fieldLabel + ' est obligatoire'
                } else if (value.length < 5) {
                    isValid = false
                    errorMessage = 'Au moins 5 caractères requis'
                }
                break

            case fieldName.includes('codePostal'):
            case fieldName.includes('code_postal'):
                if (!value) {
                    isValid = false
                    errorMessage = 'Le code postal est obligatoire'
                } else if (value.length > 10) {
                    isValid = false
                    errorMessage = 'Maximum 10 caractères'
                }
                break

            case fieldName.includes('ville'):
                if (!value) {
                    isValid = false
                    errorMessage = 'La ville est obligatoire'
                } else if (value.length < 2) {
                    isValid = false
                    errorMessage = 'Au moins 2 caractères requis'
                } else if (value.length > 100) {
                    isValid = false
                    errorMessage = 'Maximum 100 caractères'
                }
                break

            case fieldName.includes('tauxTVADefaut'):
            case fieldName.includes('taux_tva_defaut'):
                if (value) {
                    const taux = parseFloat(value)
                    if (isNaN(taux)) {
                        isValid = false
                        errorMessage = 'Le taux de TVA doit être un nombre'
                    } else if (taux < 0) {
                        isValid = false
                        errorMessage = 'Le taux de TVA ne peut pas être négatif'
                    } else if (taux > 100) {
                        isValid = false
                        errorMessage = 'Le taux de TVA ne peut pas dépasser 100%'
                    }
                }
                break

            case fieldName.includes('plainPassword'):
                // Validation du mot de passe (si rempli)
                if (value) {
                    if (value.length < 6) {
                        isValid = false
                        errorMessage = 'Le mot de passe doit contenir au moins 6 caractères'
                    } else if (value.length > 4096) {
                        isValid = false
                        errorMessage = 'Le mot de passe ne peut pas dépasser 4096 caractères'
                    } else if (fieldName.includes('first')) {
                        // Vérifier que les deux mots de passe correspondent
                        const confirmField = this.fieldTargets.find(f =>
                            f.name && f.name.includes('plainPassword') && f.name.includes('second')
                        )
                        if (confirmField && confirmField.value && value !== confirmField.value) {
                            isValid = false
                            errorMessage = 'Les mots de passe ne correspondent pas'
                            // Valider aussi le champ de confirmation
                            setTimeout(() => this.validateField(confirmField), 100)
                        }
                    } else if (fieldName.includes('second')) {
                        // Vérifier que les deux mots de passe correspondent
                        const firstField = this.fieldTargets.find(f =>
                            f.name && f.name.includes('plainPassword') && f.name.includes('first')
                        )
                        if (firstField && firstField.value && value !== firstField.value) {
                            isValid = false
                            errorMessage = 'Les mots de passe ne correspondent pas'
                            // Valider aussi le champ principal
                            setTimeout(() => this.validateField(firstField), 100)
                        }
                    }
                }
                break

            case fieldName.includes('pdpMode'):
            case fieldName.includes('pdp_mode'):
                if (!value) {
                    isValid = false
                    errorMessage = 'Le mode PDP est obligatoire'
                }
                break

            case fieldName.includes('notes'):
                if (value && value.length > 1000) {
                    isValid = false
                    errorMessage = 'Maximum 1000 caractères'
                }
                break

            case fieldName.includes('titre'):
                if (!value) {
                    isValid = false
                    errorMessage = 'Ce champ est obligatoire'
                } else if (value.length < 2) {
                    isValid = false
                    errorMessage = 'Au moins 2 caractères requis'
                } else if (value.length > 100) {
                    isValid = false
                    errorMessage = 'Maximum 100 caractères'
                }
                break

            case fieldName.includes('description'):
                if (!value) {
                    isValid = false
                    errorMessage = 'Ce champ est obligatoire'
                } else if (value.length < 10) {
                    isValid = false
                    errorMessage = 'Au moins 10 caractères requis'
                } else if (value.length > 5000) {
                    isValid = false
                    errorMessage = 'Maximum 5000 caractères'
                }
                break

            case fieldName && fieldName.includes('client'):
                // Validation spécifique pour le champ client (select)
                if (field.tagName === 'SELECT') {
                    // Le client est toujours obligatoire
                    const selectValue = field.value
                    if (!selectValue || selectValue === '' || selectValue === null || selectValue === '0' || selectValue === undefined) {
                        isValid = false
                        errorMessage = 'Le client est obligatoire'
                    }
                }
                break

            case fieldName.includes('dateValidite'):
            case fieldName.includes('date_validite'):
                // Validation pour la date de validité
                if (!value || value === '') {
                    isValid = false
                    errorMessage = 'La date de validité est obligatoire'
                } else {
                    // Vérifier que la date n'est pas dans le passé
                    const dateValue = new Date(value)
                    const today = new Date()
                    today.setHours(0, 0, 0, 0)
                    if (dateValue < today) {
                        isValid = false
                        errorMessage = 'La date de validité ne peut pas être dans le passé'
                    }
                }
                break

        }

        // Application du style selon la validation
        if (isValid) {
            this.setFieldValid(field)
        } else {
            this.setFieldInvalid(field, errorMessage)
        }

        return isValid
    }

    setFieldValid(field) {
        field.classList.add('is-valid')
        field.classList.remove('is-invalid')
        // Supprimer les messages d'erreur
        this.clearFieldError(field)
    }

    setFieldInvalid(field, message) {
        field.classList.add('is-invalid')
        field.classList.remove('is-valid')
        this.showFieldError(field, message)
        return false
    }

    clearFieldError(field) {
        field.classList.remove('is-valid', 'is-invalid')
        // Suppression des messages d'erreur existants (recherche dans toute la form-group)
        const formGroup = field.closest('.form-group')
        if (formGroup) {
            const existingErrors = formGroup.querySelectorAll('.form-error')
            existingErrors.forEach(error => error.remove())
        }
    }

    showFieldError(field, message) {
        const errorElement = document.createElement('div')
        errorElement.className = 'form-error'
        errorElement.textContent = message

        const formGroup = field.closest('.form-group')
        if (formGroup) {
            formGroup.appendChild(errorElement)
        }
    }

    handleSubmit(event) {
        // Éviter le double-clic
        if (this.isSubmitting) {
            event.preventDefault()
            return
        }

        // Re-détecter tous les champs (y compris ceux ajoutés dynamiquement)
        const allFields = Array.from(this.element.querySelectorAll('[data-admin-form-target="field"]'))

        // Pour les selects cachés par select-search, trouver le select original
        // Chercher tous les selects dans le formulaire, même ceux cachés
        const allSelects = Array.from(this.element.querySelectorAll('select'))

        allSelects.forEach(select => {
            // Si le select contient "client" dans son name et qu'il n'est pas déjà dans allFields
            if (select.name && select.name.includes('client') && !allFields.includes(select)) {
                allFields.push(select)
            }
        })

        // Validation spécifique pour les formulaires de devis : vérifier qu'au moins une ligne existe
        const isQuoteForm = this.element.closest('[data-controller*="quote-form"]') !== null
        if (isQuoteForm) {
            const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]')
            if (linesContainer) {
                // Compter les lignes existantes (exclure le template caché)
                const existingLines = Array.from(linesContainer.children).filter(line => {
                    // Exclure les éléments template et les lignes vides
                    return line.tagName !== 'TEMPLATE' && 
                           line.querySelector('input[name*="[description]"], input[name*="[quantity]"], input[name*="[unitPrice]"]')
                })
                
                if (existingLines.length === 0) {
                    // Aucune ligne : empêcher la soumission et afficher une erreur
                    event.preventDefault()
                    event.stopPropagation()
                    event.stopImmediatePropagation()
                    
                    // Afficher un message d'erreur
                    this.showLinesError(linesContainer)
                    
                    // Animation d'erreur
                    this.animateError()
                    
                    return false
                }
            }
        }

        // Validation côté client (exclure les champs de type file)
        let allValid = true
        allFields.forEach(field => {
            // Ne pas valider les champs de type file (ils sont gérés par Symfony)
            if (field.type === 'file') {
                return
            }
            // Ne pas valider les champs cachés (sauf les selects pour select-search)
            if (field.type === 'hidden') {
                return
            }
            // Ne pas valider les champs désactivés
            if (field.disabled) {
                return
            }
            const isValid = this.validateField(field)
            if (!isValid) {
                allValid = false
            }
        })

        if (!allValid) {
            // Empêcher la soumission si la validation client échoue
            event.preventDefault()
            event.stopPropagation()
            event.stopImmediatePropagation()

            // Empêcher aussi la propagation au niveau du formulaire
            if (event.cancelable) {
                event.preventDefault()
            }

            // Animation d'erreur - appliquer sur le formulaire et son conteneur parent
            const formElement = this.formTarget || this.element

            // Trouver le conteneur parent avec data-controller="quote-form"
            let container = null
            let currentElement = formElement.parentElement
            while (currentElement && !container) {
                const controllers = currentElement.getAttribute('data-controller')
                if (controllers && controllers.includes('quote-form')) {
                    container = currentElement
                    break
                }
                currentElement = currentElement.parentElement
            }

            // Si pas trouvé, utiliser le parent direct
            if (!container) {
                container = formElement.parentElement
            }

            // Appliquer l'animation sur le conteneur principal
            if (container) {
                // Retirer d'abord les autres animations qui pourraient interférer
                container.classList.remove('animate-delay-100', 'animate-fade-up')
                // Forcer le reflow pour s'assurer que l'animation se déclenche
                void container.offsetHeight
                // Ajouter la classe avec un léger délai pour forcer le re-render
                requestAnimationFrame(() => {
                    container.classList.add('animate-shake')
                })
                setTimeout(() => {
                    container.classList.remove('animate-shake')
                    // Restaurer les animations originales
                    container.classList.add('animate-delay-100', 'animate-fade-up')
                }, 600)
            }

            // Aussi sur le formulaire lui-même
            // Forcer le reflow pour s'assurer que l'animation se déclenche
            void formElement.offsetHeight
            // Ajouter la classe avec un léger délai pour forcer le re-render
            requestAnimationFrame(() => {
                formElement.classList.add('animate-shake')
            })
            setTimeout(() => {
                formElement.classList.remove('animate-shake')
            }, 600)

            // Faire défiler vers le premier champ invalide
            const firstInvalidField = this.element.querySelector('.is-invalid')
            if (firstInvalidField) {
                firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' })
                // Pour les selects cachés, essayer de trouver l'input de recherche
                const formGroup = firstInvalidField.closest('.form-group')
                if (formGroup) {
                    const searchInput = formGroup.querySelector('input[type="text"]')
                    if (searchInput) {
                        searchInput.focus()
                    } else {
                        firstInvalidField.focus()
                    }
                } else {
                    firstInvalidField.focus()
                }
            }

            return false
        }

        // Validation client OK : marquer comme en cours et appliquer le style de chargement
        this.isSubmitting = true
        if (this.submitTarget) {
            this.submitTarget.classList.add('btn-loading')
            this.submitTarget.disabled = true

            // Sauvegarder le texte original pour le restaurer si nécessaire
            if (!this.originalSubmitText) {
                this.originalSubmitText = this.submitTarget.textContent
            }
            this.submitTarget.textContent = 'Enregistrement...'
        }

        // Timeout de sécurité : restaurer le bouton après 10 secondes au cas où
        // aucun événement Turbo ne serait déclenché (par exemple en cas d'erreur réseau)
        if (this.safetyTimeout) {
            clearTimeout(this.safetyTimeout)
        }
        this.safetyTimeout = setTimeout(() => {
            console.warn('[AdminFormController] Timeout de sécurité : restauration du bouton après 10 secondes')
            this.resetSubmitButton()
        }, 10000)

        // Le formulaire se soumet normalement (pas de preventDefault)
        // Symfony fera sa validation côté serveur
    }

    /**
     * Affiche une erreur pour indiquer qu'au moins une ligne est requise
     */
    showLinesError(linesContainer) {
        // Supprimer l'erreur précédente si elle existe
        const existingError = linesContainer.parentElement.querySelector('.lines-error-message')
        if (existingError) {
            existingError.remove()
        }

        // Créer le message d'erreur
        const errorDiv = document.createElement('div')
        errorDiv.className = 'lines-error-message mb-4 p-4 bg-red-500/20 border border-red-500/30 rounded-lg'
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 bg-red-500/20">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-red-300 font-medium">Au moins une ligne de devis est requise.</p>
                    <p class="text-red-200 text-sm mt-1">Veuillez cliquer sur "Ajouter une ligne" pour ajouter au moins une ligne avant de soumettre le formulaire.</p>
                </div>
            </div>
        `

        // Insérer l'erreur avant le conteneur de lignes
        linesContainer.parentElement.insertBefore(errorDiv, linesContainer)

        // Faire défiler vers l'erreur
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }

    /**
     * Applique l'animation d'erreur sur le formulaire
     */
    animateError() {
        const formElement = this.formTarget || this.element

        // Trouver le conteneur parent avec data-controller="quote-form"
        let container = null
        let currentElement = formElement.parentElement
        while (currentElement && !container) {
            const controllers = currentElement.getAttribute('data-controller')
            if (controllers && controllers.includes('quote-form')) {
                container = currentElement
                break
            }
            currentElement = currentElement.parentElement
        }

        // Si pas trouvé, utiliser le parent direct
        if (!container) {
            container = formElement.parentElement
        }

        // Appliquer l'animation sur le conteneur principal
        if (container) {
            // Retirer d'abord les autres animations qui pourraient interférer
            container.classList.remove('animate-delay-100', 'animate-fade-up')
            // Forcer le reflow pour s'assurer que l'animation se déclenche
            void container.offsetHeight
            // Ajouter la classe avec un léger délai pour forcer le re-render
            requestAnimationFrame(() => {
                container.classList.add('animate-shake')
            })
            setTimeout(() => {
                container.classList.remove('animate-shake')
                // Restaurer les animations originales
                container.classList.add('animate-delay-100', 'animate-fade-up')
            }, 600)
        }

        // Aussi sur le formulaire lui-même
        void formElement.offsetHeight
        requestAnimationFrame(() => {
            formElement.classList.add('animate-shake')
        })
        setTimeout(() => {
            formElement.classList.remove('animate-shake')
        }, 600)
    }
}
