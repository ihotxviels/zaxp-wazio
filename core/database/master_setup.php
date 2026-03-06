<?php
/**
 * 🚀 WAZIO MASTER DATABASE SETUP
 * Criação consolidada de todas as tabelas crm_ para o ecossistema Wazio.
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<body style='background:#080c09; color:#fff; font-family:sans-serif; padding:40px;'>";
echo "<h1 style='color:#bcfd49;'>🏗️ WAZIO MASTER SCHEMA SETUP</h1>";
echo "<p>Iniciando criação da infraestrutura PostgreSQL...</p><hr style='border-color:#1a2a1d;'>";

try {
    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception("Falha na conexão com o banco de dados. Verifique o config.php");
    }

    // 0. TRIGGER FUNCTION
    $pdo->exec("
        CREATE OR REPLACE FUNCTION update_updated_at_column()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ language 'plpgsql';
    ");
    echo "✅ Função de Trigger validada.<br>";

    // 1. INSTÂNCIAS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_instances (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            instance_name VARCHAR(100) UNIQUE NOT NULL,
            instance_token TEXT NOT NULL,
            instance_number VARCHAR(30),
            status VARCHAR(50) DEFAULT 'disconnected',
            connected BOOLEAN DEFAULT false,
            webhook_url TEXT,
            proxy_host VARCHAR(255),
            proxy_port VARCHAR(10),
            proxy_user VARCHAR(100),
            proxy_pass VARCHAR(100),
            proxy_protocol VARCHAR(20) DEFAULT 'http',
            webhook_enabled BOOLEAN DEFAULT true,
            instance_hidden BOOLEAN DEFAULT false,
            last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_instances</b> ok.<br>";

    // 2. CONTATOS (CRM + AD TRACKING)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_contacts (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            instance_name VARCHAR(100) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            chat_lid VARCHAR(100),
            name VARCHAR(255) DEFAULT 'Cliente Novo',
            status VARCHAR(50) DEFAULT 'lead',
            tags JSONB DEFAULT '[]'::jsonb,
            chatwclid VARCHAR(255) NULL,
            ctwa_clid TEXT NULL,
            utm_source VARCHAR(100) NULL,
            utm_medium VARCHAR(100) NULL,
            utm_campaign VARCHAR(100) NULL,
            utm_term VARCHAR(100) NULL,
            utm_content VARCHAR(100) NULL,
            conversion_app VARCHAR(50) NULL,
            conversion_source VARCHAR(100) NULL,
            ad_source_id VARCHAR(100) NULL,
            ad_source_url TEXT NULL,
            ad_media_url TEXT NULL,
            ad_thumbnail_url TEXT NULL,
            ad_title TEXT NULL,
            ad_original_image_url TEXT NULL,
            ad_greeting_message TEXT NULL,
            device_source VARCHAR(50) NULL,
            referencia_venda VARCHAR(255) NULL, 
            last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(instance_name, phone)
        );
    ");
    echo "✅ Tabela <b>crm_contacts</b> ok.<br>";

    // 3. MENSAGENS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_messages (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            contact_id INTEGER REFERENCES crm_contacts(id) ON DELETE CASCADE,
            message_id VARCHAR(255) UNIQUE, 
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
    ");
    echo "✅ Tabela <b>crm_messages</b> ok.<br>";

    // 4. FUNIS (Flow Engine)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_funnels (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            instance_name VARCHAR(100) NULL,
            name VARCHAR(100) NOT NULL,
            status VARCHAR(30) DEFAULT 'active',
            nodes JSONB DEFAULT '[]'::jsonb,
            edges JSONB DEFAULT '[]'::jsonb,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_funnels</b> ok.<br>";

    // 5. PROGRESSO DO FLUXO
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_funnel_progress (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            contact_id INTEGER REFERENCES crm_contacts(id) ON DELETE CASCADE,
            funnel_id INTEGER REFERENCES crm_funnels(id) ON DELETE CASCADE,
            current_node_id VARCHAR(100),
            variables JSONB DEFAULT '{}'::jsonb,
            status VARCHAR(30) DEFAULT 'running',
            next_step_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(contact_id, funnel_id)
        );
    ");
    echo "✅ Tabela <b>crm_funnel_progress</b> ok.<br>";

    // 6. FINANCEIRO
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_finance (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NULL,
            transaction_type VARCHAR(20) NOT NULL,
            category VARCHAR(50) DEFAULT 'venda',
            amount DECIMAL(15,2) NOT NULL,
            description TEXT,
            status VARCHAR(20) DEFAULT 'pago',
            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_finance</b> ok.<br>";

    // 7. ETIQUETAS
    $pdo->exec("
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
    ");
    echo "✅ Tabela <b>crm_labels</b> ok.<br>";

    // 8. CHIPS (Contingencia)
    $pdo->exec("
      -- 8. CHIPS
CREATE TABLE IF NOT EXISTS crm_chips (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NULL,
    instance_name VARCHAR(100) NULL,
    nome VARCHAR(100),
    numero VARCHAR(30),
    status VARCHAR(50) DEFAULT 'DISPONÍVEL',
    conexao VARCHAR(30) DEFAULT 'OFFLINE',
    funcao VARCHAR(100),
    categoria VARCHAR(30) DEFAULT '1',
    dispositivo VARCHAR(100),
    index_id VARCHAR(10) DEFAULT '00',
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
        ");
    echo "✅ Tabela <b>crm_chips</b> ok.<br>";

    // 9. REMARKETING
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
    echo "✅ Tabela <b>crm_remarketing</b> ok.<br>";

    // 10. USUÁRIOS (Sessões)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            full_name VARCHAR(100),
            role VARCHAR(20) DEFAULT 'user',
            is_active BOOLEAN DEFAULT true,
            permissions JSONB DEFAULT '{}'::jsonb,
            instance_limits INTEGER DEFAULT 10,
            instances JSONB DEFAULT '[]'::jsonb,
            modulos JSONB DEFAULT '[]'::jsonb,
            hidden_instances JSONB DEFAULT '[]'::jsonb,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_users</b> ok.<br>";

    // 11. CONFIGURAÇÕES (Módulos & Preferências)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_settings (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value JSONB DEFAULT '{}'::jsonb,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(username, setting_key)
        );
        
        -- Aplicar trigger de updated_at
        DROP TRIGGER IF EXISTS trg_update_crm_settings ON crm_settings;
        CREATE TRIGGER trg_update_crm_settings BEFORE UPDATE ON crm_settings
        FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
    ");
    echo "✅ Tabela <b>crm_settings</b> validada (com triggers).<br>";

    // 12. PUSH TOKENS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_push_tokens (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            device_id VARCHAR(255) UNIQUE NOT NULL,
            token TEXT,
            label VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        -- Aplicar trigger de updated_at
        DROP TRIGGER IF EXISTS trg_update_crm_push_tokens ON crm_push_tokens;
        CREATE TRIGGER trg_update_crm_push_tokens BEFORE UPDATE ON crm_push_tokens
        FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
    ");
    echo "✅ Tabela <b>crm_push_tokens</b> ok.<br>";

    // 10. SYSTEM LOGS
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
    // 11. CHECKOUTS (Rastreamento de Vendas)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_checkout (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            contact_id INTEGER REFERENCES crm_contacts(id) ON DELETE CASCADE,
            instance_name VARCHAR(100),
            product_name VARCHAR(255),
            amount DECIMAL(15,2),
            payment_method VARCHAR(50), 
            payment_url TEXT,
            status VARCHAR(30) DEFAULT 'pending',
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>crm_checkout</b> ok.<br>";

    echo "<hr style='border-color:#1a2a1d;'>";
    echo "<h2 style='color:#bcfd49;'>🌟 DATABASE PRONTO!</h2>";
    echo "<p>O ecossistema Wazio está validado no PostgreSQL.</p>";
    echo "<a href='../../index.php' style='color:#000; background:#bcfd49; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>VOLTAR AO PAINEL</a>";

} catch (Exception $e) {
    echo "<h2 style='color:#f87171;'>❌ ERRO NO SETUP:</h2>";
    echo "<pre style='background:#1a1a1a; padding:20px; border-radius:10px; color:#f87171;'>" . $e->getMessage() . "</pre>";
}
echo "</body>";
