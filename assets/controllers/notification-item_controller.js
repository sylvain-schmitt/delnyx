import { Controller } from '@hotwired/stimulus';

/**
 * Controller pour marquer une notification individuelle comme lue ou cachée
 */
export default class extends Controller {
    static targets = ["readButton", "unreadIndicator"]
    static values = {
        readUrl: String,
        hideUrl: String,
        isRead: Boolean
    }

    async markAsRead(event) {
        // Si déjà lu, on ne fait rien (ex: clic sur le lien)
        if (this.isReadValue) return;

        // UI immédiate
        this._setAsRead();

        // Envoie de la requête
        if (this.readUrlValue) {
            try {
                const response = await fetch(this.readUrlValue, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.ok) {
                    const data = await response.json();
                    // Synchroniser le badge si le serveur renvoie un compte différent
                    if (data.unreadCount !== undefined) {
                        this._dispatchUpdateCount(data.unreadCount);
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
            }
        }
    }

    async hide(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this.hideUrlValue) {
            // UI immédiate (décrémenter si c'était non lu)
            if (!this.isReadValue) {
                document.dispatchEvent(new CustomEvent('notification:decrement'));
            }

            try {
                // Animation de sortie
                this.element.style.transition = 'all 0.3s ease';
                this.element.style.opacity = '0';
                this.element.style.transform = 'translateX(20px)';

                const response = await fetch(this.hideUrlValue, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.ok) {
                    setTimeout(() => {
                        this.element.remove();
                        // Dispatcher une mise à jour du texte "X visibles"
                        document.dispatchEvent(new CustomEvent('notification:item-removed'));
                    }, 300);
                } else {
                    // Rollback UI si erreur
                    this.element.style.opacity = '1';
                    this.element.style.transform = 'translateX(0)';
                }
            } catch (error) {
                console.error('Erreur:', error);
                this.element.style.opacity = '1';
                this.element.style.transform = 'translateX(0)';
            }
        }
    }

    _setAsRead() {
        if (this.isReadValue) return;

        this.isReadValue = true;
        this.element.classList.remove('bg-blue-900/10', 'notification-unread');

        if (this.hasReadButtonTarget) {
            this.readButtonTarget.classList.add('hidden');
        }

        if (this.hasUnreadIndicatorTarget) {
            this.unreadIndicatorTarget.classList.add('hidden');
        }

        // Décrémenter le badge global
        document.dispatchEvent(new CustomEvent('notification:decrement'));
    }

    _dispatchUpdateCount(count) {
        document.dispatchEvent(new CustomEvent('notification:update-count', {
            detail: { count: count }
        }));
    }
}
