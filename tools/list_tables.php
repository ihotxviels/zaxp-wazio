<?php
// list_tables.php
require_once 'config.php';
$pdo = get_db_connection();
if ($pdo) {
    echo "📊 Tabelas no Banco:\n";
    $stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['tablename'] . "\n";
    }
}
