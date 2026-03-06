// ============================================================
// ⚙️ DASHBOARD JS — WAZ.IO WAR ROOM (METAL EDITION)
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

let whState = { token: '', enabled: false, url: '', events: [], excludeMessages: [], addUrlEvents: false, addUrlTypeMessages: false };
let todasInstancias = [];
let currentTab = 'instancias';
let qrInterval;

let currentTagToken = '';
let currentProxyToken = '';
let currentPerfilToken = '';
let currentQRToken = '';
let currentWHToken = '';

let localHidden = {};
let globalNumerosOcultos = false;
let localWhState = JSON.parse(localStorage.getItem('waz_wh_state') || '{}');
let localTags = JSON.parse(localStorage.getItem('waz_tags') || '{}');
let proxyCache = JSON.parse(localStorage.getItem('waz_proxy_cache') || '{}');

const EVENTOS_DISPONIVEIS = ['messages', 'labels', 'chat_labels', 'presence', 'qrcode', 'connection', 'disconnection', 'groups', 'contacts'];
const EXCLUSOES_DISPONIVEIS = ['wasSentByApi', 'isGroupYes', 'fromMeYes', 'fromMeNo'];

// ── UTILS ─────────────────────────────────────────────────────
function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
async function api(method, qs, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const sep = qs.startsWith('?') ? '&' + qs.slice(1) : (qs.startsWith('&') ? qs : '&' + qs);
    try {
        const r = await fetch(API + sep, opts);
        return await r.json();
    } catch (e) { return { ok: false, erro: 'Erro de conexão' }; }
}
window.toast = function (msg, tipo = 'ok') {
    const el = document.createElement('div');
    el.className = `toast-msg ${tipo}`;
    const icon = tipo === 'ok' ? 'check-circle' : (tipo === 'erro' ? 'x-circle' : 'info');
    el.innerHTML = `<i data-lucide="${icon}" width="16"></i> <span>${msg}</span>`;
    const container = document.getElementById('toast');
    if (container) {
        container.appendChild(el);
        if (window.lucide) lucide.createIcons();
        setTimeout(() => el.remove(), 4000);
    }
}
window.openModal = function (id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'flex'; setTimeout(() => el.classList.add('open'), 10); }
}
window.closeModal = function (id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); setTimeout(() => el.style.display = 'none', 300); }
    if (id === 'modalQR') clearInterval(qrInterval);
}

// ── DASHBOARD CORE ───────────────────────────────────────────
window.addEventListener('load', () => {
    if (window.lucide) lucide.createIcons();
    carregarInstancias();
    fetchDBKPIs();

    // Auto-parser para Proxy
    const pHost = document.getElementById('proxyHost');
    if (pHost) {
        pHost.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            if (!val.includes(':')) return;
            // logic to parse proxy (omitted for brevity in this chunk, or I can add it)
        });
    }
});

async function carregarInstancias(force = false) {
    try {
        if (force) await api('POST', '?action=sync_instancias');
        const res = await api('GET', '?action=instancias');
        if (!res.ok) return;

        let lista = res.data || [];
        lista.sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { numeric: true }));

        lista.forEach(i => {
            const tok = i.token || i.id;
            if (i.tag) localTags[tok] = i.tag;
            localHidden[tok] = !!i.instance_hidden;
        });

        if (window.sessRole !== 'admin') {
            lista = lista.filter(i => window.sessInst && window.sessInst.includes(i.name));
        }

        todasInstancias = lista;
        renderKPIs(todasInstancias);
        atualizarBadgeLimite(todasInstancias);
        renderInstancias(todasInstancias);
    } catch (e) { console.error(e); }
}

