<?php
// =========================================================================
// 1. BLINDAGEM DE SESSÃO E SEGURANÇA (AUTOSSUFICIENTE)
// =========================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/wazio/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Location: /wazio/');
    exit;
}

$user = $_SESSION['user'];
$role = $user['role'] ?? 'user';
$username = $user['username'] ?? 'admin';
$modulos_permitidos = $user['modulos'] ?? [];

if ($role !== 'admin' && !in_array('contingencia', $modulos_permitidos)) {
    header('Location: /wazio/index.php?error=no_access');
    exit;
}

// =========================================================================
// 2. FUNÇÕES E LÓGICA DE NEGÓCIO (CHIPS)
// =========================================================================
function calcularKPIs($dados)
{
    $kpis = [
        'total_ativos' => 0,
        'total_geral' => is_array($dados) ? count($dados) : 0,
        'disparadores' => 0,
        'ads' => 0,
        'boletos' => 0,
        'aquecimento' => 0,
        'banidos' => 0,
        'analise' => 0,
        'conectados' => 0,
        'offline' => 0
    ];
    if (!is_array($dados))
        return $kpis;

    foreach ($dados as $chip) {
        $st = strtoupper(trim($chip['status'] ?? ''));
        $con = strtoupper(trim($chip['conexao'] ?? ''));
        $cat = (string) ($chip['categoria'] ?? '1');

        $isBanido = (strpos($st, 'BANIDO') !== false);
        $isAnalise = (strpos($st, 'ANÁLISE') !== false) || (strpos($st, 'ANALISE') !== false);
        $isConectado = ($con === 'ONLINE');

        if ($isBanido)
            $kpis['banidos']++;
        if ($isAnalise)
            $kpis['analise']++;
        if (strpos($st, 'AQUEC') !== false || strpos($st, 'AQ') !== false)
            $kpis['aquecimento']++;
        if ($isConectado)
            $kpis['conectados']++;
        else
            $kpis['offline']++;

        if (!$isBanido && !$isAnalise && $isConectado) {
            $kpis['total_ativos']++;
            if ($cat === '1')
                $kpis['disparadores']++;
            elseif ($cat === '2')
                $kpis['ads']++;
            elseif ($cat === '3')
                $kpis['boletos']++;
        }
    }
    return $kpis;
}

