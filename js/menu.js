document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const navContainer = document.querySelector('.header .nav-container');
    const navMenu = document.getElementById('navMenu');
    if (!menuToggle || !navContainer || !navMenu) return;

    const setExpanded = (val)=> menuToggle.setAttribute('aria-expanded', val ? 'true' : 'false');
    const icon = () => menuToggle.querySelector('i');
    const setIcon = (open)=>{ if (!icon()) return; icon().classList.toggle('fa-bars', !open); icon().classList.toggle('fa-times', !!open); };

    function openMenu() {
        navContainer.classList.add('open');
        setExpanded(true);
        setIcon(true);
    }

    function closeMenu() {
        navContainer.classList.remove('open');
        setExpanded(false);
        setIcon(false);
    }

    function flip(e){ e?.stopPropagation?.(); const willOpen = !navContainer.classList.contains('open'); willOpen ? openMenu() : closeMenu(); }

    menuToggle.addEventListener('click', flip);
    menuToggle.addEventListener('keydown', (e)=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); flip(e); }});

    // Close when clicking a link
    navMenu.querySelectorAll('a').forEach(a=> a.addEventListener('click', closeMenu));

    // Close on outside click (mobile)
    document.addEventListener('click', (e)=>{
        if (window.innerWidth<=768){
            const within = e.target.closest && (e.target.closest('.header .nav-container') || e.target.closest('#menuToggle'));
            if(!within) closeMenu();
        }
    });

    // Guard for desktop
    window.addEventListener('resize', ()=>{ if(window.innerWidth>768){ closeMenu(); } });
});
