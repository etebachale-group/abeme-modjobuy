<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Rotteri Nza Kus'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/modern.css">
    <style>
        /* Notifications bar/bell */
        .notif-wrap{position:relative;margin-left:12px}
        .notif-bell{position:relative;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--text);cursor:pointer}
        .notif-bell .badge{position:absolute;top:-6px;right:-6px;background:var(--error);color:#fff;border-radius:999px;padding:.15rem .35rem;font-size:.72rem;border:2px solid #0b132b}
        .notif-dd{position:absolute;right:0;top:48px;width:360px;max-width:90vw;background:linear-gradient(180deg, rgba(28,37,65,.98), rgba(28,37,65,.9));border:1px solid rgba(255,255,255,.12);border-radius:14px;box-shadow:0 18px 36px rgba(0,0,0,.35);display:none;z-index:100}
        .notif-dd.open{display:block}
        .notif-dd .dd-head{display:flex;align-items:center;justify-content:space-between;padding:.6rem .8rem;border-bottom:1px solid rgba(255,255,255,.08)}
        .notif-dd .filters{display:flex;gap:.4rem;padding:.5rem .8rem;border-bottom:1px solid rgba(255,255,255,.06)}
        .notif-dd .filters button{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--text);padding:.35rem .6rem;border-radius:10px;cursor:pointer}
        .notif-dd .list{max-height:360px;overflow:auto}
        .notif-item{display:flex;gap:.6rem;padding:.6rem .8rem;border-bottom:1px solid rgba(255,255,255,.06)}
        .notif-item.unread{background:rgba(91,192,190,.08)}
        .notif-item .actions{margin-left:auto;display:flex;gap:.35rem}
        .notif-item .actions button{background:transparent;border:1px solid rgba(255,255,255,.2);color:var(--text);padding:.2rem .4rem;border-radius:8px;cursor:pointer}
        .notif-dd .dd-foot{display:flex;justify-content:space-between;padding:.6rem .8rem}
    </style>
    <?php if (isset($cssFiles) && is_array($cssFiles)): ?>
        <?php foreach ($cssFiles as $cssFile): ?>
            <link rel="stylesheet" href="<?php echo $cssFile; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="img/logo-without-bg.png" alt="Rotteri Nza Kus Logo">
                    <h1>Rotteri Nza Kus</h1>
                </div>
                <nav class="nav">
                    <ul class="nav-menu">
                        <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Inicio</a></li>
                        <li><a href="index.php#products">Productos</a></li>
                        <li><a href="index.php#contact">Contacto</a></li>
                        <?php if (isAuthenticated()): ?>
                            <?php if (isAdmin()): ?>
                                <li><a href="admin/index.php">Panel Admin</a></li>
                            <?php endif; ?>
                            <li><a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">Mi Perfil</a></li>
                            <li><a href="logout.php">Cerrar Sesión</a></li>
                        <?php else: ?>
                            <li><a href="login.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">Iniciar Sesión</a></li>
                            <li><a href="register.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>">Registrarse</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="cart-icon">
                    <a href="cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                </div>
                <?php if (isAuthenticated()): ?>
                <div class="notif-wrap" id="notifWrap">
                    <button class="notif-bell" id="notifBell" aria-label="Notificaciones">
                        <i class="fas fa-bell"></i>
                        <span class="badge" id="notifBadge" style="display:none">0</span>
                    </button>
                    <div class="notif-dd" id="notifDropdown">
                        <div class="dd-head">
                            <strong>Notificaciones</strong>
                            <button id="notifMarkAll" style="background:transparent;border:none;color:var(--accent);cursor:pointer">Marcar todo leído</button>
                        </div>
                        <div class="filters">
                            <button data-filter="all" class="nf-filter active">Todas</button>
                            <button data-filter="unread" class="nf-filter">No leídas</button>
                            <button data-filter="read" class="nf-filter">Leídas</button>
                        </div>
                        <div class="list" id="notifList"><div style="padding:.8rem;color:var(--text-muted)">Cargando…</div></div>
                        <div class="dd-foot">
                            <button id="notifDeleteRead" class="btn btn-secondary" style="padding:.4rem .6rem">Eliminar leídas</button>
                            <button id="notifDeleteAll" class="btn btn-danger" style="padding:.4rem .6rem">Eliminar todas</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>
    <?php if (isAuthenticated()): ?>
    <script>
    (function(){
        const bell = document.getElementById('notifBell');
        const badge = document.getElementById('notifBadge');
        const dd = document.getElementById('notifDropdown');
        const list = document.getElementById('notifList');
        const filtBtns = [];
        document.querySelectorAll('.nf-filter').forEach(b=>filtBtns.push(b));
        let currentFilter = 'all';

        function setBadge(n){ if(!badge) return; if(n>0){ badge.textContent=String(n); badge.style.display='inline-block'; } else { badge.style.display='none'; } }
        function render(rows){
            if(!Array.isArray(rows) || rows.length===0){ list.innerHTML = '<div style="padding:.8rem;color:var(--text-muted)">Sin notificaciones</div>'; return; }
            list.innerHTML = rows.map(n=>`
                <div class="notif-item ${n.is_read==0?'unread':''}" data-id="${n.id}">
                    <div style="font-size:1.1rem;color:var(--accent)"><i class="fas fa-info-circle"></i></div>
                    <div>
                        <div><strong>${SafeUtils.escapeHtml(n.title||'')}</strong></div>
                        <div style="color:var(--text-muted);font-size:.9rem">${SafeUtils.escapeHtml(n.message||'')}</div>
                        ${n.link?`<a href="${n.link}" style="color:var(--accent-2);font-size:.9rem">Abrir</a>`:''}
                    </div>
                    <div class="actions">
                        <button class="mark-read">${n.is_read==0?'Leer':'No leer'}</button>
                        <button class="delete">Eliminar</button>
                    </div>
                </div>
            `).join('');
        }
        async function fetchList(){
            try{
                const r = await fetch(`api/notifications/list.php?filter=${encodeURIComponent(currentFilter)}&limit=50`);
                const j = await r.json();
                if(!j.success) throw new Error(j.message||'Error');
                render(j.notifications||[]);
                setBadge(j.unread||0);
            }catch(e){ list.innerHTML = '<div style="padding:.8rem;color:#ef476f">Error cargando notificaciones</div>'; }
        }
        async function mark(ids, read=true){
            await fetch('api/notifications/mark_read.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids, read})});
            await fetchList();
        }
        async function del(ids){
            await fetch('api/notifications/delete.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids})});
            await fetchList();
        }

        bell?.addEventListener('click', ()=> dd.classList.toggle('open'));
        document.addEventListener('click', (e)=>{
            if(!dd.contains(e.target) && e.target!==bell && !bell.contains(e.target)) dd.classList.remove('open');
        });
        document.getElementById('notifMarkAll')?.addEventListener('click', ()=> mark('all', true));
        document.getElementById('notifDeleteRead')?.addEventListener('click', ()=> del('all-read'));
        document.getElementById('notifDeleteAll')?.addEventListener('click', ()=> del('all'));
        filtBtns.forEach(b=> b.addEventListener('click', ()=>{ filtBtns.forEach(x=>x.classList.remove('active')); b.classList.add('active'); currentFilter=b.dataset.filter; fetchList(); }));
        list.addEventListener('click', (e)=>{
            const item = e.target.closest('.notif-item'); if(!item) return; const id = item.getAttribute('data-id');
            if(e.target.classList.contains('mark-read')){ const unread = item.classList.contains('unread'); mark([id], unread); }
            if(e.target.classList.contains('delete')){ del([id]); }
        });

        // Initial load + periodic refresh
        fetchList();
        setInterval(fetchList, 30000);
    })();
    </script>
    <?php endif; ?>
    <?php // Ensure cart badge sync script is present across pages using this header ?>
    <?php include __DIR__ . '/cart_ui.php'; ?>