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
    }
    
    $pdo = new PDOWrapper($mysqli);
}

// Cargar funciones si no están cargadas
if (!function_exists('createAdminIfNotExists')) {
    require_once 'functions.php';
}

// Crear usuario admin si no existe
createAdminIfNotExists($pdo);
?>