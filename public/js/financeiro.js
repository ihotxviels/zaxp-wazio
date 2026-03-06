// ============================================================
// 💰 FINANCEIRO JS — WAZ.IO WAR ROOM (REDESIGNED)
// ============================================================
const API = '/wazio/index.php?route=api';

// ── INTERCEPTOR GLOBAL: redireciona para login em 401 ─────────
(function () {
    const _fetch = window.fetch;
    window.fetch = async function (...args) {
        const res = await _fetch(...args);
        if (res.status === 401) {
            const o = document.createElement('div');
            o.style.cssText = 'position:fixed;inset:0;background:#080c09;display:flex;align-items:center;justify-content:center;z-index:99999;font-family:"Inter",sans-serif;color:#bcfd49;font-size:16px;font-weight:700;flex-direction:column;gap:12px;';
            o.innerHTML = '<i style="font-size:32px;">⏱</i><span>Sessão expirada — redirecionando...</span>';
            document.body.appendChild(o);
            setTimeout(() => { window.location.href = '/wazio/'; }, 800);
        }
        return res;
    };
})();

let transacoesGlobal = [];
let finChart = null;

// ── LOGOUT ───────────────────────────────────────────────────
window.logout = function () { window.location.href = '/wazio/index.php?action=logout'; };

// ── MOBILE HAMBURGER DRAWER ──────────────────────────────────
window.toggleMobileMenu = function (forceClose = false) {
    const dropdown = document.getElementById('mobDropdown');
    const overlay = document.getElementById('mainOverlay');
    if (forceClose) {
        if (dropdown) dropdown.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        return;
    }
    if (dropdown) dropdown.classList.toggle('open');
    if (overlay) overlay.classList.toggle('show');
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

// ── INIT ─────────────────────────────────────────────────────
window.addEventListener('load', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    const loader = document.getElementById('fullLoader');
    if (loader) {
        setTimeout(() => { loader.style.opacity = '0'; loader.style.transition = 'opacity 0.5s ease'; setTimeout(() => loader.style.display = 'none', 500); }, 300);
    }

    // Mês atual no seletor
    const mesAtual = new Date().toISOString().slice(5, 7);
    const sel = document.getElementById('filterMes');
    if (sel) sel.value = mesAtual;

    // Inicializa moeda e categorias
    if (typeof window.selecionarTipo === 'function') window.selecionarTipo('receita');

    // Carrega dados
    carregarFinanceiro();
});

// ── UTIL ─────────────────────────────────────────────────────
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
}
function parseMoeda(str) {
    if (!str) return 0;
    return parseFloat(String(str).replace(/\./g, '').replace(',', '.')) || 0;
}
function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function dataBr(d) { if (!d) return '--'; const [a, m, dia] = d.split('-'); return `${dia}/${m}/${a}`; }

async function api(method, qs, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const sep = qs.startsWith('?') ? '&' + qs.slice(1) : (qs.startsWith('&') ? qs : '&' + qs);
    try { return await (await fetch(API + sep, opts)).json(); } catch (e) { return { ok: false, erro: 'Erro de conexão' }; }
}

window.toast = function (msg, tipo = 'ok') {
    const el = document.createElement('div');
    el.className = `toast-msg ${tipo}`;
    const icon = tipo === 'ok' ? 'check-circle' : (tipo === 'erro' ? 'x-circle' : 'info');
    el.innerHTML = `<i data-lucide="${icon}" width="16"></i> <span>${msg}</span>`;
    const tc = document.getElementById('toast');
    if (tc) { tc.appendChild(el); if (typeof lucide !== 'undefined') lucide.createIcons(); setTimeout(() => el.remove(), 4000); }
};

window.openModal = function (id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'flex'; setTimeout(() => el.classList.add('open'), 10); }
};
window.closeModal = function (id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); setTimeout(() => el.style.display = 'none', 300); }
};

// ── CARREGAR DADOS ──────────────────────────────────────────
window.carregarFinanceiro = async function () {
    const res = await api('GET', '?action=fin_listar');
    if (!res.ok) return toast('Erro ao buscar financeiro', 'erro');
    // Garante que é array de objetos, removendo lixo
    transacoesGlobal = Array.isArray(res.data) ? res.data : Object.values(res.data || {});
    renderTabelasEKpis();
    renderChart();
};

