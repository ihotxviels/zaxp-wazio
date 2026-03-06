// URL DINÂMICA — pode ser sobrescrita via window.WAZ_API_URL antes de carregar este script
const API_URL = (typeof window.WAZ_API_URL !== 'undefined') ? window.WAZ_API_URL : '/wazio/contingencia';

// =========================================================================
// MÁGICA DOS FILTROS E MENUS CUSTOMIZADOS (Estilo Escuro com VERDE NEON)
// =========================================================================
const customFilterStyles = document.createElement('style');
customFilterStyles.innerHTML = `
    .filter-dropdown { padding: 6px !important; background: var(--card2) !important; border: 1px solid var(--border) !important; border-radius: 8px !important; min-width: 160px !important; box-shadow: 0 10px 30px rgba(0,0,0,0.9) !important; }
    .filter-dropdown select { display: none !important; }
    .custom-filter-list { display: flex; flex-direction: column; gap: 4px; list-style: none; margin: 0; padding: 0; }
    .custom-filter-option { padding: 8px 12px; font-size: 11px; font-weight: 700; color: var(--muted); cursor: pointer; border-radius: 6px; transition: all 0.2s; font-family: var(--font-ui); text-transform: uppercase; letter-spacing: 0.5px; }
    .custom-filter-option:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .custom-filter-option.active { background: var(--green); color: #080c09; box-shadow: 0 2px 8px rgba(188,253,73,0.4); }
    
    .custom-select-container { position: relative; width: 100%; }
    .custom-select-trigger {
        width: 100%; background: linear-gradient(180deg, #141d16 0%, #030504 100%) !important; 
        box-shadow: inset 0 3px 8px rgba(0,0,0,0.9), inset 0 0 2px rgba(0,0,0,1) !important; 
        border: 1px solid #1a2a1d !important; border-radius: 6px; 
        color: var(--green) !important; font-size: 12px; font-weight: 700; 
        padding: 0 14px; outline: none; font-family: var(--font-ui) !important; 
        transition: all 0.3s; height: 34px; box-sizing: border-box; cursor: pointer; display: flex; justify-content: space-between; align-items: center; text-transform:uppercase;
    }
    .custom-select-trigger:hover, .form-input:focus { border-color: var(--green) !important; box-shadow: inset 0 3px 8px rgba(0,0,0,0.9), 0 0 10px rgba(188,253,73,0.15) !important; }
    .custom-select-options {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0;
        background: var(--card2); border: 1px solid var(--border); border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.9); z-index: 9999;
        display: none; flex-direction: column; padding: 6px; gap: 4px;
        max-height: 200px; overflow-y: auto;
    }
    .custom-select-options.show { display: flex; }
    .custom-select-option {
        padding: 8px 12px; font-size: 11px; font-weight: 700; color: var(--muted); 
        cursor: pointer; border-radius: 6px; transition: all 0.2s; font-family: var(--font-ui) !important; text-transform: uppercase;
    }
    .custom-select-option:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .custom-select-option.active { background: var(--green); color: #080c09; box-shadow: 0 2px 8px rgba(188,253,73,0.4); }
    .custom-select-options::-webkit-scrollbar { width: 4px; }
    .custom-select-options::-webkit-scrollbar-thumb { background: var(--green); border-radius: 2px; }
`;
document.head.appendChild(customFilterStyles);

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
            select.dispatchEvent(new Event('change'));
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

window.syncFormSelectsUI = function () {
    document.querySelectorAll('.custom-select-container').forEach(container => {
        const select = container.querySelector('select');
        const triggerText = container.querySelector('.custom-select-trigger span');
        const options = container.querySelectorAll('.custom-select-option');

        if (select && triggerText) {
            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt) triggerText.innerText = selectedOpt.text;

            options.forEach(optDiv => {
                if (optDiv.dataset.value === select.value) {
                    optDiv.classList.add('active');
                } else {
                    optDiv.classList.remove('active');
                }
            });
        }
    });
}

