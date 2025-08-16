<?php
require_once 'includes/auth.php';

session_start();
// Si el usuario no está autenticado, redirigir a login
requireAuth();

session_destroy();
header('Location: login.php');
exit;
?>