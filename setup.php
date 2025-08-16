<?php
require_once 'includes/db.php';

try {
    // 1. Verificar y crear la tabla users si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 2. Verificar si existe el usuario admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['admin@admin.com']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        // Crear usuario admin si no existe
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute(['admin@admin.com', $password]);
        echo "Usuario administrador creado correctamente.<br>";
        echo "Email: admin@admin.com<br>";
        echo "Contraseña: admin123<br>";
    } else {
        // Asegurarse de que el usuario admin tenga el rol correcto
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
        $stmt->execute(['admin@admin.com']);
        echo "Usuario administrador verificado y actualizado.<br>";
    }
    
    echo "<br>Configuración completada. <a href='login.php'>Ir al login</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
