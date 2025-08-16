<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

try {
    // Verificar si existe el usuario admin
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@admin.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Actualizar el rol si el usuario existe
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
        $stmt->execute(['admin@admin.com']);
        echo "Usuario admin actualizado correctamente.";
    } else {
        // Crear el usuario admin si no existe
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute(['admin@admin.com', $password]);
        echo "Usuario admin creado correctamente.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
