import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour le header admin
 * Gère l'effet de scroll (disparition/réapparition)
 */
export default class extends Controller {
    static values = { lastScroll: Number }

    connect() {
        this.lastScrollValue = window.scrollY
        this.onScroll = this.onScroll.bind(this)
        window.addEventListener("scroll", this.onScroll)
    }

    disconnect() {
        window.removeEventListener("scroll", this.onScroll)
    }

    onScroll() {
        const currentScroll = window.scrollY
        const header = this.element

        if (currentScroll > 100) {
            header.classList.add("header-scrolled")
            header.style.transform =
                currentScroll > this.lastScrollValue && currentScroll > 200
                    ? "translateY(-100%)"
                    : "translateY(0)"
        } else {
            header.classList.remove("header-scrolled")
            header.style.transform = "translateY(0)"
        }

        this.lastScrollValue = currentScroll
    }
}
