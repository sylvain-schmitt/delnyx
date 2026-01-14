/**
 * Gestion de la signature électronique avec Signature Pad
 * Supporte 3 méthodes : Taper, Dessiner, Uploader
 */

document.addEventListener('DOMContentLoaded', function () {
    const signatureTabs = document.querySelectorAll('[data-signature-tab]');
    const signaturePanes = document.querySelectorAll('[data-signature-pane]');
    const signatureForm = document.getElementById('signature-form');
    const canvas = document.getElementById('signature-canvas');
    const clearButton = document.getElementById('clear-signature');
    const submitButton = document.getElementById('submit-signature');

    let signaturePad = null;
    let currentMethod = 'text';

    // Initialiser Signature Pad si le canvas existe
    if (canvas) {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 1,
            maxWidth: 3,
        });

        // Redimensionner le canvas pour qu'il soit responsive
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear();
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    }

    // Gestion des onglets
    signatureTabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            const method = this.dataset.signatureTab;

            // Retirer la classe active de tous les onglets
            signatureTabs.forEach(t => {
                t.classList.remove('border-blue-500', 'text-white');
                t.classList.add('border-transparent', 'text-white/60');
            });

            // Ajouter la classe active à l'onglet cliqué
            this.classList.remove('border-transparent', 'text-white/60');
            this.classList.add('border-blue-500', 'text-white');

            // Cacher tous les panneaux
            signaturePanes.forEach(pane => {
                pane.classList.add('hidden');
            });

            // Afficher le panneau correspondant
            const activePane = document.querySelector(`[data-signature-pane="${method}"]`);
            if (activePane) {
                activePane.classList.remove('hidden');
            }

            currentMethod = method;
        });
    });

    // Bouton Effacer pour le canvas
    if (clearButton && signaturePad) {
        clearButton.addEventListener('click', function (e) {
            e.preventDefault();
            signaturePad.clear();
        });
    }

    // Validation du formulaire
    if (signatureForm) {
        signatureForm.addEventListener('submit', function (e) {
            e.preventDefault();

            let isValid = false;
            let signatureData = {};

            // Validation selon la méthode choisie
            if (currentMethod === 'text') {
                const nameInput = document.getElementById('signature-name');
                if (nameInput && nameInput.value.trim()) {
                    isValid = true;
                    signatureData = {
                        name: nameInput.value.trim()
                    };
                } else {
                    showError('Veuillez saisir votre nom pour signer.');
                    return;
                }
            } else if (currentMethod === 'draw') {
                if (signaturePad && !signaturePad.isEmpty()) {
                    isValid = true;
                    signatureData = {
                        data: signaturePad.toDataURL('image/png')
                    };
                } else {
                    showError('Veuillez dessiner votre signature.');
                    return;
                }
            } else if (currentMethod === 'upload') {
                const fileInput = document.getElementById('signature-file');
                if (fileInput && fileInput.files.length > 0) {
                    const file = fileInput.files[0];

                    // Validation du fichier
                    if (!file.type.match('image/png') && !file.type.match('image/jpeg')) {
                        showError('Seuls les fichiers PNG et JPEG sont acceptés.');
                        return;
                    }

                    if (file.size > 500 * 1024) { // 500KB max
                        showError('Le fichier ne doit pas dépasser 500 Ko.');
                        return;
                    }

                    isValid = true;
                    // Le fichier sera uploadé via le formulaire normal
                } else {
                    showError('Veuillez sélectionner une image de signature.');
                    return;
                }
            }

            if (isValid) {
                // Ajouter la méthode et les données au formulaire
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = 'signature_method';
                methodInput.value = currentMethod;
                signatureForm.appendChild(methodInput);

                if (currentMethod !== 'upload') {
                    const dataInput = document.createElement('input');
                    dataInput.type = 'hidden';
                    dataInput.name = 'signature_data';
                    dataInput.value = JSON.stringify(signatureData);
                    signatureForm.appendChild(dataInput);
                }

                // Soumettre le formulaire
                signatureForm.submit();
            }
        });
    }

    // Prévisualisation de l'image uploadée
    const fileInput = document.getElementById('signature-file');
    const preview = document.getElementById('signature-preview');

    if (fileInput && preview) {
        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];

            if (file) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    preview.innerHTML = `<img src="${event.target.result}" alt="Aperçu" class="max-w-full max-h-48 rounded border border-white/20">`;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<p class="text-white/60 text-sm">Aucun fichier sélectionné</p>';
            }
        });
    }

    // Fonction pour afficher les erreurs
    function showError(message) {
        const errorDiv = document.getElementById('signature-error');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');

            setTimeout(() => {
                errorDiv.classList.add('hidden');
            }, 5000);
        } else {
            alert(message);
        }
    }
});