window.buildCustomDropdowns = function () {
    document.querySelectorAll('.filter-dropdown').forEach(dropdown => {
        if (!dropdown.querySelector('.custom-filter-list')) {
            const select = dropdown.querySelector('select');
            if (!select) return;

            select.style.display = 'none';

            const ul = document.createElement('ul');
            ul.className = 'custom-filter-list';

            Array.from(select.options).forEach((opt) => {
                const li = document.createElement('li');
                const isActive = select.value === opt.value;
                li.className = 'custom-filter-option' + (isActive ? ' active' : '');

                let cleanText = opt.text.replace(/🔴|🟢|🔵|🔥|🔎|🚫/g, '').trim();
                cleanText = cleanText.replace(/^(Status|Conexão|Dispositivo|Função):\s*/i, '');

                li.innerText = cleanText;
                li.dataset.value = opt.value;

                li.addEventListener('click', (e) => {
                    e.stopPropagation();
                    ul.querySelectorAll('.custom-filter-option').forEach(el => el.classList.remove('active'));
                    li.classList.add('active');

                    select.value = opt.value;
                    const onchangeStr = select.getAttribute('onchange');
                    if (onchangeStr) {
                        const match = onchangeStr.match(/setColFilter\('(\d+)',\s*'([^']+)'/);
                        if (match) { window.setColFilter(match[1], match[2], select); }
                    }
                    dropdown.classList.remove('show');
                });
                ul.appendChild(li);
            });
            dropdown.appendChild(ul);
        }
    });
}

let chipsCache = [];
let currentConfig = null;
let tempDevices = [];
let colFilters = {
    '1': { status: 'ALL', conexao: 'ALL', dispositivo: 'ALL', funcao: 'ALL' },
    '2': { status: 'ALL', conexao: 'ALL', dispositivo: 'ALL', funcao: 'ALL' },
    '3': { status: 'ALL', conexao: 'ALL', dispositivo: 'ALL', funcao: 'ALL' }
};

window.toggleDropdown = function (btn) {
    document.querySelectorAll('.filter-dropdown').forEach(d => {
        if (d !== btn.nextElementSibling) d.classList.remove('show');
    });
    btn.nextElementSibling.classList.toggle('show');
}

document.addEventListener('click', e => {
    if (!e.target.closest('.col-filters')) {
        document.querySelectorAll('.filter-dropdown').forEach(d => d.classList.remove('show'));
    }
    if (!e.target.closest('.custom-select-container')) {
        document.querySelectorAll('.custom-select-options').forEach(d => d.classList.remove('show'));
    }
});

window.setColFilter = function (colId, filterType, selectElement) {
    const value = selectElement.value;
    // Evitar quebra se a coluna for inválida
    if (!colFilters[colId]) colFilters[colId] = { status: 'ALL', conexao: 'ALL', dispositivo: 'ALL', funcao: 'ALL' };
    colFilters[colId][filterType] = value;

    const btnIcon = selectElement.parentElement.previousElementSibling;
    if (value !== 'ALL') { btnIcon.classList.add('active-filter'); }
    else { btnIcon.classList.remove('active-filter'); }
    window.renderBoard(chipsCache);
}

const indexSelect = document.getElementById('chipIndex');
if (indexSelect) {
    for (let i = 0; i <= 99; i++) indexSelect.add(new Option(String(i).padStart(2, '0'), String(i).padStart(2, '0')));
}

