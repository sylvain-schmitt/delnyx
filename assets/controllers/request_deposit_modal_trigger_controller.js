import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        totalTtc: Number,
        defaultPercentage: Number
    }

    open(event) {
        event.preventDefault();

        const customEvent = new CustomEvent('open-deposit-modal', {
            detail: {
                actionUrl: this.urlValue,
                csrfToken: this.csrfTokenValue,
                totalTtc: this.totalTtcValue,
                defaultPercentage: this.defaultPercentageValue
            }
        });

        document.dispatchEvent(customEvent);
    }
}
