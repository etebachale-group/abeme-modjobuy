<?php
// Configuración para Supabase/PostgreSQL usando variable de entorno POSTGRES_URL
$envDsn = getenv('POSTGRES_URL');
if (!$envDsn) {
    die('La variable de entorno POSTGRES_URL no está definida.');
}

if (strpos($envDsn, 'postgres://') === 0) {
    $url = parse_url($envDsn);
    $user = $url['user'];
    $pass = $url['pass'];
    $host = $url['host'];
    $port = isset($url['port']) ? $url['port'] : 5432;
    $db   = ltrim($url['path'], '/');
    $query = isset($url['query']) ? $url['query'] : '';
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    if ($query) {
        $dsn .= ";$query";
    }
    $username = $user;
    $password = $pass;
} else {
    $dsn = $envDsn;
}

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos PostgreSQL: " . $e->getMessage());
}
?>