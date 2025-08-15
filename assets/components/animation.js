document.addEventListener("DOMContentLoaded", () => {
    const elements = document.querySelectorAll(".scroll-animate");

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("animate-fade-up");
                entry.target.classList.remove("scroll-animate");
                observer.unobserve(entry.target); // On ne l'observe plus après animation
            }
        });
    }, {
        threshold: 0.2 // 20% visible avant déclenchement
    });

    elements.forEach(el => observer.observe(el));
});
