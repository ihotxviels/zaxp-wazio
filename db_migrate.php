<?php
/**
 * WAZIO - DB MIGRATION SCRIPT
 * Garante que todas as tabelas e colunas estão corretas.
 * Acesse este arquivo UMA VEZ via browser para aplicar.
 */
require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("<b style='color:red'>ERRO: Não foi possível conectar ao banco de dados.</b>");
}

echo "<style>body{font-family:monospace;background:#111;color:#bcfd49;padding:20px} .ok{color:#bcfd49} .err{color:#f87171} .info{color:#60a5fa}</style>";
echo "<h2>🔧 WAZIO DB MIGRATION</h2>";

$errors = [];
$ok = [];

/**
 * Helper: adiciona coluna se não existir
 */
function add_column_if_missing(PDO $pdo, string $table, string $col, string $type): void
{
    $check = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name=? AND column_name=?");
    $check->execute([$table, $col]);
    if (!$check->fetchColumn()) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$col} {$type}");
        echo "<div class='ok'>✅ Coluna <b>{$col}</b> adicionada em <b>{$table}</b></div>";
    } else {
        echo "<div class='info'>ℹ️ Coluna <b>{$col}</b> já existe em <b>{$table}</b></div>";
    }
}

try {
    // ══════════════════════════════════════════════
    // 1. crm_instances — tabela principal de instâncias
    // ══════════════════════════════════════════════
    echo "<h3>📦 crm_instances</h3>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_instances (
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
    )");
    echo "<div class='ok'>✅ crm_instances criada/verificada</div>";

    // Garante colunas que podem estar faltando em tabelas antigas
    add_column_if_missing($pdo, 'crm_instances', 'instance_token', 'TEXT DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'instance_number', 'VARCHAR(100) DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'profile_name', 'VARCHAR(255) DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'profile_picture_url', 'TEXT DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'server_url', 'TEXT DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'connected', 'SMALLINT DEFAULT 0');
    add_column_if_missing($pdo, 'crm_instances', 'webhook_enabled', 'SMALLINT DEFAULT 0');
    add_column_if_missing($pdo, 'crm_instances', 'webhook_url', 'TEXT DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'instance_hidden', 'SMALLINT DEFAULT 0');
    add_column_if_missing($pdo, 'crm_instances', 'tag', 'VARCHAR(100) DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'proxy_host', 'VARCHAR(255) DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'proxy_port', 'VARCHAR(20) DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'proxy_protocol', 'VARCHAR(20) DEFAULT \'http\'');
    add_column_if_missing($pdo, 'crm_instances', 'proxy_user', 'VARCHAR(255) DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'proxy_pass', 'VARCHAR(255) DEFAULT \'\'');
    add_column_if_missing($pdo, 'crm_instances', 'full_data', 'JSONB DEFAULT \'{}\'');
    add_column_if_missing($pdo, 'crm_instances', 'last_checked', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    add_column_if_missing($pdo, 'crm_instances', 'user_id', 'INT DEFAULT 1');

    // ══════════════════════════════════════════════
    // 2. crm_users
    // ══════════════════════════════════════════════
    echo "<h3>👤 crm_users</h3>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_users (
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
    )");
    echo "<div class='ok'>✅ crm_users criada/verificada</div>";
    add_column_if_missing($pdo, 'crm_users', 'instance_limits', 'INT DEFAULT 10');
    add_column_if_missing($pdo, 'crm_users', 'modulos', 'JSONB DEFAULT \'[]\'');
    add_column_if_missing($pdo, 'crm_users', 'hidden_instances', 'JSONB DEFAULT \'[]\'');

    // ══════════════════════════════════════════════
    // 3. crm_settings
    // ══════════════════════════════════════════════
    echo "<h3>⚙️ crm_settings</h3>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_settings (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        setting_key VARCHAR(255) NOT NULL,
        setting_value TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(username, setting_key)
    )");
    echo "<div class='ok'>✅ crm_settings criada/verificada</div>";

    // ══════════════════════════════════════════════
    // 4. crm_system_logs
    // ══════════════════════════════════════════════
    echo "<h3>📋 crm_system_logs</h3>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_system_logs (
        id SERIAL PRIMARY KEY,
        level VARCHAR(50) DEFAULT 'info',
        source VARCHAR(255) DEFAULT '',
        message TEXT DEFAULT '',
        context JSONB DEFAULT '{}',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='ok'>✅ crm_system_logs criada/verificada</div>";

    // ══════════════════════════════════════════════
    // 5. crm_contacts
    // ══════════════════════════════════════════════
    echo "<h3>📇 crm_contacts</h3>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_contacts (
        id SERIAL PRIMARY KEY,
        instance_name VARCHAR(255) NOT NULL,
        phone VARCHAR(100) NOT NULL,
        name VARCHAR(255) DEFAULT 'Cliente Novo',
        status VARCHAR(100) DEFAULT 'lead',
        tags JSONB DEFAULT '[]',
        raw_packet JSONB DEFAULT '{}',
        extra_data JSONB DEFAULT '{}',
        chatwclid TEXT,
        utm_source VARCHAR(255),
        utm_medium VARCHAR(255),
        utm_campaign VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(instance_name, phone)
    )");
    echo "<div class='ok'>✅ crm_contacts criada/verificada</div>";

    // ══════════════════════════════════════════════
    // 6. crm_chips
    // ══════════════════════════════════════════════
    echo "<h3>📱 crm_chips</h3>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_chips (
        id SERIAL PRIMARY KEY,
        user_id INT DEFAULT 1,
        nome VARCHAR(255) DEFAULT '',
        numero VARCHAR(50) DEFAULT '',
        instance_name VARCHAR(255) DEFAULT '',
        status VARCHAR(50) DEFAULT 'DISPONÍVEL',
        conexao VARCHAR(50) DEFAULT 'OFFLINE',
        funcao TEXT DEFAULT '',
        categoria TEXT DEFAULT '1',
        dispositivo TEXT DEFAULT '',
        index_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='ok'>✅ crm_chips criada/verificada</div>";
    add_column_if_missing($pdo, 'crm_chips', 'instance_name', 'VARCHAR(255) DEFAULT \'\'');

    // ══════════════════════════════════════════════
    // 7. crm_push_tokens
    // ══════════════════════════════════════════════
    echo "<h3>🔔 crm_push_tokens</h3>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_push_tokens (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        token TEXT NOT NULL,
        device_id TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(username, token)
    )");
    echo "<div class='ok'>✅ crm_push_tokens criada/verificada</div>";
    add_column_if_missing($pdo, 'crm_push_tokens', 'device_id', 'TEXT DEFAULT \'\'');

    // ══════════════════════════════════════════════
    // 8. Mostrar estado atual das colunas de crm_instances
    // ══════════════════════════════════════════════
    echo "<h3>🔍 Estado atual: crm_instances</h3>";
    $stmt = $pdo->query("SELECT column_name, data_type, column_default FROM information_schema.columns WHERE table_name='crm_instances' ORDER BY ordinal_position");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' style='border-color:#333;border-collapse:collapse;width:100%;margin-top:10px'>";
    echo "<tr style='background:#222'><th style='padding:6px 12px'>Coluna</th><th>Tipo</th><th>Default</th></tr>";
    foreach ($cols as $c) {
        echo "<tr><td style='padding:4px 12px;color:#bcfd49'>{$c['column_name']}</td><td style='color:#60a5fa'>{$c['data_type']}</td><td style='color:#888'>{$c['column_default']}</td></tr>";
    }
    echo "</table>";

    // ══════════════════════════════════════════════
    // 9. Mostrar estado atual das instancias no banco
    // ══════════════════════════════════════════════
    echo "<h3>📦 Instâncias no banco:</h3>";
    $stmt2 = $pdo->query("SELECT instance_name, instance_token, status, profile_name FROM crm_instances ORDER BY created_at DESC LIMIT 20");
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "<div class='err'>Nenhuma instância no banco ainda.</div>";
    } else {
        echo "<table border='1' style='border-color:#333;border-collapse:collapse'>";
        echo "<tr style='background:#222'><th style='padding:6px'>Nome</th><th>Token</th><th>Status</th><th>Profile</th></tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            echo "<td style='padding:4px 10px;color:#bcfd49'>{$r['instance_name']}</td>";
            echo "<td style='color:#888;font-size:11px'>" . substr($r['instance_token'], 0, 20) . "...</td>";
            echo "<td style='color:" . ($r['status'] === 'open' ? '#bcfd49' : '#f87171') . "'>{$r['status']}</td>";
            echo "<td style='color:#60a5fa'>{$r['profile_name']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<br><div class='ok' style='font-size:18px;font-weight:bold'>✅ MIGRAÇÃO CONCLUÍDA COM SUCESSO!</div>";

} catch (Exception $e) {
    echo "<div class='err'>❌ ERRO: " . $e->getMessage() . "</div>";
}
?>