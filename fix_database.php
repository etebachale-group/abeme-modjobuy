<?php
require_once 'includes/db.php';

try {
    // Verificar si la tabla users existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Crear la tabla users si no existe
        $pdo->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user'
        )");
        echo "Tabla users creada correctamente.<br>";
    } else {
        // Verificar si la columna role existe
        $columnExists = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->rowCount() > 0;
        
        if (!$columnExists) {
            // Agregar la columna role si no existe
            $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user'");
            echo "Columna role agregada correctamente.<br>";
        }
    }
    
    // Verificar si existe el usuario admin
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@admin.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Actualizar el rol del usuario admin
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
        $stmt->execute(['admin@admin.com']);
        echo "Role del usuario admin actualizado correctamente.<br>";
    } else {
        // Crear el usuario admin
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute(['admin@admin.com', $password]);
        echo "Usuario admin creado correctamente.<br>";
    }

    // Crear la tabla shipment_groups si no existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'shipment_groups'")->rowCount() > 0;
    
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE shipment_groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_code VARCHAR(50) NOT NULL UNIQUE,
                is_archived TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "Tabla shipment_groups creada correctamente.<br>";
        
        // Poblar la tabla con los group_codes existentes
        $pdo->exec("
            INSERT IGNORE INTO shipment_groups (group_code)
            SELECT DISTINCT group_code FROM shipments 
            WHERE group_code IS NOT NULL
        ");
        echo "Datos de grupos migrados correctamente.<br>";
    }
    
    // Verificar si la columna advance_payment existe en la tabla shipments
    $columnExists = $pdo->query("SHOW COLUMNS FROM shipments LIKE 'advance_payment'")->rowCount() > 0;
    
    if (!$columnExists) {
        // Agregar la columna advance_payment si no existe
        $pdo->exec("ALTER TABLE shipments ADD advance_payment DECIMAL(10,2) DEFAULT 0.00");
        echo "Columna advance_payment agregada correctamente.<br>";
    }
    
    echo "Todo listo. Ahora puedes iniciar sesión con:<br>";
    echo "Email: admin@admin.com<br>";
    echo "Contraseña: admin123";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
