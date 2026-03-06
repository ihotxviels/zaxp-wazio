require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/WhatsAppService.php';

class FlowInterpreter
{
private $pdo;
private $waService;

public function __construct()
{
$this->pdo = get_db_connection();
$this->waService = new WhatsAppService();
$this->logActivity("Motor PHP-First Inicializado.");
}

public function processIncomingMessage($packet)
{
$instanceName = $packet['instance'];
$leadPhone = $packet['remoteJid'];
$text = strtolower(trim($packet['text']));

$this->logActivity("Mensagem Recebida de $leadPhone na instância $instanceName: $text");

// 0. Resolver o UserID da Instância
$instanceData = $this->getInstanceData($instanceName);
if (!$instanceData) {
$this->logActivity("Erro: Instância $instanceName não cadastrada no DB.");
return;
}
$userId = $instanceData['user_id'];

// 1. Verificar se o Lead já está no meio de um fluxo aguardando
$activeFlow = $this->getActiveFlowStatus($instanceName, $leadPhone, $userId);

if ($activeFlow) {
$this->logActivity("[State Machine] Lead encontrado no fluxo: " . $activeFlow['funnel_id']);
// O Lead estava em um Wait ou parou num Node específico.
$this->resumeFlowExecution($activeFlow, $packet, $userId);
return;
}

// 2. Se não está em nenhum fluxo, verificar Gatilhos (Triggers)
$this->logActivity("Checando Gatilhos disponíveis para a Instância: $instanceName");
$triggeredFlow = $this->checkTriggers($instanceName, $text, $userId);

if ($triggeredFlow) {
$this->startNewFlow($triggeredFlow, $packet, $userId);
} else {
$this->logActivity("Nenhum Gatilho ativado para a mensagem: '$text'. Ignorando.");
}
}

private function getActiveFlowStatus($instanceName, $leadPhone, $userId)
{
if (!$this->pdo)
return null; // Sem DB, sem estado de longa duração. Fallbacks não aguentam concorrência complexa de pausas longas.

try {
$stmt = $this->pdo->prepare("SELECT * FROM crm_funnel_progress WHERE user_id = ? AND instance_name = ? AND lead_phone =
? AND status = 'running' LIMIT 1");
$stmt->execute([$userId, $instanceName, $leadPhone]);
$status = $stmt->fetch(PDO::FETCH_ASSOC);

if ($status && $status['next_step_at']) {
if (strtotime($status['next_step_at']) > time()) {
return null; // Ainda em delay
}
}
return $status;
} catch (Exception $e) {
$this->logActivity("Erro DB getActiveFlowStatus: " . $e->getMessage());
return null;
}
}

private function checkTriggers($instanceName, $text, $userId)
{
if (!$this->pdo) return null;

try {
// Agora buscamos na tabela crm_funnels filtrando por user_id
$stmt = $this->pdo->prepare("SELECT * FROM crm_funnels WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$funnels = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($funnels as $f) {
$nodes = json_decode($f['nodes'], true) ?? [];
foreach ($nodes as $node) {
if ($node['type'] === 'trigger_message') {
$keyword = strtolower(trim($node['data']['conditionValue'] ?? ''));
if ($keyword === $text) {
return [
'flow_json' => $f,
'start_node_id' => $node['id']
];
}
}
}
}
} catch (Exception $e) {
$this->logActivity("Erro em checkTriggers DB: " . $e->getMessage());
}
return null;
}

private function startNewFlow($triggeredFlow, $packet, $userId)
{
$flowName = $triggeredFlow['flow_json']['name'];
$funnelId = $triggeredFlow['flow_json']['id'];
$startNodeId = $triggeredFlow['start_node_id'];

$this->logActivity("====== INICIANDO NOVO FLUXO: $flowName (User: $userId) =====");
$this->saveLeadStatus($packet['instance'], $packet['remoteJid'], $funnelId, $startNodeId, null, $userId);

// Inicia a Traversia a partir do bloco LOGO APÓS o gatilho
$nextNodeId = $this->getNextNodeId($triggeredFlow['flow_json']['edges'], $startNodeId);
if ($nextNodeId) {
$this->executeNodeLoop($triggeredFlow['flow_json'], $nextNodeId, $packet, $userId);
} else {
$this->logActivity("Gatilho sem conexão aparente. Encerrando.");
$this->clearLeadStatus($packet['instance'], $packet['remoteJid'], $userId);
}
}

private function resumeFlowExecution($activeFlow, $packet, $userId)
{
$instanceName = $activeFlow['instance_name'];
$funnelId = $activeFlow['funnel_id'];
$currentNodeId = $activeFlow['current_node_id'];

// Buscar o Funil no DB
$stmt = $this->pdo->prepare("SELECT * FROM crm_funnels WHERE user_id = ? AND id = ? LIMIT 1");
$stmt->execute([$userId, $funnelId]);
$funnel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$funnel) {
$this->logActivity("Funil $funnelId não encontrado no DB para resume.");
$this->clearLeadStatus($instanceName, $packet['remoteJid'], $userId);
return;
}

$flowJson = [
'nodes' => json_decode($funnel['nodes'], true),
'edges' => json_decode($funnel['edges'] ?? '[]', true),
'name' => $funnel['name']
];

// Se estava num Wait, avançar para o próximo ligado a ele
$nextNodeId = $this->getNextNodeId($flowJson['edges'], $currentNodeId);

$this->logActivity("====== RETOMANDO FLUXO: {$funnel['name']} =====");
if ($nextNodeId) {
$this->executeNodeLoop($flowJson, $nextNodeId, $packet, $userId);
} else {
$this->logActivity("Fim da Linha no resume do fluxo.");
$this->clearLeadStatus($instanceName, $packet['remoteJid'], $userId);
}
}

private function executeNodeLoop($flowJson, $startNodeId, $packet, $userId)
{
$currentNodeId = $startNodeId;
$maxSteps = 50;
$step = 0;

while ($currentNodeId && $step < $maxSteps) { $node=$this->getNodeById($flowJson['nodes'], $currentNodeId);
    if (!$node) break;

    $this->logActivity(">> Executando Node: [{$node['type']}] ({$node['id']})");
    $goNext = $this->processAction($node, $packet);

    $this->saveLeadStatus($packet['instance'], $packet['remoteJid'], $flowJson['name'], $currentNodeId, null, $userId);

    if ($goNext === 'BREAK_WAIT' || $goNext === 'BREAK_DELAY') break;

    $currentNodeId = $this->getNextNodeId($flowJson['edges'], $currentNodeId);
    $step++;
    usleep(800000);
    }

    if (!$currentNodeId || $step >= $maxSteps) {
    $this->clearLeadStatus($packet['instance'], $packet['remoteJid'], $userId);
    }
    }

