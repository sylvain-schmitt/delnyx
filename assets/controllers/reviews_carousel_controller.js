import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track'];
    static values = {
        delay: { type: Number, default: 5000 }
    };

    connect() {
        this.index = 0;
        this.isPaused = false;

        setTimeout(() => {
            this.startAutoScroll();
            this.resizeObserver = new ResizeObserver(() => this.updatePosition('auto'));
            this.resizeObserver.observe(this.trackTarget);
        }, 500);
    }

    disconnect() {
        this.stopAutoScroll();
        if (this.resizeObserver) this.resizeObserver.disconnect();
    }

    get slides() {
        return Array.from(this.trackTarget.children);
    }

    get totalSlides() {
        return this.slides.length;
    }

    get slidesPerPage() {
        const tw = this.element.offsetWidth;
        const sw = this.slides[0]?.offsetWidth || 1;
        return Math.max(1, Math.round(tw / sw));
    }

    updatePosition(behavior = 'smooth') {
        const sw = this.slides[0]?.offsetWidth || 0;
        const gap = parseInt(window.getComputedStyle(this.trackTarget).gap) || 0;

        // Calcul de l'offset maximal pour ne pas scroller dans le vide à la fin
        const maxIndex = Math.max(0, this.totalSlides - this.slidesPerPage);
        const currentIndex = Math.min(this.index, maxIndex);

        const offset = currentIndex * (sw + gap);

        this.trackTarget.style.transition = behavior === 'smooth' ? 'transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)' : 'none';
        this.trackTarget.style.transform = `translateX(-${offset}px)`;

        // Si on arrive au bout, on reset l'index pour le prochain tour
        if (this.index >= maxIndex) {
            this.index = -1; // Sera incrémenté à 0 par next()
        }
    }

    next() {
        this.index++;
        this.updatePosition();
    }

    startAutoScroll() {
        this.stopAutoScroll();
        if (this.totalSlides <= this.slidesPerPage) return;

        this.scroller = setInterval(() => {
            if (!this.isPaused) this.next();
        }, this.delayValue);
    }

    stopAutoScroll() {
        if (this.scroller) {
            clearInterval(this.scroller);
            this.scroller = null;
        }
    }

    pause() { this.isPaused = true; }
    resume() { this.isPaused = false; }

    // Pour mobile : on peut ajouter des listeners explicites dans le HTML si besoin
    // Mais pointerenter/pointerleave du group Tailwind peuvent suffire.
}
