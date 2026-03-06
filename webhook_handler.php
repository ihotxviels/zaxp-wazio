<?php
/**
 * 🛠️ WAZIO WEBHOOK HANDLER
 * Recebe eventos da UAZAPI Cloud e sincroniza com o banco de dados PostgreSQL.
 */

require_once __DIR__ . '/config.php';

// 1. Log da requisição bruta (debug)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    exit('Payload inválido');
}

// Log no arquivo local
log_acao('WEBHOOK', 'RECEIVE', substr($rawInput, 0, 500));

// 2. Extração de informações básicas do evento
$event = $data['event'] ?? $data['type'] ?? '';
$instance = $data['instance'] ?? $data['instanceName'] ?? '';

// 3. Lógica de Sincronização
switch ($event) {
    case 'messages.upsert':
    case 'messages':
        // No Uazapi Cloud, o evento costuma trazer detalhes do contato
        if (isset($data['data']['key']['remoteJid'])) {
            $phone = explode('@', $data['data']['key']['remoteJid'])[0];
            $name = $data['data']['pushName'] ?? 'Cliente';

            upsert_contato([
                'instance' => $instance,
                'phone' => $phone,
                'name' => $name,
                'status' => 'lead'
            ]);
        }
        break;

    case 'contacts.upsert':
    case 'contacts':
        $contacts = $data['data'] ?? [];
        if (isset($contacts['id']))
            $contacts = [$contacts]; // Normaliza se for único

        foreach ($contacts as $c) {
            $phone = explode('@', $c['id'] ?? $c['number'] ?? '')[0];
            if (empty($phone))
                continue;

            upsert_contato([
                'instance' => $instance,
                'phone' => $phone,
                'name' => $c['name'] ?? $c['verifiedName'] ?? $c['pushName'] ?? 'Cliente',
                'chatwclid' => $c['chatwclid'] ?? null
            ]);

            // [NOVO] Sincroniza Tags com a Categoria do Chip na Contingência
            if (!empty($c['labels'])) {
                sync_tags_to_chip($phone, $c['labels']);
            }
        }
        break;

    case 'connection.update':
    case 'status':
        // Sincroniza status da instância no DB local e na Contingência
        $status = $data['data']['status'] ?? $data['state'] ?? 'unknown';
        sync_instance_to_chip($instance, $status);
        break;

    case 'chip.update': // Evento customizado se houver
        if (isset($data['data']['number'])) {
            upsert_chip([
                'nome' => $data['data']['name'] ?? '',
                'numero' => $data['data']['number'],
                'status' => $data['data']['status'] ?? 'DISPONÍVEL',
                'conexao' => 'ONLINE'
            ]);
        }
        break;
}

// 4. Retorno de sucesso para a API
http_response_code(200);
echo json_encode(['ok' => true, 'message' => 'Evento processado']);
