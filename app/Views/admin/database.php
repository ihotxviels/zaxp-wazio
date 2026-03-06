<?php require_once __DIR__ . '/../layouts/admin_header.php'; ?>

<?php
// Busca estatísticas reais do PostgreSQL
$pdo = get_db_connection();
$tables = [
    ['name' => 'crm_instances', 'label' => 'Instâncias/Whatsapp'],
    ['name' => 'crm_contacts', 'label' => 'Contatos/Leads'],
    ['name' => 'crm_messages', 'label' => 'Mensagens'],
    ['name' => 'crm_funnels', 'label' => 'Fluxos/Funis'],
    ['name' => 'crm_finance', 'label' => 'Transações'],
    ['name' => 'crm_checkout', 'label' => 'Checkouts/Carrinhos'],
    ['name' => 'crm_funnel_progress', 'label' => 'Progresso Funis'],
    ['name' => 'crm_labels', 'label' => 'Etiquetas'],
    ['name' => 'crm_chips', 'label' => 'Contingência (Chips)'],
    ['name' => 'crm_remarketing', 'label' => 'Remarketing'],
    ['name' => 'crm_users', 'label' => 'Usuários/Sessão'],
    ['name' => 'crm_settings', 'label' => 'Configurações App'],
    ['name' => 'crm_system_logs', 'label' => 'Logs do Sistema'],
];

$stats = [];
$totalRecords = 0;
$totalSizeStr = "0 MB";

if ($pdo) {
    try {
        foreach ($tables as $t) {
            $stmt = $pdo->query("SELECT count(*) FROM " . $t['name']);
            $count = $stmt->fetchColumn();

            // Pega tamanho da tabela
            $stmtSize = $pdo->query("SELECT pg_size_pretty(pg_total_relation_size('" . $t['name'] . "'))");
            $size = $stmtSize->fetchColumn();

            $stats[] = [
                'table' => $t['name'],
                'label' => $t['label'],
                'count' => $count,
                'size' => $size
            ];
            $totalRecords += $count;
        }

        $stmtTotal = $pdo->query("SELECT pg_size_pretty(sum(pg_total_relation_size(quote_ident(schemaname) || '.' || quote_ident(relname)))) FROM pg_stat_user_tables");
        $totalSizeStr = $stmtTotal->fetchColumn() ?: "0 MB";
    } catch (Exception $e) {
        // Fallback or error display
    }
}
?>

<div class="topbar">
    <div class="topbar-left">
        <span class="topbar-title">
            <i data-lucide="database" width="18" style="color:var(--green)"></i> WAZIO <span class="glow-text"
                style="color:var(--green);">///</span> DASHBOARD DATABASE
        </span>
    </div>
</div>

