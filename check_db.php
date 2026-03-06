<?php
/**
 * WAZIO - DB CHECK + AUTO MIGRATE
 * Diagnostica e corrige o banco automaticamente.
 */
require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("ERRO: Sem conexão com banco.");
}

echo "<style>body{font-family:monospace;background:#111;color:#bcfd49;padding:20px} .ok{color:#bcfd49} .err{color:#f87171} .info{color:#60a5fa} h3{color:#fff} table{border-collapse:collapse;width:100%} th,td{padding:5px 10px;border:1px solid #333;text-align:left}</style>";
echo "<h2>🔧 WAZIO DB DIAGNOSTICS + AUTO-FIX</h2>";

function col_exists(PDO $pdo, string $table, string $col): bool
{
    $s = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name=? AND column_name=?");
    $s->execute([$table, $col]);
    return (bool) $s->fetchColumn();
}

function ensure_col(PDO $pdo, string $table, string $col, string $type): void
{
    if (!col_exists($pdo, $table, $col)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$col} {$type}");
        echo "<div class='ok'>✅ Adicionado: <b>{$table}.{$col}</b></div>";
    }
}

// === CRIAR TABELAS ===
$tables = [
    'crm_instances' => "CREATE TABLE IF NOT EXISTS crm_instances (
        id SERIAL PRIMARY KEY,
        user_id INT DEFAULT 1,
        instance_name VARCHAR(255) UNIQUE NOT NULL,
        instance_token TEXT DEFAULT '',
        instance_number VARCHAR(100) DEFAULT '',
        profile_name VARCHAR(255) DEFAULT '',
        profile_picture_url TEXT DEFAULT '',
        server_url TEXT DEFAULT '',
        status VARCHAR(50) DEFAULT 'disconnected',
        connected SMALLINT DEFAULT 0,
        webhook_enabled SMALLINT DEFAULT 0,
        webhook_url TEXT DEFAULT '',
        instance_hidden SMALLINT DEFAULT 0,
        tag VARCHAR(100) DEFAULT '',
        proxy_host VARCHAR(255) DEFAULT '',
        proxy_port VARCHAR(20) DEFAULT '',
        proxy_protocol VARCHAR(20) DEFAULT 'http',
        proxy_user VARCHAR(255) DEFAULT '',
        proxy_pass VARCHAR(255) DEFAULT '',
        full_data JSONB DEFAULT '{}',
        last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'crm_users' => "CREATE TABLE IF NOT EXISTS crm_users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        full_name VARCHAR(255) DEFAULT '',
        role VARCHAR(50) DEFAULT 'user',
        is_active BOOLEAN DEFAULT true,
        instance_limits INT DEFAULT 10,
        instances JSONB DEFAULT '[]',
        modulos JSONB DEFAULT '[]',
        hidden_instances JSONB DEFAULT '[]',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'crm_settings' => "CREATE TABLE IF NOT EXISTS crm_settings (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        setting_key VARCHAR(255) NOT NULL,
        setting_value TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(username, setting_key)
    )",
    'crm_system_logs' => "CREATE TABLE IF NOT EXISTS crm_system_logs (
        id SERIAL PRIMARY KEY, level VARCHAR(50) DEFAULT 'info',
        source VARCHAR(255) DEFAULT '', message TEXT DEFAULT '',
        context JSONB DEFAULT '{}', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'crm_push_tokens' => "CREATE TABLE IF NOT EXISTS crm_push_tokens (
        id SERIAL PRIMARY KEY, username VARCHAR(100) NOT NULL,
        token TEXT NOT NULL, device_id TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE(username, token)
    )",
    'crm_chips' => "CREATE TABLE IF NOT EXISTS crm_chips (
        id SERIAL PRIMARY KEY, user_id INT DEFAULT 1,
        nome VARCHAR(255) DEFAULT '', numero VARCHAR(50) DEFAULT '',
        instance_name VARCHAR(255) DEFAULT '', status VARCHAR(50) DEFAULT 'DISPONÍVEL',
        conexao VARCHAR(50) DEFAULT 'OFFLINE', funcao TEXT DEFAULT '',
        categoria TEXT DEFAULT '1', dispositivo TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'crm_chats' => "CREATE TABLE IF NOT EXISTS crm_chats (
        id SERIAL PRIMARY KEY,
        instance_name VARCHAR(255) NOT NULL,
        remote_jid VARCHAR(255) NOT NULL,
        name VARCHAR(255) DEFAULT '',
        unread_count INT DEFAULT 0,
        last_message TEXT DEFAULT '',
        last_message_timestamp TIMESTAMP,
        profile_picture TEXT DEFAULT '',
        archive BOOLEAN DEFAULT false,
        is_group BOOLEAN DEFAULT false,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(instance_name, remote_jid)
    )",
    'crm_messages' => "CREATE TABLE IF NOT EXISTS crm_messages (
        id SERIAL PRIMARY KEY,
        instance_name VARCHAR(255) NOT NULL,
        remote_jid VARCHAR(255) NOT NULL,
        message_id VARCHAR(255) NOT NULL,
        from_me BOOLEAN DEFAULT false,
        content JSONB DEFAULT '{}',
        status VARCHAR(50) DEFAULT 'sent',
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(instance_name, message_id)
    )"
];

