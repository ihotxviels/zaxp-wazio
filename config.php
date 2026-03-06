<?php
// ============================================================
// ⚙️ CONFIGURAÇÃO CENTRAL — UAZAPI PANEL
// ============================================================

define('N8N_BASE', 'https://criadordigital-n8n-webhook.7phgib.easypanel.host/webhook');
define('N8N_WEBHOOK_URL', 'https://criadordigital-n8n-webhook.7phgib.easypanel.host/webhook/receber-evento-wazio');
define('WAZIO_BASE', 'https://zapx1analytics.uazapi.com');
define('WAZIO_TOKEN', 'CLa2LD2E1AtLWWgw1HiCjvFxj3LAipXjBKizJRiQybEs5lM1mz');
define('SYSTEM_NAME', 'Wazio Chatbot');
define('SYSTEM_VERSION', '1.0.0');
define('LOG_FILE', __DIR__ . '/logs/users.log');
define('USERS_FILE', __DIR__ . '/core/database/users.json');
define('PUSH_DATA_FILE', __DIR__ . '/core/database/push_data.json');
define('FINANCE_DATA_FILE', __DIR__ . '/core/database/financeiro.json');
define('PROXY_DATA_FILE', __DIR__ . '/core/database/proxies.json');
define('SESSION_TTL', 3600);
define('PANEL_SECRET', 'wazio_secret_key_2024'); // Chave para comunicações seguras (ex: n8n)

// ── BANCO DE DADOS POSTGRESQL (FLOWS & CRM) ──────────────────────
define('DB_HOST', '137.184.202.248'); // Host Externo (Para reconstrução)
define('DB_PORT', '5433');           // Porta Externa exposta no Easypanel
define('DB_NAME', 'wazio');
define('DB_USER', 'postgres');
define('DB_PASS', '6f76f38842b1ebfec9e4');

// URL base do painel para webhooks
$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentProto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
define('PANEL_URL', $currentProto . "://" . $currentHost . "/wazio");
define('WEBHOOK_SELF_URL', PANEL_URL . '/webhook_handler.php');

function get_db_connection()
{
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";options='--client_encoding=UTF8'";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        log_acao('SYSTEM', 'DB_ERROR', $e->getMessage());
        return null;
    }
}

// Global connection instance
$pdo = get_db_connection();


