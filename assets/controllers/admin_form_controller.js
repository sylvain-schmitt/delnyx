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
    }

    disconnect() {
        this.isSubmitting = false
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
        const value = field.type === 'file' ? field.files.length > 0 : field.value.trim()
        const isRequired = field.hasAttribute('required') || field.getAttribute('required') === 'required'

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
                if (!field.value || field.value === '') {
                    return this.setFieldInvalid(field, 'Ce champ est obligatoire')
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
                if (!value) {
                    isValid = false
                    errorMessage = 'L\'adresse est obligatoire'
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

        // Validation côté client (exclure les champs de type file)
        let allValid = true
        this.fieldTargets.forEach(field => {
            // Ne pas valider les champs de type file (ils sont gérés par Symfony)
            if (field.type === 'file') {
                return
            }
            if (!this.validateField(field)) {
                allValid = false
            }
        })

        if (!allValid) {
            // Empêcher la soumission si la validation client échoue
            event.preventDefault()

            // Animation d'erreur
            this.element.classList.add('animate-shake')
            setTimeout(() => {
                this.element.classList.remove('animate-shake')
            }, 600)
            return
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

        // Le formulaire se soumet normalement (pas de preventDefault)
        // Symfony fera sa validation côté serveur
    }
}
