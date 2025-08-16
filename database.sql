CREATE DATABASE IF NOT EXISTS `abeme_modjobuy`;
USE `abeme_modjobuy`;

-- Tabla de usuarios
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar usuario administrador
INSERT INTO `users` (`email`, `password`) 
VALUES ('djesis@abememodjobuy.com', '$2y$10$S.4V8uU1k5K0l2M3fXq0/.VqLd8i6wR7a0jZ1sYb3c4d5e6f7g8h9i');

-- Tabla de envíos


CREATE TABLE `shipments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `group_code` VARCHAR(20) NOT NULL,
  `sender_name` VARCHAR(100) NOT NULL,
  `sender_phone` VARCHAR(20) NOT NULL,
  `receiver_name` VARCHAR(100) NOT NULL,
  `receiver_phone` VARCHAR(20) NOT NULL,
  `product` VARCHAR(100) NOT NULL,
  `weight` DECIMAL(10,2) NOT NULL,
  `shipping_cost` DECIMAL(10,2) NOT NULL,
  `sale_price` DECIMAL(10,2) NOT NULL,
  `advance_payment` DECIMAL(10,2) DEFAULT 0.00,
  `profit` DECIMAL(10,2) NOT NULL,
  `ship_date` DATE NOT NULL,
  `est_date` DATE NOT NULL,
  `status` ENUM('pending', 'ontheway', 'arrived', 'delay', 'delivered') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `group_code_index` (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de registro de acciones
CREATE TABLE `action_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `shipment_id` INT NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `details` TEXT,
  `action_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`shipment_id`) REFERENCES `shipments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos de ejemplo


INSERT INTO `shipments` (
  `code`, `group_code`, `sender_name`, `sender_phone`, `receiver_name`, `receiver_phone`, 
  `product`, `weight`, `shipping_cost`, `sale_price`, `advance_payment`, `profit`,
  `ship_date`, `est_date`, `status`
) VALUES
('ABM-2023-001', '01-15-23', 'Juan Perez', '600111222', 'Maria Gomez', '600333444', 'Electrónicos', 5.2, 80.00, 120.00, 0.00, 40.00, '2023-01-15', '2023-02-01', 'delivered'),
('ABM-2023-045', '05-10-23', 'Ana Lopez', '600555666', 'Pedro Martinez', '600777888', 'Ropa y accesorios', 3.8, 60.00, 85.00, 0.00, 25.00, '2023-05-10', '2023-05-25', 'ontheway'),
('ABM-2023-078', '07-22-23', 'Carlos Sanchez', '600999000', 'Laura Fernandez', '600123456', 'Documentos importantes', 0.5, 20.00, 35.00, 0.00, 15.00, '2023-07-22', '2023-07-30', 'pending'),
('ABM-2023-102', '08-05-23', 'Sofia Ramirez', '600789012', 'David Jimenez', '600456789', 'Herramientas', 15.7, 150.00, 210.00, 0.00, 60.00, '2023-08-05', '2023-08-20', 'delayed');