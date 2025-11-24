import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour la modale d'envoi d'email
 * Permet de personnaliser le message avant envoi
 */
export default class extends Controller {
    static targets = ["modal", "overlay", "form", "recipient", "message", "title", "fileInput", "fileList"]
    static values = {
        actionUrl: String,
        csrfToken: String,
        recipientEmail: String,
        documentType: String,
        documentNumber: String
    }
    
    selectedFiles = new DataTransfer() // Pour gérer les fichiers sélectionnés

    connect() {
        // Vérifier que les targets existent
        if (!this.hasModalTarget || !this.hasOverlayTarget) {
            console.warn('⚠️ Email modal: targets manquants.')
            return
        }

        // Initialiser la modale comme fermée
        this.close()
        
        // Lier la gestion de la touche Escape
        this.boundCloseOnEscape = this.closeOnEscape.bind(this)
        document.addEventListener('keydown', this.boundCloseOnEscape)

        // Écouter les événements personnalisés pour ouvrir la modale
        this.boundOpenModal = this.openModal.bind(this)
        document.addEventListener('open-email-modal', this.boundOpenModal)
    }

    /**
     * Ouvre la modale avec les paramètres fournis
     */
    openModal(event) {
        const { url, csrfToken, recipientEmail, documentType, documentNumber } = event.detail

        this.actionUrlValue = url
        this.csrfTokenValue = csrfToken || ''
        this.recipientEmailValue = recipientEmail || ''
        this.documentTypeValue = documentType || 'document'
        this.documentNumberValue = documentNumber || ''

        // Mettre à jour le titre
        if (this.hasTitleTarget) {
            const typeLabel = this.getDocumentTypeLabel(documentType)
            this.titleTarget.textContent = `Envoyer ${typeLabel}`
        }

        // Mettre à jour le destinataire
        if (this.hasRecipientTarget) {
            this.recipientTarget.textContent = recipientEmail
        }

        // Mettre à jour l'action du formulaire
        if (this.hasFormTarget) {
            this.formTarget.action = url
            
            // S'assurer que le token CSRF est dans le formulaire
            let tokenInput = this.formTarget.querySelector('[name="_token"]')
            if (!tokenInput) {
                tokenInput = document.createElement('input')
                tokenInput.type = 'hidden'
                tokenInput.name = '_token'
                this.formTarget.appendChild(tokenInput)
            }
            tokenInput.value = csrfToken
        }

        // Réinitialiser le message personnalisé
        if (this.hasMessageTarget) {
            this.messageTarget.value = ''
        }

        // Réinitialiser les fichiers
        this.clearFiles()

        // Afficher la modale
        this.modalTarget.classList.remove('hidden')
        this.overlayTarget.classList.remove('hidden')
        document.body.style.overflow = 'hidden'

        // Focus sur le textarea
        if (this.hasMessageTarget) {
            setTimeout(() => this.messageTarget.focus(), 100)
        }
    }

    /**
     * Ouvre le sélecteur de fichiers
     */
    openFileSelector(event) {
        event.preventDefault()
        if (this.hasFileInputTarget) {
            this.fileInputTarget.click()
        }
    }

    /**
     * Gère le changement de fichiers
     */
    handleFileChange(event) {
        const files = Array.from(event.target.files)
        
        if (files.length === 0) return

        // Vérifier la taille des fichiers (max 10 Mo)
        const maxSize = 10 * 1024 * 1024 // 10 Mo
        const invalidFiles = files.filter(file => file.size > maxSize)
        
        if (invalidFiles.length > 0) {
            alert(`Les fichiers suivants dépassent 10 Mo et ne seront pas ajoutés :\n${invalidFiles.map(f => f.name).join('\n')}`)
        }

        // Ajouter les fichiers valides
        const validFiles = files.filter(file => file.size <= maxSize)
        validFiles.forEach(file => {
            this.selectedFiles.items.add(file)
        })

        // Mettre à jour l'affichage
        this.updateFileList()
        
        // Mettre à jour l'input avec les nouveaux fichiers
        if (this.hasFileInputTarget) {
            this.fileInputTarget.files = this.selectedFiles.files
        }
    }

