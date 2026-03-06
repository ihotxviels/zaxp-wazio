<?php
require_once __DIR__ . '/config.php';

try {
    // 1. Connect to default postgres database to create the new one (cannot create db while connected to it)
    $dsn_default = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=postgres";
    $pdo_default = new PDO($dsn_default, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Check if 'wazio' already exists
    $stmt = $pdo_default->query("SELECT 1 FROM pg_database WHERE datname = 'wazio'");
    if (!$stmt->fetch()) {
        $pdo_default->exec("CREATE DATABASE wazio");
        echo "Database 'wazio' created successfully.\n";
    } else {
        echo "Database 'wazio' already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error creating database: " . $e->getMessage() . "\n";
    exit;
}
?>