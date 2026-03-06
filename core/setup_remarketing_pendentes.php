<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = get_db_connection();
    if (!$pdo)
        throw new Exception("Conexão falhou.");

    echo "<h1>🛠️ SETUP REMARKETING PENDENTES</h1>";

    // 1. Criar tabela remarketing (se não existir)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remarketing (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            instance_name VARCHAR(100),
            phone VARCHAR(50),
            name VARCHAR(255),
            last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(instance_name, phone)
        );
    ");
    echo "✅ Tabela <b>remarketing</b> garantida.<br>";

    // 2. Criar tabela remarketing_pendentes (mesma estrutura)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remarketing_pendentes (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL DEFAULT 1,
            instance_name VARCHAR(100),
            phone VARCHAR(50),
            name VARCHAR(255),
            last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(instance_name, phone)
        );
    ");
    echo "✅ Tabela <b>remarketing_pendentes</b> garantida.<br>";

    // 3. Criar tabela compradores (se não existir, usada no fluxo anterior)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS compradores (
            id SERIAL PRIMARY KEY,
            phone_id VARCHAR(50) UNIQUE,
            name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Tabela <b>compradores</b> garantida.<br>";

    echo "<br>🚀 Pronto para disparar!";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
