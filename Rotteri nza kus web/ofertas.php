<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Ensure minimal tables
try { $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS products (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, description TEXT NULL, price DECIMAL(10,2) NOT NULL DEFAULT 0.00, weight DECIMAL(10,2) NULL, image_url VARCHAR(500) NULL, tags VARCHAR(500) NULL, category_id INT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(category_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}

// Fetch active banners for possible offers header
$banners = [];
try { $st=$pdo->query("SELECT * FROM homepage_banners WHERE is_active=1 ORDER BY sort_order ASC, created_at DESC"); $banners=$st->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $banners=[]; }

// Fetch offer products (tags include oferta/promo/descuento)
$products = [];
try {
    $st = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 AND (p.tags LIKE '%oferta%' OR p.tags LIKE '%promo%' OR p.tags LIKE '%descuento%') ORDER BY p.created_at DESC LIMIT 120");
    $st->execute();
    $products = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){ $products=[]; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ofertas - Rotteri Nza Kus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/modern.css">
    <style>
        .page-header { background:#0b132b; color:#fff; padding:24px 0; }
        .page-header .container { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .offers-hero { background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85)); border:1px solid rgba(255,255,255,.08); padding:12px; border-radius:10px; margin-top:16px; color:#e5e7eb; }
        .products-grid { display:grid; grid-template-columns: repeat(auto-fill,minmax(240px,1fr)); gap:16px; }
        .product-card { border:1px solid rgba(255,255,255,.08); border-radius:12px; overflow:hidden; background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85)); box-shadow:0 10px 30px rgba(0,0,0,0.25); color:#e5e7eb; }
        .product-image { height:180px; overflow:hidden; }
        .product-image img { width:100%; height:100%; object-fit:cover; }
        .product-info { padding:12px; }
        .product-actions { display:flex; gap:8px; margin-top:8px; }
        .btn { padding:8px 12px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
        .btn-cart { background:#5bc0be; color:#0b132b; }
        .btn-buy { background:#ffd166; color:#3a2b09; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/layout_header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h2>Ofertas destacadas</h2>
        </div>
    </section>

    <div class="container">
        <?php if (!empty($banners)): ?>
        <div class="offers-hero">
            <strong>Promociones:</strong>
            <ul style="margin:8px 0 0 16px;">
                <?php foreach ($banners as $b): ?>
                <li>
                    <?php if (!empty($b['cta_url'])): ?><a href="<?php echo htmlspecialchars($b['cta_url']); ?>" target="_blank"><?php echo htmlspecialchars($b['title'] ?: 'Ver promoción'); ?></a><?php else: ?><?php echo htmlspecialchars($b['title'] ?: 'Promoción'); ?><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <h3 style="margin:16px 0;">Productos en oferta</h3>
        <div class="products-grid" id="offersGrid">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $p): ?>
                <div class="product-card" data-product-id="<?php echo (int)$p['id']; ?>">
                    <div class="product-image"><img src="<?php echo htmlspecialchars($p['image_url'] ?: ''); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></div>
                    <div class="product-info">
                        <h3 class="product-name"><?php echo htmlspecialchars($p['name']); ?></h3>
                        <p class="product-category"><?php echo htmlspecialchars($p['category_name'] ?: ''); ?></p>
                        <p class="product-description"><?php echo htmlspecialchars(mb_substr($p['description'] ?: '',0,100)); ?>...</p>
                        <div class="product-details"><span class="product-price">CFA <?php echo number_format((float)$p['price'],2); ?></span> <span class="product-weight"><?php echo htmlspecialchars((string)$p['weight']); ?> kg</span></div>
                        <div class="product-actions">
                            <button class="btn btn-cart" data-product-id="<?php echo (int)$p['id']; ?>"><i class="fas fa-shopping-cart"></i> Añadir</button>
                            <button class="btn btn-buy" data-product-id="<?php echo (int)$p['id']; ?>"><i class="fas fa-bolt"></i> Comprar</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products"><p>No hay productos en oferta en este momento.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer"><div class="container"><p>&copy; <?php echo date('Y'); ?> Rotteri Nza Kus. Todos los derechos reservados.</p></div></footer>

    <script src="js/script.js"></script>
    <?php include __DIR__ . '/includes/cart_ui.php'; ?>
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
