<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already authenticated
redirectIfAuthenticated();

$error = '';

// Fetch active homepage banners for a small carousel on the login page
$banners = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM homepage_banners WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $stmt->execute();
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $banners = []; }

$__rk_basePath = rk_base_path();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $user = null;
        try {
            $stmt = $pdo->prepare("SELECT id, email, password, first_name, last_name, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Fallback: attempt minimal columns if extended fields absent
            try {
                $stmt = $pdo->prepare("SELECT id, email, password, 'Usuario' AS first_name, '' AS last_name, COALESCE(role,'user') AS role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $inner) {
                $error = 'Error de base de datos (tabla users ausente).';
            }
        }
        
        // Debug logging
        try {
            $dbgDir = __DIR__ . '/var';
            if (!is_dir($dbgDir)) { @mkdir($dbgDir, 0775, true); }
            $logLine = date('c') . " EMAIL=" . $email;
            if ($user) {
                $logLine .= " USER_ID=" . $user['id'] . " ROLE=" . ($user['role'] ?? 'n/a');
                $logLine .= " HASH_PREFIX=" . substr($user['password'],0,15);
            } else {
                $logLine .= " USER_NOT_FOUND";
            }
            // We do not log the plain password for security reasons.
            @file_put_contents($dbgDir . '/login_debug.log', $logLine . "\n", FILE_APPEND);
        } catch (Exception $e) { /* ignore logging errors */ }

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: admin/index.php');
            } else {
                header('Location: index.php');
            }
            exit();
        } else {
            // Additional logging for failed password_verify
            try {
                if ($user) {
                    @file_put_contents(__DIR__ . '/var/login_debug.log', date('c') . ' VERIFY_FAILED user_id=' . $user['id'] . "\n", FILE_APPEND);
                }
            } catch (Exception $e) {}
            $error = 'Credenciales incorrectas';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Iniciar Sesión - Rotteri Nza Kus</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/modern.css">
            <style>
            :root{ --brand:#0b132b; --brand-2:#1c2541; --accent:#ffb703; --paper:#0b132b; --ink:#e5e7eb; --muted:#94a3b8; }
            body{ margin:0; font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial,sans-serif; color:var(--ink); background: transparent; }
                /* Unified layout: stack banner and card */
                .auth-wrap{ min-height:100vh; display:flex; align-items:center; justify-content:center; padding: 24px; }
                .stack{ width:100%; max-width: 920px; display:flex; flex-direction:column; gap: 18px; align-items: center; }
            .card{ width:100%; max-width: 460px; background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85)); backdrop-filter: blur(6px); border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.35); border: 1px solid rgba(255,255,255,.08); color: var(--ink); }
            .card .hd{ padding: 22px 24px 10px; text-align:center; }
            .stack > .promo{ margin-bottom: 4px; }
            .stack > .card{ margin-top: 4px; }
            .card .hd h1{ margin:0; font-size: 1.6rem; font-weight: 700; color:#fff; }
            .card .hd p{ margin:6px 0 0; color: var(--muted); }
            .card .bd{ padding: 8px 24px 24px; }
            .form-group{ margin-bottom: 14px; }
            label{ display:block; font-weight: 600; font-size:.95rem; margin-bottom:6px; color:#e5e7eb; }
            input[type=email], input[type=password]{ width:100%; padding: 12px 14px; border: 1px solid rgba(255,255,255,.18); border-radius: 10px; font-size: 1rem; outline: none; transition: border-color .2s, box-shadow .2s; background: rgba(255,255,255,.06); color:#e5e7eb; }
            input::placeholder{ color:#94a3b8; }
            input[type=email]:focus, input[type=password]:focus{ border-color:#5bc0be; box-shadow: 0 0 0 3px rgba(91,192,190,.25); }
            .btn{ display:inline-flex; align-items:center; gap:10px; padding: 12px 16px; border:0; border-radius: 12px; font-weight:700; cursor:pointer; transition: transform .02s ease, background .2s ease; }
            .btn-primary{ background: var(--brand); color:#fff; width:100%; justify-content:center; }
            .btn-primary:hover{ background: var(--brand-2); }
            .btn-primary:active{ transform: translateY(1px); }
            .meta{ display:flex; align-items:center; justify-content:space-between; margin-top: 6px; }
            .link{ color: #cbd5e1; text-decoration: none; font-weight:600; }
            .link:hover{ text-decoration: underline; }
            .alert{ padding:12px 14px; border-radius:10px; margin: 0 24px 10px; font-weight:600; }
            .alert-danger{ background: rgba(239,71,111,.12); color:#fca5a5; border:1px solid rgba(239,71,111,.35); }
            .foot{ padding: 0 24px 24px; text-align:center; color: var(--muted); }
            .foot a{ color: #cbd5e1; font-weight:600; text-decoration:none; }
            .foot a:hover{ text-decoration:underline; }
            /* Small promo carousel */
                    /* Multi-image carousel */
                    .promo{ --spv: 1; position:relative; width:100%; max-width: 980px; margin: 0 auto; }
                    @media(min-width:768px){ .promo{ --spv: 2; } }
                    @media(min-width:1200px){ .promo{ --spv: 3; } }
                    .promo .viewport{ overflow:hidden; border-radius:14px; position:relative; margin: 0 auto; }
                    .promo .track{ display:flex; will-change: transform; transition: transform 700ms cubic-bezier(.22,.61,.36,1); }
                    .promo .track.centered{ justify-content: center; }
                    .promo .slide{ flex: 0 0 calc(100% / var(--spv)); position: relative; height: clamp(200px, 28vw, 360px); }
                    @media(max-width:920px){ .promo .slide{ height: clamp(180px, 45vw, 260px); } }
                    .promo .img-wrap{ position:absolute; inset:0; overflow:hidden; background:#111827; display:flex; align-items:center; justify-content:center; }
                    .promo .img-wrap img{ max-width:100%; max-height:100%; }
                    .promo .slide img{ width:100%; height:100%; object-fit:contain; display:block; transform: none; animation: none; background: transparent; }
                    .promo .overlay{ position:absolute; inset:0; display:flex; flex-direction:column; justify-content:flex-end; padding: 18px; background: linear-gradient(180deg, rgba(0,0,0,0.02) 0%, rgba(0,0,0,0.4) 100%); color:#fff; opacity:0; transform: translateY(8px); transition: all 400ms ease; }
                    .promo .slide.active .overlay{ opacity:1; transform: translateY(0); }
                    .promo .title{ font-size:1.05rem; font-weight:800; margin:0 0 6px; }
                    .promo .subtitle{ font-size:.92rem; margin:0 0 10px; opacity:.95; }
                    .promo .cta{ align-self:flex-start; background: var(--accent); color: #0b132b; padding:8px 12px; border-radius:10px; font-weight:800; text-decoration:none; }
                    .promo .nav{ position:absolute; inset:0; pointer-events:none; }
                    .promo .arrow{ position:absolute; top:50%; transform: translateY(-50%); background: rgba(0,0,0,.45); color:#fff; border:0; width:38px; height:38px; border-radius:50%; display:grid; place-items:center; cursor:pointer; pointer-events:auto; transition: background .2s; }
                    .promo .arrow:hover{ background: rgba(0,0,0,.6); }
                    .promo .arrow.prev{ left:10px; }
                    .promo .arrow.next{ right:10px; }
                    .dots{ display:flex; gap:6px; justify-content:center; margin-top:10px; }
                    .dot{ width:10px; height:10px; border-radius:50%; background:#cbd5e1; opacity:.5; transition:opacity .2s, transform .2s; outline:none; }
                    .dot.active{ opacity:1; background:#ffffff; transform: scale(1.1); }
                    .dot:focus-visible{ box-shadow: 0 0 0 3px rgba(17,24,39,.35); }
                    @media (prefers-reduced-motion: reduce){
                        .promo .track{ transition-duration: 0ms; }
                        .promo .slide img{ animation: none; }
                    }
        </style>
    </head>
    <body>
                <div class="auth-wrap">
                    <div class="stack">
                        <?php if (!empty($banners)): ?>
                                <section class="promo" id="loginPromos" aria-label="Promociones" role="region" aria-roledescription="carrusel" aria-live="polite">
                                    <div class="viewport">
                                        <div class="track" id="promoTrack">
                                <?php foreach ($banners as $idx => $b): ?>
                                            <div class="slide" id="promoSlide-<?php echo (int)$idx; ?>">
                                                <div class="img-wrap">
                                        <?php $__raw = (string)($b['image_url'] ?? ''); $__img = rk_banner_public_url($__raw, $__rk_basePath); ?>
                                        <?php if ($__img && rk_banner_exists($__raw)): ?>
                                            <img src="<?php echo htmlspecialchars($__img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($b['title'] ?: 'Promoción', ENT_QUOTES, 'UTF-8'); ?>" loading="<?php echo $idx===0 ? 'eager' : 'lazy'; ?>" decoding="async" fetchpriority="<?php echo $idx===0 ? 'high' : 'low'; ?>" onerror="this.style.display='none'; this.parentNode.style.background='linear-gradient(135deg,#1c2541,#3a506b)';">
                                        <?php else: ?>
                                            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1c2541,#3a506b);color:#94a3b8;font-size:.75rem;font-weight:600;">Sin imagen</div>
                                        <?php endif; ?>
                                                </div>
                                                <div class="overlay">
                                            <?php if (!empty($b['title'])): ?><div class="title"><?php echo htmlspecialchars($b['title']); ?></div><?php endif; ?>
                                            <?php if (!empty($b['subtitle'])): ?><div class="subtitle"><?php echo htmlspecialchars($b['subtitle']); ?></div><?php endif; ?>
                                            <?php if (!empty($b['cta_url'])): ?>
                                                <a class="cta" href="<?php echo htmlspecialchars($b['cta_url']); ?>" target="_blank" rel="noopener"> 
                                                    <?php echo htmlspecialchars($b['cta_text'] ?: 'Ver más'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                        </div>
                                        <div class="nav">
                                            <button type="button" class="arrow prev" id="promoPrev" aria-label="Anterior">❮</button>
                                            <button type="button" class="arrow next" id="promoNext" aria-label="Siguiente">❯</button>
                                        </div>
                            </div>
                            <div class="dots" id="promoDots" role="tablist" aria-label="Paginación del carrusel"></div>
                        </section>
                        <?php else: ?>
                                        <section class="promo" aria-label="Promociones">
                                            <div class="viewport">
                                                <div class="track">
                                                    <div class="slide" style="background:linear-gradient(135deg,#1c2541,#3a506b);">
                                                        <div class="overlay" style="opacity:1;">
                                                            <div class="title">Bienvenido a Rotteri Nza Kus</div>
                                                            <div class="subtitle">Las mejores ofertas te esperan</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                        <?php endif; ?>

                        <div class="card" role="form" aria-labelledby="loginTitle">
                    <div class="hd">
                        <h1 id="loginTitle">Iniciar Sesión</h1>
                        <p>Accede a tu cuenta para continuar</p>
                    </div>
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    <div class="bd">
                        <form method="POST" class="login-form" autocomplete="on">
                            <div class="form-group">
                                <label for="email">Correo Electrónico</label>
                                <input type="email" id="email" name="email" autocomplete="email" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Contraseña</label>
                                <div style="position:relative;">
                                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                                    <button type="button" id="togglePwd" aria-label="Mostrar u ocultar contraseña" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:transparent; border:0; color:#6b7280; cursor:pointer;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</button>
                            <div class="meta">
                                <span></span>
                                <a class="link" href="register.php">Crear cuenta</a>
                            </div>
                        </form>
                    </div>
                    <div class="foot">
                        <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
                    </div>
                        </div>
        </div>

                <script src="js/script.js"></script>
                <script>
                (function(){
                        // Toggle password visibility
                        const t = document.getElementById('togglePwd'); const p = document.getElementById('password');
                        if(t){ t.addEventListener('click', ()=>{ if(!p) return; const is = p.getAttribute('type')==='password'; p.setAttribute('type', is?'text':'password'); t.innerHTML = is? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>'; }); }

                        // Enhanced multi-image carousel
                        const promo = document.getElementById('loginPromos');
                        const track = document.getElementById('promoTrack');
                        const dotsEl = document.getElementById('promoDots');
                        const btnPrev = document.getElementById('promoPrev');
                        const btnNext = document.getElementById('promoNext');
                        const slides = track ? Array.from(track.children) : [];
                        if(!promo || !track || slides.length===0){ return; }

                        // Slides per view from CSS var
                        const getSpv = () => {
                            const styles = getComputedStyle(promo);
                            const val = parseFloat(styles.getPropertyValue('--spv'));
                            return Math.max(1, isNaN(val) ? 1 : val);
                        };

                        let index = 0;
                        let spv = getSpv();
                        let autoTimer = null;
                        const maxIndex = () => Math.max(0, slides.length - spv);

                        // Build dots based on pages
                        let dots = [];
                        function buildDots(){
                            const pages = Math.ceil(slides.length / spv);
                            dots = [];
                            if(dotsEl){ dotsEl.innerHTML = ''; }
                            for(let i=0;i<pages;i++){
                                const d = document.createElement('div');
                                d.className = 'dot' + (i===0?' active':'');
                                d.setAttribute('role','button');
                                d.tabIndex = 0;
                                d.setAttribute('aria-label', 'Ir a la diapositiva ' + (i+1));
                                d.addEventListener('click', () => goTo(i*spv));
                                d.addEventListener('keydown', (e)=>{ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); goTo(i*spv);} });
                                dotsEl.appendChild(d);
                                dots.push(d);
                            }
                        }

                                                function updateActive(){
                                                    // Center if not enough slides to scroll
                                                    const notEnough = slides.length <= spv;
                                                    track.classList.toggle('centered', notEnough);
                                                    if(notEnough){
                                                        index = 0;
                                                    }
                            // Mark visible slides as active for overlay animation
                                        slides.forEach((s,i)=>{ 
                                            const active = i>=index && i<index+spv; 
                                            s.classList.toggle('active', active);
                                            s.setAttribute('aria-hidden', active ? 'false' : 'true');
                                        });
                            const pIdx = Math.round(index / spv);
                                                    dots.forEach((d,i)=> d.classList.toggle('active', i===pIdx));
                            // Translate track by slide width
                                                    const slideWidth = slides[0].getBoundingClientRect().width;
                                                    track.style.transform = notEnough ? 'translateX(0)' : `translateX(${-index * slideWidth}px)`;
                                                    // Toggle arrows availability
                                                    if(btnPrev) btnPrev.style.display = notEnough ? 'none' : '';
                                                    if(btnNext) btnNext.style.display = notEnough ? 'none' : '';
                        }

                        function goTo(i){ index = Math.max(0, Math.min(i, maxIndex())); updateActive(); restartAuto(); }
                        function next(){ goTo(index + spv); }
                        function prev(){ goTo(index - spv); }

                                    // Buttons
                        if(btnNext) btnNext.addEventListener('click', next);
                        if(btnPrev) btnPrev.addEventListener('click', prev);

                        // Autoplay
                                                function restartAuto(){
                                        if(autoTimer) clearInterval(autoTimer);
                                                    const notEnough = slides.length <= spv;
                                                    if(notEnough) return; // do not autoplay when centered static
                                                    autoTimer = setInterval(()=>{ if(index >= maxIndex()) index = -spv; next(); }, 4000);
                                    }

                        // Responsive recalculation
                                    function onResize(){
                            const old = spv; spv = getSpv();
                            if(spv !== old){ index = Math.floor(index / spv) * spv; buildDots(); }
                            updateActive();
                        }
                        window.addEventListener('resize', onResize);

                        // Drag / swipe support
                        const vp = promo.querySelector('.viewport');
                        let startX=0, currentX=0, dragging=false, startIndex=0;
                        function onStart(x){ dragging=true; startX=x; currentX=x; startIndex=index; track.style.transition='none'; }
                        function onMove(x){ if(!dragging) return; currentX=x; const dx=currentX-startX; const slideWidth=slides[0].getBoundingClientRect().width; const base=-startIndex*slideWidth; track.style.transform=`translateX(${base+dx}px)`; }
                        function onEnd(){ if(!dragging) return; dragging=false; track.style.transition=''; const dx=currentX-startX; const slideWidth=slides[0].getBoundingClientRect().width; if(Math.abs(dx)>slideWidth*0.2){ if(dx<0) next(); else prev(); } else { updateActive(); } restartAuto(); }
                                    if(vp){
                            vp.addEventListener('mousedown', (e)=>{ e.preventDefault(); onStart(e.clientX); });
                            window.addEventListener('mousemove', (e)=> onMove(e.clientX));
                            window.addEventListener('mouseup', onEnd);
                            vp.addEventListener('touchstart', (e)=> onStart(e.touches[0].clientX), {passive:true});
                            window.addEventListener('touchmove', (e)=> onMove(e.touches[0].clientX), {passive:true});
                            window.addEventListener('touchend', onEnd);
                                        // Pause/resume on hover for desktop
                                        vp.addEventListener('mouseenter', ()=>{ if(autoTimer) clearInterval(autoTimer); });
                                        vp.addEventListener('mouseleave', restartAuto);
                                                    // Keyboard nav: left/right arrows
                                                    vp.setAttribute('tabindex','0');
                                                    vp.addEventListener('keydown', (e)=>{
                                                        if(e.key==='ArrowRight'){ e.preventDefault(); next(); }
                                                        if(e.key==='ArrowLeft'){ e.preventDefault(); prev(); }
                                                    });
                        }

                        // Init
                                                buildDots();
                        updateActive();
                        restartAuto();

                                                // Respect reduced motion
                                                const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
                                                function applyReduced(){ if(mql.matches && autoTimer){ clearInterval(autoTimer); autoTimer=null; } }
                                                applyReduced();
                                                mql.addEventListener?.('change', applyReduced);

                                                // Pause when tab hidden
                                                document.addEventListener('visibilitychange', ()=>{
                                                    if(document.hidden){ if(autoTimer) clearInterval(autoTimer); }
                                                    else { restartAuto(); }
                                                });

                                                // Image error fallback
                                                slides.forEach(slide=>{
                                                    const img = slide.querySelector('img');
                                                    if(!img) return;
                                                    img.addEventListener('error', ()=>{
                                                        slide.style.background = 'linear-gradient(135deg,#1c2541,#3a506b)';
                                                        img.style.display='none';
                                                    }, {once:true});
                                                });
                })();
                </script>
    </body>
</html>