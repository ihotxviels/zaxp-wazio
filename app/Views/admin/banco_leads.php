<?php
require_once __DIR__ . '/../layouts/admin_header.php';
if (!isset($_SESSION['user'])) {
    header("Location: /wazio/");
    exit;
}
?>

<style>
    .leads-wrapper {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 60px);
        overflow: hidden;
    }

    .leads-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
        gap: 12px;
        flex-wrap: wrap;
    }

    .leads-topbar-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* Mode toggle */
    .mode-toggle {
        display: flex;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 3px;
        gap: 2px;
    }

    .mode-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 5px 14px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 11px;
        font-weight: 700;
        font-family: var(--font-ui);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--muted);
        background: transparent;
        transition: all 0.2s;
    }

    .mode-btn.active {
        background: var(--card);
        color: var(--green);
        border: 1px solid var(--border);
    }

    .leads-filter-bar {
        display: flex;
        gap: 10px;
        padding: 12px 20px;
        background: rgba(0, 0, 0, 0.2);
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-metal {
        flex: 1;
        min-width: 200px;
        display: flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(180deg, #1c2126 0%, #0a0c0e 100%);
        border: 1px solid #2c3136;
        border-radius: 6px;
        padding: 0 12px;
        height: 32px;
        transition: border-color 0.2s;
    }

    .search-metal:focus-within {
        border-color: #bcfd49;
    }

    .search-metal input {
        background: transparent;
        border: none;
        color: #e2e8f0;
        outline: none;
        font-size: 12px;
        width: 100%;
        font-family: 'JetBrains Mono', monospace;
    }

    .search-metal input::placeholder {
        color: #4b5563;
    }

    .select-icon-wrap {
        position: relative;
        display: flex;
        align-items: center;
        background: linear-gradient(180deg, #1c2126 0%, #0a0c0e 100%);
        border: 1px solid #2c3136;
        border-radius: 6px;
        height: 32px;
        transition: border-color 0.2s;
    }

    .select-icon-wrap:focus-within {
        border-color: #bcfd49;
    }

    .select-icon-wrap i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: var(--muted);
    }

    .metal-sel {
        background: transparent;
        border: none;
        color: #e2e8f0;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        padding: 0 28px 0 10px;
        height: 100%;
        width: 100%;
        font-family: 'JetBrains Mono', monospace;
        outline: none;
        cursor: pointer;
        color-scheme: dark;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%23bcfd49' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 11px 11px;
    }

    .metal-sel.with-icon {
        padding-left: 34px;
    }

    .metal-sel:focus {
        color: #bcfd49;
    }

    .metal-sel option {
        background-color: #1c2126;
        color: #e2e8f0;
        font-weight: normal;
    }

    .metal-sel option:checked,
    .metal-sel option:hover {
        background-color: rgba(188, 253, 73, 0.1) !important;
        color: #bcfd49;
    }

    .leads-table-wrap {
        flex: 1;
        overflow-y: auto;
    }

    table.ltable {
        width: 100%;
        border-collapse: collapse;
    }

    table.ltable thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #0d0f0d;
        color: var(--green);
        text-align: left;
        padding: 12px 16px;
        font-size: 10px;
        text-transform: uppercase;
        font-family: var(--font-ui);
        letter-spacing: .08em;
        border-bottom: 1px solid var(--border);
    }

    table.ltable td {
        padding: 11px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        font-size: 13px;
        vertical-align: middle;
    }

    table.ltable tr:hover td {
        background: rgba(188, 253, 73, 0.025);
    }

    .tag-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        font-family: var(--font-ui);
        text-transform: uppercase;
    }

    .btn-icon {
        width: 28px;
        height: 28px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: transparent;
        color: var(--muted);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: .2s;
    }

    .btn-icon:hover {
        border-color: var(--green);
        color: var(--green);
    }

    .pagination {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-top: 1px solid var(--border);
        flex-shrink: 0;
    }

    .page-info {
        font-size: 11px;
        color: var(--muted);
        font-family: var(--mono);
        flex: 1;
    }

    .empty-row td {
        text-align: center;
        padding: 80px 20px;
        color: var(--muted);
        font-size: 13px;
    }
