<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Defensive schema creation for orders & order_items
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_number VARCHAR(50) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NULL,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* ignore */ }

// Ensure optional products.source_url exists (defensive)
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS source_url VARCHAR(500) NULL");
} catch (Exception $e) { /* noop if exists or no permission */ }

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

// Fetch orders safely (if schema incomplete, fallback empty)
$orders = [];
try {
    $stmt = $pdo->prepare("SELECT o.*, oi.product_name, oi.quantity, oi.unit_price, u.first_name, u.last_name, p.source_url
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.product_id IN (SELECT id FROM products WHERE admin_id = ?)
        ORDER BY o.created_at DESC");
    $stmt->execute([$admin_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Leave $orders empty
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Rotteri Nza Kus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/toast.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .admin-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .admin-nav {
            background: #34495e;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .admin-nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        
        .admin-nav li {
            margin-right: 20px;
        }
        
        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .admin-nav a:hover,
        .admin-nav a.active {
            background: #3498db;
        }
        
        .admin-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .table-responsive { width: 100%; overflow-x: auto; }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .orders-table th {
            background: #f2f2f2;
            font-weight: bold;
        }
        
        .orders-table tr:hover {
            background: #f9f9f9;
        }

        /* Highlight for new items */
    .orders-table tr.new-item { animation: flashIn 2.4s ease-out; background: #f0fff4; }
        .badge-new { background:#10b981; color:#fff; border-radius:999px; padding:2px 8px; font-size:.75rem; margin-left:6px; }
    @keyframes flashIn { 0%{background:#bbf7d0} 60%{background:#e7fee9} 100%{background:#fff} }
        
        .order-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-shipped {
            background: #bee5eb;
            color: #0c5460;
        }
        
        .status-delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            padding: 10px 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            line-height: 1.2;
            min-height: 40px;
        }
        
        .btn-accept {
            background: #27ae60;
            color: white;
        }
        
        .btn-accept:hover {
            background: #219653;
        }
        
        .btn-reject {
            background: #e74c3c;
            color: white;
        }
        
        .btn-reject:hover {
            background: #c0392b;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
        }
        
        .btn-view:hover {
            background: #2980b9;
        }
        
        .no-orders {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Modal styling */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: none; align-items: center; justify-content: center; padding: 16px; z-index: 1000; }
        .modal .modal-content { background: #fff; width: 100%; max-width: 900px; border-radius: 12px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 90vh; overflow: auto; }
        .modal .close { float: right; font-size: 24px; cursor: pointer; }
        .modal h3 { margin-top: 0; }

        /* Sticky admin nav */
        .admin-nav { position: sticky; top: 0; z-index: 20; }

        /* Responsive table -> cards */
        @media (max-width: 900px) {
            .admin-header h1 { font-size: 1.6rem; }
            .orders-table { border-collapse: separate; border-spacing: 0; }
            .orders-table thead { display: none; }
            .orders-table, .orders-table tbody, .orders-table tr, .orders-table td { display: block; width: 100%; }
            .orders-table tr { background: #fff; margin-bottom: 12px; border: 1px solid #eee; border-radius: 10px; padding: 8px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
            .orders-table td { border: 0; border-bottom: 1px dashed #eee; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between; }
            .orders-table td:last-child { border-bottom: 0; }
            .orders-table td::before { content: attr(data-label); font-weight: 600; color: #6b7280; margin-right: 10px; text-align: left; }
            .btn { width: 100%; margin: 6px 0; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="../img/logo-without-bg.png" alt="Rotteri Nza Kus Logo">
                    <h1>Rotteri Nza Kus</h1>
                </div>
                <nav class="nav">
                    <ul class="nav-menu">
                        <li><a href="../index.php">Inicio</a></li>
                        <li><a href="../index.php#products">Productos</a></li>
                        <li><a href="../index.php#contact">Contacto</a></li>
                        <?php if (isAuthenticated()): ?>
                            <?php if (isAdmin()): ?>
                                <li><a href="index.php">Panel Admin</a></li>
                                <li><a href="../profile.php">Mi Perfil</a></li>
                            <?php else: ?>
                                <li><a href="../profile.php">Mi Perfil</a></li>
                            <?php endif; ?>
                            <li><a href="../logout.php">Cerrar Sesión</a></li>
                        <?php else: ?>
                            <li><a href="../login.php">Iniciar Sesión</a></li>
                            <li><a href="../register.php">Registrarse</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count">0</span>
                </div>
                <?php if (isAuthenticated()) { include __DIR__ . '/../includes/notifications_ui.php'; } ?>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-container">
        <div class="admin-header">
            <h1>Gestión de Pedidos</h1>
            <p>Bienvenido, <?php echo htmlspecialchars(currentUserName()); ?></p>
        </div>
        
        <div class="admin-nav">
            <ul>
                <li><a href="index.php">Productos</a></li>
                <li><a href="categories.php">Categorías</a></li>
                <li><a href="orders.php" class="active">Pedidos</a></li>
                <li><a href="banners.php">Banners</a></li>
                <li><a href="settings.php">Configuración</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <h2>Pedidos Recibidos</h2>
            <div class="orders-controls" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:8px 0 4px">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="checkbox" id="onlyNewToggle"> Solo nuevos
                </label>
                <button class="btn btn-view" id="markAllSeenBtn" type="button"><i class="fas fa-check-double"></i> Marcar todo como visto</button>
            </div>
            
            <div id="ordersMount">
                <div class="no-orders"><p>Cargando pedidos…</p></div>
            </div>
            
            <!-- Modal Detalles de Pedido -->
            <div id="orderModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="close" id="orderModalClose">&times;</span>
                    <h3>Detalles del Pedido</h3>
                    <div id="orderDetails">
                        <p>Cargando...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const CSRF_TOKEN = '<?php echo htmlspecialchars(csrf_token()); ?>';
    const USER_ID = <?php echo (int)(currentUserId() ?? 0); ?>;
        // Local state for new/highlight controls
        let __ordersCache = [];
        let __onlyNew = false;
    const SEEN_KEY = `admin_seen_item_ids_${USER_ID}`;
    const ANIM_KEY = `admin_animated_item_ids_${USER_ID}`;
    const getSet = (k)=> new Set((localStorage.getItem(k)||'').split(',').filter(Boolean));
    const setSet = (k, set)=> localStorage.setItem(k, Array.from(set).join(','));
        function setOnlyNew(v){ __onlyNew = !!v; renderOrders(__ordersCache); }
        function fmtStatus(s){
            switch (s){
                case 'pending': return 'Pendiente';
                case 'confirmed': return 'Confirmado';
                case 'processing': return 'Procesando';
                case 'shipped': return 'Enviado';
                case 'delivered': return 'Entregado';
                case 'cancelled': return 'Cancelado';
                default: return (s||'').charAt(0).toUpperCase()+(s||'').slice(1);
            }
        }
        function renderOrders(rows){
            const mount = document.getElementById('ordersMount');
            if (!Array.isArray(rows) || rows.length===0){
                mount.innerHTML = '<div class="no-orders"><p>No hay pedidos pendientes.</p></div>';
                return;
            }
            __ordersCache = rows.slice();
            const seen = getSet(SEEN_KEY);
            const animated = getSet(ANIM_KEY);
            const displayRows = __onlyNew ? rows.filter(o=>(!seen.has(String(o.item_id)) && Number(o.is_seen)!==1)) : rows;
            let firstTimeCount = 0;
            const body = displayRows.map(order=>{
                const idStr = String(order.item_id);
                const isSeen = seen.has(idStr) || Number(order.is_seen)===1;
                const isFirstTime = !animated.has(idStr);
                if (isFirstTime && !isSeen) firstTimeCount++;
                return `
                <tr class="${isFirstTime && !isSeen ? 'new-item' : ''}" data-item-id="${order.item_id}">
                    <td data-label="Orden #">${order.order_number} ${(isFirstTime && !isSeen)?'<span class="badge-new">Nuevo</span>':''}</td>
                    <td data-label="Cliente">${order.first_name} ${order.last_name}</td>
                    <td data-label="Producto">${order.product_name}</td>
                    <td data-label="Proveedor">${order.source_url?`<a class="btn btn-view" href="${order.source_url}" target="_blank" rel="noopener noreferrer"><i class='fas fa-link'></i> Ver proveedor</a>`:'&mdash;'}</td>
                    <td data-label="Cantidad">${order.quantity}</td>
                    <td data-label="Precio Unitario">CFA ${Number(order.unit_price).toFixed(2)}</td>
                    <td data-label="Total">CFA ${(Number(order.unit_price)*Number(order.quantity)).toFixed(2)}</td>
                    <td data-label="Fecha">${new Date(order.created_at).toLocaleDateString()}</td>
                    <td data-label="Estado"><span class="order-status status-${order.status}">${fmtStatus(order.status)}</span></td>
                    <td data-label="Acciones">${order.status==='pending'
                        ? `<button class=\"btn btn-accept\" onclick=\"updateOrderStatus(${order.id}, 'confirmed')\"><i class=\"fas fa-check\"></i> Aceptar</button>
                           <button class=\"btn btn-reject\" onclick=\"updateOrderStatus(${order.id}, 'cancelled')\"><i class=\"fas fa-times\"></i> Rechazar</button>`
                        : `<button class=\"btn btn-view\" onclick=\"viewOrder(${order.id})\"><i class=\"fas fa-eye\"></i> Ver</button>`}
                    </td>
                </tr>`;
            }).join('');
            mount.innerHTML = `
                <div class="table-responsive">
                  <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Orden #</th><th>Cliente</th><th>Producto</th><th>Proveedor</th>
                            <th>Cantidad</th><th>Precio Unitario</th><th>Total</th>
                            <th>Fecha</th><th>Estado</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>${body}</tbody>
                  </table>
                </div>`;
            // After rendering, mark animated so highlight plays once per item, and toast
            const toAnimate = new Set(animated);
            Array.from(document.querySelectorAll('tr[data-item-id]')).forEach(tr=>{
                const id = tr.getAttribute('data-item-id');
                if (!animated.has(id)) toAnimate.add(id);
            });
            setSet(ANIM_KEY, toAnimate);
            if (firstTimeCount>0 && window.toast?.success) {
                const msg = firstTimeCount===1 ? 'Nuevo artículo de pedido' : `${firstTimeCount} artículos nuevos de pedido`;
                toast.success(msg);
            }
        }
        async function fetchOrders(){
            try { const r = await fetch('get_orders.php'); const j = await r.json(); if(!j.success) throw new Error(j.message||'Error'); renderOrders(j.orders||[]); }
            catch(e){ document.getElementById('ordersMount').innerHTML = '<div class="no-orders"><p>Error cargando pedidos</p></div>'; }
        }
        function startOrdersSSE(){
            try{ const es = new EventSource('orders_stream.php'); es.addEventListener('ping', ()=> fetchOrders()); es.onerror = ()=>{ try{es.close();}catch{}; setInterval(fetchOrders, 15000); }; }
            catch{ setInterval(fetchOrders, 15000); }
        }
        fetchOrders();
        startOrdersSSE();
        // Controls wiring
        document.getElementById('onlyNewToggle')?.addEventListener('change', (e)=> setOnlyNew(e.target.checked));
        document.getElementById('markAllSeenBtn')?.addEventListener('click', async ()=>{
            const ids = Array.from(document.querySelectorAll('tr[data-item-id]')).map(tr=> tr.getAttribute('data-item-id'));
            if (ids.length===0) return;
            // Update server
            try{
                await fetch('mark_seen.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ item_ids: ids })});
            }catch{}
            // Update local for instant UX
            const seen = getSet(SEEN_KEY); ids.forEach(id=> seen.add(String(id))); setSet(SEEN_KEY, seen);
            renderOrders(__ordersCache);
            if (window.toast?.info) toast.info('Todos los artículos visibles fueron marcados como vistos');
        });
        async function updateOrderStatus(orderId, status) {
            if (!confirm('¿Está seguro de que desea ' + (status === 'confirmed' ? 'aceptar' : 'rechazar') + ' este pedido?')) return;
            try {
                const fd = new FormData();
                fd.append('order_id', String(orderId));
                fd.append('status', String(status));
                fd.append('csrf_token', CSRF_TOKEN);
                const res = await fetch('update_order_status.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Error');
                toast.success('Pedido ' + (status === 'confirmed' ? 'aceptado' : 'rechazado'));
                setTimeout(()=> location.reload(), 600);
            } catch (err) {
                toast.error('No se pudo actualizar el pedido: ' + err.message);
            }
        }
        async function viewOrder(orderId) {
            try {
                const res = await fetch('get_order_details.php?order_id=' + encodeURIComponent(orderId));
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'No se pudo obtener el pedido');
                const o = data.order || {};
                const items = data.items || [];
                const hdr = `
                    <div style="margin-bottom:10px">
                        <strong>Orden #:</strong> ${o.order_number || orderId} &nbsp;|&nbsp;
                        <strong>Estado:</strong> ${o.status || '-'} &nbsp;|&nbsp;
                        <strong>Fecha:</strong> ${o.created_at ? new Date(o.created_at).toLocaleString() : '-'}
                    </div>
                    <div style="margin-bottom:15px">
                        <strong>Cliente:</strong> ${[o.first_name, o.last_name].filter(Boolean).join(' ')} (${o.email || ''})
                    </div>
                `;
                const rows = items.map(it => `
                    <tr>
                        <td data-label="Producto">${it.product_name || '-'}</td>
                        <td data-label="Cantidad">${it.quantity}</td>
                        <td data-label="Precio Unitario">CFA ${Number(it.unit_price).toFixed(2)}</td>
                        <td data-label="Total">CFA ${(Number(it.unit_price) * Number(it.quantity)).toFixed(2)}</td>
                        <td data-label="Proveedor">${it.source_url ? `<a class=\"btn btn-view\" href=\"${it.source_url}\" target=\"_blank\" rel=\"noopener noreferrer\"><i class='fas fa-link'></i> Ver proveedor</a>` : '&mdash;'}</td>
                    </tr>
                `).join('');
                const tbl = `
                    <div class=\"table-responsive\">
                        <table class=\"orders-table\">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unitario</th>
                                    <th>Total</th>
                                    <th>Proveedor</th>
                                </tr>
                            </thead>
                            <tbody>${rows || '<tr><td colspan=\"5\" style=\"text-align:center\">Sin artículos</td></tr>'}</tbody>
                        </table>
                    </div>
                `;
                document.getElementById('orderDetails').innerHTML = hdr + tbl;
                openOrderModal();
            } catch (e) {
                toast.error(e.message);
            }
        }

        function openOrderModal() {
            const m = document.getElementById('orderModal');
            m.style.display = 'flex';
        }
        function closeOrderModal() {
            const m = document.getElementById('orderModal');
            m.style.display = 'none';
        }
        document.getElementById('orderModalClose').addEventListener('click', closeOrderModal);
        window.addEventListener('click', (e) => {
            const m = document.getElementById('orderModal');
            if (e.target === m) closeOrderModal();
        });
        // Cross-tab sync for seen/animated states
        window.addEventListener('storage', (ev)=>{
            if (ev.key===SEEN_KEY || ev.key===ANIM_KEY) {
                if (Array.isArray(__ordersCache) && __ordersCache.length>0) renderOrders(__ordersCache);
            }
        });
    </script>
    <script src="../js/toast.js"></script>
    <?php include __DIR__ . '/../includes/cart_ui.php'; ?>
</body>
</html>