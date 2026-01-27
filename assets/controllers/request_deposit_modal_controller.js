import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'form',
        'csrfToken',
        'totalTTC',
        'percentageInput',
        'amountInput',
        'remaining'
    ];

    connect() {
        document.addEventListener('open-deposit-modal', this.open.bind(this));
    }

    disconnect() {
        document.removeEventListener('open-deposit-modal', this.open.bind(this));
    }

    open(event) {
        const { actionUrl, csrfToken, totalTtc, defaultPercentage } = event.detail;

        this.element.classList.remove('hidden');
        this.formTarget.action = actionUrl;
        this.csrfTokenTarget.value = csrfToken;

        this.totalTtcValue = totalTtc; // Stocker comme nombre flottant
        this.totalTTCTarget.textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(totalTtc);

        // Initialiser avec le pourcentage par défaut
        this.percentageInputTarget.value = defaultPercentage;
        this.updateFromPercentage();
    }

    close() {
        this.element.classList.add('hidden');
    }

    stopPropagate(event) {
        event.stopPropagation();
    }

    updateFromPercentage() {
        const percentage = parseFloat(this.percentageInputTarget.value) || 0;
        const amount = (this.totalTtcValue * percentage) / 100;

        this.amountInputTarget.value = amount.toFixed(2);
        this.updateRemaining(amount);
    }

    updateFromAmount() {
        const amount = parseFloat(this.amountInputTarget.value) || 0;
        let percentage = (amount / this.totalTtcValue) * 100;

        // Limiter le pourcentage à 100%
        if (percentage > 100) percentage = 100;

        this.percentageInputTarget.value = percentage.toFixed(2);
        this.updateRemaining(amount);
    }

    updateRemaining(depositAmount) {
        const remaining = Math.max(0, this.totalTtcValue - depositAmount);
        this.remainingTarget.textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(remaining);
    }
}