    private function getNodeById($nodes, $id)
    {
    foreach ($nodes as $n) {
    if ($n['id'] === $id) return $n;
    }
    return null;
    }

    private function processAction($node, $packet)
    {
    $type = $node['type'];
    $data = $node['data'];
    $remoteJid = $packet['remoteJid'];
    $instance = $packet['instance'];

    $token = $this->getInstanceToken($instance);
    if (!$token) return false;

    switch ($type) {
    case 'action_text':
    $textRaw = $data['content'] ?? '';
    $textParsed = str_replace(['{{nome}}', '{{lead}}'], [$packet['pushName'], $packet['remoteJid']], $textRaw);
    $this->waService->sendText($instance, $token, $remoteJid, $textParsed);
    return true;

    case 'action_media':
    $url = $data['url'] ?? '';
    $mType = $data['mediaType'] ?? 'image';
    $caption = $data['caption'] ?? '';
    $this->waService->sendMedia($instance, $token, $remoteJid, $url, $mType, $caption);
    return true;

    case 'logic_wait':
    return 'BREAK_WAIT';

    case 'logic_delay':
    $sec = intval($data['conditionValue'] ?? 5);
    if ($sec > 30) return 'BREAK_DELAY';
    sleep($sec);
    return true;

    case 'n8n_worker':
    // Worker para processamento pesado
    $this->triggerN8NWorker($data['webhookUrl'], $packet);
    return 'BREAK_WAIT';

    default:
    $this->logActivity("Ação '$type' processada.");
    return true;
    }
    }

    private function cleanJid($jid)
    {
    return explode('@', $jid)[0];
    }

    private function saveLeadStatus($instanceName, $leadPhone, $funnelId, $nodeId, $nextStepAt = null, $userId = 1)
    {
    if (!$this->pdo) return;
    try {
    $stmt = $this->pdo->prepare("
    INSERT INTO crm_funnel_progress (user_id, instance_name, lead_phone, funnel_id, current_node_id, next_step_at,
    status, updated_at)
    VALUES (?, ?, ?, (SELECT id FROM crm_funnels WHERE (name = ? OR id::text = ?) AND user_id = ? LIMIT 1), ?, ?,
    'running', CURRENT_TIMESTAMP)
    ON CONFLICT (instance_name, lead_phone)
    DO UPDATE SET
    current_node_id = EXCLUDED.current_node_id,
    next_step_at = EXCLUDED.next_step_at,
    updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$userId, $instanceName, $this->cleanJid($leadPhone), $funnelId, (string)$funnelId, $userId, $nodeId,
    $nextStepAt]);
    } catch (Exception $e) { }
    }

    private function clearLeadStatus($instanceName, $leadPhone, $userId = 1)
    {
    if (!$this->pdo) return;
    try {
    $stmt = $this->pdo->prepare("DELETE FROM crm_funnel_progress WHERE user_id = ? AND instance_name = ? AND lead_phone
    = ?");
    $stmt->execute([$userId, $instanceName, $this->cleanJid($leadPhone)]);
    } catch (Exception $e) { }
    }

    private function getInstanceData($name) {
    $stmt = $this->pdo->prepare("SELECT * FROM crm_instances WHERE instance_name = ? LIMIT 1");
    $stmt->execute([$name]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getInstanceToken($name) {
    $data = $this->getInstanceData($name);
    return $data['instance_token'] ?? null;
    }

    private function triggerN8NWorker($url, $packet) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($packet));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
    }

    private function logActivity($msg)
    {
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $date = date('Y-m-d H:i:s');
    file_put_contents($logDir . '/flow_engine.log', "[$date] $msg" . PHP_EOL, FILE_APPEND);
    }
    }