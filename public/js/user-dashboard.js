// ============================================================
// ⚙️ DASHBOARD JS — WAZ.IO WAR ROOM (METAL EDITION)
// ============================================================
const API = '/wazio/index.php?route=api';
let whState = { token: '', enabled: false, url: '', events: [], excludeMessages: [], addUrlEvents: false, addUrlTypeMessages: false };
let todasInstancias = [];
let currentTab = 'instancias';
let qrInterval;

let currentTagToken = '';
let currentProxyToken = '';
let currentPerfilToken = '';
let currentQRToken = '';

// PERSISTÊNCIA NATIVA CENTRADA NO DB
let localHidden = {}; // Populado via API
let globalNumerosOcultos = false;

// CACHE DE STATUS DE PROXY (Para cores do Wi-Fi)
let proxyCache = JSON.parse(localStorage.getItem('waz_proxy_cache') || '{}');

const EVENTOS_DISPONIVEIS = ['messages', 'labels', 'chat_labels', 'presence', 'qrcode', 'connection', 'disconnection', 'groups', 'contacts'];
const EXCLUSOES_DISPONIVEIS = ['wasSentByApi', 'isGroupYes', 'fromMeYes', 'fromMeNo'];

window.addEventListener('load', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Carregar instâncias imediatamente
    if (window.renderizarDashboard) window.renderizarDashboard();

    // BOOTSTRAP CONFIGS GLOBAIS DO DB
    fetch(API + '&action=get_setting&key=global_hide_numbers')
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            if (data && data.ok && typeof data.value === 'boolean') {
                globalNumerosOcultos = data.value;
                const btn = document.getElementById('btnOcultarGlob');
                const btnMob = document.getElementById('btnOcultarGlobMob');
                if (btn) btn.innerHTML = `<i data-lucide="${globalNumerosOcultos ? 'eye' : 'eye-off'}" width="14"></i>`;
                if (btnMob) btnMob.innerHTML = `<i data-lucide="${globalNumerosOcultos ? 'eye' : 'eye-off'}" width="16"></i>`;
                if (window.lucide) lucide.createIcons();
            }
        });

    const loader = document.getElementById('fullLoader');
    if (loader) {
        setTimeout(() => {
            loader.style.opacity = '0';
            loader.style.transition = 'opacity 0.5s ease';
            setTimeout(() => loader.style.display = 'none', 500);
        }, 300);
    }

    setTimeout(() => {
        ['filterTag', 'selectTagDef', 'proxyProtocol', 'userRole'].forEach(id => {
            const el = document.getElementById(id);
            if (el) window.buildSingleFormSelect(el);
        });
    }, 100);

    // PARSER AUTOMÁTICO DA PROXY
    const proxyHostInput = document.getElementById('proxyHost');
    if (proxyHostInput) {
        proxyHostInput.addEventListener('input', function (e) {
            const val = e.target.value.trim();
            if (!val.includes(':')) return;

            let data = { host: '', port: '', user: '', pass: '', protocol: 'socks5' };

            const regexUri = /^(.*):\/\/(.*):(.*)@(.*):(\d+)$/;
            const m = val.match(regexUri);

            if (m) {
                data = { protocol: m[1], user: decodeURIComponent(m[2]), pass: decodeURIComponent(m[3]), host: m[4], port: m[5] };
            } else {
                const parts = val.split(':');
                if (parts.length === 4) {
                    data = { host: parts[0], port: parts[1], user: parts[2], pass: parts[3], protocol: 'socks5' };
                } else if (parts.length === 2) {
                    data = { host: parts[0], port: parts[1], user: '', pass: '', protocol: 'http' };
                }
            }

            if (data.host && data.port) {
                document.getElementById('proxyHost').value = data.host;
                document.getElementById('proxyPort').value = data.port;
                document.getElementById('proxyUser').value = data.user;
                document.getElementById('proxyPass').value = data.pass;

                const protoSelect = document.getElementById('proxyProtocol');
                if (protoSelect) {
                    protoSelect.value = data.protocol;
                    window.buildSingleFormSelect(protoSelect);
                }
                toast('Proxy formatada e pronta para teste!', 'info');
            }
        });
    }

    // CHECKER DE PROXY EM BACKGROUND (5s após carregar, depois a cada 1h)
    setTimeout(window.verificarTodasProxies, 5000);
    setInterval(window.verificarTodasProxies, 3600000);
});

window.togglePassword = function (inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        if (window.lucide) lucide.createIcons();
    }
};

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
}

window.buildSingleFormSelect = function (select) {
    if (!select) return;
    let container = select.parentElement;
    if (!container.classList.contains('custom-select-container')) return;

    const oldTrigger = container.querySelector('.custom-select-trigger');
    const oldOptions = container.querySelector('.custom-select-options');
    if (oldTrigger) oldTrigger.remove();
    if (oldOptions) oldOptions.remove();

    select.style.display = 'none';

    const trigger = document.createElement('div');
    trigger.className = 'custom-select-trigger';

    const textSpan = document.createElement('span');
    textSpan.innerText = select.options[select.selectedIndex]?.text || '';

    const icon = document.createElement('i');
    icon.setAttribute('data-lucide', 'chevron-down');
    icon.setAttribute('width', '16');

    trigger.appendChild(textSpan);
    trigger.appendChild(icon);

    const optionsDiv = document.createElement('div');
    optionsDiv.className = 'custom-select-options';

    Array.from(select.options).forEach((opt) => {
        const optDiv = document.createElement('div');
        optDiv.className = 'custom-select-option' + (select.value === opt.value ? ' active' : '');
        optDiv.innerText = opt.text;
        optDiv.dataset.value = opt.value;

        optDiv.addEventListener('click', (e) => {
            e.stopPropagation();
            select.value = opt.value;
            textSpan.innerText = opt.text;
            optionsDiv.querySelectorAll('.custom-select-option').forEach(el => el.classList.remove('active'));
            optDiv.classList.add('active');
            optionsDiv.classList.remove('show');
            const event = new Event('change', { bubbles: true });
            select.dispatchEvent(event);
        });
        optionsDiv.appendChild(optDiv);
    });

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        document.querySelectorAll('.custom-select-options').forEach(el => {
            if (el !== optionsDiv) el.classList.remove('show');
        });
        optionsDiv.classList.toggle('show');
    });

    container.appendChild(trigger);
    container.appendChild(optionsDiv);
    if (window.lucide) lucide.createIcons();
}

document.addEventListener('click', e => {
    if (!e.target.closest('.custom-select-container')) {
        document.querySelectorAll('.custom-select-options').forEach(d => d.classList.remove('show'));
    }
});

function extrairNumero(i) {
    let num = '';
    if (!i) return num;
    if (i.owner) num = String(i.owner).replace(/[^0-9]/g, '');
    else if (i.ownerJid || i.connectedPhone) num = String(i.ownerJid || i.connectedPhone).split('@')[0].split(':')[0].replace(/[^0-9]/g, '');
    return num;
}