// ── Helpers de sessão ─────────────────────────────────────────
function log_acao(string $user, string $acao, string $detalhe = ''): void
{
    // 1. Log em Arquivo (Legado/Segurança)
    $dir = dirname(LOG_FILE);
    if (!is_dir($dir))
        mkdir($dir, 0755, true);
    $linha = sprintf("[%s] USER=%s | ACAO=%s | %s | IP=%s\n", date('Y-m-d H:i:s'), $user, $acao, $detalhe, $_SERVER['REMOTE_ADDR'] ?? 'CLI');
    file_put_contents(LOG_FILE, $linha, FILE_APPEND | LOCK_EX);

    // 2. Log no Banco de Dados (Para o Painel de Notificações)
    $pdo = get_db_connection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO crm_system_logs (level, source, message, context) VALUES (?, ?, ?, ?)");
            $level = (str_contains(strtoupper($acao), 'ERR') || str_contains(strtoupper($acao), 'FAIL')) ? 'CRITICAL' : 'info';
            $stmt->execute([$level, $user, $acao, json_encode(['detalhe' => $detalhe, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'])]);
        } catch (Exception $e) { /* Silencioso para não travar o processo principal */
        }
    }
}

function mask_token($token)
{
    if (!$token || strlen($token) < 10)
        return $token;
    return substr($token, 0, 4) . str_repeat('*', strlen($token) - 8) . substr($token, -4);
}

// ── Helpers N8N Push Webhook ─────────────────────────────────────────
function disparar_alerta_n8n(string $tipo, string $titulo, string $mensagem, array $extra_data = [])
{
    $pdo = get_db_connection();
    if (!$pdo)
        return false;

    // 1. Carregar Configurações Globais (Admin)
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM crm_settings WHERE username = 'admin' AND setting_key = 'n8n_push_settings'");
        $stmt->execute();
        $pDataJson = $stmt->fetchColumn();
        $settings = $pDataJson ? json_decode($pDataJson, true) : [];

        // Fallback Webhook
        $stmtWh = $pdo->prepare("SELECT setting_value FROM crm_settings WHERE username = 'admin' AND setting_key = 'n8n_webhook'");
        $stmtWh->execute();
        $webhookUrl = $stmtWh->fetchColumn();
        if ($webhookUrl) {
            $decoded = json_decode($webhookUrl, true);
            $webhookUrl = is_array($decoded) ? ($decoded['value'] ?? $webhookUrl) : $webhookUrl;
        }
    } catch (Exception $e) {
        return false;
    }

    if (empty($webhookUrl))
        $webhookUrl = N8N_WEBHOOK_URL;
    if (empty($webhookUrl))
        return false;

    // 2. Bloqueios e Filtros
    if (isset($settings['master_enabled']) && $settings['master_enabled'] === false)
        return false;

    $chaves = [
        'entrada' => 'entradas',
        'saida' => 'saidas',
        'instancia' => 'instancias',
        'conectada' => 'instancias_conectadas',
        'proxy' => 'proxies'
    ];
    if (isset($chaves[$tipo]) && empty($settings[$chaves[$tipo]]))
        return false;

    // 3. Busca Tokens de Dispositivos (Filtrado por Usuário se necessário)
    $instanciaNomeFiltro = $extra_data['instancia_nome'] ?? '';
    // Pegamos todos os tokens do banco
    $stmtDev = $pdo->query("SELECT token, device_id, username FROM crm_push_tokens");
    $allDevices = $stmtDev->fetchAll(PDO::FETCH_ASSOC);

    $devices = $allDevices;
    if (!empty($instanciaNomeFiltro)) {
        // Filtro por dono de instância
        $stmtUsers = $pdo->prepare("SELECT username, instances, role FROM crm_users");
        $stmtUsers->execute();
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        $donos = [];
        $adminReceberTodas = !empty($settings['admin_receber_todas']);

        foreach ($users as $u) {
            $uInsts = json_decode($u['instances'] ?? '[]', true);
            $isAdmin = ($u['role'] === 'admin');
            if (in_array($instanciaNomeFiltro, $uInsts)) {
                $donos[] = $u['username'];
            } elseif ($isAdmin && $adminReceberTodas) {
                $donos[] = $u['username'];
            }
        }
        $devices = array_filter($allDevices, fn($d) => in_array($d['username'], $donos));
    }

    if (empty($devices))
        return false;

    // Recalcula URLs absolutas
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}";
    $iconUrl = $baseUrl . '/wazio/public/images/waz-icon-hd.png?v=6';

    $deviceIds = array_values(array_filter(array_map(fn($d) => $d['device_id'] ?: $d['token'], $devices)));

    $pushPayload = json_encode([
        'app_id' => $settings['onesignal_id'] ?? '7efc7f67-06fd-4c11-a9b8-1086f316b424',
        'include_subscription_ids' => $deviceIds,
        'headings' => ['en' => $titulo, 'pt' => $titulo],
        'contents' => ['en' => $mensagem, 'pt' => $mensagem],
        'chrome_web_icon' => $iconUrl,
        'large_icon' => $iconUrl
    ], JSON_UNESCAPED_UNICODE);

    $payload = [
        'evento' => $tipo,
        'pushPayload' => $pushPayload,
        'tokens_destinos' => array_map(fn($id) => ['deviceId' => $id], $deviceIds),
        'titulo' => $titulo,
        'mensagem' => $mensagem,
        'painel_url' => $baseUrl . '/wazio',
        'timestamp' => date('c')
    ];

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// ── FUNÇÕES DE SINCRONIZAÇÃO E BANCO ──

function upsert_instance_to_db($i, $userId = 1)
{
    global $pdo;
    if (!$pdo)
        $pdo = get_db_connection();
    if (!$pdo)
        return;

    $name = $i['name'] ?? $i['instanceName'] ?? ($i['instance']['name'] ?? '');
    if (!$name)
        return;

    $sql = "INSERT INTO crm_instances (
                user_id, instance_name, instance_token, instance_number, profile_name, 
                profile_picture_url, server_url, status, connected, 
                webhook_enabled, webhook_url, full_data, last_checked, updated_at,
                proxy_host, proxy_port, proxy_protocol, proxy_user, proxy_pass
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?)
            ON CONFLICT (instance_name) DO UPDATE SET
                instance_token = COALESCE(NULLIF(EXCLUDED.instance_token, ''), crm_instances.instance_token),
                instance_number = COALESCE(NULLIF(EXCLUDED.instance_number, ''), crm_instances.instance_number),
                profile_name = COALESCE(NULLIF(EXCLUDED.profile_name, ''), crm_instances.profile_name),
                profile_picture_url = COALESCE(NULLIF(EXCLUDED.profile_picture_url, ''), crm_instances.profile_picture_url),
                status = EXCLUDED.status,
                connected = EXCLUDED.connected,
                webhook_url = COALESCE(NULLIF(EXCLUDED.webhook_url, ''), crm_instances.webhook_url),
                proxy_host = COALESCE(NULLIF(EXCLUDED.proxy_host, ''), crm_instances.proxy_host),
                proxy_port = COALESCE(NULLIF(EXCLUDED.proxy_port, ''), crm_instances.proxy_port),
                proxy_user = COALESCE(NULLIF(EXCLUDED.proxy_user, ''), crm_instances.proxy_user),
                proxy_pass = COALESCE(NULLIF(EXCLUDED.proxy_pass, ''), crm_instances.proxy_pass),
                full_data = EXCLUDED.full_data,
                updated_at = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);
    $status = strtolower($i['status'] ?? 'disconnected');
    $conn = ($status === 'open' || $status === 'connected');

    // Extrai dados de proxy se existirem
    $px = $i['proxy'] ?? [];

    $stmt->execute([
        $userId,
        (string) $name,
        $i['token'] ?? $i['instanceToken'] ?? '',
        $i['owner'] ?? $i['number'] ?? '',
        $i['profile_name'] ?? $i['pushname'] ?? $i['profileName'] ?? '',
        $i['profile'] ?? $i['profilePictureUrl'] ?? $i['picture'] ?? '',
        $i['server_url'] ?? WAZIO_BASE,
        $status,
        (int) $conn,
        (int) !empty($i['webhook_url']),
        $i['webhook_url'] ?? '',
        json_encode($i, JSON_UNESCAPED_UNICODE),
        $px['host'] ?? '',
        $px['port'] ?? '',
        $px['protocol'] ?? 'http',
        $px['username'] ?? $px['user'] ?? '',
        $px['password'] ?? $px['pass'] ?? ''
    ]);
}

