import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour gérer le modal d'annulation
 * 
 * Le modal doit avoir: data-controller="cancel-modal"
 * Les boutons doivent avoir: data-controller="cancel-modal-trigger" avec les valeurs
 */
export default class extends Controller {
    static targets = ['form', 'csrfToken', 'documentType', 'title', 'message', 'reasonSelect', 'otherReasonContainer', 'otherReasonText'];
    
    connect() {
        // Enregistrer le modal globalement
        if (!window.cancelModal) {
            window.cancelModal = this;
        }
        
        // Écouter les événements d'ouverture depuis les boutons
        document.addEventListener('cancel-modal:open', this.handleOpen.bind(this));
    }

    disconnect() {
        if (window.cancelModal === this) {
            window.cancelModal = null;
        }
        document.removeEventListener('cancel-modal:open', this.handleOpen.bind(this));
    }

    /**
     * Gère l'ouverture du modal depuis un événement personnalisé
     */
    handleOpen(event) {
        const { url, csrfToken, documentType, documentNumber } = event.detail;
        
        // Peupler le formulaire
        if (this.hasFormTarget && url) {
            this.formTarget.action = url;
        }
        
        if (this.hasCsrfTokenTarget && csrfToken) {
            this.csrfTokenTarget.value = csrfToken;
        }
        
        if (this.hasDocumentTypeTarget && documentType) {
            this.documentTypeTarget.value = documentType;
        }

        // Mettre à jour le titre
        if (this.hasTitleTarget && documentType) {
            const typeLabels = {
                'quote': 'le devis',
                'invoice': 'la facture',
                'amendment': 'l\'avenant',
                'credit_note': 'l\'avoir'
            };
            this.titleTarget.textContent = 'Annuler ' + (typeLabels[documentType] || 'le document');
        }

        // Générer les options de raisons selon le type de document (PRIORITAIRE)
        if (this.hasReasonSelectTarget) {
            if (documentType) {
                this.populateReasons(documentType);
            } else {
                // Fallback : si pas de type, utiliser les raisons par défaut (devis)
                this.populateReasons('quote');
            }
        }

        // Mettre à jour le message
        if (this.hasMessageTarget && documentType) {
            const messages = {
                'quote': 'Cette action est <strong>irréversible</strong>. Le devis sera marqué comme annulé et ne pourra plus être signé.',
                'invoice': 'Cette action est <strong>irréversible</strong>. La facture sera marquée comme annulée. Pour une annulation légale, créez un avoir.',
                'amendment': 'Cette action est <strong>irréversible</strong>. L\'avenant sera marqué comme annulé et n\'affectera plus le devis parent.',
                'credit_note': 'Cette action est <strong>irréversible</strong>. L\'avoir sera marqué comme annulé.'
            };
            this.messageTarget.innerHTML = messages[documentType] || 'Êtes-vous sûr de vouloir annuler ce document ?';
        }

        // Afficher le modal
        this.element.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    /**
     * Ferme le modal
     */
    close(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        this.element.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        this.resetForm();
    }

    /**
     * Empêche la propagation du clic sur le contenu
     */
    stopPropagate(event) {
        event.stopPropagation();
    }

    /**
     * Affiche/masque le champ "Autre raison" selon la sélection
     */
    toggleOtherReason(event) {
        if (!this.hasOtherReasonContainerTarget || !this.hasOtherReasonTextTarget) {
            return;
        }

        const selectedValue = event.target.value;
        
        if (selectedValue === 'Autre') {
            this.otherReasonContainerTarget.classList.remove('hidden');
            this.otherReasonTextTarget.setAttribute('required', 'required');
        } else {
            this.otherReasonContainerTarget.classList.add('hidden');
            this.otherReasonTextTarget.removeAttribute('required');
            this.otherReasonTextTarget.value = '';
        }
    }

    /**
     * Valide le formulaire avant soumission
     */
    validateForm(event) {
        if (!this.hasReasonSelectTarget) {
            event.preventDefault();
            alert('Erreur : Impossible de valider le formulaire. Veuillez réessayer.');
            return false;
        }
        
        const selectedReason = this.reasonSelectTarget.value;
        
        // Vérifier qu'une raison est sélectionnée
        if (!selectedReason || selectedReason === '') {
            event.preventDefault();
            alert('Veuillez sélectionner une raison d\'annulation.');
            this.reasonSelectTarget.focus();
            return false;
        }
        
        // Si "Autre" est sélectionné, vérifier que le texte est rempli
        if (selectedReason === 'Autre') {
            if (!this.hasOtherReasonTextTarget || !this.otherReasonTextTarget.value.trim()) {
                event.preventDefault();
                alert('Veuillez préciser la raison d\'annulation.');
                if (this.hasOtherReasonContainerTarget) {
                    this.otherReasonContainerTarget.classList.remove('hidden');
                }
                if (this.hasOtherReasonTextTarget) {
                    this.otherReasonTextTarget.focus();
                }
                return false;
            }
        }
        
        return true;
    }

    /**
     * Réinitialise le formulaire
     */
    resetForm() {
        if (this.hasFormTarget) {
            this.formTarget.reset();
        }
        
        if (this.hasOtherReasonContainerTarget) {
            this.otherReasonContainerTarget.classList.add('hidden');
        }
        
        if (this.hasOtherReasonTextTarget) {
            this.otherReasonTextTarget.removeAttribute('required');
            this.otherReasonTextTarget.value = '';
        }
    }

    /**
     * Génère les options de raisons selon le type de document
     */
    populateReasons(documentType) {
        const reasons = this.getReasonsForType(documentType);
        const select = this.reasonSelectTarget;
        
        if (!select) {
            return;
        }
        
        // Vider les options existantes (sauf la première option vide)
        while (select.options.length > 1) {
            select.remove(1);
        }
        
        // Ajouter les raisons spécifiques
        reasons.forEach(reason => {
            const option = document.createElement('option');
            option.value = reason.value;
            option.textContent = reason.label;
            select.appendChild(option);
        });
        
        // Toujours ajouter "Autre" à la fin
        const otherOption = document.createElement('option');
        otherOption.value = 'Autre';
        otherOption.textContent = 'Autre raison...';
        select.appendChild(otherOption);
    }

    /**
     * Retourne les raisons d'annulation selon le type de document
     */
    getReasonsForType(documentType) {
        const reasonsByType = {
            'quote': [
                { value: 'Refusé par le client', label: 'Refusé par le client' },
                { value: 'Client injoignable', label: 'Client injoignable' },
                { value: 'Budget insuffisant', label: 'Budget insuffisant du client' },
                { value: 'Délais trop longs', label: 'Délais trop longs pour le client' },
                { value: 'Concurrent choisi', label: 'Concurrent choisi par le client' },
                { value: 'Projet abandonné', label: 'Projet abandonné par le client' },
                { value: 'Devis erroné', label: 'Devis erroné (erreur de calcul/contenu)' },
                { value: 'Doublon', label: 'Devis en doublon' }
            ],
            'amendment': [
                { value: 'Modifications non validées', label: 'Modifications non validées par le client' },
                { value: 'Erreur de saisie', label: 'Erreur de saisie' },
                { value: 'Doublon', label: 'Avenant en doublon' }
            ],
            'invoice': [
                { value: 'Erreur de facturation', label: 'Erreur de facturation' },
                { value: 'Doublon', label: 'Facture en doublon' },
                { value: 'Prestation non réalisée', label: 'Prestation non réalisée' },
                { value: 'Remplacée par avoir', label: 'Remplacée par un avoir' }
            ],
            'credit_note': [
                { value: 'Erreur de création', label: 'Erreur de création' },
                { value: 'Doublon', label: 'Avoir en doublon' },
                { value: 'Montant erroné', label: 'Montant erroné' }
            ]
        };
        
        return reasonsByType[documentType] || [];
    }
}

