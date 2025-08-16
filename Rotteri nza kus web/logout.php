<?php
require_once 'includes/auth.php';

// Destroy session and redirect to login
session_destroy();
header('Location: login.php');
exit();
?>