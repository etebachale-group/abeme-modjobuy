    </main>
    <!-- Pie de página -->
    <footer class="footer">
        <!-- Footer desktop -->
        <div class="footer-desktop">
            <div class="footer-container">
                <div class="footer-col">
                    <div class="footer-title">Abeme Modjobuy</div>
                    <div class="footer-contact">Conectando Ghana y Guinea Ecuatorial con servicios de envío confiables y eficientes desde 2018.</div>
                    <div class="footer-social">
                        <a href="https://chat.whatsapp.com/KkIiqpqZp3W9XMM0iOpsjv?mode=r_c" target="_blank"><i class="fab fa-whatsapp"></i></a>
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <div class="footer-title">Enlaces Rápidos</div>
                    <div class="footer-links">
                        <a href="index.php" class="footer-link"><i class="fas fa-home"></i> Inicio</a>
                        <a href="track_shipment.php" class="footer-link"><i class="fas fa-search"></i> Seguimiento</a>
                        <a href="#contact" class="footer-link"><i class="fas fa-envelope"></i> Contacto</a>
                        <a href="https://chat.whatsapp.com/KkIiqpqZp3W9XMM0iOpsjv?mode=r_c" class="footer-link"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                        <a href="login.php" class="footer-link"><i class="fas fa-user"></i> Administración</a>
                    </div>
                </div>
                <div class="footer-col">
                    <div class="footer-title">Legal</div>
                    <div class="footer-links">
                        <a href="#" class="footer-link">Términos y Condiciones</a>
                        <a href="#" class="footer-link">Política de Privacidad</a>
                        <a href="#" class="footer-link">Política de Envíos</a>
                        <a href="#" class="footer-link">Preguntas Frecuentes</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; Eteba Group. Todos los derechos reservados.
            </div>
        </div>
        
        <!-- Footer mobile (nav bar style) -->
        <nav class="mobile-nav">
            <a href="index.php" class="mobile-nav-item">
                <i class="fas fa-home"></i>
                <span>Inicio</span>
            </a>
            <a href="track_shipment.php" class="mobile-nav-item">
                <i class="fas fa-search"></i>
                <span>Seguimiento</span>
            </a>
            <a href="https://chat.whatsapp.com/KkIiqpqZp3W9XMM0iOpsjv?mode=r_c" class="mobile-nav-item" target="_blank">
                <i class="fab fa-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="admin.php" class="mobile-nav-item">
                <i class="fas fa-user-shield"></i>
                <span>Admin</span>
            </a>
            <?php else: ?>
            <a href="index.php#recent-shipments" class="mobile-nav-item">
                <i class="fas fa-box"></i>
                <span>Envíos</span>
            </a>
            <?php endif; ?>
        </nav>
    </footer>
    <script src="js/script.js"></script>
    <?php if (basename($_SERVER['PHP_SELF']) === 'register_partner.php'): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php endif; ?>
</body>
</html>