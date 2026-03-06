<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/wazio/']);
    session_start();
}
// ============================================================
// 🔌 ApiController.php — REST CENTRAL (MVC VERSION)
// ============================================================
require_once __DIR__ . '/../../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit(0);

$action = $_GET['action'] ?? '';
$inputJSON = file_get_contents('php://input');
$body = json_decode($inputJSON, true) ?? [];
$user = usuario_logado();

// Autenticação via Bearer Token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$user && str_starts_with($authHeader, 'Bearer ')) {
    if (substr($authHeader, 7) === PANEL_SECRET) {
        $user = ['username' => 'n8n_bot', 'role' => 'admin', 'ts' => time()];
    }
}

if (!$user && !in_array($action, ['login', 'media_downloader', 'run_ffmpeg'])) {
    $errorMsg = !isset($_SESSION['user']) ? 'Sessão não encontrada' : 'Sessão expirada';
    json_resposta(['ok' => false, 'erro' => "Não autenticado: $errorMsg"], 401);
}

function check_instance_permission($identifier, $user, $isName = false)
{
    if (!$user)
        json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
    if ($user['role'] === 'admin')
        return;

    $name = $identifier;
    if (!$isName) {
        $res = n8n('listar-instancias', [], 'GET');
        $todas = extrair_instancias($res);
        $name = '';
        foreach ($todas as $i) {
            if (($i['token'] ?? '') === $identifier || ($i['id'] ?? '') === $identifier) {
                $name = $i['name'] ?? '';
                break;
            }
        }
    }

    if (empty($name) || !in_array($name, $user['instancias'] ?? [])) {
        log_acao($user['username'], 'API_ACCESS_DENIED', "Tentativa de acesso não autorizada a instância: $identifier");
        json_resposta(['ok' => false, 'erro' => 'Acesso HTTP 403: Instância não pertence ao seu usuário.'], 403);
    }
}