</style>

<div class="leads-wrapper">
    <!-- TOPBAR -->
    <div class="leads-topbar">
        <div class="leads-topbar-left">
            <span class="topbar-title"
                style="margin-left:0; white-space:nowrap; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="users-2" width="18" style="color:var(--green)"></i>
                <span>WAZ.IO <span style="color:var(--green);">///</span> LEADS</span>
            </span>
            <div class="mode-toggle">
                <button class="mode-btn active" id="btnAtend" onclick="setMode('atendimento')">
                    <i data-lucide="zap" width="13"></i> Atendimento
                </button>
                <button class="mode-btn" id="btnCamp" onclick="setMode('campanha')">
                    <i data-lucide="megaphone" width="13"></i> Campanha Ads
                </button>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-ghost" onclick="exportar()"><i data-lucide="download" width="14"></i> CSV</button>
            <button class="btn btn-ghost" onclick="window.location.href='/wazio/kanban'"><i data-lucide="layout-kanban"
                    width="14"></i> Kanban</button>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="leads-filter-bar">
        <div class="search-metal">
            <i data-lucide="search" width="14" style="color:var(--muted);flex-shrink:0"></i>
            <input type="text" id="searchQ" placeholder="Buscar nome ou número..." oninput="debounceSearch()">
        </div>
        <div class="select-icon-wrap">
            <i data-lucide="tags" width="14"></i>
            <select class="metal-sel with-icon" id="filterTag" onchange="loadLeads(1)">
                <option value="">Todas as Etiquetas</option>
            </select>
        </div>
        <div class="select-icon-wrap">
            <i data-lucide="smartphone" width="14"></i>
            <select class="metal-sel with-icon" id="filterInst" onchange="loadLeads(1)">
                <option value="">Filtrar por instância</option>
            </select>
        </div>
        <span id="leadsCount"
            style="font-size:11px;color:var(--muted);font-family:var(--mono);white-space:nowrap"></span>
    </div>

    <!-- TABELA -->
    <div class="leads-table-wrap">
        <table class="ltable">
            <thead>
                <tr>
                    <th>Contato</th>
                    <th>WhatsApp</th>
                    <th>Etiqueta</th>
                    <th>Instância</th>
                    <th>Última Interação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="leadsBody">
                <tr class="empty-row">
                    <td colspan="6"><i data-lucide="loader-2" class="spin" width="20"></i><br>Carregando...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- PAGINAÇÃO -->
    <div class="pagination">
        <span class="page-info" id="pageInfo">—</span>
        <button class="btn btn-ghost" id="btnPrev" onclick="loadLeads(currentPage - 1)" disabled
            style="padding:4px 10px;font-size:11px">
            <i data-lucide="chevron-left" width="13"></i> Anterior
        </button>
        <button class="btn btn-ghost" id="btnNext" onclick="loadLeads(currentPage + 1)"
            style="padding:4px 10px;font-size:11px">
            Próximo <i data-lucide="chevron-right" width="13"></i>
        </button>
    </div>
</div>

