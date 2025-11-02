<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1976d2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Sistema de Beneficios - Abeme Modjobuy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/benefits-app.css">
    <link rel="stylesheet" href="css/payment-history.css">
</head>
<body>
    <?php
    require_once 'includes/db.php';
    require_once 'includes/auth.php';
    require_once 'includes/functions.php';

    // Verificar que el usuario esté autenticado
    requireAuth();

    // Definir los socios y sus porcentajes
    $partners = [
        'FERNANDO CHALE' => 18,
        'MARIA CARMEN NSUE' => 18,
        'GENEROSA ABEME' => 30,
        'MARIA ISABEL' => 8,
        'CAJA' => 16,
        'FONDOS DE SOCIOS' => 10
    ];


    // Calcular ingresos totales (suma de lo que los clientes han pagado)
    $stmt = $pdo->query("SELECT SUM(sale_price) as total_revenue, SUM(weight) as total_kilos, COUNT(*) as total_shipments FROM shipments WHERE status = 'delivered'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = $result['total_revenue'] ?? 0;
    $totalKilos = $result['total_kilos'] ?? 0;
    $totalShipments = $result['total_shipments'] ?? 0;

    // Beneficio base = kilos entregados * 2500
    $baseProfit = $totalKilos * 2500;

    // Obtener ingresos adicionales (operaciones tipo 'add')
    $stmt = $pdo->query("SELECT SUM(amount) as additional_profit FROM expenses WHERE operation_type = 'add'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $additionalProfit = $result['additional_profit'] ?? 0;

    // Beneficio total = beneficio base + ingresos adicionales
    $totalProfit = $baseProfit + $additionalProfit;

    // Calcular gastos totales (solo operaciones subtract y adjust)
    $stmt = $pdo->query("SELECT SUM(amount) as total_expenses FROM expenses WHERE operation_type IN ('subtract', 'adjust')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalExpenses = $result['total_expenses'] ?? 0;

    // Beneficio neto = beneficio total - gastos
    $netProfit = $totalProfit - $totalExpenses;

    // Calcular total pagado a socios desde los monederos
    $totalPaidOut = 0;
    try {
        $stmt = $pdo->query("SELECT SUM(amount) as total_paid_out FROM partner_wallet_transactions WHERE type = 'deposit'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalPaidOut = $result['total_paid_out'] ?? 0;
    } catch (Exception $e) {
        // La tabla aún no existe, no hacer nada
    }

    // Saldo real disponible = Beneficio Neto - Total Pagado
    $realNetProfit = $netProfit - $totalPaidOut;

    // Asegurar tabla partner_benefits y filas de socios; luego obtener saldos actuales
    try {
        // Crear tabla si no existe (mínimo necesario)
        $pdo->exec("CREATE TABLE IF NOT EXISTS partner_benefits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_name VARCHAR(100) NOT NULL UNIQUE,
            percentage DECIMAL(5,2) NOT NULL,
            total_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Asegurar que existan filas para cada socio definido arriba
        $checkStmt = $pdo->prepare("SELECT partner_name FROM partner_benefits WHERE partner_name = ?");
        $insertStmt = $pdo->prepare("INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, ?)");
        foreach ($partners as $pName => $pct) {
            $checkStmt->execute([$pName]);
            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $insertStmt->execute([$pName, $pct]);
            }
        }

        // Obtener datos actuales de socios
        $partnerBalances = [];
        $stmt = $pdo->query("SELECT partner_name, percentage, total_earnings, current_balance FROM partner_benefits");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $partnerBalances[$row['partner_name']] = $row;
        }
    } catch (Exception $e) {
        $partnerBalances = [];
    }
    ?>

    <!-- Encabezado -->
    <header class="header">
        <nav class="nav-container">
            <a href="index.php" class="logo">
                <img src="img/logo.png" alt="AME Logo" class="logo-img">
            </a>
            <div class="header-title">
                <h1>Sistema de Beneficios</h1>
                <p>Distribución de ganancias entre socios</p>
            </div>
            <a href="admin.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
            <button id="btnResetBalances" class="back-button" style="margin-left:10px; background:#b71c1c; border-color:#b71c1c;">
                <i class="fas fa-rotate-left"></i> Reiniciar saldos
            </button>
            <button id="btnSeedWallets" class="back-button" style="margin-left:10px; background:#1b5e20; border-color:#1b5e20;">
                <i class="fas fa-seedling"></i> Cargar monederos
            </button>
            <button id="btnRemoveWithdrawals" class="back-button" style="margin-left:10px; background:#4e342e; border-color:#4e342e;">
                <i class="fas fa-eraser"></i> Eliminar retiros
            </button>
            <button id="btnClearPendingPayments" class="back-button" style="margin-left:10px; background:#37474f; border-color:#37474f;">
                <i class="fas fa-trash"></i> Borrar pagos pendientes
            </button>
            <button id="btnClearAllPayments" class="back-button" style="margin-left:10px; background:#263238; border-color:#263238;">
                <i class="fas fa-trash-alt"></i> Borrar TODOS los pagos
            </button>
            <?php endif; ?>
        </nav>
    </header>

    <main class="main-content wallet-page">
        <section class="dashboard-section">
            <div class="stats-summary">
                <?php if (currentUserHasPermission($pdo, 'view_ingresos_totales')): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Ingresos Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalRevenue, 2, ',', '.'); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (currentUserHasPermission($pdo, 'view_beneficios_totales')): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Beneficios Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalProfit, 2, ',', '.'); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (currentUserHasPermission($pdo, 'view_envios_entregados')): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Envíos Entregados</h3>
                        <p class="stat-value"><?php echo $totalShipments; ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (currentUserHasPermission($pdo, 'view_kilos_entregados')): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-weight-hanging"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Kilos Entregados</h3>
                        <p class="stat-value"><?php echo number_format($totalKilos, 2, ',', '.'); ?> kg</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (currentUserHasPermission($pdo, 'view_gastos_totales')): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Gastos Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalExpenses, 2, ',', '.'); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (currentUserHasPermission($pdo, 'view_beneficio_neto')): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Beneficio Neto</h3>
                        <p class="stat-value">XAF <?php echo number_format($netProfit, 2, ',', '.'); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (currentUserHasPermission($pdo, 'view_saldo_real_disponible')): ?>
                <div class="stat-card" style="background-color: #e8f5e9;">
                    <div class="stat-icon" style="color: #2e7d32;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Saldo Real Disponible</h3>
                        <p class="stat-value" style="color: #2e7d32;">XAF <?php echo number_format($realNetProfit, 2, ',', '.'); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            </div>

            <div class="benefits-distribution">
                <div class="chart-section">
                    <h2>Distribución de Beneficios</h2>
                    <div class="distribution-chart">
                        <canvas id="benefitsChart"></canvas>
                    </div>
                    <script>
                        window.partners = <?php echo json_encode($partners); ?>;
                    </script>
                </div>
                <?php
                // Definir colores para cada socio
                $partnerColors = [
                    'FERNANDO CHALE' => '#1976d2',      // Azul principal
                    'MARIA CARMEN NSUE' => '#2196f3',   // Azul secundario
                    'GENEROSA ABEME' => '#0d47a1',      // Azul oscuro
                    'MARIA ISABEL' => '#64b5f6',        // Azul claro
                    'CAJA' => '#90caf9',                // Azul más claro
                    'FONDOS DE SOCIOS' => '#bbdefb'     // Azul muy claro
                ];
                ?>
        <div class="partners-progress">
            <?php foreach ($partners as $name => $percentage): ?>
                        <?php
            // Saldo disponible real del socio
        $currentBalance = isset($partnerBalances[$name]) ? (float)$partnerBalances[$name]['current_balance'] : ($netProfit * ($percentage / 100));
    $role = $_SESSION['role'] ?? 'user';
    $own = trim((string)($_SESSION['partner_name'] ?? ''));
    $isSuper = ($role === 'super_admin');
    $isOwn = (strcasecmp($own, trim($name)) === 0);
    $canSee = $isSuper || $isOwn;
                        $isMainPartner = in_array($name, ['FERNANDO CHALE', 'MARIA CARMEN NSUE', 'GENEROSA ABEME']);
                        $color = $partnerColors[$name];
                        ?>
                        <div class="partner-progress-card <?php echo $isMainPartner ? 'main-partner' : ''; ?>">
                            <div class="progress-header">
                                <div class="progress-info">
                                    <span class="partner-name"><?php echo $name; ?></span>
                                    <div class="progress-stats">
                                        <span class="percentage-badge"><?php echo $percentage; ?>%</span>
                    <span class="amount" title="Saldo disponible" <?php echo $canSee ? ('data-partner="' . htmlspecialchars($name) . '"') : ''; ?>>
                        <?php if ($canSee): ?>
                        XAF <?php echo number_format($currentBalance, 2, ',', '.'); ?>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </span>
                                    </div>
                                </div>
                                <?php if ($isMainPartner): ?>
                                    <span class="main-partner-tag">Principal</span>
                                <?php endif; ?>
                            </div>
                            <div class="progress-container">
                                <div class="progress-track">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>">
                                        <div class="progress-glow" style="background: <?php echo $color; ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="detailed-view">
                <h2>Detalle de Beneficios por Socio</h2>
                <div class="table-responsive">
                    <table class="table-dark">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Porcentaje</th>
                                <th>Saldo Disponible (XAF)</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partners as $name => $percentage): ?>
                            <?php 
                            $currentBalance = isset($partnerBalances[$name]) ? (float)$partnerBalances[$name]['current_balance'] : ($netProfit * ($percentage / 100));
                            $isMainPartner = in_array($name, ['FERNANDO CHALE', 'MARIA CARMEN NSUE', 'GENEROSA ABEME']);
                            // Recalcular visibilidad por fila para evitar usar valores residuales de otros bucles
                            $role = $_SESSION['role'] ?? 'user';
                            $own = trim((string)($_SESSION['partner_name'] ?? ''));
                            $isSuper = ($role === 'super_admin');
                            $isOwn = (strcasecmp($own, $name) === 0);
                            $canSee = $isSuper || $isOwn;
                            ?>
                            <tr class="<?php echo $isMainPartner ? 'main-partner-row' : ''; ?>">
                                <td>
                                    <div class="partner-info">
                                        <span class="partner-name"><?php echo $name; ?></span>
                                        <?php if ($isMainPartner): ?>
                                            <span class="main-partner-badge">Principal</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo $percentage; ?>%</td>
                                <td <?php echo $canSee ? ('data-partner="' . htmlspecialchars($name) . '"') : ''; ?>>
                                    <?php if ($canSee): ?>
                                        XAF <?php echo number_format($currentBalance, 2, ',', '.'); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if (($role ?? 'user') === 'super_admin' || $isOwn): ?>
                                        <button class="btn btn-primary" onclick="viewDetails('<?php echo $name; ?>')">
                                            <i class="fas fa-eye"></i> Detalles
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-primary" disabled title="Solo tu socio o super admin">
                                            <i class="fas fa-eye"></i> Detalles
                                        </button>
                                        <?php endif; ?>
                                        <?php /* variables $role, $isSuper, $isOwn ya calculadas arriba por fila */ ?>
                                        <?php if ($name === 'CAJA'): ?>
                                            <?php if ($isSuper): ?>
                                                <a class="btn" href="caja.php"><i class="fas fa-wallet"></i> Panel</a>
                                            <?php else: ?>
                                                <button class="btn" disabled title="Solo super admin"><i class="fas fa-wallet"></i> Panel</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($isSuper || $isOwn): ?>
                                                <a class="btn" href="<?php echo 'partner_wallet.php?partner=' . urlencode($name); ?>">
                                                    <i class="fas fa-wallet"></i> Panel
                                                </a>
                                            <?php else: ?>
                                                <button class="btn" disabled title="Solo tu monedero"><i class="fas fa-wallet"></i> Panel</button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($isSuper || $isOwn): ?>
                                            <button class="btn btn-success" onclick="requestPayment('<?php echo htmlspecialchars($name); ?>', <?php echo $currentBalance; ?>)">
                                                <i class="fab fa-whatsapp"></i> Solicitar
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-success" disabled title="Solo tu monedero"><i class="fab fa-whatsapp"></i> Solicitar</button>
                                        <?php endif; ?>
                                        <?php if ($isSuper): ?>
                                            <button class="btn btn-info" onclick="confirmPayment('<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $currentBalance; ?>)"><i class="fas fa-piggy-bank"></i> Depositar</button>
                                        <?php else: ?>
                                            <button class="btn btn-info" disabled title="Solo super admin"><i class="fas fa-piggy-bank"></i> Depositar</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal para detalles -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Detalles del Socio</h2>
                <span class="close">&times;</span>
            </div>
            <div id="modalBody" class="modal-body">
                <div class="partner-details">
                    <div class="partner-header">
                        <h3 id="partnerName"></h3>
                    </div>
                    <div class="partner-summary">
                        <div class="summary-card">
                            <h4>Ganancias Totales</h4>
                            <p id="partnerTotalEarnings"></p>
                        </div>
                        <div class="summary-card">
                            <h4>Balance Actual</h4>
                            <p id="partnerCurrentBalance"></p>
                        </div>
                    </div>
                </div>

                <!-- Historial de pagos eliminado por simplificación del monedero -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="js/benefits.js"></script>
        <script>
            (function(){
                const els = Array.from(document.querySelectorAll('[data-partner]'));
                if (!els.length) return;
                const fmt = (n) => 'XAF ' + Number(n).toLocaleString('es-GQ',{minimumFractionDigits:2, maximumFractionDigits:2});
                let timer = null; let inflight = false;
                async function tick(){
                    if (inflight) return; inflight = true;
                    try {
                        // Fetch each partner balance in sequence to avoid hammering the server
                        for (const el of els) {
                            const p = el.getAttribute('data-partner');
                            const url = 'api/get_wallet_balances.php?partner=' + encodeURIComponent(p);
                            const res = await fetch(url);
                            const j = await res.json();
                            if (j && j.success && j.data) {
                                el.textContent = fmt(j.data.current_balance || 0);
                            }
                        }
                    } catch(e) { /* ignore */ }
                    finally { inflight = false; }
                }
                function start(){ if (!timer) { timer = setInterval(tick, 5000); } }
                function stop(){ if (timer) { clearInterval(timer); timer = null; } }
                document.addEventListener('visibilitychange', ()=>{ if (document.hidden) stop(); else { tick(); start(); } });
                tick(); start();
            })();
        </script>
