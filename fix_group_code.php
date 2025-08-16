<?php
require_once 'includes/db.php'; // This will define and initialize $pdo

try {
    // The $pdo variable is now available from db.php
    $query = "ALTER TABLE shipments ADD COLUMN group_code VARCHAR(50) NOT NULL DEFAULT ''";
    $pdo->exec($query);
    echo "Columna 'group_code' agregada exitosamente.";
} catch (PDOException $e) {
    // If the column already exists, it will throw an exception.
    // e.g., SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'group_code'
    echo "Error al agregar columna: " . $e->getMessage();
}
?>