window.updateDeviceSelects = function () {
    if (!currentConfig || !currentConfig.devices) return;
    const devices = currentConfig.devices;

    const filterSelects = document.querySelectorAll('select[onchange*="dispositivo"]');
    filterSelects.forEach(select => {
        const currentVal = select.value;
        const currentOptions = Array.from(select.options).map(o => o.value).filter(v => v !== 'ALL');

        if (JSON.stringify(currentOptions) !== JSON.stringify(devices)) {
            select.innerHTML = '<option value="ALL">🔴 Dispositivo: Todos</option>';
            devices.forEach(d => select.add(new Option(d, d)));
            if (devices.includes(currentVal)) select.value = currentVal;

            const existingUl = select.parentElement.querySelector('.custom-filter-list');
            if (existingUl) existingUl.remove();
        }
    });

    const modalSelect = document.getElementById('chipDispositivo');
    if (modalSelect) {
        const currentVal = modalSelect.value;
        const currentOptions = Array.from(modalSelect.options).map(o => o.value);
        if (JSON.stringify(currentOptions) !== JSON.stringify(devices)) {
            modalSelect.innerHTML = '';
            devices.forEach(d => modalSelect.add(new Option(d, d)));
            if (devices.includes(currentVal)) { modalSelect.value = currentVal; }
            window.buildSingleFormSelect(modalSelect);
        }
    }

    // [NOVO] Atualiza select de instâncias no modal
    const instanceSelect = document.getElementById('chipInstanceName');
    if (instanceSelect && window.instancesCache) {
        const currentVal = instanceSelect.value;
        const currentOptions = Array.from(instanceSelect.options).map(o => o.value).filter(v => v !== '');
        if (JSON.stringify(currentOptions) !== JSON.stringify(window.instancesCache)) {
            instanceSelect.innerHTML = '<option value="">Nenhuma instância vinculada</option>';
            window.instancesCache.forEach(inst => instanceSelect.add(new Option(inst.toUpperCase(), inst)));
            if (window.instancesCache.includes(currentVal)) instanceSelect.value = currentVal;
            window.buildSingleFormSelect(instanceSelect);
        }
    }

    window.buildCustomDropdowns();
}

window.openDeviceModal = function () {
    const modal = document.getElementById('deviceModal');
    if (!modal) return;
    if (currentConfig && currentConfig.devices) { tempDevices = [...currentConfig.devices]; } else { tempDevices = []; }
    window.renderDeviceList();
    document.getElementById('newDeviceInput').value = '';
    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('open'); }, 10);
}

window.closeDeviceModal = function () {
    const modal = document.getElementById('deviceModal');
    if (modal) {
        modal.classList.remove('open');
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    }
}

window.renderDeviceList = function () {
    const container = document.getElementById('deviceListContainer');
    if (!container) return;

    container.innerHTML = '';
    if (tempDevices.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding: 20px; color: var(--muted); font-size: 11px; font-family: var(--font-ui);">Nenhum dispositivo cadastrado.</div>';
        return;
    }
    tempDevices.forEach((dev, index) => {
        const div = document.createElement('div');
        div.className = 'device-list-item';
        div.innerHTML = `<span style="font-family: var(--font-ui); font-weight:700;">${dev}</span><button type="button" onclick="removeDeviceItem(${index})" class="btn-remove-device" title="Remover"><i data-lucide="x" width="14"></i></button>`;
        container.appendChild(div);
    });
    if (window.lucide) lucide.createIcons();
}

window.addDeviceItem = function () {
    const input = document.getElementById('newDeviceInput');
    const val = input.value.trim().toUpperCase();
    if (val === '') return;
    if (tempDevices.includes(val)) { window.showToast('Este dispositivo já existe!', 'erro'); return; }
    tempDevices.push(val);
    input.value = '';
    input.focus();
    window.renderDeviceList();
}

window.removeDeviceItem = function (index) {
    tempDevices.splice(index, 1);
    window.renderDeviceList();
}

window.saveDevicesConfig = async function () {
    try {
        await fetch(API_URL + '?action=save_devices', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ devices: tempDevices }) });
        window.showToast('Lista de Dispositivos atualizada!', 'success');
        window.closeDeviceModal();
        window.loadData();
    } catch (e) { window.showToast('Erro ao salvar', 'erro'); }
}

window.editColumnName = function (id) {
    const span = document.getElementById(`col-title-${id}`);
    const editBtn = document.getElementById(`btn-edit-col-${id}`);
    const saveBtn = document.getElementById(`btn-save-col-${id}`);
    span.contentEditable = "true";
    span.focus();
    editBtn.style.display = "none";
    saveBtn.style.display = "inline-flex";
}

