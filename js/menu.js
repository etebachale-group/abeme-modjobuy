document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');
    const body = document.body;

    // Función para abrir el menú
    function openMenu() {
        navMenu.classList.add('show');
        body.classList.add('menu-open');
        const icon = menuToggle.querySelector('i');
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
    }

    // Función para cerrar el menú
    function closeMenu() {
        navMenu.classList.remove('show');
        body.classList.remove('menu-open');
        const icon = menuToggle.querySelector('i');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
    }

    // Toggle del menú
    menuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (navMenu.classList.contains('show')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Cerrar menú al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!navMenu.contains(e.target) && !menuToggle.contains(e.target) && navMenu.classList.contains('show')) {
            closeMenu();
        }
    });

    // Cerrar menú al presionar la tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && navMenu.classList.contains('show')) {
            closeMenu();
        }
    });

    // Prevenir que los clics dentro del menú lo cierren
    navMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Cerrar menú en modo desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            menuToggle.classList.remove('active');
            navMenu.classList.remove('active');
            body.style.overflow = '';
        }
    });

    // Añadir transición suave al cambiar entre móvil y desktop
    let resizeTimer;
    window.addEventListener('resize', function() {
        document.body.classList.add('resize-transition-stopper');
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            document.body.classList.remove('resize-transition-stopper');
        }, 400);
    });
});
