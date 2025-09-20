<nav class="nav-container">
    <ul class="nav-menu" id="navMenu">
        <li><a href="index.php" class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? ' active' : ''; ?>"><i class="fas fa-home"></i> Inicio</a></li>
        <li><a href="Rotteri nza kus web/index.php" class="nav-link"><i class="fas fa-shopping-cart"></i> Compras</a></li>
        <li><a href="track_shipment.php" class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'track_shipment.php' ? ' active' : ''; ?>"><i class="fas fa-search"></i> Seguimiento</a></li>
        <li><a href="index.php#contact" class="nav-link"><i class="fas fa-envelope"></i> Contacto</a></li>
        <?php if(isAuthenticated()): ?>
            <li><a href="admin.php" class="nav-link"><i class="fas fa-cog"></i> Administración</a></li>
            <li><a href="archived_shipments.php" class="nav-link"><i class="fas fa-archive"></i> Archivados</a></li>
            <li><a href="caja.php" class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'caja.php' ? ' active' : ''; ?>"><i class="fas fa-vault"></i> Caja</a></li>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión (<?php echo currentUser(); ?>)</a></li>
        <?php else: ?>
            <li><a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</a></li>
        <?php endif; ?>
    </ul>
</nav>