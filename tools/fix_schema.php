<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

echo "Updating crm_instances schema...\n";

$queries = [
    "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS webhook_url TEXT",
    "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS proxy_host VARCHAR(255)",
    "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS proxy_port VARCHAR(10)",
    "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS proxy_user VARCHAR(100)",
    "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS proxy_pass VARCHAR(100)",
    "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS proxy_protocol VARCHAR(20) DEFAULT 'http'",
    "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS webhook_enabled BOOLEAN DEFAULT true",
    "ALTER TABLE crm_instances ADD COLUMN IF NOT EXISTS instance_hidden BOOLEAN DEFAULT false"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        echo "SUCCESS: $q\n";
    } catch (Exception $e) {
        echo "ERROR: $q -> " . $e->getMessage() . "\n";
    }
}

echo "Schema update completed.\n";
