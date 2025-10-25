<style>
/* Minimal functional styles for collapsible legacy nav (no design) */
.nav-container .nav-toggle { display: none !important; }
@media (min-width: 769px) {
    .nav-container .nav-menu { display: flex !important; align-items: center; gap: .5rem; }
    .nav-container .nav-menu > li { display: inline-block; }
}
@media (max-width: 768px) {
    .nav-container { position: relative; }
    .nav-container .nav-toggle { display: inline-block !important; }
    .nav-container .nav-menu { display: none !important; }
    .nav-container.open .nav-menu { display: block !important; position: absolute; top: calc(100% + 6px); right: 0; z-index: 1000; background-color: inherit; }
}
</style>
<nav class="nav-container" id="legacyNav" role="navigation" aria-label="Navegación">
    <button type="button" class="nav-toggle" id="legacyMenuToggle" aria-label="Abrir menú" aria-controls="legacyNavMenu" aria-expanded="false">☰ Menú</button>
    <ul class="nav-menu" id="legacyNavMenu">
    <li><a href="index.php" class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? ' active' : ''; ?>"><i class="fas fa-home"></i> Inicio</a></li>
    <li><a href="Rotteri nza kus web/index.php" class="nav-link"><i class="fas fa-shopping-cart"></i> Compras</a></li>
    <li><a href="track_shipment.php" class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'track_shipment.php' ? ' active' : ''; ?>"><i class="fas fa-search"></i> Seguimiento</a></li>
    <li><a href="index.php#contact" class="nav-link"><i class="fas fa-envelope"></i> Contacto</a></li>
        <?php if(isAuthenticated()): ?>
            <li><a href="admin.php" class="nav-link"><i class="fas fa-cog"></i> Administración</a></li>
            <li><a href="archived_shipments.php" class="nav-link"><i class="fas fa-archive"></i> Archivados</a></li>
            <li><a href="caja.php" class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'caja.php' ? ' active' : ''; ?>"><i class="fas fa-vault"></i> Caja</a></li>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión (<?php echo currentUser(); ?>)</a></li>
        <?php else: ?>
            <li><a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</a></li>
        <?php endif; ?>
        </ul>
</nav>
<script>
(function(){
    const container = document.getElementById('legacyNav') || document.querySelector('.nav-container');
    const btn = document.getElementById('legacyMenuToggle') || container?.querySelector('.nav-toggle');
    const menu = document.getElementById('legacyNavMenu') || container?.querySelector('.nav-menu');
    if(btn && container && menu){
        const setExpanded = (val)=> btn.setAttribute('aria-expanded', val ? 'true' : 'false');
        const t = ()=> { const willOpen = !container.classList.contains('open'); container.classList.toggle('open'); setExpanded(willOpen); };
        const close = ()=> { container.classList.remove('open'); setExpanded(false); };
        btn.addEventListener('click', t);
        btn.addEventListener('keydown', (e)=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); t(); }});
        container.querySelectorAll('.nav-menu a').forEach(a=> a.addEventListener('click', close));
        document.addEventListener('click', (e)=>{
            if(window.innerWidth<=768){
                const within = e.target.closest && (e.target.closest('#legacyNav') || e.target.closest('#legacyMenuToggle') || e.target.closest('.nav-container') || e.target.closest('.nav-toggle'));
                if(!within) close();
            }
        });
        window.addEventListener('resize', ()=>{ if(window.innerWidth>768){ close(); } });
    }
})();
</script>