// =========================================================================
// 3. LÓGICA DA API / MULTI-TENANT (GET/POST) — chamada via fetch JS
// =========================================================================
if (isset($_GET['action']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    require_once __DIR__ . '/../../../config.php';
    $pdo = get_db_connection();

    // ==========================================
    // 1. CONFIGURAÇÕES DA CONTA (Dispositivos e Colunas - Mantidos em JSON pra layout)
    // ==========================================
    $dir = __DIR__ . '/chips_data';
    if (!is_dir($dir))
        // --- CARREGAR CHIPS DO BANCO DE DADOS (SYNC 100%) ---
        if ($action === 'check_sync') {
            try {
                $stmt = $pdo->query("SELECT * FROM crm_chips ORDER BY id ASC");
                $chips = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $kpis = calcularKPIs($chips);
                json_resposta(['ok' => true, 'chips' => $chips, 'kpis' => $kpis]);
            } catch (Exception $e) {
                json_resposta(['ok' => false, 'error' => $e->getMessage()], 500);
            }
        }
    @mkdir($dir, 0775, true);

    $config = $default_config;
    try {
        $stmtSet = $pdo->prepare("SELECT setting_value FROM crm_settings WHERE username = ? AND setting_key = 'contingencia_config'");
        $stmtSet->execute([$username]);
        $rowSet = $stmtSet->fetch(PDO::FETCH_ASSOC);
        if ($rowSet) {
            $config = json_decode($rowSet['setting_value'], true) ?: $default_config;
        } else {
            // Fallback para JSON local (legado) se não houver no banco
            $config_file = $dir . '/config_' . $username . '.json';
            if (file_exists($config_file)) {
                $config = json_decode(file_get_contents($config_file), true) ?: $default_config;
            }
        }
    } catch (Exception $e) { /* fallback already set */
    }

    // ==========================================
    // 2. FUNÇÕES DE CRUD (POSTGRESQL - crm_chips)
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        // Obter Chips do BD limitando à conta atual
        $stmt = $pdo->query("SELECT *, index_id as index FROM crm_chips ORDER BY added_date ASC");
        $chips_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obter Instâncias para o select do modal
        $stmtInst = $pdo->prepare("SELECT instance_name FROM crm_instances ORDER BY instance_name ASC");
        $stmtInst->execute();
        $instances_list = $stmtInst->fetchAll(PDO::FETCH_COLUMN);

        $dados = [];
        foreach ($chips_raw as $c) {
            $dados[] = [
                'id' => $c['id'],
                'index' => $c['index_id'] ?? '00',
                'nome' => $c['nome'] ?? '',
                'numero' => $c['numero'] ?? '',
                'instance_name' => $c['instance_name'] ?? '',
                'categoria' => (string) ($c['categoria'] ?? '1'),
                'status' => $c['status'] ?? 'DISPONÍVEL',
                'conexao' => $c['conexao'] ?? 'OFFLINE',
                'funcao' => $c['funcao'] ?? 'ADMINISTRADOR',
                'dispositivo' => $c['dispositivo'] ?? 'MOTOROLA 01'
            ];
        }
        echo json_encode(['chips' => $dados, 'kpis' => calcularKPIs($dados), 'config' => $config, 'instances' => $instances_list]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $body = $input; // Alias for clarity with the provided snippet

        if ($action === 'save') {
            $num = $input['numero'] ?? '';
            $nome = $input['nome'] ?? '';
            $categoria = $input['categoria'] ?? '1';
            $status = $input['status'] ?? 'DISPONÍVEL';
            $conexao = $input['conexao'] ?? 'OFFLINE';
            $funcao = $input['funcao'] ?? 'ADMINISTRADOR';
            $dispositivo = $input['dispositivo'] ?? 'MOTOROLA 01';
            $index = $input['index'] ?? '00';

            // Se for Update ou Novo
            if ($body['id']) {
                $stmt = $pdo->prepare("UPDATE crm_chips SET nome = ?, numero = ?, instance_name = ?, status = ?, conexao = ?, funcao = ?, categoria = ?, dispositivo = ?, index_id = ? WHERE id = ?");
                $stmt->execute([$body['nome'], $body['numero'], $body['instance_name'] ?? $body['nome'], $body['status'], $body['conexao'], $body['funcao'], $body['categoria'], $body['dispositivo'], $body['index'], $body['id']]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO crm_chips (nome, numero, instance_name, status, conexao, funcao, categoria, dispositivo, index_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)");
                $stmt->execute([$body['nome'], $body['numero'], $body['instance_name'] ?? $body['nome'], $body['status'], $body['conexao'], $body['funcao'], $body['categoria'], $body['dispositivo'], $body['index']]);
            }

        } elseif ($action === 'delete') {
            if (!empty($input['id'])) {
                $stmt = $pdo->prepare("DELETE FROM crm_chips WHERE id = ?");
                $stmt->execute([$body['id']]);
            }

        } elseif ($action === 'update_category' || $action === 'move') {
            if (!empty($input['id'])) {
                $stmt = $pdo->prepare("UPDATE crm_chips SET categoria = ? WHERE id = ?");
                $stmt->execute([$input['categoria'], $input['id']]);
            }

        } elseif ($action === 'save_devices') {
            $config['devices'] = $input['devices'] ?? [];
            try {
                $stmtSave = $pdo->prepare("INSERT INTO crm_settings (username, setting_key, setting_value) VALUES (?, 'contingencia_config', ?)
                                           ON CONFLICT (username, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP");
                $stmtSave->execute([$username, json_encode($config)]);
            } catch (Exception $e) { /* silent fail */
            }
            // Sync local fallback
            $config_file = $dir . '/config_' . $username . '.json';
            file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        } elseif ($action === 'save_col') {
            $colId = $input['id'] ?? null;
            if ($colId && isset($config['cols'][$colId])) {
                $config['cols'][$colId]['title'] = $input['title'] ?? $config['cols'][$colId]['title'];
                try {
                    $stmtSave = $pdo->prepare("INSERT INTO crm_settings (username, setting_key, setting_value) VALUES (?, 'contingencia_config', ?)
                                               ON CONFLICT (username, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP");
                    $stmtSave->execute([$username, json_encode($config)]);
                } catch (Exception $e) { /* silent fail */
                }
                // Sync local fallback
                $config_file = $dir . '/config_' . $username . '.json';
                file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }
}

// =========================================================================
// 4. DADOS PARA RENDERIZAÇÃO HTML INICIAL
// =========================================================================
$dir = __DIR__ . '/chips_data';
$config_file = $dir . '/config_' . $username . '.json';
$default_config = [
    'cols' => [
        '1' => ['title' => 'Administradores', 'icon' => 'shield-alert', 'color' => 'var(--yellow)'],
        '2' => ['title' => 'Campanha ADS', 'icon' => 'target', 'color' => 'var(--blue)'],
        '3' => ['title' => 'Gerar Boletos', 'icon' => 'file-text', 'color' => 'var(--green)']
    ],
    'devices' => [
        'MOTOROLA 01',
        'MOTOROLA 02',
        'MOTOROLA 03',
        'MOTOROLA 04',
        'MOTOROLA 05',
        'MOTOROLA 06',
        'MOTOROLA 07',
        'MOTOROLA 08',
        'MOTOROLA 09',
        'MOTOROLA 10'
    ]
];
$config_html = json_decode(@file_get_contents($config_file), true) ?: $default_config;

// URL da API aponta para esta própria página (self-endpoint multi-tenant)
$api_self = '/wazio/contingencia';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WAZ.IO /// CONTINGÊNCIA</title>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="/wazio/public/images/waz-icon-hd.png?v=5" type="image/png">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/wazio/public/css/contingencia.css?v=<?= time() ?>">

    <style>
        .col-filters {
            display: flex;
            gap: 4px;
            margin-right: 8px;
        }

        .filter-icon-btn {
            background: transparent;
            border: 1px solid transparent;
            color: var(--muted);
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
            transition: 0.2s;
        }

        .filter-icon-btn:hover,
        .filter-icon-btn.active-filter {
            color: var(--green);
            border-color: var(--border);
            background: rgba(188, 253, 73, 0.1);
        }

        .filter-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 5px;
            z-index: 100;
            display: none;
        }

        .filter-dropdown.show {
            display: block;
        }

        .modal-overlay {
            position: fixed !important;
            inset: 0 !important;
            background: rgba(0, 0, 0, 0.85) !important;
            z-index: 99999 !important;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
            padding: 10px;
            opacity: 0;
            transition: opacity 0.3s ease !important;
        }

        .modal-overlay.open {
            opacity: 1 !important;
            display: flex !important;
        }
    </style>
</head>

<body>

    <div id="initial-loader"
        style="position:fixed; inset:0; z-index:9999; background:var(--bg); display:flex; flex-direction:column; align-items:center; justify-content:center; transition: opacity 0.5s ease;">
        <div class="spin"
            style="width:40px; height:40px; border:2px solid rgba(188,253,73,0.2); border-top-color:var(--green); border-radius:50%; margin-bottom:20px;">
        </div>
        <div
            style="color:var(--green); font-family:var(--mono); font-size:10px; letter-spacing:4px; font-weight:700; animation: pulse 2s infinite;">
            WAZ.IO /// INICIALIZANDO</div>
    </div>

    <main class="main" style="margin-left:0; height:100vh; overflow:hidden;">

        <div class="topbar" style="padding: 10px 20px;">
            <div class="topbar-left">
                <button onclick="window.location.href='/wazio/dashboard'" class="btn btn-ghost"
                    style="padding: 6px 10px; font-size: 14px; background:#080c09; border:1px solid var(--border); box-shadow: inset 0 2px 5px rgba(0,0,0,0.8);">
                    <i data-lucide="arrow-left" width="16" height="16"></i>
                </button>
                <i data-lucide="shield-check" width="18" height="18" style="color:var(--green)"></i>
                <span class="topbar-title" id="topTitle">
                    <i data-lucide="shield" width="18" style="color:var(--green)"></i> WAZ.IO <span class="glow-text"
                        style="color:var(--green); text-shadow: 0 0 10px rgba(188,253,73,0.6);">///</span> CONTINGÊNCIA
                </span>
            </div>
            <div class="topbar-right">
                <button class="btn btn-ghost" onclick="openDeviceModal()"
                    style="padding: 8px 16px; background:#080c09; border:1px solid var(--border); box-shadow: inset 0 2px 5px rgba(0,0,0,0.8);">
                    <i data-lucide="smartphone" width="14"></i> DISPOSITIVOS
                </button>
                <button class="btn btn-primary" onclick="openModal()" style="padding: 8px 16px;">
                    <i data-lucide="plus" width="14"></i> NOVO CHIP
                </button>
            </div>
        </div>

        <div class="content"
            style="display:flex; flex-direction:column; height: calc(100vh - 55px); padding-bottom: 0;">

            <div class="kpi-grid-mini">
                <div class="kpi-mini"><span class="kpi-mini-title">Admins.</span>
                    <div style="display: flex; align-items: center; gap: 8px;"><i data-lucide="shield-alert"
                            style="color: var(--yellow);" width="22"></i><span class="kpi-mini-val"
                            style="color: var(--yellow);" id="kpi-disparadores">0</span></div>
                </div>
                <div class="kpi-mini"><span class="kpi-mini-title">Campanha ADS</span>
                    <div style="display: flex; align-items: center; gap: 8px;"><i data-lucide="target"
                            style="color: var(--blue);" width="22"></i><span class="kpi-mini-val"
                            style="color: var(--blue);" id="kpi-ads">0</span></div>
                </div>
                <div class="kpi-mini"><span class="kpi-mini-title">Gerar Bol.</span>
                    <div style="display: flex; align-items: center; gap: 8px;"><i data-lucide="file-text"
                            style="color: var(--green3);" width="22"></i><span class="kpi-mini-val"
                            style="color: var(--green3);" id="kpi-boletos">0</span></div>
                </div>
                <div class="kpi-mini highlight"><span class="kpi-mini-title" style="color: var(--green);">Total
                        Ativos</span>
                    <div style="display: flex; align-items: center; gap: 8px;"><i data-lucide="check-circle-2"
                            style="color: var(--green);" width="26"></i><span class="kpi-mini-val text-white"
                            id="kpi-total">0</span></div>
                </div>
                <div class="kpi-mini"><span class="kpi-mini-title">Aquecendo</span>
                    <div style="display: flex; align-items: center; gap: 8px;"><i data-lucide="flame"
                            style="color: #f97316;" width="22"></i><span class="kpi-mini-val" style="color: #f97316;"
                            id="kpi-aquecimento">0</span></div>
                </div>
                <div class="kpi-mini"><span class="kpi-mini-title">Banidos</span>
                    <div style="display: flex; align-items: center; gap: 8px;"><i data-lucide="ban"
                            style="color: var(--red);" width="22"></i><span class="kpi-mini-val"
                            style="color: var(--red);" id="kpi-banidos">0</span></div>
                </div>
                <div class="kpi-mini"><span class="kpi-mini-title">Em Análise</span>
                    <div style="display: flex; align-items: center; gap: 8px;"><i data-lucide="search"
                            style="color: #a855f7;" width="22"></i><span class="kpi-mini-val" style="color: #a855f7;"
                            id="kpi-analise">0</span></div>
                </div>
            </div>

            <div class="kanban-board-wrapper">
                <?php foreach ($config_html['cols'] as $id => $info): ?>
                    <div class="kanban-col-wrapper">
                        <div class="kanban-col-header"
                            style="align-items: center; display: flex; justify-content: space-between;">

                            <div class="kanban-col-title" style="display: flex; align-items: center; gap: 8px; flex: 1;">
                                <i data-lucide="<?= $info['icon'] ?>" width="16" height="16"
                                    style="color: <?= $info['color'] ?>;"></i>
                                <span class="col-title-text" id="col-title-<?= $id ?>" contenteditable="false"
                                    style="padding: 2px 6px; border-radius: 4px; border: 1px solid transparent; transition: 0.2s; white-space: nowrap; outline: none;"><?= $info['title'] ?></span>

                                <button class="icon-btn-clean" id="btn-edit-col-<?= $id ?>"
                                    onclick="editColumnName('<?= $id ?>')"><i data-lucide="edit-2" width="12"></i></button>
                                <button class="icon-btn-clean save-btn" id="btn-save-col-<?= $id ?>"
                                    onclick="saveColumnName('<?= $id ?>')" style="display: none;"><i data-lucide="save"
                                        width="14"></i></button>
                            </div>

                            <div style="display: flex; align-items: center;">
                                <div class="col-filters">
                                    <div style="position:relative;">
                                        <button class="filter-icon-btn" onclick="toggleDropdown(this)"
                                            title="Filtrar Status"><i data-lucide="activity" width="12"></i></button>
                                        <div class="filter-dropdown">
                                            <select onchange="setColFilter('<?= $id ?>', 'status', this)">
                                                <option value="ALL">🔴 Status: Todos</option>
                                                <option value="ATIVOS">🟢 Online</option>
                                                <option value="OFFLINE">🔴 Offline</option>
                                                <option value="AQUECENDO">🔥 Aquecendo</option>
                                                <option value="ANÁLISE">🔎 Análise</option>
                                                <option value="BANIDO">🚫 Banidos</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="position:relative;">
                                        <button class="filter-icon-btn" onclick="toggleDropdown(this)"
                                            title="Filtrar Conexão"><i data-lucide="wifi" width="12"></i></button>
                                        <div class="filter-dropdown">
                                            <select onchange="setColFilter('<?= $id ?>', 'conexao', this)">
                                                <option value="ALL">🔴 Conexão: Todas</option>
                                                <option value="ONLINE">🟢 Online</option>
                                                <option value="OFFLINE">🔴 Offline</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="position:relative;">
                                        <button class="filter-icon-btn" onclick="toggleDropdown(this)"
                                            title="Filtrar Dispositivo"><i data-lucide="smartphone" width="12"></i></button>
                                        <div class="filter-dropdown">
                                            <select onchange="setColFilter('<?= $id ?>', 'dispositivo', this)">
                                                <option value="ALL">🔴 Dispositivo: Todos</option>
                                                <?php foreach ($config_html['devices'] as $dev): ?>
                                                    <option value="<?= htmlspecialchars($dev) ?>"><?= htmlspecialchars($dev) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="position:relative;">
                                        <button class="filter-icon-btn" onclick="toggleDropdown(this)"
                                            title="Filtrar Função"><i data-lucide="briefcase" width="12"></i></button>
                                        <div class="filter-dropdown">
                                            <select onchange="setColFilter('<?= $id ?>', 'funcao', this)">
                                                <option value="ALL">🔴 Função: Todas</option>
                                                <option value="ADMINISTRADOR">ADMINISTRADOR</option>
                                                <option value="ATENDIMENTO">ATENDIMENTO</option>
                                                <option value="CAMPANHA ADS">CAMPANHA ADS</option>
                                                <option value="GERAR BOLETOS">GERAR BOLETOS</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <span class="kanban-badge-count" id="count-<?= $id ?>">0</span>
                            </div>
                        </div>
                        <div id="col-<?= $id ?>" class="kanban-col" data-category="<?= $id ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </main>

    <!-- MODAL: CHIP -->
    <div id="chipModal" class="modal-overlay">
        <div class="modal" id="modalContent">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                <h3
                    style="margin:0; font-size:13px; color:var(--green); text-transform:uppercase; letter-spacing:0.08em;">
                    <i data-lucide="settings" width="16"></i> GERENCIADOR DE CHIP
                </h3>
                <button onclick="closeModal()" type="button" class="modal-close"><i data-lucide="x" width="16"
                        height="16"></i></button>
            </div>

            <form id="chipForm" onsubmit="saveChip(event)">
                <input type="hidden" id="chipId">
                <div class="form-grid-group">
                    <div class="form-group"><label class="form-label">ID</label>
                        <div class="custom-select-container"><select id="chipIndex" class="form-select"></select></div>
                    </div>
                    <div class="form-group"><label class="form-label">Nome da Operação</label><input type="text"
                            id="chipNome" required class="form-input"></div>
                </div>
                <div class="form-grid-group">
                    <div class="form-group"><label class="form-label">Número WhatsApp</label><input type="text"
                            id="chipNumero" required class="form-input"></div>
                    <div class="form-group"><label class="form-label">Dispositivo</label>
                        <div class="custom-select-container"><select id="chipDispositivo" class="form-select"></select>
                        </div>
                    </div>
                </div>
                <!-- NOVO: Vínculo de Instância -->
                <div class="form-grid-group">
                    <div class="form-group" style="flex: 1;"><label class="form-label">Vincular Instância
                            (Uazapi)</label>
                        <div class="custom-select-container">
                            <select id="chipInstanceName" class="form-select">
                                <option value="">Nenhuma instância vinculada</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-grid-group">
                    <div class="form-group"><label class="form-label">Status</label>
                        <div class="custom-select-container">
                            <select id="chipStatus" class="form-select">
                                <option value="DISPONÍVEL">DISPONÍVEL</option>
                                <option value="RESTABELECIDO">RESTABELECIDO</option>
                                <option value="AQUECENDO">AQUECENDO</option>
                                <option value="ANÁLISE">ANÁLISE</option>
                                <option value="BANIDO">BANIDO</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Conexão</label>
                        <div class="custom-select-container"><select id="chipConexao" class="form-select">
                                <option value="ONLINE">ONLINE</option>
                                <option value="OFFLINE">OFFLINE</option>
                            </select></div>
                    </div>
                </div>
                <div class="form-grid-group">
                    <div class="form-group"><label class="form-label">Função</label>
                        <div class="custom-select-container"><select id="chipFuncao" class="form-select">
                                <option value="ADMINISTRADOR">ADMINISTRADOR</option>
                                <option value="ATENDIMENTO">ATENDIMENTO</option>
                                <option value="CAMPANHA ADS">CAMPANHA ADS</option>
                                <option value="CRIADOR DE GRUPOS">CRIADOR DE GRUPOS</option>
                                <option value="CRIADOR DE LINKS">CRIADOR DE LINKS</option>
                                <option value="GERAR BOLETOS">GERAR BOLETOS</option>
                                <option value="AQUECIMENTO">AQUECIMENTO</option>
                            </select></div>
                    </div>
                    <div class="form-group"><label class="form-label">Categoria</label>
                        <div class="custom-select-container"><select id="chipCategoria" class="form-select">
                                <?php foreach ($config_html['cols'] as $k => $c): ?>
                                    <option value="<?= $k ?>"><?= mb_strtoupper($c['title']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"
                    style="width:100%; margin-top:10px; padding:12px;">CADASTRAR CHIP</button>
            </form>
        </div>
    </div>

    <!-- MODAL: DISPOSITIVOS -->
    <div id="deviceModal" class="modal-overlay">
        <div class="modal" id="deviceModalContent">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                <h3
                    style="margin:0; font-size:13px; color:var(--green); text-transform:uppercase; letter-spacing:0.08em;">
                    <i data-lucide="smartphone" width="16"></i> LISTA DE DISPOSITIVOS
                </h3>
                <button onclick="closeDeviceModal()" type="button" class="modal-close"><i data-lucide="x" width="16"
                        height="16"></i></button>
            </div>

            <div class="form-group" style="display:flex; gap:12px; margin-bottom: 20px; align-items: center;">
                <input type="text" id="newDeviceInput" class="form-input" style="flex:1;"
                    placeholder="Novo dispositivo (Ex: GALAXY 01)"
                    onkeydown="if(event.key === 'Enter'){ event.preventDefault(); window.addDeviceItem(); }">
                <button type="button" onclick="window.addDeviceItem()" class="btn btn-primary"
                    style="padding: 0 16px; height: 34px; white-space: nowrap;"><i data-lucide="plus" width="14"></i>
                    ADICIONAR</button>
            </div>

            <div id="deviceListContainer"></div>

            <button onclick="saveDevicesConfig()" class="btn btn-primary"
                style="width:100%; padding:12px; margin-top: 16px;"><i data-lucide="save" width="14"></i> SALVAR
                ALTERAÇÕES</button>
        </div>
    </div>

    <!-- MODAL: CONFIRMAR DELETE -->
    <div id="modalConfirm" class="modal-overlay">
        <div class="modal" style="max-width:360px;">
            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom: 1px solid rgba(248,113,113,0.2); padding-bottom: 12px;">
                <h3 id="confirmTitle"
                    style="margin:0; color:var(--red); text-shadow: 0 0 10px rgba(248,113,113,0.5); font-size: 14px; border:none; padding:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="trash-2" width="18"></i> DELETAR CHIP
                </h3>
                <button onclick="closeConfirmModal()" type="button" class="modal-close" style="position:static;"><i
                        data-lucide="x" width="16"></i></button>
            </div>
            <div id="confirmMsg"
                style="margin:15px 0 20px; font-family:var(--font-ui); color:var(--text); font-size:13px; line-height:1.6; text-align:center;">
            </div>
            <div style="display:flex; gap:10px; margin-top:25px;">
                <button class="btn btn-ghost" onclick="closeConfirmModal()" style="flex:1;">Cancelar</button>
                <button class="btn"
                    style="flex:1; background: linear-gradient(180deg, #f87171 0%, #dc2626 100%); color: #ffffff; border: 1px solid #f87171; box-shadow: 0 0 15px rgba(248,113,113,0.3);"
                    onclick="executarConfirm()">Deletar</button>
            </div>
        </div>
    </div>

    <div id="toast"></div>

    <script>
        // Sobrescreve a API_URL do script externo para apontar para a rota correta deste projeto
        window.WAZIO_API_URL = '/wazio/contingencia';
    </script>
    <script src="/wazio/public/js/contingencia.js?v=<?= time() ?>"></script>
</body>

</html>