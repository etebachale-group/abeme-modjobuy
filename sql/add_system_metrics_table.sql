CREATE TABLE IF NOT EXISTS system_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(255) NOT NULL UNIQUE,
    metric_value DECIMAL(15, 2) NOT NULL DEFAULT 0.00
);

INSERT IGNORE INTO system_metrics (metric_name, metric_value) VALUES ('total_accumulated_benefits', 0.00);