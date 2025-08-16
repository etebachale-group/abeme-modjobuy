<?php
require_once '../includes/db.php';

try {
    // Verificar si la columna last_updated ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM partner_benefits LIKE 'last_updated'");
    $columnExists = $stmt->rowCount() > 0;

    if (!$columnExists) {
        // Agregar la columna last_updated
        $pdo->exec("
            ALTER TABLE partner_benefits 
            ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
            ON UPDATE CURRENT_TIMESTAMP
        ");
    }

    // Verificar otras columnas necesarias
    $stmt = $pdo->query("DESCRIBE partner_benefits");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Asegurarse de que todas las columnas necesarias existen
    $requiredColumns = [
        'partner_name' => "VARCHAR(100) NOT NULL",
        'percentage' => "DECIMAL(5,2) NOT NULL",
        'total_earnings' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
        'current_balance' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
        'role' => "ENUM('Principal', 'Socio', 'Caja', 'Fondos') NOT NULL DEFAULT 'Socio'",
        'join_date' => "DATE NOT NULL"
    ];

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $columns)) {
            $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN `$column` $definition");
        }
    }

    // Actualizar los datos existentes con valores por defecto si es necesario
    $pdo->exec("
        UPDATE partner_benefits 
        SET join_date = '2023-01-01' 
        WHERE join_date IS NULL
    ");

    // Verificar y actualizar la estructura de la tabla expenses
    $stmt = $pdo->query("DESCRIBE expenses");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Verificar y agregar las columnas necesarias
    $requiredColumns = [
        'partner_name' => "VARCHAR(100)",
        'notes' => "TEXT",
        'amount' => "DECIMAL(15,2) NOT NULL",
        'operation_type' => "ENUM('add', 'subtract', 'adjust') NOT NULL"
    ];

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $columns)) {
            $pdo->exec("ALTER TABLE expenses ADD COLUMN `$column` $definition");
        }
    }

    // Si la tabla no existe, créala
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_name VARCHAR(100),
            amount DECIMAL(15,2) NOT NULL,
            operation_type ENUM('add', 'subtract', 'adjust') NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Verificar si la tabla partner_payments existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'partner_payments'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Crear la tabla partner_payments si no existe
        $pdo->exec("
            CREATE TABLE partner_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                partner_name VARCHAR(100) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                confirmation_date TIMESTAMP NULL,
                confirmed BOOLEAN DEFAULT FALSE,
                previous_balance DECIMAL(15,2) NOT NULL,
                new_balance DECIMAL(15,2) NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (partner_name) REFERENCES partner_benefits(partner_name)
            )
        ");
    } else {
        // Verificar y agregar columnas faltantes en partner_payments
        $stmt = $pdo->query("DESCRIBE partner_payments");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $requiredPaymentColumns = [
            'previous_balance' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'new_balance' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'notes' => "TEXT NULL",
            'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($requiredPaymentColumns as $column => $definition) {
            if (!in_array($column, $columns)) {
                $pdo->exec("ALTER TABLE partner_payments ADD COLUMN `$column` $definition");
            }
        }
    }

    // Devolver éxito
    echo json_encode(['success' => true, 'message' => 'Estructura de tablas actualizada correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al actualizar la estructura de las tablas: ' . $e->getMessage()]);
}
