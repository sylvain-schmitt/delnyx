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
        console.log('🔵 Admin form controller connected')
        console.log('📋 Form target:', this.hasFormTarget)
        console.log('🎯 Field targets:', this.fieldTargets.length)
        console.log('🔘 Submit target:', this.hasSubmitTarget)

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
            field.addEventListener('blur', () => this.validateField(field))
            field.addEventListener('input', () => this.clearFieldError(field))
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
        const value = field.value.trim()

        console.log(`🔎 Validating field: ${fieldName}, value: "${value}"`)

        // Suppression des erreurs existantes
        this.clearFieldError(field)

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
                if (!value) {
                    isValid = false
                    errorMessage = 'L\'email est obligatoire'
                } else if (!emailRegex.test(value)) {
                    isValid = false
                    errorMessage = 'Format d\'email invalide'
                } else if (value.length > 255) {
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

            case fieldName.includes('siret'):
                if (value && value.length !== 14) {
                    isValid = false
                    errorMessage = 'Le SIRET doit contenir exactement 14 caractères'
                }
                break

            case fieldName.includes('notes'):
                if (value && value.length > 1000) {
                    isValid = false
                    errorMessage = 'Maximum 1000 caractères'
                }
                break
        }

        console.log(`📋 Field validation result: ${isValid ? '✅ VALID' : '❌ INVALID'}`, errorMessage)

        // Application du style selon la validation
        if (isValid) {
            field.classList.add('is-valid')
            field.classList.remove('is-invalid')
        } else {
            field.classList.add('is-invalid')
            field.classList.remove('is-valid')
            this.showFieldError(field, errorMessage)
        }

        return isValid
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
        console.log('🚀 handleSubmit triggered')
        console.log('⏳ isSubmitting:', this.isSubmitting)

        // Éviter le double-clic
        if (this.isSubmitting) {
            console.log('⚠️ Already submitting, preventing...')
            event.preventDefault()
            return
        }

        console.log('✅ Starting validation...')
        // Validation côté client
        let allValid = true
        this.fieldTargets.forEach(field => {
            const isFieldValid = this.validateField(field)
            console.log(`🔍 Field ${field.name}: ${isFieldValid ? '✅' : '❌'}`)
            if (!isFieldValid) {
                allValid = false
            }
        })

        console.log('📊 All fields valid:', allValid)

        if (!allValid) {
            console.log('❌ Validation failed, showing shake animation')
            // Empêcher la soumission si la validation client échoue
            event.preventDefault()

            // Animation d'erreur
            this.element.classList.add('animate-shake')
            setTimeout(() => {
                this.element.classList.remove('animate-shake')
            }, 600)
            return
        }

        console.log('✅ Validation passed, submitting form...')
        // Validation client OK : marquer comme en cours et appliquer le style de chargement
        this.isSubmitting = true
        this.submitTarget.classList.add('btn-loading')
        this.submitTarget.disabled = true

        // Sauvegarder le texte original pour le restaurer si nécessaire
        if (!this.originalSubmitText) {
            this.originalSubmitText = this.submitTarget.textContent
        }
        this.submitTarget.textContent = 'Enregistrement...'

        // Le formulaire se soumet normalement (pas de preventDefault)
        // Symfony fera sa validation côté serveur
    }
}
