document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const iconMenu = document.getElementById('icon-menu');
    const iconClose = document.getElementById('icon-close');
    const navbar = document.getElementById('navbar');
    
    let isMenuOpen = false;
    
    // Toggle mobile menu
    menuToggle.addEventListener('click', function() {
        isMenuOpen = !isMenuOpen;
        
        if (isMenuOpen) {
            mobileMenu.style.maxHeight = mobileMenu.scrollHeight + 'px';
            iconMenu.classList.add('hidden');
            iconClose.classList.remove('hidden');
        } else {
            mobileMenu.style.maxHeight = '0';
            iconMenu.classList.remove('hidden');
            iconClose.classList.add('hidden');
        }
    });
    
    // Navbar scroll effect
    let lastScrollY = window.scrollY;
    
    window.addEventListener('scroll', function() {
        const currentScrollY = window.scrollY;
        
        if (currentScrollY > 100) {
            // Navbar plus compacte au scroll
            navbar.classList.add('navbar-scrolled');
            navbar.style.transform = currentScrollY > lastScrollY && currentScrollY > 200 ? 'translateY(-100%)' : 'translateY(0)';
        } else {
            navbar.classList.remove('navbar-scrolled');
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScrollY = currentScrollY;
    });
    
    // Fermer le menu mobile en cliquant sur un lien
    document.querySelectorAll('#mobile-menu a').forEach(link => {
        link.addEventListener('click', function() {
            isMenuOpen = false;
            mobileMenu.style.maxHeight = '0';
            iconMenu.classList.remove('hidden');
            iconClose.classList.add('hidden');
        });
    });
    
    // Fermer le menu mobile en cliquant ailleurs
    document.addEventListener('click', function(event) {
        if (isMenuOpen && !navbar.contains(event.target)) {
            isMenuOpen = false;
            mobileMenu.style.maxHeight = '0';
            iconMenu.classList.remove('hidden');
            iconClose.classList.add('hidden');
        }
    });
});