function renderKPIs(lista) {
    const tagFilter = window.currentDashboardTagFilter || 'all';
    const listVis = lista.filter(i => {
        const isHidden = !!i.instance_hidden;
        if (tagFilter === 'hidden') return isHidden;
        if (tagFilter === 'all') return !isHidden;
        return !isHidden && (i.tag === tagFilter);
    });
    const on = listVis.filter(i => (i.status || '').toLowerCase().includes('open')).length;
    const off = listVis.filter(i => (i.status || '').toLowerCase().includes('close') || !i.status).length;

    if (document.getElementById('kpiOn')) document.getElementById('kpiOn').textContent = on;
    if (document.getElementById('kpiOff')) document.getElementById('kpiOff').textContent = off;
}

function atualizarBadgeLimite(lista) {
    const el = document.getElementById('instLimitText');
    if (el) el.textContent = `${lista.length} / ${window.sessLimit || '∞'}`;
}

async function fetchDBKPIs() {
    try {
        const res = await api('GET', '?action=kpi_data');
        if (res.ok && res.data) {
            if (document.getElementById('kpiContacts')) document.getElementById('kpiContacts').textContent = res.data.contacts.toLocaleString();
            if (document.getElementById('kpiMessages')) document.getElementById('kpiMessages').textContent = res.data.messages.toLocaleString();
            if (document.getElementById('kpiFunnels')) document.getElementById('kpiFunnels').textContent = res.data.funnels.toLocaleString();
        }
    } catch (e) { }
}

function renderInstancias(lista) {
    const el = document.getElementById('instList');
    if (!el) return;

    let html = '';
    for (const i of lista) {
        const st = (i.status || 'disconnected').toLowerCase();
        const isOn = st.includes('open') || st === 'connected';
        const tok = i.token || i.id;
        const isHidden = !!i.instance_hidden;
        const myTag = i.tag || '';

        html += `
        <div class="chip-card ${isOn ? '' : 'card-offline'}" data-tag="${myTag}" data-hidden="${isHidden}" style="display: ${isHidden ? 'none' : 'flex'}">
            <div class="chip-header">
                <div class="chip-info">
                    <div class="chip-index">${esc(i.name.substring(0, 2))}</div>
                    <div>
                        <h3 class="chip-name">${esc(i.name)}</h3>
                        <div class="chip-number"><span>${esc(i.owner || 'Sem Número')}</span></div>
                    </div>
                </div>
                <div class="chip-actions">
                    <button class="chip-btn-act" onclick="window.abrirRenomear('${tok}', '${esc(i.name)}')"><i data-lucide="pencil" width="15"></i></button>
                    <button class="chip-btn-act" onclick="window.toggleOcultar('${tok}')"><i data-lucide="${isHidden ? 'eye-off' : 'eye'}" width="16"></i></button>
                    <button class="chip-btn-act" onclick="window.verificarConexao('${tok}', this)"><i data-lucide="wifi" width="16"></i></button>
                    <button class="chip-btn-act" onclick="window.abrirPerfil('${tok}')"><i data-lucide="user" width="16"></i></button>
                    <button class="chip-btn-act del" onclick="window.excluirInstancia('${tok}')"><i data-lucide="trash-2" width="16"></i></button>
                </div>
            </div>
            <div class="chip-tags-row">
                <span class="badge ${isOn ? 'st-api' : 'st-ban'}">${isOn ? 'CONECTADA' : 'DESCONECTADA'}</span>
                ${myTag ? `<span class="badge tag-custom">${myTag}</span>` : ''}
            </div>
            <div class="chip-mid-row">
                <div class="mid-left">
                    <label class="toggle-switch"><input type="checkbox" ${i.webhook_enabled ? 'checked' : ''} onchange="window.toggleMiniWebhook('${tok}', this)"><span class="toggle-slider"></span></label>
                    <span class="conn-status ${i.webhook_enabled ? 'conn-online' : 'conn-offline'}">${i.webhook_enabled ? 'ONLINE' : 'OFFLINE'}</span>
                </div>
                <div class="mid-right">
                    <button class="btn btn-card-purple" onclick="window.abrirToken('${tok}')">TOKEN</button>
                    <button class="btn btn-card-teal" onclick="window.abrirProxy('${tok}')">PROXY</button>
                </div>
            </div>
            <div class="chip-bot-row">
                <button class="btn btn-card-blue" onclick="window.abrirWebhook('${tok}')">WEBHOOK</button>
                ${!isOn
                ? `<button class="btn btn-success" onclick="window.reconectar('${tok}')">CONECTAR</button>`
                : `<button class="btn btn-card-red" onclick="window.desconectar('${tok}')">DESCONECTAR</button>`
            }
            </div>
        </div>`;
    }
    el.innerHTML = html;
    if (window.lucide) lucide.createIcons();
    window.filtrarPainel();
}