    /**
     * Met à jour la liste des fichiers affichés
     */
    updateFileList() {
        if (!this.hasFileListTarget) return

        const files = Array.from(this.selectedFiles.files)

        if (files.length === 0) {
            this.fileListTarget.classList.add('hidden')
            this.fileListTarget.innerHTML = ''
            return
        }

        this.fileListTarget.classList.remove('hidden')
        this.fileListTarget.innerHTML = files.map((file, index) => {
            const sizeInKB = (file.size / 1024).toFixed(1)
            const sizeInMB = (file.size / (1024 * 1024)).toFixed(1)
            const sizeDisplay = file.size < 1024 * 1024 ? `${sizeInKB} Ko` : `${sizeInMB} Mo`
            
            return `
                <div class="flex items-center justify-between p-2 bg-slate-700/50 rounded border border-white/10">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <svg class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-white truncate" title="${file.name}">${file.name}</p>
                            <p class="text-xs text-white/60">${sizeDisplay}</p>
                        </div>
                    </div>
                    <button 
                        type="button"
                        data-action="click->email-modal#removeFile"
                        data-file-index="${index}"
                        class="ml-2 p-1 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded transition-colors flex-shrink-0"
                        title="Retirer ce fichier"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            `
        }).join('')
    }

    /**
     * Retire un fichier de la liste
     */
    removeFile(event) {
        event.preventDefault()
        const index = parseInt(event.currentTarget.dataset.fileIndex)
        
        // Créer un nouveau DataTransfer sans le fichier supprimé
        const newFiles = new DataTransfer()
        const filesArray = Array.from(this.selectedFiles.files)
        
        filesArray.forEach((file, i) => {
            if (i !== index) {
                newFiles.items.add(file)
            }
        })
        
        this.selectedFiles = newFiles
        
        // Mettre à jour l'input
        if (this.hasFileInputTarget) {
            this.fileInputTarget.files = this.selectedFiles.files
        }
        
        // Mettre à jour l'affichage
        this.updateFileList()
    }

    /**
     * Efface tous les fichiers
     */
    clearFiles() {
        this.selectedFiles = new DataTransfer()
        if (this.hasFileInputTarget) {
            this.fileInputTarget.value = ''
        }
        this.updateFileList()
    }

    /**
     * Ouvre la modale depuis un élément avec data-action
     */
    open(event) {
        event.preventDefault()

        const trigger = event.currentTarget
        const url = trigger.dataset.emailUrl || trigger.closest('form')?.action
        const token = trigger.dataset.emailCsrfToken || trigger.closest('form')?.querySelector('[name="_token"]')?.value || ''
        const recipientEmail = trigger.dataset.emailRecipient || ''
        const documentType = trigger.dataset.emailDocumentType || 'document'
        const documentNumber = trigger.dataset.emailDocumentNumber || ''

        // Déclencher l'événement personnalisé
        const customEvent = new CustomEvent('open-email-modal', {
            detail: { url, csrfToken: token, recipientEmail, documentType, documentNumber }
        })
        document.dispatchEvent(customEvent)
    }

    /**
     * Ferme la modale
     */
    close() {
        this.modalTarget.classList.add('hidden')
        this.overlayTarget.classList.add('hidden')
        document.body.style.overflow = ''
    }

    /**
     * Soumet le formulaire d'envoi
     */
    submit(event) {
        event.preventDefault()
        
        if (this.hasFormTarget) {
            this.formTarget.submit()
        }
    }

    /**
     * Ferme la modale au clic sur l'overlay
     */
    closeOnOverlay(event) {
        if (event.target === this.overlayTarget) {
            this.close()
        }
    }

    /**
     * Ferme la modale avec la touche Escape
     */
    closeOnEscape(event) {
        if (event.key === 'Escape' && !this.modalTarget.classList.contains('hidden')) {
            this.close()
        }
    }

    /**
     * Obtient le label selon le type de document
     */
    getDocumentTypeLabel(type) {
        const labels = {
            'quote': 'le devis',
            'invoice': 'la facture',
            'amendment': 'l\'avenant',
            'credit_note': 'l\'avoir'
        }
        return labels[type] || 'le document'
    }

    disconnect() {
        if (this.boundCloseOnEscape) {
            document.removeEventListener('keydown', this.boundCloseOnEscape)
        }
        if (this.boundOpenModal) {
            document.removeEventListener('open-email-modal', this.boundOpenModal)
        }
    }
}


