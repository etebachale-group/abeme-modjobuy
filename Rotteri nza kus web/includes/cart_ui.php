<?php
// Shared cart UI logic to keep cart badge in sync in real time
?>
<script>
(function(){
  if (window.__cartUiInit) return; window.__cartUiInit = true;
  function setCartCount(n){
    const val = Number.isFinite(n) ? n : parseInt(n)||0;
    try { if (typeof window.updateCartCount === 'function') { window.updateCartCount(val); } } catch{}
    document.querySelectorAll('.cart-count, #fabCartCount').forEach(el=>{ if (el) el.textContent = String(val); });
  }
  async function fetchCount(){
    try{ const r=await fetch('api/get_cart_count.php'); const j=await r.json(); if(j.success){ setCartCount(j.count||0); } }catch{}
  }
  // Real-time via SSE for authenticated users
  async function isAuthed(){ try{ const r=await fetch('api/is_authenticated.php'); const j=await r.json(); return !!j.authenticated; }catch{ return false; } }
  (async function init(){
    const authed = await isAuthed();
    if (authed) {
      // Start SSE
      try {
        const es = new EventSource('api/cart/stream.php');
        es.addEventListener('count', (ev)=>{ try{ const d=JSON.parse(ev.data); setCartCount(d.count||0); }catch{} });
        es.addEventListener('error', ()=>{ /* fallback below will handle */ });
      } catch {}
      // Initial fetch + fallback polling
      fetchCount();
      setInterval(fetchCount, 15000);
    } else {
      // Guest: reflect localStorage cart
      function lsCount(){
        try{ const cart=JSON.parse(localStorage.getItem('cart')||'[]'); const total=cart.reduce((s,i)=> s + (parseInt(i.quantity)||0), 0); setCartCount(total); }catch{ setCartCount(0); }
      }
      lsCount();
      window.addEventListener('storage', (e)=>{ if(e.key==='cart') lsCount(); });
    }
  })();
})();
</script>