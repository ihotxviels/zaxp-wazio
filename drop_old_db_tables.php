<?php
require_once __DIR__ . '/config.php';

try {
    // Note: config.php now points to wazio, so we must connect to criadordigital explicitly to drop its tables
    $pdo_source = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=criadordigital", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "Connected to criadordigital.\n";

    // List of tables to drop
    $tables = [
        'crm_messages',
        'crm_funnel_progress',
        'crm_funnels',
        'crm_contacts',
        'crm_chips',
        'crm_instances',
        'crm_labels',
        'crm_finance',
        'crm_checkout',
        'crm_settings',
        'crm_system_logs',
        'crm_push_tokens',
        'crm_remarketing',
        'crm_users'
    ];

    $pdo_source->beginTransaction();
    foreach ($tables as $table) {
        $pdo_source->exec("DROP TABLE IF EXISTS {$table} CASCADE");
        echo "Dropped table {$table}.\n";
    }
    $pdo_source->commit();

    echo "\nAll old tables from criadordigital have been dropped successfully.\n";

} catch (Exception $e) {
    if (isset($pdo_source) && $pdo_source->inTransaction()) {
        $pdo_source->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>