window.formatPhone = function (value) {
    if (!value) return '';
    let v = String(value).replace(/\D/g, '');
    if (v.startsWith('55') && v.length >= 12) { v = v.substring(2); }
    if (v.length === 0) return '';
    let formatted = '';
    if (v.length <= 2) return '(' + v;
    formatted += '(' + v.substring(0, 2) + ') ';
    if (v.length <= 6) return formatted + v.substring(2);
    if (v.length <= 10) return formatted + v.substring(2, 6) + '-' + v.substring(6, 10);
    return formatted + v.substring(2, 7) + '-' + v.substring(7, 11);
}

window.copyPhone = function (btn, numero) {
    const numFormatado = window.formatPhone(numero);
    const copyText = (text) => {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text);
        } else {
            const temp = document.createElement('textarea');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
        }
    };
    copyText(numFormatado);
    toast('Copiado: ' + numFormatado, 'ok');
}

window.showTab = function (tab, el) {
    ['instancias', 'usuarios', 'logs'].forEach(t => {
        const target = document.getElementById('tab' + cap(t));
        if (target) target.style.display = 'none';
    });
    const currentTarget = document.getElementById('tab' + cap(tab));
    if (currentTarget) currentTarget.style.display = 'block';
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    if (el) el.classList.add('active');
    currentTab = tab;

    const titles = {
        instancias: '<i data-lucide="smartphone" width="18" style="color:var(--green)"></i> WAZ.IO <span class="glow-text" style="color:var(--green); text-shadow: 0 0 10px rgba(188,253,73,0.6);">///</span> INSTÂNCIAS',
        usuarios: '<i data-lucide="users" width="18" style="color:var(--green)"></i> WAZ.IO <span class="glow-text" style="color:var(--green); text-shadow: 0 0 10px rgba(188,253,73,0.6);">///</span> USUÁRIOS',
        logs: '<i data-lucide="terminal" width="18" style="color:var(--green)"></i> WAZ.IO <span class="glow-text" style="color:var(--green); text-shadow: 0 0 10px rgba(188,253,73,0.6);">///</span> LOGS'
    };
    const topTitle = document.getElementById('topTitle');
    if (topTitle) {
        topTitle.innerHTML = titles[tab] || '';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (tab === 'usuarios') carregarUsuarios();
    if (tab === 'logs') carregarLogs();
}
const cap = s => s.charAt(0).toUpperCase() + s.slice(1);

window.animarRecarregamento = async function () {
    const loader = document.getElementById('fullLoader');
    if (loader) { loader.style.display = 'flex'; loader.style.opacity = '1'; }
    await recarregar();
    setTimeout(() => { if (loader) { loader.style.opacity = '0'; setTimeout(() => loader.style.display = 'none', 500); } }, 800);
}

window.filtrarPainel = function () {
    const txtInput = document.getElementById('searchInst');
    const tagInput = document.getElementById('filterTag');
    const txt = (txtInput ? txtInput.value : '').toLowerCase();
    const tagFilter = tagInput ? tagInput.value : 'all';

    document.querySelectorAll('.chip-card').forEach(card => {
        const nameEl = card.querySelector('.chip-name');
        const name = nameEl ? nameEl.textContent.toLowerCase() : '';
        const cardTag = card.dataset.tag || '';
        const isHidden = card.dataset.hidden === 'true';
        const matchTxt = name.includes(txt);
        let show = false;

        if (tagFilter === 'hidden') show = isHidden && matchTxt;
        else if (!isHidden) show = matchTxt && ((tagFilter === 'all') || (cardTag === tagFilter));

        card.style.display = show ? 'flex' : 'none';
    });
}

window.toggleGlobalOcultarNumeros = async function () {
    globalNumerosOcultos = !globalNumerosOcultos;
    const btn = document.getElementById('btnOcultarGlob');
    const btnMob = document.getElementById('btnOcultarGlobMob');
    if (btn) { btn.innerHTML = `<i data-lucide="${globalNumerosOcultos ? 'eye' : 'eye-off'}" width="14"></i>`; }
    if (btnMob) { btnMob.innerHTML = `<i data-lucide="${globalNumerosOcultos ? 'eye' : 'eye-off'}" width="16"></i>`; }
    if (window.lucide) lucide.createIcons();
    renderInstancias(todasInstancias);

    try {
        await api('POST', '?action=save_setting', { key: 'global_hide_numbers', value: globalNumerosOcultos });
    } catch (e) {
        console.error("Erro ao salvar config global:", e);
    }
}

window.toggleOcultar = async function (token) {
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    if (!inst) return;

    const isCurrentlyHidden = !!inst.instance_hidden;
    const newHidden = !isCurrentlyHidden;

    inst.instance_hidden = newHidden;
    toast(newHidden ? 'Instância ocultada do painel!' : 'Instância visível novamente!', 'ok');

    renderInstancias(todasInstancias);

    try {
        await api('POST', '?action=toggle_instance_visibility', { instance: inst.name, hidden: newHidden });
    } catch (e) {
        console.error("Erro ao ocultar:", e);
    }
}

window.toggleMiniWebhook = async function (token, checkbox) {
    const enabled = checkbox.checked;
    const labelOnline = checkbox.parentElement.nextElementSibling;
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    if (!inst) return;

    inst.webhook_enabled = enabled;

    if (labelOnline) {
        labelOnline.classList.remove('conn-online', 'conn-offline');
        labelOnline.classList.add(enabled ? 'conn-online' : 'conn-offline');
    }

    toast(enabled ? 'Ativando Webhook...' : 'Desativando Webhook...', 'info');

    try {
        const res = await api('POST', '?action=webhook_get', { instance: inst.name });
        let urlAtual = inst.webhook_url || '';

        if (res && res.data && res.data.url) {
            urlAtual = res.data.url;
        }

        const payload = {
            instance: inst.name,
            enabled: enabled,
            url: urlAtual
        };

        const saveRes = await api('POST', '?action=webhook_set', payload);
        if (!saveRes.ok) throw new Error("Failed");
    } catch (e) {
        toast('Erro ao comunicar', 'erro');
        checkbox.checked = !enabled;
        inst.webhook_enabled = !enabled;
        if (labelOnline) {
            labelOnline.classList.remove('conn-online', 'conn-offline');
            labelOnline.classList.add(!enabled ? 'conn-online' : 'conn-offline');
        }
    }
}

function atualizarBadgeLimite(totalInstanciasArray) {
    const badgeText = document.getElementById('instLimitText');
    if (badgeText) {
        const limiteStr = window.sessRole === 'admin' ? '∞' : window.sessLimit;
        const count = totalInstanciasArray.length;
        badgeText.innerText = `${count} / ${limiteStr}`;

        if (window.sessRole !== 'admin' && count >= window.sessLimit) {
            badgeText.parentElement.style.color = 'var(--red)';
            badgeText.parentElement.style.borderColor = 'rgba(248,113,113,0.3)';
            badgeText.parentElement.style.background = 'linear-gradient(180deg, rgba(248,113,113,0.15) 0%, rgba(248,113,113,0.05) 100%)';
        } else {
            badgeText.parentElement.style.color = 'var(--green)';
            badgeText.parentElement.style.borderColor = 'rgba(188,253,73,0.3)';
            badgeText.parentElement.style.background = 'linear-gradient(180deg, rgba(188,253,73,0.15) 0%, rgba(188,253,73,0.05) 100%)';
        }
    }
}

// CHECKER DE PROXY EM BACKGROUND
window.verificarTodasProxies = async function () {
    let temErro = false;
    let msgErro = [];

    for (let i of todasInstancias) {
        const tok = i.token || i.id;
        try {
            const res = await api('POST', '?action=proxy_ver', { token: tok });
            if (res && res.data && res.data.host && res.data.enabled !== false && res.data.host !== '') {
                if (res.data.host !== 'managed_pool' && !res.data.host.includes('hidden')) {
                    const testRes = await api('POST', '?action=testar_proxy', {
                        host: res.data.host, port: res.data.port,
                        protocol: res.data.protocol || 'http',
                        user: res.data.user || '', pass: res.data.pass || ''
                    });
                    if (testRes.ok) {
                        proxyCache[tok] = 'ok';
                    } else {
                        proxyCache[tok] = 'error';
                        temErro = true;
                        msgErro.push(i.name || 'Desconhecida');
                    }
                } else {
                    proxyCache[tok] = 'ok';
                }
            } else {
                proxyCache[tok] = 'none';
            }
        } catch (e) { }
    }

    localStorage.setItem('waz_proxy_cache', JSON.stringify(proxyCache));
    renderInstancias(todasInstancias);

    if (temErro) {
        abrirConfirm(
            '<i data-lucide="alert-triangle" width="20"></i> ALERTA: PROXY EXPIRADA!',
            `<strong style="color:var(--red);">Atenção:</strong> Sua proxy local falhou ou foi recusada nas instâncias: <br><br><b>${msgErro.join(', ')}</b><br><br>Renove ou verifique a conexão do Storage Local agora mesmo!`,
            () => { }
        );

        // 🚀 GATILHO SILENCIOSO PUSH ENGINE 
        try {
            api('POST', '?action=push_proxy_alert', { nome: msgErro.join(', ') });
        } catch (e) { }
    }
}

async function carregarInstancias() {
    const res = await api('GET', '?action=instancias');
    if (!res.ok) return toast('Erro ao carregar', 'erro');
    let lista = res.data || [];

    lista.sort((a, b) => (a.name || '').localeCompare((b.name || ''), undefined, { numeric: true, sensitivity: 'base' }));

    if (window.sessRole === 'admin') {
        let hiddenChanged = false;
        lista.forEach(i => {
            const tok = i.token || i.id;
            if (!window.sessInst.includes(i.name)) {
                if (localHidden[tok] === undefined) {
                    localHidden[tok] = true;
                    hiddenChanged = true;
                }
            }
        });
        if (hiddenChanged) {
            localStorage.setItem(userHiddenKey, JSON.stringify(localHidden));
            api('POST', '?action=set_hidden', { hidden: localHidden }).catch(() => { });
        }
    }

    if (window.sessRole !== 'admin') {
        lista = lista.filter(i => window.sessInst.includes(i.name));
    }

    todasInstancias = lista;
    renderKPIs(todasInstancias);
    atualizarBadgeLimite(todasInstancias);
    renderInstancias(todasInstancias);
}

function renderKPIs(lista) {
    const on = lista.filter(i => normSt(i) === 'connected').length;
    const off = lista.filter(i => normSt(i) === 'disconnected').length;
    const conn = lista.filter(i => normSt(i) === 'connecting').length;
    if (document.getElementById('kpiOn')) document.getElementById('kpiOn').textContent = on;
    if (document.getElementById('kpiOff')) document.getElementById('kpiOff').textContent = off;
    if (document.getElementById('kpiConn')) document.getElementById('kpiConn').textContent = conn;
    if (document.getElementById('kpiTotal')) document.getElementById('kpiTotal').textContent = lista.length;
}

function normSt(i) {
    const s = (i.connectionStatus || i.status || '').toLowerCase();
    return s === 'open' ? 'connected' : s;
}

function renderInstancias(lista) {
    const el = document.getElementById('instList');
    if (!el) return;

    if (!lista.length) {
        el.innerHTML = '<div class="empty"><div class="spin"><i data-lucide="loader-2" width="32"></i></div> Carregando...</div>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }

    let statusAnterior = JSON.parse(localStorage.getItem('waz_status_cache') || '{}');

    el.innerHTML = lista.map(i => {
        const st = normSt(i);
        const isOn = st === 'connected';

        const tok = i.token || i.id || '';
        const whEnabled = localWhState[tok] !== false;

        const stLabel = isOn ? 'CONECTADA' : st === 'disconnected' ? 'DESCONECTADA' : 'CONECTANDO';
        const stCls = (isOn && whEnabled) ? '' : 'card-offline';
        const badgeCls = isOn ? 'st-api' : st === 'disconnected' ? 'st-ban' : 'st-aq';

        let num = extrairNumero(i) || '';
        let numFormatado = num ? window.formatPhone(num) : 'Sem Número';
        let blurClass = globalNumerosOcultos ? 'blur-text' : '';

        const myTag = localTags[tok] || '';
        const nomeLimpo = esc(i.displayName || i.name || '?');

        const isHidden = localHidden[tok] === true ? 'true' : 'false';

        let idMatch = nomeLimpo.match(/\d{2}/);
        let idNum = idMatch ? idMatch[0] : (num ? num.substring(0, 2) : '00');
        if (idNum.length < 2) idNum = '00';

        let successClass = '';
        if (statusAnterior[tok] !== 'connected' && st === 'connected') {
            successClass = 'connected-success';
            closeModal('modalQR');
        }
        statusAnterior[tok] = st;

        // LÓGICA DE CORES DO WI-FI DA PROXY LOCAL
        let wifiStyle = '';
        if (proxyCache[tok] === 'error') wifiStyle = 'color: var(--red); text-shadow: 0 0 10px rgba(248,113,113,0.6);';
        else if (proxyCache[tok] === 'ok') wifiStyle = 'color: var(--green); text-shadow: 0 0 10px rgba(188,253,73,0.5);';

        return `
    <div class="chip-card ${stCls} ${successClass}" data-tag="${myTag}" data-hidden="${isHidden}" style="display: ${isHidden === 'true' ? 'none' : 'flex'}">
        <div class="chip-header">
            <div class="chip-info">
                <div class="chip-index">${idNum}</div>
                <div>
                        <h3 class="chip-name">${nomeLimpo}</h3>
                        <div class="chip-number">
                            <i data-lucide="smartphone" width="12" style="margin-right:4px;"></i> 
                            <span class="${blurClass}">${numFormatado}</span>
                            ${num ? `<button type="button" onclick="window.copyPhone(this, '${num}')" class="btn-copy"><i data-lucide="copy" width="12"></i></button>` : ''}
                        </div>
                        ${profileNameText}
                    </div>
            </div>
            
            <div class="chip-actions">
                <button class="chip-btn-act eye" onclick="window.toggleOcultar('${tok}')" title="Ocultar"><i data-lucide="${isHidden === 'true' ? 'eye-off' : 'eye'}" width="16"></i></button>
                <button class="chip-btn-act" onclick="window.verificarConexao('${tok}', this)" title="Verificar Conexão e Proxy" style="${wifiStyle}"><i data-lucide="wifi" width="16"></i></button>
                <button class="chip-btn-act" onclick="window.abrirPerfil('${tok}')" title="Perfil"><i data-lucide="user" width="16"></i></button>
                <button class="chip-btn-act del" onclick="window.excluirInstancia('${tok}')" title="Excluir"><i data-lucide="trash-2" width="16"></i></button>
            </div>
        </div>

        <div class="chip-tags-row">
            <span class="badge ${badgeCls}">${stLabel}</span>
            ${myTag ? `<span class="badge tag-custom"><i data-lucide="tag" width="10" style="margin-right:4px;"></i> ${myTag}</span>` : ''}
        </div>

        <div class="chip-mid-row">
            <div class="mid-left">
                <label class="toggle-switch" title="Ativar/Desativar Webhook" style="margin:0;">
                    <input type="checkbox" ${whEnabled ? 'checked' : ''} onchange="window.toggleMiniWebhook('${tok}', this)">
                    <span class="toggle-slider"></span>
                </label>
                <span class="conn-status ${whEnabled ? 'conn-online' : 'conn-offline'}">${whEnabled ? 'ONLINE' : 'OFFLINE'}</span>
            </div>
                        <div class="mid-right" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:6px; flex:1;">
                    <button class="btn btn-card-purple" style="font-size:10px; padding:6px 2px;" onclick="window.abrirToken('${tok}')"><i data-lucide="key" width="12"></i> TOKEN</button>
                    <button class="btn btn-card-teal" style="font-size:10px; padding:6px 2px;" onclick="window.abrirProxy('${tok}')"><i data-lucide="router" width="12"></i> PROXY</button>
                    <button class="btn btn-card-yellow" style="font-size:10px; padding:6px 2px;" onclick="window.abrirModalTag('${tok}')"><i data-lucide="tag" width="12"></i> ETIQUETAS</button>
                </div>
        </div>
        
        <div class="chip-bot-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:6px;">
             <button class="btn btn-card-blue" style="width:100%;" onclick="window.abrirWebhook('${tok}')"><i data-lucide="zap" width="14"></i> WEBHOOK</button>
             ${!isOn
                ? `<button class="btn btn-success" style="width:100%; font-weight:800;" onclick="window.reconectar('${tok}')"><i data-lucide="plug" width="14"></i> CONECTAR</button>`
                : `<button class="btn btn-card-red" style="width:100%;" onclick="window.desconectar('${tok}')"><i data-lucide="power-off" width="14"></i> DESCONECTAR</button>`
            }
        </div>
    </div>`;
    }).join('');

    localStorage.setItem('waz_status_cache', JSON.stringify(statusAnterior));
    window.filtrarPainel();
    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 10);
}

let confirmCallback = null;
window.abrirConfirm = function (title, msg, callback) {
    document.getElementById('confirmTitle').innerHTML = title;
    document.getElementById('confirmMsg').innerHTML = msg;
    confirmCallback = callback;
    openModal('modalConfirm');
}
window.executarConfirm = function () {
    closeModal('modalConfirm');
    if (confirmCallback) confirmCallback();
}

window.reconectar = async function (token) {
    currentQRToken = token;
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    const nome = inst ? (inst.name || '') : '';
    toast('Conectando...', 'info');
    const res = await api('POST', '?action=conectar', { token: token, name: nome, instanceName: nome });
    if (res && res.qrCode) { window.mostrarQR(res.qrCode.replace(/^data:image\/png;base64,/, ''), token); }
    else { toast(res.ok ? 'Aguarde conexão...' : 'Erro ao conectar', res.ok ? 'ok' : 'erro'); }
    carregarInstancias();
}

// ── MOSTRAR QR CODE + ANIMAÇÃO CONECTADO ────────────────────
window.mostrarQR = function (b64, token = null) {
    if (token) currentQRToken = token;
    clearInterval(qrInterval);
    const qrWrap = document.getElementById('qrWrap');
    const timerCont = document.getElementById('qrTimerContainer');
    if (!qrWrap) return;

    // Aplicar fade-out se já existir imagem
    const oldImg = document.getElementById('qrImg');
    if (oldImg) {
        oldImg.classList.add('qr-fade-out');
        setTimeout(() => renderNewQR(b64), 400);
    } else {
        renderNewQR(b64);
    }

    function renderNewQR(code) {
        qrWrap.innerHTML = `
            <div class="laser-line"></div>
            <img id="qrImg" class="qr-fade-out" src="data:image/png;base64,${code}" alt="QR" style="width:220px; border-radius:12px; border:2px solid var(--border); box-shadow:0 0 30px rgba(0,0,0,0.5); display:block; margin: 0 auto;"/>
        `;
        // Trigger fade-in
        setTimeout(() => {
            const img = document.getElementById('qrImg');
            if (img) img.classList.remove('qr-fade-out');
        }, 50);

        if (timerCont) {
            timerCont.innerHTML = `<div id="qrTimer">EXPIRA EM 30s</div>`;
        }

        const title = document.getElementById('qrModalTitle');
        if (title) title.innerHTML = '<i data-lucide="qr-code" width="18"></i> ESCANEADOR DE QRCODE';
        if (window.lucide) lucide.createIcons();

        openModal('modalQR');
        iniciarLoopQR(currentQRToken);
    }
}

window.iniciarLoopQR = function (token) {
    clearInterval(qrInterval);
    let tempo = 30;
    const el = document.getElementById('qrTimer');
    const updateTimerText = (t) => {
        if (!el) return;
        el.textContent = `EXPIRA EM ${t}s`;
    };
    updateTimerText(tempo);

    qrInterval = setInterval(async () => {
        const modal = document.getElementById('modalQR');
        if (!modal || !modal.classList.contains('open')) { clearInterval(qrInterval); return; }

        if (token && tempo % 3 === 0) {
            try {
                const resInst = await api('GET', '?action=instancias');
                if (resInst.ok && resInst.data) {
                    const minha = resInst.data.find(i => (i.token || i.id) === token);
                    const isConnected = minha && (
                        minha.connectionStatus === 'connected' ||
                        minha.state === 'open' ||
                        minha.status === 'open' ||
                        minha.instance?.status === 'open' ||
                        minha.instance?.state === 'open'
                    );

                    if (isConnected) {
                        clearInterval(qrInterval);
                        api('POST', '?action=push_instancia_conectada', { nome: minha.nome || 'WhatsApp' });

                        const qrWrap = document.getElementById('qrWrap');
                        if (qrWrap) {
                            qrWrap.innerHTML = `
                             <div class="success-mark">
                                 <div class="success-circle">
                                    <i data-lucide="check" width="40" height="40" style="color:#000; stroke-width:4px;"></i>
                                 </div>
                                 <h2 class="success-title">WHATSAPP CONECTADO!</h2>
                             </div>`;
                            const timerCont = document.getElementById('qrTimerContainer');
                            if (timerCont) timerCont.innerHTML = '';
                            if (window.lucide) lucide.createIcons();
                        }
                        toast('WhatsApp Conectado!', 'ok');
                        if (typeof carregarInstancias === 'function') carregarInstancias();
                        setTimeout(() => closeModal('modalQR'), 3500);
                        return;
                    }
                }
            } catch (e) { }
        }

        tempo--;
        updateTimerText(tempo);

        if (tempo <= 0) {
            clearInterval(qrInterval);
            if (el) el.textContent = "GERANDO NOVO QR...";
            if (token) {
                api('GET', '?action=qrcode&token=' + encodeURIComponent(token))
                    .then(res => {
                        if (res && (res.qrCode || res.qrCodeBase64)) {
                            window.mostrarQR((res.qrCode || res.qrCodeBase64).replace(/^data:image\/[a-z]+;base64,/, ''), token);
                        } else {
                            window.reconectar(token);
                        }
                    })
                    .catch(() => window.reconectar(token));
            }
        }
    }, 1000);
}

window.desconectar = function (token) {
    abrirConfirm('<i data-lucide="power-off" width="20"></i> Desconectar Instância', `Você tem certeza que deseja <strong style="color:var(--red);">DESCONECTAR</strong> esta instância?`, async () => {
        const res = await api('POST', '?action=desconectar', { token });
        toast(res.ok ? 'Desconectado!' : 'Erro ao desconectar', res.ok ? 'ok' : 'erro');
        if (res.ok) carregarInstancias();
    });
}

window.excluirInstancia = function (token) {
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    const nome = inst ? (inst.name || '?') : '?';
    abrirConfirm('<i data-lucide="trash-2" width="20"></i> Apagar Instância', `Deseja apagar a instância?<br><br><span style="color:#fff; font-size:15px; font-weight:800; background:var(--input-bg); padding:6px 12px; border-radius:6px; border:1px solid var(--border); display:inline-block;">"${esc(nome)}"</span>`, async () => {
        const res = await api('DELETE', '?action=excluir', { token, name: nome });
        toast(res.ok ? 'Excluída!' : 'Erro ao excluir', res.ok ? 'ok' : 'erro');
        if (res.ok) carregarInstancias();
    });
}

window.abrirModalTag = function (token) {
    currentTagToken = token;
    const select = document.getElementById('selectTagDef');
    if (select) { select.value = localTags[token] || ''; window.buildSingleFormSelect(select); }
    openModal('modalTag');
}

window.salvarTag = function () {
    const val = document.getElementById('selectTagDef')?.value || '';
    localTags[currentTagToken] = val;
    localStorage.setItem('waz_tags', JSON.stringify(localTags));
    closeModal('modalTag');
    renderInstancias(todasInstancias);
    toast('Etiqueta atualizada!', 'ok');
}

window.abrirModalCriar = function () {
    if (window.sessRole !== 'admin' && todasInstancias.length >= window.sessLimit) {
        return toast(`Você já atingiu o limite de ${window.sessLimit} instâncias.`, 'erro');
    }
    if (document.getElementById('criarNome')) document.getElementById('criarNome').value = '';
    if (document.getElementById('criarWebhook')) document.getElementById('criarWebhook').value = '';
    openModal('modalCriar');
}

window.criarInstancia = async function () {
    const nomeInput = document.getElementById('criarNome');
    const whInput = document.getElementById('criarWebhook');
    const nome = nomeInput ? nomeInput.value.trim() : '';
    const wh = whInput ? whInput.value.trim() : '';
    if (!nome) return toast('Nome obrigatório', 'erro');
    toast('Criando instância...', 'info');
    const res = await api('POST', '?action=criar', { name: nome, webhookUrl: wh });

    if (res.ok || res.success) {
        closeModal('modalCriar');
        let qrParaMostrar = res.qrCode || res.qrCodeBase64;
        let novoToken = res.token;
        const payloadWH = { token: novoToken, instanceName: nome, name: nome, enabled: true, url: wh, events: ['messages', 'labels', 'chat_labels'], excludeMessages: ['isGroupYes', 'fromMeYes'], addUrlEvents: false, addUrlTypeMessages: false };
        await api('POST', '?action=webhook_set', payloadWH);
        if (qrParaMostrar) { window.mostrarQR(qrParaMostrar.replace(/^data:image\/[a-z]+;base64,/, ''), novoToken); }
        carregarInstancias();
        toast('Instância criada e webhook configurado!', 'ok');
    } else { toast(res.erro || res.error || 'Erro ao criar', 'erro'); }
}

window.abrirToken = function (token) {
    const el = document.getElementById('instTokenDisplay');
    if (el) el.value = token;
    openModal('modalToken');
}

window.copiarTokenDisplay = function () {
    const el = document.getElementById('instTokenDisplay');
    if (el && el.value) { navigator.clipboard.writeText(el.value); toast('Token copiado com sucesso!', 'ok'); closeModal('modalToken'); }
}

// BOTÃO WI-FI (POPOVER ANIMADO + TESTE DE STORAGE LOCAL)
window.verificarConexao = async function (token, btnElement) {
    document.querySelectorAll('.wifi-popover').forEach(el => el.remove());

    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    const num = extrairNumero(inst) ? window.formatPhone(extrairNumero(inst)) : 'N/A';
    const lastAccess = new Date().toLocaleString('pt-BR');

    btnElement.innerHTML = '<i data-lucide="loader-2" class="spin" width="16" style="color:var(--yellow);"></i>';
    if (window.lucide) lucide.createIcons();

    const popover = document.createElement('div');
    popover.className = 'wifi-popover';
    popover.innerHTML = `
        <div class="popover-title">Conexão</div>
        <div class="popover-text">Número: <span>${num}</span></div>
        <div class="popover-text">Último acesso: <span>${lastAccess}</span></div>
        <div class="popover-divider"></div>
        <div class="popover-title">IP e localização</div>
        <div id="popover-proxy-content-${token}">
            <div class="popover-text" style="color:var(--yellow);"><div class="spin" style="margin-right:4px;"><i data-lucide="loader-2" width="12"></i></div> Testando storage proxy...</div>
        </div>
        `;
    document.body.appendChild(popover);
    if (window.lucide) lucide.createIcons();

    const rect = btnElement.getBoundingClientRect();
    popover.style.top = (rect.bottom + window.scrollY + 8) + 'px';
    popover.style.left = (rect.right + window.scrollX - 240) + 'px';

    setTimeout(() => popover.classList.add('show'), 10);

    const closeHandler = (e) => {
        if (!popover.contains(e.target) && !btnElement.contains(e.target)) {
            popover.classList.remove('show');
            setTimeout(() => popover.remove(), 200);
            document.removeEventListener('click', closeHandler);
        }
    };
    setTimeout(() => document.addEventListener('click', closeHandler), 100);

    try {
        const res = await api('POST', '?action=proxy_ver', { token });
        let finalStatus = 'none';
        let contentHtml = '';

        if (res && res.data && res.data.host) {
            const host = res.data.host;
            const port = res.data.port;

            const testRes = await api('POST', '?action=testar_proxy', {
                host: host, port: port, protocol: res.data.protocol || 'http', user: res.data.user || '', pass: res.data.pass || ''
            });

            if (testRes.ok) {
                finalStatus = 'ok';
                let loc = testRes.geo?.city ? `${testRes.geo.city}, ${testRes.geo.country}` : 'Localização Oculta';
                let lat = testRes.latency || '??';
                contentHtml = `
        <div class="popover-text">Via proxy</div>
        <div class="popover-text">IP: <span class="popover-ip">${host}</span></div>
        <div class="popover-text"><span>${loc}</span></div>
        <div class="popover-text"><span>(${lat}ms)</span></div>
        `;
            } else {
                finalStatus = 'error';
                contentHtml = `
        <div class="popover-text">Via proxy</div>
        <div class="popover-text">IP: <span class="popover-ip offline">${host}</span></div>
        <div class="popover-text" style="color:var(--red);">Falha / Recusada</div>
        `;
            }
        } else {
            contentHtml = `
                <div class="popover-text">Sem proxy configurada</div>
                <div class="popover-text" style="color:var(--muted); font-size:10px;">Monitoramento Local inativo.</div>
            `;
        }

        document.getElementById(`popover-proxy-content-${token}`).innerHTML = contentHtml;

        proxyCache[token] = finalStatus;
        localStorage.setItem('waz_proxy_cache', JSON.stringify(proxyCache));

        let btnColor = '', btnShadow = '';
        if (finalStatus === 'ok') { btnColor = 'var(--green)'; btnShadow = '0 0 10px rgba(188,253,73,0.5)'; }
        else if (finalStatus === 'error') { btnColor = 'var(--red)'; btnShadow = '0 0 10px rgba(248,113,113,0.6)'; }

        btnElement.style.color = btnColor;
        btnElement.style.textShadow = btnShadow;
        btnElement.innerHTML = '<i data-lucide="wifi" width="16"></i>';
        if (window.lucide) lucide.createIcons();

    } catch (e) {
        document.getElementById(`popover-proxy-content-${token}`).innerHTML = `<div class="popover-text" style="color:var(--red);">Erro no servidor.</div>`;
        btnElement.innerHTML = '<i data-lucide="wifi" width="16"></i>';
        if (window.lucide) lucide.createIcons();
    }
}

window.abrirWebhook = async function (token) {
    whState.token = token;
    const inst = todasInstancias.find(i => (i.token || i.id) === token);
    const instName = inst ? (inst.name || '?') : '?';
    const contentEl = document.getElementById('whContent');
    if (contentEl) contentEl.innerHTML = '<div style="display:flex; justify-content:center; padding:20px; color:var(--green);"><div class="spin" style="width:20px;height:20px;border:3px solid var(--border);border-top-color:var(--green);border-radius:50%;margin-right:10px;"></div> Buscando...</div>';
    document.getElementById('webhookModalTitle').innerHTML = '<i data-lucide="zap" width="18"></i> Gerenciar Webhook';
    openModal('modalWebhook');
    try {
        const res = await api('POST', '?action=webhook_get', { token });
        let cfg = {};
        if (res && res.data) { if (Array.isArray(res.data) && res.data.length > 0) cfg = res.data[0]; else if (typeof res.data === 'object' && !Array.isArray(res.data)) cfg = res.data; } else if (res && Array.isArray(res)) { if (res.length > 0) cfg = res[0]; } else cfg = res || {};
        if (cfg.webhook) cfg = cfg.webhook;
        whState.enabled = !!cfg.enabled;
        whState.url = cfg.url || '';
        whState.events = Array.isArray(cfg.events) ? cfg.events : ['messages'];
        whState.excludeMessages = Array.isArray(cfg.excludeMessages) ? cfg.excludeMessages : [];
        whState.addUrlEvents = !!cfg.addUrlEvents;
        whState.addUrlTypeMessages = !!(cfg.addUrlTypeMessages || cfg.addUrlTypesMessages);
        renderWebhookForm(instName);
    } catch (error) {
        if (contentEl) contentEl.innerHTML = '<div style="color:var(--red); text-align:center; padding:20px;"><i data-lucide="alert-circle"></i> Erro ao buscar webhook.</div>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function renderWebhookForm(instName) {
    const contentEl = document.getElementById('whContent');
    if (!contentEl) return;
    contentEl.innerHTML = `
        <div style="background:var(--card2); border:1px solid var(--border); border-radius:8px; padding:16px; margin-bottom:16px; box-shadow:inset 0 1px 0 rgba(255,255,255,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                <div style="flex:1; min-width:0; margin-right:10px;">
                    <div class="neon-text-green" style="font-family:var(--font-ui); font-weight:800; font-size:13px; margin-bottom:4px; text-transform:uppercase; color:var(--green);">Status do Webhook — ${esc(instName || '')}</div>
                    <div style="color:var(--muted); font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(whState.url || 'URL não configurada')}</div>
                </div>
                <label class="toggle-switch" style="margin:0; flex-shrink:0;">
                    <input type="checkbox" id="whEnabled" ${whState.enabled ? 'checked' : ''} onchange="whState.enabled=this.checked">
                        <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="color:var(--green);">URL do Webhook</label>
                <input class="form-input" id="whUrl" value="${esc(whState.url)}" placeholder="https://meu-n8n.com/webhook/...">
            </div>
        </div>
        <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:8px; padding:16px; box-shadow:inset 0 2px 5px rgba(0,0,0,0.6);">
            <div style="font-family:var(--font-ui); font-size:12px; font-weight:800; text-transform:uppercase; margin-bottom:10px; color:var(--blue);">Escutar Eventos</div>
            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px;">
                ${EVENTOS_DISPONIVEIS.map(ev => `<span class="badge norm" style="cursor:pointer; transition:0.2s; ${whState.events.includes(ev) ? 'background:#0284c7; color:#fff; border-color:#0284c7;' : ''}" onclick="window.toggleEvento('${ev}', this)">${ev}</span>`).join('')}
            </div>
            <div style="height:1px; background:var(--border); margin:12px 0;"></div>
            <div style="font-family:var(--font-ui); font-size:12px; font-weight:800; text-transform:uppercase; margin-bottom:10px; color:var(--red);">Excluir Mensagens</div>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                ${EXCLUSOES_DISPONIVEIS.map(ex => `<span class="badge norm" style="cursor:pointer; transition:0.2s; ${whState.excludeMessages.includes(ex) ? 'background:#dc2626; color:#fff; border-color:#dc2626;' : ''}" onclick="window.toggleExclusao('${ex}', this)">${ex}</span>`).join('')}
            </div>
        </div>`;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.toggleEvento = function (ev, el) {
    const idx = whState.events.indexOf(ev);
    if (idx >= 0) { whState.events.splice(idx, 1); el.style.background = 'var(--card2)'; el.style.color = 'var(--muted)'; el.style.borderColor = 'var(--border)'; }
    else { whState.events.push(ev); el.style.background = '#0284c7'; el.style.color = '#fff'; el.style.borderColor = '#0284c7'; }
}
window.toggleExclusao = function (ex, el) {
    const idx = whState.excludeMessages.indexOf(ex);
    if (idx >= 0) { whState.excludeMessages.splice(idx, 1); el.style.background = 'var(--card2)'; el.style.color = 'var(--muted)'; el.style.borderColor = 'var(--border)'; }
    else { whState.excludeMessages.push(ex); el.style.background = '#dc2626'; el.style.color = '#fff'; el.style.borderColor = '#dc2626'; }
}

window.salvarWebhook = async function () {
    const urlInput = document.getElementById('whUrl');
    whState.url = urlInput ? urlInput.value : whState.url;
    const inst = todasInstancias.find(i => (i.token || i.id) === whState.token);
    const instanceName = inst ? (inst.name || '') : '';
    const payload = { token: whState.token, instanceName: instanceName, name: instanceName, enabled: whState.enabled, url: whState.url, events: whState.events, excludeMessages: whState.excludeMessages, addUrlEvents: whState.addUrlEvents, addUrlTypeMessages: whState.addUrlTypeMessages };
    toast('Salvando webhook...', 'info');
    const res = await api('POST', '?action=webhook_set', payload);
    toast(res.ok ? 'Webhook salvo!' : 'Erro ao salvar', res.ok ? 'ok' : 'erro');
    if (res.ok) { localWhState[whState.token] = whState.enabled; localStorage.setItem('waz_wh_state', JSON.stringify(localWhState)); closeModal('modalWebhook'); renderInstancias(todasInstancias); }
}

window.testarWebhookBtn = async function () {
    const url = document.getElementById('whUrl')?.value;
    if (!url) return toast('Insira a URL do Webhook primeiro', 'erro');
    toast('Disparando evento de teste...', 'info');
    document.querySelector('#modalWebhook .modal').classList.add('glow-pulse');
    const res = await api('POST', '?action=testar_webhook', { url });
    document.querySelector('#modalWebhook .modal').classList.remove('glow-pulse');
    toast(res.ok ? res.msg : res.erro, res.ok ? 'ok' : 'erro');
}

// 🚀 ABRIR PROXY - AGORA COM AUTO-PREENCHIMENTO NATIVO DO STORAGE LOCAL
window.abrirProxy = async function (token) {
    currentProxyToken = token;
    window.switchProxyTab('set');
    openModal('modalProxy');

    // Limpa campos primeiro
    if (document.getElementById('proxyHost')) document.getElementById('proxyHost').value = '';
    if (document.getElementById('proxyPort')) document.getElementById('proxyPort').value = '';
    if (document.getElementById('proxyUser')) document.getElementById('proxyUser').value = '';
    if (document.getElementById('proxyPass')) document.getElementById('proxyPass').value = '';
    if (document.getElementById('proxyProtocol')) {
        document.getElementById('proxyProtocol').value = 'http';
        window.buildSingleFormSelect(document.getElementById('proxyProtocol'));
    }

    // Busca e preenche
    const res = await api('POST', '?action=proxy_ver', { token });
    if (res && res.data && res.data.host) {
        if (document.getElementById('proxyHost')) document.getElementById('proxyHost').value = res.data.host;
        if (document.getElementById('proxyPort')) document.getElementById('proxyPort').value = res.data.port;
        if (document.getElementById('proxyUser')) document.getElementById('proxyUser').value = res.data.user || '';
        if (document.getElementById('proxyPass')) document.getElementById('proxyPass').value = res.data.pass || '';
        if (res.data.protocol && document.getElementById('proxyProtocol')) {
            document.getElementById('proxyProtocol').value = res.data.protocol;
            window.buildSingleFormSelect(document.getElementById('proxyProtocol'));
        }
    }
}

window.switchProxyTab = function (tab) {
    const setForm = document.getElementById('proxySetForm'), btnSalvar = document.getElementById('btnSalvarProxy'), verInfo = document.getElementById('proxyVerInfo'), tabSet = document.getElementById('tabProxySet'), tabVer = document.getElementById('tabProxyVer');
    if (setForm) setForm.style.display = tab === 'set' ? 'block' : 'none';
    if (btnSalvar) btnSalvar.style.display = tab === 'set' ? 'inline-flex' : 'none';
    if (verInfo) verInfo.style.display = tab === 'ver' ? 'block' : 'none';
    if (tabSet) { tabSet.classList.remove('active'); tabSet.style.color = tab === 'set' ? 'var(--green)' : 'var(--text)'; tabSet.style.textShadow = tab === 'set' ? '0 0 10px rgba(188,253,73,0.5)' : 'none'; }
    if (tabVer) { tabVer.classList.remove('active'); tabVer.style.color = tab === 'ver' ? 'var(--green)' : 'var(--text)'; tabVer.style.textShadow = tab === 'ver' ? '0 0 10px rgba(188,253,73,0.5)' : 'none'; }
    if (tab === 'ver') window.verProxy();
}

window.verProxy = async function () {
    const content = document.getElementById('proxyVerContent');
    if (!content) return;
    content.innerHTML = '<div class="spin"><i data-lucide="loader-2" width="24"></i></div> Buscando dados locais...';
    if (window.lucide) lucide.createIcons();

    const res = await api('POST', '?action=proxy_ver', { token: currentProxyToken });

    if (res && res.data && res.data.host) {
        const host = res.data.host;
        const port = res.data.port;
        const proto = res.data.protocol || 'http';
        const user = res.data.user || '';
        const pass = res.data.pass || '';

        content.innerHTML = `<div style="display:flex; align-items:center; gap:10px; color:var(--muted); font-family:var(--font-ui);"><div class="spin"><i data-lucide="loader-2" width="20"></i></div> <span style="font-size:12px;">Testando Storage Local ${host}:${port}...</span></div>`;
        if (window.lucide) lucide.createIcons();

        const testRes = await api('POST', '?action=testar_proxy', { host, port, protocol: proto, user, pass });

        let html = `<div style="margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid var(--border);">
            <span style="color:var(--muted); font-size:11px; font-family:var(--font-ui); text-transform:uppercase;">URL Storage:</span><br>
                <strong style="color:var(--text); font-family:var(--mono); font-size:13px;">${proto}://${user ? '***:***@' : ''}${host}:${port}</strong>
        </div>`;

        if (testRes.ok) {
            let geoText = testRes.geo?.city ? `${testRes.geo.city}, ${testRes.geo.country}<br>ISP: ${testRes.geo.isp || 'N/A'} <br>Latência: <span style="color:var(--yellow);">${testRes.latency || '??'}ms</span>` : `Proxy Ativa <br>Latência: <span style="color:var(--yellow);">${testRes.latency || '??'}ms</span>`;
            html += `<div style="color:var(--green); font-weight:800; font-family:var(--font-ui); margin-bottom:6px; display:flex; align-items:center; gap:6px;"><i data-lucide="check-circle" width="16"></i> STATUS: ONLINE</div><div style="color:var(--muted); font-size:12px; line-height:1.6; font-family:var(--font-ui);">${geoText}</div>`;
        } else {
            html += `<div style="color:var(--red); font-weight:800; font-family:var(--font-ui); margin-bottom:6px; display:flex; align-items:center; gap:6px;"><i data-lucide="x-circle" width="16"></i> STATUS: OFFLINE / REJEITADA</div><div style="color:var(--muted); font-size:12px; line-height:1.5; font-family:var(--font-ui);">${testRes.erro || 'Falha ao conectar pelo túnel.'}</div>`;
        }
        content.innerHTML = html;
        if (window.lucide) lucide.createIcons();
    } else {
        content.innerHTML = '<div style="color:var(--muted); font-size:12px; font-family:var(--font-ui); display:flex; align-items:center; gap:6px;"><i data-lucide="shield-alert" width="16"></i> Nenhum Storage de Proxy configurado.</div>';
        if (window.lucide) lucide.createIcons();
    }
}

// 🚀 SALVAR PROXY - TESTA E RECARREGA TUDO SE SUCESSO
window.salvarProxy = async function () {
    const host = document.getElementById('proxyHost')?.value || '';
    const port = document.getElementById('proxyPort')?.value || '';
    const protocol = document.getElementById('proxyProtocol')?.value || 'http';
    const user = document.getElementById('proxyUser')?.value || '';
    const pass = document.getElementById('proxyPass')?.value || '';

    if (!host || !port) return toast('Preencha Host e Porta', 'erro');

    toast('Validando proxy antes de salvar no Storage...', 'info');
    document.querySelector('#modalProxy .modal').classList.add('glow-pulse');

    const testRes = await api('POST', '?action=testar_proxy', { host, port, protocol, user, pass });
    document.querySelector('#modalProxy .modal').classList.remove('glow-pulse');

    if (!testRes.ok) {
        proxyCache[currentProxyToken] = 'error';
        localStorage.setItem('waz_proxy_cache', JSON.stringify(proxyCache));
        renderInstancias(todasInstancias);
        return toast('Proxy recusada! Verifique os dados.', 'erro');
    }

    const body = { token: currentProxyToken, host, port, username: user, password: pass, protocol };
    const res = await api('POST', '?action=proxy_set', body);

    if (res.ok) {
        proxyCache[currentProxyToken] = 'ok';
        localStorage.setItem('waz_proxy_cache', JSON.stringify(proxyCache));
        toast('Proxy salva no Storage Local!', 'ok');
        closeModal('modalProxy');
        carregarInstancias(); // 🚀 RELOAD DASHBOARD AUTOMÁTICO
    } else {
        toast('Erro ao salvar proxy local', 'erro');
    }
}

window.testarProxyBtn = async function () {
    const host = document.getElementById('proxyHost')?.value;
    const port = document.getElementById('proxyPort')?.value;
    const protocol = document.getElementById('proxyProtocol')?.value || 'http';
    const user = document.getElementById('proxyUser')?.value || '';
    const pass = document.getElementById('proxyPass')?.value || '';

    if (!host || !port) return toast('Preencha Host e Porta para testar', 'erro');
    toast('Testando túnel da proxy...', 'info');
    document.querySelector('#modalProxy .modal').classList.add('glow-pulse');
    const res = await api('POST', '?action=testar_proxy', { host, port, protocol, user, pass });
    document.querySelector('#modalProxy .modal').classList.remove('glow-pulse');
    if (res.ok) {
        let locInfo = res.geo?.city ? ` (${res.geo.city}, ${res.geo.country}) - ${res.latency || '??'}ms` : ` - ${res.latency || '??'}ms`;
        toast(`Sucesso! Proxy Online${locInfo}`, 'ok');
    } else {
        toast(res.erro || 'Falha na conexão proxy', 'erro');
    }
}

window.removerProxy = async function () {
    abrirConfirm('Remover Storage Proxy', 'Remover monitoramento de proxy desta instância?', async () => {
        const res = await api('DELETE', '?action=proxy_del', { token: currentProxyToken });
        if (res.ok) {
            proxyCache[currentProxyToken] = 'none';
            localStorage.setItem('waz_proxy_cache', JSON.stringify(proxyCache));
            toast('Storage de Proxy removido!', 'ok');
            closeModal('modalProxy');
            carregarInstancias(); // RELOAD PÓS REMOÇÃO
        } else { toast('Erro ao remover', 'erro'); }
    });
}

window.abrirPerfil = function (token) {
    currentPerfilToken = token;
    if (document.getElementById('perfilNome')) document.getElementById('perfilNome').value = '';
    if (document.getElementById('perfilFoto')) document.getElementById('perfilFoto').value = '';
    openModal('modalPerfil');
}
window.salvarPerfil = async function () {
    const nome = document.getElementById('perfilNome')?.value || '';
    const foto = document.getElementById('perfilFoto')?.value || '';
    if (nome) { const r = await api('POST', '?action=perfil_nome', { token: currentPerfilToken, name: nome }); toast(r.ok ? 'Nome atualizado!' : 'Erro', r.ok ? 'ok' : 'erro'); }
    if (foto) { const r = await api('POST', '?action=perfil_foto', { token: currentPerfilToken, imageUrl: foto }); toast(r.ok ? 'Foto atualizada!' : 'Erro', r.ok ? 'ok' : 'erro'); }
    if (nome || foto) closeModal('modalPerfil'); else toast('Preencha ao menos um campo', 'erro');
}

// Admin modules removed in Client build.

window.abrirMeuPerfil = function () {
    if (document.getElementById('meuNome')) document.getElementById('meuNome').value = window.sessName || '';
    if (document.getElementById('meuSenha')) document.getElementById('meuSenha').value = '';
    openModal('modalMeuPerfil');
}
window.salvarMeuPerfil = async function () {
    const nome = document.getElementById('meuNome')?.value.trim();
    const password = document.getElementById('meuSenha')?.value;
    const body = { username: window.sessUser, nome: nome };
    if (password) body.password = password;
    toast('Salvando perfil...', 'info');
    const res = await api('POST', '?action=meu_perfil', body);
    if (res.ok) {
        toast('Perfil atualizado!', 'ok');
        window.sessName = nome;
        if (document.getElementById('sidebarName')) document.getElementById('sidebarName').innerText = nome;
        closeModal('modalMeuPerfil');
    } else { toast(res.erro || 'Erro', 'erro'); }
}

// Terminal Admin Modules Removed

function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

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
    const toastContainer = document.getElementById('toast');
    if (toastContainer) {
        toastContainer.appendChild(el);
        if (typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => el.remove(), 4000);
    }
}

// O CLIQUE FORA PARA FECHAR FOI REMOVIDO DAQUI
window.openModal = function (id) {
    const el = document.getElementById(id);
    if (el) {
        el.style.display = 'flex';
        setTimeout(() => el.classList.add('open'), 10);
    }
}
window.closeModal = function (id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('open');
        setTimeout(() => el.style.display = 'none', 300);
    }
    if (id === 'modalQR') clearInterval(qrInterval);
}

window.recarregar = function () { if (currentTab === 'instancias') carregarInstancias(); }
window.logout = async function () { await api('POST', '?action=logout'); window.location.href = '/wazio/index.php'; }

carregarInstancias();
setInterval(() => { if (currentTab === 'instancias') carregarInstancias(); }, 30000);