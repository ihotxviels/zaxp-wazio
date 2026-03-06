<?php
/**
 * WAZ.IO — FRONT CONTROLLER (ROUTER)
 * Suporta URLs limpas: /wazio/dashboard, /wazio/financeiro, etc.
 */

// ─── 0. SESSION INIT ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/wazio/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ─── 1. SEGURANÇA DE CABEÇALHO ─────────────────────────────────
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Shield.php'; // 🔥 WAF Anti-Intrusão & Anti-DDoS Ativo!

// ─── 2. MAPA DE ROTAS LIMPAS → INTERNAS ────────────────────────
// Formato: 'slug_limpa' => 'rota_interna'
$cleanPaths = [
    '' => 'auth/login',
    'dashboard' => 'admin/metricas',
    'instancias' => 'admin/instancias',
    'financeiro' => 'admin/financeiro',
    'renovacoes' => 'admin/renovacoes',
    'fluxos' => 'admin/fluxos',
    'remarketing' => 'admin/remarketing',
    'bloqueador' => 'admin/bloqueador',
    'contingencia' => 'admin/contingencia',
    'metricas' => 'admin/metricas',
    'push' => 'admin/push_settings',
    'conversor' => 'admin/conversor',
    'compressor' => 'admin/compressor',
    'transcricao' => 'admin/transcricao',
    'limpar-hash' => 'admin/limpar_hash',
    'baixar-videos' => 'admin/baixar_videos',
    'metadados' => 'admin/metadados',
    'inbox' => 'admin/inbox',
    'kanban' => 'admin/kanban',
    'banco-leads' => 'admin/banco_leads',
    'n8n' => 'admin/n8n',
    'database' => 'admin/database',
    'configuracoes' => 'admin/configuracoes',
    'setup-db' => 'core/schema_master_setup',
    'view-schema' => 'core/view_schema',
    'user' => 'user/dashboard',
];

// Mapa interno de rotas → arquivos físicos
$routes = [
    'auth/login' => 'app/Views/auth/login.php',
    'admin/dashboard' => 'app/Views/admin/dashboard.php',
    'admin/instancias' => 'app/Views/admin/instancias.php',
    'admin/metricas' => 'app/Views/admin/metricas.php',
    'admin/financeiro' => 'app/Views/admin/financeiro.php',
    'admin/renovacoes' => 'app/Views/admin/renovacoes.php',
    'admin/fluxos' => 'app/Views/admin/fluxos.php',
    'admin/remarketing' => 'app/Views/admin/remarketing.php',
    'admin/bloqueador' => 'app/Views/admin/bloqueador.php',
    'admin/contingencia' => 'app/Views/admin/contingencia.php',
    'admin/push_settings' => 'app/Views/admin/push_settings.php',
    'admin/conversor' => 'app/Views/admin/conversor.php',
    'admin/compressor' => 'app/Views/admin/compressor.php',
    'admin/transcricao' => 'app/Views/admin/transcricao.php',
    'admin/limpar_hash' => 'app/Views/admin/limpar_hash.php',
    'admin/baixar_videos' => 'app/Views/admin/baixar_videos.php',
    'admin/metadados' => 'app/Views/admin/metadados.php',
    'admin/n8n' => 'app/Views/admin/n8n.php',
    'admin/database' => 'app/Views/admin/database.php',
    'admin/inbox' => 'app/Views/admin/inbox.php',
    'admin/kanban' => 'app/Views/admin/kanban.php',
    'admin/banco_leads' => 'app/Views/admin/banco_leads.php',
    'admin/configuracoes' => 'app/Views/admin/configuracoes.php',
    'user/dashboard' => 'app/Views/user/dashboard.php',
    'core/schema_master_setup' => 'core/schema_master_setup.php',
    'core/view_schema' => 'core/view_schema.php',
    'api' => 'app/Controllers/ApiController.php',
];

// ─── 3. DETECTAR ROTA ──────────────────────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$base = ($scriptName === '/' || $scriptName === '\\') ? '' : $scriptName;

// Extrai o slug proporcional à pasta onde o index.php está
$slug = trim(substr($uri, strlen($base)), '/');
$slug = ($slug === 'index.php') ? '' : $slug;
$slug = str_replace(['..', "\0"], '', $slug);

// Lógica de Despacho:
$requestedRoute = $_GET['route'] ?? $slug;
$requestedRoute = trim(str_replace(['..', "\0"], '', $requestedRoute), '/');

// ─── BYPASS: UAZAPI Flow Engine Webhook (WhatsApp API) ───
if ($requestedRoute === 'api/flow_webhook') {
    require_once __DIR__ . '/app/Controllers/FlowWebhook.php';
    exit;
}

if (array_key_exists($requestedRoute, $cleanPaths)) {
    $route = $cleanPaths[$requestedRoute];
} elseif (array_key_exists($requestedRoute, $routes)) {
    $route = $requestedRoute;
} elseif (empty($requestedRoute)) {
    $route = 'auth/login';
} else {
    $route = $requestedRoute; // Tenta carregar o que veio, se falhar o validador abaixo pega
}

// ─── 4. LOGOUT RÁPIDO ──────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: /wazio/');
    exit;
}

// ─── 5. DESPACHAR ──────────────────────────────────────────────
if (array_key_exists($route, $routes)) {
    $file = __DIR__ . '/' . $routes[$route];
    if (file_exists($file)) {
        require_once $file;
    } else {
        http_response_code(404);
        echo "404 - View não encontrada: " . htmlspecialchars($routes[$route]);
    }
} else {
    http_response_code(404);
    echo "404 - Rota inválida.";
}