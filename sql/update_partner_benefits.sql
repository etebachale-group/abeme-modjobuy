USE `abeme_modjobuy`;

-- Tabla de beneficios de socios
CREATE TABLE IF NOT EXISTS `partner_benefits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL UNIQUE,
    `percentage` DECIMAL(5,2) NOT NULL,
    `total_earnings` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total histórico de ganancias generadas',
    `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Balance actual después de pagos',
    `role` ENUM('Principal', 'Socio', 'Caja', 'Fondos') NOT NULL DEFAULT 'Socio',
    `join_date` DATE NOT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `partner_name_index` (`partner_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de pagos a socios
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar datos iniciales de socios
INSERT INTO `partner_benefits` 
    (`partner_name`, `percentage`, `role`, `join_date`)
VALUES 
    ('FERNANDO CHALE', 18.00, 'Principal', '2023-01-01'),
    ('MARIA CARMEN NSUE', 18.00, 'Principal', '2023-01-01'),
    ('GENEROSA ABEME', 30.00, 'Principal', '2023-01-01'),
    ('MARIA ISABEL', 8.00, 'Socio', '2023-01-01'),
    ('CAJA', 16.00, 'Caja', '2023-01-01'),
    ('FONDOS DE SOCIOS', 10.00, 'Fondos', '2023-01-01')
ON DUPLICATE KEY UPDATE
    percentage = VALUES(percentage),
    role = VALUES(role);

-- Trigger para actualizar total_earnings cuando se confirma un nuevo pago
DELIMITER //
CREATE TRIGGER update_partner_earnings_after_payment
AFTER UPDATE ON partner_payments
FOR EACH ROW
BEGIN
    IF NEW.confirmed = 1 AND OLD.confirmed = 0 THEN
        UPDATE partner_benefits
        SET 
            current_balance = current_balance - NEW.amount
        WHERE partner_name = NEW.partner_name;
    END IF;
END;
//
DELIMITER ;

-- Procedimiento para actualizar las ganancias totales de los socios
DELIMITER //
CREATE PROCEDURE update_partner_total_earnings()
BEGIN
    -- Calcular beneficio base total de envíos entregados
    DECLARE total_base_profit DECIMAL(15,2);
    SELECT SUM(weight * 2500) INTO total_base_profit
    FROM shipments 
    WHERE status = 'delivered';
    
    -- Obtener ingresos adicionales
    DECLARE additional_profit DECIMAL(15,2);
    SELECT COALESCE(SUM(amount), 0) INTO additional_profit
    FROM expenses 
    WHERE operation_type = 'add';
    
    -- Calcular beneficio total
    SET total_base_profit = COALESCE(total_base_profit, 0) + COALESCE(additional_profit, 0);
    
    -- Actualizar ganancias totales de cada socio
    UPDATE partner_benefits
    SET total_earnings = (total_base_profit * (percentage / 100));
END;
//
DELIMITER ;

-- Trigger para actualizar las ganancias totales cuando se marca un envío como entregado
DELIMITER //
CREATE TRIGGER update_earnings_after_delivery
AFTER UPDATE ON shipments
FOR EACH ROW
BEGIN
    IF NEW.status = 'delivered' AND OLD.status != 'delivered' THEN
        CALL update_partner_total_earnings();
    END IF;
END;
//
DELIMITER ;
