import { Controller } from "@hotwired/stimulus"

/**
 * Contrôleur Stimulus pour gérer l'icône calendrier personnalisée
 */
export default class extends Controller {
    static targets = ["input", "button"]

    connect() {
        // Si le navigateur supporte showPicker(), l'utiliser
        // Sinon, utiliser focus() pour ouvrir le datepicker
        if (this.hasButtonTarget && this.hasInputTarget) {
            this.buttonTarget.addEventListener('click', () => this.openDatePicker())
        }
    }

    openDatePicker() {
        if (this.hasInputTarget) {
            // Essayer showPicker() si disponible (navigateurs modernes)
            if (this.inputTarget.showPicker) {
                this.inputTarget.showPicker()
            } else {
                // Fallback : focus sur l'input
                this.inputTarget.focus()
                this.inputTarget.click()
            }
        }
    }
}

