import { Controller } from '@hotwired/stimulus';

/**
 * Controller pour gérer la cloche de notification et son badge
 */
export default class extends Controller {
    static targets = ["badge", "unreadCount"]

    connect() {
        // Écouter les événements de mise à jour du compteur
        this.updateCountBound = this.updateCount.bind(this)
        document.addEventListener('notification:update-count', this.updateCountBound)

        this.decrementBound = this.decrement.bind(this)
        document.addEventListener('notification:decrement', this.decrementBound)
    }

    disconnect() {
        document.removeEventListener('notification:update-count', this.updateCountBound)
        document.removeEventListener('notification:decrement', this.decrementBound)
    }

    /**
     * Met à jour le compteur avec une valeur précise
     */
    updateCount(event) {
        const count = event.detail.count
        this._applyCount(count)
    }

    /**
     * Décrémente le compteur de 1
     */
    decrement() {
        let currentCount = parseInt(this.unreadCountTarget.textContent)
        if (isNaN(currentCount)) return // Cas du "9+" ou autre

        if (currentCount > 0) {
            this._applyCount(currentCount - 1)
        }
    }

    _applyCount(count) {
        if (count <= 0) {
            this.badgeTarget.classList.add('hidden')
            this.unreadCountTarget.textContent = '0'
        } else {
            this.badgeTarget.classList.remove('hidden')
            this.unreadCountTarget.textContent = count > 9 ? '9+' : count
        }
    }
}