<div class="content">
    <div class="section">
        <div class="section-head">
            <div class="section-title">Gerenciamento de Dados — PostgreSQL (Cloud Engine)</div>
            <div class="section-actions">
                <button class="btn btn-ghost" onclick="window.location.reload()" style="gap:8px;">
                    <i data-lucide="refresh-cw" width="14"></i> Sincronizar
                </button>
            </div>
        </div>
        <div class="section-body" style="padding:25px;">
            <div class="db-stats-grid">
                <div class="db-stat-card">
                    <div class="s-val"><?= $totalRecords ?></div>
                    <div class="s-label">REGISTROS TOTAIS (PSQL)</div>
                </div>
                <div class="db-stat-card">
                    <div class="s-val" style="color:var(--green)"><?= $totalSizeStr ?></div>
                    <div class="s-label">TAMANHO NO DISCO</div>
                </div>
                <div class="db-stat-card">
                    <div class="s-val" style="color:var(--green)">ONLINE</div>
                    <div class="s-label">STATUS POSTGRESQL</div>
                </div>
            </div>

            <div class="terminal-table" style="margin-top:30px;">
                <div class="table-header-metal">
                    <span>TABELA</span>
                    <span>FUNCIONALIDADE</span>
                    <span>REGISTROS</span>
                    <span>TAMANHO</span>
                    <span>AÇÕES</span>
                </div>
                <?php foreach ($stats as $s): ?>
                    <div class="table-row-metal">
                        <span style="color:var(--green); font-family:var(--mono)"><?= $s['table'] ?></span>
                        <span style="font-size:11px;"><?= $s['label'] ?></span>
                        <span><?= $s['count'] ?></span>
                        <span style="font-size:11px; opacity:0.7;"><?= $s['size'] ?></span>
                        <div style="display:flex; gap:6px;">
                            <button onclick="abrirSchema('<?= $s['table'] ?>')" class="btn btn-ghost"
                                style="font-size: 9px; padding: 4px 8px; height: auto; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(188,253,73,0.3);">
                                <i data-lucide="eye" width="10"></i> SCHEMA
                            </button>
                            <a href="https://criadordigital-postgres-pgweb.7phgib.easypanel.host/" target="_blank"
                                class="btn btn-ghost"
                                style="text-decoration:none; font-size: 9px; padding: 4px 8px; height: auto; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(188,253,73,0.3); color:var(--green);">
                                <i data-lucide="external-link" width="10"></i> ABRIR
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SCHEMA -->
<div id="modalSchema" class="modal-overlay" style="display:none;">
    <div class="modal-content-metal">
        <div class="modal-header-metal">
            <div style="display:flex; align-items:center; gap:10px;">
                <i data-lucide="layout" width="18" style="color:var(--green)"></i>
                <span id="schemaTitle" style="font-family:var(--mono); color:#fff; font-weight:700;">ESTRUTURA DA TABELA</span>
            </div>
            <button onclick="fecharModal()" class="btn-close-metal"><i data-lucide="x" width="16"></i></button>
        </div>
        <div class="modal-body-metal">
            <div id="schemaLoading" style="text-align:center; padding:40px; color:var(--muted);">
                <i data-lucide="loader-2" class="spin" width="24"></i>
                <div style="margin-top:10px; font-size:11px;">Consultando Information Schema...</div>
            </div>
            <div id="schemaDisplay" style="display:none;">
                <div class="schema-grid-header">
                    <span>COLUNA</span>
                    <span>TIPO</span>
                    <span>NULL?</span>
                    <span>DEFAULT</span>
                </div>
                <div id="schemaList" class="schema-list"></div>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .modal-content-metal {
        background: #0d110e;
        border: 1px solid rgba(188, 253, 73, 0.3);
        width: 100%;
        max-width: 600px;
        border-radius: 12px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 0 0 20px rgba(188, 253, 73, 0.05);
        overflow: hidden;
    }

    .modal-header-metal {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .btn-close-metal {
        background: transparent;
        border: none;
        color: var(--muted);
        cursor: pointer;
        padding: 5px;
        border-radius: 4px;
        transition: 0.2s;
    }

    .btn-close-metal:hover {
        color: var(--red);
        background: rgba(248, 113, 113, 0.1);
    }

    .modal-body-metal {
        padding: 20px;
        max-height: 70vh;
        overflow-y: auto;
    }

    /* Estilo para Tabela do Schema */
    .schema-grid-header {
        display: grid;
        grid-template-columns: 2fr 1.5fr 0.8fr 1.5fr;
        font-family: var(--mono);
        font-size: 10px;
        color: var(--green);
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        margin-bottom: 10px;
        font-weight: 700;
    }

    .schema-item {
        display: grid;
        grid-template-columns: 2fr 1.5fr 0.8fr 1.5fr;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        font-size: 12px;
        align-items: center;
    }

    .col-name { color: #fff; font-family: var(--mono); font-weight: 500; }
    .col-type { color: var(--muted); font-size: 11px; }
    .col-null { color: var(--muted); font-size: 11px; }
    .col-def { color: var(--green); font-size: 10px; font-family: var(--mono); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .db-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
    }

    .db-stat-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border);
        padding: 25px;
        border-radius: 16px;
        text-align: center;
    }

    .s-val {
        font-family: var(--font-ui);
        font-size: 24px;
        font-weight: 800;
        color: #fff;
        margin-bottom: 5px;
    }

    .s-label {
        font-family: var(--mono);
        font-size: 10px;
        color: var(--muted);
        letter-spacing: 1px;
    }

    .table-header-metal {
        display: grid;
        grid-template-columns: 2fr 1.5fr 1fr 0.8fr 1fr;
        padding: 12px 20px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: var(--mono);
        font-size: 11px;
        color: var(--green);
        font-weight: 800;
    }

    .table-row-metal {
        display: grid;
        grid-template-columns: 2fr 1.5fr 1fr 0.8fr 1fr;
        padding: 12px 20px;
        border-bottom: 1px solid var(--border);
        align-items: center;
        color: #fff;
        font-size: 13px;
    }

    .table-row-metal:hover {
        background: rgba(255, 255, 255, 0.02);
    }
</style>

<script>
async function abrirSchema(table) {
    const modal = document.getElementById('modalSchema');
    const display = document.getElementById('schemaDisplay');
    const loading = document.getElementById('schemaLoading');
    const list = document.getElementById('schemaList');
    const title = document.getElementById('schemaTitle');
    
    title.innerText = `ESQUEMA: ${table.toUpperCase()}`;
    modal.style.display = 'flex';
    display.style.display = 'none';
    loading.style.display = 'block';
    list.innerHTML = '';

    try {
        const res = await fetch(`/wazio/app/Controllers/ApiController.php?action=db_schema&table=${table}`);
        const data = await res.json();
        
        if (data.ok) {
            data.schema.forEach(col => {
                const item = document.createElement('div');
                item.className = 'schema-item';
                item.innerHTML = `
                    <span class="col-name">${col.name}</span>
                    <span class="col-type">${col.type}</span>
                    <span class="col-null">${col.nullable}</span>
                    <span class="col-def">${col.default || '-'}</span>
                `;
                list.appendChild(item);
            });
            loading.style.display = 'none';
            display.style.display = 'block';
            lucide.createIcons();
        } else {
            list.innerHTML = `<div style="color:var(--red); padding:20px; text-align:center;">Erro: ${data.erro}</div>`;
            loading.style.display = 'none';
            display.style.display = 'block';
        }
    } catch (e) {
        list.innerHTML = `<div style="color:var(--red); padding:20px; text-align:center;">Erro na requisição.</div>`;
        loading.style.display = 'none';
        display.style.display = 'block';
    }
}

function fecharModal() {
    document.getElementById('modalSchema').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>