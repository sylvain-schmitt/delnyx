import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["mobileMenu", "iconMenu", "iconClose"]
    static values = { open: Boolean, lastScroll: Number }

    connect() {
        this.openValue = false
        this.lastScrollValue = window.scrollY
        window.addEventListener("scroll", this.onScroll.bind(this))
        document.addEventListener("click", this.onClickOutside.bind(this))
    }

    disconnect() {
        window.removeEventListener("scroll", this.onScroll.bind(this))
        document.removeEventListener("click", this.onClickOutside.bind(this))
    }

    toggle() {
        this.openValue = !this.openValue
        this.updateMenu()
    }

    close() {
        this.openValue = false
        this.updateMenu()
    }

    updateMenu() {
        if (this.openValue) {
            this.mobileMenuTarget.style.maxHeight = this.mobileMenuTarget.scrollHeight + "px"
            this.iconMenuTarget.classList.add("hidden")
            this.iconCloseTarget.classList.remove("hidden")
        } else {
            this.mobileMenuTarget.style.maxHeight = "0"
            this.iconMenuTarget.classList.remove("hidden")
            this.iconCloseTarget.classList.add("hidden")
        }
    }

    onScroll() {
        const currentScroll = window.scrollY
        const navbar = this.element

        if (currentScroll > 100) {
            navbar.classList.add("navbar-scrolled")
            navbar.style.transform =
                currentScroll > this.lastScrollValue && currentScroll > 200
                    ? "translateY(-100%)"
                    : "translateY(0)"
        } else {
            navbar.classList.remove("navbar-scrolled")
            navbar.style.transform = "translateY(0)"
        }

        this.lastScrollValue = currentScroll
    }

    onClickOutside(event) {
        if (this.openValue && !this.element.contains(event.target)) {
            this.close()
        }
    }
}
