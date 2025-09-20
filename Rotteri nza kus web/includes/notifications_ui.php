<?php
// Shared notifications UI: bell + dropdown + SSE real-time updates
if (!function_exists('isAuthenticated')) require_once __DIR__ . '/auth.php';
if (!isAuthenticated()) { return; }

if (!defined('NOTIF_UI_LOADED')):
    define('NOTIF_UI_LOADED', true);
?>
<style>
/* Notifications bar/bell */
.notif-wrap{position:relative;margin-left:12px}
.notif-bell{position:relative;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--text,#e5e7eb);cursor:pointer}
.notif-bell .badge{position:absolute;top:-6px;right:-6px;background:#ef476f;color:#fff;border-radius:999px;padding:.15rem .35rem;font-size:.72rem;border:2px solid #0b132b}
.notif-dd{position:absolute;right:0;top:48px;width:360px;max-width:90vw;background:linear-gradient(180deg, rgba(28,37,65,.98), rgba(28,37,65,.9));border:1px solid rgba(255,255,255,.12);border-radius:14px;box-shadow:0 18px 36px rgba(0,0,0,.35);display:none;z-index:100}
.notif-dd.open{display:block}
.notif-dd .dd-head{display:flex;align-items:center;justify-content:space-between;padding:.6rem .8rem;border-bottom:1px solid rgba(255,255,255,.08);color:var(--text,#e5e7eb)}
.notif-dd .filters{display:flex;gap:.4rem;padding:.5rem .8rem;border-bottom:1px solid rgba(255,255,255,.06)}
.notif-dd .filters button{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--text,#e5e7eb);padding:.35rem .6rem;border-radius:10px;cursor:pointer}
.notif-dd .filters .active{outline:2px solid rgba(91,192,190,.35)}
.notif-dd .list{max-height:360px;overflow:auto}
.notif-item{display:flex;gap:.6rem;padding:.6rem .8rem;border-bottom:1px solid rgba(255,255,255,.06);color:var(--text,#e5e7eb)}
.notif-item.unread{background:rgba(91,192,190,.08)}
.notif-item .actions{margin-left:auto;display:flex;gap:.35rem}
.notif-item .actions button{background:transparent;border:1px solid rgba(255,255,255,.2);color:var(--text,#e5e7eb);padding:.2rem .4rem;border-radius:8px;cursor:pointer}
.notif-dd .dd-foot{display:flex;justify-content:space-between;padding:.6rem .8rem}
</style>
<script>
(function(){
    if (window.__notifUiInit) return; window.__notifUiInit = true;
    function escapeHtml(text){
        try{ if (window.SafeUtils?.escapeHtml) return window.SafeUtils.escapeHtml(text); }catch{}
        const div=document.createElement('div'); div.textContent=String(text??''); return div.innerHTML;
    }
    function setupNotif(){
        const wrap = document.getElementById('notifWrap');
        const bell = document.getElementById('notifBell');
        const badge = document.getElementById('notifBadge');
        const dd = document.getElementById('notifDropdown');
        const list = document.getElementById('notifList');
        if (!bell || !dd || !list) return;
        const filtBtns = Array.from(document.querySelectorAll('.nf-filter'));
        let currentFilter = 'all';

        function setBadge(n){ if(!badge) return; if(n>0){ badge.textContent=String(n); badge.style.display='inline-block'; } else { badge.style.display='none'; } }
        function render(rows){
            if(!Array.isArray(rows) || rows.length===0){ list.innerHTML = '<div style="padding:.8rem;color:#94a3b8">Sin notificaciones</div>'; return; }
            list.innerHTML = rows.map(n=>`
                <div class="notif-item ${Number(n.is_read)===0?'unread':''}" data-id="${n.id}">
                    <div style="font-size:1.1rem;color:#5bc0be"><i class="fas fa-info-circle"></i></div>
                    <div>
                        <div><strong>${escapeHtml(n.title||'')}</strong></div>
                        <div style="color:#94a3b8;font-size:.9rem">${escapeHtml(n.message||'')}</div>
                        ${n.link?`<a href="${n.link}" style="color:#ffd166;font-size:.9rem">Abrir</a>`:''}
                    </div>
                    <div class="actions">
                        <button class="mark-read">${Number(n.is_read)===0?'Leer':'No leer'}</button>
                        <button class="delete">Eliminar</button>
                    </div>
                </div>
            `).join('');
        }
        const base = /\/admin(\/|$)/.test(location.pathname) ? '../' : '';
        async function fetchList(){
            try{
                const r = await fetch(`${base}api/notifications/list.php?filter=${encodeURIComponent(currentFilter)}&limit=50`);
                const j = await r.json();
                if(!j.success) throw new Error(j.message||'Error');
                render(j.notifications||[]);
                setBadge(j.unread||0);
            }catch(e){ list.innerHTML = '<div style="padding:.8rem;color:#ef476f">Error cargando notificaciones</div>'; }
        }
        async function mark(ids, read=true){
            await fetch(`${base}api/notifications/mark_read.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids, read})});
            await fetchList();
        }
        async function del(ids){
            await fetch(`${base}api/notifications/delete.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids})});
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

        // Real-time via SSE
        let es; let fallbackTimer;
        function startSSE(){
            try {
                es = new EventSource(`${base}api/notifications/stream.php`);
                es.addEventListener('badge', (ev)=>{ try{ const d=JSON.parse(ev.data); setBadge(d.unread||0); }catch{} });
                es.addEventListener('new', (ev)=>{ try{ /* refresh list to show latest event */ fetchList(); }catch{} });
                es.addEventListener('error', ()=>{ try{ es.close(); }catch{}; startFallback(); });
            } catch { startFallback(); }
        }
        function startFallback(){
            if (fallbackTimer) return; fallbackTimer = setInterval(async ()=>{
                try{ const r=await fetch(`${base}api/notifications/list.php?filter=unread&limit=1`); const j=await r.json(); setBadge(j.unread||0); }catch{}
            }, 10000);
        }
        fetchList();
        startSSE();
    }
    window.addEventListener('DOMContentLoaded', setupNotif);
})();
</script>
<?php endif; ?>

<div class="notif-wrap" id="notifWrap">
    <button class="notif-bell" id="notifBell" aria-label="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="badge" id="notifBadge" style="display:none">0</span>
    </button>
    <div class="notif-dd" id="notifDropdown">
        <div class="dd-head">
            <strong>Notificaciones</strong>
            <button id="notifMarkAll" style="background:transparent;border:none;color:#5bc0be;cursor:pointer">Marcar todo leído</button>
        </div>
        <div class="filters">
            <button data-filter="all" class="nf-filter active">Todas</button>
            <button data-filter="unread" class="nf-filter">No leídas</button>
            <button data-filter="read" class="nf-filter">Leídas</button>
        </div>
        <div class="list" id="notifList"><div style="padding:.8rem;color:#94a3b8">Cargando…</div></div>
        <div class="dd-foot">
            <button id="notifDeleteRead" class="btn btn-secondary" style="padding:.4rem .6rem">Eliminar leídas</button>
            <button id="notifDeleteAll" class="btn btn-danger" style="padding:.4rem .6rem">Eliminar todas</button>
        </div>
    </div>
    </div>
<?php return; ?>
