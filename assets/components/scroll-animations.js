/**
 * Système d'animations au scroll optimisé avec Intersection Observer
 * Améliore les performances et la fluidité des animations
 */

// Configuration de l'Intersection Observer
const observerOptions = {
    root: null,
    rootMargin: '0px 0px -50px 0px', // Déclenche l'animation un peu avant que l'élément soit visible
    threshold: 0.1 // L'élément doit être visible à 10% pour déclencher l'animation
};

// Callback appelé quand un élément entre/sort du viewport
const handleIntersection = (entries, observer) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            // Ajoute la classe visible pour déclencher l'animation
            entry.target.classList.add('visible');

            // Optionnel : arrêter d'observer cet élément une fois animé
            // pour des performances optimales
            observer.unobserve(entry.target);
        }
    });
};

// Initialise l'observer
const scrollObserver = new IntersectionObserver(handleIntersection, observerOptions);

// Démarre les observations quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    // Sélectionne tous les éléments avec la classe scroll-animate
    const animatedElements = document.querySelectorAll('.scroll-animate');

    // Commence à observer chaque élément
    animatedElements.forEach(element => {
        scrollObserver.observe(element);
    });

    // Ajoute une animation de parallax subtile aux lumières de fond
    const lightElements = document.querySelectorAll('.light-ambient');

    // Observer pour le parallax des lumières (effet plus subtil)
    const lightObserverOptions = {
        root: null,
        rootMargin: '100px',
        threshold: 0
    };

    const lightObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Ajoute une classe pour déclencher les animations des lumières
                entry.target.style.animationPlayState = 'running';
            } else {
                // Pause l'animation quand l'élément n'est pas visible
                // pour économiser les ressources
                entry.target.style.animationPlayState = 'paused';
            }
        });
    }, lightObserverOptions);

    lightElements.forEach(element => {
        lightObserver.observe(element);
    });
});

// Optimisation des performances : utilise requestAnimationFrame 
// pour les animations custom si nécessaire
let ticking = false;

function updateAnimations() {
    // Place ici les animations custom qui nécessitent requestAnimationFrame
    ticking = false;
}

function requestTick() {
    if (!ticking) {
        requestAnimationFrame(updateAnimations);
        ticking = true;
    }
}

// Export pour utilisation dans d'autres modules si nécessaire
export { scrollObserver, handleIntersection };
