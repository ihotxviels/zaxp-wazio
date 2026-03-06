<?php
require_once __DIR__ . '/config.php';

echo "<h1>🚀 MIGRATOR UAZAPI SAAS (POSTGRESQL)</h1>";
echo "Iniciando verificação de tabelas...<br>";

// 1. Criar Tabelas
try {
    $pdo = get_db_connection();

    // Tabela: Chips (Contingencia)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_chips (
            id SERIAL PRIMARY KEY,
            iccid VARCHAR(50) UNIQUE NOT NULL,
            number VARCHAR(30),
            status VARCHAR(20) DEFAULT 'ativo',
            ddd VARCHAR(5),
            added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_chips</b> validada e/ou criada.<br>";

    // Tabela: Financeiro (Transações)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_finance (
            id SERIAL PRIMARY KEY,
            transaction_type VARCHAR(20) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            description TEXT,
            status VARCHAR(20) DEFAULT 'pago',
            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_finance</b> validada e/ou criada.<br>";

    // Tabela: System Errors / Logs Mestre
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_system_logs (
            id SERIAL PRIMARY KEY,
            user_tag VARCHAR(100),
            action VARCHAR(100),
            details TEXT,
            ip_address VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_system_logs</b> validada e/ou criada.<br>";

    // Tabela: Instâncias (n8n Sync)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_instances (
            id SERIAL PRIMARY KEY,
            instance_name VARCHAR(100) UNIQUE NOT NULL,
            instance_token TEXT,
            status VARCHAR(50) DEFAULT 'disconnected',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_instances</b> validada e/ou criada.<br>";

    // Tabela: Etiquetas (Label Sync)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_labels (
            id SERIAL PRIMARY KEY,
            instance_name VARCHAR(100) NOT NULL,
            label_id VARCHAR(100) NOT NULL,
            label_name VARCHAR(255) NOT NULL,
            UNIQUE(instance_name, label_id)
        );
    ");
    echo "✅ Tabela <b>crm_labels</b> validada e/ou criada.<br>";

    // Tabela: Compradores (Marketing)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_buyers (
            id SERIAL PRIMARY KEY,
            phone_id VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) DEFAULT 'lead_pagador',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_buyers</b> validada e/ou criada.<br>";

    // Tabela: Remarketing (Fila de Disparo)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_remarketing (
            id SERIAL PRIMARY KEY,
            phone VARCHAR(50) UNIQUE NOT NULL,
            status VARCHAR(30) DEFAULT 'pendente',
            instance_name VARCHAR(100),
            tag_alvo VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_remarketing</b> validada e/ou criada.<br>";

} catch (Exception $e) {
    echo "❌ Erro ao rodar script de migração DDL: " . $e->getMessage() . "<br>";
    exit;
}

echo "<hr><h3>Migração do Legacy JSON para POSTGRESQL (Financeiro & Chips)</h3>";

// ==========================================
// 2. LER JSONs ANTIGOS E INSERTAR NO BANCO
// ==========================================

// Migrar Chips
$chipFile = __DIR__ . '/database/chipdata.json';
if (file_exists($chipFile)) {
    $cData = json_decode(file_get_contents($chipFile), true);
    if (!empty($cData['chips']) && is_array($cData['chips'])) {
        $stmt_chip = $pdo->prepare("INSERT INTO crm_chips (iccid, number, status, ddd, added_date) VALUES (?, ?, ?, ?, ?) ON CONFLICT DO NOTHING");
        $count = 0;
        foreach ($cData['chips'] as $c) {
            $status = strtolower($c['status'] ?? 'estoque');
            $status = ($status === 'inoperante') ? 'banido' : $status;

            $stmt_chip->execute([
                $c['iccid'] ?? null,
                $c['number'] ?? null,
                $status,
                $c['ddd'] ?? null,
                $c['added_date'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo "➡️ $count Chips migrados para o PostgreSQL.<br>";

        // Renomeia o Json pra não migrar 2x
        rename($chipFile, __DIR__ . '/database/chipdata_migrated.json');
    }
} else {
    echo "Nenhum arquivo JSON de Chips antigo encontrado (chipdata.json).<br>";
}


// Migrar Financeiro
$finFile = __DIR__ . '/database/finance_data.json';
if (file_exists($finFile)) {
    $fData = json_decode(file_get_contents($finFile), true);
    if (!empty($fData['transactions']) && is_array($fData['transactions'])) {
        $stmt_fin = $pdo->prepare("INSERT INTO crm_finance (transaction_type, amount, description, status, transaction_date) VALUES (?, ?, ?, ?, ?)");
        $count = 0;
        foreach ($fData['transactions'] as $t) {
            $type = strtolower($t['type'] ?? 'entrada');
            $val = floatval($t['amount'] ?? 0);
            $stmt_fin->execute([
                $type,
                $val,
                $t['description'] ?? 'Migração de Sistema JSON',
                'concluido',
                $t['date'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo "➡️ $count Transações Financeiras migradas para o PostgreSQL.<br>";

        // Renomeia o Json pra não migrar 2x
        rename($finFile, __DIR__ . '/database/finance_data_migrated.json');
    }
} else {
    echo "Nenhum arquivo JSON financeiro antigo encontrado (finance_data.json).<br>";
}

echo "<h2>Migração SQL concluída 100% com sucesso. O sistema agora lê os Dashboards através do PostgreSQL!</h2>";
?>