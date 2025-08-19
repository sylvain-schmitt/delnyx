import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        phrases: Array // on définit une "value" qui sera remplie depuis le HTML
    }

    connect() {
        this.phraseIndex = 0;
        this.letterIndex = 0;
        this.deleting = false;

        this.typeLoop();
    }

    typeLoop() {
        const currentPhrase = this.phrasesValue[this.phraseIndex];

        if (!this.deleting) {
            this.element.textContent = currentPhrase.substring(0, this.letterIndex + 1);
            this.letterIndex++;

            if (this.letterIndex === currentPhrase.length) {
                this.deleting = true;
                setTimeout(() => this.typeLoop(), 1500); // pause avant suppression
                return;
            }
        } else {
            this.element.textContent = currentPhrase.substring(0, this.letterIndex - 1);
            this.letterIndex--;

            if (this.letterIndex === 0) {
                this.deleting = false;
                this.phraseIndex = (this.phraseIndex + 1) % this.phrasesValue.length;
            }
        }

        setTimeout(() => this.typeLoop(), this.deleting ? 50 : 80);
    }
}
