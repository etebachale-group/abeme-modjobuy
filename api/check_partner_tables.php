<?php
require_once '../includes/db.php';

// Verificar si la tabla existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'partner_benefits'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Crear la tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `partner_benefits` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `partner_name` VARCHAR(100) NOT NULL UNIQUE,
                `percentage` DECIMAL(5,2) NOT NULL,
                `total_earnings` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `role` ENUM('Principal', 'Socio', 'Caja', 'Fondos') NOT NULL DEFAULT 'Socio',
                `join_date` DATE NOT NULL,
                `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `partner_name_index` (`partner_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Insertar datos iniciales
        $pdo->exec("
            INSERT INTO `partner_benefits` 
                (`partner_name`, `percentage`, `role`, `join_date`)
            VALUES 
                ('FERNANDO CHALE', 18.00, 'Principal', '2023-01-01'),
                ('MARIA CARMEN NSUE', 18.00, 'Principal', '2023-01-01'),
                ('GENEROSA ABEME', 30.00, 'Principal', '2023-01-01'),
                ('MARIA ISABEL', 8.00, 'Socio', '2023-01-01'),
                ('CAJA', 16.00, 'Caja', '2023-01-01'),
                ('FONDOS DE SOCIOS', 10.00, 'Fondos', '2023-01-01')
        ");
    }

    // Verificar si la tabla partner_payments existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'partner_payments'");
    $paymentsTableExists = $stmt->rowCount() > 0;

    if (!$paymentsTableExists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `partner_payments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `partner_name` VARCHAR(100) NOT NULL,
                `amount` DECIMAL(15,2) NOT NULL,
                `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `confirmed` BOOLEAN DEFAULT FALSE,
                `confirmation_date` TIMESTAMP NULL,
                `notes` TEXT,
                FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits`(`partner_name`),
                INDEX `payment_date_index` (`payment_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    echo json_encode(['success' => true, 'message' => 'Estructura de base de datos verificada y corregida']);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