function sync_all_uazapi_instances($force = false)
{
    $pdo = get_db_connection();
    if (!$pdo)
        return ['ok' => false, 'erro' => 'DB Connection Failed', 'data' => []];

    $cacheKey = 'last_sync_uazapi';
    $lastSync = $_SESSION[$cacheKey] ?? 0;

    if (!$force && (time() - $lastSync < 30)) {
        try {
            $stmt = $pdo->query("SELECT id, instance_name as name, instance_token as token, instance_number as owner, profile_name, profile_picture_url as profile, server_url, status, connected, webhook_enabled, webhook_url, instance_hidden, tag, proxy_host, proxy_port, proxy_protocol, full_data, last_checked, created_at, updated_at FROM crm_instances ORDER BY created_at DESC");
            return ['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'cached' => true];
        } catch (Exception $e) {
            return ['ok' => false, 'data' => []];
        }
    }

    $res = uazapi_api('/instance/all', [], 'GET');
    $apiInstances = extrair_instancias($res);

    if (!empty($apiInstances)) {
        foreach ($apiInstances as $inst) {
            upsert_instance_to_db($inst);
        }
        $_SESSION[$cacheKey] = time();
    }

    try {
        $stmt = $pdo->query("SELECT id, instance_name as name, instance_token as token, instance_number as owner, profile_name, profile_picture_url as profile, server_url, status, connected, webhook_enabled, webhook_url, instance_hidden, tag, proxy_host, proxy_port, proxy_protocol, full_data, last_checked, created_at, updated_at FROM crm_instances ORDER BY created_at DESC");
        return ['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'cached' => false];
    } catch (Exception $e) {
        return ['ok' => false, 'data' => [], 'error' => $e->getMessage()];
    }
}

function carregar_usuarios(): array
{
    $pdo = get_db_connection();
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT * FROM crm_users ORDER BY username ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $users = [];
            foreach ($rows as $r) {
                $users[$r['username']] = [
                    'id' => $r['id'],
                    'username' => $r['username'],
                    'password' => $r['password_hash'], // Renomeando para compatibilidade local
                    'nome' => $r['full_name'],
                    'role' => $r['role'],
                    'ativo' => $r['is_active'],
                    'limite_instancias' => $r['instance_limits'] ?? 10,
                    'instancias' => json_decode($r['instances'] ?? '[]', true),
                    'modulos' => json_decode($r['modulos'] ?? '[]', true),
                    'hidden_instances' => json_decode($r['hidden_instances'] ?? '[]', true),
                    'criado_em' => $r['created_at']
                ];
            }
            if (!empty($users))
                return $users;
        } catch (Exception $e) {
            // Fallback para JSON se o banco falhar/não houver tabela
            log_acao('SYSTEM', 'DB_LOAD_USERS_FAIL', $e->getMessage());
        }
    }

    if (!file_exists(USERS_FILE))
        return [];
    return json_decode(file_get_contents(USERS_FILE), true) ?? [];
}

