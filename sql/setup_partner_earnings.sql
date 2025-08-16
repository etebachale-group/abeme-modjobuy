-- Crear tabla de beneficios de socios si no existe
CREATE TABLE IF NOT EXISTS `partner_benefits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL UNIQUE,
    `percentage` DECIMAL(5,2) NOT NULL,
    `total_earnings` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Ganancias totales históricas (solo incrementa)',
    `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Balance actual después de pagos',
    `role` ENUM('Principal', 'Socio', 'Caja', 'Fondos') NOT NULL DEFAULT 'Socio',
    `join_date` DATE NOT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Crear tabla de pagos a socios
CREATE TABLE IF NOT EXISTS `partner_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `confirmed` BOOLEAN DEFAULT FALSE,
    `confirmation_date` TIMESTAMP NULL,
    `previous_balance` DECIMAL(15,2) NOT NULL COMMENT 'Balance antes del pago',
    `new_balance` DECIMAL(15,2) NOT NULL COMMENT 'Balance después del pago',
    `notes` TEXT,
    FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits`(`partner_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Crear tabla de historial de beneficios
CREATE TABLE IF NOT EXISTS `partner_earnings_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `source` VARCHAR(50) NOT NULL COMMENT 'shipment/expense',
    `source_id` INT NOT NULL COMMENT 'ID del envío o gasto',
    `earned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `previous_total` DECIMAL(15,2) NOT NULL COMMENT 'Total anterior',
    `new_total` DECIMAL(15,2) NOT NULL COMMENT 'Nuevo total después de sumar',
    FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits`(`partner_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger para actualizar total_earnings cuando se registra un nuevo beneficio
DELIMITER //
CREATE TRIGGER update_partner_earnings_after_history
AFTER INSERT ON partner_earnings_history
FOR EACH ROW
BEGIN
    UPDATE partner_benefits 
    SET 
        total_earnings = NEW.new_total,
        current_balance = current_balance + NEW.amount
    WHERE partner_name = NEW.partner_name;
END;
//

-- Trigger para actualizar current_balance cuando se confirma un pago
CREATE TRIGGER update_balance_after_payment_confirmation
AFTER UPDATE ON partner_payments
FOR EACH ROW
BEGIN
    IF NEW.confirmed = 1 AND OLD.confirmed = 0 THEN
        UPDATE partner_benefits
        SET current_balance = current_balance - NEW.amount
        WHERE partner_name = NEW.partner_name;
    END IF;
END;
//

-- Procedimiento para registrar un nuevo beneficio
CREATE PROCEDURE register_partner_earning(
    IN p_partner_name VARCHAR(100),
    IN p_amount DECIMAL(15,2),
    IN p_source VARCHAR(50),
    IN p_source_id INT
)
BEGIN
    DECLARE current_total DECIMAL(15,2);
    
    -- Obtener el total actual
    SELECT total_earnings INTO current_total
    FROM partner_benefits
    WHERE partner_name = p_partner_name;
    
    -- Registrar en el historial
    INSERT INTO partner_earnings_history (
        partner_name,
        amount,
        source,
        source_id,
        previous_total,
        new_total
    ) VALUES (
        p_partner_name,
        p_amount,
        p_source,
        p_source_id,
        current_total,
        current_total + p_amount
    );
END;
//

-- Procedimiento para actualizar beneficios de envío
CREATE PROCEDURE update_shipment_benefits(IN p_shipment_id INT)
BEGIN
    DECLARE total_benefit DECIMAL(15,2);
    
    -- Calcular beneficio total del envío
    SELECT (weight * 2500) INTO total_benefit
    FROM shipments
    WHERE id = p_shipment_id AND status = 'delivered';
    
    IF total_benefit > 0 THEN
        -- Distribuir beneficios entre socios
        INSERT INTO partner_earnings_history (
            partner_name,
            amount,
            source,
            source_id,
            previous_total,
            new_total
        )
        SELECT 
            pb.partner_name,
            (total_benefit * (pb.percentage / 100)) as benefit_amount,
            'shipment',
            p_shipment_id,
            pb.total_earnings,
            pb.total_earnings + (total_benefit * (pb.percentage / 100))
        FROM partner_benefits pb;
    END IF;
END;
//

DELIMITER ;
