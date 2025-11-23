import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
	static targets = ['toggle', 'rateWrapper'];

	connect() {
		this.applyVisibility();
	}

	toggleRate() {
		this.applyVisibility();
	}

	applyVisibility() {
		if (!this.hasToggleTarget || !this.hasRateWrapperTarget) return;
		const enabled = this.toggleTarget.checked === true;

		if (enabled) {
			this.rateWrapperTarget.classList.remove('hidden');
		} else {
			this.rateWrapperTarget.classList.add('hidden');
			const select = this.rateWrapperTarget.querySelector('select');
			if (select) {
				select.value = '';
				select.dispatchEvent(new Event('change', { bubbles: true }));
				select.dispatchEvent(new Event('blur', { bubbles: true }));
			}
		}
	}
}