function salvar_usuarios(array $users): void
{
    // Primeiro salva no Banco (Principal)
    $pdo = get_db_connection();
    if ($pdo) {
        try {
            foreach ($users as $username => $u) {
                $stmt = $pdo->prepare("INSERT INTO crm_users (username, password_hash, full_name, role, is_active, instance_limits, instances, modulos, hidden_instances, updated_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                                       ON CONFLICT (username) DO UPDATE SET 
                                       password_hash = EXCLUDED.password_hash,
                                       full_name = EXCLUDED.full_name,
                                       role = EXCLUDED.role,
                                       is_active = EXCLUDED.is_active,
                                       instance_limits = EXCLUDED.instance_limits,
                                       instances = EXCLUDED.instances,
                                       modulos = EXCLUDED.modulos,
                                       hidden_instances = EXCLUDED.hidden_instances,
                                       updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([
                    $username,
                    $u['password'] ?? '',
                    $u['nome'] ?? $username,
                    $u['role'] ?? 'user',
                    $u['ativo'] ?? true,
                    $u['limite_instancias'] ?? 10,
                    json_encode($u['instancias'] ?? []),
                    json_encode($u['modulos'] ?? []),
                    json_encode($u['hidden_instances'] ?? [])
                ]);
            }
        } catch (Exception $e) {
            log_acao('SYSTEM', 'DB_SAVE_USERS_FAIL', $e->getMessage());
        }
    }

    // Mantém o JSON de Fallback para segurança absoluta
    file_put_contents(
        USERS_FILE,
        json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function usuario_logado(): ?array
{
    if (!isset($_SESSION['user']))
        return null;

    $ts = $_SESSION['user']['ts'] ?? 0;
    if ((time() - $ts) > SESSION_TTL) {
        session_destroy();
        return null;
    }

    // [FIX] Atualiza o timestamp para manter a sessão viva enquanto houver atividade
    $_SESSION['user']['ts'] = time();
    return $_SESSION['user'];
}

function requer_login(string $role = 'user'): array
{
    $u = usuario_logado();
    if (!$u) {
        header('Location: /wazio/');
        exit;
    }
    if ($role === 'admin' && $u['role'] !== 'admin') {
        header('Location: /wazio/user?erro=acesso_negado');
        exit;
    }
    return $u;
}

function instancias_do_usuario(array $user, array $todas): array
{
    if ($user['role'] === 'admin')
        return $todas;
    $permitidas = $user['instancias'] ?? [];
    return array_values(array_filter($todas, fn($i) => in_array($i['name'] ?? $i['instanceName'] ?? '', $permitidas)));
}

// PROTEÇÃO CONTRA REDECLARAÇÃO
if (!function_exists('json_resposta')) {
    function json_resposta(array $data, int $code = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($code);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function n8n(string $slug, array $body = [], string $method = 'POST'): array
{
    $url = N8N_BASE . '/' . ltrim($slug, '/');
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
    ];
    if ($method !== 'GET' && !empty($body)) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $dec = json_decode($raw, true);
    return is_array($dec) ? $dec : ['ok' => false, 'erro' => "Falha no n8n ($code)"];
}

function uazapi_api(string $endpoint, array $body = [], string $method = 'POST', string $instanceToken = ''): array
{
    $url = WAZIO_BASE . '/' . ltrim($endpoint, '/');
    $token = $instanceToken ?: WAZIO_TOKEN;

    $headers = [
        'Content-Type: application/json',
        'token: ' . $token,
        'admintoken: ' . $token
    ];

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];

    if ($method !== 'GET' && !empty($body)) {
        // Garante que instanceName seja string para evitar erro 400
        if (isset($body['instanceName']) && is_numeric($body['instanceName'])) {
            $body['instanceName'] = (string) $body['instanceName'];
        }
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'erro' => "Erro de conexão: $err", 'http_code' => $code];
    }

    $dec = json_decode($raw, true);
    if (!is_array($dec)) {
        return ['ok' => false, 'erro' => "Resposta inválida da API ($code)", 'raw' => $raw];
    }

    return $dec;
}

function extrair_instancias($res): array
{
    $list = [];
    if (is_array($res)) {
        if (isset($res[0])) {
            $list = $res;
        } elseif (isset($res['instances']) && is_array($res['instances'])) {
            $list = $res['instances'];
        } elseif (isset($res['data']) && is_array($res['data'])) {
            $list = $res['data'];
        } else {
            // Pode ser um objeto único de instância
            $list = [$res];
        }
    }

    $fmt = [];
    foreach ($list as $i) {
        if (!is_array($i))
            continue;

        // Tenta resolver dados aninhados (comum em algumas versões da API)
        $base = $i['instance'] ?? $i;

        $name = $base['instanceName'] ?? $base['name'] ?? '';
        if (empty($name))
            continue;

        $fmt[] = [
            'name' => (string) $name,
            'token' => $base['instanceToken'] ?? $base['token'] ?? $base['hash'] ?? '',
            'status' => $base['status'] ?? $base['connectionStatus'] ?? $base['state'] ?? 'disconnected',
            'owner' => $base['owner'] ?? $base['number'] ?? $i['owner'] ?? '',
            'profile_name' => $base['profileName'] ?? $base['pushname'] ?? $i['pushname'] ?? '',
            'profile' => $base['profilePictureUrl'] ?? $base['picture'] ?? $i['profilePicUrl'] ?? '',
            'webhook_url' => $base['webhook']['url'] ?? $i['webhook']['url'] ?? '',
            'proxy' => $base['proxy'] ?? $i['proxy'] ?? []
        ];
    }
    return $fmt;
}

function upsert_contato(array $data): bool
{
    $pdo = get_db_connection();
    if (!$pdo)
        return false;

    $sql = "INSERT INTO crm_contacts (
                instance_name, phone, name, status, tags, raw_packet, extra_data, 
                chatwclid, utm_source, utm_medium, utm_campaign, created_at, updated_at
            )
            VALUES (:instance, :phone, :name, :status, :tags, :raw_packet, :extra_data, :chatwclid, :utm_source, :utm_medium, :utm_campaign, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT (instance_name, phone) DO UPDATE SET
                name = EXCLUDED.name,
                status = EXCLUDED.status,
                tags = EXCLUDED.tags,
                raw_packet = EXCLUDED.raw_packet,
                extra_data = EXCLUDED.extra_data,
                chatwclid = EXCLUDED.chatwclid,
                utm_source = EXCLUDED.utm_source,
                utm_medium = EXCLUDED.utm_medium,
                utm_campaign = EXCLUDED.utm_campaign,
                updated_at = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':instance' => $data['instance'] ?? '',
        ':phone' => $data['phone'] ?? '',
        ':name' => $data['name'] ?? 'Cliente Novo',
        ':status' => $data['status'] ?? 'lead',
        ':tags' => json_encode($data['tags'] ?? []),
        ':raw_packet' => json_encode($data['raw_packet'] ?? $data),
        ':extra_data' => json_encode($data['extra_data'] ?? []),
        ':chatwclid' => $data['chatwclid'] ?? null,
        ':utm_source' => $data['utm_source'] ?? null,
        ':utm_medium' => $data['utm_medium'] ?? null,
        ':utm_campaign' => $data['utm_campaign'] ?? null
    ]);
}

