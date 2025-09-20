<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Ensure minimal store tables exist to avoid fatal errors on fresh setups
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        weight DECIMAL(10,2) DEFAULT 0.00,
        image_url VARCHAR(255) DEFAULT NULL,
        tags VARCHAR(500) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (category_id),
        CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    try { $pdo->exec("ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL"); } catch (Exception $ignore) {}
    // Promo banners table for homepage carousel
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS homepage_banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NULL,
            subtitle VARCHAR(300) NULL,
            image_url VARCHAR(600) NULL,
            cta_text VARCHAR(120) NULL,
            cta_url VARCHAR(600) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $ignore) {}
} catch (Exception $e) {
    // Ignore DDL errors here; selects below will still surface issues if any
}

// Get products from database
try {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.created_at DESC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
}

// Get categories for filter
try {
    $stmt = $pdo->prepare("SELECT * FROM categories");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Fetch active homepage banners
$banners = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM homepage_banners WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $stmt->execute();
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $banners = []; }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotteri Nza Kus - Compras Online</title>
    <meta name="theme-color" content="#0b132b">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/modern.css">
    <style>
        /* Lightweight promo carousel */
        .promo-carousel { position: relative; overflow: hidden; background:#0b132b; }
        .promo-track { display: flex; transition: transform .5s ease; }
        .promo-slide { min-width: 100%; position: relative; height: 200px; }
        .promo-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .promo-overlay { position:absolute; inset:0; display:flex; flex-direction:column; align-items:flex-start; justify-content:center; padding: 16px; background: linear-gradient(90deg, rgba(0,0,0,0.45), rgba(0,0,0,0.0)); color:#fff; }
        .promo-title { font-size: 1.25rem; font-weight: 700; margin: 0 0 6px; }
        .promo-subtitle { font-size: .95rem; margin: 0 0 10px; opacity: .95; }
        .promo-cta { display:inline-block; background:#ffb703; color:#0b132b; padding:8px 14px; border-radius:8px; font-weight:700; text-decoration:none; }
        .promo-arrow { position:absolute; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.4); color:#fff; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .promo-arrow.left { left:10px; }
        .promo-arrow.right { right:10px; }
        .promo-dots { position:absolute; bottom:8px; left:0; right:0; display:flex; gap:6px; justify-content:center; }
        .promo-dot { width:8px; height:8px; border-radius:50%; background:#ffffff66; }
        .promo-dot.active { background:#fff; }
        @media (min-width: 768px){ .promo-slide { height: 300px; } .promo-title{ font-size:1.6rem;} }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="img/logo-without-bg.png" alt="Rotteri Nza Kus Logo">
                    <h1>Rotteri Nza Kus</h1>
                </div>
                <nav class="nav">
                    <ul class="nav-menu">
                        <li><a href="index.php" class="active">Inicio</a></li>
                        <li><a href="#products">Productos</a></li>
                        <li><a href="#contact">Contacto</a></li>
                        <?php if (isAuthenticated()): ?>
                            <?php if (isAdmin()): ?>
                                <li><a href="admin/index.php">Panel Admin</a></li>
                            <?php else: ?>
                                <li><a href="profile.php">Mi Perfil</a></li>
                            <?php endif; ?>
                            <li><a href="logout.php">Cerrar Sesión</a></li>
                        <?php else: ?>
                            <li><a href="login.php">Iniciar Sesión</a></li>
                            <li><a href="register.php">Registrarse</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count">0</span>
                </div>
                <?php if (isAuthenticated()) { include __DIR__ . '/includes/notifications_ui.php'; } ?>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- Promo Carousel -->
    <?php if (!empty($banners)): ?>
    <section class="promo-carousel" aria-label="Promociones">
        <div class="promo-track" id="promoTrack">
            <?php foreach ($banners as $b): ?>
            <div class="promo-slide">
                <?php if (!empty($b['image_url'])): ?>
                    <?php $__img = (string)($b['image_url'] ?? ''); if (strpos($__img,'../')===0) { $__img = substr($__img,3); } ?>
                    <img src="<?php echo htmlspecialchars($__img); ?>" alt="<?php echo htmlspecialchars($b['title'] ?: 'Promoción'); ?>">
                <?php endif; ?>
                <div class="promo-overlay">
                    <?php if (!empty($b['title'])): ?><h3 class="promo-title"><?php echo htmlspecialchars($b['title']); ?></h3><?php endif; ?>
                    <?php if (!empty($b['subtitle'])): ?><p class="promo-subtitle"><?php echo htmlspecialchars($b['subtitle']); ?></p><?php endif; ?>
                    <?php if (!empty($b['cta_url'])): ?>
                        <a class="promo-cta" href="<?php echo htmlspecialchars($b['cta_url']); ?>" target="_blank" rel="noopener">
                            <?php echo htmlspecialchars($b['cta_text'] ?: 'Ver oferta'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="promo-arrow left" id="promoPrev" aria-label="Anterior">&#10094;</button>
        <button class="promo-arrow right" id="promoNext" aria-label="Siguiente">&#10095;</button>
        <div class="promo-dots" id="promoDots"></div>
    </section>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h2>Compra productos desde Ghana y Guinea Ecuatorial</h2>
                <p>Envíos confiables y eficientes con seguimiento en tiempo real</p>
                <img src="img/BANNER1.png" alt="Rotteri Nza Kus Banner" class="hero-banner">
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products" class="products">
        <div class="container">
            <h2 class="section-title">Nuestros Productos</h2>
            
            <!-- Filters -->
            <div class="filters">
                <select id="categoryFilter">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="searchFilter" placeholder="Buscar por nombre, descripción o etiquetas...">
            </div>
            
            <!-- Products Grid -->
            <div class="products-grid" id="productsGrid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" data-category="<?php echo $product['category_id']; ?>" data-product-id="<?php echo $product['id']; ?>">
                            <div class="product-image">
                                <img src="<?php echo $product['image_url']; ?>" alt="<?php echo $product['name']; ?>" loading="lazy">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo $product['name']; ?></h3>
                                <p class="product-category"><?php echo $product['category_name']; ?></p>
                                <p class="product-description"><?php echo substr($product['description'], 0, 100); ?>...</p>
                                <div class="product-details">
                                    <span class="product-price">CFA <?php echo number_format($product['price'], 2); ?></span>
                                    <span class="product-weight"><?php echo $product['weight']; ?> kg</span>
                                </div>
                                <?php if (!empty($product['tags'])): ?>
                                <div class="tags" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
                                    <?php foreach (array_filter(array_map('trim', explode(',', (string)$product['tags']))) as $tg): ?>
                                        <span class="tag-chip" style="background:#f1f2f6;color:#34495e;padding:4px 8px;border-radius:12px;font-size:.8rem;">#<?php echo htmlspecialchars($tg); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div class="product-actions">
                                    <button class="btn btn-cart" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-shopping-cart"></i> Añadir al carrito
                                    </button>
                                    <button class="btn btn-buy" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-bolt"></i> Comprar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products">
                        <p>No hay productos disponibles en este momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

        <!-- Floating Actions -->
        <button class="fab cart-fab" id="fabCart" title="Carrito">
            <i class="fas fa-shopping-cart"></i>
            <span class="badge" id="fabCartCount">0</span>
        </button>
        <a class="fab whatsapp-fab" href="https://wa.me/233123456789" target="_blank" rel="noopener" title="WhatsApp">
            <i class="fab fa-whatsapp"></i>
        </a>
        <button class="fab scroll-top" id="scrollTopBtn" title="Subir">
            <i class="fas fa-arrow-up"></i>
        </button>

    <!-- Modal for purchase confirmation -->
    <div id="purchaseModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Confirmar Compra</h2>
            <div id="modalProductDetails"></div>
            <button id="confirmPurchase" class="btn btn-primary">Proceder al Checkout</button>
        </div>
    </div>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2 class="section-title">Contacto</h2>
            <div class="contact-info">
                <p>Para más información, contáctanos:</p>
                <p><i class="fas fa-envelope"></i> info@rotterinzakus.com</p>
                <p><i class="fas fa-phone"></i> +233 123 456 789</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Rotteri Nza Kus. Todos los derechos reservados.</p>
        </div>
    </footer>

        <script src="js/script.js"></script>
        <?php include __DIR__ . '/includes/cart_ui.php'; ?>
        <script>
            // Promo carousel logic (no external deps)
            (function(){
                const track = document.getElementById('promoTrack');
                if(!track) return; // no banners
                const slides = Array.from(track.children);
                const prev = document.getElementById('promoPrev');
                const next = document.getElementById('promoNext');
                const dotsWrap = document.getElementById('promoDots');
                let i = 0; let timer;
                function renderDots(){
                    dotsWrap.innerHTML = slides.map((_,idx)=>`<span class="promo-dot ${idx===i?'active':''}"></span>`).join('');
                }
                function go(idx){ i = (idx+slides.length)%slides.length; track.style.transform = `translateX(${-i*100}%)`; renderDots(); }
                function nextSlide(){ go(i+1); }
                function prevSlide(){ go(i-1); }
                function start(){ stop(); timer = setInterval(nextSlide, 5000); }
                function stop(){ if(timer) clearInterval(timer); }
                next?.addEventListener('click', ()=>{ nextSlide(); start(); });
                prev?.addEventListener('click', ()=>{ prevSlide(); start(); });
                dotsWrap.addEventListener('click', (e)=>{
                    const idx = Array.from(dotsWrap.children).indexOf(e.target);
                    if(idx>=0){ go(idx); start(); }
                });
                renderDots(); start();
                // Pause on hover (desktop)
                track.addEventListener('mouseenter', stop); track.addEventListener('mouseleave', start);
            })();
        </script>
        <script>
            // Basic UX interactions for floating elements and menu
            (function(){
                const scrollBtn = document.getElementById('scrollTopBtn');
                const fabCart = document.getElementById('fabCart');
                const cartCountEls = [document.querySelector('.cart-count'), document.getElementById('fabCartCount')].filter(Boolean);
                function onScroll(){
                    if(window.scrollY > 300){ scrollBtn?.classList.add('show'); } else { scrollBtn?.classList.remove('show'); }
                }
                window.addEventListener('scroll', onScroll, { passive: true });
                onScroll();
                scrollBtn?.addEventListener('click', ()=> window.scrollTo({ top: 0, behavior: 'smooth' }));
                // Example sync for cart count if your script.js updates a global counter
                window.updateCartCount = function(n){ cartCountEls.forEach(el=> el.textContent = n); };
                // Open cart page
                fabCart?.addEventListener('click', ()=> { window.location.href = 'cart.php'; });
                // Mobile menu toggle support if not present
                const toggle = document.querySelector('.menu-toggle');
                const nav = document.querySelector('.nav');
                toggle?.addEventListener('click', ()=> nav?.classList.toggle('open'));
            })();

                        // Live search and category filter using API
                        (function(){
                                const qEl = document.getElementById('searchFilter');
                                const catEl = document.getElementById('categoryFilter');
                                const grid = document.getElementById('productsGrid');
                                let controller;
                                async function search(){
                                        const q = qEl.value.trim();
                                        const c = catEl.value.trim();
                                        try {
                                                if (controller) controller.abort();
                                                controller = new AbortController();
                                                const params = new URLSearchParams();
                                                if (q) params.set('q', q);
                                                if (c) params.set('category', c);
                                                params.set('limit', '60');
                                                const res = await fetch('api/search_products.php?' + params.toString(), { signal: controller.signal });
                                                const data = await res.json();
                                                if (!data.success) throw new Error(data.message || 'Error de búsqueda');
                                                render(data.results || []);
                                        } catch (e) {
                                                if (e.name === 'AbortError') return;
                                                console.error(e);
                                        }
                                }
                                function render(items){
                                        if (!Array.isArray(items) || items.length === 0){
                                                grid.innerHTML = '<div class="no-products"><p>No hay resultados para tu búsqueda.</p></div>';
                                                return;
                                        }
                                        grid.innerHTML = items.map(p => {
                                            const tags = (p.tags||'').split(',').map(s=>s.trim()).filter(Boolean);
                                            const tagsHtml = tags.length ? `<div class="tags" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">${tags.map(t=>`<span class=\"tag-chip\" style=\"background:#f1f2f6;color:#34495e;padding:4px 8px;border-radius:12px;font-size:.8rem;\">#${t.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</span>`).join('')}</div>` : '';
                                            return `
                                                <div class="product-card" data-category="${p.category_id || ''}" data-product-id="${p.id}">
                                                    <div class="product-image">
                                                        <img src="${p.image_url || ''}" alt="${(p.name||'').replace(/"/g,'&quot;')}" loading="lazy">
                                                    </div>
                                                    <div class="product-info">
                                                        <h3 class="product-name">${p.name || ''}</h3>
                                                        <p class="product-category">${p.category_name || ''}</p>
                                                        <p class="product-description">${(p.description||'').substring(0,100)}...</p>
                                                        <div class="product-details">
                                                            <span class="product-price">CFA ${Number(p.price||0).toFixed(2)}</span>
                                                            <span class="product-weight">${p.weight || 0} kg</span>
                                                        </div>
                                                ${tagsHtml}
                                                        <div class="product-actions">
                                                            <button class="btn btn-cart" data-product-id="${p.id}"><i class="fas fa-shopping-cart"></i> Añadir al carrito</button>
                                                            <button class="btn btn-buy" data-product-id="${p.id}"><i class="fas fa-bolt"></i> Comprar</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            `;
                                        }).join('');
                                }
                                qEl?.addEventListener('input', debounce(search, 250));
                                catEl?.addEventListener('change', search);
                                function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(null,a), ms); }; }
                        })();
        </script>
        <script>
        (function(){
            async function isAuthed(){ try{ const r=await fetch('api/is_authenticated.php'); const j=await r.json(); return !!j.authenticated; }catch{ return false; } }
            function lsAdd(productId){
                const key='cart';
                const cart=JSON.parse(localStorage.getItem(key)||'[]');
                const existing=cart.find(it=> String(it.id)===String(productId));
                if(existing){ existing.quantity += 1; } else { cart.push({ id: String(productId), quantity: 1 }); }
                localStorage.setItem(key, JSON.stringify(cart));
                const total = cart.reduce((s,i)=> s + (parseInt(i.quantity)||0), 0);
                if(window.updateCartCount) updateCartCount(total);
                return total;
            }
            async function serverAdd(productId){
                const r=await fetch('api/add_to_cart.php',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ product_id:Number(productId), quantity:1 })});
                const j=await r.json(); if(!j.success) throw new Error(j.message||'No se pudo añadir al carrito');
                try{ const c=await fetch('api/get_cart_count.php'); const jc=await c.json(); if(jc.success && window.updateCartCount) updateCartCount(jc.count||0);}catch{}
            }
            function bind(){
                document.querySelectorAll('.btn-cart').forEach(btn=>{
                    btn.addEventListener('click', async ()=>{
                        const id=btn.getAttribute('data-product-id');
                        try{ if(await isAuthed()){ await serverAdd(id); toast?.success?toast.success('Añadido al carrito'):console.log('Añadido'); }
                              else { lsAdd(id); toast?.info?toast.info('Inicia sesión para guardar tu carrito'):console.log('Local cart'); } }
                        catch(err){ toast?.error?toast.error(err.message):alert(err.message); }
                    });
                });
                document.querySelectorAll('.btn-buy').forEach(btn=>{
                    btn.addEventListener('click', async ()=>{
                        const id=btn.getAttribute('data-product-id');
                        try{ if(await isAuthed()){ await serverAdd(id); window.location.href='checkout.php'; }
                              else { lsAdd(id); window.location.href='checkout.php'; } }
                        catch(err){ toast?.error?toast.error(err.message):alert(err.message); }
                    });
                });
            }
            bind();
        })();
        </script>
</body>
</html>
