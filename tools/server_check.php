<?php
/**
 * 🛠️ WAZIO SERVER DIAGNOSTIC
 * Verifique se o seu servidor tem tudo o que precisa.
 */

header('Content-Type: text/html; charset=utf-8');
echo "<body style='background:#0f171e; color:#fff; font-family:sans-serif; padding:40px;'>";
echo "<h1 style='color:#bcfd49;'>🔍 Diagnóstico de Servidor - Wazio</h1><hr>";

function check_extension($name)
{
    if (extension_loaded($name)) {
        echo "<p style='color:#bcfd49;'>✅ Extensão <b>$name</b>: Carregada!</p>";
    } else {
        echo "<p style='color:#ff4d4d;'>❌ Extensão <b>$name</b>: NÃO ENCONTRADA!</p>";
        echo "<small>Dica: Execute 'sudo apt install php-{$name}' no seu servidor Linux.</small>";
    }
}

echo "<h3>1. Verificando Drivers de Banco (PostgreSQL)</h3>";
check_extension('pgsql');
check_extension('pdo_pgsql');

echo "<h3>2. Versão do PHP</h3>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";

echo "<h3>3. Testando Conexão com Banco</h3>";
require_once 'config.php';
try {
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";options='--client_encoding=UTF8'";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    echo "<p style='color:#bcfd49;'>✅ Conexão com PostgreSQL: OK!</p>";
} catch (Exception $e) {
    echo "<p style='color:#ff4d4d;'>❌ Erro de Conexão: " . $e->getMessage() . "</p>";
}

echo "<hr><p>Wazio Dashboard Infrastructure</p></body>";
