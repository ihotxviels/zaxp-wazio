<?php
require_once __DIR__ . '/config.php';

try {
    // Connect to source DB
    $dsn_source = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=criadordigital";
    $pdo_source = new PDO($dsn_source, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Connect to target DB
    $dsn_target = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=wazio";
    $pdo_target = new PDO($dsn_target, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Create a backup of the master schema and logic we just fixed up
    $tables = [
        'crm_users',
        'crm_chips',
        'crm_instances',
        'crm_contacts',
        'crm_messages',
        'crm_funnels',
        'crm_funnel_progress',
        'crm_labels',
        'crm_finance',
        'crm_checkout',
        'crm_settings',
        'crm_system_logs',
        'crm_push_tokens',
        'crm_remarketing'
    ];

    // Setup function for updated_at tracking
    $pdo_target->exec("
        CREATE OR REPLACE FUNCTION update_updated_at_column()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ language 'plpgsql';
    ");

    // We can just dump and restore using pg_dump if it's available in exec(), but since psql failed earlier,
    // let's try calling pg_dump and psql directly via shell_exec in case they are in the PATH but PowerShell aliases were messing up "psql" vs "psql.exe".

    // Instead of doing it in PHP entirely which is hard for schema, let's just write a .bat file to run the native binaries if they exist in standard Postgres paths.

    echo "Script prepared. We will try multiple pg_dump locations.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>