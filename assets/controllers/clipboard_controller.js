import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur pour copier du texte dans le presse-papiers
 */
export default class extends Controller {
    static values = {
        text: String,
        successMessage: { type: String, default: 'Copié !' }
    }

    /**
     * Copie le texte dans le presse-papiers
     */
    async copy(event) {
        const button = event.currentTarget;
        const textToCopy = this.textValue;

        if (!textToCopy) {
            console.error('Aucun texte à copier');
            return;
        }

        try {
            // Utiliser l'API Clipboard moderne si disponible
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(textToCopy);
            } else {
                // Fallback pour les navigateurs plus anciens
                this.copyToClipboardFallback(textToCopy);
            }

            // Afficher le feedback visuel
            this.showSuccess(button);
        } catch (error) {
            console.error('Erreur lors de la copie:', error);
            this.showError(button);
        }
    }

    /**
     * Méthode de fallback pour copier dans le presse-papiers
     */
    copyToClipboardFallback(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        textArea.style.top = '-9999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (!successful) {
                throw new Error('La commande de copie a échoué');
            }
        } finally {
            document.body.removeChild(textArea);
        }
    }

    /**
     * Affiche un feedback de succès
     */
    showSuccess(button) {
        const originalContent = button.innerHTML;
        const successMessage = this.successMessageValue;

        // Changer le contenu du bouton temporairement
        button.innerHTML = `
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>${successMessage}</span>
        `;
        button.classList.add('!bg-green-500/20', '!border-green-500/30', '!text-green-300');
        button.disabled = true;

        // Restaurer après 2 secondes
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.classList.remove('!bg-green-500/20', '!border-green-500/30', '!text-green-300');
            button.disabled = false;
        }, 2000);
    }

    /**
     * Affiche un feedback d'erreur
     */
    showError(button) {
        const originalContent = button.innerHTML;

        button.innerHTML = `
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span>Erreur</span>
        `;
        button.classList.add('!bg-red-500/20', '!border-red-500/30', '!text-red-300');

        setTimeout(() => {
            button.innerHTML = originalContent;
            button.classList.remove('!bg-red-500/20', '!border-red-500/30', '!text-red-300');
        }, 2000);
    }
}