echo "<h3>📦 Criar/verificar tabelas:</h3>";
foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "<div class='ok'>✅ {$name}</div>";
    } catch (Exception $e) {
        echo "<div class='err'>❌ {$name}: " . $e->getMessage() . "</div>";
    }
}

// === GARANTIR COLUNAS EM crm_instances ===
echo "<h3>🔧 Garantir colunas em crm_instances:</h3>";
$cols = [
    'instance_token' => 'TEXT DEFAULT \'\'',
    'instance_number' => 'VARCHAR(100) DEFAULT \'\'',
    'profile_name' => 'VARCHAR(255) DEFAULT \'\'',
    'profile_picture_url' => 'TEXT DEFAULT \'\'',
    'server_url' => 'TEXT DEFAULT \'\'',
    'status' => 'VARCHAR(50) DEFAULT \'disconnected\'',
    'connected' => 'SMALLINT DEFAULT 0',
    'webhook_enabled' => 'SMALLINT DEFAULT 0',
    'webhook_url' => 'TEXT DEFAULT \'\'',
    'instance_hidden' => 'SMALLINT DEFAULT 0',
    'tag' => 'VARCHAR(100) DEFAULT \'\'',
    'proxy_host' => 'VARCHAR(255) DEFAULT \'\'',
    'proxy_port' => 'VARCHAR(20) DEFAULT \'\'',
    'proxy_protocol' => 'VARCHAR(20) DEFAULT \'http\'',
    'proxy_user' => 'VARCHAR(255) DEFAULT \'\'',
    'proxy_pass' => 'VARCHAR(255) DEFAULT \'\'',
    'full_data' => 'JSONB DEFAULT \'{}\'',
    'last_checked' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'user_id' => 'INT DEFAULT 1',
];
foreach ($cols as $col => $type) {
    try {
        ensure_col($pdo, 'crm_instances', $col, $type);
    } catch (Exception $e) {
        echo "<div class='err'>❌ {$col}: " . $e->getMessage() . "</div>";
    }
}

// === OUTROS FIXES ===
try {
    ensure_col($pdo, 'crm_users', 'instance_limits', 'INT DEFAULT 10');
} catch (Exception $e) {
}
try {
    ensure_col($pdo, 'crm_users', 'modulos', 'JSONB DEFAULT \'[]\'');
} catch (Exception $e) {
}
try {
    ensure_col($pdo, 'crm_users', 'hidden_instances', 'JSONB DEFAULT \'[]\'');
} catch (Exception $e) {
}
try {
    ensure_col($pdo, 'crm_push_tokens', 'device_id', 'TEXT DEFAULT \'\'');
} catch (Exception $e) {
}
try {
    ensure_col($pdo, 'crm_chips', 'instance_name', 'VARCHAR(255) DEFAULT \'\'');
} catch (Exception $e) {
}

// === MOSTRAR ESTADO ATUAL ===
echo "<h3>📋 Colunas de crm_instances:</h3>";
$stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name='crm_instances' ORDER BY ordinal_position");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>Coluna</th><th>Tipo</th></tr>";
foreach ($cols as $c) {
    echo "<tr><td class='ok'>{$c['column_name']}</td><td class='info'>{$c['data_type']}</td></tr>";
}
echo "</table>";

// === INSTÂNCIAS NO BANCO ===
echo "<h3>📱 Instâncias no banco:</h3>";
try {
    $stmt = $pdo->query("SELECT instance_name, instance_token, status, profile_name, profile_picture_url FROM crm_instances ORDER BY created_at DESC LIMIT 30");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "<div class='err'>Nenhuma instância salva no banco ainda.</div>";
    } else {
        echo "<table><tr><th>Nome</th><th>Token</th><th>Status</th><th>Profile</th><th>Foto?</th></tr>";
        foreach ($rows as $r) {
            $tokenShort = substr($r['instance_token'], 0, 12) . '...';
            $hasFoto = !empty($r['profile_picture_url']) ? "✅" : "❌";
            echo "<tr><td class='ok'>{$r['instance_name']}</td><td class='info'>{$tokenShort}</td>";
            echo "<td style='color:" . ($r['status'] === 'open' ? '#bcfd49' : '#f87171') . "'>{$r['status']}</td>";
            echo "<td>{$r['profile_name']}</td><td>{$hasFoto}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div class='err'>Erro ao ler instâncias: " . $e->getMessage() . "</div>";
}

echo "<br><div class='ok' style='font-size:20px'>✅ DB MIGRAÇÃO CONCLUÍDA!</div>";
echo "<div class='info' style='margin-top:10px'>Agora acesse seu dashboard e teste novamente.</div>";
?>