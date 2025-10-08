<?php
$host = 'localhost';
$dbname = 'abeme_modjobuy';
$username = 'root';
$password = '';

try {
    // First try to connect using PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If PDO fails, try mysqli
    $mysqli = new mysqli($host, $username, $password, $dbname);
    
    if ($mysqli->connect_error) {
        die("Error de conexión a la base de datos: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset("utf8");
    
    // Create a PDO-like wrapper for mysqli
    class PDOWrapper {
        private $mysqli;
        private $inTransaction = false;
        
        public function __construct($mysqli) {
            $this->mysqli = $mysqli;
        }
        
        public function prepare($query) {
            return new PDOStatementWrapper($this->mysqli, $query);
        }
        
        public function query($query) {
            $result = $this->mysqli->query($query);
            if ($result === false) {
                throw new Exception($this->mysqli->error);
            }
            return new PDOStatementWrapper($this->mysqli, $query, $result);
        }
        
        public function exec($query) {
            return $this->mysqli->query($query);
        }
        
        public function lastInsertId() {
            return $this->mysqli->insert_id;
        }

        // Transaction support to be compatible with PDO
        public function beginTransaction() {
            // Disable autocommit and start a transaction
            if (method_exists($this->mysqli, 'begin_transaction')) {
                $result = $this->mysqli->begin_transaction();
                if ($result) { $this->inTransaction = true; }
                return $result;
            }
            $this->mysqli->autocommit(false);
            $this->inTransaction = true;
            return true;
        }

        public function commit() {
            $result = $this->mysqli->commit();
            // Re-enable autocommit after commit
            $this->mysqli->autocommit(true);
            $this->inTransaction = false;
            return $result;
        }

        public function rollBack() {
            $result = $this->mysqli->rollback();
            // Re-enable autocommit after rollback
            $this->mysqli->autocommit(true);
            $this->inTransaction = false;
            return $result;
        }

        public function inTransaction() {
            return (bool)$this->inTransaction;
        }
    }
    
    class PDOStatementWrapper {
        private $mysqli;
        private $query;
        private $result;
        private $params = [];
        
        public function __construct($mysqli, $query, $result = null) {
            $this->mysqli = $mysqli;
            $this->query = $query;
            $this->result = $result;
        }
        
        public function execute($params = null) {
            if ($params) {
                $this->params = $params;
            }
            
            $query = $this->query;
            if (!empty($this->params)) {
                foreach ($this->params as $param) {
                    $param = $this->mysqli->real_escape_string($param);
                    $query = preg_replace('/\?/', "'$param'", $query, 1);
                }
            }
            
            $this->result = $this->mysqli->query($query);
            return $this->result !== false;
        }
        
        public function fetch($fetch_style = null) {
            if (!$this->result) return false;
            return $this->result->fetch_assoc();
        }
        
        public function fetchAll($fetch_style = null) {
            if (!$this->result) return [];
            $rows = [];
            while ($row = $this->result->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        }
        
        public function fetchColumn() {
            if (!$this->result) return false;
            $row = $this->result->fetch_row();
            return $row ? $row[0] : null;
        }

        // Add rowCount compatibility for SELECT/SHOW queries
        public function rowCount() {
            if (!$this->result) return 0;
            return isset($this->result->num_rows) ? $this->result->num_rows : 0;
        }
    }
    
    $pdo = new PDOWrapper($mysqli);
}

// Cargar funciones si no están cargadas
if (!function_exists('createAdminIfNotExists')) {
    require_once 'functions.php';
}

// Crear usuario admin si no existe
createAdminIfNotExists($pdo);

// Auto-healing: ensure users & admins tables/columns exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add missing columns defensively (MySQL <8 compatibility: attempt, ignore failures)
    $alterColumns = [
        "ALTER TABLE users ADD COLUMN first_name VARCHAR(100) NULL",
        "ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NULL",
        "ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user'"
    ];
    foreach ($alterColumns as $sql) {
        try { $pdo->exec($sql); } catch (Exception $ignore) {}
    }
} catch (Exception $e) {
    // Silent: avoid blocking app if permissions limited
}

// Auto-healing: ensure ad_banners table exists for homepage carousel
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ad_banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        title VARCHAR(255) NULL,
        link_url VARCHAR(500) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add missing columns defensively
    $alters = [
        "ALTER TABLE ad_banners ADD COLUMN title VARCHAR(255) NULL",
        "ALTER TABLE ad_banners ADD COLUMN link_url VARCHAR(500) NULL",
        "ALTER TABLE ad_banners ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE ad_banners ADD COLUMN sort_order INT NOT NULL DEFAULT 0"
    ];
    foreach ($alters as $sql) { try { $pdo->exec($sql); } catch (Exception $ignore) {} }
} catch (Exception $e) {
    // ignore if DB user lacks permissions; carousel will simply be hidden
}
?>