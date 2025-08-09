document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.querySelector('#menu-toggle');
    const mobileMenu = document.querySelector('#mobile-menu');
    const iconMenu = document.querySelector('#icon-menu');
    const iconClose = document.querySelector('#icon-close');

    const openMenu = () => {
        const menuHeight = mobileMenu.scrollHeight; // recalcul dynamique
        mobileMenu.style.maxHeight = menuHeight + 'px';
        iconMenu.classList.add('hidden');
        iconClose.classList.remove('hidden');
    };

    const closeMenu = () => {
        mobileMenu.style.maxHeight = '0';
        iconMenu.classList.remove('hidden');
        iconClose.classList.add('hidden');
    };

    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            if (mobileMenu.style.maxHeight === '0px' || mobileMenu.style.maxHeight === '') {
                openMenu();
            } else {
                closeMenu();
            }
        });

        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('click', (event) => {
            if (
                mobileMenu.style.maxHeight !== '0px' &&
                !mobileMenu.contains(event.target) &&
                !menuToggle.contains(event.target)
            ) {
                closeMenu();
            }
        });

        // Menu fermé au chargement
        closeMenu();
    }
});
