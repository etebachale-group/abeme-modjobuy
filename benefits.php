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
        </nav>
    </header>

    <main class="main-content">
        <section class="dashboard-section">
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Ingresos Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalRevenue, 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Beneficios Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalProfit, 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Envíos Entregados</h3>
                        <p class="stat-value"><?php echo $totalShipments; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-weight-hanging"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Kilos Entregados</h3>
                        <p class="stat-value"><?php echo number_format($totalKilos, 2, ',', '.'); ?> kg</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Gastos Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalExpenses, 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Beneficio Neto</h3>
                        <p class="stat-value">XAF <?php echo number_format($netProfit, 2, ',', '.'); ?></p>
                    </div>
                </div>
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
                        $amount = $netProfit * ($percentage / 100);
                        $isMainPartner = in_array($name, ['FERNANDO CHALE', 'MARIA CARMEN NSUE', 'GENEROSA ABEME']);
                        $color = $partnerColors[$name];
                        ?>
                        <div class="partner-progress-card <?php echo $isMainPartner ? 'main-partner' : ''; ?>">
                            <div class="progress-header">
                                <div class="progress-info">
                                    <span class="partner-name"><?php echo $name; ?></span>
                                    <div class="progress-stats">
                                        <span class="percentage-badge"><?php echo $percentage; ?>%</span>
                                        <span class="amount">XAF <?php echo number_format($amount, 2, ',', '.'); ?></span>
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
                    <table class="benefits-table">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Porcentaje</th>
                                <th>Monto (XAF)</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partners as $name => $percentage): ?>
                            <?php 
                            $amount = $netProfit * ($percentage / 100);
                            $isMainPartner = in_array($name, ['FERNANDO CHALE', 'MARIA CARMEN NSUE', 'GENEROSA ABEME']);
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
                                <td>XAF <?php echo number_format($amount, 2, ',', '.'); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-primary" onclick="viewDetails('<?php echo $name; ?>')">
                                            <i class="fas fa-eye"></i> Detalles
                                        </button>
                                        <button class="btn btn-success" onclick="requestPayment('<?php echo htmlspecialchars($name); ?>', <?php echo $amount; ?>)">
                                            <i class="fab fa-whatsapp"></i> Solicitar
                                        </button>
                                        <button class="btn btn-info" onclick="confirmPayment('<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $amount; ?>)">
                                            <i class="fas fa-check-circle"></i> Confirmar
                                        </button>
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

                <div class="payment-history">
                    <h4>Historial de Pagos</h4>
                    <div class="table-responsive">
                        <table id="paymentsHistory" class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Balance Anterior</th>
                                    <th>Nuevo Balance</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/benefits.js"></script>
</body>
</html>