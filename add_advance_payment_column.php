<?php
require_once 'includes/db.php';

try {
    // Agregar la columna advance_payment si no existe
    $pdo->exec("ALTER TABLE shipments ADD advance_payment DECIMAL(10,2) DEFAULT 0.00");
    echo "Columna advance_payment agregada correctamente.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>