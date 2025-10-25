<?php
// Reusable adaptive header with responsive menu
if (!function_exists('rk_is_authenticated')) {
    function rk_is_authenticated() {
        return function_exists('isAuthenticated') ? isAuthenticated() : false;
    }
}
if (!function_exists('rk_is_admin')) {
    function rk_is_admin() {
        return function_exists('isAdmin') ? isAdmin() : false;
    }
}

// Compute prefix to web root for links/assets
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isAdminArea = strpos($reqPath, '/admin/') !== false;
$prefix = $isAdminArea ? '../' : '';

// Helper: compute active link
if (!function_exists('rk_nav_active')){
    function rk_nav_active($key, $reqPath, $isAdminArea){
        switch ($key){
            case 'home':
                return (!$isAdminArea && (preg_match('#/(index\\.php)?$#', $reqPath))) ? 'active' : '';
            case 'offers':
                return (strpos($reqPath, '/ofertas.php') !== false) ? 'active' : '';
            case 'panel':
                return $isAdminArea ? 'active' : '';
            case 'profile':
                return (strpos($reqPath, '/profile.php') !== false) ? 'active' : '';
            case 'login':
                return (strpos($reqPath, '/login.php') !== false) ? 'active' : '';
            case 'register':
                return (strpos($reqPath, '/register.php') !== false) ? 'active' : '';
            default:
                return '';
        }
    }
}
?>
<style>
/* Minimal functional styles for collapsible nav (no design) */
.header { position: relative; z-index: 2000; }
.header .container { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
.header .header-content { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 64px; }
.header .cart-icon { display: flex; align-items: center; gap: 6px; font-size: 1.1rem; cursor: pointer; }
.header .cart-icon a { display: inline-flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 8px; text-decoration: none; color: inherit; transition: background .2s ease, color .2s ease; }
.header .cart-icon a:hover, .header .cart-icon a:focus { background: rgba(0,0,0,0.06); outline: none; }
.header .cart-icon .cart-count { background: #ff4757; color: #fff; border-radius: 999px; padding: 2px 8px; font-size: .8rem; }
.header .logo { display: flex; align-items: center; gap: .5rem; }
/* Constrain logo size for better balance */
.header .logo img { height: 40px; width: auto; display: block; }
.header .logo h1 { font-size: 1.1rem; margin: 0; }
.header a { text-decoration: none; color: inherit; }
.header .menu-toggle { display: none !important; cursor: pointer; line-height: 1; padding: 8px; border-radius: 8px; }
.header .menu-toggle:hover { background: rgba(0,0,0,0.06); }
.header .nav .nav-menu { display: block !important; margin: 0; padding: 0; list-style: none; }
.header .nav .nav-menu > li { list-style: none; }
.header .nav .nav-menu a { display: inline-block; padding: 8px 10px; border-radius: 8px; text-decoration: none; transition: background .2s ease, color .2s ease; }
.header .nav .nav-menu a:hover, .header .nav .nav-menu a:focus { background: rgba(0,0,0,0.06); outline: none; }
.header .nav .nav-menu a:focus-visible { outline: 2px solid rgba(0,0,0,0.25); outline-offset: 2px; }
.header .nav .nav-menu a.active { background: rgba(0,0,0,0.08); font-weight: 600; }
.header .nav .has-submenu { position: relative; }
.header .nav .has-submenu .submenu { display: none; position: absolute; top: calc(100% + 6px); right: 0; z-index: 2100; margin: 0; padding: 6px; background: linear-gradient(180deg, rgba(28,37,65,.98), rgba(28,37,65,.92)); color: #e5e7eb; min-width: 220px; border: 1px solid rgba(255,255,255,.08); border-radius: 10px; box-shadow: 0 12px 32px rgba(0,0,0,.35); }
.header .nav .has-submenu .submenu a { display: block; padding: 10px 12px; border-radius: 8px; color: #e5e7eb; text-decoration: none; }
.header .nav .has-submenu .submenu a:hover, .header .nav .has-submenu .submenu a:focus { background: rgba(255,255,255,.06); color: #ffffff; outline: none; }
.header .nav .has-submenu.open .submenu { display: block; }
@media (min-width: 769px) {
  /* Desktop: inline links */
  .header .logo img { height: 56px; }
  .header .logo h1 { font-size: 1.25rem; }
  .header .nav .nav-menu { display: flex !important; align-items: center; gap: .25rem; }
  .header .nav .nav-menu > li { display: inline-block; }
}
@media (max-width: 768px) {
  .header .menu-toggle { display: block !important; }
  .header .nav { position: relative; }
  .header .nav .nav-menu { display: none !important; }
  .header .nav.open .nav-menu { display: block !important; position: absolute; top: 100%; left: 0; right: 0; z-index: 2050; background: linear-gradient(180deg, rgba(28,37,65,.98), rgba(28,37,65,.92)); border-top: 1px solid rgba(255,255,255,.08); box-shadow: 0 12px 32px rgba(0,0,0,.35); padding: 8px; }
  .header .nav .nav-menu > li { display: block; }
  .header .nav .nav-menu a { display: block; padding: 12px 14px; color:#e5e7eb; }
  /* Submenu becomes inline within the panel on mobile */
  .header .nav .has-submenu .submenu { position: static; background: transparent; border: 0; box-shadow: none; padding: 0; min-width: 0; color: #e5e7eb; }
  .header .nav .has-submenu .submenu a { padding: 10px 18px; color: #e5e7eb; }
  .header .nav .has-submenu .submenu a:hover, .header .nav .has-submenu .submenu a:focus { background: rgba(255,255,255,.06); color: #ffffff; }
}
</style>
<header class="header">
  <div class="container">
    <div class="header-content">
      <div class="logo">
        <img src="<?php echo htmlspecialchars($prefix); ?>img/logo-without-bg.png" alt="Rotteri Nza Kus Logo">
        <h1>Rotteri Nza Kus</h1>
      </div>
      <nav class="nav" id="siteNav">
        <ul class="nav-menu" id="mainNavMenu">
          <?php $aHome = rk_nav_active('home', $reqPath, $isAdminArea); ?>
          <li><a href="<?php echo htmlspecialchars($prefix); ?>index.php" class="<?php echo $aHome; ?>" <?php if($aHome) echo 'data-active="1"'; ?>><i class="fas fa-home"></i> Inicio</a></li>
          <li><a href="<?php echo htmlspecialchars($prefix); ?>index.php#products"><i class="fas fa-box"></i> Productos</a></li>
          <?php $aOffers = rk_nav_active('offers', $reqPath, $isAdminArea); ?>
          <li><a href="<?php echo htmlspecialchars($prefix); ?>ofertas.php" class="<?php echo $aOffers; ?>" <?php if($aOffers) echo 'data-active="1"'; ?>><i class="fas fa-tags"></i> Ofertas</a></li>
          <li><a href="<?php echo htmlspecialchars($prefix); ?>index.php#contact"><i class="fas fa-envelope"></i> Contacto</a></li>
          <li class="has-submenu">
            <a href="#" class="submenu-toggle"><i class="fas fa-user"></i> Cuenta <span class="submenu-icon">▾</span></a>
            <div class="submenu" role="menu">
              <?php if (rk_is_authenticated()): ?>
                <?php if (rk_is_admin()): ?>
                  <?php $aPanel = rk_nav_active('panel', $reqPath, $isAdminArea); ?>
                  <a role="menuitem" href="<?php echo htmlspecialchars($prefix); ?>admin/index.php" class="<?php echo $aPanel; ?>" <?php if($aPanel) echo 'data-active="1"'; ?>><i class="fas fa-tools"></i> Panel Admin</a>
                <?php endif; ?>
                <?php $aProfile = rk_nav_active('profile', $reqPath, $isAdminArea); ?>
                <a role="menuitem" href="<?php echo htmlspecialchars($prefix); ?>profile.php" class="<?php echo $aProfile; ?>" <?php if($aProfile) echo 'data-active="1"'; ?>><i class="fas fa-user"></i> Mi Perfil</a>
                <a role="menuitem" href="<?php echo htmlspecialchars($prefix); ?>logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
              <?php else: ?>
                <?php $aLogin = rk_nav_active('login', $reqPath, $isAdminArea); ?>
                <?php $aReg = rk_nav_active('register', $reqPath, $isAdminArea); ?>
                <a role="menuitem" href="<?php echo htmlspecialchars($prefix); ?>login.php" class="<?php echo $aLogin; ?>" <?php if($aLogin) echo 'data-active="1"'; ?>><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</a>
                <a role="menuitem" href="<?php echo htmlspecialchars($prefix); ?>register.php" class="<?php echo $aReg; ?>" <?php if($aReg) echo 'data-active="1"'; ?>><i class="fas fa-user-plus"></i> Registrarse</a>
              <?php endif; ?>
            </div>
          </li>
        </ul>
      </nav>
      <div class="cart-icon">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-count">0</span>
      </div>
      <?php if (rk_is_authenticated()) { @include __DIR__ . '/notifications_ui.php'; } ?>
      <div class="menu-toggle" id="siteMenuToggle" aria-label="Abrir menú" role="button" tabindex="0" aria-controls="mainNavMenu" aria-expanded="false">
        <i class="fas fa-bars"></i>
      </div>
    </div>
  </div>
</header>
<script>
(function(){
  // Prefer unique IDs to avoid collisions across includes
  const toggle=document.getElementById('siteMenuToggle') || document.querySelector('.header .menu-toggle');
  const nav=document.getElementById('siteNav') || document.querySelector('.header .nav');
  const navMenu=document.getElementById('mainNavMenu');
  const setExpanded = (val)=>{
    if(toggle) toggle.setAttribute('aria-expanded', val ? 'true' : 'false');
  };
  if(toggle && nav){
    const close = () => { nav.classList.remove('open'); setExpanded(false); };
    const flip = () => { const willOpen = !nav.classList.contains('open'); nav.classList.toggle('open'); setExpanded(willOpen); };
    toggle.addEventListener('click', flip);
    toggle.addEventListener('keydown', (e)=>{ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); flip(); }});
    document.querySelectorAll('.nav .nav-menu a').forEach(a=> a.addEventListener('click', close));
    // Close on outside click (mobile)
    document.addEventListener('click', (e)=>{
      if (window.innerWidth<=768){
        const withinNav = e.target.closest && (e.target.closest('#siteNav') || e.target.closest('#siteMenuToggle') || e.target.closest('.header .nav') || e.target.closest('.header .menu-toggle'));
        if (!withinNav) close();
      }
    });
    // Guard: if resize to desktop, ensure expanded false and no stale state
    window.addEventListener('resize', ()=>{ if(window.innerWidth>768){ setExpanded(false); nav.classList.remove('open'); } });
  }

  // Submenu toggle (mobile/desktop) with ARIA and desktop hover
  const submenuParents = Array.from(document.querySelectorAll('.nav .has-submenu'));
  submenuParents.forEach(li => {
    const link = li.querySelector('.submenu-toggle');
    const submenu = li.querySelector('.submenu');
    if (!link || !submenu) return;
    link.setAttribute('aria-haspopup', 'true');
    link.setAttribute('aria-expanded', 'false');
    const setSubExpanded = (val)=> link.setAttribute('aria-expanded', val ? 'true' : 'false');
    const toggleSub = (e)=>{ e?.preventDefault?.(); e?.stopPropagation?.(); const willOpen = !li.classList.contains('open'); submenuParents.forEach(other => { if (other!==li) { other.classList.remove('open'); const ol = other.querySelector('.submenu-toggle'); ol&&ol.setAttribute('aria-expanded','false'); } }); li.classList.toggle('open'); setSubExpanded(willOpen); };
    link.addEventListener('click', toggleSub);
    link.addEventListener('keydown', (e)=>{
      if (e.key==='Enter' || e.key===' '){ e.preventDefault(); toggleSub(e); }
      if (e.key==='Escape'){ li.classList.remove('open'); setSubExpanded(false); }
    });
    // Desktop hover only when width>768
    li.addEventListener('mouseenter', ()=>{ if(window.innerWidth>768){ li.classList.add('open'); setSubExpanded(true); } });
    li.addEventListener('mouseleave', ()=>{ if(window.innerWidth>768){ li.classList.remove('open'); setSubExpanded(false); } });
  });
  document.addEventListener('click', (e)=>{
    if (!(e.target.closest && e.target.closest('.nav .has-submenu'))) {
      submenuParents.forEach(li=> { li.classList.remove('open'); const link = li.querySelector('.submenu-toggle'); link&&link.setAttribute('aria-expanded','false'); });
    }
  });

  // Active link for in-page anchors on Home (#products, #contact)
  function syncActiveLink(){
    const links = Array.from(document.querySelectorAll('.nav .nav-menu a'));
    if (!links.length) return;
    const hash = location.hash;
    const path = location.pathname;
    const isHome = /\/(index\.php)?$/.test(path) && !/\/admin\//.test(path);
    // Reset to server-selected active if not hash case
    const server = document.querySelector('.nav .nav-menu a[data-active="1"]');
    links.forEach(a => a.classList.remove('active'));
    if (isHome && (hash === '#products' || hash === '#contact')){
      const el = document.querySelector(`.nav .nav-menu a[href$='${hash}']`);
      (el || server || links[0]).classList.add('active');
    } else {
      (server || links[0]).classList.add('active');
    }
  }
  window.addEventListener('hashchange', syncActiveLink);
  window.addEventListener('DOMContentLoaded', syncActiveLink);
})();
</script>
