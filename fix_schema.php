<?php
require_once __DIR__ . '/config.php';

try {
    echo "Iniciando ajuste de schema Postgres...\n";

    // 1. CRM_INSTANCES
    $q1 = "
    DROP TABLE IF EXISTS crm_instances CASCADE;
    CREATE TABLE crm_instances (
        id SERIAL PRIMARY KEY,
        user_id INT,
        instance_name VARCHAR(255) UNIQUE NOT NULL,
        token TEXT,
        status VARCHAR(50) DEFAULT 'disconnected',
        owner VARCHAR(255),
        webhook_enabled BOOLEAN DEFAULT false,
        webhook_url TEXT,
        proxy_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";

    // 2. CRM_CHIPS (Mapeamento Uazapi)
    $q2 = "
    DROP TABLE IF EXISTS crm_chips CASCADE;
    CREATE TABLE crm_chips (
        id SERIAL PRIMARY KEY,
        user_id INT,
        nome VARCHAR(255),
        numero VARCHAR(50),
        instance_name VARCHAR(255), -- Link nativo com crm_instances
        status VARCHAR(50),
        conexao VARCHAR(50) DEFAULT 'OFFLINE',
        funcao TEXT,
        categoria TEXT,
        dispositivo TEXT,
        index_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";

    $pdo->exec($q1);
    echo "Tabela crm_instances recriada com sucesso.\n";
    $pdo->exec($q2);
    echo "Tabela crm_chips recriada com sucesso.\n";

    echo "Schema alinhado com sucesso!\n";

} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
