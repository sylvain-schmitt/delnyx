import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour gérer les modals
 * 
 * Usage:
 * - Ajouter data-controller="modal" sur le modal
 * - Ajouter data-modal-id="unique-id" sur le modal
 * - Bouton pour ouvrir: data-action="click->modal#open" data-modal-target-param="unique-id"
 * - Bouton pour fermer: data-action="click->modal#close"
 */
export default class extends Controller {
    static targets = ['content'];

    connect() {
        // Enregistrer le modal globalement par son ID
        const modalId = this.element.dataset.modalId;
        if (modalId) {
            if (!window.modals) {
                window.modals = {};
            }
            window.modals[modalId] = this;
        }
    }

    disconnect() {
        // Nettoyer l'enregistrement global
        const modalId = this.element.dataset.modalId;
        if (modalId && window.modals) {
            delete window.modals[modalId];
        }
    }

    /**
     * Ouvre le modal
     * Peut être appelé depuis un autre controller avec data-modal-target-param
     */
    open(event) {
        // Si appelé depuis un bouton avec data-modal-target-param
        if (event && event.params && event.params.target) {
            const targetId = event.params.target;
            if (window.modals && window.modals[targetId]) {
                window.modals[targetId].show();
                return;
            }
        }
        
        // Sinon, ouvrir ce modal
        this.show();
    }

    /**
     * Affiche le modal
     */
    show() {
        this.element.classList.remove('hidden');
        // Ajouter une animation d'entrée
        this.element.style.opacity = '0';
        setTimeout(() => {
            this.element.style.transition = 'opacity 0.2s ease-in-out';
            this.element.style.opacity = '1';
        }, 10);
        
        // Empêcher le scroll du body
        document.body.style.overflow = 'hidden';
        
        // Focus sur le premier input
        setTimeout(() => {
            const firstInput = this.element.querySelector('input, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        }, 200);
    }

    /**
     * Ferme le modal
     */
    close(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Animation de sortie
        this.element.style.opacity = '0';
        setTimeout(() => {
            this.element.classList.add('hidden');
            // Réactiver le scroll du body
            document.body.style.overflow = '';
        }, 200);
    }

    /**
     * Ferme le modal si on clique sur le backdrop (pas sur le contenu)
     */
    closeOnBackdrop(event) {
        if (event.target === this.element) {
            this.close(event);
        }
    }

    /**
     * Empêche la propagation du clic sur le contenu
     */
    stopPropagation(event) {
        event.stopPropagation();
    }
}

