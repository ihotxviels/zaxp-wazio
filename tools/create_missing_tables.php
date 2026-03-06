<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

echo "Creating missing tables...\n";

$queries = [
    // SETTINGS
    "CREATE TABLE IF NOT EXISTS crm_settings (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        setting_value JSONB DEFAULT '{}'::jsonb,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(username, setting_key)
    )",
    // LOGS
    "CREATE TABLE IF NOT EXISTS crm_system_logs (
        id SERIAL PRIMARY KEY,
        user_tag VARCHAR(100),
        action VARCHAR(100),
        details TEXT,
        ip_address VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    // PUSH TOKENS
    "CREATE TABLE IF NOT EXISTS crm_push_tokens (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        device_id VARCHAR(255) UNIQUE NOT NULL,
        token TEXT,
        label VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    // CHECKOUTS
    "CREATE TABLE IF NOT EXISTS crm_checkout (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL DEFAULT 1,
        contact_id INTEGER,
        instance_name VARCHAR(100),
        product_name VARCHAR(255),
        amount DECIMAL(15,2),
        payment_method VARCHAR(50), 
        payment_url TEXT,
        status VARCHAR(30) DEFAULT 'pending',
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        echo "SUCCESS: Table created/verified.\n";
    } catch (Exception $e) {
        echo "ERROR: Table creation failed -> " . $e->getMessage() . "\n";
    }
}

echo "Missing tables setup completed.\n";
