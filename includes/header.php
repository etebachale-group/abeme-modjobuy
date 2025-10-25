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
    <link rel="stylesheet" href="css/panel-ui.css">
    <link rel="stylesheet" href="css/header-responsive.css">
    <link rel="stylesheet" href="css/carousel.css">
    <style>
        /* Minimal functional styles for collapsible nav (no design) */
        .header .menu-toggle { display: none !important; }
        .header .nav-container .nav-menu { display: block !important; margin: 0; padding: 0; list-style: none; }
        @media (min-width: 769px) {
            .header .nav-container .nav-menu { display: flex !important; align-items: center; gap: .5rem; }
            .header .nav-container .nav-menu > li { display: inline-block; }
        }
        @media (max-width: 768px) {
            .header .menu-toggle { display: block !important; }
            .header .nav-container { position: relative; }
            .header .nav-container .nav-menu { display: none !important; }
            /* Open state: dropdown overlays content below without changing header size */
            .header .nav-container.open .nav-menu {
                display: block !important;
                position: absolute;
                top: calc(100% + 6px);
                right: 8px;
                left: 8px;
                z-index: 3000;
                background-color: inherit; /* visual style comes from external css */
            }
        }
    </style>
    <?php if (basename($_SERVER['PHP_SELF']) === 'register_partner.php'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/admin-console.css">
    <?php endif; ?>
    <script src="js/menu.js" defer></script>
    <script src="js/shipments.js" defer></script>
    <script src="js/shipment-groups.js" defer></script>
    <script src="js/notifications.js" defer></script>
    <script src="js/tracking.js" defer></script>
    <script src="js/payment-calculator.js" defer></script>
    <script src="js/notify-arrival.js" defer></script>
    <script src="js/carousel.js" defer></script>
</head>
<body>
    <!-- Encabezado -->
    <header class="header">
        <nav class="nav-container">
            <a href="index.php" class="logo">
                <img src="img/logo.png" alt="AME Logo" class="logo-img">
            </a>
            <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-controls="navMenu" aria-expanded="false" role="button" tabindex="0">
                <i class="fas fa-bars"></i>
            </button>
            
            <?php
                $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
                $active = function($pages) use ($currentPage) {
                    if (!is_array($pages)) { $pages = [$pages]; }
                    return in_array($currentPage, $pages, true) ? ' active' : '';
                };
            ?>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="nav-link<?php echo $active('index.php'); ?>"><i class="fas fa-home"></i><span>Inicio</span></a></li>
                <li><a href="Rotteri nza kus web/index.php" class="nav-link"><i class="fas fa-shopping-cart"></i><span>Compras</span></a></li>
                <li><a href="track_shipment.php" class="nav-link<?php echo $active('track_shipment.php'); ?>"><i class="fas fa-search"></i><span>Rastrear</span></a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <?php $__role = $_SESSION['role'] ?? 'user'; ?>
                <?php if ($__role === 'admin' || $__role === 'super_admin'): ?>
                <li><a href="admin.php" class="nav-link<?php echo $active('admin.php'); ?>"><i class="fas fa-cog"></i><span>Admin</span></a></li>
                <?php endif; ?>
                <?php if ($__role === 'super_admin'): ?>
                <li><a href="register_partner.php" class="nav-link<?php echo $active('register_partner.php'); ?>"><i class="fas fa-user-plus"></i><span>Registrar Socio</span></a></li>
                <li><a href="benefits.php" class="nav-link<?php echo $active('benefits.php'); ?>"><i class="fas fa-wallet"></i><span>Monederos</span></a></li>
                <?php endif; ?>
                <?php if ($__role !== 'super_admin'): ?>
                <li><a href="benefits.php" class="nav-link<?php echo $active('benefits.php'); ?>"><i class="fas fa-chart-bar"></i><span>Beneficios</span></a></li>
                <?php endif; ?>
                <?php if ($__role === 'admin' && !empty($_SESSION['partner_name'])): ?>
                <li>
                    <a href="partner_wallet.php?partner=<?php echo urlencode($_SESSION['partner_name'] ?? ''); ?>" class="nav-link<?php echo $active('partner_wallet.php'); ?>">
                        <i class="fas fa-wallet"></i><span>Mi Monedero</span>
                    </a>
                </li>
                <?php elseif ($__role === 'super_admin'): ?>
                <li><a href="caja.php" class="nav-link<?php echo $active('caja.php'); ?>"><i class="fas fa-vault"></i><span>Caja</span></a></li>
                <?php else: ?>
                <?php if (isset($pdo) && function_exists('currentUserHasPermission') && currentUserHasPermission($pdo, 'access_caja')): ?>
                <li><a href="caja.php" class="nav-link<?php echo $active('caja.php'); ?>"><i class="fas fa-vault"></i><span>Caja</span></a></li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($__role === 'super_admin'): ?>
                <li><a href="expenses.php" class="nav-link<?php echo $active('expenses.php'); ?>"><i class="fas fa-money-bill-wave"></i><span>Gastos</span></a></li>
                <?php else: ?>
                <?php if (isset($pdo) && function_exists('currentUserHasPermission') && currentUserHasPermission($pdo, 'access_expenses')): ?>
                <li><a href="expenses.php" class="nav-link<?php echo $active('expenses.php'); ?>"><i class="fas fa-money-bill-wave"></i><span>Gastos</span></a></li>
                <?php endif; ?>
                <?php endif; ?>
                <li><a href="archived_shipments.php" class="nav-link<?php echo $active('archived_shipments.php'); ?>"><i class="fas fa-box-archive"></i><span>Entregas</span></a></li>
                <li>
                    <div class="notification-bell">
                        <a href="#" id="notificationBell" class="nav-link">
                            <i class="fas fa-bell"></i>
                            <span id="notificationCount" class="notification-badge" style="display: none;">0</span>
                        </a>
                        <div id="notificationList" class="notification-list"></div>
                    </div>
                </li>
                <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i><span>Salir</span></a></li>
                <?php else: ?>
                <li><a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i><span>Login</span></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    
    <main class="main-content">