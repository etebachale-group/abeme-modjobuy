CREATE TABLE IF NOT EXISTS shipment_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_code VARCHAR(50) NOT NULL UNIQUE,
    is_archived TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Populate shipment_groups table with existing group_codes from shipments
INSERT IGNORE INTO shipment_groups (group_code)
SELECT DISTINCT group_code FROM shipments WHERE group_code IS NOT NULL;
