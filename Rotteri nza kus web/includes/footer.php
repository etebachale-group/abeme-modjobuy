    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Rotteri Nza Kus. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Scripts movidos al footer para optimización -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Utilidades compartidas (SafeUtils) -->
    <script>
        const SafeUtils = {
            sanitize: function(text) {
                return DOMPurify.sanitize(text);
            },
            escapeHtml: function(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },
            showError: function(message) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: this.escapeHtml(message)
                });
            },
            showSuccess: function(message) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: this.escapeHtml(message)
                });
            },
            showConfirm: function(message) {
                return Swal.fire({
                    icon: 'question',
                    title: '¿Estás seguro?',
                    text: this.escapeHtml(message),
                    showCancelButton: true,
                    confirmButtonText: 'Sí',
                    cancelButtonText: 'No'
                });
            }
        };
    </script>

    <?php if (isset($jsFiles) && is_array($jsFiles)): ?>
        <?php foreach ($jsFiles as $jsFile): ?>
            <?php if ($jsFile !== 'js/mobile.js' && $jsFile !== 'js/script.js'): ?>
                <script src="<?php echo $jsFile; ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>