// assets/components/particles-config.js
function loadParticlesScript(callback) {
    const script = document.createElement('script');
    script.src = '/components/particles.min.js'; // chemin vers ton fichier
    script.onload = callback;
    document.body.appendChild(script);
}

function initParticles(id) {
    const configs = {
        'particles-cyan': {
            particles: {
                number: { value: 70, density: { enable: true, value_area: 300 } },
                color: { value: "#3b82f6" },
                shape: { type: "circle" },
                opacity: { value: 1, random: true, anim: { enable: true, speed: 1.5, opacity_min: 0.4, sync: false } },
                size: { value: 5, random: true, anim: { enable: true, speed: 3, size_min: 2, sync: false } },
                line_linked: { enable: true, distance: 120, color: "#3b82f6", opacity: 0.8, width: 2 },
                move: { enable: true, speed: 1.5, direction: "none", random: false, straight: false, out_mode: "bounce", bounce: true }
            },
            interactivity: {
                events: { onhover: { enable: true, mode: "grab" }, onclick: { enable: true, mode: "push" } },
                modes: { grab: { distance: 200, line_linked: { opacity: 1 } }, push: { particles_nb: 3 } }
            }
        },
        'particles-purple': {
            particles: {
                number: { value: 60, density: { enable: true, value_area: 300 } },
                color: { value: "#8b5cf6" },
                shape: { type: "circle" },
                opacity: { value: 0.9, random: true, anim: { enable: true, speed: 1.2, opacity_min: 0.3, sync: false } },
                size: { value: 6, random: true, anim: { enable: true, speed: 2.5, size_min: 3, sync: false } },
                line_linked: { enable: true, distance: 130, color: "#8b5cf6", opacity: 0.7, width: 2.5 },
                move: { enable: true, speed: 1.2, direction: "none", random: false, straight: false, out_mode: "bounce", bounce: true }
            },
            interactivity: {
                events: { onhover: { enable: true, mode: "grab" }, onclick: { enable: true, mode: "repulse" } },
                modes: { grab: { distance: 180, line_linked: { opacity: 1 } }, repulse: { distance: 120 } }
            }
        }
    };

    if (configs[id]) {
        window.particlesJS(id, configs[id]);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const sections = ['particles-cyan', 'particles-purple'];

    sections.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return; // pas présent sur la page → rien à faire

        const observer = new IntersectionObserver((entries, obs) => {
            if (!entries[0].isIntersecting) return;

            obs.unobserve(el);

            if (!window.particlesJS) {
                loadParticlesScript(() => initParticles(id));
            } else {
                initParticles(id);
            }

        }, { threshold: 0.1 });

        observer.observe(el);
    });
});
