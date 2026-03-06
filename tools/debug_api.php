<?php
// debug_api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['user'] = [
    'username' => 'admin',
    'role' => 'admin',
    'ts' => time(),
    'modulos' => ['financeiro']
];

$_GET['action'] = 'fin_listar';
require_once 'app/Controllers/ApiController.php';