// ── HELPER DE PERÍODO ──
function matchPeriodo(dataIso, per) {
    if (!dataIso || typeof dataIso !== 'string') return false;
    if (per === 'ws' || per === 'todos') return true;

    // Remove time se houver e garante ISO YYYY-MM-DD
    const dOnly = dataIso.split(' ')[0].split('T')[0];
    const tDate = new Date(dOnly + 'T12:00:00Z');
    if (isNaN(tDate.getTime())) return false;
    const tMs = tDate.getTime();

    const now = new Date();
    // Ajusta base pra hoje 00:00 local
    const baseToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
    const dayMs = 86400000;

    if (per === 'hoje') return tMs >= baseToday;
    if (per === 'ontem') return tMs >= (baseToday - dayMs) && tMs < baseToday;
    if (per === '7d') return tMs >= (baseToday - 6 * dayMs);

    if (per === 'mes') {
        return tDate.getFullYear() === now.getFullYear() && tDate.getMonth() === now.getMonth();
    }

    if (per === '6m') {
        const past = new Date(now.getFullYear(), now.getMonth() - 5, 1).getTime();
        return tMs >= past;
    }

    if (per === '12m') {
        const past = new Date(now.getFullYear() - 1, now.getMonth() + 1, 1).getTime();
        return tMs >= past;
    }
    return true;
}

// ── RENDER GERAL ─────────────────────────────────────────────
function renderTabelasEKpis() {
    const filtroPer = document.getElementById('filterMes')?.value || 'mes';

    const filtrar = (tipo) => {
        if (!Array.isArray(transacoesGlobal)) return [];
        return transacoesGlobal.filter(t => {
            if (!t) return false;
            if (!matchPeriodo(t.data, filtroPer)) return false;
            if (tipo) return t.tipo === tipo;
            return true;
        });
    };

    renderLista('listaAtualizacoes', filtrar(null));
    renderLista('listaEntradas', filtrar('receita'));
    renderLista('listaSaidas', filtrar('despesa'));
    atualizarKPIs(filtroPer);
}

function atualizarKPIs(filtroPer) {
    let entradas = 0, despesas = 0, trafego = 0, pendentes = 0;
    if (!Array.isArray(transacoesGlobal)) return;

    transacoesGlobal.filter(t => t && matchPeriodo(t.data, filtroPer))
        .forEach(t => {
            const val = parseFloat(t.valor) || 0;
            if (t.status === 'pendente') { if (t.tipo === 'receita') pendentes += val; return; }
            if (t.tipo === 'receita') entradas += val;
            if (t.tipo === 'despesa') {
                despesas += val;
                if ((t.categoria || '').toUpperCase() === 'TRÁFEGO PAGO') trafego += val;
            }
        });
    const lucro = entradas - despesas;
    const setKPI = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = formatarMoeda(val); };
    setKPI('kpiReceitas', entradas);
    setKPI('kpiDespesas', despesas);
    setKPI('kpiTrafego', trafego);
    setKPI('kpiPendente', pendentes);
    const kpiSaldo = document.getElementById('kpiSaldo');
    if (kpiSaldo) {
        kpiSaldo.textContent = formatarMoeda(lucro);
        kpiSaldo.style.color = lucro >= 0 ? 'var(--blue)' : 'var(--red)';
    }
}

function renderLista(containerId, lista) {
    const container = document.getElementById(containerId);
    if (!container) return;
    if (!lista || lista.length === 0) {
        container.innerHTML = `<div class="fin-empty"><i data-lucide="inbox" width="32" style="color:var(--border);"></i> Nenhum lançamento neste período.</div>`;
        if (window.lucide) lucide.createIcons();
        return;
    }
    const sorted = [...lista].sort((a, b) => new Date(b.data) - new Date(a.data));
    container.innerHTML = sorted.map((t, idx) => {
        const isReceita = t.tipo === 'receita';
        const isPend = t.status === 'pendente';
        const iconClass = isPend ? 'pend' : (isReceita ? 'in' : 'out');
        const valClass = isPend ? 'pend' : (isReceita ? 'in' : 'out');
        const icon = isReceita ? 'trending-up' : 'trending-down';
        const sinal = isReceita ? '+' : '-';
        const delay = idx * 0.04;
        const cat = t.categoria ? `<span class="fin-cat-badge">${esc(t.categoria)}</span>` : '';
        return `
        <div class="fin-row" style="animation-delay:${delay}s;">
            <div class="fin-icon ${iconClass}"><i data-lucide="${icon}" width="16"></i></div>
            <div class="fin-desc">
                <div class="fin-desc-title">${esc(t.descricao)}</div>
                <div class="fin-desc-meta">
                    <span>${dataBr(t.data)}</span>
                    <span class="fin-status-badge ${t.status}">${t.status === 'pago' ? '✓ PAGO' : '⏳ PENDENTE'}</span>
                    ${cat}
                    <span style="color:${isReceita ? 'var(--green)' : 'var(--red)'}; font-weight:800;">${isReceita ? 'ENTRADA' : 'SAÍDA'}</span>
                </div>
            </div>
            <div class="fin-value ${valClass}">${sinal} ${formatarMoeda(t.valor)}</div>
            <div class="fin-actions">
                <button class="fin-btn-act" onclick="editarTransacao('${t.id}')" title="Editar"><i data-lucide="edit-2" width="14"></i></button>
                <button class="fin-btn-act del" onclick="excluirTransacao('${t.id}')" title="Excluir"><i data-lucide="trash-2" width="14"></i></button>
            </div>
        </div>`;
    }).join('');
    if (window.lucide) lucide.createIcons();
}

