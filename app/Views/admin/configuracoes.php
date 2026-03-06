<?php
// app/Views/admin/configuracoes.php
require_once __DIR__ . '/../layouts/admin_header.php';
?>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <div class="topbar-title" id="topTitle">
                <i data-lucide="settings" width="18" style="color:var(--green)"></i> WAZ.IO <span
                    style="color:var(--green)">///</span> CONFIGURAÇÕES
            </div>
        </div>
        <div class="topbar-right">
            <button class="btn btn-primary" onclick="salvarConfiguracoes()">
                <i data-lucide="save" width="16"></i> SALVAR ALTERAÇÕES
            </button>
        </div>
    </div>

    <div class="content">
        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi-val" id="totalSettings">--</div>
                <div class="kpi-label">Parâmetros Ativos</div>
                <i data-lucide="settings-2" class="kpi-icon"></i>
            </div>
            <div class="kpi c-green">
                <div class="kpi-val" id="pushStatus">OFF</div>
                <div class="kpi-label">Push Status</div>
                <i data-lucide="bell-ring" class="kpi-icon"></i>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <i data-lucide="sliders" width="16"></i> Preferências do Painel
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Ocultar Números Globalmente</label>
                        <div class="custom-select-container">
                            <select id="cfgHideNumbers" class="form-select">
                                <option value="false">Desativado</option>
                                <option value="true">Ativado (Privacidade)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <label>Tema do Painel</label>
                        <div class="custom-select-container">
                            <select id="cfgTheme" class="form-select">
                                <option value="metal_dark">WAZ.IO Metal Dark</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <i data-lucide="bell" width="16"></i> Push Notifications & Master Controls
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group col-12">
                        <label>Estado Mestre do Push</label>
                        <div class="custom-select-container">
                            <select id="cfgPushMaster" class="form-select">
                                <option value="true">Ativado (Online)</option>
                                <option value="false">Desativado (Mudo)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-row" style="margin-top:10px;">
                    <div class="form-group col-6">
                        <label>OneSignal App ID</label>
                        <input type="text" id="cfgOneSignalId" class="form-input" placeholder="7efc7f67-...">
                    </div>
                    <div class="form-group col-6">
                        <label>Safari Web ID</label>
                        <input type="text" id="cfgSafariId" class="form-input" placeholder="web.onesignal.auto...">
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <i data-lucide="webhook" width="16"></i> Integração N8N & Webhooks
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group col-12">
                        <label>URL Principal Webhook N8N (Receiver)</label>
                        <div class="input-with-icon">
                            <i data-lucide="link-2" width="16"></i>
                            <input type="text" id="n8nWebhookUrl" class="form-input"
                                placeholder="https://n8n.seu-dominio.com/webhook/...">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', async () => {
        if (typeof lucide !== 'undefined') lucide.createIcons();
        await carregarConfiguracoes();
    });

    async function carregarConfiguracoes() {
        try {
            const resSettings = await fetch(API + '&action=get_setting&key=global_hide_numbers');
            const dataHide = await resSettings.json();
            if (dataHide.ok) {
                document.getElementById('cfgHideNumbers').value = dataHide.value ? 'true' : 'false';
            }

            const resPush = await fetch(API + '&action=get_setting&key=n8n_push_settings');
            const dataPush = await resPush.json();
            if (dataPush.ok && dataPush.value) {
                const s = dataPush.value;
                document.getElementById('cfgPushMaster').value = s.master_enabled !== false ? 'true' : 'false';
                document.getElementById('cfgOneSignalId').value = s.onesignal_id || '';
                document.getElementById('cfgSafariId').value = s.safari_web_id || '';
            }

            const resWh = await fetch(API + '&action=get_setting&key=n8n_webhook');
            const dataWh = await resWh.json();
            if (dataWh.ok && dataWh.value) {
                const v = typeof dataWh.value === 'object' ? dataWh.value.value : dataWh.value;
                document.getElementById('n8nWebhookUrl').value = v || '';
            }

            buildAllSelects();
        } catch (e) { console.error(e); }
    }

    function buildAllSelects() {
        ['cfgHideNumbers', 'cfgTheme', 'cfgPushMaster'].forEach(id => {
            const el = document.getElementById(id);
            if (el) window.buildSingleFormSelect(el);
        });
        if (window.lucide) lucide.createIcons();
    }

    async function salvarConfiguracoes() {
        const hideNumbers = document.getElementById('cfgHideNumbers').value === 'true';
        const pushMaster = document.getElementById('cfgPushMaster').value === 'true';
        const osId = document.getElementById('cfgOneSignalId').value.trim();
        const sfId = document.getElementById('cfgSafariId').value.trim();
        const whUrl = document.getElementById('n8nWebhookUrl').value.trim();

        try {
            await Promise.all([
                fetch(API + '&action=save_setting', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key: 'global_hide_numbers', value: hideNumbers })
                }),
                fetch(API + '&action=save_setting', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        key: 'n8n_push_settings', value: {
                            master_enabled: pushMaster,
                            onesignal_id: osId,
                            safari_web_id: sfId,
                            entradas: true, saídas: true, instancias: true, proxies: true // Padrão
                        }
                    })
                }),
                fetch(API + '&action=save_setting', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key: 'n8n_webhook', value: whUrl })
                })
            ]);
            toast('Todas as configurações salvas no Postgres!', 'ok');
        } catch (e) { toast('Erro ao salvar no banco', 'erro'); }
    }
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>