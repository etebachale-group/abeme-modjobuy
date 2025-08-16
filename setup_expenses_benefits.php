<?php
require_once 'includes/db.php';

try {
    // Leer el archivo SQL
    $sql = file_get_contents('sql/expenses_benefits.sql');
    
    // Ejecutar el SQL
    $pdo->exec($sql);
    
    echo "Tablas de gastos y beneficios creadas correctamente.";
} catch (PDOException $e) {
    echo "Error al crear las tablas: " . $e->getMessage();
}
?>