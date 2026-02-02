import { Controller } from '@hotwired/stimulus';

/**
 * Controller pour les actions globales sur les notifications dans le dropdown
 */
export default class extends Controller {
    static targets = ["list", "emptyState", "footerActions", "countValue"]
    static values = {
        markAllUrl: String,
        hideAllUrl: String
    }

    connect() {
        this.itemRemovedBound = this.updateVisibleCount.bind(this)
        document.addEventListener('notification:item-removed', this.itemRemovedBound)
    }

    disconnect() {
        document.removeEventListener('notification:item-removed', this.itemRemovedBound)
    }

    /**
     * Ouvre la modale de confirmation pour tout marquer comme lu
     */
    confirmMarkAll(event) {
        event.preventDefault();
        event.stopPropagation();

        document.dispatchEvent(new CustomEvent('open-confirm-modal', {
            detail: {
                title: 'Tout marquer comme lu',
                message: 'Voulez-vous marquer toutes les notifications comme lues ?',
                buttonText: 'Marquer tout comme lu',
                // On surcharge le confirm du bouton personnalisé pour faire de l'AJAX au lieu de submit
                confirmCallback: () => this.executeMarkAll()
            }
        }));
    }

    /**
     * Ouvre la modale de confirmation pour tout supprimer
     */
    confirmHideAll(event) {
        event.preventDefault();
        event.stopPropagation();

        document.dispatchEvent(new CustomEvent('open-confirm-modal', {
            detail: {
                title: 'Tout supprimer',
                message: 'Voulez-vous supprimer (masquer) toutes les notifications ?',
                buttonText: 'Tout supprimer',
                confirmCallback: () => this.executeHideAll()
            }
        }));
    }

    async executeMarkAll() {
        try {
            const response = await fetch(this.markAllUrlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok) {
                // UI instantanée
                const unreadItems = this.listTarget.querySelectorAll('.notification-unread');
                unreadItems.forEach(item => {
                    // On pourrait appeler le controller de l'item,
                    // mais plus simple de manipuler le DOM ici
                    item.classList.remove('bg-blue-900/10', 'notification-unread');
                    const indicator = item.querySelector('[data-notification-item-target="unreadIndicator"]');
                    if (indicator) indicator.classList.add('hidden');
                    const btn = item.querySelector('[data-notification-item-target="readButton"]');
                    if (btn) btn.classList.add('hidden');
                });

                // Reset badge
                document.dispatchEvent(new CustomEvent('notification:update-count', {
                    detail: { count: 0 }
                }));
            }
        } catch (error) {
            console.error('Erreur:', error);
        }
    }

    async executeHideAll() {
        try {
            const response = await fetch(this.hideAllUrlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok) {
                // Vider la liste
                this.listTarget.innerHTML = '';
                this.emptyStateTargets.forEach(el => el.classList.remove('hidden'));
                if (this.hasFooterActionsTarget) this.footerActionsTarget.classList.add('hidden');
                this.countValueTarget.textContent = '0';

                // Reset badge
                document.dispatchEvent(new CustomEvent('notification:update-count', {
                    detail: { count: 0 }
                }));
            }
        } catch (error) {
            console.error('Erreur:', error);
        }
    }

    updateVisibleCount() {
        if (this.hasCountValueTarget) {
            const count = this.listTarget.children.length;
            this.countValueTarget.textContent = count;

            if (count === 0) {
                this.emptyStateTargets.forEach(el => el.classList.remove('hidden'));
                if (this.hasFooterActionsTarget) this.footerActionsTarget.classList.add('hidden');
            }
        }
    }
}
