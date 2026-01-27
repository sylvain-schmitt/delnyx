import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour la recherche globale dans la navbar
 * Affiche un dropdown avec les résultats en temps réel
 */
export default class extends Controller {
    static targets = ['input', 'dropdown', 'results', 'loading', 'noResults'];
    static values = {
        url: String,
        debounce: { type: Number, default: 300 }
    };

    connect() {
        this.selectedIndex = -1;
        this.results = [];
        this.debounceTimer = null;

        // Fermer le dropdown en cliquant ailleurs
        document.addEventListener('click', this.handleClickOutside.bind(this));
    }

    disconnect() {
        document.removeEventListener('click', this.handleClickOutside.bind(this));
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
    }

    /**
     * Recherche avec debounce
     */
    search(event) {
        const query = event.target.value.trim();

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        if (query.length < 2) {
            this.hideDropdown();
            return;
        }

        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, this.debounceValue);
    }

    /**
     * Effectue la recherche API
     */
    async performSearch(query) {
        this.showLoading();

        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            this.results = this.flattenResults(data.results);
            this.renderResults(data);
        } catch (error) {
            console.error('Erreur de recherche:', error);
            this.showNoResults();
        }
    }

    /**
     * Aplatit les résultats pour la navigation clavier
     */
    flattenResults(results) {
        const flat = [];
        for (const type of ['clients', 'quotes', 'invoices', 'projects']) {
            if (results[type]) {
                flat.push(...results[type]);
            }
        }
        return flat;
    }

    /**
     * Affiche les résultats dans le dropdown
     */
    renderResults(data) {
        const { results, total, showAllUrl } = data;

        if (total === 0) {
            this.showNoResults();
            return;
        }

        const typeConfig = {
            clients: { label: 'Clients', icon: 'user', color: 'blue' },
            quotes: { label: 'Devis', icon: 'file-text', color: 'green' },
            invoices: { label: 'Factures', icon: 'receipt', color: 'amber' },
            projects: { label: 'Projets', icon: 'folder', color: 'purple' }
        };

        let html = '';

        for (const [type, items] of Object.entries(results)) {
            if (items.length === 0) continue;

            const config = typeConfig[type];
            html += `
                <div class="px-3 py-2 bg-white/5">
                    <span class="text-xs font-semibold text-white/60 uppercase">${config.label}</span>
                </div>
            `;

            for (const item of items) {
                const index = this.results.indexOf(item);
                html += `
                    <a href="${item.url}"
                       class="flex items-center px-4 py-3 hover:bg-white/10 transition-colors search-result"
                       data-index="${index}"
                       data-action="mouseenter->global-search#highlightResult">
                        <div class="w-8 h-8 bg-${config.color}-500/20 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-${config.color}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                ${this.getIconPath(config.icon)}
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium text-sm truncate">${item.title}</p>
                            ${item.subtitle ? `<p class="text-white/60 text-xs truncate">${item.subtitle}</p>` : ''}
                        </div>
                    </a>
                `;
            }
        }

        // Lien "Voir tous les résultats"
        html += `
            <div class="border-t border-white/10 p-3">
                <a href="${showAllUrl}"
                   class="flex items-center justify-center gap-2 px-4 py-2 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 transition-colors text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Voir tous les résultats (${total})
                </a>
            </div>
        `;

        this.resultsTarget.innerHTML = html;
        this.showDropdown();
        this.hideLoading();
    }

    /**
     * Retourne le path SVG pour une icône
     */
    getIconPath(icon) {
        const icons = {
            'user': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
            'file-text': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
            'receipt': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>',
            'folder': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>'
        };
        return icons[icon] || '';
    }

    /**
     * Navigation clavier
     */
    navigate(event) {
        if (!this.hasDropdownTarget || this.dropdownTarget.classList.contains('hidden')) {
            return;
        }

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
                this.updateHighlight();
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                this.updateHighlight();
                break;
            case 'Enter':
                event.preventDefault();
                if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                    window.location.href = this.results[this.selectedIndex].url;
                }
                break;
            case 'Escape':
                this.hideDropdown();
                event.target.blur();
                break;
        }
    }

    /**
     * Met à jour le surlignage du résultat sélectionné
     */
    updateHighlight() {
        const items = this.resultsTarget.querySelectorAll('.search-result');
        items.forEach((item, index) => {
            if (parseInt(item.dataset.index) === this.selectedIndex) {
                item.classList.add('bg-white/10');
            } else {
                item.classList.remove('bg-white/10');
            }
        });
    }

    /**
     * Surligne un résultat au survol
     */
    highlightResult(event) {
        this.selectedIndex = parseInt(event.currentTarget.dataset.index);
        this.updateHighlight();
    }

    /**
     * Affiche le dropdown
     */
    showDropdown() {
        if (this.hasDropdownTarget) {
            this.dropdownTarget.classList.remove('hidden');
        }
    }

    /**
     * Cache le dropdown
     */
    hideDropdown() {
        if (this.hasDropdownTarget) {
            this.dropdownTarget.classList.add('hidden');
        }
        this.selectedIndex = -1;
    }

    /**
     * Affiche le loader
     */
    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove('hidden');
        }
        if (this.hasNoResultsTarget) {
            this.noResultsTarget.classList.add('hidden');
        }
        if (this.hasResultsTarget) {
            this.resultsTarget.innerHTML = '';
        }
        this.showDropdown();
    }

    /**
     * Cache le loader
     */
    hideLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.add('hidden');
        }
    }

    /**
     * Affiche "Aucun résultat"
     */
    showNoResults() {
        this.hideLoading();
        if (this.hasNoResultsTarget) {
            this.noResultsTarget.classList.remove('hidden');
        }
        if (this.hasResultsTarget) {
            this.resultsTarget.innerHTML = '';
        }
        this.showDropdown();
    }

    /**
     * Ferme le dropdown au clic extérieur
     */
    handleClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.hideDropdown();
        }
    }
}
