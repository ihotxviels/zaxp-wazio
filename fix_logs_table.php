<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("Erro conexao\n");

$sql = "ALTER TABLE crm_system_logs ADD COLUMN IF NOT EXISTS message TEXT";
$pdo->exec($sql);
echo "✅ Coluna message validada na tabela crm_system_logs.\n";
