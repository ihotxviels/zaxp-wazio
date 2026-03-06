<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("❌ FALHA NA CONEXÃO DB\n");

echo "🚀 INICIANDO DIAGNÓSTICO FINAL DE BACKEND WAZIO\n";
echo "--------------------------------------------------\n";

// 1. Verificar Colunas Críticas
$tables = [
    'crm_instances' => ['profile_name', 'connected', 'proxy_host', 'webhook_url'],
    'crm_users' => ['password_hash', 'role'],
    'crm_system_logs' => ['message']
];

foreach ($tables as $table => $cols) {
    echo "Checking Table: $table... ";
    try {
        $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$table'");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff($cols, $existing);
        if (empty($missing))
            echo "✅ OK\n";
        else
            echo "❌ FALTANDO: " . implode(', ', $missing) . "\n";
    } catch (Exception $e) {
        echo "❌ ERRO: " . $e->getMessage() . "\n";
    }
}

// 2. Verificar Tipagem Booleana (Testar Inserção)
echo "Testing Boolean Casting... ";
try {
    $stmt = $pdo->prepare("UPDATE crm_instances SET connected = ? WHERE id = -1"); // Test update logic
    $stmt->execute([(int) true]);
    echo "✅ OK (Cast integer safe)\n";
} catch (Exception $e) {
    echo "❌ FALHA: " . $e->getMessage() . "\n";
}

// 3. Verificar Ações de API (Simular chamadas internas se necessário)
echo "Checking API Logic Structure... ";
$apiContent = file_get_contents(__DIR__ . '/app/Controllers/ApiController.php');
$requiredActions = ['set_proxy', 'set_webhook', 'tag_set', 'instance_status'];
$missingActions = [];
foreach ($requiredActions as $act) {
    if (strpos($apiContent, "case '$act'") === false)
        $missingActions[] = $act;
}
if (empty($missingActions))
    echo "✅ OK\n";
else
    echo "❌ FALTANDO CASOS: " . implode(', ', $missingActions) . "\n";

echo "--------------------------------------------------\n";
echo "🏁 DIAGNÓSTICO CONCLUÍDO.\n";
