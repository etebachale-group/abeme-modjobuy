<?php
if (!function_exists('isActiveAdminNav')){
    function isActiveAdminNav($file){
        $current = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        return $current === $file ? 'active' : '';
    }
}
?>
<style>
/* Minimal-functional admin nav + polished links/buttons */
/* Base: reset list + hide toggle on desktop */
.admin-nav { position: relative; z-index: 1500; }
.admin-nav .admin-menu { list-style: none; margin: 0; padding: 0; }
.admin-nav .admin-menu > li { list-style: none; }
.admin-nav .admin-toggle { display: none !important; }

/* Nav links: no underline, comfy padding, focus-visible */
.admin-nav .admin-menu a {
  display: inline-block;
  padding: 8px 10px;
  border-radius: 8px;
  text-decoration: none;
  color: inherit;
  transition: background .2s ease, color .2s ease, box-shadow .2s ease;
}
.admin-nav .admin-menu a:hover,
.admin-nav .admin-menu a:focus { background: rgba(0,0,0,0.06); outline: none; }
.admin-nav .admin-menu a:focus-visible { outline: 2px solid rgba(0,0,0,0.25); outline-offset: 2px; }
.admin-nav .admin-menu a.active,
.admin-nav .admin-menu a[aria-current="page"] { background: rgba(0,0,0,0.08); font-weight: 600; }

/* Safer base button styling for admin pages (no colors to avoid conflicts) */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  line-height: 1.1;
  transition: background .2s ease, color .2s ease, border-color .2s ease, box-shadow .2s ease, transform .02s ease;
}
.btn:focus { outline: none; }
.btn:focus-visible { outline: 2px solid rgba(0,0,0,0.25); outline-offset: 2px; }
.btn:active { transform: translateY(0.5px); }
.btn[disabled], .btn:disabled { opacity: .6; cursor: not-allowed; }

/* Optional: subtle hover if no color defined by page */
.btn:not([class*="btn-"]):hover { background: rgba(0,0,0,0.06); }

@media (min-width: 769px) {
  .admin-nav .admin-menu { display: flex !important; align-items: center; gap: .5rem; }
  .admin-nav .admin-menu > li { display: inline-block; }
}
@media (max-width: 768px) {
  .admin-nav { position: relative; }
  .admin-nav .admin-toggle {
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid rgba(0,0,0,0.08);
  background: var(--surface-800,#121b36);
    color: inherit;
    cursor: pointer;
  }
  .admin-nav .admin-toggle:hover { background: rgba(0,0,0,0.04); }
  .admin-nav .admin-toggle:focus-visible { outline: 2px solid rgba(0,0,0,0.25); outline-offset: 2px; }
  .admin-nav .admin-menu { display: none !important; }
  .admin-nav.open .admin-menu {
      display: block !important;
      position: absolute; top: 100%; right: 0; z-index: 1600;
      background: linear-gradient(180deg, rgba(28,37,65,.98), rgba(28,37,65,.92)); color: #e5e7eb;
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 10px;
      box-shadow: 0 12px 32px rgba(0,0,0,.35);
    padding: 6px; min-width: 220px;
  }
  .admin-nav.open .admin-menu > li { display: block; }
    .admin-nav.open .admin-menu a { display: block; padding: 10px 12px; border-radius: 8px; color: #e5e7eb; }
}
</style>
<div class="admin-nav" role="navigation" aria-label="Menú de administración">
  <button type="button" class="admin-toggle" aria-label="Abrir menú">☰ Menú</button>
  <ul class="admin-menu">
    <li><a class="<?php echo isActiveAdminNav('index.php'); ?>" href="index.php"><i class="fas fa-box"></i> Productos</a></li>
    <li><a class="<?php echo isActiveAdminNav('categories.php'); ?>" href="categories.php"><i class="fas fa-list"></i> Categorías</a></li>
    <li><a class="<?php echo isActiveAdminNav('orders.php'); ?>" href="orders.php"><i class="fas fa-receipt"></i> Pedidos</a></li>
    <li><a class="<?php echo isActiveAdminNav('banners.php'); ?>" href="banners.php"><i class="fas fa-image"></i> Banners</a></li>
    <li><a class="<?php echo isActiveAdminNav('settings.php'); ?>" href="settings.php"><i class="fas fa-cog"></i> Configuración</a></li>
  </ul>
</div>
<script>
(function(){
  const wrap = document.querySelector('.admin-nav');
  const btn = wrap?.querySelector('.admin-toggle');
  if(btn && wrap){
    const t = ()=> {
      const willOpen = !wrap.classList.contains('open');
      wrap.classList.toggle('open');
      btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    };
    const menu = wrap.querySelector('.admin-menu');
    if (menu) menu.id = 'adminMenu';
    btn.setAttribute('aria-controls', 'adminMenu');
    btn.setAttribute('aria-expanded', 'false');
    btn.addEventListener('click', t);
    btn.addEventListener('keydown', (e)=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); t(); }});
    wrap.querySelectorAll('.admin-menu a').forEach(a=> a.addEventListener('click', ()=> {
      wrap.classList.remove('open');
      btn.setAttribute('aria-expanded','false');
    }));
    document.addEventListener('click', (e)=>{
      if(window.innerWidth<=768){
        const within = e.target.closest && (e.target.closest('.admin-nav') || e.target.closest('.admin-toggle'));
        if(!within) { wrap.classList.remove('open'); btn.setAttribute('aria-expanded','false'); }
      }
    });
  }
})();
</script>