</body>
</html>
<script>
    (function(){
        const btn = document.getElementById('btnResetBalances');
        if (!btn) return;
        btn.addEventListener('click', async () => {
            if (!confirm('¿Reiniciar saldos de todos los socios? Esto borrará movimientos de monedero y marcará pagos como no confirmados.')) return;
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reiniciando…';
            try {
                                                                const res = await fetch('api/reset_partner_balances.php', {
                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                                    body: 'scope=all',
                                    credentials: 'same-origin',
                                    cache: 'no-cache'
                                });
                                                                const txt = await res.text();
                                                                let data = null; try { data = JSON.parse(txt); } catch {}
                                                                if (res.ok && data && data.success) { alert('Reinicio completado'); location.reload(); }
                                                                else {
                                                                    const msg = data && data.message ? data.message : (txt ? txt.slice(0,200) : 'No se pudo reiniciar');
                                                                    throw new Error(`HTTP ${res.status} ${res.statusText} | ${msg}`);
                                                                }
            } catch (e) {
                console.error(e); alert('Error: ' + (e.message || 'Error de red'));
            } finally {
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-rotate-left"></i> Reiniciar saldos';
            }
        });
    })();

        // Borrar pagos pendientes
        (function(){
            const btn = document.getElementById('btnClearPendingPayments');
            if (!btn) return;
            btn.addEventListener('click', async () => {
                if (!confirm('¿Borrar TODOS los pagos PENDIENTES? Esta acción no se puede deshacer.')) return;
                try {
                    const res = await fetch('api/clear_partner_payments.php?status=pending', { method:'POST', headers:{'Accept':'application/json'}, credentials:'same-origin' });
                    const j = await res.json();
                    if (!res.ok || !j.success) throw new Error(j.message || 'Error al borrar pagos pendientes');
                    alert('Pagos pendientes borrados: ' + (j.deleted||0));
                    location.reload();
                } catch(e) { alert('Error: ' + (e.message||'Error de red')); }
            });
        })();

        // Borrar todos los pagos
        (function(){
            const btn = document.getElementById('btnClearAllPayments');
            if (!btn) return;
            btn.addEventListener('click', async () => {
                if (!confirm('¿Borrar TODOS los pagos (pendientes y confirmados)? Esta acción no se puede deshacer.')) return;
                try {
                    const res = await fetch('api/clear_partner_payments.php?status=all', { method:'POST', headers:{'Accept':'application/json'}, credentials:'same-origin' });
                    const j = await res.json();
                    if (!res.ok || !j.success) throw new Error(j.message || 'Error al borrar pagos');
                    alert('Pagos borrados: ' + (j.deleted||0));
                    location.reload();
                } catch(e) { alert('Error: ' + (e.message||'Error de red')); }
            });
        })();