window.saveColumnName = async function (id) {
    const span = document.getElementById(`col-title-${id}`);
    const editBtn = document.getElementById(`btn-edit-col-${id}`);
    const saveBtn = document.getElementById(`btn-save-col-${id}`);
    span.contentEditable = "false";
    editBtn.style.display = "inline-flex";
    saveBtn.style.display = "none";
    const newTitle = span.innerText.trim();
    try {
        await fetch(API_URL + '?action=save_col', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, title: newTitle }) });
        window.showToast('Título da coluna salvo!', 'success');
        const opt = document.querySelector(`#chipCategoria option[value="${id}"]`);
        if (opt) {
            opt.innerText = newTitle.toUpperCase();
            window.buildSingleFormSelect(document.getElementById('chipCategoria'));
        }
    } catch (e) { window.showToast('Erro ao renomear', 'erro'); }
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
    window.showToast('Copiado: ' + numFormatado, 'success');
    const icon = btn.querySelector('i');
    const oldIcon = icon.getAttribute('data-lucide');
    icon.setAttribute('data-lucide', 'check');
    icon.style.color = 'var(--green)';
    if (window.lucide) lucide.createIcons();
    setTimeout(() => { icon.setAttribute('data-lucide', oldIcon); icon.style.color = ''; if (window.lucide) lucide.createIcons(); }, 1500);
};

const numeroInput = document.getElementById('chipNumero');
if (numeroInput) { numeroInput.addEventListener('input', function (e) { this.value = window.formatPhone(this.value); }); }

window.getStatusBadge = function (status) {
    const s = status ? status.toUpperCase().trim() : 'OFF';
    let cls = 'st-def';
    if (s.includes('DISPONÍVEL')) cls = 'st-disp';
    else if (s.includes('RESTABELECIDO')) cls = 'st-rest';
    else if (s.includes('API') || s.includes('ATIVO')) cls = 'st-api';
    else if (s.includes('BANIDO')) cls = 'st-ban';
    else if (s.includes('ANÁLISE') || s.includes('ANALISE')) cls = 'st-analise';
    else if (s.includes('AQUEC')) cls = 'st-aq';
    return `<span class="badge ${cls}">${s}</span>`;
}

window.getFunctionBadge = function (funcao) {
    const f = funcao ? funcao.toUpperCase().trim() : 'ADMINISTRADOR';
    let cls = 'fn-admin';
    if (f.includes('GERAR BOLETOS')) cls = 'fn-boletos';
    else if (f.includes('GRUPOS')) cls = 'fn-grupos';
    else if (f.includes('AQUECIMENTO')) cls = 'fn-aq';
    else if (f.includes('ATENDIMENTO')) cls = 'fn-atend';
    else if (f.includes('CAMPANHA ADS')) cls = 'fn-ads';
    else if (f.includes('LINKS')) cls = 'fn-links';
    return `<span class="badge ${cls}">${f}</span>`;
}

window.hideLoader = function () {
    const loader = document.getElementById('initial-loader');
    if (loader) { loader.style.opacity = '0'; setTimeout(() => { loader.style.display = 'none'; }, 500); }
}

// ── PROTEÇÃO CONTRA VALORES NULOS NOS KPIs ──
window.updateKPIs = function (kpis) {
    if (!kpis) kpis = {};
    const map = {
        'total': kpis.total_ativos || 0,
        'disparadores': kpis.disparadores || 0,
        'ads': kpis.ads || 0,
        'boletos': kpis.boletos || 0,
        'aquecimento': kpis.aquecimento || 0,
        'banidos': kpis.banidos || 0,
        'analise': kpis.analise || 0
    };
    for (let key in map) {
        const el = document.getElementById(`kpi-${key}`);
        if (el) el.innerText = map[key];
    }
}

window.loadData = async function () {
    try {
        const res = await fetch(`${API_URL}?action=list`);
        if (!res.ok) throw new Error('Falha na resposta do servidor');
        const r = await res.json();

        if (r.error) { window.showToast(r.error, 'erro'); window.hideLoader(); return; }

        chipsCache = r.chips || [];
        currentConfig = r.config || null;
        window.instancesCache = r.instances || []; // [NOVO] Cache de instâncias vindo do PHP

        window.updateDeviceSelects();
        window.renderBoard(chipsCache);
        window.updateKPIs(r.kpis);
        window.hideLoader();
    } catch (e) {
        console.error('Erro ao carregar JSON:', e);
        window.hideLoader();
    }
}

