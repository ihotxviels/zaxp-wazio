<?php
/**
 * 🛠️ WAZIO DATABASE EXECUTOR
 * Use este arquivo para criar todas as tabelas no PostgreSQL.
 */

require_once 'config.php';

// Redireciona para o setup mestre localizado na pasta core
$setupUrl = '/wazio/core/database/master_setup.php';

header("Location: $setupUrl");
exit;