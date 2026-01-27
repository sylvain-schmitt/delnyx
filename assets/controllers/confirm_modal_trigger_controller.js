import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        title: String,
        message: String,
        buttonText: String,
        method: { type: String, default: 'POST' }
    }

    open(event) {
        event.preventDefault();

        const customEvent = new CustomEvent('open-confirm-modal', {
            detail: {
                url: this.urlValue,
                csrfToken: this.csrfTokenValue,
                title: this.titleValue,
                message: this.messageValue,
                buttonText: this.buttonTextValue,
                method: this.methodValue
            }
        });

        document.dispatchEvent(customEvent);
    }
}