(function(){
    const btn = document.getElementById('btnSeedWallets');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        if (!confirm('¿Cargar monederos con el saldo pendiente actual de todos los socios?')) return;
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando…';
        try {
                                                const res = await fetch('api/seed_wallets_from_pending.php', {
                            method:'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                            body: 'all=1',
                            credentials: 'same-origin',
                            cache: 'no-cache'
                        });
                                                const txt = await res.text();
                                                let data = null; try { data = JSON.parse(txt); } catch {}
                                                if (res.ok && data && data.success) { alert('Monederos cargados'); location.reload(); }
                                                else {
                                                    const msg = data && data.message ? data.message : (txt ? txt.slice(0,200) : 'No se pudo cargar');
                                                    throw new Error(`HTTP ${res.status} ${res.statusText} | ${msg}`);
                                                }
        } catch(e) { console.error(e); alert('Error: ' + (e.message || 'Error de red')); }
        finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-seedling"></i> Cargar monederos'; }
    });
})();

(function(){
    const btn = document.getElementById('btnRemoveWithdrawals');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        if (!confirm('¿Eliminar retiros de todos los socios y restaurar los saldos de monedero?')) return;
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando…';
        try {
                                                const res = await fetch('api/remove_partner_withdrawals.php', {
                            method:'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                            body: 'all=1',
                            credentials: 'same-origin',
                            cache: 'no-cache'
                        });
                                                const txt = await res.text();
                                                let data = null; try { data = JSON.parse(txt); } catch {}
                                                if (res.ok && data && data.success) { alert('Retiros eliminados. Restaurado: ' + (data.restored || 0)); location.reload(); }
                                                else {
                                                    const msg = data && data.message ? data.message : (txt ? txt.slice(0,200) : 'No se pudo eliminar');
                                                    throw new Error(`HTTP ${res.status} ${res.statusText} | ${msg}`);
                                                }
        } catch(e) { console.error(e); alert('Error: ' + (e.message || 'Error de red')); }
        finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-eraser"></i> Eliminar retiros'; }
    });
})();
</script>