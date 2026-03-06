<?php
// debug_db.php
require_once 'config.php';
try {
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";options='--client_encoding=UTF8'";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($pdo) {
        echo "✅ Conexão OK\n";
        $stmt = $pdo->query("SELECT current_database(), current_user");
        print_r($stmt->fetch(PDO::FETCH_ASSOC));
    }
} catch (PDOException $e) {
    echo "❌ Erro PDO: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erro Geral: " . $e->getMessage() . "\n";
}