function upsert_chip(array $data): bool
{
    $pdo = get_db_connection();
    if (!$pdo)
        return false;

    $id = $data['id'] ?? null;
    if ($id) {
        $sql = "UPDATE crm_chips SET 
                nome = :nome, numero = :numero, status = :status, conexao = :conexao, 
                funcao = :funcao, categoria = :categoria, dispositivo = :dispositivo 
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $params = [
            ':id' => $id,
            ':nome' => $data['nome'] ?? '',
            ':numero' => $data['numero'] ?? '',
            ':status' => $data['status'] ?? 'DISPONÍVEL',
            ':conexao' => $data['conexao'] ?? 'OFFLINE',
            ':funcao' => $data['funcao'] ?? '',
            ':categoria' => $data['categoria'] ?? '1',
            ':dispositivo' => $data['dispositivo'] ?? '',
        ];
    } else {
        $sql = "INSERT INTO crm_chips (nome, numero, status, conexao, funcao, categoria, dispositivo)
                VALUES (:nome, :numero, :status, :conexao, :funcao, :categoria, :dispositivo)";
        $stmt = $pdo->prepare($sql);
        $params = [
            ':nome' => $data['nome'] ?? '',
            ':numero' => $data['numero'] ?? '',
            ':status' => $data['status'] ?? 'DISPONÍVEL',
            ':conexao' => $data['conexao'] ?? 'OFFLINE',
            ':funcao' => $data['funcao'] ?? '',
            ':categoria' => $data['categoria'] ?? '1',
            ':dispositivo' => $data['dispositivo'] ?? '',
        ];
    }
    return $stmt->execute($params);
}

