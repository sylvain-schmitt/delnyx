import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["input", "stripeSection", "manualSection"]

    connect() {
        this.toggle()
    }

    toggle() {
        const selectedValue = this.inputTargets.find(input => input.checked)?.value

        if (selectedValue === 'stripe') {
            this.stripeSectionTarget.classList.remove('hidden')
            this.manualSectionTarget.classList.add('hidden')
        } else if (selectedValue === 'manual') {
            this.stripeSectionTarget.classList.add('hidden')
            this.manualSectionTarget.classList.remove('hidden')
        }
    }
}
