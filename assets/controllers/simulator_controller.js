import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["totalHT", "totalTTC", "totalTVA", "itemCount", "selectionList", "ctaButton", "tvaRow"];
    static values = {
        tvaEnabled: Boolean,
        tvaRate: Number
    }

    connect() {
        this.updateTotals();
    }

    updateTotals() {
        let totalHT = 0;
        let count = 0;
        const selectedItems = [];

        // Reset category counts
        const categoryCounts = {};
        this.element.querySelectorAll('[data-simulator-category-count]').forEach(span => {
            const cat = span.dataset.simulatorCategoryCount;
            categoryCounts[cat] = 0;
        });

        // Find all checked items
        this.element.querySelectorAll('input[type="checkbox"]:checked').forEach(input => {
            const price = parseFloat(input.dataset.price);
            const name = input.dataset.name;
            const unit = input.dataset.unit;
            const category = input.dataset.category;

            totalHT += price;
            count++;

            if (category) {
                categoryCounts[category] = (categoryCounts[category] || 0) + 1;
            }

            selectedItems.push({ name, price, unit });
        });

        // Update category indicators
        Object.entries(categoryCounts).forEach(([cat, val]) => {
            const badge = this.element.querySelector(`[data-simulator-category-count="${cat}"]`);
            if (badge) {
                badge.textContent = val;
                if (val > 0) {
                    badge.classList.remove('hidden');
                    // Petit effet d'échelle lors de l'incrémentation
                    badge.animate([
                        { transform: 'scale(1.2)', offset: 0 },
                        { transform: 'scale(1)', offset: 1 }
                    ], { duration: 200 });

                    // Accentuer l'icône
                    const icon = this.element.querySelector(`[data-simulator-category-icon="${cat}"]`);
                    if (icon) {
                        icon.classList.add('bg-blue-500/30', 'border-blue-500/60', 'shadow-[0_0_15px_-3px_rgba(59,130,246,0.5)]');
                        icon.classList.remove('bg-blue-500/10', 'border-blue-500/20');
                    }
                } else {
                    badge.classList.add('hidden');
                    // Réinitialiser l'icône
                    const icon = this.element.querySelector(`[data-simulator-category-icon="${cat}"]`);
                    if (icon) {
                        icon.classList.remove('bg-blue-500/30', 'border-blue-500/60', 'shadow-[0_0_15px_-3px_rgba(59,130,246,0.5)]');
                        icon.classList.add('bg-blue-500/10', 'border-blue-500/20');
                    }
                }
            }
        });

        const tvaEnabled = this.tvaEnabledValue;
        const tvaRatePercentage = this.tvaRateValue;
        const tvaRate = tvaEnabled ? (tvaRatePercentage / 100) : 0;
        const tvaAmount = totalHT * tvaRate;
        const totalTTC = totalHT + tvaAmount;

        // Update UI with animations
        this.animateValue(this.totalHTTarget, totalHT);

        if (tvaEnabled && this.hasTotalTVATarget) {
            this.animateValue(this.totalTVATarget, tvaAmount);
            this.animateValue(this.totalTTCTarget, totalTTC);
            if (this.hasTvaRowTarget) this.tvaRowTarget.classList.remove('hidden');
        } else {
            this.animateValue(this.totalTTCTarget, totalHT);
            if (this.hasTvaRowTarget) this.tvaRowTarget.classList.add('hidden');
        }

        if (this.hasItemCountTarget) {
            this.itemCountTarget.textContent = count;
        }

        // Update selection summary
        this.updateSummary(selectedItems);

        // Tech feedback (optional: haptic or visual pop)
        if (count > 0 && window.navigator.vibrate) {
            window.navigator.vibrate(5);
        }
    }

    animateValue(target, endValue) {
        const startValue = parseFloat(target.textContent.replace(/[^\d.-]/g, '')) || 0;
        const duration = 500;
        const startTime = performance.now();

        const format = (val) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val);

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easing = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            const currentValue = startValue + (endValue - startValue) * easing;

            target.textContent = format(currentValue);

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };

        requestAnimationFrame(animate);
    }

    updateSummary(items) {
        if (!this.hasSelectionListTarget) return;

        if (items.length === 0) {
            this.selectionListTarget.innerHTML = `
                <div class="text-slate-400 text-sm italic py-4 text-center">
                    Aucun service sélectionné
                </div>
            `;
            this.ctaButtonTarget.classList.add('opacity-50', 'pointer-events-none');
            return;
        }

        this.ctaButtonTarget.classList.remove('opacity-50', 'pointer-events-none');

        const html = items.map(item => `
            <div class="flex justify-between items-start py-2 border-b border-white/5 last:border-0 group">
                <div class="flex flex-col">
                    <span class="text-white text-sm font-medium">${item.name}</span>
                    <span class="text-slate-400 text-xs">${item.unit !== 'forfait' ? 'Démarrage à partir de' : 'Forfait'}</span>
                </div>
                <span class="text-blue-400 font-semibold text-sm">${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(item.price)}</span>
            </div>
        `).join('');

        this.selectionListTarget.innerHTML = html;
    }
}