function sync_instance_to_chip(string $instanceName, string $status): bool
{
    $pdo = get_db_connection();
    if (!$pdo)
        return false;

    // 1. Primeiro atualiza status da instancia
    $stmtInst = $pdo->prepare("UPDATE crm_instances SET status = ?, connected = ?, last_checked = CURRENT_TIMESTAMP WHERE instance_name = ?");
    $stmtInst->execute([$status, ($status === 'open' || $status === 'connected' ? 1 : 0), $instanceName]);

    // 2. Busca o número dessa instância para atualizar o chip
    $stmtNum = $pdo->prepare("SELECT instance_number FROM crm_instances WHERE instance_name = ?");
    $stmtNum->execute([$instanceName]);
    $num = $stmtNum->fetchColumn();

    if ($num) {
        $cleanNum = preg_replace('/[^0-9]/', '', $num);
        $conStatus = ($status === 'open' || $status === 'connected') ? 'ONLINE' : 'OFFLINE';

        // Tenta primeiro por instance_name direto (precisão total)
        $stmtChip = $pdo->prepare("UPDATE crm_chips SET conexao = ?, instance_name = ? WHERE instance_name = ? OR numero LIKE ?");
        return $stmtChip->execute([$conStatus, $instanceName, $instanceName, "%$cleanNum%"]);
    }
    return false;
}

function sync_tags_to_chip(string $phone, array $tags): bool
{
    $pdo = get_db_connection();
    if (!$pdo)
        return false;

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

    // Mapeamento de Tags para Categorias (Configurável)
    $categoryMap = [
        'boleto' => '3',
        'ads' => '2',
        'admin' => '1',
        'venda' => '3',
        'bloqueio' => '99' // Exemplo para banidos
    ];

    $newCat = null;
    foreach ($tags as $tag) {
        $t = strtolower($tag);
        foreach ($categoryMap as $key => $catId) {
            if (strpos($t, $key) !== false || (strpos($key, $t) !== false && strlen($t) > 3)) {
                $newCat = $catId;
                break 2;
            }
        }
    }

    if ($newCat) {
        $stmt = $pdo->prepare("UPDATE crm_chips SET categoria = ? WHERE numero LIKE ? OR instance_name IN (SELECT instance_name FROM crm_instances WHERE instance_number LIKE ?)");
        return $stmt->execute([$newCat, "%$cleanPhone%", "%$cleanPhone%"]);
    }
    return false;
}


/**
 * 🏷️ SINCRONIZA ETIQUETAS DE UMA INSTÂNCIA
 */
function sync_instance_labels(string $instanceName): bool
{
    $pdo = get_db_connection();
    if (!$pdo)
        return false;

    // 1. Puxa etiquetas da API
    $res = uazapi_api("/chat/labels", ['instance' => $instanceName], 'POST');
    // Obs: Algumas APIs podem usar GET, mas conforme ApiController.php line 281, é POST

    // Fallback: se o endpoint acima for restrito a mensagens específicas, 
    // tentamos buscar todas as labels se a UAZAPI suportar (ex: /instance/labels)
    // Por enquanto, seguimos o padrão do ApiController.

    $labels = is_array($res) ? ($res['labels'] ?? $res['data'] ?? $res) : [];
    if (!is_array($labels))
        return false;

    foreach ($labels as $l) {
        $lId = $l['id'] ?? $l['labelId'] ?? '';
        $lName = $l['name'] ?? $l['labelName'] ?? '';
        if (empty($lId) || empty($lName))
            continue;

        $sql = "INSERT INTO crm_labels (instance_name, label_id, label_name, updated_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (instance_name, label_id) DO UPDATE SET
                    label_name = EXCLUDED.label_name,
                    updated_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$instanceName, $lId, $lName]);
    }
    return true;
}

