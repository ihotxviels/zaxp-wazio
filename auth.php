<?php
// /wazio/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    session_unset();
    session_destroy();
    header("Location: /wazio/index.php");
    exit;
}

// 2. Extrai as variáveis para uso global nos módulos
$user = $_SESSION['user'];
$role = $user['role'] ?? 'user';
$username = $user['username'] ?? 'admin';
$modulos_permitidos = $user['modulos'] ?? [];
$allowed_instances = json_encode($user['instancias'] ?? []);

// 3. Função global para verificar permissão de módulo
function temPermissaoModulo($moduloEsperado)
{
    global $role, $modulos_permitidos;
    if ($role === 'admin')
        return true;
    return in_array($moduloEsperado, $modulos_permitidos);
}
?>