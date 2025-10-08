<?php
// Determines which admin table to use: prefer existing healthy 'admins', otherwise fallback/create 'administradores'
// We keep lightweight logic here to avoid duplicating across pages.
if (!function_exists('admin_table')) {
    function admin_table(PDO $pdo) : string {
        static $cached = null;
        if ($cached !== null) return $cached;
    $primary = 'admins';
    // New fallback table name using singular 'administrador'
    $fallback = 'administrador';
        $choose = $primary;
        try {
            $stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$primary]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Test minimal access
                try { $pdo->query("SELECT 1 FROM `".$primary."` LIMIT 0"); }
                catch (Exception $e) {
                    // Corrupt access, switch
                    $choose = $fallback;
                }
            } else {
                // Primary missing; use fallback
                $choose = $fallback;
            }
        } catch (Exception $e) {
            $choose = $fallback;
        }
        // Ensure fallback exists if chosen
        if ($choose === $fallback) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `".$fallback."` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    company_name VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Exception $ignore) {}
        }
        return $cached = $choose;
    }
}
?>
