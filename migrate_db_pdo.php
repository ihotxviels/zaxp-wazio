<?php
require_once __DIR__ . '/config.php';

try {
    // 1. Connect to both databases
    $pdo_source = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=criadordigital", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo_target = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=wazio", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "Connected to both databases.\n";

    // 2. Setup standard functions
    $pdo_target->exec("
        CREATE OR REPLACE FUNCTION update_updated_at_column()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ language 'plpgsql';
    ");

    $tables = [
        'crm_users' => "
            CREATE TABLE IF NOT EXISTS crm_users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name VARCHAR(255),
                role VARCHAR(20) DEFAULT 'user',
                is_active BOOLEAN DEFAULT true,
                instance_limits INTEGER DEFAULT 10,
                instances JSONB DEFAULT '[]',
                modulos JSONB DEFAULT '[]',
                hidden_instances JSONB DEFAULT '[]',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ",
        'crm_chips' => "
            CREATE TABLE IF NOT EXISTS crm_chips (
                id SERIAL PRIMARY KEY,
                user_id INTEGER,
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
        ",
        'crm_instances' => "
            CREATE TABLE IF NOT EXISTS crm_instances (
                id SERIAL PRIMARY KEY,
                user_id INTEGER DEFAULT 1,
                instance_name VARCHAR(100) NOT NULL UNIQUE,
                instance_token TEXT,
                instance_number VARCHAR(30),
                status VARCHAR(50) DEFAULT 'disconnected',
                connected BOOLEAN DEFAULT false,
                webhook_enabled BOOLEAN DEFAULT false,
                webhook_url TEXT,
                proxy_host TEXT,
                proxy_port TEXT,
                proxy_user TEXT,
                proxy_pass TEXT,
                proxy_protocol TEXT,
                last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                instance_hidden BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                tag VARCHAR(100) DEFAULT '',
                profile_name VARCHAR(255),
                profile_picture_url TEXT,
                server_url TEXT,
                full_data JSONB DEFAULT '{}'
            );
        ",
        'crm_contacts' => "
            CREATE TABLE IF NOT EXISTS crm_contacts (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL DEFAULT 1,
                instance_name VARCHAR(100) NOT NULL,
                phone VARCHAR(50) NOT NULL,
                chat_lid VARCHAR(100),
                name VARCHAR(255) DEFAULT 'Cliente Novo',
                status VARCHAR(50) DEFAULT 'lead',
                tags JSONB DEFAULT '[]',
                extra_data JSONB DEFAULT '{}',
                raw_packet JSONB DEFAULT '{}',
                chatwclid VARCHAR(255),
                ctwa_clid TEXT,
                utm_source VARCHAR(100),
                utm_medium VARCHAR(100),
                utm_campaign VARCHAR(100),
                utm_term VARCHAR(100),
                utm_content VARCHAR(100),
                conversion_app VARCHAR(50),
                conversion_source VARCHAR(100),
                ad_source_id VARCHAR(100),
                ad_source_url TEXT,
                ad_media_url TEXT,
                ad_thumbnail_url TEXT,
                ad_title TEXT,
                ad_original_image_url TEXT,
                ad_greeting_message TEXT,
                device_source VARCHAR(50),
                referencia_venda VARCHAR(255),
                last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(instance_name, phone)
            );
        ",
        'crm_messages' => "
            CREATE TABLE IF NOT EXISTS crm_messages (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL DEFAULT 1,
                contact_id INTEGER REFERENCES crm_contacts(id) ON DELETE CASCADE,
                message_id VARCHAR(255) UNIQUE,
                from_me BOOLEAN DEFAULT false,
                message_type VARCHAR(30) DEFAULT 'text',
                media_type VARCHAR(30),
                body TEXT,
                media_url TEXT,
                filename VARCHAR(255),
                status VARCHAR(30) DEFAULT 'delivered',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ",
        'crm_funnels' => "
            CREATE TABLE IF NOT EXISTS crm_funnels (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL DEFAULT 1,
                name VARCHAR(100) NOT NULL,
                status VARCHAR(30) DEFAULT 'active',
                nodes JSONB DEFAULT '[]',
                edges JSONB DEFAULT '[]',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ",
        'crm_funnel_progress' => "
            CREATE TABLE IF NOT EXISTS crm_funnel_progress (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL DEFAULT 1,
                contact_id INTEGER REFERENCES crm_contacts(id) ON DELETE CASCADE,
                instance_name VARCHAR(100),
                lead_phone VARCHAR(50),
                funnel_id INTEGER REFERENCES crm_funnels(id) ON DELETE CASCADE,
                current_node_id VARCHAR(100),
                variables JSONB DEFAULT '{}',
                status VARCHAR(30) DEFAULT 'running',
                next_step_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(instance_name, lead_phone)
            );
        ",
        'crm_labels' => "
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
        ",
        'crm_finance' => "
            CREATE TABLE IF NOT EXISTS crm_finance (
                id SERIAL PRIMARY KEY,
                user_id INTEGER,
                transaction_type VARCHAR(20) NOT NULL,
                category VARCHAR(50) DEFAULT 'venda',
                amount NUMERIC(15,2) NOT NULL,
                description TEXT,
                status VARCHAR(20) DEFAULT 'pago',
                transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ",
        'crm_checkout' => "
            CREATE TABLE IF NOT EXISTS crm_checkout (
                id SERIAL PRIMARY KEY,
                user_id INTEGER DEFAULT 1,
                instance_name VARCHAR(100),
                phone VARCHAR(50),
                method VARCHAR(20) DEFAULT 'pix',
                amount NUMERIC(15,2) DEFAULT 0,
                status VARCHAR(30) DEFAULT 'pending',
                payload JSONB DEFAULT '{}',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ",
        'crm_settings' => "
            CREATE TABLE IF NOT EXISTS crm_settings (
                id SERIAL PRIMARY KEY,
                username VARCHAR(100) DEFAULT 'admin',
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(username, setting_key)
            );
        ",
        'crm_system_logs' => "
            CREATE TABLE IF NOT EXISTS crm_system_logs (
                id SERIAL PRIMARY KEY,
                user_tag VARCHAR(100),
                action VARCHAR(100),
                details TEXT,
                ip_address VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                message TEXT
            );
        ",
        'crm_push_tokens' => "
            CREATE TABLE IF NOT EXISTS crm_push_tokens (
                id SERIAL PRIMARY KEY,
                username VARCHAR(100),
                token TEXT UNIQUE,
                device_id TEXT UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                label VARCHAR(100) DEFAULT 'Browser',
                platform VARCHAR(50) DEFAULT 'web'
            );
        ",
        'crm_remarketing' => "
            CREATE TABLE IF NOT EXISTS crm_remarketing (
                id SERIAL PRIMARY KEY,
                user_id INTEGER DEFAULT 1,
                instance_name VARCHAR(100),
                phone VARCHAR(50),
                name VARCHAR(255),
                status VARCHAR(50) DEFAULT 'pendente',
                tags JSONB DEFAULT '[]',
                scheduled_at TIMESTAMP,
                sent_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        "
    ];

    // Create tables in wazio
    foreach ($tables as $name => $sql) {
        $pdo_target->exec($sql);
        echo "Table {$name} created.\n";
    }

    // Add triggers for updated_at
    $trigger_tables = ['crm_users', 'crm_contacts', 'crm_finance'];
    foreach ($trigger_tables as $tt) {
        $pdo_target->exec("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'update_{$tt}_modtime') THEN
                    CREATE TRIGGER update_{$tt}_modtime
                    BEFORE UPDATE ON {$tt}
                    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
                END IF;
            END
            $$;
        ");
    }

    // Export and Import Data
    foreach (array_keys($tables) as $table) {
        $stmt = $pdo_source->query("SELECT * FROM {$table}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $columns = array_keys($rows[0]);
            $col_names = implode(', ', $columns);

            // Use named parameters instead of ? to avoid PDO regex clashes with Postgres jsonb ops
            $named_params = array_map(function ($col) {
                return ":" . $col;
            }, $columns);
            $placeholders = implode(', ', $named_params);

            $insert_sql = "INSERT INTO {$table} ({$col_names}) VALUES ({$placeholders}) ON CONFLICT DO NOTHING";
            $insert_stmt = $pdo_target->prepare($insert_sql);

            $pdo_target->beginTransaction();
            foreach ($rows as $row) {
                // Bind values explicitly, converting empty strings to null to satisfy strict PG types
                foreach ($row as $col => $val) {
                    if ($val === '') {
                        $insert_stmt->bindValue(":" . $col, null, PDO::PARAM_NULL);
                    } else if (is_bool($val)) {
                        $insert_stmt->bindValue(":" . $col, $val, PDO::PARAM_BOOL);
                    } else {
                        $insert_stmt->bindValue(":" . $col, $val);
                    }
                }
                $insert_stmt->execute();
            }
            $pdo_target->commit();
            echo "Migrated " . count($rows) . " rows for {$table}.\n";
        } else {
            echo "No data to migrate for {$table}.\n";
        }
    }

    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    if (isset($pdo_target) && $pdo_target->inTransaction()) {
        $pdo_target->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>