switch ($action) {

    case 'login':
        $username = trim($body['username'] ?? '');
        $senha = $body['password'] ?? '';
        $users = carregar_usuarios();

        if (empty($users[$username]) || !($users[$username]['ativo'] ?? false)) {
            json_resposta(['ok' => false, 'erro' => 'Credenciais inválidas'], 401);
        }

        $u = $users[$username];
        if (!password_verify($senha, $u['password'])) {
            json_resposta(['ok' => false, 'erro' => 'Credenciais inválidas'], 401);
        }

        $_SESSION['user'] = [
            'user_id' => $u['id'] ?? $username,
            'username' => $username,
            'role' => $u['role'],
            'nome' => $u['nome'],
            'instancias' => $u['instancias'] ?? [],
            'modulos' => $u['modulos'] ?? [],
            'hidden_instances' => $u['hidden_instances'] ?? [],
            'limite_instancias' => $u['limite_instancias'] ?? 10,
            'ts' => time(),
        ];
        json_resposta(['ok' => true, 'role' => $u['role'], 'nome' => $u['nome']]);
        break;

    case 'api_uazapi':
        // Proxy Genérico para Nodes de API do Evolution / n8n
        $uLogin = requer_login();
        $method = $_POST['method'] ?? $_GET['method'] ?? 'GET';
        $endpoint = $_POST['endpoint'] ?? $_GET['endpoint'] ?? '';
        $instName = $_POST['instance'] ?? $_GET['instance'] ?? '';
        $tok = $_POST['token'] ?? $_GET['token'] ?? '';
        $reqBody = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($endpoint)) {
            json_resposta(['ok' => false, 'erro' => 'Endpoint não especificado'], 400);
        }

        // Segurança: Se passou instancia, valida se o usuário tem acesso
        if (!empty($instName) && $uLogin['role'] !== 'admin') {
            if (!in_array($instName, $uLogin['instancias'] ?? [])) {
                json_resposta(['ok' => false, 'erro' => 'Acesso negado a esta instância'], 403);
            }
        }

        // Constrói a chamada para o n8n
        $resApi = n8n('/proxy-evolution', [
            'method' => $method,
            'endpoint' => $endpoint,
            'instance' => $instName,
            'token' => $tok,
            'body' => $reqBody
        ]);

        json_resposta($resApi);
        break;

    case 'api_evolution':
        // Proxy Direto PHP -> Evolution (Bypass N8N)
        $uLogin = requer_login();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $method = $input['method'] ?? $_POST['method'] ?? $_GET['method'] ?? 'GET';
        $endpoint = $input['endpoint'] ?? $_POST['endpoint'] ?? $_GET['endpoint'] ?? '';
        $instToken = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? '';

        // Se a requisição JS envia um campo 'body', passamos ele, senão, passamos o payload todo
        $bodyForEvo = isset($input['body']) ? $input['body'] : ($input ?? $_POST);

        if (empty($endpoint)) {
            json_resposta(['ok' => false, 'erro' => 'Endpoint não especificado'], 400);
        }

        // O evolution_api já lida com o token da instância ou o global
        $resApi = uazapi_api($endpoint, $bodyForEvo, $method, $instToken);
        json_resposta($resApi);
        break;

    case 'instancias':
        // 1. Sincronização em Tempo Real (API -> DB)
        $sync = sync_all_uazapi_instances();
        $apiInsts = $sync['data'] ?? [];

        $pdo = get_db_connection();
        if (!$pdo)
            json_resposta(['ok' => false, 'erro' => 'DB offline'], 500);

        // 2. Busca do Banco (Fonte da Verdade para configurações locais)
        $stmt = $pdo->query("SELECT *, instance_name as name, instance_token as token FROM crm_instances ORDER BY instance_name ASC");
        $dbInsts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($dbInsts as $dbI) {
            // Mapeia para o formato esperado pelo frontend
            $item = [
                'name' => $dbI['instance_name'],
                'token' => $dbI['instance_token'],
                'owner' => $dbI['instance_number'],
                'profile' => $dbI['profile_picture_url'],
                'displayName' => !empty($dbI['profile_name']) ? $dbI['profile_name'] : $dbI['instance_name'],
                'status' => $dbI['status'],
                'is_active' => ($dbI['status'] === 'open' || $dbI['status'] === 'connected'),
                'webhook_url' => $dbI['webhook_url'],
                'webhook_enabled' => (bool) $dbI['webhook_enabled'],
                'instance_hidden' => (bool) $dbI['instance_hidden'],
                'tag' => $dbI['tag'] ?? '',
                'proxy_host' => $dbI['proxy_host'] ?? '',
                'is_mine' => ($user['role'] === 'admin' || in_array($dbI['instance_name'], $user['instancias'] ?? []))
            ];

            // Filtros
            if ($user['role'] !== 'admin' && !$item['is_mine'])
                continue;
            if (($_GET['active_only'] ?? '') === 'true' && !$item['is_active'])
                continue;

            $resultado[] = $item;
        }

        json_resposta([
            'ok' => true,
            'data' => $resultado,
            'sync' => !empty($apiInsts)
        ]);
        break;

    case 'kpi_data':
        $pdo = get_db_connection();
        if (!$pdo) {
            json_resposta(['ok' => false, 'erro' => 'Falha de conexão com banco de dados'], 500);
        }

        $userId = $user['user_id'] ?? 1;
        $isAdmin = ($user['role'] === 'admin');

        try {
            // Count total contacts
            $contactSql = $isAdmin ? "SELECT COUNT(*) FROM crm_contacts" : "SELECT COUNT(*) FROM crm_contacts WHERE user_id = :uid";
            $stmtC = $pdo->prepare($contactSql);
            if (!$isAdmin)
                $stmtC->bindParam(':uid', $userId, PDO::PARAM_INT);
            $stmtC->execute();
            $totalContacts = $stmtC->fetchColumn();

            // Count total funnels
            $funnelSql = $isAdmin ? "SELECT COUNT(*) FROM crm_funnels" : "SELECT COUNT(*) FROM crm_funnels WHERE user_id = :uid";
            $stmtF = $pdo->prepare($funnelSql);
            if (!$isAdmin)
                $stmtF->bindParam(':uid', $userId, PDO::PARAM_INT);
            $stmtF->execute();
            $totalFunnels = $stmtF->fetchColumn();

            // Count total messages
            $msgSql = $isAdmin ? "SELECT COUNT(*) FROM crm_messages" : "SELECT COUNT(*) FROM crm_messages WHERE user_id = :uid";
            $stmtM = $pdo->prepare($msgSql);
            if (!$isAdmin)
                $stmtM->bindParam(':uid', $userId, PDO::PARAM_INT);
            $stmtM->execute();
            $totalMessages = $stmtM->fetchColumn();

            json_resposta([
                'ok' => true,
                'data' => [
                    'contacts' => (int) $totalContacts,
                    'funnels' => (int) $totalFunnels,
                    'messages' => (int) $totalMessages
                ]
            ]);
        } catch (Exception $e) {
            json_resposta(['ok' => false, 'erro' => 'Erro ao buscar KPIs do DB', 'details' => $e->getMessage()], 500);
        }
        break;


    case 'criar':
        $nomeInstancia = trim((string) ($body['name'] ?? $body['instanceName'] ?? ''));
        if (empty($nomeInstancia))
            json_resposta(['ok' => false, 'erro' => 'Nome obrigatório'], 400);

        // Valida limites para não-admins
        if ($user['role'] !== 'admin') {
            $uData = carregar_usuarios()[$user['username']] ?? [];
            if (count($uData['instancias'] ?? []) >= ($uData['limite_instancias'] ?? 10)) {
                json_resposta(['ok' => false, 'erro' => 'Limite atingido'], 403);
            }
        }

        // 1. API INIT
        $res = uazapi_api('/instance/init', [
            'instanceName' => (string) $nomeInstancia,
            'token' => $body['token'] ?? '',
            'number' => (string) ($body['number'] ?? '')
        ], 'POST');

        $success = ($res['hash'] ?? $res['token'] ?? $res['instance']['token'] ?? $res['ok'] ?? false);
        $token = (string) ($res['hash'] ?? $res['token'] ?? $res['instance']['token'] ?? $res['data']['token'] ?? '');

        // 2. DB PERSISTENCE
        if ($success) {
            upsert_instance_to_db([
                'name' => $nomeInstancia,
                'token' => $token,
                'number' => $body['number'] ?? '',
                'status' => 'disconnected'
            ], $user['user_id'] ?? 1);

            if ($user['role'] !== 'admin') {
                $users = carregar_usuarios();
                $users[$user['username']]['instancias'][] = $nomeInstancia;
                salvar_usuarios($users);
                $_SESSION['user']['instancias'] = $users[$user['username']]['instancias'];
            }
        }

        json_resposta([
            'ok' => (bool) $success,
            'name' => $nomeInstancia,
            'token' => $token,
            'qrCode' => $res['qrcode']['base64'] ?? $res['base64'] ?? $res['qrcode'] ?? $res['data']['qrcode'] ?? null,
            'erro' => $res['erro'] ?? $res['message'] ?? $res['error'] ?? null
        ]);
        break;

    case 'conectar':
        $instanceName = (string) ($body['name'] ?? $body['instanceName'] ?? $body['instance'] ?? $_GET['name'] ?? '');
        $token = $body['token'] ?? $_GET['token'] ?? '';

        if (empty($instanceName) && !empty($token)) {
            $pdo = get_db_connection();
            $s = $pdo->prepare("SELECT instance_name FROM crm_instances WHERE instance_token = ?");
            $s->execute([$token]);
            $instanceName = $s->fetchColumn() ?: '';
        }

        if (empty($instanceName))
            json_resposta(['ok' => false, 'erro' => 'Nome obrigatório']);
        check_instance_permission($instanceName, $user, true);

        // 1. API CONNECT
        $res = uazapi_api("/instance/connect", ['instanceName' => (string) $instanceName], 'POST', $token);

        // 2. Extração de QR Robusta
        $qr = $res['base64'] ?? $res['qrcode']['base64'] ?? $res['qrcode'] ?? $res['code'] ?? $res['pairingCode'] ?? $res['data']['qrcode'] ?? null;

        // Se já abriu (já conectou)
        $state = strtolower($res['instance']['status'] ?? $res['status'] ?? $res['state'] ?? $res['data']['status'] ?? '');
        $connected = ($state === 'open' || $state === 'connected');

        json_resposta([
            'ok' => ($connected || !empty($qr)),
            'qrCode' => $qr,
            'connected' => $connected,
            'status' => $state,
            'data' => $res
        ]);
        break;


    case 'qrcode':
        // Only receives token via GET: api('GET', '?action=qrcode&token='...)
        $tokRef = $_GET['token'] ?? '';
        $pdo = get_db_connection();
        $instNameQ = '';
        if ($pdo && $tokRef) {
            $sT = $pdo->prepare("SELECT instance_name FROM crm_instances WHERE instance_token = ?");
            $sT->execute([$tokRef]);
            $instNameQ = $sT->fetchColumn() ?: '';
        }
        if (!$instNameQ) {
            json_resposta(['ok' => false]);
        }
        check_instance_permission($instNameQ, $user, true);
        $res = uazapi_api("/instance/connect", ['instanceName' => $instNameQ], 'POST', $tokRef);
        $qrB64 = $res['base64'] ?? $res['qrcode']['base64'] ?? $res['qrcode'] ?? $res['code'] ?? $res['pairingCode'] ?? null;
        json_resposta(['ok' => !empty($qrB64), 'qrCode' => $qrB64]);
        break;

    case 'desconectar':
        $instanceName = $body['name'] ?? $body['instance'] ?? $body['instanceName'] ?? '';
        if (empty($instanceName))
            json_resposta(['ok' => false, 'erro' => 'Nome obrigatório']);
        check_instance_permission($instanceName, $user, true);
        $res = uazapi_api("/instance/disconnect", ['instanceName' => $instanceName], 'POST');
        json_resposta(['ok' => true, 'data' => $res]);
        break;

    case 'excluir':
        $instanceName = $body['name'] ?? '';
        check_instance_permission($instanceName, $user, true);
        // Uazapi Cloud: DELETE /instance/delete?instance=NAME
        $res = uazapi_api("/instance/delete?instanceName={$instanceName}", [], 'DELETE');
        json_resposta(['ok' => true, 'data' => $res]);
        break;

    case 'webhook_get':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $name = $body['instance'] ?? $body['name'] ?? $body['instanceName'] ?? '';
        if (empty($name))
            $name = $_GET['name'] ?? $_GET['instance'] ?? '';
        check_instance_permission($name, $user, true);

        $res = uazapi_api("/webhook?instanceName={$name}", [], 'GET');
        // Merge com dados do DB (fallback)
        $pdo = get_db_connection();
        if ($pdo) {
            $stmtDb = $pdo->prepare("SELECT webhook_url, webhook_enabled FROM crm_instances WHERE instance_name = ?");
            $stmtDb->execute([$name]);
            $dbRow = $stmtDb->fetch(PDO::FETCH_ASSOC);
            if ($dbRow && empty($res['url'])) {
                $res['url'] = $dbRow['webhook_url'] ?? '';
                $res['enabled'] = (bool) ($dbRow['webhook_enabled'] ?? false);
            }
        }
        json_resposta(['ok' => true, 'data' => $res]);
        break;

    case 'webhook_set':
        if (!$user)
            json_resposta(['ok' => false], 401);

        $instName = $body['instance'] ?? $body['name'] ?? $body['instanceName'] ?? '';
        $url = $body['url'] ?? '';
        $enabled = (bool) ($body['enabled'] ?? true);
        $events = $body['events'] ?? ['messages', 'labels', 'chat_labels'];
        $exclude = $body['excludeMessages'] ?? [];

        if (empty($instName))
            json_resposta(['ok' => false, 'erro' => 'Nome da instância ausente']);

        $payload = [
            'instanceName' => (string) $instName,
            'enabled' => $enabled,
            'url' => $url,
            'events' => $events,
            'excludeMessages' => $exclude
        ];

        $res = uazapi_api("/webhook", $payload, 'POST');

        // Sync com DB local
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("UPDATE crm_instances SET webhook_url = ?, webhook_enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE instance_name = ?");
            $stmt->execute([$url, (int) $enabled, $instName]);
        }

        json_resposta([
            'ok' => $res['ok'] ?? $res['success'] ?? ($res['status'] === 'SUCCESS') ?? true,
            'data' => $res,
            'sync' => true
        ]);
        break;

    // ── PROXY GET ────────────────────────────────────────────────────────────
    case 'proxy_get':
    case 'proxy_ver':
        $tokRef = $body['token'] ?? $_GET['token'] ?? '';
        $instNameProxy = $body['name'] ?? $_GET['name'] ?? '';

        try {
            $pdo = get_db_connection();
            if (!$pdo)
                json_resposta(['ok' => false, 'erro' => 'DB offline']);

            $stmt = $pdo->prepare("SELECT proxy_host, proxy_port, proxy_protocol, proxy_user, proxy_pass FROM crm_instances WHERE instance_token = ? OR instance_name = ?");
            $stmt->execute([$tokRef, $instNameProxy]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            json_resposta([
                'ok' => true,
                'data' => [
                    'host' => $row['proxy_host'] ?? '',
                    'port' => $row['proxy_port'] ?? '',
                    'protocol' => $row['proxy_protocol'] ?? 'http',
                    'username' => $row['proxy_user'] ?? '',
                    'password' => $row['proxy_pass'] ?? ''
                ]
            ]);
        } catch (Exception $e) {
            json_resposta(['ok' => true, 'data' => []]); // Silencioso no polling
        }
        break;



    // ── PROXY REMOVE ─────────────────────────────────────────────────────────
    case 'proxy_remove':
    case 'proxy_del':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $tokRef = $body['token'] ?? '';
        $pdo = get_db_connection();
        $instNameP = $body['name'] ?? $body['instance'] ?? '';
        if ($pdo && $tokRef && empty($instNameP)) {
            $sT = $pdo->prepare("SELECT instance_name FROM crm_instances WHERE instance_token = ?");
            $sT->execute([$tokRef]);
            $instNameP = $sT->fetchColumn() ?: '';
        }
        if (!empty($instNameP))
            check_instance_permission($instNameP, $user, true);
        // Remove proxy na UAZAPI Cloud via DELETE /instance/proxy
        uazapi_api("/instance/proxy?instanceName={$instNameP}", [], 'DELETE');
        if ($pdo && $instNameP) {
            $stmt = $pdo->prepare("UPDATE crm_instances SET proxy_host=NULL, proxy_port=NULL, proxy_protocol=NULL, proxy_username=NULL, proxy_password=NULL, updated_at=CURRENT_TIMESTAMP WHERE instance_name=?");
            $stmt->execute([$instNameP]);
        }
        json_resposta(['ok' => true]);
        break;

    // ── PERFIL GET ───────────────────────────────────────────────────────────
    case 'get_chats':
        $uLogin = requer_login();
        $instance = $body['instance'] ?? $_GET['instance'] ?? '';

        $pdo = get_db_connection();
        if (!$pdo)
            json_resposta(['ok' => false, 'erro' => 'DB Offline']);

        // Se uma instância foi fornecida, sincroniza ela primeiro (Mirroring)
        if (!empty($instance)) {
            check_instance_permission($instance, $uLogin, true);
            sync_chats_to_db($instance);

            $stmt = $pdo->prepare("SELECT * FROM crm_chats WHERE instance_name = ? ORDER BY last_message_timestamp DESC NULLS LAST");
            $stmt->execute([$instance]);
        } else {
            // Global Inbox
            $stmt = $pdo->query("SELECT * FROM crm_chats ORDER BY last_message_timestamp DESC NULLS LAST");
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_resposta(['ok' => true, 'data' => $rows, 'source' => 'local_mirror']);
        break;

    case 'get_messages':
        $uLogin = requer_login();
        $instance = $body['instance'] ?? $_GET['instance'] ?? '';
        $remoteJid = $body['remoteJid'] ?? $_GET['remoteJid'] ?? '';

        if (empty($instance) || empty($remoteJid))
            json_resposta(['ok' => false, 'erro' => 'Instância e JID obrigatórios']);

        check_instance_permission($instance, $uLogin, true);

        // Sincroniza mensagens desta conversa (Mirroring)
        sync_messages_to_db($instance, $remoteJid);

        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT content FROM crm_messages WHERE instance_name = ? AND remote_jid = ? ORDER BY timestamp DESC LIMIT 40");
        $stmt->execute([$instance, $remoteJid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = array_reverse($rows); // Exibe as mensagens na ordem natural (mais velha para a mais nova)

        $msgs = array_map(fn($r) => json_decode($r['content'], true), $rows);
        json_resposta(['ok' => true, 'data' => $msgs, 'source' => 'local_mirror']);
        break;

    case 'send_message':
        $uLogin = requer_login();
        $instance = $body['instance'] ?? $body['instanceName'] ?? '';
        $remoteJid = $body['remoteJid'] ?? '';
        $text = $body['text'] ?? '';

        if (empty($instance) || empty($remoteJid) || empty($text)) {
            json_resposta(['ok' => false, 'erro' => 'Dados incompletos para envio']);
        }

        check_instance_permission($instance, $uLogin, true);

        $payload = [
            'instanceName' => $instance,
            'number' => $remoteJid,
            'text' => $text,
            'delay' => 0,
            'linkPreview' => true
        ];

        $res = uazapi_api("/message/sendText", $payload, 'POST');
        json_resposta(['ok' => isset($res['key']) || isset($res['ok']) || isset($res['message']), 'data' => $res]);
        break;

    case 'chat_read':
        $uLogin = requer_login();
        $instance = $body['instance'] ?? $body['name'] ?? '';
        $remoteJid = $body['remoteJid'] ?? $body['number'] ?? '';
        if (empty($instance) || empty($remoteJid))
            json_resposta(['ok' => false]);

        check_instance_permission($instance, $uLogin, true);

        $res = uazapi_api("/chat/read", [
            'instanceName' => $instance,
            'number' => $remoteJid,
            'read' => true
        ], 'POST');

        json_resposta(['ok' => true, 'data' => $res]);
        break;

    case 'perfil_get':

        $tokRef = $_GET['token'] ?? $body['token'] ?? '';
        $instName = $_GET['name'] ?? $body['name'] ?? '';

        if (empty($instName) && !empty($tokRef)) {
            $pdo = get_db_connection();
            $s = $pdo->prepare("SELECT instance_name FROM crm_instances WHERE instance_token = ?");
            $s->execute([$tokRef]);
            $instName = $s->fetchColumn() ?: '';
        }

        // 1. Tenta API com timeout curto
        $apiRes = uazapi_api("/instance/status?instanceName={$instName}", [], 'GET');
        $data = is_array($apiRes) ? ($apiRes[0] ?? $apiRes) : [];

        $pName = $data['profileName'] ?? $data['pushname'] ?? '';
        $pStatus = $data['about'] ?? $data['status'] ?? '';
        $pPicture = $data['profilePictureUrl'] ?? $data['picture'] ?? '';

        // 2. Fallback / Sync com DB
        $pdo = get_db_connection();
        if ($pdo && !empty($instName)) {
            if (empty($pName) || empty($pPicture)) {
                $sDB = $pdo->prepare("SELECT profile_name, profile_picture_url FROM crm_instances WHERE instance_name = ?");
                $sDB->execute([$instName]);
                $dbRow = $sDB->fetch(PDO::FETCH_ASSOC);
                if ($dbRow) {
                    $pName = $pName ?: $dbRow['profile_name'];
                    $pPicture = $pPicture ?: $dbRow['profile_picture_url'];
                }
            }
            // Atualiza DB se pegamos algo novo da API
            if (!empty($pName) || !empty($pPicture)) {
                $stmt = $pdo->prepare("UPDATE crm_instances SET profile_name = COALESCE(NULLIF(?, ''), profile_name), profile_picture_url = COALESCE(NULLIF(?, ''), profile_picture_url) WHERE instance_name = ?");
                $stmt->execute([$pName, $pPicture, $instName]);
            }
        }

        json_resposta([
            'ok' => true,
            'name' => $pName,
            'status' => $pStatus,
            'picture' => $pPicture
        ]);
        break;

    // ── PERFIL SET ───────────────────────────────────────────────────────────
    case 'perfil_set':
    case 'perfil_name':
    case 'perfil_foto':
        $tokRef = $body['token'] ?? '';
        $nome = trim($body['name'] ?? $body['profileName'] ?? '');
        $status = trim($body['status'] ?? $body['about'] ?? '');
        $instName = $body['instance'] ?? $body['instanceName'] ?? '';

        if (empty($instName) && !empty($tokRef)) {
            $pdo = get_db_connection();
            $s = $pdo->prepare("SELECT instance_name FROM crm_instances WHERE instance_token = ?");
            $s->execute([$tokRef]);
            $instName = $s->fetchColumn() ?: '';
        }

        check_instance_permission($instName, $user, true);

        if ($nome) {
            uazapi_api("/profile/name", ['instanceName' => $instName, 'name' => $nome], 'POST', $tokRef);
            $pdo = get_db_connection();
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE crm_instances SET profile_name = ?, updated_at = CURRENT_TIMESTAMP WHERE instance_name = ?");
                $stmt->execute([$nome, $instName]);
            }
        }
        if ($status) {
            uazapi_api("/profile/status", ['instanceName' => $instName, 'status' => $status], 'POST', $tokRef);
        }

        json_resposta(['ok' => true]);
        break;

    case 'renomear_instancia':
        $oldTokenOrName = $body['old_name'] ?? '';
        $newName = trim($body['new_name'] ?? '');

        if (empty($oldTokenOrName) || empty($newName))
            json_resposta(['ok' => false, 'erro' => 'Dados incompletos']);

        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT instance_name, instance_token FROM crm_instances WHERE instance_name = ? OR instance_token = ?");
        $stmt->execute([$oldTokenOrName, $oldTokenOrName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row)
            json_resposta(['ok' => false, 'erro' => 'Instância não encontrada no banco']);
        $oldName = $row['instance_name'];
        $token = $row['instance_token'];

        check_instance_permission($oldName, $user, true);

        // 1. API RENAME
        $res = uazapi_api('/instance/updateInstanceName', [
            'instanceName' => $oldName,
            'newName' => $newName
        ], 'POST', $token);

        // 2. DB UPDATE
        if ($res['ok'] ?? false || isset($res['instance'])) {
            $stmt = $pdo->prepare("UPDATE crm_instances SET instance_name = ?, updated_at = CURRENT_TIMESTAMP WHERE instance_name = ?");
            $stmt->execute([$newName, $oldName]);

            // Atualiza lista de instâncias do usuário se necessário
            if ($user['role'] !== 'admin') {
                $users = carregar_usuarios();
                $uInsts = $users[$user['username']]['instancias'] ?? [];
                if (($key = array_search($oldName, $uInsts)) !== false) {
                    $uInsts[$key] = $newName;
                    $users[$user['username']]['instancias'] = $uInsts;
                    salvar_usuarios($users);
                    $_SESSION['user']['instancias'] = $uInsts;
                }
            }
        }

        json_resposta($res);
        break;

    case 'save_setting':
        $key = $body['key'] ?? '';
        $val = $body['value'] ?? '';
        if (empty($key))
            json_resposta(['ok' => false, 'erro' => 'Chave ausente']);

        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO crm_settings (username, setting_key, setting_value) 
                                   VALUES (?, ?, ?) 
                                   ON CONFLICT (username, setting_key) DO UPDATE SET 
                                   setting_value = EXCLUDED.setting_value, 
                                   updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$user['username'], $key, json_encode($val)]);
            json_resposta(['ok' => true]);
        }
        json_resposta(['ok' => false, 'erro' => 'DB offline']);
        break;

    case 'get_setting':
        $key = $_GET['key'] ?? '';
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT setting_value FROM crm_settings WHERE username = ? AND setting_key = ?");
            $stmt->execute([$user['username'], $key]);
            $res = $stmt->fetchColumn();
            json_resposta(['ok' => true, 'value' => $res ? json_decode($res, true) : null]);
        }
        json_resposta(['ok' => false, 'value' => null]);
        break;

    // --- NOVOS ENDPOINTS CRM CLOUD ---
    case 'chat_labels':
        $name = $body['instance'] ?? '';
        check_instance_permission($name, $user, true);
        $res = uazapi_api("/chat/labels", [
            'instance' => $name,
            'number' => $body['number'] ?? '',
            'labels' => $body['labels'] ?? []
        ], 'POST');

        // Opcional: Se for uma atualização global ou se quisermos sync imediato
        sync_instance_labels($name);

        json_resposta($res);
        break;


    // ─── CHECKOUT MÁQUINA DE VENDAS (PIX/BOLETO) ───────────
    case 'checkout_generate':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $instToken = trim($body['instancia_token'] ?? '');
        if (empty($instToken))
            json_resposta(['ok' => false, 'erro' => 'Token da Instância Ausente']);

        check_instance_permission($instToken, $user, false);

        // Dispara Webhook PRO N8N processar
        // O N8N deve ter um Webhook Receiver nesta URL genérica ou pode estar configurado em PUSH_DATA_FILE
        $n8nWebhookUrl = "http://localhost:5678/webhook/uazapi-checkout";

        // Pega URL dinâmica se houver salva nas configs do Admin (Push/N8n Settings)
        $pd = json_decode(@file_get_contents(PUSH_DATA_FILE), true);
        if (!empty($pd['settings']['n8n_webhook'])) {
            // Troca o sufixo uazapi-push do n8n por uazapi-checkout
            $baseWebhook = explode('?', $pd['settings']['n8n_webhook'])[0];
            $baseWebhook = str_replace(basename($baseWebhook), 'uazapi-checkout', $baseWebhook);
            $n8nWebhookUrl = $baseWebhook;
        }

        $payload = [
            'method' => $body['method'] ?? 'pix',
            'valor' => floatval($body['valor'] ?? 0),
            'nome' => $body['nome'] ?? 'Cliente Padrão',
            'cpf' => $body['cpf'] ?? '',
            'telefone' => $body['telefone'] ?? '',
            'instancia_token' => $instToken,
            'user' => $user['username']
        ];

        $ch = curl_init($n8nWebhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $curl_resp = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            json_resposta(['ok' => true, 'msg' => 'Enviado N8N.']);
        } else {
            // Pode ser que o usuario não tenha criado o webhook no n8n.
            // Para não quebrar o UX (já que estamos sendo saas), damos 'ok' mas gravamos no log e mostramos fake error
            log_acao($user['username'], 'CHECKOUT_FAIL', "Webhook do N8N em $n8nWebhookUrl não respondeu com sucesso. Código: $http_code");
            json_resposta(['ok' => true, 'msg' => 'Gerador Offline no momento. Acionado modo fallback.']);
        }
        break;


    case 'proxy_set':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $token = $body['token'] ?? '';
        if (empty($token))
            json_resposta(['ok' => false, 'erro' => 'Token da instância ausente']);

        check_instance_permission($token, $user, false);

        $host = trim($body['host'] ?? '');
        $port = trim($body['port'] ?? '');
        $protocol = strtolower(trim($body['protocol'] ?? 'http'));
        $user_proxy = trim($body['username'] ?? '');
        $pass_proxy = trim($body['password'] ?? '');

        // Prepara objeto UAZ API / proxy
        if (empty($host)) {
            $payload = ['proxy' => []];
        } else {
            $payload = [
                'proxy' => [
                    'host' => $host,
                    'port' => (int) $port,
                    'protocol' => $protocol,
                    'username' => $user_proxy,
                    'password' => $pass_proxy
                ]
            ];
        }

        // POST /instance/proxy/:instanceName
        $res = uazapi_api("/instance/proxy", array_merge(['instanceName' => $token], $payload), 'POST');
        if (isset($res['error']) && $res['error']) {
            json_resposta(['ok' => false, 'erro' => $res['message'] ?? 'Erro na UAZ API ao configurar Proxy']);
        }

        // Formata a URL crua para salvar visualmente local
        $auth = (!empty($user_proxy)) ? "{$user_proxy}:{$pass_proxy}@" : "";
        $raw_url = empty($host) ? "" : "{$protocol}://{$auth}{$host}:{$port}";

        // Tenta salvar localmente para dashboard Wazio
        $arquivoDB = PROXY_DATA_FILE;
        $proxies = file_exists($arquivoDB) ? json_decode(file_get_contents($arquivoDB), true) : [];
        if (empty($host)) {
            unset($proxies[$token]);
        } else {
            $proxies[$token] = [
                'host' => $host,
                'port' => $port,
                'protocol' => $protocol,
                'username' => $user_proxy,
                'password' => $pass_proxy,
                'url' => $raw_url
            ];
        }
        file_put_contents($arquivoDB, json_encode($proxies, JSON_PRETTY_PRINT));

        // Salva DB Postgres
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("UPDATE crm_instances SET proxy_host = ?, proxy_port = ?, proxy_user = ?, proxy_pass = ?, proxy_protocol = ? WHERE instance_token = ? OR instance_name = ?");
            if (empty($host)) {
                $stmt->execute([null, null, null, null, null, $token, $token]);
            } else {
                $stmt->execute([$host, $port, $user_proxy, $pass_proxy, $protocol, $token, $token]);
            }
        }

        json_resposta(['ok' => true, 'msg' => empty($host) ? 'Proxy removida com sucesso!' : 'Proxy configurada com sucesso!', 'api' => $res]);
        break;

    case 'testar_proxy':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $host = trim($body['host'] ?? '');
        $port = trim($body['port'] ?? '');
        $protocol = strtolower(trim($body['protocol'] ?? 'http'));
        $user_proxy = trim($body['user'] ?? '');
        $pass_proxy = trim($body['pass'] ?? '');

        if (empty($host) || empty($port)) {
            json_resposta(['ok' => false, 'erro' => 'Host ou porta inválidos']);
        }

        // URL para testar a geolocalização e confirmar conectividade por DENTRO do túnel
        $testUrl = "http://ip-api.com/json/?lang=pt-BR";

        $ch = curl_init($testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_PROXY, "$host:$port");

        if (!empty($user_proxy) || !empty($pass_proxy)) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$user_proxy:$pass_proxy");
        }

        if (str_contains($protocol, 'socks5')) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        } elseif (str_contains($protocol, 'socks4')) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
        } else {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        }

        $startTime = microtime(true);
        $res = curl_exec($ch);
        $endTime = microtime(true);
        $latency = round(($endTime - $startTime) * 1000);

        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            json_resposta(['ok' => false, 'erro' => "Falha na conexão do proxy: $err"]);
        }

        $geo = json_decode($res, true);
        if ($geo && isset($geo['status']) && $geo['status'] === 'success') {
            json_resposta(['ok' => true, 'msg' => 'Proxy Online!', 'geo' => $geo, 'latency' => $latency]);
        } else {
            json_resposta(['ok' => true, 'msg' => 'Proxy Online (mas sem dados geo)', 'raw' => $res, 'latency' => $latency]);
        }
        break;

    case 'testar_webhook':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $urlWebhook = $body['url'] ?? '';
        if (empty($urlWebhook) || !filter_var($urlWebhook, FILTER_VALIDATE_URL)) {
            json_resposta(['ok' => false, 'erro' => 'URL inválida ou vazia']);
        }

        $n8nTestUrl = $urlWebhook; // Faz o ping direto para a URL do usuário
        $ch = curl_init($n8nTestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'event' => 'connection.update',
            'instance' => 'Teste-Painel',
            'data' => ['state' => 'open', 'statusReason' => 200],
            'message' => 'Ping de teste do Painel WAZIO'
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $res = curl_exec($ch);
        $erroCurl = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false) {
            json_resposta(['ok' => false, 'erro' => 'Falha de rede ao pingar URL: ' . $erroCurl]);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            json_resposta(['ok' => true, 'msg' => 'Sucesso! Webhook respondeu com HTTP ' . $httpCode]);
        } else {
            json_resposta(['ok' => false, 'erro' => "Falha no Webhook (Status $httpCode)"]);
        }
        break;

    case 'meu_perfil_legacy':

    case 'usuarios_listar':
        if (!$user || $user['role'] !== 'admin')
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);
        $users = carregar_usuarios();
        $safe = array_map(fn($u) => array_diff_key($u, ['password' => '']), $users);
        json_resposta(['ok' => true, 'data' => array_values($safe)]);
        break;

    case 'usuario_criar':
    case 'usuario_editar':
        if (!$user || $user['role'] !== 'admin')
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);
        $users = carregar_usuarios();
        $target = $body['username'] ?? '';

        $novoDados = [
            'username' => $target,
            'nome' => $body['nome'] ?? '',
            'role' => $body['role'] ?? 'user',
            'ativo' => true,
            'instancias' => $body['instancias'] ?? [],
            'modulos' => $body['modulos'] ?? [],
            'hidden_instances' => $users[$target]['hidden_instances'] ?? [],
            'limite_instancias' => intval($body['limite_instancias'] ?? 10)
        ];

        if (!empty($body['password'])) {
            $novoDados['password'] = password_hash($body['password'], PASSWORD_DEFAULT);
        } elseif ($action === 'usuario_editar') {
            $novoDados['password'] = $users[$target]['password'];
        }

        $users[$target] = $novoDados;
        salvar_usuarios($users);
        json_resposta(['ok' => true]);
        break;

    case 'meu_perfil':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $novoNome = $body['nome'] ?? '';
        $novaSenha = $body['password'] ?? '';

        $pdo = get_db_connection();
        if ($pdo) {
            $sql = "UPDATE crm_users SET full_name = ?, updated_at = CURRENT_TIMESTAMP";
            $params = [$novoNome];
            if (!empty($novaSenha)) {
                $sql .= ", password_hash = ?";
                $params[] = password_hash($novaSenha, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE username = ?";
            $params[] = $user['username'];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Atualiza sessão e fallback
            $_SESSION['user']['nome'] = $novoNome;
            $users = carregar_usuarios();
            if (isset($users[$user['username']])) {
                $users[$user['username']]['nome'] = $novoNome;
                if (!empty($novaSenha))
                    $users[$user['username']]['password'] = password_hash($novaSenha, PASSWORD_DEFAULT);
                salvar_usuarios($users);
            }
        }
        json_resposta(['ok' => true]);
        break;



    case 'usuario_excluir':
        if (!$user || $user['role'] !== 'admin')
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);

        $targetUser = $body['username'] ?? '';
        if (empty($targetUser))
            json_resposta(['ok' => false, 'erro' => 'Usuário não especificado']);

        // 1. Deleta do Banco de Dados Postgres
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM crm_users WHERE username = ?");
            $stmt->execute([$targetUser]);
        }

        // 2. Deleta do JSON Fallback
        $users = carregar_usuarios();
        if (isset($users[$targetUser])) {
            unset($users[$targetUser]);
            salvar_usuarios($users);
        }

        json_resposta(['ok' => true]);
        break;


    case 'logout':
        if ($user)
            log_acao($user['username'], 'LOGOUT');
        session_destroy();
        json_resposta(['ok' => true]);
        break;

    case 'logs':
        if (!$user || $user['role'] !== 'admin')
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);
        $linhas = file_exists(LOG_FILE) ? array_slice(file(LOG_FILE, FILE_IGNORE_NEW_LINES), -200) : [];
        json_resposta(['ok' => true, 'data' => array_reverse($linhas)]);
        break;

    case 'logs_sistema':
        if (!$user || $user['role'] !== 'admin')
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);

        $pdo = get_db_connection();
        if (!$pdo)
            json_resposta(['ok' => false, 'erro' => 'DB offline']);
        try {
            $stmt = $pdo->query("SELECT * FROM crm_system_logs ORDER BY created_at DESC LIMIT 100");
            $erros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_resposta(['ok' => true, 'data' => $erros]);
        } catch (Exception $e) {
            json_resposta(['ok' => true, 'data' => [], 'aviso' => 'Tabela crm_system_logs não encontrada. Execute /wazio/setup-db']);
        }
        break;

    case 'fin_listar':
        if (!$user || ($user['role'] !== 'admin' && !in_array('financeiro', $user['modulos'] ?? []))) {
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);
        }
        $pdo = get_db_connection();
        if (!$pdo) {
            json_resposta(['ok' => false, 'erro' => 'Erro de conexão com o banco'], 500);
        }
        $stmt = $pdo->query("SELECT * FROM crm_finance ORDER BY transaction_date DESC");
        $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados = [];
        foreach ($transacoes as $t) {
            $dados[] = [
                'id' => $t['id'],
                'descricao' => $t['description'] ?? '',
                'valor' => (float) ($t['amount'] ?? 0),
                'data' => isset($t['transaction_date']) ? date('Y-m-d', strtotime($t['transaction_date'])) : '',
                'tipo' => $t['transaction_type'] ?? '',
                'status' => $t['status'] ?? '',
                'categoria' => $t['category'] ?? ''
            ];
        }

        json_resposta(['ok' => true, 'data' => $dados]);
        break;

    case 'fin_salvar':
        if (!$user || ($user['role'] !== 'admin' && !in_array('financeiro', $user['modulos'] ?? []))) {
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);
        }

        $pdo = get_db_connection();
        $id = $body['id'] ?? '';
        $descricao = $body['descricao'] ?? 'Sem descrição';
        $valor = floatval($body['valor'] ?? 0);
        $data = $body['data'] ?? date('Y-m-d');
        $tipo = $body['tipo'] ?? 'receita';
        $status = $body['status'] ?? 'pago';

        if ($id && is_numeric($id)) {
            $stmt = $pdo->prepare("UPDATE crm_finance SET description = ?, amount = ?, transaction_date = ?, transaction_type = ?, status = ? WHERE id = ?");
            $stmt->execute([$descricao, $valor, $data . ' 00:00:00', $tipo, $status, $id]);
            $novoLancamentoId = $id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO crm_finance (description, amount, transaction_date, transaction_type, status, user_id) VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
            $stmt->execute([$descricao, $valor, $data . ' 00:00:00', $tipo, $status, $user['user_id'] ?? 1]);
            $resInsert = $stmt->fetch(PDO::FETCH_ASSOC);
            $novoLancamentoId = $resInsert['id'] ?? null;

            // 🚀 GATILHO PUSH FINANCEIRO
            $vFormatado = number_format($valor, 2, ',', '.');
            if (strtolower($tipo) === 'entrada' || strtolower($tipo) === 'receita') {
                $msg = "Nova entrada registrada no valor de R$ {$vFormatado}!\n\nReferência: " . $descricao;
                disparar_alerta_n8n('entrada', "💵 Venda Registrada!", $msg, ['valor' => $valor, 'tipo' => $tipo]);
            } elseif (strtolower($tipo) === 'saida' || strtolower($tipo) === 'despesa') {
                $msg = "Nova despesa registrada no valor de R$ {$vFormatado}!\n\nReferência: " . $descricao;
                disparar_alerta_n8n('saida', "📉 Despesa Registrada!", $msg, ['valor' => $valor, 'tipo' => $tipo]);
            }
        }

        json_resposta(['ok' => true, 'id' => $novoLancamentoId]);
        break;

    case 'fin_excluir':
        if (!$user || ($user['role'] !== 'admin' && !in_array('financeiro', $user['modulos'] ?? []))) {
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);
        }

        $pdo = get_db_connection();
        $idDeletar = $body['id'] ?? '';

        if ($idDeletar && is_numeric($idDeletar)) {
            $stmt = $pdo->prepare("DELETE FROM crm_finance WHERE id = ?");
            $stmt->execute([$idDeletar]);
        }

        json_resposta(['ok' => true]);
        break;

    case 'db_schema':
        if (!$user || $user['role'] !== 'admin') {
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);
        }
        $tableName = $_GET['table'] ?? '';
        if (empty($tableName)) {
            json_resposta(['ok' => false, 'erro' => 'Tabela não informada'], 400);
        }

        $pdo = get_db_connection();
        if (!$pdo) {
            json_resposta(['ok' => false, 'erro' => 'Erro de conexão com o banco'], 500);
        }

        $stmt = $pdo->prepare("
            SELECT 
                column_name as name, 
                data_type as type, 
                is_nullable as nullable, 
                column_default as default
            FROM information_schema.columns 
            WHERE table_name = ? 
            ORDER BY ordinal_position
        ");
        $stmt->execute([$tableName]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($columns)) {
            json_resposta(['ok' => false, 'erro' => 'Tabela não encontrada ou sem colunas'], 404);
        }

        json_resposta(['ok' => true, 'schema' => $columns]);
        break;

    // ==========================================
    // 🔔 ROTAS: PUSH ENGINE (ONESIGNAL)
    // ==========================================

    case 'push_testar':
        if (!$user || $user['role'] !== 'admin')
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);

        $res = disparar_alerta_n8n('entrada', "🚀 Teste de Conexão WAZ.IO", "Sua engine de Push via PostgreSQL e N8N está operante e blindada.");
        if ($res) {
            json_resposta(['ok' => true]);
        } else {
            json_resposta(['ok' => false, 'erro' => 'Falha no disparo. Verifique se a URL do N8N foi salva nas Configurações.']);
        }
        break;

    // Gatilho Proxy Desconectada (Chamado pelo dashboard.js)
    case 'push_proxy_alert':
        if (!$user)
            json_resposta(['ok' => false], 401);

        $instanciaNome = $body['nome'] ?? 'Desconhecida';
        disparar_alerta_n8n('proxy', "⚠️ ALERTA DE PROXY (TÚNEL)", "A proxy atrelada a instância [{$instanciaNome}] parou de responder ou foi recusada!");
        json_resposta(['ok' => true]);
        break;

    // Gatilho Instancia Desconectada
    case 'push_instancia_alert':
        if (!$user)
            json_resposta(['ok' => false], 401);

        $instanciaNome = $body['nome'] ?? 'Desconhecida';
        disparar_alerta_n8n('instancia', "❌ Instância Desconectada!", "{$instanciaNome} foi desconectada e precisa de leitura do QR Code urgente!", ['instancia_nome' => $instanciaNome]);
        json_resposta(['ok' => true]);
        break;

    // Gatilho Instancia Conectada
    case 'push_instancia_conectada':
        if (!$user)
            json_resposta(['ok' => false], 401);

        $instanciaNome = $body['nome'] ?? 'Desconhecida';
        disparar_alerta_n8n('conectada', "✅ Instância Conectada!", "{$instanciaNome} está online e pronta para uso!", ['instancia_nome' => $instanciaNome]);
        json_resposta(['ok' => true]);
        break;

    // ── Salvar configurações de push (toggles) ──────────
    case 'salvar_settings':
        $pdo = get_db_connection();
        $uname = $user['username'] ?? 'admin';

        $pushSettings = [
            'master_enabled' => (bool) ($body['master_enabled'] ?? true),
            'entradas' => (bool) ($body['entradas'] ?? true),
            'saidas' => (bool) ($body['saidas'] ?? true),
            'instancias' => (bool) ($body['instancias'] ?? true),
            'instancias_conectadas' => (bool) ($body['instancias_conectadas'] ?? false),
            'admin_receber_todas' => (bool) ($body['admin_receber_todas'] ?? false),
            'proxies' => (bool) ($body['proxies'] ?? true)
        ];

        $stmt = $pdo->prepare("INSERT INTO crm_settings (username, setting_key, setting_value) VALUES (?, 'n8n_push_settings', ?)
                                ON CONFLICT (username, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
        $stmt->execute([$uname, json_encode($pushSettings)]);

        json_resposta(['ok' => true]);
        break;

    // ── Salvar device token ──────────────────────────────────────
    case 'salvar_device':
        $tok = trim($body['token'] ?? '');
        $uname = $user['username'] ?? 'unknown';
        if (!$tok)
            json_resposta(['ok' => false, 'erro' => 'Token vazio']);

        $pdo = get_db_connection();
        $stmt = $pdo->prepare("INSERT INTO crm_push_tokens (username, device_id, token, label) 
                                VALUES (?, ?, ?, 'Browser')
                                ON CONFLICT (device_id) DO UPDATE SET token = EXCLUDED.token, updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$uname, $tok, $tok]);

        json_resposta(['ok' => true]);
        break;

    // ── Remover device token ──────────────────────────────────────
    case 'remover_device':
        $tok = trim($body['token'] ?? '');
        if (!$tok)
            json_resposta(['ok' => false, 'erro' => 'Token vazio']);

        $pdo = get_db_connection();
        $stmt = $pdo->prepare("DELETE FROM crm_push_tokens WHERE token = ? OR device_id = ?");
        $stmt->execute([$tok, $tok]);

        json_resposta(['ok' => true]);
        break;

    // Salvar token push do OneSignal do usuário
    case 'push_save_token':
        $deviceId = $body['deviceId'] ?? '';
        $tokenPush = $body['token'] ?? '';
        $label = $body['label'] ?? 'Dispositivo';
        $uname = $user['username'] ?? 'unknown';

        if (!$deviceId && !$tokenPush) {
            json_resposta(['ok' => false, 'erro' => 'Token ausente']);
        }

        $pdo = get_db_connection();
        $stmt = $pdo->prepare("INSERT INTO crm_push_tokens (username, device_id, token, label) 
                                VALUES (?, ?, ?, ?)
                                ON CONFLICT (device_id) DO UPDATE SET 
                                    token = EXCLUDED.token, 
                                    label = EXCLUDED.label, 
                                    username = EXCLUDED.username,
                                    updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$uname, $deviceId, $tokenPush, $label]);

        json_resposta(['ok' => true, 'msg' => 'Token salvo no Postgres']);
        break;

    // Salvar configurações globais de Push (Admin Only)
    case 'push_save_global_settings':
        if (($user['role'] ?? '') !== 'admin') {
            json_resposta(['ok' => false, 'erro' => 'Acesso negado']);
        }

        $onesignal_id = $body['onesignal_id'] ?? '';
        $safari_web_id = $body['safari_web_id'] ?? '';
        $n8n_webhook = $body['n8n_webhook'] ?? '';

        $pdo = get_db_connection();

        // Salva Webhook N8N
        $stmtW = $pdo->prepare("INSERT INTO crm_settings (username, setting_key, setting_value) VALUES ('admin', 'n8n_webhook', ?)
                                ON CONFLICT (username, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
        $stmtW->execute([json_encode(['value' => $n8n_webhook])]);

        // Salva OneSignal ID e Safari
        $pushSettings = [
            'onesignal_id' => $onesignal_id,
            'safari_web_id' => $safari_web_id,
            'master_enabled' => true
        ];
        $stmtP = $pdo->prepare("INSERT INTO crm_settings (username, setting_key, setting_value) VALUES ('admin', 'n8n_push_settings', ?)
                                ON CONFLICT (username, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
        $stmtP->execute([json_encode($pushSettings)]);

        json_resposta(['ok' => true, 'msg' => 'Configurações mestres salvas no Postgres']);
        break;

    // Old qrcode case removed (duplicate)

    case 'media_downloader':
        $url_video = $body['url'] ?? '';
        $isAudio = $body['audio'] ?? false;

        if (empty($url_video)) {
            json_resposta(['ok' => false, 'erro' => 'Nenhuma URL de mídia informada']);
        }

        // TENTATIVA 1: SPECÍFICO PARA TIKTOK (TikWM API) - 100% Free / No Limits
        if (strpos($url_video, 'tiktok.com') !== false || strpos($url_video, 'vt.tiktok.com') !== false) {
            $ch = curl_init('https://www.tikwm.com/api/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['url' => $url_video, 'hd' => 1]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $res = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($res, true);
            if (!empty($data['data']['play']) || !empty($data['data']['hdplay']) || !empty($data['data']['music'])) {
                $chosen = !empty($data['data']['hdplay']) ? $data['data']['hdplay'] : $data['data']['play'];
                $ext = '.mp4';
                if ($isAudio && !empty($data['data']['music'])) {
                    $chosen = $data['data']['music'];
                    $ext = '.mp3';
                }
                json_resposta([
                    'ok' => true,
                    'download_url' => $chosen,
                    'title' => ($data['data']['title'] ?? 'TikTok_Extraido') . $ext,
                    'thumbnail' => $data['data']['origin_cover'] ?? ''
                ]);
            }
        }

        // TENTATIVA 2: COBALT MIRRORS (Alta Resiliência Anti-Block)
        // Usamos as payloads V6 que os mirrors da comunidade ainda mantêm ativas
        $payload = [
            'url' => $url_video,
            'vQuality' => '1080',
            'isAudioOnly' => $isAudio,
            'aFormat' => 'mp3'
        ];

        $cobalt_instances = [
            'https://co.wuk.sh',
            'https://cobalt.api.zluo.de',
            'https://dl.ohmyz.sh',
            'https://cobalt.q0.wtf',
            'https://cobalt.tools.x0.tf',
            'https://cobalt.c7e.uk'
        ];

        foreach ($cobalt_instances as $host) {
            $ch = curl_init("{$host}/api/json");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json',
                "Origin: {$host}",
                "Referer: {$host}/",
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $res = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode == 200 || $httpcode == 201) {
                $data = json_decode($res, true);
                if (!empty($data['url'])) {
                    json_resposta([
                        'ok' => true,
                        'download_url' => $data['url'],
                        'title' => 'Midia.' . ($isAudio ? 'mp3' : 'mp4')
                    ]);
                }
            }
        }

        // TENTATIVA 3 (Fallback Supremo P/ YouTube via YtDlp APIs públicas)
        if (strpos(strtolower($url_video), 'youtu') !== false) {
            // Usa bk9 ou vreden caso o Cobalt falhe massivamente
            $yt_fallbacks = [
                "https://api.vreden.my.id/api/ytmp4?url=" . urlencode($url_video),
                "https://api.bk9.site/download/youtube?url=" . urlencode($url_video)
            ];
            foreach ($yt_fallbacks as $fb) {
                $ch = curl_init($fb);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $res = curl_exec($ch);
                curl_close($ch);
                if ($res) {
                    $dp = json_decode($res, true);
                    $dUrl = $dp['data']['url'] ?? $dp['BK9']['url'] ?? $dp['result']['link'] ?? null;
                    if (is_string($dUrl) && filter_var($dUrl, FILTER_VALIDATE_URL)) {
                        json_resposta([
                            'ok' => true,
                            'download_url' => $dUrl,
                            'title' => 'YouTube_Download.' . ($isAudio ? 'mp3' : 'mp4')
                        ]);
                    }
                }
            }
        }

        // TENTATIVA 4: ULTIMATE FALLBACK (yt-dlp local)
        // O servidor possui o yt-dlp instalado. Se todas as APIs de terceiros e Cobalt bloquearem sua request
        // O próprio backend do painel rouba a mídia diretamente da rede usando a engenharia do yt-dlp
        if (function_exists('shell_exec')) {
            $format = $isAudio ? "-x --audio-format mp3" : "";
            $cmd = "yt-dlp -j --no-warnings " . escapeshellarg($url_video) . " 2>&1";
            $out = shell_exec($cmd);
            if ($out) {
                // Remove warnings/erros e pega apenas as linhas json válidas geradas pelo -j
                $lines = explode("\n", trim($out));
                $jsonStr = '';
                foreach ($lines as $line) {
                    if (strpos($line, '{') === 0) {
                        $jsonStr = $line;
                        break;
                    }
                }

                if ($jsonStr) {
                    $dlpData = json_decode($jsonStr, true);
                    $dUrl = $dlpData['url'] ?? null;
                    if ($dUrl && filter_var($dUrl, FILTER_VALIDATE_URL)) {
                        json_resposta([
                            'ok' => true,
                            'download_url' => $dUrl,
                            'title' => ($dlpData['title'] ?? 'Download_Direto') . ($isAudio ? '.mp3' : '.mp4'),
                            'thumbnail' => $dlpData['thumbnail'] ?? ''
                        ]);
                    }
                }
            }
        }

        json_resposta(['ok' => false, 'erro' => 'Nenhuma API Gratuita ou Local (Cobalt/YTDLP) conseguiu extrair. O vídeo pode ser privado, restrito a login, ou bloqueado por direitos autorais.']);
        break;

    case 'n8n_proxy':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $slug = $body['slug'] ?? '';
        $data = $body['data'] ?? [];

        if (empty($slug))
            json_resposta(['ok' => false, 'erro' => 'Slug do fluxo não informado']);

        // Se o slug começar com http(s), significa que é uma url de Webhook direta de outra instância N8N do usuário.
        if (str_starts_with($slug, 'http://') || str_starts_with($slug, 'https://')) {
            $ch = curl_init($slug);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Motores de Mídia demoram a responder
            $raw = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($raw, true);
            if (!is_array($res))
                $res = ['ok' => false, 'erro' => 'Falha severa de rede no N8N externo', 'debug' => $raw];

        } else {
            // Encaminha para a função original n8n que já lida com curl base de api interna
            $res = n8n($slug, $data, 'POST');
        }

        json_resposta([
            'ok' => $res['ok'] ?? $res['success'] ?? true,
            'data' => $res['data'] ?? $res,
            'erro' => $res['error'] ?? $res['message'] ?? null
        ]);
        break;

    case 'save_flow':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $instanceId = trim($body['instance_id'] ?? '');
        $flowName = trim($body['name'] ?? 'Novo Fluxo');
        $nodes = json_encode($body['nodes'] ?? []);
        $edges = json_encode($body['edges'] ?? []);
        $isActive = ($body['is_active'] ?? true) ? 'true' : 'false';

        if (empty($instanceId)) {
            json_resposta(['ok' => false, 'erro' => 'instance_id ausente. Selecione uma instância para vincular este fluxo.']);
        }

        // Em actions de fluxo, o instanceId é na verdade o instanceName
        check_instance_permission($instanceId, $user, true);

        $pdo = get_db_connection();

        if ($pdo) {
            // Persistência Avançada via PostgreSQL
            try {
                // Remove fluxo anterior da mesma instância com mesmo nome antes de inserir
                $stmtDel = $pdo->prepare("DELETE FROM crm_funnels WHERE instance_name = ? AND name = ?");
                $stmtDel->execute([$instanceId, $flowName]);

                $stmt = $pdo->prepare("INSERT INTO crm_funnels (user_id, instance_name, name, nodes, edges, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user['user_id'] ?? 1,
                    $instanceId,
                    $flowName,
                    $nodes,
                    $edges,
                    $isActive === 'true' ? 'active' : 'inactive'
                ]);

                json_resposta(['ok' => true, 'msg' => 'Fluxo salvo no Postgres com sucesso!']);
            } catch (Exception $e) {
                // Cai para o fallback se a tabela não existir ou erro de SQL
                log_acao($user['username'], 'DB_SAVE_FLOW_FAIL', $e->getMessage());
            }
        }

        // 🛡️ Fallback JSON caso o banco não esteja criado ainda
        $flowDir = __DIR__ . '/../../core/database/flows';
        if (!is_dir($flowDir))
            @mkdir($flowDir, 0755, true);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $flowName);
        $flowFile = $flowDir . "/flow_{$instanceId}_{$safeName}.json";

        file_put_contents($flowFile, json_encode([
            'instance_id' => $instanceId,
            'name' => $flowName,
            'nodes' => json_decode($nodes, true),
            'edges' => json_decode($edges, true),
            'is_active' => $isActive,
            'updated_at' => time()
        ], JSON_PRETTY_PRINT));

        json_resposta(['ok' => true, 'msg' => 'Fluxo salvo localmente (JSON Fallback)!']);
        break;

    case 'delete_flow':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $filename = trim($body['filename'] ?? '');
        if (empty($filename)) {
            json_resposta(['ok' => false, 'erro' => 'Nome do arquivo ausente.']);
        }

        $parts = explode('_', str_replace('.json', '', str_replace('flow_', '', $filename)), 2);
        if (count($parts) === 2) {
            $instId = $parts[0]; // InstanceName
            check_instance_permission($instId, $user, true);
        }

        // Tenta remover do banco PostgreSQL, caso exista
        $pdo = get_db_connection();
        if ($pdo && count($parts) === 2) {
            try {
                $fName = $parts[1];
                $stmtDb = $pdo->prepare("DELETE FROM crm_funnels WHERE instance_name = ? AND name = ?");
                $stmtDb->execute([$instId, $fName]);
            } catch (Exception $e) { /* ignore */
            }
        }

        // Deleta do Fallback
        $flowDir = __DIR__ . '/../../core/database/flows';
        $path = $flowDir . '/' . basename($filename);
        if (file_exists($path)) {
            @unlink($path);
        }

        json_resposta(['ok' => true, 'msg' => 'Fluxo removido com sucesso.']);
        break;

    case 'run_ffmpeg':
        $mode = $_POST['mode'] ?? 'metadados';
        $exif = $_POST['exif'] ?? '0';
        $gps = $_POST['gps'] ?? '0';
        $hash = $_POST['hash'] ?? '0';

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            json_resposta(['ok' => false, 'erro' => 'Falha no Upload do Arquivo JS. Verifique tamanho limite do PHP (upload_max_filesize).']);
        }

        $tmpPath = $_FILES['file']['tmp_name'];
        $originalName = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Tipos validos
        $validExts = ['mp4', 'mov', 'avi', 'mkv', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $validExts)) {
            json_resposta(['ok' => false, 'erro' => 'Formato não suportado. Somente vídeos e imagens.']);
        }

        $uploadDir = __DIR__ . '/../../public/uploads/temp_media';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $inFilename = uniqid('in_') . '.' . $ext;
        $inPath = $uploadDir . '/' . $inFilename;

        $outFilename = uniqid('out_') . '.' . $ext;
        $outPath = $uploadDir . '/' . $outFilename;

        move_uploaded_file($tmpPath, $inPath);

        $cmd = "ffmpeg -i \"$inPath\" -y ";
        $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'mkv']);

        if ($mode === 'metadados') {
            if ($exif == '1')
                $cmd .= "-map_metadata -1 ";

            if ($gps == '1') {
                $randomTimestamp = time() - rand(1000, 86400000);
                $randDate = date('Y-m-d\TH:i:s\Z', $randomTimestamp);
                $randLat = mt_rand(-900000, 900000) / 10000;
                $randLon = mt_rand(-1800000, 1800000) / 10000;
                $loc = sprintf('%+08.4f%+09.4f/', $randLat, $randLon);

                $cmd .= "-metadata creation_time=\"$randDate\" ";
                $cmd .= "-metadata location=\"$loc\" ";
                $cmd .= "-metadata make=\"Apple\" -metadata model=\"iPhone 15 Pro\" -metadata software=\"iOS 17.1\" ";
            }

            if ($isVideo)
                $cmd .= "-c:v copy -c:a copy ";

        } elseif ($mode === 'hash') {
            // 🚀 ESTRATÉGIA ANTI-BOT (SEC CLEAN):
            // Combinamos ruído visual imperceptível + Alteração de MD5 Binário
            if ($isVideo) {
                // Adiciona ruído estático leve nos frames para quebrar assinaturas de pixel
                $cmd .= "-vf \"noise=alls=1:allf=t\" -c:v libx64 -preset ultrafast -crf 28 -c:a copy ";
            } else {
                // Para fotos, apenas processa com filtro de ruído
                $cmd .= "-vf \"noise=alls=1:allf=t\" ";
            }
        }

        $cmd .= "\"$outPath\" 2>&1";
        $output = shell_exec($cmd);

        // 👻 TÉCNICA BINARY GHOSTING: Se o modo for Hash, injetamos bytes aleatórios no final do arquivo
        // Isso garante que mesmo sem re-encode o MD5 seja 100% diferente do original.
        if ($mode === 'hash' && file_exists($outPath)) {
            $f = fopen($outPath, 'a');
            fwrite($f, "\0" . bin2hex(random_bytes(8)));
            fclose($f);
        }

        if (!file_exists($outPath) || filesize($outPath) == 0) {
            @unlink($inPath);
            json_resposta(['ok' => false, 'erro' => 'O motor de segurança (FFmpeg) falhou ou não está instalado no servidor.', 'debug' => $output]);
        }

        @unlink($inPath);
        $downloadUrl = '/wazio/public/uploads/temp_media/' . $outFilename;

        json_resposta(['ok' => true, 'download_url' => $downloadUrl, 'filename' => 'Spoofed_' . $originalName]);
        break;


    case 'list_flows':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM crm_funnels ORDER BY created_at DESC");
                $stmt->execute();
                $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                json_resposta(['ok' => true, 'data' => $flows, 'source' => 'db']);
            } catch (Exception $e) { /* fallback */
            }
        }

        // Fallback JSON LIST
        $flows = [];
        $flowDir = __DIR__ . '/../../core/database/flows';
        if (is_dir($flowDir)) {
            $files = glob($flowDir . '/*.json');
            foreach ($files as $f) {
                $content = json_decode(file_get_contents($f), true);
                if ($content) {
                    $content['filename'] = basename($f);
                    $flows[] = $content;
                }
            }
        }
        json_resposta(['ok' => true, 'data' => $flows, 'source' => 'json']);
        break;

    case 'remarketing_disparar':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);

        $instances = $body['instances'] ?? []; // Array de nomes
        $tag = $body['tag'] ?? '';
        $message = $body['message'] ?? '';

        if (empty($instances) || empty($message)) {
            json_resposta(['ok' => false, 'erro' => 'Selecione instâncias e defina a mensagem']);
        }

        $pdo = get_db_connection();
        // Filtra contatos com a tag em qualquer uma das instâncias selecionadas
        $placeholders = implode(',', array_fill(0, count($instances), '?'));
        $sql = "SELECT * FROM crm_contacts WHERE instance_name IN ($placeholders)";
        if (!empty($tag) && $tag !== 'all') {
            $sql .= " AND tags @> ?";
            $instances[] = json_encode([$tag]);
        }

        $stmtC = $pdo->prepare($sql);
        $stmtC->execute($instances);
        $contacts = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        if (count($contacts) === 0) {
            json_resposta(['ok' => false, 'erro' => 'Nenhum contato encontrado com este filtro']);
        }

        // Simula disparo (em produção usaria um job queue, aqui faz um loop controlado para teste)
        $sucesso = 0;
        foreach ($contacts as $c) {
            $res = uazapi_api("/message/sendText/{$c['instance_name']}", [
                'number' => $c['phone'],
                'text' => $message
            ], 'POST', '');
            if (isset($res['key']))
                $sucesso++;
        }

        log_acao($user['username'], 'REMARKETING_TRIGGER', "Disparo iniciado para " . count($contacts) . " leads. Sucesso: $sucesso");
        json_resposta(['ok' => true, 'sucesso' => $sucesso, 'total' => count($contacts)]);
        break;

    // ── SYNC FORÇADO ─────────────────────────────────────────────────────────
    case 'sync_instancias':
        if (!$user || $user['role'] !== 'admin')
            json_resposta(['ok' => false, 'erro' => 'Acesso negado'], 403);
        $result = sync_all_uazapi_instances(true);
        json_resposta(['ok' => $result['ok'], 'total' => count($result['data'] ?? [])]);
        break;

    // ── STATUS RÁPIDO DE INSTÂNCIA ───────────────────────────────────────────
    case 'instance_status':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $instName = $_GET['name'] ?? $body['name'] ?? '';
        if (empty($instName))
            json_resposta(['ok' => false, 'erro' => 'Nome obrigatório']);
        check_instance_permission($instName, $user, true);
        $res = uazapi_api("/instance/status?instanceName={$instName}", [], 'GET');
        $st = strtolower($res['instance']['state'] ?? $res['state'] ?? $res['status'] ?? 'disconnected');
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("UPDATE crm_instances SET status = ?, connected = ?, last_checked = CURRENT_TIMESTAMP WHERE instance_name = ?");
            $stmt->execute([$st, (int) ($st === 'open' || $st === 'connected'), $instName]);
        }
        json_resposta(['ok' => true, 'status' => $st, 'raw' => $res]);
        break;

    // ── OCULTAR / DESOCULTAR INSTÂNCIA ──────────────────────────────────────
    case 'set_hidden':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $instName = $body['name'] ?? '';
        $hidden = (bool) ($body['hidden'] ?? false);
        if (empty($instName))
            json_resposta(['ok' => false, 'erro' => 'Nome da instância obrigatório']);
        check_instance_permission($instName, $user, true);
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("UPDATE crm_instances SET instance_hidden = ?, updated_at = CURRENT_TIMESTAMP WHERE instance_name = ?");
            $stmt->execute([(int) $hidden, $instName]);
            json_resposta(['ok' => true]);
        }
        json_resposta(['ok' => false, 'erro' => 'DB offline']);
        break;



    // ── GET LEADS (Banco de Leads / Lista) ────────────────────────────────────
    case 'get_leads':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $pdo = get_db_connection();
        if (!$pdo)
            json_resposta(['ok' => false, 'erro' => 'DB offline']);

        $uid = $user['user_id'] ?? 0;
        $role = $user['role'] ?? 'user';
        $tag = trim($_GET['tag'] ?? '');
        $inst = trim($_GET['instance'] ?? '');
        $q = trim($_GET['q'] ?? '');
        $mode = trim($_GET['mode'] ?? 'atendimento');
        $limit = max(1, min(200, (int) ($_GET['limit'] ?? 100)));
        $off = max(0, (int) ($_GET['offset'] ?? 0));

        $where = ($role === 'admin') ? '1=1' : 'user_id = ' . (int) $uid;
        $params = [];

        if ($inst !== '') {
            $where .= ' AND instance_name = ?';
            $params[] = $inst;
        }

        // Listas de tags por modo para não cruzar contatos de campanha com atendimento
        $tagsAtendimento = ['boas_vindas', 'quebrar-objeção', 'boleto pendente', 'boleto pago', 'adicionado no grupo'];
        $tagsCampanha = ['novo cliente', 'acompanhar'];

        // Se uma etiqueta específica FOI selecionada
        if ($tag === '__sem_etiqueta__') {
            $where .= " AND (tags IS NULL OR tags = '[]'::jsonb OR jsonb_array_length(tags) = 0)";
        } elseif ($tag !== '') {
            $where .= ' AND tags @> ?::jsonb';
            $params[] = json_encode([$tag]);
        }
        // Se NENHUMA etiqueta foi selecionada (Todas as Etiquetas), filtra pelo modo
        else {
            if ($mode === 'campanha') {
                $tagClauses = array_map(fn($t) => 'tags @> ?::jsonb', $tagsCampanha);
                $where .= ' AND (' . implode(' OR ', $tagClauses) . ')';
                foreach ($tagsCampanha as $t)
                    $params[] = json_encode([$t]);
            } else {
                // Modo Atendimento (padrão): tags de atendimento OU sem tag
                $tagClauses = array_map(fn($t) => 'tags @> ?::jsonb', $tagsAtendimento);
                $where .= " AND (tags IS NULL OR tags = '[]'::jsonb OR jsonb_array_length(tags) = 0 OR (" . implode(' OR ', $tagClauses) . "))";
                foreach ($tagsAtendimento as $t)
                    $params[] = json_encode([$t]);
            }
        }
        if ($q !== '') {
            $where .= ' AND (name ILIKE ? OR phone ILIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        $stmt = $pdo->prepare("SELECT id, name, phone, instance_name, tags, created_at, updated_at
                                FROM crm_contacts WHERE $where
                                ORDER BY updated_at DESC NULLS LAST
                                LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $off;
        $stmt->execute($params);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode tags
        foreach ($leads as &$l) {
            $l['tags'] = json_decode($l['tags'] ?? '[]', true) ?: [];
        }
        unset($l);

        // Count total
        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE $where");
        $paramsC = array_slice($params, 0, count($params) - 2);
        $stmtC->execute($paramsC);
        $total = (int) $stmtC->fetchColumn();

        json_resposta(['ok' => true, 'data' => $leads, 'total' => $total]);
        break;

    // ── GET KANBAN LEADS ──────────────────────────────────────────────────────
    case 'get_kanban_leads':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $pdo = get_db_connection();
        if (!$pdo)
            json_resposta(['ok' => false, 'erro' => 'DB offline']);

        $uid = $user['user_id'] ?? 0;
        $role = $user['role'] ?? 'user';
        $mode = $_GET['mode'] ?? 'atendimento'; // atendimento | campanha
        $inst = trim($_GET['instance'] ?? '');

        $baseWhere = ($role === 'admin') ? '1=1' : 'user_id = ' . (int) $uid;
        $baseParams = [];

        if ($inst !== '') {
            $baseWhere .= ' AND instance_name = ?';
            $baseParams[] = $inst;
        }

        // Definir colunas por modo
        $columns = ($mode === 'campanha') ? [
            ['id' => 'novo_cliente', 'label' => '<i data-lucide="target" width="14" height="14"></i> Novo Cliente', 'color' => '#3b82f6', 'tags' => ['novo cliente']],
            ['id' => 'acompanhar', 'label' => '<i data-lucide="eye" width="14" height="14"></i> Acompanhar', 'color' => '#eab308', 'tags' => ['acompanhar']],
        ] : [
            ['id' => 'novos_leads', 'label' => '<i data-lucide="circle-dashed" width="14" height="14"></i> Novos Leads', 'color' => '#3b82f6', 'tags' => []],  // sem etiqueta
            ['id' => 'obj', 'label' => '<i data-lucide="shield-alert" width="14" height="14"></i> Quebrar Objeção', 'color' => '#a855f7', 'tags' => ['quebrar-objeção']],
            ['id' => 'pendente', 'label' => '<i data-lucide="hourglass" width="14" height="14"></i> Boleto Pendente', 'color' => '#ef4444', 'tags' => ['boleto pendente']],
            ['id' => 'vendido', 'label' => '<i data-lucide="check-circle-2" width="14" height="14"></i> Vendido / Pago', 'color' => '#22c55e', 'tags' => ['boleto pago', 'adicionado no grupo']],
        ];

        $result = [];
        foreach ($columns as $col) {
            $w = $baseWhere;
            $p = $baseParams;

            if (empty($col['tags'])) {
                // Sem etiqueta
                $w .= " AND (tags IS NULL OR tags = '[]'::jsonb OR jsonb_array_length(tags) = 0)";
            } else {
                // Uma ou mais tags (OR entre elas)
                $tagClauses = array_map(fn($t) => 'tags @> ?::jsonb', $col['tags']);
                $w .= ' AND (' . implode(' OR ', $tagClauses) . ')';
                foreach ($col['tags'] as $t) {
                    $p[] = json_encode([$t]);
                }
            }

            $stmt = $pdo->prepare("SELECT id, name, phone, instance_name, tags, updated_at
                                    FROM crm_contacts WHERE $w
                                    ORDER BY updated_at DESC NULLS LAST LIMIT 150");
            $stmt->execute($p);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cards as &$c) {
                $c['tags'] = json_decode($c['tags'] ?? '[]', true) ?: [];
            }
            unset($c);

            $result[] = [
                'id' => $col['id'],
                'label' => $col['label'],
                'color' => $col['color'],
                'cards' => $cards,
            ];
        }

        // Instâncias disponíveis para filtro
        $qInst = ($role === 'admin')
            ? $pdo->query("SELECT DISTINCT instance_name FROM crm_instances WHERE instance_hidden IS NOT TRUE ORDER BY instance_name")
            : $pdo->prepare("SELECT DISTINCT instance_name FROM crm_instances WHERE user_id = ? AND instance_hidden IS NOT TRUE ORDER BY instance_name");
        ($role !== 'admin') ? $qInst->execute([$uid]) : null;
        $instancias = $qInst->fetchAll(PDO::FETCH_COLUMN);

        json_resposta(['ok' => true, 'columns' => $result, 'instancias' => $instancias]);
        break;

    // ── UPDATE LEAD TAG ───────────────────────────────────────────────────────
    case 'update_lead_tag':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $pdo = get_db_connection();
        if (!$pdo)
            json_resposta(['ok' => false, 'erro' => 'DB offline']);

        $leadId = (int) ($body['id'] ?? 0);
        $newTag = trim($body['tag'] ?? '');

        if (!$leadId)
            json_resposta(['ok' => false, 'erro' => 'ID inválido']);

        if ($newTag === '' || $newTag === '__sem_etiqueta__') {
            $stmt = $pdo->prepare("UPDATE crm_contacts SET tags = '[]'::jsonb, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$leadId]);
        } else {
            $stmt = $pdo->prepare("UPDATE crm_contacts SET tags = ?::jsonb, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([json_encode([$newTag]), $leadId]);
        }

        json_resposta(['ok' => true]);
        break;

    // ── ANALYTICS KPI ─────────────────────────────────────────────────────────
    case 'analytics_kpi':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $pdo = get_db_connection();
        if (!$pdo)
            json_resposta(['ok' => false, 'erro' => 'DB offline']);
        $uid = $user['user_id'] ?? 0;
        $role = $user['role'] ?? 'user';
        $today = date('Y-m-d');

        // Total contatos
        $q = ($role === 'admin')
            ? $pdo->query("SELECT COUNT(*) FROM crm_contacts")
            : $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ?");
        if ($role !== 'admin')
            $q->execute([$uid]);
        $totalContatos = (int) ($q->fetchColumn() ?? 0);

        // Novos contatos hoje
        $q2 = ($role === 'admin')
            ? $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE DATE(created_at) = ?")
            : $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ? AND DATE(created_at) = ?");
        ($role === 'admin') ? $q2->execute([$today]) : $q2->execute([$uid, $today]);
        $novosHoje = (int) ($q2->fetchColumn() ?? 0);

        // Conversas hoje (contatos com mensagem hoje = contatos únicos com interação)
        $q3 = ($role === 'admin')
            ? $pdo->prepare("SELECT COUNT(DISTINCT contact_id) FROM crm_messages WHERE DATE(created_at) = ?")
            : $pdo->prepare("SELECT COUNT(DISTINCT m.contact_id) FROM crm_messages m INNER JOIN crm_contacts c ON c.id = m.contact_id WHERE c.user_id = ? AND DATE(m.created_at) = ?");
        ($role === 'admin') ? $q3->execute([$today]) : $q3->execute([$uid, $today]);
        $conversasHoje = (int) ($q3->fetchColumn() ?? 0);

        // Mensagens hoje
        $q4 = ($role === 'admin')
            ? $pdo->prepare("SELECT COUNT(*) FROM crm_messages WHERE DATE(created_at) = ?")
            : $pdo->prepare("SELECT COUNT(*) FROM crm_messages m INNER JOIN crm_contacts c ON c.id = m.contact_id WHERE c.user_id = ? AND DATE(m.created_at) = ?");
        ($role === 'admin') ? $q4->execute([$today]) : $q4->execute([$uid, $today]);
        $msgsHoje = (int) ($q4->fetchColumn() ?? 0);

        // Fluxos ativos / total
        $q5 = ($role === 'admin')
            ? $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as ativos FROM crm_funnels")
            : $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as ativos FROM crm_funnels WHERE user_id = ?");
        ($role === 'admin') ? null : $q5->execute([$uid]);
        $fluxosRow = ($role === 'admin') ? $q5->fetch(PDO::FETCH_ASSOC) : $q5->fetch(PDO::FETCH_ASSOC);
        $fluxosTotal = (int) ($fluxosRow['total'] ?? 0);
        $fluxosAtivos = (int) ($fluxosRow['ativos'] ?? 0);

        // Leads em funil (funnel_progress ativo)
        $q6 = ($role === 'admin')
            ? $pdo->query("SELECT COUNT(*) FROM crm_funnel_progress WHERE status NOT IN ('completed','failed')")
            : $pdo->prepare("SELECT COUNT(*) FROM crm_funnel_progress WHERE user_id = ? AND status NOT IN ('completed','failed')");
        ($role === 'admin') ? null : $q6->execute([$uid]);
        $leadsFunil = (int) (($role === 'admin') ? $q6->fetchColumn() : $q6->fetchColumn());

        // Vendas hoje (checkouts confirmados)
        $q7 = ($role === 'admin')
            ? $pdo->prepare("SELECT COUNT(*) FROM crm_checkout WHERE status IN ('paid','confirmed','APPROVED') AND DATE(created_at) = ?")
            : $pdo->prepare("SELECT COUNT(*) FROM crm_checkout WHERE user_id = ? AND status IN ('paid','confirmed','APPROVED') AND DATE(created_at) = ?");
        ($role === 'admin') ? $q7->execute([$today]) : $q7->execute([$uid, $today]);
        $vendasHoje = (int) ($q7->fetchColumn() ?? 0);

        // Instâncias conectadas / total
        $q8 = ($role === 'admin')
            ? $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN connected=true THEN 1 ELSE 0 END) as conn FROM crm_instances WHERE instance_hidden IS NOT TRUE")
            : $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN connected=true THEN 1 ELSE 0 END) as conn FROM crm_instances WHERE user_id = ? AND instance_hidden IS NOT TRUE");
        ($role !== 'admin') ? $q8->execute([$uid]) : null;
        $instRow = $q8->fetch(PDO::FETCH_ASSOC);
        $numTotal = (int) ($instRow['total'] ?? 0);
        $numConn = (int) ($instRow['conn'] ?? 0);

        // Lista de instâncias para filtro de gráfico
        $q9 = ($role === 'admin')
            ? $pdo->query("SELECT DISTINCT instance_name FROM crm_instances WHERE instance_hidden IS NOT TRUE ORDER BY instance_name")
            : $pdo->prepare("SELECT DISTINCT instance_name FROM crm_instances WHERE user_id = ? AND instance_hidden IS NOT TRUE ORDER BY instance_name");
        ($role !== 'admin') ? $q9->execute([$uid]) : null;
        $instancias = $q9->fetchAll(PDO::FETCH_COLUMN);

        // Total contatos back (base completa no banco, sem filtro de user - representa o backend total)
        $qBack = $pdo->query("SELECT COUNT(*) FROM crm_contacts");
        $totalContatosBack = (int) ($qBack->fetchColumn() ?? 0);

        // Pendentes hoje (contatos que entraram no banco hoje = novas entradas do dia)
        $qPend = ($role === 'admin')
            ? $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE DATE(created_at) = ?")
            : $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ? AND DATE(created_at) = ?");
        ($role === 'admin') ? $qPend->execute([$today]) : $qPend->execute([$uid, $today]);
        $pendentesHoje = (int) ($qPend->fetchColumn() ?? 0);

        // ── PENDENTES por etiqueta (tag 'pendente' ou 'pendentes') ──────────────────
        $qPendTag = ($role === 'admin')
            ? $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE (tags @> '[\"pendente\"]'::jsonb OR tags @> '[\"pendentes\"]'::jsonb)")
            : $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ? AND (tags @> '[\"pendente\"]'::jsonb OR tags @> '[\"pendentes\"]'::jsonb)");
        ($role === 'admin') ? $qPendTag->execute([]) : $qPendTag->execute([$uid]);
        $pendentesTag = (int) ($qPendTag->fetchColumn() ?? 0);

        // Pendentes final = maior entre entradas hoje e etiquetados
        $pendentesHojeFinal = max($pendentesHoje, $pendentesTag);

        // ── VENDAS por etiqueta (tag 'pago' ou 'paga') hoje ─────────────────────────
        $qVendasTag = ($role === 'admin')
            ? $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE DATE(updated_at) = ? AND (tags @> '[\"pago\"]'::jsonb OR tags @> '[\"paga\"]'::jsonb)")
            : $pdo->prepare("SELECT COUNT(*) FROM crm_contacts WHERE user_id = ? AND DATE(updated_at) = ? AND (tags @> '[\"pago\"]'::jsonb OR tags @> '[\"paga\"]'::jsonb)");
        ($role === 'admin') ? $qVendasTag->execute([$today]) : $qVendasTag->execute([$uid, $today]);
        $vendasTag = (int) ($qVendasTag->fetchColumn() ?? 0);

        // Vendas final = maior entre checkout confirmado e etiquetados como pago hoje
        $vendasHojeFinal = max($vendasHoje, $vendasTag);

        json_resposta([
            'ok' => true,
            'data' => [
                'total_contatos' => $totalContatos,
                'total_contatos_back' => $totalContatosBack,
                'novos_contatos_hoje' => $novosHoje,
                'pendentes_hoje' => $pendentesHojeFinal,   // max(entradas hoje, tag pendente)
                'pendentes_tag' => $pendentesTag,          // só por etiqueta
                'conversas_hoje' => $conversasHoje,
                'mensagens_hoje' => $msgsHoje,
                'fluxos_total' => $fluxosTotal,
                'fluxos_ativos' => $fluxosAtivos,
                'leads_em_funil' => $leadsFunil,
                'vendas_hoje' => $vendasHojeFinal,       // max(checkout, tag pago)
                'vendas_tag' => $vendasTag,             // só por etiqueta
                'numeros_conectados' => $numConn,
                'numeros_total' => $numTotal,
                'instancias' => $instancias,
            ]
        ]);
        break;

    // ── CHART 7 DIAS ─────────────────────────────────────────────────────────
    case 'analytics_chart_7dias':
        if (!$user)
            json_resposta(['ok' => false, 'erro' => 'Não autenticado'], 401);
        $pdo = get_db_connection();
        if (!$pdo)
            json_resposta(['ok' => false, 'erro' => 'DB offline']);
        $uid = $user['user_id'] ?? 0;
        $role = $user['role'] ?? 'user';

        // Last 7 days
        $dates = [];
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-$i days"));
        }

        // Get conversation counts per instance per day
        $sql = ($role === 'admin')
            ? "SELECT c.instance_name, DATE(m.created_at) as dia, COUNT(DISTINCT m.contact_id) as cnt
               FROM crm_messages m
               INNER JOIN crm_contacts c ON c.id = m.contact_id
               WHERE m.created_at >= NOW() - INTERVAL '7 days'
               GROUP BY c.instance_name, dia ORDER BY dia"
            : "SELECT c.instance_name, DATE(m.created_at) as dia, COUNT(DISTINCT m.contact_id) as cnt
               FROM crm_messages m
               INNER JOIN crm_contacts c ON c.id = m.contact_id
               WHERE c.user_id = ? AND m.created_at >= NOW() - INTERVAL '7 days'
               GROUP BY c.instance_name, dia ORDER BY dia";

        $q = $pdo->prepare($sql);
        ($role !== 'admin') ? $q->execute([$uid]) : $q->execute([]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);

        // Transform to series
        $byInst = [];
        foreach ($rows as $r) {
            $byInst[$r['instance_name']][$r['dia']] = (int) $r['cnt'];
        }

        $series = [];
        foreach ($byInst as $name => $dayMap) {
            $data = [];
            foreach ($dates as $d) {
                $data[] = $dayMap[$d] ?? 0;
            }
            $series[] = ['name' => $name, 'data' => $data];
        }

        json_resposta(['ok' => true, 'data' => ['dates' => $dates, 'series' => $series]]);
        break;

    case 'proxy_image':
        $imageUrl = $_GET['url'] ?? '';
        if (empty($imageUrl)) {
            header("HTTP/1.1 400 Bad Request");
            exit("URL ausente.");
        }

        // Permite URLs válidas para contornar problemas de CORS na exibição de avatares
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            header("HTTP/1.1 403 Forbidden");
            exit("URL inválida.");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $imageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        $data = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $data) {
            header("Content-Type: $contentType");
            header("Cache-Control: public, max-age=86400"); // Cache por 24h
            echo $data;
        } else {
            // Fallback para ícone padrão se falhar - Retorna HTTP 200 para a UI exibir normalmente
            $defaultIcon = __DIR__ . '/../../public/images/waz-icon-hd.png';
            if (file_exists($defaultIcon)) {
                header("HTTP/1.1 200 OK");
                header("Content-Type: image/png");
                header("Cache-Control: public, max-age=86400"); // Cache por 24h
                readfile($defaultIcon);
            } else {
                header("HTTP/1.1 404 Not Found");
            }
        }
        exit;

    default:
        json_resposta(['ok' => false, 'erro' => "Ação '$action' desconhecida."], 200);
        break;
}