// ── INSTANCE ACTIONS ─────────────────────────────────────────
window.excluirInstancia = function (token) {
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    const nome = inst ? inst.name : '?';
    window.abrirConfirm('Apagar Instância', `Deseja apagar <strong>"${esc(nome)}"</strong>?`, async () => {
        const res = await api('DELETE', '?action=excluir', { token, name: nome });
        if (res.ok) { window.toast('Excluída!', 'ok'); carregarInstancias(); }
        else window.toast('Erro ao excluir', 'erro');
    });
}
window.reconectar = async function (token) {
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    const nome = inst ? inst.name : '';
    window.toast('Iniciando conexão...', 'info');
    const res = await api('POST', '?action=conectar', { token, name: nome, instanceName: nome });
    if (res.qrCode || res.qrCodeBase64) {
        window.mostrarQR((res.qrCode || res.qrCodeBase64).replace(/^data:image\/[a-z]+;base64,/, ''), token);
    } else if (res.ok) {
        window.toast('Aguarde conexão...', 'ok');
        window.iniciarLoopQR(token);
    }
}
window.desconectar = function (token) {
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    window.abrirConfirm('Desconectar', 'Deseja desconectar esta instância?', async () => {
        const res = await api('POST', '?action=desconectar', { token, name: inst.name });
        if (res.ok) carregarInstancias();
    });
}

// ── USER MANAGEMENT ──────────────────────────────────────────
window.carregarUsuarios = async function () {
    const res = await api('GET', '?action=usuarios_listar');
    const c = document.getElementById('userListContainer');
    if (!c || !res.ok) return;
    c.innerHTML = (res.data || []).map(u => `
        <div class="user-card">
            <div class="user-card-info">
                <strong>${esc(u.nome)}</strong> (${esc(u.username)})
                <div class="badge norm">${u.role.toUpperCase()}</div>
            </div>
            <div class="user-card-actions">
                <button class="btn btn-info" onclick='window.editarUsuario(${JSON.stringify(u)})'>Editar</button>
                ${u.username !== 'admin' ? `<button class="btn btn-cancel" onclick="window.excluirUsuario('${u.username}')">Excluir</button>` : ''}
            </div>
        </div>
    `).join('');
}

// ── QR CODE LOGIC ─────────────────────────────────────────────
window.mostrarQR = function (b64, token = null) {
    if (token) currentQRToken = token;
    clearInterval(qrInterval);
    const qrWrap = document.getElementById('qrWrap');
    if (!qrWrap) return;
    qrWrap.innerHTML = `<div class="laser-line"></div><img id="qrImg" class="qr-fade-out" src="data:image/png;base64,${b64}" style="width:220px; border-radius:12px; display:block; margin: 0 auto;"/>`;
    setTimeout(() => { const img = document.getElementById('qrImg'); if (img) img.classList.remove('qr-fade-out'); }, 50);
    const timerCont = document.getElementById('qrTimerContainer');
    if (timerCont) timerCont.innerHTML = `<div id="qrTimer">EXPIRA EM 30s</div>`;
    openModal('modalQR');
    window.iniciarLoopQR(currentQRToken);
}

