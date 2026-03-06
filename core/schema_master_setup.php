<?php
require_once __DIR__ . '/../config.php';

// Allow direct browser access during setup
if (session_status() !== PHP_SESSION_NONE) {
    $user = usuario_logado();
    if ($user && $user['role'] !== 'admin') {
        die('<b style="color:red">Acesso negado.</b>');
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>WAZ.IO — DB Setup</title>
    <style>
        body {
            font-family: monospace;
            background: #080c09;
            color: #bcfd49;
            padding: 30px;
        }

        h1 {
            border-bottom: 1px solid #333;
            padding-bottom: 12px;
        }

        .ok {
            color: #bcfd49;
        }

        .warn {
            color: #eab308;
        }

        .err {
            color: #f87171;
        }

        .section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #1a2a1a;
            border-radius: 8px;
            background: rgba(188, 253, 73, 0.03);
        }

        a.btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #bcfd49;
            color: #080c09;
            font-weight: bold;
            border-radius: 6px;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <h1>🚀 WAZ.IO — MASTER DB MIGRATION</h1>
    <p style="color:#888">Idempotente: seguro para executar múltiplas vezes.</p>
    <?php

    $logLines = [];
    $errors = [];

    function dbLog($msg, $type = 'ok')
    {
        global $logLines;
        $logLines[] = ['msg' => $msg, 'type' => $type];
        $cls = $type === 'ok' ? 'ok' : ($type === 'warn' ? 'warn' : 'err');
        echo "<div class=\"$cls\">$msg</div>";
        flush();
        ob_flush();
    }

    function tryExec($pdo, $sql, $desc)
    {
        try {
            $pdo->exec($sql);
            dbLog("✅ $desc");
            return true;
        } catch (Exception $e) {
            dbLog("⚠️ $desc — " . $e->getMessage(), 'warn');
            return false;
        }
    }

    try {
        $pdo = get_db_connection();
        if (!$pdo)
            throw new Exception("Não foi possível conectar ao PostgreSQL. Verifique config.php");
        dbLog("🔌 Conexão PostgreSQL estabelecida com sucesso!");

        // ── FUNÇÃO DE TRIGGER PARA updated_at ──────────────────────────────────
        echo "<div class=\"section\"><b>📦 Funções e Triggers</b><br>";
        tryExec($pdo, "
        CREATE OR REPLACE FUNCTION update_updated_at_column()
        RETURNS TRIGGER AS \$\$
        BEGIN NEW.updated_at = CURRENT_TIMESTAMP; RETURN NEW; END;
        \$\$ language 'plpgsql';
    ", "Função update_updated_at_column");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>📋 Tabela: crm_instances</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_instances (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            instance_name VARCHAR(100) UNIQUE NOT NULL,
            instance_token TEXT NOT NULL DEFAULT '',
            instance_number VARCHAR(30),
            profile_name VARCHAR(255),
            profile_picture_url TEXT,
            server_url TEXT,
            status VARCHAR(50) DEFAULT 'disconnected',
            connected BOOLEAN DEFAULT false,
            webhook_enabled BOOLEAN DEFAULT false,
            webhook_url TEXT,
            instance_hidden BOOLEAN DEFAULT false,
            tag VARCHAR(100) DEFAULT '',
            proxy_host VARCHAR(255) DEFAULT '',
            proxy_port VARCHAR(20) DEFAULT '',
            proxy_user VARCHAR(100) DEFAULT '',
            proxy_pass VARCHAR(255) DEFAULT '',
            proxy_protocol VARCHAR(20) DEFAULT 'http',
            full_data JSONB DEFAULT '{}'::jsonb,
            last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_instances");
        // Adiciona colunas faltantes (idempotente)
        $colsInstances = [
            ['instance_hidden', 'BOOLEAN DEFAULT false'],
            ['tag', 'VARCHAR(100) DEFAULT \'\''],
            ['proxy_host', 'VARCHAR(255) DEFAULT \'\''],
            ['proxy_port', 'VARCHAR(20) DEFAULT \'\''],
            ['proxy_user', 'VARCHAR(100) DEFAULT \'\''],
            ['proxy_pass', 'VARCHAR(255) DEFAULT \'\''],
            ['proxy_protocol', 'VARCHAR(20) DEFAULT \'http\''],
            ['webhook_url', 'TEXT'],
            ['webhook_enabled', 'BOOLEAN DEFAULT false'],
            ['profile_name', 'VARCHAR(255)'],
            ['profile_picture_url', 'TEXT'],
            ['server_url', 'TEXT'],
            ['full_data', 'JSONB DEFAULT \'{}\'::jsonb'],
            ['last_checked', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ];
        foreach ($colsInstances as [$col, $def]) {
            tryExec($pdo, "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS $col $def", "Coluna crm_instances.$col");
        }
        tryExec($pdo, "DROP TRIGGER IF EXISTS update_instances_modtime ON crm_instances;", "Drop trigger instances");
        tryExec($pdo, "CREATE TRIGGER update_instances_modtime BEFORE UPDATE ON crm_instances FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();", "Trigger updated_at instances");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>👤 Tabela: crm_users</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            full_name VARCHAR(255) DEFAULT '',
            role VARCHAR(30) DEFAULT 'user',
            is_active BOOLEAN DEFAULT true,
            instance_limits INTEGER DEFAULT 10,
            instances JSONB DEFAULT '[]'::jsonb,
            modulos JSONB DEFAULT '[]'::jsonb,
            hidden_instances JSONB DEFAULT '[]'::jsonb,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_users");
        $colsUsers = [
            ['modulos', 'JSONB DEFAULT \'[]\'::jsonb'],
            ['hidden_instances', 'JSONB DEFAULT \'[]\'::jsonb'],
            ['instance_limits', 'INTEGER DEFAULT 10'],
            ['full_name', 'VARCHAR(255) DEFAULT \'\''],
        ];
        foreach ($colsUsers as [$col, $def]) {
            tryExec($pdo, "ALTER TABLE crm_users ADD COLUMN IF NOT EXISTS $col $def", "Coluna crm_users.$col");
        }

        // Migra admin do JSON para o banco, se não existir
        try {
            $stmtCheck = $pdo->query("SELECT COUNT(*) FROM crm_users WHERE username = 'admin'");
            if ($stmtCheck->fetchColumn() == 0) {
                $usersFile = __DIR__ . '/../core/database/users.json';
                if (file_exists($usersFile)) {
                    $users = json_decode(file_get_contents($usersFile), true) ?? [];
                    foreach ($users as $uname => $u) {
                        $stmtIns = $pdo->prepare("INSERT INTO crm_users (username, password_hash, full_name, role, is_active, instance_limits, instances, modulos, hidden_instances)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON CONFLICT (username) DO NOTHING");
                        $stmtIns->execute([
                            $uname,
                            $u['password'] ?? '',
                            $u['nome'] ?? $uname,
                            $u['role'] ?? 'user',
                            $u['ativo'] ?? true,
                            $u['limite_instancias'] ?? 10,
                            json_encode($u['instancias'] ?? []),
                            json_encode($u['modulos'] ?? []),
                            json_encode($u['hidden_instances'] ?? [])
                        ]);
                    }
                    dbLog("✅ Usuários migrados do JSON para crm_users");
                } else {
                    dbLog("⚠️ users.json não encontrado — crie o admin manualmente", 'warn');
                }
            } else {
                dbLog("ℹ️ Admin já existe em crm_users");
            }
        } catch (Exception $e) {
            dbLog("⚠️ Migração de usuários: " . $e->getMessage(), 'warn');
        }

        tryExec($pdo, "DROP TRIGGER IF EXISTS update_users_modtime ON crm_users;", "Drop trigger users");
        tryExec($pdo, "CREATE TRIGGER update_users_modtime BEFORE UPDATE ON crm_users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();", "Trigger updated_at users");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>📞 Tabela: crm_contacts</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_contacts (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            instance_name VARCHAR(100) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            chat_lid VARCHAR(100),
            name VARCHAR(255) DEFAULT 'Cliente Novo',
            status VARCHAR(50) DEFAULT 'lead',
            tags JSONB DEFAULT '[]'::jsonb,
            extra_data JSONB DEFAULT '{}'::jsonb,
            raw_packet JSONB DEFAULT '{}'::jsonb,
            chatwclid VARCHAR(255) NULL,
            ctwa_clid TEXT NULL,
            utm_source VARCHAR(100) NULL,
            utm_medium VARCHAR(100) NULL,
            utm_campaign VARCHAR(100) NULL,
            utm_term VARCHAR(100) NULL,
            utm_content VARCHAR(100) NULL,
            referencia_venda VARCHAR(255) NULL,
            last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(instance_name, phone)
        );
    ", "Criada crm_contacts");
        tryExec($pdo, "DROP TRIGGER IF EXISTS update_contacts_modtime ON crm_contacts;", "Drop trigger contacts");
        tryExec($pdo, "CREATE TRIGGER update_contacts_modtime BEFORE UPDATE ON crm_contacts FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();", "Trigger updated_at contacts");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>💬 Tabela: crm_messages</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_messages (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            contact_id INTEGER,
            message_id VARCHAR(255) UNIQUE,
            instance_name VARCHAR(100),
            from_me BOOLEAN DEFAULT false,
            message_type VARCHAR(30) DEFAULT 'text',
            media_type VARCHAR(30) NULL,
            body TEXT,
            media_url TEXT,
            filename VARCHAR(255),
            status VARCHAR(30) DEFAULT 'delivered',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_messages");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>🔄 Tabela: crm_funnels</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_funnels (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            name VARCHAR(100) NOT NULL,
            status VARCHAR(30) DEFAULT 'active',
            nodes JSONB DEFAULT '[]'::jsonb,
            edges JSONB DEFAULT '[]'::jsonb,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_funnels");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>📊 Tabela: crm_funnel_progress</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_funnel_progress (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            contact_id INTEGER,
            instance_name VARCHAR(100),
            lead_phone VARCHAR(50),
            funnel_id INTEGER,
            current_node_id VARCHAR(100),
            variables JSONB DEFAULT '{}'::jsonb,
            status VARCHAR(30) DEFAULT 'running',
            next_step_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(instance_name, lead_phone)
        );
    ", "Criada crm_funnel_progress");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>💰 Tabela: crm_finance</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_finance (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            transaction_type VARCHAR(20) NOT NULL DEFAULT 'entrada',
            category VARCHAR(50) DEFAULT 'venda',
            amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            description TEXT,
            status VARCHAR(30) DEFAULT 'pago',
            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_finance");
        $colsFinance = [
            ['status', 'VARCHAR(30) DEFAULT \'pago\''],
            ['category', 'VARCHAR(50) DEFAULT \'venda\''],
        ];
        foreach ($colsFinance as [$col, $def]) {
            tryExec($pdo, "ALTER TABLE crm_finance ADD COLUMN IF NOT EXISTS $col $def", "Coluna crm_finance.$col");
        }
        tryExec($pdo, "DROP TRIGGER IF EXISTS update_finance_modtime ON crm_finance;", "Drop trigger finance");
        tryExec($pdo, "CREATE TRIGGER update_finance_modtime BEFORE UPDATE ON crm_finance FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();", "Trigger updated_at finance");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>🏷️ Tabela: crm_labels</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_labels (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            instance_name VARCHAR(100) NOT NULL,
            label_id VARCHAR(100) NOT NULL,
            label_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(instance_name, label_id)
        );
    ", "Criada crm_labels");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>🔔 Tabela: crm_push_tokens</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_push_tokens (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) NOT NULL DEFAULT 'admin',
            device_id VARCHAR(500) UNIQUE NOT NULL,
            token TEXT,
            label VARCHAR(100) DEFAULT 'Browser',
            platform VARCHAR(50) DEFAULT 'web',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_push_tokens");
        $colsPushTokens = [
            ['username', 'VARCHAR(100) NOT NULL DEFAULT \'admin\''],
            ['device_id', 'VARCHAR(500)'],
            ['label', 'VARCHAR(100) DEFAULT \'Browser\''],
            ['platform', 'VARCHAR(50) DEFAULT \'web\''],
        ];
        foreach ($colsPushTokens as [$col, $def]) {
            tryExec($pdo, "ALTER TABLE crm_push_tokens ADD COLUMN IF NOT EXISTS $col $def", "Coluna crm_push_tokens.$col");
        }
        // Garante unique constraint em device_id
        tryExec($pdo, "CREATE UNIQUE INDEX IF NOT EXISTS crm_push_tokens_device_id_key ON crm_push_tokens(device_id)", "Unique device_id");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>⚙️ Tabela: crm_settings</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_settings (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) DEFAULT 'admin',
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(username, setting_key)
        );
    ", "Criada crm_settings");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>📱 Tabela: crm_chips</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_chips (
            id SERIAL PRIMARY KEY,
            user_id INTEGER DEFAULT 1,
            nome VARCHAR(100),
            numero VARCHAR(30),
            status VARCHAR(50) DEFAULT 'DISPONÍVEL',
            conexao VARCHAR(30) DEFAULT 'OFFLINE',
            funcao VARCHAR(100),
            categoria VARCHAR(30) DEFAULT '1',
            dispositivo VARCHAR(100),
            index_id VARCHAR(10) DEFAULT '00',
            instance_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_chips");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>📣 Tabela: crm_remarketing</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_remarketing (
            id SERIAL PRIMARY KEY,
            user_id INTEGER DEFAULT 1,
            instance_name VARCHAR(100),
            phone VARCHAR(50),
            name VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pendente',
            tags JSONB DEFAULT '[]'::jsonb,
            scheduled_at TIMESTAMP NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_remarketing");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>🛒 Tabela: crm_checkout</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_checkout (
            id SERIAL PRIMARY KEY,
            user_id INTEGER DEFAULT 1,
            instance_name VARCHAR(100),
            phone VARCHAR(50),
            method VARCHAR(20) DEFAULT 'pix',
            amount DECIMAL(15,2) DEFAULT 0,
            status VARCHAR(30) DEFAULT 'pending',
            payload JSONB DEFAULT '{}'::jsonb,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_checkout");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        echo "<div class=\"section\"><b>🗄️ Tabela: crm_system_logs</b><br>";
        tryExec($pdo, "
        CREATE TABLE IF NOT EXISTS crm_system_logs (
            id SERIAL PRIMARY KEY,
            level VARCHAR(20) DEFAULT 'info',
            source VARCHAR(100) DEFAULT 'system',
            message TEXT,
            context JSONB DEFAULT '{}'::jsonb,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ", "Criada crm_system_logs");
        echo "</div>";

        // ─────────────────────────────────────────────────────────────────────────
        // ESTATÍSTICAS FINAIS
        echo "<div class=\"section\">";
        echo "<b>📊 Resumo das Tabelas no Banco:</b><br><br>";
        $tabelas = [
            'crm_instances',
            'crm_users',
            'crm_contacts',
            'crm_messages',
            'crm_funnels',
            'crm_funnel_progress',
            'crm_finance',
            'crm_labels',
            'crm_push_tokens',
            'crm_settings',
            'crm_chips',
            'crm_remarketing',
            'crm_checkout',
            'crm_system_logs'
        ];
        foreach ($tabelas as $t) {
            try {
                $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
                $s = $pdo->query("SELECT pg_size_pretty(pg_total_relation_size('$t'))")->fetchColumn();
                echo "<div class=\"ok\">✅ <b>$t</b>: $c registros ($s)</div>";
            } catch (Exception $e) {
                echo "<div class=\"err\">❌ <b>$t</b>: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        echo "</div>";

        echo "<br><h2 style='color:#bcfd49'>🌟 MIGRAÇÃO COMPLETA COM SUCESSO!</h2>";
        echo "<p style='color:#888'>Todas as tabelas e colunas foram criadas/atualizadas.</p>";
        echo "<a href='/wazio/dashboard' class='btn'>🏠 Ir para o Dashboard</a>";
        echo "<a href='/wazio/database' class='btn' style='margin-left:12px; background:#1a2a1a; color:#bcfd49; border:1px solid #bcfd49;'>📊 Ver Database</a>";

    } catch (Exception $e) {
        echo "<br><b class='err'>❌ ERRO CRÍTICO: " . htmlspecialchars($e->getMessage()) . "</b>";
    }
    ?>
</body>

</html>