window.renderBoard = function (chips) {
    ['1', '2', '3'].forEach(id => { const el = document.getElementById(`col-${id}`); if (el) el.innerHTML = ''; });
    const counts = { 1: 0, 2: 0, 3: 0 };

    let filteredChips = chips.filter(chip => {
        const cat = chip.categoria || '1';
        const st = (chip.status || '').toUpperCase();
        const conn = (chip.conexao || '').toUpperCase();
        const func = (chip.funcao || '').toUpperCase();
        const disp = (chip.dispositivo || '').toUpperCase();

        // Trava de Segurança Se Categoria For Inválida
        const f = colFilters[cat] || { status: 'ALL', conexao: 'ALL', dispositivo: 'ALL', funcao: 'ALL' };

        if (f.status !== 'ALL') {
            if (f.status === 'ATIVOS' && conn !== 'ONLINE') return false;
            if (f.status === 'OFFLINE' && conn !== 'OFFLINE') return false;
            if (f.status === 'DISPONÍVEL' && !st.includes('DISPON')) return false;
            if (f.status === 'RESTABELECIDO' && !st.includes('RESTAB')) return false;
            if (f.status === 'AQUECENDO' && !st.includes('AQUEC')) return false;
            if (f.status === 'ANÁLISE' && (!st.includes('ANÁLISE') && !st.includes('ANALISE'))) return false;
            if (f.status === 'BANIDO' && !st.includes('BANIDO')) return false;
        }

        if (f.conexao !== 'ALL' && conn !== f.conexao) return false;
        if (f.dispositivo !== 'ALL' && disp !== f.dispositivo) return false;
        if (f.funcao !== 'ALL' && func !== f.funcao) return false;

        return true;
    });

    filteredChips.sort((a, b) => parseInt(a.index || 0, 10) - parseInt(b.index || 0, 10));

    filteredChips.forEach((chip) => {
        const cat = chip.categoria || '1';
        if (counts[cat] !== undefined) {
            counts[cat]++;
            const col = document.getElementById(`col-${cat}`);
            if (col) col.innerHTML += window.createCardHTML(chip);
        }
    });

    for (let k in counts) {
        const badge = document.getElementById(`count-${k}`);
        if (badge) badge.innerText = counts[k];
        const col = document.getElementById(`col-${k}`);
        if (col && counts[k] === 0) {
            col.innerHTML = `<div class="empty" style="padding:20px; text-align:center; color:var(--muted); font-size:12px; display:flex; flex-direction:column; align-items:center; gap:8px;"><i data-lucide="sim-card" width="24" class="empty-icon" style="opacity:0.5"></i> Nenhum Terminal</div>`;
        }
    }
    if (window.lucide) lucide.createIcons();
}