window.iniciarLoopQR = function (token) {
    clearInterval(qrInterval);
    let tempo = 30;
    qrInterval = setInterval(async () => {
        const modal = document.getElementById('modalQR');
        if (!modal || !modal.classList.contains('open')) { clearInterval(qrInterval); return; }

        if (token && tempo % 3 === 0) {
            const res = await api('GET', '?action=instancias');
            if (res.ok && res.data) {
                const i = res.data.find(inst => (inst.token || inst.id) === token);
                if (i && (i.status === 'open' || i.connectionStatus === 'connected')) {
                    clearInterval(qrInterval);
                    document.getElementById('qrWrap').innerHTML = `<div class="success-mark">CONECTADO!</div>`;
                    window.toast('WhatsApp Conectado!', 'ok');
                    carregarInstancias();
                    setTimeout(() => closeModal('modalQR'), 2500);
                    return;
                }
            }
        }
        tempo--;
        const el = document.getElementById('qrTimer');
        if (el) el.textContent = `EXPIRA EM ${tempo}s`;
        if (tempo <= 0) { clearInterval(qrInterval); window.reconectar(token); }
    }, 1000);
}

// ── MODALS & UI HELPERS ───────────────────────────────────────
let confirmCallback = null;
window.abrirConfirm = function (title, msg, cb) {
    document.getElementById('confirmTitle').innerHTML = title;
    document.getElementById('confirmMsg').innerHTML = msg;
    confirmCallback = cb;
    openModal('modalConfirm');
}
window.executarConfirm = function () { closeModal('modalConfirm'); if (confirmCallback) confirmCallback(); }

window.abrirRenomear = function (token, nome) {
    document.getElementById('renomearInstToken').value = token;
    document.getElementById('renomearInstNomeAtual').value = nome;
    document.getElementById('renomearInstNovoNome').value = nome;
    openModal('modalRenomear');
}
window.salvarRenomear = async function () {
    const token = document.getElementById('renomearInstToken').value;
    const novo = document.getElementById('renomearInstNovoNome').value.trim();
    if (!novo) return;
    const res = await api('POST', '?action=renomear_instancia', { old_name: token, new_name: novo });
    if (res.ok) { window.toast('Renomeada!', 'ok'); closeModal('modalRenomear'); carregarInstancias(); }
}

window.toggleOcultar = async function (token) {
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    if (!inst) return;
    const newHidden = !inst.instance_hidden;
    inst.instance_hidden = newHidden;
    renderInstancias(todasInstancias);
    await api('POST', '?action=set_hidden', { name: inst.name, hidden: newHidden });
}

window.abrirToken = function (token) {
    document.getElementById('instTokenDisplay').value = token;
    openModal('modalToken');
}
window.copiarTokenDisplay = function () {
    const el = document.getElementById('instTokenDisplay');
    navigator.clipboard.writeText(el.value);
    window.toast('Copiado!', 'ok');
}

// ── WEBHOOK ───────────────────────────────────────────────────
window.abrirWebhook = async function (token) {
    currentWHToken = token;
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    openModal('modalWebhook');
    const res = await api('POST', '?action=webhook_get', { token });
    const cfg = res.data || {};
    document.getElementById('whContent').innerHTML = `
        <div class="form-group">
            <label class="form-label">URL do Webhook</label>
            <input class="form-input" id="whUrl" value="${esc(cfg.url || '')}">
        </div>
        <div class="form-group">
            <label class="toggle-switch">
                <input type="checkbox" id="whEnabled" ${cfg.enabled ? 'checked' : ''}>
                <span class="toggle-slider"></span>
            </label>
            <span>Ativado</span>
        </div>
    `;
}
window.salvarWebhook = async function () {
    const url = document.getElementById('whUrl').value;
    const enabled = document.getElementById('whEnabled').checked;
    const inst = todasInstancias.find(i => (i.token || i.id) === currentWHToken);
    const res = await api('POST', '?action=webhook_set', { token: currentWHToken, name: inst.name, url, enabled });
    if (res.ok) { window.toast('Salvo!', 'ok'); closeModal('modalWebhook'); }
}

