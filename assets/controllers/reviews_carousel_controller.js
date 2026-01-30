import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track'];
    static values = {
        delay: { type: Number, default: 4000 } // ms entre chaque saut de page
    };

    connect() {
        this.startAutoScroll();
    }

    disconnect() {
        this.stopAutoScroll();
    }

    startAutoScroll() {
        if (this.scroller) return;

        this.scroller = setInterval(() => {
            if (!this.trackTarget) return;

            const container = this.trackTarget;
            const scrollLeft = container.scrollLeft;
            const scrollWidth = container.scrollWidth;
            const clientWidth = container.clientWidth;
            const maxScroll = scrollWidth - clientWidth;

            // Calculer le gap dynamiquement (basé sur Tailwind gap-8 = 32px)
            const gap = parseInt(window.getComputedStyle(container).gap) || 0;

            // Si on est à la fin (ou presque), on revient au début
            if (scrollLeft >= maxScroll - 10) {
                container.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                // On défile d'une page + le gap pour que la carte suivante soit bien centrée
                container.scrollBy({ left: clientWidth + gap, behavior: 'smooth' });
            }
        }, this.delayValue);
    }

    stopAutoScroll() {
        if (this.scroller) {
            clearInterval(this.scroller);
            this.scroller = null;
        }
    }

    pause() {
        this.stopAutoScroll();
    }

    resume() {
        this.startAutoScroll();
    }
}
