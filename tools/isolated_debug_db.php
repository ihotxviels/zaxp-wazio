<?php
// isolated_debug_db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', '137.184.202.248');
define('DB_NAME', 'criadordigital');
define('DB_USER', 'postgres');
define('DB_PASS', '2612167B98D188F2D15D854AD8AA7');

try {
    echo "Iniciando conexão com " . DB_HOST . "...\n";
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ Conexão OK\n";
    $stmt = $pdo->query("SELECT current_database(), current_user");
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo "❌ Erro PDO: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erro Geral: " . $e->getMessage() . "\n";
}