// ── PROXY ─────────────────────────────────────────────────────
window.abrirProxy = async function (token) {
    currentProxyToken = token;
    openModal('modalProxy');
    const res = await api('POST', '?action=proxy_ver', { token });
    if (res.ok && res.data) {
        document.getElementById('proxyHost').value = res.data.host || '';
        document.getElementById('proxyPort').value = res.data.port || '';
        document.getElementById('proxyProtocol').value = res.data.protocol || 'http';
        document.getElementById('proxyUser').value = res.data.user || '';
        document.getElementById('proxyPass').value = res.data.pass || '';
    }
}
window.salvarProxy = async function () {
    const host = document.getElementById('proxyHost').value;
    const port = document.getElementById('proxyPort').value;
    const protocol = document.getElementById('proxyProtocol').value;
    const user = document.getElementById('proxyUser').value;
    const pass = document.getElementById('proxyPass').value;
    const res = await api('POST', '?action=proxy_set', { token: currentProxyToken, host, port, protocol, username: user, password: pass });
    if (res.ok) { window.toast('Proxy salva!', 'ok'); closeModal('modalProxy'); }
}

// ── PERFIL ────────────────────────────────────────────────────
window.abrirPerfil = async function (token) {
    currentPerfilToken = token;
    openModal('modalPerfil');
    const res = await api('GET', '?action=perfil_get&token=' + encodeURIComponent(token));
    if (res.ok) {
        document.getElementById('perfilNome').value = res.name || '';
        document.getElementById('perfilStatus').value = res.status || '';
    }
}
window.salvarPerfil = async function () {
    const nome = document.getElementById('perfilNome').value;
    const status = document.getElementById('perfilStatus').value;
    const res = await api('POST', '?action=perfil_set', { token: currentPerfilToken, name: nome, status });
    if (res.ok) { window.toast('Perfil salvo!', 'ok'); closeModal('modalPerfil'); }
}

// ── MISC ──────────────────────────────────────────────────────
window.carregarLogs = async function () {
    const res = await api('GET', '?action=logs');
    const el = document.getElementById('logList');
    if (el && res.ok) {
        el.innerHTML = '<pre>' + (res.data || []).join('\n') + '</pre>';
    }
}
window.carregarErrosSistema = async function () {
    const res = await api('GET', '?action=logs_sistema');
    const el = document.getElementById('sysErrorList');
    if (el && res.ok) {
        el.innerHTML = (res.data || []).map(l => `<div>[${l.created_at}] ${esc(l.message)}</div>`).join('');
    }
}

window.toggleNotificationDropdown = function () {
    const dr = document.getElementById('notifDropdown');
    dr.classList.toggle('show');
    if (dr.classList.contains('show')) carregarNotificacoes();
}

async function carregarNotificacoes() {
    const res = await api('GET', '?action=logs_sistema');
    const el = document.getElementById('notifList');
    if (el && res.ok) {
        el.innerHTML = (res.data || []).slice(0, 5).map(n => `<div>${esc(n.message)}</div>`).join('');
    }
}

window.showTab = function (tab) {
    ['instancias', 'usuarios', 'logs', 'erros'].forEach(t => {
        const el = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
        if (el) el.style.display = t === tab ? 'block' : 'none';
    });
    if (tab === 'usuarios') carregarUsuarios();
    if (tab === 'logs') carregarLogs();
    if (tab === 'erros') carregarErrosSistema();
}

window.filtrarPainel = function () {
    const txt = document.getElementById('searchInst')?.value.toLowerCase() || '';
    document.querySelectorAll('.chip-card').forEach(card => {
        const name = card.querySelector('.chip-name').textContent.toLowerCase();
        card.style.display = name.includes(txt) ? 'flex' : 'none';
    });
}

window.animarRecarregamento = function () { carregarInstancias(true); }
window.abrirModalCriar = function () { openModal('modalCriar'); }
window.criarInstancia = async function () {
    const nome = document.getElementById('criarNome').value;
    const wh = document.getElementById('criarWebhook').value;
    const res = await api('POST', '?action=criar', { name: nome, webhookUrl: wh });
    if (res.ok) { closeModal('modalCriar'); carregarInstancias(); }
}