window.toggleChipBan = async function (id, checkbox) {
    const chip = chipsCache.find(c => String(c.id) === String(id));
    if (!chip) return;
    const isAtivo = checkbox.checked;

    chip.status = isAtivo ? 'DISPONÍVEL' : 'BANIDO';
    checkbox.nextElementSibling.style.borderColor = isAtivo ? 'var(--green)' : 'var(--red)';

    try {
        await fetch(API_URL + '?action=save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(chip) });
        window.showToast(isAtivo ? 'Chip Disponível' : 'Chip Banido', isAtivo ? 'success' : 'erro');
        window.loadData();
    } catch (error) {
        checkbox.checked = !isAtivo;
        window.showToast('Erro ao atualizar', 'erro');
    }
}

window.createCardHTML = function (chip) {
    const conexaoStr = (chip.conexao || 'OFFLINE').toUpperCase();
    const isOffline = conexaoStr === 'OFFLINE';
    const isBanned = (chip.status || '').toUpperCase() === 'BANIDO';

    const funcaoStr = (chip.funcao || '').toUpperCase();
    let typeBadgeHTML = '<span class="type-badge type-normal">NORMAL</span>';
    if (funcaoStr.includes('BOLETOS') || funcaoStr.includes('ADS') || funcaoStr.includes('CAMPANHA')) {
        typeBadgeHTML = '<span class="type-badge type-business">BUSINESS</span>';
    }

    return `
    <div class="chip-card ${isOffline || isBanned ? 'card-offline' : ''}" data-id="${chip.id}">
        
        <div class="chip-header">
            <div class="chip-info">
                <div class="chip-index">${chip.index || '00'}</div>
                <div>
                    <h3 class="chip-name">${chip.nome || 'Terminal'}</h3>
                    <div class="chip-number">
                        <i data-lucide="smartphone" width="12" style="margin-right: 4px;"></i> 
                        ${window.formatPhone(chip.numero)}
                        <button type="button" onclick="copyPhone(this, '${chip.numero}')" class="btn-copy" title="Copiar Número">
                            <i data-lucide="copy" width="12"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="chip-actions">
                <button onclick="editChip('${chip.id}')" class="chip-btn-act"><i data-lucide="edit-3" width="14"></i></button>
                <button onclick="deleteChip('${chip.id}')" class="chip-btn-act del"><i data-lucide="trash-2" width="14"></i></button>
            </div>
        </div>

        <div class="chip-tags">
            ${window.getStatusBadge(chip.status)}
            ${window.getFunctionBadge(chip.funcao)}
        </div>
        
        <div class="chip-mid-row">
            <div style="display:flex; align-items:center; gap:8px;">
                <label class="toggle-switch" title="Status do Chip (Ativo / Banido)">
                    <input type="checkbox" ${!isBanned ? 'checked' : ''} onchange="toggleChipBan('${chip.id}', this)">
                    <span class="toggle-slider" style="${isBanned ? 'border-color:var(--red);' : ''}"></span>
                </label>
                <span class="conn-status" style="font-family:var(--font-ui); font-size:10px; font-weight:800; color:${!isBanned ? 'var(--green)' : 'var(--red)'};">${!isBanned ? 'ATIVO' : 'BANIDO'}</span>
            </div>
            <div class="conn-status conn-${conexaoStr.toLowerCase()}"><span class="conn-dot"></span> ${conexaoStr}</div>
        </div>

        <div class="chip-footer">
            <div class="device-badge">${chip.dispositivo || 'MOTOROLA 01'}</div>
            ${typeBadgeHTML}
        </div>
        
    </div>`;
}

window.openModal = function (isEdit = false) {
    const modal = document.getElementById('chipModal');
    if (!modal) return;
    if (!isEdit) {
        document.getElementById('chipForm').reset();
        document.getElementById('chipId').value = '';
        setTimeout(() => { window.syncFormSelectsUI(); }, 10);
    }
    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('open'); }, 10);
}

window.closeModal = function () {
    const modal = document.getElementById('chipModal');
    if (modal) { modal.classList.remove('open'); setTimeout(() => { modal.style.display = 'none'; }, 300); }
}

window.editChip = function (id) {
    const chip = chipsCache.find(c => String(c.id) === String(id));
    if (chip) {
        document.getElementById('chipId').value = chip.id;
        document.getElementById('chipIndex').value = chip.index || '00';
        document.getElementById('chipNome').value = chip.nome || '';
        document.getElementById('chipNumero').value = window.formatPhone(chip.numero) || '';
        if (chip.status) document.getElementById('chipStatus').value = chip.status.toUpperCase().trim();
        if (chip.conexao) document.getElementById('chipConexao').value = chip.conexao.toUpperCase().trim();
        if (chip.categoria) document.getElementById('chipCategoria').value = chip.categoria;
        if (chip.funcao) document.getElementById('chipFuncao').value = chip.funcao.toUpperCase().trim();
        document.getElementById('chipDispositivo').value = (chip.dispositivo || 'MOTOROLA 01').toUpperCase().trim();

        // [NOVO] Sincronia de Instância no Modal
        const instSel = document.getElementById('chipInstanceName');
        if (instSel) instSel.value = chip.instance_name || '';

        window.syncFormSelectsUI();
        window.openModal(true);
    }
}

