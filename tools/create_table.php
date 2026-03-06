<?php
require 'config.php';
$pdo = get_db_connection();
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS lead_flow_status (
            id SERIAL PRIMARY KEY,
            instance_id VARCHAR(50) NOT NULL,
            lead_phone VARCHAR(50) NOT NULL,
            current_flow_name VARCHAR(100),
            current_node_id VARCHAR(50),
            wait_until TIMESTAMP,
            variables JSONB DEFAULT '{}'::jsonb,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(instance_id, lead_phone)
        )");
        echo 'Table lead_flow_status created successfully';
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo 'No connection to PostgreSQL';
}
