import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour gérer l'activation de la TVA par ligne
 * Utilisé pour les factures, avenants et avoirs
 */
export default class extends Controller {
    static targets = ['toggle', 'rateWrapper'];
    static values = { enabled: Boolean };

    connect() {
        // Pour les devis : vérifier l'état du checkbox usePerLineTva (même s'il est caché)
        const usePerLineTvaCheckbox = this.element.querySelector('input[name*="[usePerLineTva]"]') || 
                                     this.element.querySelector('input[data-quote-use-per-line-tva]');
        if (usePerLineTvaCheckbox) {
            // Si le checkbox est coché, activer le mode TVA par ligne
            if (usePerLineTvaCheckbox.checked) {
                this.enabledValue = true;
            }
        } else {
            // Pour les autres formulaires : détecter si au moins une ligne a un taux de TVA défini
            const rateWrappers = this.element.querySelectorAll('[data-tva-per-line-target="rateWrapper"]');
            let hasTvaRate = false;
            
            rateWrappers.forEach(wrapper => {
                const select = wrapper.querySelector('select');
                if (select && select.value && select.value !== '') {
                    hasTvaRate = true;
                }
            });
            
            // Si des lignes ont déjà un taux de TVA, activer le mode TVA par ligne
            if (hasTvaRate) {
                this.enabledValue = true;
            }
        }
        
        // Appliquer l'état initial
        this.applyVisibility();
        
        // Observer les changements dans le DOM pour mettre à jour les nouvelles lignes ajoutées
        const linesContainer = this.element.querySelector('[data-quote-form-target="linesContainer"]') || 
                              this.element.querySelector('[data-admin-form-target="linesContainer"]') ||
                              this.element;
        
        this.observer = new MutationObserver(() => {
            // Appliquer la visibilité aux nouvelles lignes
            this.applyVisibility();
        });
        
        this.observer.observe(linesContainer, { childList: true, subtree: true });
    }
    
    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }

    toggle() {
        // Inverser l'état
        this.enabledValue = !this.enabledValue;
        
        // Pour les devis : synchroniser avec le checkbox usePerLineTva (même s'il est caché)
        const usePerLineTvaCheckbox = this.element.querySelector('input[name*="[usePerLineTva]"]') || 
                                     this.element.querySelector('input[data-quote-use-per-line-tva]');
        if (usePerLineTvaCheckbox) {
            usePerLineTvaCheckbox.checked = this.enabledValue;
            usePerLineTvaCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        this.applyVisibility();
    }

    applyVisibility() {
        const enabled = this.hasEnabledValue ? this.enabledValue : false;
        const rateWrappers = this.element.querySelectorAll('[data-tva-per-line-target="rateWrapper"]');

        rateWrappers.forEach(wrapper => {
            if (enabled) {
                wrapper.classList.remove('hidden');
                wrapper.style.display = '';
            } else {
                wrapper.classList.add('hidden');
                wrapper.style.display = 'none';
                // Réinitialiser les valeurs si on désactive (sauf pour les devis où on garde la valeur)
                const select = wrapper.querySelector('select');
                if (select && !select.hasAttribute('data-keep-value')) {
                    // Pour les devis, ne pas réinitialiser si le checkbox usePerLineTva est coché
                    const usePerLineTvaCheckbox = this.element.querySelector('input[name*="[usePerLineTva]"]') || 
                                                 this.element.querySelector('input[data-quote-use-per-line-tva]');
                    if (!usePerLineTvaCheckbox || !usePerLineTvaCheckbox.checked) {
                        select.value = '';
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
        });

        // Mettre à jour le texte du bouton (préserver l'icône et la structure)
        const button = this.element.querySelector('[data-tva-per-line-target="toggle"]');
        if (button) {
            const span = button.querySelector('span');
            
            if (enabled) {
                const label = button.dataset.labelEnabled || 'Désactiver la TVA par ligne';
                if (span) {
                    span.textContent = label;
                } else {
                    button.textContent = label;
                }
                button.classList.remove('btn-secondary');
                button.classList.add('btn-primary');
            } else {
                const label = button.dataset.labelDisabled || 'Appliquer la TVA par ligne';
                if (span) {
                    span.textContent = label;
                } else {
                    button.textContent = label;
                }
                button.classList.remove('btn-primary');
                button.classList.add('btn-secondary');
            }
        }
    }
}

