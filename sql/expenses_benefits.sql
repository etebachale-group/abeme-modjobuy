-- Tabla para registrar gastos
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    paid_by VARCHAR(100) NOT NULL, -- Socio que pagó el gasto
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para registrar beneficios de socios
CREATE TABLE IF NOT EXISTS partner_benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_name VARCHAR(100) NOT NULL UNIQUE,
    total_benefits DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_expenses DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    current_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla para registrar el historial de beneficios
CREATE TABLE IF NOT EXISTS benefit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_name VARCHAR(100) NOT NULL,
    shipment_id INT,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('benefit', 'expense') NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL
);

-- Insertar socios iniciales si no existen
INSERT IGNORE INTO partner_benefits (partner_name, total_benefits, total_expenses, current_balance) VALUES
('FERNANDO CHALE', 0.00, 0.00, 0.00),
('MARIA CARMEN NSUE', 0.00, 0.00, 0.00),
('GENEROSA ABEME', 0.00, 0.00, 0.00),
('MARIA ISABEL', 0.00, 0.00, 0.00),
('CAJA', 0.00, 0.00, 0.00),
('FONDOS DE SOCIOS', 0.00, 0.00, 0.00);