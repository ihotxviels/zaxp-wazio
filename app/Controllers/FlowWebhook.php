<?php
/**
 * WAZ.IO — SMART WEBHOOK RECEIVER (uazapiGO Optimized)
 * Captura leads de forma resiliente usando múltiplos fallbacks.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/FlowInterpreter.php';

header('Content-Type: application/json; charset=utf-8');

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (!$data || empty($data['body'])) {
    echo json_encode(['status' => 'ignored', 'reason' => 'invalid_payload']);
    exit;
}

$body = $data['body'];
$eventType = $body['EventType'] ?? '';

// Só processamos mensagens (por enquanto)
if ($eventType !== 'messages') {
    echo json_encode(['status' => 'ignored', 'type' => $eventType]);
    exit;
}

$instanceName = $body['instanceName'] ?? 'unknown';

// ─── 1. SMART EXTRACTION (FALLBACKS) ───────────────────────────

// A. Telefone (Prioridade: sender_pn > chatid > phone > chat.id)
$rawPhone = $body['message']['sender_pn']
    ?? $body['message']['chatid']
    ?? $body['chat']['wa_chatid']
    ?? $body['chat']['phone']
    ?? $body['chat']['id']
    ?? '';

// Limpeza: remove @s.whatsapp.net e caracteres não numéricos
$cleanPhone = preg_replace('/[^0-9]/', '', str_replace('@s.whatsapp.net', '', $rawPhone));
if (strlen($cleanPhone) < 10) {
    echo json_encode(['status' => 'ignored', 'reason' => 'invalid_phone', 'raw' => $rawPhone]);
    exit;
}

// B. Nome (Prioridade: senderName > wa_name > name)
$cleanName = $body['message']['senderName']
    ?? $body['chat']['wa_name']
    ?? $body['chat']['name']
    ?? 'Lead WAZ.IO';

// C. LID & ID
$chatLid = $body['message']['chatlid'] ?? $body['chat']['wa_chatlid'] ?? null;
$messageId = $body['message']['messageid'] ?? $body['message']['id'] ?? null;

// D. AD TRACKING (CTWA)
$adData = [
    'ctwa_clid' => $body['message']['content']['externalAdReply']['ctwaClid'] ?? null,
    'conversion_source' => $body['message']['content']['contextInfo']['entryPointConversionSource'] ?? null,
    'conversion_app' => $body['message']['content']['contextInfo']['entryPointConversionApp'] ?? null,
    'ad_source_id' => $body['message']['content']['externalAdReply']['sourceID'] ?? null,
    'ad_source_url' => $body['message']['content']['externalAdReply']['sourceURL'] ?? null,
    'ad_title' => $body['message']['content']['externalAdReply']['title'] ?? null,
    'ad_media_url' => $body['message']['content']['externalAdReply']['mediaURL'] ?? null,
    'ad_greeting' => $body['message']['content']['externalAdReply']['greetingMessageBody'] ?? null,
    'device' => $body['message']['source'] ?? null
];

// ─── 2. PERSISTÊNCIA NO CRM (crm_contacts) ─────────────────────
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        INSERT INTO crm_contacts 
        (instance_name, phone, chat_lid, name, ctwa_clid, conversion_source, conversion_app, ad_source_id, ad_source_url, ad_title, ad_media_url, ad_greeting_message, device_source, last_interaction)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (instance_name, phone) 
        DO UPDATE SET 
            chat_lid = COALESCE(EXCLUDED.chat_lid, crm_contacts.chat_lid),
            name = CASE WHEN EXCLUDED.name != 'Lead WAZ.IO' THEN EXCLUDED.name ELSE crm_contacts.name END,
            ctwa_clid = COALESCE(EXCLUDED.ctwa_clid, crm_contacts.ctwa_clid),
            ad_title = COALESCE(EXCLUDED.ad_title, crm_contacts.ad_title),
            last_interaction = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $instanceName,
        $cleanPhone,
        $chatLid,
        $cleanName,
        $adData['ctwa_clid'],
        $adData['conversion_source'],
        $adData['conversion_app'],
        $adData['ad_source_id'],
        $adData['ad_source_url'],
        $adData['ad_title'],
        $adData['ad_media_url'],
        $adData['ad_greeting'],
        $adData['device']
    ]);
    // Pegar o ID do contato para vincular a mensagem
    $stmtId = $pdo->prepare("SELECT id, user_id FROM crm_contacts WHERE instance_name = ? AND phone = ?");
    $stmtId->execute([$instanceName, $cleanPhone]);
    $contactObj = $stmtId->fetch(PDO::FETCH_ASSOC);
    $cid = $contactObj['id'];
    $uid = $contactObj['user_id'] ?? 1;

    // ─── 2.1 PERSISTÊNCIA DA MENSAGEM (crm_messages) ─────────────
    $stmtMsg = $pdo->prepare("
        INSERT INTO crm_messages 
        (user_id, contact_id, message_id, from_me, message_type, body, created_at)
        VALUES (?, ?, ?, false, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (message_id) DO NOTHING
    ");
    $stmtMsg->execute([
        $uid,
        $cid,
        $messageId,
        'text', // Suporte inicial apenas texto, expansível para mídia
        $text
    ]);
} catch (Exception $e) {
    // Log erro mas continua para o fluxo
}

// ─── 3. DISPARAR MOTOR DE FLUXOS ───────────────────────────────
$text = $body['message']['text'] ?? $body['message']['content']['text'] ?? '';
$packet = [
    'instance' => $instanceName,
    'remoteJid' => $cleanPhone . '@s.whatsapp.net',
    'pushName' => $cleanName,
    'text' => $text,
    'timestamp' => time()
];

$engine = new FlowInterpreter();
$engine->processIncomingMessage($packet);

// ─── 4. ENCAMINHAR PARA N8N (DASHBOARD & MÉTRICAS) ──────────────
// Re-encaminha o payload original para o n8n para manter o dashboard sincronizado
n8n('receber-evento-uazapi', $data);

echo json_encode(['status' => 'success', 'lead' => $cleanPhone]);