window.saveChip = async function (e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="spin" style="width:14px;height:14px;border:2px solid var(--bg);border-top-color:transparent;border-radius:50%;display:inline-block;vertical-align:middle;margin-right:6px;"></div> SALVANDO...';
    btn.style.pointerEvents = 'none'; btn.style.opacity = '0.8';

    const payload = {
        id: document.getElementById('chipId').value,
        index: document.getElementById('chipIndex').value,
        nome: document.getElementById('chipNome').value,
        numero: document.getElementById('chipNumero').value.replace(/\D/g, ''),
        status: document.getElementById('chipStatus').value,
        conexao: document.getElementById('chipConexao').value,
        categoria: document.getElementById('chipCategoria').value,
        funcao: document.getElementById('chipFuncao').value,
        dispositivo: document.getElementById('chipDispositivo').value,
        instance_name: document.getElementById('chipInstanceName').value // [NOVO]
    };

    try {
        const response = await fetch(API_URL + '?action=save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        if (!response.ok) throw new Error("Erro no servidor");
        window.closeModal();
        await window.loadData();
        window.showToast('Terminal salvo com sucesso!', 'success');
    } catch (error) { window.showToast('Erro ao salvar terminal', 'erro'); }
    finally { btn.innerHTML = originalText; btn.style.pointerEvents = 'auto'; btn.style.opacity = '1'; }
}

let confirmCallback = null;

window.abrirConfirm = function (title, msg, callback) {
    document.getElementById('confirmTitle').innerHTML = title;
    document.getElementById('confirmMsg').innerHTML = msg;
    confirmCallback = callback;

    const modal = document.getElementById('modalConfirm');
    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('open'); }, 10);
    if (window.lucide) lucide.createIcons();
}

window.closeConfirmModal = function () {
    const modal = document.getElementById('modalConfirm');
    modal.classList.remove('open');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

window.executarConfirm = function () {
    closeConfirmModal();
    if (confirmCallback) confirmCallback();
}

window.deleteChip = async function (id) {
    abrirConfirm(
        '<i data-lucide="trash-2" width="18"></i> DELETAR CHIP',
        'Tem certeza que deseja apagar este chip da base de dados?<br><br><span style="color:var(--muted); font-size:11px;">Esta ação removerá todas as configurações de contingência.</span>',
        async () => {
            try {
                await fetch(API_URL + '?action=delete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
                window.loadData();
                window.showToast('Terminal excluído da base', 'success');
            } catch (error) { window.showToast('Erro ao excluir terminal', 'erro'); }
        }
    );
}

window.showToast = function (msg, type = 'success') {
    const classType = type === 'success' ? 'ok' : 'erro';
    const icon = type === 'success' ? 'check-circle' : 'alert-circle';
    const el = document.createElement('div');
    el.className = `toast-msg ${classType}`;
    el.innerHTML = `<i data-lucide="${icon}" width="16"></i> <span>${msg}</span>`;
    let container = document.getElementById('toast');
    if (!container) { container = document.createElement('div'); container.id = 'toast'; document.body.appendChild(container); }
    container.appendChild(el);
    if (window.lucide) lucide.createIcons();
    setTimeout(() => el.remove(), 4000);
}

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => {
        if (e.target === el) { el.classList.remove('open'); setTimeout(() => el.style.display = 'none', 300); }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    ['chipStatus', 'chipConexao', 'chipFuncao', 'chipCategoria', 'chipIndex'].forEach(id => {
        const el = document.getElementById(id);
        if (el) window.buildSingleFormSelect(el);
    });

    if (typeof Sortable !== 'undefined' && window.innerWidth > 768) {
        ['col-1', 'col-2', 'col-3'].forEach(colId => {
            const col = document.getElementById(colId);
            if (col) {
                new Sortable(col, {
                    group: 'chips', animation: 200, ghostClass: 'opacity-20',
                    onEnd: async function (evt) {
                        if (evt.from !== evt.to) {
                            try {
                                await fetch(API_URL + '?action=move', {
                                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ id: evt.item.dataset.id, categoria: evt.to.dataset.category })
                                });
                                window.loadData();
                            } catch (error) { window.loadData(); }
                        } else { window.loadData(); }
                    }
                });
            }
        });
    }
    window.loadData();
    setInterval(window.loadData, 30000);
});