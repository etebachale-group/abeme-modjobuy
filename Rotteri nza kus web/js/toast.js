// Minimal toast helper
(function(){
  const ensureContainer = () => {
    let c = document.getElementById('toast-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toast-container';
      document.body.appendChild(c);
    }
    return c;
  };
  const make = (msg,type='info') => {
    const c = ensureContainer();
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    c.appendChild(t);
    requestAnimationFrame(()=>{ t.classList.add('show'); });
    setTimeout(()=>{ t.classList.remove('show'); setTimeout(()=> t.remove(), 300); }, 3000);
  };
  window.toast = {
    info:(m)=>make(m,'info'),
    success:(m)=>make(m,'success'),
    error:(m)=>make(m,'error')
  };
})();
