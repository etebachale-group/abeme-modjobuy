(function(){
  function initCarousel(root){
    const track = root.querySelector('.carousel-track');
    const slides = Array.from(root.querySelectorAll('.carousel-slide'));
    const prevBtn = root.querySelector('.carousel-btn.prev');
    const nextBtn = root.querySelector('.carousel-btn.next');
    const dotsWrap = root.querySelector('.carousel-dots');
    const container = root; // use root for height adjustments

    if (!track || slides.length === 0) return;

    let index = 0;
    let timer = null;

    // Dots
    dotsWrap.innerHTML = '';
    slides.forEach((_, i)=>{
      const b = document.createElement('button');
      b.className = 'carousel-dot' + (i===0?' active':'');
      b.setAttribute('aria-label','Ir al slide ' + (i+1));
      b.addEventListener('click', ()=> goTo(i));
      dotsWrap.appendChild(b);
    });

    function update(){
      track.style.transform = `translateX(-${index * 100}%)`;
      dotsWrap.querySelectorAll('.carousel-dot').forEach((d, i)=>{
        d.classList.toggle('active', i === index);
      });
      adjustHeight();
    }

    function goTo(i){ index = (i + slides.length) % slides.length; update(); restart(); }
    function next(){ goTo(index + 1); }
    function prev(){ goTo(index - 1); }

    prevBtn && prevBtn.addEventListener('click', prev);
    nextBtn && nextBtn.addEventListener('click', next);

    function start(){ if (timer) return; timer = setInterval(next, 5000); }
    function stop(){ if (!timer) return; clearInterval(timer); timer = null; }
    function restart(){ stop(); start(); }

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);

    function naturalSlideHeight(slide){
      const img = slide.querySelector('img');
      if (!img) return slide.offsetHeight;
      if (img.complete && img.naturalWidth) {
        // width-based scaling to container width
        const containerWidth = container.clientWidth || img.clientWidth || img.naturalWidth;
        const ratio = img.naturalHeight / img.naturalWidth;
        return Math.round(containerWidth * ratio);
      }
      return img.clientHeight || slide.offsetHeight || 0;
    }

    function adjustHeight(){
      const current = slides[index];
      const h = naturalSlideHeight(current);
      const SCALE_FACTOR = 0.70; // reduce height by 30% across devices
      const isMobile = window.innerWidth <= 768;
      const MIN_MOBILE = 220; // px
      const MIN_DESKTOP = 320; // px
      const minH = isMobile ? MIN_MOBILE : MIN_DESKTOP;
      const scaled = Math.max(0, Math.round(h * SCALE_FACTOR));
      const finalH = Math.max(minH, scaled);
      if (finalH > 0) { container.style.height = finalH + 'px'; }
    }

    // Recompute height once images load and on resize
    slides.forEach(slide => {
      const img = slide.querySelector('img');
      if (img) {
        img.addEventListener('load', adjustHeight, { passive: true });
      }
    });
    window.addEventListener('resize', adjustHeight);

    // Initial kick
    update();
    start();
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.carousel').forEach(initCarousel);
  });
})();