/**
 * 🔄 ESPELHA CHATS DA UAZAPI PARA O BANCO LOCAL
 */
function sync_chats_to_db(string $instanceName, string $instanceToken = ''): array
{
    $pdo = get_db_connection();
    if (!$pdo)
        return ['ok' => false, 'erro' => 'DB Connection Failed'];

    // Busca chats na UAZAPI (Filter empty where to get all)
    $res = uazapi_api("/chat/find", ['instanceName' => $instanceName, 'limit' => 9999, 'where' => (object) []], 'POST', $instanceToken);
    $chats = $res['chats'] ?? $res['data'] ?? [];

    if (!is_array($chats))
        return ['ok' => false, 'erro' => 'Invalid API Response', 'raw' => $res];

    $count = 0;
    foreach ($chats as $c) {
        $jid = $c['id'] ?? $c['remoteJid'] ?? '';
        if (!$jid)
            continue;

        $name = $c['name'] ?? $c['pushname'] ?? '';
        $unread = $c['unreadCount'] ?? 0;
        $archive = (bool) ($c['archive'] ?? false);
        $isGroup = (bool) ($c['isGroup'] ?? false);

        // Extrai última mensagem
        $lastMsg = $c['lastMessage']['message']['conversation'] ??
            $c['lastMessage']['message']['extendedTextMessage']['text'] ??
            'Mídia / Outro';

        $ts = null;
        if (isset($c['lastMessageTimestamp'])) {
            $ts = date('Y-m-d H:i:s', (int) $c['lastMessageTimestamp']);
        }

        $sql = "INSERT INTO crm_chats (instance_name, remote_jid, name, unread_count, last_message, last_message_timestamp, archive, is_group, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (instance_name, remote_jid) DO UPDATE SET
                    name = EXCLUDED.name,
                    unread_count = EXCLUDED.unread_count,
                    last_message = EXCLUDED.last_message,
                    last_message_timestamp = EXCLUDED.last_message_timestamp,
                    archive = EXCLUDED.archive,
                    is_group = EXCLUDED.is_group,
                    updated_at = CURRENT_TIMESTAMP";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $instanceName,
            $jid,
            $name,
            $unread,
            $lastMsg,
            $ts,
            (int) $archive,
            (int) $isGroup
        ]);
        $count++;
    }

    return ['ok' => true, 'count' => $count];
}

/**
 * 🔄 ESPELHA MENSAGENS DA UAZAPI PARA O BANCO LOCAL
 */
function sync_messages_to_db(string $instanceName, string $jid, string $instanceToken = ''): array
{
    $pdo = get_db_connection();
    if (!$pdo)
        return ['ok' => false, 'erro' => 'DB Connection Failed'];

    $res = uazapi_api("/message/find", [
        'instanceName' => $instanceName,
        'remoteJid' => $jid,
        'limit' => 40,
        'where' => (object) []
    ], 'POST', $instanceToken);

    $msgs = $res['messages'] ?? $res['data'] ?? [];
    if (!is_array($msgs))
        return ['ok' => false, 'erro' => 'Invalid API Response'];

    $count = 0;
    foreach ($msgs as $m) {
        $msgId = $m['key']['id'] ?? '';
        if (!$msgId)
            continue;

        $fromMe = (bool) ($m['key']['fromMe'] ?? false);
        $status = $m['status'] ?? 'sent';
        $ts = date('Y-m-d H:i:s', (int) ($m['messageTimestamp'] ?? time()));

        $sql = "INSERT INTO crm_messages (instance_name, remote_jid, message_id, from_me, content, status, timestamp)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (instance_name, message_id) DO UPDATE SET
                    status = EXCLUDED.status,
                    timestamp = EXCLUDED.timestamp";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $instanceName,
            $jid,
            $msgId,
            (int) $fromMe,
            json_encode($m, JSON_UNESCAPED_UNICODE),
            $status,
            $ts
        ]);
        $count++;
    }

    return ['ok' => true, 'count' => $count];
}
