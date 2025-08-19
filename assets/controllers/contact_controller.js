import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["form", "field", "submit", "counter"]
    static values = {
        maxLength: { type: Number, default: 2000 }
    }

    connect() {
        this.setupFormValidation()
        this.setupCharacterCounter()
        this.setupFieldAnimations()
    }

    setupFormValidation() {
        // Validation en temps réel des champs
        this.fieldTargets.forEach(field => {
            field.addEventListener('blur', () => this.validateField(field))
            field.addEventListener('input', () => this.clearFieldError(field))
        })
    }

    setupCharacterCounter() {
        // Compteur de caractères pour le textarea
        const messageField = this.element.querySelector('textarea[name*="message"]')
        if (messageField && this.hasCounterTarget) {
            messageField.addEventListener('input', () => {
                const currentLength = messageField.value.length
                this.counterTarget.textContent = currentLength

                // Changer la couleur selon la proximité de la limite
                if (currentLength > this.maxLengthValue * 0.9) {
                    this.counterTarget.className = 'text-red-400 font-semibold'
                } else if (currentLength > this.maxLengthValue * 0.7) {
                    this.counterTarget.className = 'text-yellow-400'
                } else {
                    this.counterTarget.className = ''
                }
            })
        }
    }

    setupFieldAnimations() {
        // Animations sur focus/blur des champs
        this.fieldTargets.forEach(field => {
            field.addEventListener('focus', () => {
                field.classList.add('scale-[1.02]', 'shadow-lg', 'shadow-blue-500/25')
                // Animation de l'icône
                const icon = field.parentElement.querySelector('.absolute svg')
                if (icon) {
                    icon.classList.add('text-blue-400', 'scale-110')
                }
            })

            field.addEventListener('blur', () => {
                field.classList.remove('scale-[1.02]', 'shadow-lg', 'shadow-blue-500/25')
                // Reset de l'icône
                const icon = field.parentElement.querySelector('.absolute svg')
                if (icon) {
                    icon.classList.remove('text-blue-400', 'scale-110')
                    icon.classList.add('text-gray-400')
                }
            })
        })
    }

    validateField(field) {
        const fieldName = field.name
        const value = field.value.trim()

        // Suppression des erreurs existantes
        this.clearFieldError(field)

        // Validation selon le type de champ
        let isValid = true
        let errorMessage = ''

        switch (true) {
            case fieldName.includes('prenom'):
            case fieldName.includes('nom'):
                if (!value) {
                    isValid = false
                    errorMessage = 'Ce champ est obligatoire'
                } else if (value.length < 2) {
                    isValid = false
                    errorMessage = 'Au moins 2 caractères requis'
                }
                break

            case fieldName.includes('email'):
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
                if (!value) {
                    isValid = false
                    errorMessage = 'L\'adresse email est obligatoire'
                } else if (!emailRegex.test(value)) {
                    isValid = false
                    errorMessage = 'Format d\'email invalide'
                }
                break

            case fieldName.includes('telephone'):
                if (value && !/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/.test(value)) {
                    isValid = false
                    errorMessage = 'Numéro de téléphone français invalide'
                }
                break

            case fieldName.includes('sujet'):
                if (!value) {
                    isValid = false
                    errorMessage = 'Veuillez sélectionner un sujet'
                }
                break

            case fieldName.includes('message'):
                if (!value) {
                    isValid = false
                    errorMessage = 'Le message est obligatoire'
                } else if (value.length < 10) {
                    isValid = false
                    errorMessage = 'Au moins 10 caractères requis'
                }
                break
        }

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
        event.preventDefault()

        // Nettoyage de toutes les erreurs existantes
        this.fieldTargets.forEach(field => {
            this.clearFieldError(field)
        })

        // Validation de tous les champs
        let allValid = true
        this.fieldTargets.forEach(field => {
            if (!this.validateField(field)) {
                allValid = false
            }
        })

        if (!allValid) {
            // Animation d'erreur
            this.element.classList.add('animate-shake')
            setTimeout(() => {
                this.element.classList.remove('animate-shake')
            }, 600)
            return
        }

        // Animation de chargement
        this.submitTarget.classList.add('btn-loading')
        this.submitTarget.disabled = true
        this.submitTarget.textContent = 'Envoi en cours...'

        // Soumission du formulaire
        this.formTarget.submit()
    }

    // Animation de succès (appelée après redirection)
    showSuccess() {
        // Animation de confirmation
        const successElement = document.createElement('div')
        successElement.className = 'fixed top-4 right-4 bg-emerald-500 text-white p-4 rounded-lg shadow-lg z-50 transform translate-x-full'
        successElement.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Message envoyé avec succès !
            </div>
        `

        document.body.appendChild(successElement)

        // Animation d'entrée
        setTimeout(() => {
            successElement.classList.remove('translate-x-full')
        }, 100)

        // Suppression après 5 secondes
        setTimeout(() => {
            successElement.classList.add('translate-x-full')
            setTimeout(() => {
                document.body.removeChild(successElement)
            }, 300)
        }, 5000)
    }
}
