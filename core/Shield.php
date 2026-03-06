<?php
/**
 * UAZAPI WAF - Escudo Anti-Intrusão Server-Side
 * Bloqueia bots, mitigação DDoS básica (Rate Limiting) e injeções comuns.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function waff_trigger_block($reason = "Acesso Negado")
{
    // Registra tentativa no log seguro se existir banco
    if (function_exists('get_db_connection') && function_exists('log_acao')) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
        log_acao('WAF_SYSTEM', 'INTRUSION_BLOCKED', "IP: $ip. Motivo: $reason");
    }

    http_response_code(403);
    header("Location: /wazio/blind.php");
    exit;
}

// 1. Rate Limiting Simplificado (Anti-DDoS em Camada de Aplicação)
$max_requests_per_second = 100; // Aumentado para 100 para evitar bloqueios em massa de proxy check
$current_time = time();

if (!isset($_SESSION['waf_rate_limit'])) {
    $_SESSION['waf_rate_limit'] = ['hits' => 1, 'time' => $current_time];
} else {
    if ($_SESSION['waf_rate_limit']['time'] == $current_time) {
        $_SESSION['waf_rate_limit']['hits']++;
        if ($_SESSION['waf_rate_limit']['hits'] > $max_requests_per_second) {
            // Estourou o limite de requisições!
            waff_trigger_block("Rate limit exceeeded (DDoS Attempt)");
        }
    } else {
        $_SESSION['waf_rate_limit']['hits'] = 1;
        $_SESSION['waf_rate_limit']['time'] = $current_time;
    }
}

// 2. Assinaturas Comuns de Injeção Escandalosa (SQLi / XSS)
$bad_patterns = [
    '/(?:union\s+select)/i',
    '/(?:information_schema)/i',
    '/(?:<script>)/i',
    '/(?:\.\.\/\.\.\/)/i', // Path traversal (LFI/RFI)
    '/(?:eval\()/i',
    '/(?:drop\s+table)/i',
    '/(?:truncate\s+table)/i'
];

function waff_check_array($data, $patterns)
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            waff_check_array($value, $patterns);
        }
    } else {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $data)) {
                waff_trigger_block("Malicious payload detected: Input matches restricted pattern.");
            }
        }
    }
}

// Verifica payloads de Ingestão de Dados
waff_check_array($_GET, $bad_patterns);
waff_check_array($_POST, $bad_patterns);
waff_check_array($_COOKIE, $bad_patterns);

// 3. User-Agent Anômalo (Bloqueador de exploits automatizados velhos)
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$bot_agents = ['nikto', 'sqlmap', 'nmap', 'zmeu', 'dirbuster', 'havij'];

foreach ($bot_agents as $bot) {
    if (strpos($ua, $bot) !== false) {
        waff_trigger_block("Known malicious User-Agent bot block.");
    }
}
?>