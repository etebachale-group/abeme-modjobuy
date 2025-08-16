<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
?>

<?php include 'includes/header.php'; ?>

    <!-- Sección Hero -->
    <section class="hero section">
        <div class="container">
            <div class="hero-content">
                <h2 class="section-title">Conectando Ghana y Guinea Ecuatorial</h2>
                <p>Servicio de envíos confiable y eficiente entre dos naciones</p>
                <a href="#tracking" class="btn btn-lg">Rastrear mi envío</a>
            </div>
        </div>
    </section>

    <!-- Sección de Seguimiento -->
    <section id="tracking" class="section">
        <div class="container">
            <h2 class="section-title">Seguimiento de Envíos</h2>
            <div class="tracking-form">
                <input 
                    type="text" 
                    class="tracking-input form-input" 
                    id="tracking-code" 
                    placeholder="Ingresa tu código de seguimiento (Ej: ABM-123456)"
                    pattern="ABM-[0-9]{6}"
                    title="El código debe tener el formato ABM-123456">
                <button class="btn" onclick="trackShipment()">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
            <small class="form-help">Ingrese el código en el formato ABM-XXXXXX donde X son números</small>
            <div id="tracking-result" class="tracking-result">
                <h3 class="section-title">Resultados de seguimiento:</h3>
                <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Peso (kg)</th>
                            <th>Precio</th>
                            <th>Pago Adelantado</th>
                            <th>Saldo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tracking-result-body">
                        <!-- Resultados se llenarán con JavaScript -->
                    </tbody>
                </table>
                </div>
            </div>
            <h3 class="section-title" style="margin-top:2.5rem;">
                Ejemplos de envíos recientes
                <div class="sort-controls">
                    <button onclick="toggleSort()" class="sort-btn">
                        <i class="fas fa-sort"></i> Cambiar orden
                    </button>
                </div>
            </h3>
            <div class="recent-shipments-container">
                <div class="table-scroll-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Peso (kg)</th>
                                <th>Fecha de envío</th>
                                <th>Precio</th>
                                <th>Pago Adelantado</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="recent-shipments">
                            <?php
                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'DESC';
                            $perPage = 10;
                    
                    $result = getRecentUndeliveredShipments($pdo, $sort, $page, $perPage);
                    $shipments = $result['shipments'];
                    
                    foreach ($shipments as $shipment) {
                        echo '<tr>';
                        echo '<td>'.$shipment['weight'].' kg</td>';
                        echo '<td>'.date('d/m/Y', strtotime($shipment['ship_date'])).'</td>';
                        echo '<td>'.number_format($shipment['sale_price'], 0, '.', ',').' XAF</td>';
                        echo '<td>'.number_format($shipment['advance_payment'], 2, '.', ',').' XAF</td>';
                        echo '<td>'.number_format($shipment['sale_price'] - $shipment['advance_payment'], 2, '.', ',').' XAF</td>';
                        echo '<td>'.getStatusBadge($shipment['status']).'</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
                </div>
            </div>
            
            <?php if ($result['pages'] > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>&sort=<?php echo $sort; ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php endif; ?>
                
                <div class="page-numbers">
                    <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>"
                        class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $result['pages']): ?>
                    <a href="?page=<?php echo ($page + 1); ?>&sort=<?php echo $sort; ?>" class="page-link">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
                
                <div class="results-info">
                    Mostrando <?php echo count($shipments); ?> de <?php echo $result['total']; ?> envíos
                </div>
            </div>
            <?php endif; ?>
    </section>

    <!-- Sección de Contacto -->
    <section id="contact" class="contact-section">
        <div class="contact-container">
            <div class="contact-info">
                <h3>Contacta con Nosotros</h3>
                <div class="contact-details">
                    <p><i class="fas fa-map-marker-alt"></i> Accra Office: 123 Independence Ave, Accra, Ghana</p>
                    <p><i class="fas fa-map-marker-alt"></i> Malabo Office: Calle de la Libertad 45, Malabo, Guinea Ecuatorial</p>
                    <p><i class="fas fa-phone"></i> +233 24 123 4567 (Ghana)</p>
                    <p><i class="fas fa-phone"></i> +240 222 123 456 (Guinea Ecuatorial)</p>
                    <p><i class="fas fa-envelope"></i> .com</p>
                </div>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="contact-form">
                <form id="contactForm" action="send_contact.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Nombre completo *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo electrónico *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Número de teléfono *</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Asunto *</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Mensaje *</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachment">Adjuntar archivo (opcional)</label>
                        <input type="file" id="attachment" name="attachment" class="file-input" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <button type="submit" class="btn">Enviar Mensaje</button>
                </form>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>