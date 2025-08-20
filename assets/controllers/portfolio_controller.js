import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        projectId: Number
    }

    static targets = ['modal', 'modalTitle', 'modalContent']

    connect() { }

    showProjectDetails(event) {
        const projectId = event.currentTarget.dataset.portfolioProjectIdValue;

        // Récupérer les données du projet depuis l'élément parent
        const projectCard = event.currentTarget.closest('.group');
        const projectTitle = projectCard.querySelector('h3').textContent.trim();
        // Récupérer la description complète depuis l'attribut data
        const projectDescription = projectCard.querySelector('[data-project-description]')?.dataset.projectDescription || projectCard.querySelector('p').textContent.trim();
        const projectUrl = projectCard.querySelector('a[href]')?.href || null;

        // Récupérer l'image du projet
        const projectImage = projectCard.querySelector('img');
        const imageSrc = projectImage ? projectImage.src : null;
        const imageAlt = projectImage ? projectImage.alt : projectTitle;

        // Récupérer la date (à ajouter dans le template)
        const projectDate = projectCard.querySelector('[data-project-date]')?.dataset.projectDate || null;

        // Récupérer les technologies
        const technologyBadges = projectCard.querySelectorAll('.flex.flex-wrap.gap-2 span');
        const technologies = Array.from(technologyBadges).map(badge => badge.textContent.trim());

        // Construire le contenu de la modal
        this.modalTitleTarget.textContent = projectTitle;

        let content = `
            <div class="space-y-6">
                <!-- Image et informations principales -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Image du projet -->
                    <div class="relative">
                        ${imageSrc ? `
                            <img src="${imageSrc}" alt="${imageAlt}" class="w-full h-64 object-cover rounded-lg shadow-lg">
                        ` : `
                            <div class="w-full h-64 bg-gradient-to-br from-blue-500/20 to-purple-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-16 h-16 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        `}
                    </div>

                    <!-- Informations principales -->
                    <div class="space-y-4">
                        <!-- Date -->
                        ${projectDate ? `
                        <div class="flex items-center justify-end">
                            <span class="text-sm text-gray-400">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                ${projectDate}
                            </span>
                        </div>
                        ` : ''}

                        <!-- Description complète -->
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-3">Description</h3>
                            <p class="text-gray-300 leading-relaxed">${projectDescription}</p>
                        </div>
                    </div>
                </div>

                <!-- Technologies utilisées -->
                ${technologies.length > 0 ? `
                <div>
                    <h3 class="text-lg font-semibold text-white mb-3">Technologies utilisées</h3>
                    <div class="flex flex-wrap gap-2">
                        ${technologies.map(tech => `
                            <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-500/20 text-blue-300 border border-blue-500/30">
                                ${tech}
                            </span>
                        `).join('')}
                    </div>
                </div>
                ` : ''}

                <!-- Lien vers le projet -->
                ${projectUrl ? `
                <div>
                    <h3 class="text-lg font-semibold text-white mb-3">Lien du projet</h3>
                    <a href="${projectUrl}" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-300 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        Voir le projet en ligne
                    </a>
                </div>
                ` : ''}
            </div>
        `;

        this.modalContentTarget.innerHTML = content;
        this.showModal();
    }

    showModal() {
        this.modalTarget.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Empêcher le scroll
    }

    closeModal() {
        this.modalTarget.classList.add('hidden');
        document.body.style.overflow = ''; // Restaurer le scroll
    }

    // Fermer la modal en cliquant à l'extérieur
    clickOutside(event) {
        if (event.target === this.modalTarget) {
            this.closeModal();
        }
    }

    // Fermer avec la touche Escape
    keydown(event) {
        if (event.key === 'Escape') {
            this.closeModal();
        }
    }
}
