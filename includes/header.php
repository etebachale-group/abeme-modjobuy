<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Abeme Modjobuy - Envíos entre Ghana y Guinea Ecuatorial</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/shipments.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/forms.css">
    <link rel="stylesheet" href="css/shipment-groups.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/visibility-improvements.css">
    <link rel="stylesheet" href="css/pagination.css">
    <link rel="stylesheet" href="css/table-scroll.css">
    <link rel="stylesheet" href="css/ajax-loading.css">
    <link rel="stylesheet" href="css/footer-responsive.css">
    <link rel="stylesheet" href="css/group-tables-mobile.css">
    <link rel="stylesheet" href="css/payment-details.css">
    <link rel="stylesheet" href="css/archived-shipments.css">
    <script src="js/menu.js" defer></script>
    <script src="js/shipments.js" defer></script>
    <script src="js/shipment-groups.js" defer></script>
    <script src="js/notifications.js" defer></script>
    <script src="js/tracking.js" defer></script>
    <script src="js/payment-calculator.js" defer></script>
    <script src="js/notify-arrival.js" defer></script>
</head>
<body>
    <!-- Encabezado -->
    <header class="header">
        <nav class="nav-container">
            <a href="index.php" class="logo">
                <img src="img/logo.png" alt="AME Logo" class="logo-img">
            </a>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="nav-link"><i class="fas fa-home"></i><span>Inicio</span></a></li>
                <li><a href="Rotteri nza kus web/index.php" class="nav-link"><i class="fas fa-shopping-cart"></i><span>Compras</span></a></li>
                <li><a href="track_shipment.php" class="nav-link"><i class="fas fa-search"></i><span>Rastrear</span></a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="admin.php" class="nav-link"><i class="fas fa-cog"></i><span>Admin</span></a></li>
                <li><a href="benefits.php" class="nav-link"><i class="fas fa-chart-bar"></i><span>Beneficios</span></a></li>
                <li><a href="expenses.php" class="nav-link"><i class="fas fa-money-bill-wave"></i><span>Gastos</span></a></li>
                <li><a href="archived_shipments.php" class="nav-link"><i class="fas fa-box-archive"></i><span>Entregas</span></a></li>
                <li>
                    <div class="notification-bell">
                        <a href="#" id="notificationBell" class="nav-link">
                            <i class="fas fa-bell"></i>
                            <span id="notificationCount" class="notification-badge" style="display: none;">0</span>
                        </a>
                        <div id="notificationList" class="notification-list"></div>
                    </div>
                </li>
                <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
                <?php else: ?>
                <li><a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    
    <main class="main-content">