// ── CHART ────────────────────────────────────────────────────
function renderChart() {
    const canvas = document.getElementById('finChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const filtroPer = document.getElementById('filterMes')?.value || 'mes';
    const now = new Date();
    const currY = now.getFullYear();
    const currM = now.getMonth();

    let chartLabels = [];
    let dataMapArray = [];

    // Lógica para 'Hoje', 'Ontem', '7d', 'mes'
    if (['hoje', 'ontem', '7d', 'mes'].includes(filtroPer)) {
        if (filtroPer === 'hoje') {
            const dStr = `${currY}-${String(currM + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            chartLabels.push('Hoje'); dataMapArray.push([dStr]);
        } else if (filtroPer === 'ontem') {
            const ontem = new Date(now.getTime() - 86400000);
            const dStr = `${ontem.getFullYear()}-${String(ontem.getMonth() + 1).padStart(2, '0')}-${String(ontem.getDate()).padStart(2, '0')}`;
            chartLabels.push('Ontem'); dataMapArray.push([dStr]);
        } else if (filtroPer === '7d') {
            for (let i = 6; i >= 0; i--) {
                const d = new Date(now.getTime() - (i * 86400000));
                const dStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                chartLabels.push(`${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`);
                dataMapArray.push([dStr]);
            }
        } else if (filtroPer === 'mes') {
            const diasNoMes = new Date(currY, currM + 1, 0).getDate();
            for (let i = 1; i <= diasNoMes; i++) {
                chartLabels.push(String(i).padStart(2, '0'));
                const dStr = `${currY}-${String(currM + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                dataMapArray.push([dStr]);
            }
        }
    } else {
        // Lógica para Meses ('6m', '12m', 'ws', 'todos')
        let numMeses = (filtroPer === '12m' || filtroPer === 'ws' || filtroPer === 'todos') ? 12 : 6;
        const nomesMes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        for (let i = numMeses - 1; i >= 0; i--) {
            const mData = new Date(currY, currM - i, 1);
            const y = mData.getFullYear();
            const mNum = mData.getMonth() + 1;
            chartLabels.push(`${nomesMes[mData.getMonth()]}/${String(y).substring(2)}`);
            const prefix = `${y}-${String(mNum).padStart(2, '0')}`;
            dataMapArray.push([prefix]); // busca por YYYY-MM
        }
    }

    // Calcula somatórias seguras
    const sum = (arr) => arr.reduce((acc, val) => acc + (parseFloat(val) || 0), 0);
    if (!Array.isArray(transacoesGlobal)) transacoesGlobal = [];

    const entradas = dataMapArray.map(prefixos => sum(
        transacoesGlobal.filter(t => t.tipo === 'receita' && t.status !== 'pendente' && prefixos.some(px => t.data && t.data.startsWith(px))).map(t => t.valor)
    ));
    const trafego = dataMapArray.map(prefixos => sum(
        transacoesGlobal.filter(t => t.tipo === 'despesa' && (t.categoria || '').toUpperCase() === 'TRÁFEGO PAGO' && prefixos.some(px => t.data && t.data.startsWith(px))).map(t => t.valor)
    ));
    const saidas = dataMapArray.map(prefixos => sum(
        transacoesGlobal.filter(t => t.tipo === 'despesa' && t.status !== 'pendente' && prefixos.some(px => t.data && t.data.startsWith(px))).map(t => t.valor)
    ));
    const lucro = entradas.map((e, i) => e - saidas[i]);

    if (finChart) { finChart.destroy(); }

    Chart.defaults.color = '#cbd5e1';
    Chart.defaults.font.family = 'Inter, sans-serif';

    finChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [
                { label: 'Entradas', data: entradas, backgroundColor: 'rgba(188,253,73,0.2)', borderColor: '#bcfd49', borderWidth: 2, borderRadius: 2 },
                { label: 'Tráfego Pago', data: trafego, backgroundColor: 'rgba(56,189,248,0.2)', borderColor: '#38bdf8', borderWidth: 2, borderRadius: 2 },
                { label: 'Saídas', data: saidas, backgroundColor: 'rgba(248,113,113,0.2)', borderColor: '#f87171', borderWidth: 2, borderRadius: 2 },
                { label: 'Lucro Líquido', data: lucro, type: 'line', borderColor: '#bcfd49', backgroundColor: 'rgba(188,253,73,0.05)', borderWidth: 2, tension: 0.4, pointBackgroundColor: '#bcfd49', pointRadius: 3, fill: true }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { padding: 16, boxWidth: 12 } }, tooltip: { callbacks: { label: ctx => ` ${formatarMoeda(ctx.raw)}` } } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.04)' }, border: { color: 'transparent' } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, border: { color: 'transparent' }, ticks: { callback: v => 'R$' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) } }
            }
        }
    });
}