<script>
    const API = '/wazio/index.php?route=api';
    const PER_PAGE = 100;
    let currentPage = 1;
    let totalLeads = 0;
    let searchTimer = null;
    let currentMode = 'atendimento';

    const ALL_TAGS = {
        atendimento: [
            { label: 'Sem Etiqueta (Novos)', value: '__sem_etiqueta__', icon: 'circle-dashed' },
            { label: 'Boas-Vindas', value: 'boas_vindas', icon: 'hand' },
            { label: 'Quebrar Objeção', value: 'quebrar-objeção', icon: 'shield-alert' },
            { label: 'Boleto Pendente', value: 'boleto pendente', icon: 'hourglass' },
            { label: 'Boleto Pago', value: 'boleto pago', icon: 'check-circle-2' },
            { label: 'Adicionado no Grupo', value: 'adicionado no grupo', icon: 'users' },
        ],
        campanha: [
            { label: 'Novo Cliente', value: 'novo cliente', icon: 'target' },
            { label: 'Acompanhar', value: 'acompanhar', icon: 'eye' },
        ]
    };

    const TAG_COLORS = {
        'boas_vindas': { bg: 'rgba(59,130,246,.15)', color: '#3b82f6', icon: 'hand' },
        'quebrar-objeção': { bg: 'rgba(168,85,247,.15)', color: '#a855f7', icon: 'shield-alert' },
        'boleto pendente': { bg: 'rgba(239,68,68,.15)', color: '#ef4444', icon: 'hourglass' },
        'boleto pago': { bg: 'rgba(34,197,94,.15)', color: '#22c55e', icon: 'check-circle-2' },
        'adicionado no grupo': { bg: 'rgba(34,197,94,.12)', color: '#22c55e', icon: 'users' },
        'novo cliente': { bg: 'rgba(59,130,246,.12)', color: '#3b82f6', icon: 'target' },
        'acompanhar': { bg: 'rgba(234,179,8,.15)', color: '#eab308', icon: 'eye' },
    };

    function debounceSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadLeads(1), 450);
    }

    function setMode(mode) {
        currentMode = mode;
        document.getElementById('btnAtend').classList.toggle('active', mode === 'atendimento');
        document.getElementById('btnCamp').classList.toggle('active', mode === 'campanha');

        // Atualizar dropdown de tags com base no modo
        const tagSel = document.getElementById('filterTag');
        const tags = ALL_TAGS[mode] || [];
        tagSel.innerHTML = `<option value="">Todas as Etiquetas</option>` + tags.map(t => `<option value="${t.value}">${t.label}</option>`).join('');

        // Limpar tag e recarregar
        tagSel.value = '';
        loadLeads(1);
    }

    async function loadLeads(page = 1) {
        currentPage = page;
        const body = document.getElementById('leadsBody');
        const tag = document.getElementById('filterTag').value;
        const inst = document.getElementById('filterInst').value;
        const q = document.getElementById('searchQ').value.trim();
        const offset = (page - 1) * PER_PAGE;

        body.innerHTML = `<tr class="empty-row"><td colspan="6"><i data-lucide="loader-2" class="spin" width="20"></i><br>Carregando...</td></tr>`;
        if (window.lucide) lucide.createIcons();

        const url = `${API}&action=get_leads&limit=${PER_PAGE}&offset=${offset}&mode=${currentMode}` +
            (tag ? `&tag=${encodeURIComponent(tag)}` : '') +
            (inst ? `&instance=${encodeURIComponent(inst)}` : '') +
            (q ? `&q=${encodeURIComponent(q)}` : '');

        try {
            const r = await fetch(url);
            const data = await r.json();

            if (!data.ok || !data.data?.length) {
                body.innerHTML = `<tr class="empty-row"><td colspan="6"><i data-lucide="inbox" width="24" style="opacity:.3"></i><br>Nenhum lead encontrado.</td></tr>`;
                updatePagination(0, 0);
                if (window.lucide) lucide.createIcons();
                return;
            }

            totalLeads = data.total ?? data.data.length;
            updatePagination(page, totalLeads);

            // Popular instâncias no select
            const sel = document.getElementById('filterInst');
            data.data.forEach(l => {
                if (l.instance_name && ![...sel.options].some(o => o.value === l.instance_name)) {
                    const opt = document.createElement('option');
                    opt.value = l.instance_name; opt.textContent = l.instance_name;
                    sel.appendChild(opt);
                }
            });

            body.innerHTML = data.data.map(l => {
                const tags = (l.tags || []);
                const tagHtml = tags.length === 0
                    ? `<span class="tag-badge" style="background:rgba(255,255,255,.06);color:var(--muted);display:inline-flex;align-items:center;gap:4px;line-height:1;"><i data-lucide="circle-dashed" width="10" height="10"></i> Sem Etiqueta</span>`
                    : tags.map(t => {
                        const s = TAG_COLORS[t] || { bg: 'rgba(255,255,255,.08)', color: 'var(--muted)', icon: 'tag' };
                        return `<span class="tag-badge" style="background:${s.bg};color:${s.color};display:inline-flex;align-items:center;gap:4px;line-height:1;"><i data-lucide="${s.icon}" width="10" height="10"></i> ${t}</span>`;
                    }).join(' ');

                const date = l.updated_at
                    ? new Date(l.updated_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
                    : '—';
                const initials = (l.name || '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();

                return `<tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:30px;height:30px;border-radius:50%;background:rgba(188,253,73,.1);border:1px solid rgba(188,253,73,.2);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:var(--green);flex-shrink:0">${initials}</div>
                        <span style="font-weight:600">${l.name || 'Sem Nome'}</span>
                    </div>
                </td>
                <td style="font-family:var(--mono);color:var(--muted);font-size:12px">${l.phone || '—'}</td>
                <td>${tagHtml}</td>
                <td style="font-family:var(--mono);font-size:11px;color:var(--muted)">${l.instance_name || '—'}</td>
                <td style="font-size:11px;color:var(--muted)">${date}</td>
                <td>
                    <div style="display:flex;gap:5px">
                        <button class="btn-icon" title="Abrir Chat" onclick="window.location.href='/wazio/inbox?phone=${encodeURIComponent(l.phone)}&instance=${encodeURIComponent(l.instance_name)}'">
                            <i data-lucide="message-square" width="13"></i>
                        </button>
                        <button class="btn-icon" title="Ver no Kanban" onclick="window.location.href='/wazio/kanban'">
                            <i data-lucide="layout-kanban" width="13"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
            }).join('');

            if (window.lucide) lucide.createIcons();
        } catch (e) {
            body.innerHTML = `<tr class="empty-row"><td colspan="6" style="color:var(--red)">Erro ao carregar. Verifique a conexão.</td></tr>`;
            console.error(e);
        }
    }

    function updatePagination(page, total) {
        const totalPages = Math.ceil(total / PER_PAGE);
        const from = total === 0 ? 0 : (page - 1) * PER_PAGE + 1;
        const to = Math.min(page * PER_PAGE, total);

        document.getElementById('pageInfo').textContent = total > 0
            ? `Mostrando ${from}–${to} de ${total.toLocaleString('pt-BR')} leads`
            : 'Nenhum resultado';
        document.getElementById('leadsCount').textContent = total > 0 ? `${total.toLocaleString('pt-BR')} leads` : '';
        document.getElementById('btnPrev').disabled = page <= 1;
        document.getElementById('btnNext').disabled = page >= totalPages;
    }

    function exportar() {
        const tag = document.getElementById('filterTag').value;
        const inst = document.getElementById('filterInst').value;
        const q = document.getElementById('searchQ').value.trim();
        let url = `${API}&action=get_leads&limit=5000&offset=0&mode=${currentMode}` +
            (tag ? `&tag=${encodeURIComponent(tag)}` : '') +
            (inst ? `&instance=${encodeURIComponent(inst)}` : '') +
            (q ? `&q=${encodeURIComponent(q)}` : '');

        fetch(url).then(r => r.json()).then(data => {
            if (!data.ok || !data.data?.length) return alert('Nenhum dado para exportar.');
            const rows = [['Nome', 'Telefone', 'Etiquetas', 'Instância', 'Criado', 'Atualizado']];
            data.data.forEach(l => rows.push([l.name || '', l.phone || '', (l.tags || []).join(';'), l.instance_name || '', l.created_at || '', l.updated_at || '']));
            const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `leads_${new Date().toISOString().slice(0, 10)}.csv`;
            a.click();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setMode('atendimento'); // Initialize mode and load leads
    });
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>