// ── MODAL TRANSAÇÃO ───────────────────────────────────────────
window.abrirModalTransacao = function (tipoInicial) {
    document.getElementById('modalTransacaoTitle').innerHTML = '<i data-lucide="dollar-sign" width="18"></i> Novo Lançamento';
    document.getElementById('transacaoId').value = '';
    document.getElementById('transacaoDesc').value = '';
    document.getElementById('transacaoValor').value = '';
    document.getElementById('transacaoData').value = new Date().toISOString().split('T')[0];

    // Status reset
    document.querySelectorAll('#pillsStatus .opt-pill').forEach((p, i) => { p.classList.toggle('selected', i === 0); });
    document.getElementById('transacaoStatus').value = 'pago';

    // Tipo
    const tipo = tipoInicial || 'receita';
    if (typeof window.selecionarTipo === 'function') window.selecionarTipo(tipo);
    else document.getElementById('transacaoTipo').value = tipo;

    openModal('modalTransacao');
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.editarTransacao = function (id) {
    const t = transacoesGlobal.find(x => x.id === id);
    if (!t) return;

    document.getElementById('modalTransacaoTitle').innerHTML = '<i data-lucide="edit" width="18"></i> Editar Lançamento';
    document.getElementById('transacaoId').value = t.id;
    document.getElementById('transacaoDesc').value = t.descricao;
    // formata valor para exibição com máscara
    const val = parseFloat(t.valor || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    document.getElementById('transacaoValor').value = val;
    document.getElementById('transacaoData').value = t.data;

    // Status pills
    document.querySelectorAll('#pillsStatus .opt-pill').forEach(p => {
        p.classList.toggle('selected', p.dataset.val === t.status);
    });
    document.getElementById('transacaoStatus').value = t.status || 'pago';

    // Tipo e categoria
    if (typeof window.selecionarTipo === 'function') window.selecionarTipo(t.tipo || 'receita');
    setTimeout(() => {
        const cat = t.categoria || '';
        document.querySelectorAll('#pillsCategoria .opt-pill').forEach(p => {
            const isMatch = p.dataset.val.toUpperCase() === cat.toUpperCase();
            p.classList.toggle('selected', isMatch);
        });
        document.getElementById('transacaoCategoria').value = cat;
    }, 50);

    openModal('modalTransacao');
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.salvarTransacao = async function () {
    const id = document.getElementById('transacaoId').value;
    const desc = document.getElementById('transacaoDesc').value.trim();
    const valorStr = document.getElementById('transacaoValor').value;
    const data = document.getElementById('transacaoData').value;
    const tipo = document.getElementById('transacaoTipo').value;
    const status = document.getElementById('transacaoStatus').value;
    const categoria = document.getElementById('transacaoCategoria').value;

    const valor = parseMoeda(valorStr);
    if (!desc || !valor || !data) return toast('Preencha todos os campos obrigatórios!', 'erro');

    toast('Salvando...', 'info');
    const payload = { id, descricao: desc, valor, data, tipo, status, categoria };
    const res = await api('POST', '?action=fin_salvar', payload);

    if (res.ok) {
        toast('Lançamento salvo!', 'ok');
        closeModal('modalTransacao');
        carregarFinanceiro();
    } else {
        toast(res.erro || 'Erro ao salvar', 'erro');
    }
};

let confirmCallback = null;
window.abrirConfirm = function (title, msg, callback) {
    document.getElementById('confirmTitle').innerHTML = title;
    document.getElementById('confirmMsg').innerHTML = msg;
    confirmCallback = callback;
    openModal('modalConfirm');
};
window.executarConfirm = function () {
    closeModal('modalConfirm');
    if (confirmCallback) confirmCallback();
};

window.excluirTransacao = function (id) {
    abrirConfirm(
        '<i data-lucide="trash-2" width="20"></i> Excluir Lançamento',
        'Tem certeza que deseja apagar este registro financeiro? Essa ação é irreversível.',
        async () => {
            const res = await api('DELETE', '?action=fin_excluir', { id });
            if (res.ok) { toast('Registro apagado!', 'ok'); carregarFinanceiro(); }
            else toast('Erro ao excluir', 'erro');
        }
    );
};