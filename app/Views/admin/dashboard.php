<?php require_once __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="topbar">
  <div class="topbar-left" style="display:flex; align-items:center;">
    <span class="topbar-title" id="topTitle" style="margin-left: 12px;">
      <i data-lucide="smartphone" width="18" style="color:var(--green)"></i> WAZ.IO <span
        style="color:var(--green);">///</span> INSTÂNCIAS
    </span>

  </div>
  <div class="topbar-right">
    <div class="input-with-icon" style="position: relative; display: flex; align-items: center;">
      <i data-lucide="search" width="14"
        style="position: absolute; left: 12px; color: var(--muted); pointer-events: none;"></i>
      <input type="text" id="searchInst" class="form-input" placeholder="Pesquisar números..."
        style="width:200px; padding-left: 36px;" onkeyup="filtrarPainel()">
    </div>

    <button class="btn btn-ghost" onclick="animarRecarregamento()" style="margin-left: 8px;">
      <i data-lucide="refresh-cw" width="14"></i> Atualizar
    </button>

    <!-- Novo Campo de Notificações (Dropdown estilo Wifi) -->
    <div class="notification-wrapper"
      style="position: relative; display: flex; align-items: center; gap: 5px; margin-left: auto;">
      <button class="btn btn-ghost" id="btnNotifications" title="Ver Notificações"
        onclick="toggleNotificationDropdown(this)" style="width: 34px; padding: 0;">
        <i data-lucide="bell" width="14"></i>
        <span id="notifBadge"
          style="display:none; position:absolute; top:4px; right:4px; width:8px; height:8px; background:var(--red); border-radius:50%; border:2px solid var(--sidebar);"></span>
      </button>
      <button class="btn btn-ghost" id="btnNotifSettings" title="Configurar Alertas"
        onclick="openNotificationSettings()" style="width: 34px; padding: 0;">
        <i data-lucide="settings" width="14"></i>
      </button>

      <!-- 🔔 NOTIFICATION DROPDOWN -->
      <div id="notifDropdown" class="notif-dropdown">
        <div class="notif-header">
          <div style="display:flex; align-items:center; gap:8px;">
            <i data-lucide="bell" width="14" style="color:var(--green)"></i>
            <span style="font-size:11px; font-weight:800; text-transform:uppercase;">Alertas de Sistema</span>
          </div>
        </div>
        <div id="notifList" class="notif-list-dropdown">
          <div class="empty" style="padding:20px; font-size:11px; color:var(--muted); text-align:center;">
            Nenhuma notificação recente.
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<div class="content">
  <div class="kpi-grid" id="kpiGrid">
    <div class="kpi c-green" title="Total de instâncias com conexão aberta e prontas para uso."
      style="box-shadow:none !important; border:1px solid var(--border) !important;">
      <div class="kpi-icon"><i data-lucide="check-circle" width="24"></i></div>
      <div class="kpi-val" id="kpiOn">—</div>
      <div class="kpi-label">Conectadas</div>
    </div>
    <div class="kpi c-red" title="Instâncias que perderam a conexão ou foram desconectadas."
      style="box-shadow:none !important; border:1px solid var(--border) !important;">
      <div class="kpi-icon"><i data-lucide="x-circle" width="24"></i></div>
      <div class="kpi-val" id="kpiOff">—</div>
      <div class="kpi-label">Desconectadas</div>
    </div>
    <div class="kpi c-blue" title="Quantidade total de contatos únicos salvos."
      style="box-shadow:none !important; border:1px solid var(--border) !important;">
      <div class="kpi-icon"><i data-lucide="users" width="24"></i></div>
      <div class="kpi-val" id="kpiContacts">—</div>
      <div class="kpi-label">Contatos</div>
    </div>
    <div class="kpi c-purple" title="Total de mensagens trafegadas no sistema."
      style="box-shadow:none !important; border:1px solid var(--border) !important;">
      <div class="kpi-icon"><i data-lucide="message-square" width="24"></i></div>
      <div class="kpi-val" id="kpiMessages">—</div>
      <div class="kpi-label">Mensagens</div>
    </div>
    <div class="kpi c-yellow" title="Total geral de funis de vendas construídos."
      style="box-shadow:none !important; border:1px solid var(--border) !important;">
      <div class="kpi-icon"><i data-lucide="git-merge" width="24"></i></div>
      <div class="kpi-val" id="kpiFunnels">—</div>
      <div class="kpi-label">Funis</div>
    </div>
  </div>

  <div id="tabInstancias">
    <div class="section">
      <div class="section-head section-head-responsive">
        <div class="section-title">
          <div style="display:flex; align-items:center; gap:8px;"><i data-lucide="server" width="16"></i>
            Gerenciamento de Instâncias</div>
          <div id="instLimitBadge"
            style="margin-left: 12px; background: linear-gradient(180deg, rgba(188,253,73,0.15) 0%, rgba(188,253,73,0.05) 100%); border: 1px solid rgba(188,253,73,0.3); border-radius: 6px; padding: 4px 10px; font-size: 11px; font-family: var(--mono); color: var(--green); display: flex; align-items: center; gap: 6px; box-shadow: 0 0 10px rgba(188,253,73,0.1);">
            <i data-lucide="cpu" width="14"></i> <span id="instLimitText">— / —</span>
          </div>
        </div>
        <div class="section-actions">
          <button class="btn btn-ghost" id="btnOcultarGlob" onclick="toggleGlobalOcultarNumeros()"
            title="Ocultar Números" style="width: 38px; padding: 0; margin-right: 8px;">
            <i data-lucide="eye-off" width="16"></i>
          </button>
          <div class="filter-container" style="position:relative;">

            <button id="btnFilterTag" class="btn btn-ghost"
              onclick="document.getElementById('tagFilterDropdown').classList.toggle('show')"
              style="padding: 6px 12px; font-size: 11px; height: 34px; border:1px solid var(--border); min-width: 170px; justify-content: space-between;">
              <div style="display:flex; align-items:center; gap:6px;">
                <i data-lucide="tag" width="14"></i> <span id="labelFilterTag">Todas as Etiquetas</span>
              </div>
              <i data-lucide="chevron-down" width="14"></i>
            </button>
            <div id="tagFilterDropdown" class="filter-dropdown"
              style="width: 100%; right: 0; left: auto; top: 100%; margin-top: 5px;">
              <div class="filter-opt" onclick="setDashboardFilter('all', 'Todas as Etiquetas')">Todas as Etiquetas</div>
              <div class="filter-opt" onclick="setDashboardFilter('Campanha Ads', 'Campanha Ads')">Campanha Ads</div>
              <div class="filter-opt" onclick="setDashboardFilter('Gerar Boletos', 'Gerar Boletos')">Gerar Boletos</div>
              <div class="filter-opt" onclick="setDashboardFilter('Administrador', 'Administrador')">Administrador</div>
              <div class="filter-opt" onclick="setDashboardFilter('Criador de Grupos', 'Criador de Grupos')">Criador de
                Grupos</div>
              <div class="filter-opt" onclick="setDashboardFilter('hidden', 'Instâncias Ocultas')">Instâncias Ocultas
              </div>
            </div>
            <input type="hidden" id="filterTag" value="all">
          </div>
          <button class="btn btn-primary btn-criar" onclick="abrirModalCriar()">
            <i data-lucide="plus" width="14"></i>
            Criar Instância</button>
        </div>
      </div>
      <div class="section-body no-pad">
        <div class="inst-list" id="instList">
          <div class="empty">
            <div class="spin"><i data-lucide="loader-2" width="32"></i></div> Carregando instâncias...
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="tabUsuarios" style="display:none">
    <div class="section">
      <div class="section-head section-head-responsive">
        <div class="section-title"><i data-lucide="users" width="16"></i> Usuários do Painel</div>
        <div class="section-actions">
          <button class="btn btn-primary" onclick="abrirModalNovoUsuario()"><i data-lucide="plus" width="14"></i>
            Novo Usuário</button>
        </div>
      </div>
      <div class="section-body" id="userListContainer" style="padding: 18px;">
        <div class="empty"><i data-lucide="loader" class="spin" width="32"></i> Carregando...</div>
      </div>
    </div>
  </div>

  <div id="tabLogs" style="display:none">
    <div class="section">
      <div class="section-head section-head-responsive">
        <div class="section-title"><i data-lucide="terminal" width="16"></i> Terminal de Logs</div>
        <div class="section-actions">
          <button class="btn btn-ghost" onclick="carregarLogs()"><i data-lucide="refresh-cw" width="14"></i>
            Atualizar Terminal</button>
        </div>
      </div>
      <div class="section-body no-pad">
        <div class="log-list" id="logList">
          <div class="empty"><i data-lucide="loader" class="spin" width="32"></i> Inicializando terminal...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ABA: SISTEMA DE ERROS -->
  <div id="tabErros" style="display:none">
    <div class="section">
      <div class="section-head section-head-responsive" style="border-bottom-color: rgba(248,113,113,0.3);">
        <div class="section-title" style="color:var(--red); text-shadow:0 0 10px rgba(248,113,113,0.3);">
          <i data-lucide="alert-triangle" width="16"></i> Logs de Exceções & Falhas do Sistema
        </div>
        <div class="section-actions">
          <button class="btn btn-danger" onclick="carregarErrosSistema()"><i data-lucide="refresh-cw" width="14"></i>
            ATUALIZAR DETECTOR</button>
        </div>
      </div>
      <div class="section-body no-pad" style="background:#0b0505;">
        <div class="log-list" id="sysErrorList">
          <div class="empty" style="color:var(--red);"><i data-lucide="loader-2" class="spin" width="32"></i> Conectando
            ao Banco de Dados...</div>
        </div>
      </div>
    </div>
  </div>

</div>
</main>
<!-- ═══ MODAL RENOMEAR INSTÂNCIA ═══ -->
<div class="modal-overlay" id="modalRenomear">
  <div class="modal modal-anim" style="max-width:380px;">
    <button class="modal-close" onclick="closeModal('modalRenomear')"><i data-lucide="x" width="18"></i></button>
    <h3><i data-lucide="pencil" width="18" style="color:var(--yellow);"></i> Editar Instância</h3>
    <input type="hidden" id="renomearInstToken">
    <div class="form-group" style="margin-top:10px;">
      <label class="form-label">Nome atual</label>
      <input class="form-input" id="renomearInstNomeAtual" readonly style="opacity:0.5; cursor:default;">
    </div>
    <div class="form-group">
      <label class="form-label">Novo nome / alias</label>
      <input class="form-input" id="renomearInstNovoNome" placeholder="Ex: Vendas_Principal" autofocus>
      <div style="font-size:10px; color:var(--muted); margin-top:5px;">Altera o display name da instância na API e no
        banco.</div>
    </div>
    <div style="display:flex; gap:10px; margin-top:20px;">
      <button class="btn btn-cancel" onclick="closeModal('modalRenomear')" style="flex:1;"><i data-lucide="x"
          width="14"></i> Cancelar</button>
      <button class="btn btn-card-yellow" onclick="window.salvarRenomear()"
        style="flex:1; background:rgba(234,179,8,0.15); border-color:rgba(234,179,8,0.3); color:var(--yellow);"><i
          data-lucide="save" width="14"></i> Salvar</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
<script src="/wazio/public/js/admin-dashboard.js?v=<?= time() ?>"></script>

<script>
  // OVERRIDES PARA GARANTIR QR + LOOP (evita cache de versão antiga)
  window.mostrarQR = function (b64, token = null) {
    if (token) currentQRToken = token;
    clearInterval(qrInterval);
    const qrWrap = document.getElementById('qrWrap');
    const timerCont = document.getElementById('qrTimerContainer');
    if (!qrWrap) return;

    const oldImg = document.getElementById('qrImg');
    const doRender = (code) => {
      qrWrap.innerHTML = `
            <div class="laser-line"></div>
            <img id="qrImg" class="qr-fade-out" src="data:image/png;base64,${code}" alt="QR" style="width:220px; border-radius:12px; border:2px solid var(--border); box-shadow:0 0 30px rgba(0,0,0,0.5); display:block; margin: 0 auto;"/>
        `;
      setTimeout(() => { const img = document.getElementById('qrImg'); if (img) img.classList.remove('qr-fade-out'); }, 50);
      if (timerCont) timerCont.innerHTML = `<div id="qrTimer">EXPIRA EM 30s</div>`;
      const title = document.getElementById('qrModalTitle');
      if (title) title.innerHTML = '<i data-lucide="qr-code" width="18"></i> ESCANEADOR DE QRCODE';
      if (window.lucide) lucide.createIcons();
      openModal('modalQR');
      window.iniciarLoopQR(currentQRToken);
    };
    if (oldImg) { oldImg.classList.add('qr-fade-out'); setTimeout(() => doRender(b64), 400); }
    else { doRender(b64); }
  }

  window.iniciarLoopQR = function (token) {
    clearInterval(qrInterval);
    let tempo = 30;
    const updateTimerText = (t) => { const el = document.getElementById('qrTimer'); if (el) el.textContent = `EXPIRA EM ${t}s`; };
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
        const el = document.getElementById('qrTimer');
        if (el) el.textContent = 'GERANDO NOVO QR...';
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

  // Reinit quando filtros mudam
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof filtrarPainel === 'function') filtrarPainel();
    if (typeof renderKPIs === 'function' && typeof todasInstancias !== 'undefined') renderKPIs(todasInstancias);
  });
</script>

<!-- ⚙️ MODAL CONFIGURAÇÃO NOTIFICAÇÕES -->
<div id="modalNotifSettings" class="modal-overlay">
  <div class="modal card neon-border-blue" style="max-width:450px;">
    <div class="modal-header">
      <div class="title-with-icon">
        <i data-lucide="bell-ring" width="18" style="color:var(--blue)"></i>
        <span>CONFIGURAR ALERTAS</span>
      </div>
      <button class="modal-close" onclick="closeModal('modalNotifSettings')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Alertas Push (Telegram/N8N)</label>
        <label class="toggle-switch">
          <input type="checkbox" id="checkPushNotif" checked>
          <span class="toggle-slider"></span>
        </label>
        <div style="font-size:11px; color:var(--muted); margin-top:5px;">Notificar quando instâncias desconectarem.
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Alertas de Proxy</label>
        <label class="toggle-switch">
          <input type="checkbox" id="checkProxyNotif" checked>
          <span class="toggle-slider"></span>
        </label>
        <div style="font-size:11px; color:var(--muted); margin-top:5px;">Notificar se a proxy ficar offline.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-cancel" onclick="closeModal('modalNotifSettings')">Cancelar</button>
      <button class="btn btn-info" onclick="salvarConfigNotif()">
        <i data-lucide="save" width="16"></i> SALVAR CONFIGS
      </button>
    </div>
  </div>
</div>

<style>
  .notif-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 300px;
    background: #111612;
    border: 1px solid #1f2d24;
    border-radius: 12px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.9), inset 0 1px 0 rgba(255, 255, 255, 0.05);
    z-index: 9999;
    display: none;
    opacity: 0;
    transform: translateY(-5px);
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    flex-direction: column;
    overflow: hidden;
  }

  .notif-dropdown.show {
    display: flex;
    opacity: 1;
    transform: translateY(0);
  }

  .notif-header {
    padding: 12px 16px;
    border-bottom: 1px solid #1f2d24;
    background: rgba(255, 255, 255, 0.02);
  }

  .notif-list-dropdown {
    max-height: 350px;
    overflow-y: auto;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .notif-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 10px;
    transition: transform 0.2s, background 0.2s;
    cursor: default;
  }

  .notif-item:hover {
    background: rgba(188, 253, 73, 0.05);
    border-color: rgba(188, 253, 73, 0.2);
  }

  .notif-item.unread {
    border-left: 2px solid var(--green);
  }

  .notif-item-time {
    font-size: 9px;
    color: var(--muted);
    font-family: var(--mono);
    margin-bottom: 3px;
  }

  .notif-item-title {
    font-weight: 700;
    font-size: 11px;
    margin-bottom: 2px;
    text-transform: uppercase;
  }

  .notif-item-desc {
    font-size: 10px;
    color: var(--muted);
    line-height: 1.4;
  }
</style>

<!-- ═══════ MODAIS DO SISTEMA ═══════ -->

<div class="modal-overlay" id="modalConfirm">
  <div class="modal modal-anim" style="max-width:360px; text-align:center;">
    <button class="modal-close" onclick="closeModal('modalConfirm')"><i data-lucide="x" width="18"></i></button>
    <h3 id="confirmTitle"
      style="justify-content:center; color:var(--red); text-shadow: 0 0 10px rgba(248,113,113,0.5);">Atenção</h3>
    <div id="confirmMsg"
      style="margin:20px 0; font-family:var(--mono); color:var(--muted); font-size:13px; line-height:1.5;"></div>
    <div style="display:flex; gap:10px; margin-top:25px;">
      <button class="btn btn-cancel" onclick="closeModal('modalConfirm')" style="flex:1;">Cancelar</button>
      <button class="btn btn-success" onclick="executarConfirm()" style="flex:1;">Continuar</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalQR">
  <div class="modal modal-anim" style="max-width:340px; text-align:center; padding: 25px;">
    <button class="modal-close" onclick="closeModal('modalQR')"><i data-lucide="x" width="18"></i></button>
    <h3 id="qrModalTitle" style="text-transform:uppercase; margin-bottom:20px; color:var(--green);">
      <i data-lucide="qr-code" width="18"></i> ESCANEADOR DE QRCODE
    </h3>
    <div class="qr-wrap" id="qrWrap">
      <div class="laser-line"></div>
    </div>
    <div id="qrTimerContainer"
      style="margin-top:15px; min-height:40px; display:flex; align-items:center; justify-content:center; width:100%; border-top:1px solid rgba(188,253,73,0.1); padding-top:10px;">
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalToken">
  <div class="modal modal-anim" style="max-width:360px; text-align:center;">
    <button class="modal-close" onclick="closeModal('modalToken')"><i data-lucide="x" width="18"></i></button>
    <h3><i data-lucide="key" width="18" style="color:var(--purple);"></i> CREDENCIAL — API</h3>
    <p style="font-size:11px; color:var(--muted); margin-bottom:15px; font-family:var(--font-ui);">Token único desta
      instância.</p>
    <div class="form-group">
      <input type="text" class="form-input" id="instTokenDisplay" readonly
        style="font-family:var(--mono); color:var(--purple); text-align:center; background:#0b0710 !important; border-color:rgba(168,85,247,.3) !important;">
    </div>
    <div style="display:flex; justify-content:center; gap:10px; margin-top:20px;">
      <button class="btn btn-card-purple" onclick="window.copiarTokenDisplay()" style="flex:1;"><i data-lucide="copy"
          width="14"></i> Copiar Token</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalCriar">
  <div class="modal modal-anim">
    <button class="modal-close" onclick="closeModal('modalCriar')"><i data-lucide="x" width="18"></i></button>
    <h3><i data-lucide="plus-circle" width="18"></i> Nova Instância</h3>
    <div class="form-group"><label class="form-label">Nome da Instância</label><input class="form-input" id="criarNome"
        placeholder="ex: 01_Atendimento"></div>
    <div class="form-group"><label class="form-label">Webhook URL (opcional)</label><input class="form-input"
        id="criarWebhook" placeholder="https://meu-n8n.com/webhook/..."></div>
    <div style="display:flex; gap:10px; margin-top:20px;">
      <button class="btn btn-cancel" onclick="closeModal('modalCriar')"><i data-lucide="x" width="14"></i>
        Cancelar</button>
      <button class="btn btn-primary" onclick="criarInstancia()" style="flex:1;"><i data-lucide="check" width="14"></i>
        Criar Instância</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalTag">
  <div class="modal modal-anim" style="max-width:320px; overflow:visible;">
    <button class="modal-close" onclick="closeModal('modalTag')"><i data-lucide="x" width="18"></i></button>
    <h3 style="text-transform:uppercase; font-family:var(--display); letter-spacing:0.1em;"><i data-lucide="tag"
        width="18"></i> Definir Etiqueta</h3>
    <div class="form-group">
      <label class="form-label">Selecione a Categoria</label>
      <div class="custom-select-container">
        <select id="selectTagDef" class="form-select">
          <option value="">Nenhuma Etiqueta</option>
          <option value="Campanha Ads">Campanha Ads</option>
          <option value="Gerar Boletos">Gerar Boletos</option>
          <option value="Administrador">Administrador</option>
          <option value="Criador de Grupos">Criador de Grupos</option>
        </select>
      </div>
    </div>
    <div style="display:flex; gap:10px; margin-top:20px;">
      <button class="btn btn-cancel" onclick="closeModal('modalTag')"><i data-lucide="x" width="14"></i>
        Cancelar</button>
      <button class="btn btn-primary" onclick="salvarTag()" style="flex:1;"><i data-lucide="check" width="14"></i>
        Salvar</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalWebhook">
  <div class="modal modal-anim" style="max-width:540px;">
    <button class="modal-close" onclick="closeModal('modalWebhook')"><i data-lucide="x" width="18"></i></button>
    <h3 id="webhookModalTitle" style="text-transform:uppercase; font-family:var(--display); letter-spacing:0.1em;"><i
        data-lucide="zap" width="18" style="color:var(--blue);"></i> CONFIGURAR WEBHOOK</h3>
    <div id="whContent" style="margin-top:20px;">
      <!-- JavaScript will inject the webhook form here -->
    </div>
    <div style="display:flex; gap:10px; margin-top:20px;">
      <button class="btn btn-cancel" onclick="closeModal('modalWebhook')"><i data-lucide="x" width="14"></i>
        Cancelar</button>
      <button class="btn btn-info" onclick="window.testarWebhookBtn()" style="flex:1;"><i data-lucide="activity"
          width="14"></i> Testar Webhook</button>
      <button class="btn btn-success" onclick="window.salvarWebhook()" style="flex:1;"><i data-lucide="save"
          width="14"></i> Salvar</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalProxy">
  <div class="modal modal-anim" style="max-width:440px;">
    <button class="modal-close" onclick="closeModal('modalProxy')"><i data-lucide="x" width="18"></i></button>
    <h3 style="text-transform:uppercase; font-family:var(--display); letter-spacing:0.1em;"><i data-lucide="router"
        width="18" style="color:var(--teal);"></i> CONFIGURAR PROXY</h3>
    <div class="form-group">
      <label class="form-label" style="font-size:10px; color:var(--muted);">Cole o URI completo ou preencha os campos
        abaixo</label>
      <input class="form-input" id="proxyHost" placeholder="socks5://user:pass@host:port ou só host">
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
      <div class="form-group"><label class="form-label">Porta</label><input class="form-input" id="proxyPort"
          placeholder="1080"></div>
      <div class="form-group">
        <label class="form-label">Protocolo</label>
        <select class="form-select" id="proxyProtocol">
          <option value="socks5">SOCKS5</option>
          <option value="http">HTTP</option>
          <option value="https">HTTPS</option>
        </select>
      </div>
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
      <div class="form-group"><label class="form-label">Usuário</label><input class="form-input" id="proxyUser"
          placeholder="user"></div>
      <div class="form-group"><label class="form-label">Senha</label><input class="form-input" id="proxyPass"
          placeholder="password" type="password"></div>
    </div>
    <div style="display:flex; gap:10px; margin-top:20px;">
      <button class="btn btn-cancel" onclick="closeModal('modalProxy')"><i data-lucide="x" width="14"></i>
        Cancelar</button>
      <button class="btn" style="background:rgba(248,113,113,.15); border-color:rgba(248,113,113,.3); color:var(--red);"
        onclick="window.removerProxy()"><i data-lucide="trash-2" width="14"></i> Remover</button>
      <button class="btn btn-success" onclick="window.salvarProxy()" style="flex:1;"><i data-lucide="save"
          width="14"></i> Salvar Proxy</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalPerfil">
  <div class="modal modal-anim" style="max-width:440px; text-align:center;">
    <button class="modal-close" onclick="closeModal('modalPerfil')"><i data-lucide="x" width="18"></i></button>
    <h3 style="justify-content:center; text-transform:uppercase; letter-spacing:0.1em; font-family:var(--display);"><i
        data-lucide="user" width="18"></i> Perfil da Instância</h3>
    <div id="perfilContent" style="display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0;">
      <div class="spin" style="color:var(--green);"><i data-lucide="loader-2" width="32"></i></div>
      <img id="perfilPreview" src="" alt="Perfil"
        style="display:none; width:90px; height:90px; border-radius:50%; object-fit:cover; border:2px solid var(--border); box-shadow:0 0 20px rgba(0,0,0,0.5);">
    </div>
    <div style="display:flex; gap:10px; margin-top:20px; flex-direction:column; text-align: left;">
      <div class="form-group">
        <label class="form-label" style="color:var(--muted); font-size:10px;">ID DA INSTÂNCIA (ID LOCAL)</label>
        <input class="form-input" id="perfilInstanciaId" readonly
          style="background:rgba(0,0,0,0.3) !important; color:var(--muted) !important; border-color:var(--border) !important; font-family:var(--mono); font-size:11px;">
      </div>
      <div class="form-group">
        <label class="form-label">Nome do Perfil (WhatsApp)</label>
        <input class="form-input" id="perfilNome" placeholder="Nome de exibição">
      </div>
      <div class="form-group">
        <label class="form-label">Status/Recado (Bio)</label>
        <input class="form-input" id="perfilStatus" placeholder="Meu status...">
      </div>
      <button class="btn btn-primary" onclick="window.salvarPerfil()" style="width:100%; margin-top:10px; height:42px;">
        <i data-lucide="save" width="14"></i> SALVAR PERFIL
      </button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalUsuario">
  <div class="modal modal-anim" style="max-width:440px;">
    <button class="modal-close" onclick="closeModal('modalUsuario')"><i data-lucide="x" width="18"></i></button>
    <h3 id="modalUsuarioTitle"><i data-lucide="user-plus" width="18"></i> Novo Usuário</h3>
    <input type="hidden" id="editUserId" value="">
    <div class="form-group"><label class="form-label">Nome</label><input class="form-input" id="userNome"
        placeholder="Nome completo"></div>
    <div class="form-group"><label class="form-label">Email</label><input class="form-input" id="userEmail"
        placeholder="email@exemplo.com" type="email"></div>
    <div class="form-group"><label class="form-label">Usuário</label><input class="form-input" id="userUsername"
        placeholder="usuario123"></div>
    <div class="form-group"><label class="form-label">Senha <span style="color:var(--muted); font-size:10px;">(deixe em
          branco para manter)</span></label><input class="form-input" id="userSenha" placeholder="••••••••"
        type="password"></div>
    <div class="form-group">
      <label class="form-label">Função</label>
      <select class="form-select" id="userRole">
        <option value="user">Usuário</option>
        <option value="admin">Administrador</option>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Limite de Instâncias</label><input class="form-input"
        id="userLimit" type="number" value="3" min="1" max="999"></div>
    <div style="display:flex; gap:10px; margin-top:20px;">
      <button class="btn btn-cancel" onclick="closeModal('modalUsuario')"><i data-lucide="x" width="14"></i>
        Cancelar</button>
      <button class="btn btn-primary" onclick="window.salvarUsuario()" style="flex:1;"><i data-lucide="check"
          width="14"></i> Salvar</button>
    </div>
  </div>
</div>

<style>
  /* QR styles */
  .qr-wrap {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(188, 253, 73, 0.03);
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.4);
    min-height: 260px;
    margin: 0 auto;
    overflow: hidden;
  }

  .laser-line {
    position: absolute;
    left: 10px;
    right: 10px;
    height: 2px;
    background: var(--green);
    box-shadow: 0 0 15px var(--green), 0 0 30px var(--green);
    z-index: 10;
    pointer-events: none;
    animation: laserScan 2s linear infinite;
  }

  @keyframes laserScan {
    0% {
      top: 0;
      opacity: 0;
    }

    10% {
      opacity: 1;
    }

    90% {
      opacity: 1;
    }

    100% {
      top: 100%;
      opacity: 0;
    }
  }

  #qrTimer {
    font-weight: 800;
    color: var(--green);
    font-size: 18px;
    font-family: var(--mono);
    text-shadow: 0 0 10px rgba(188, 253, 73, 0.2);
    display: block !important;
  }

  #qrImg {
    transition: opacity 0.4s ease-in-out, transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    opacity: 1;
  }

  .qr-fade-out {
    opacity: 0 !important;
    transform: scale(0.9);
  }

  .success-mark {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    animation: checkBounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
  }

  .success-circle {
    background: var(--green);
    border-radius: 50%;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 40px rgba(188, 253, 73, 0.5);
    margin-bottom: 20px;
  }

  .success-title {
    color: var(--green);
    font-family: var(--font-ui);
    font-weight: 800;
    font-size: 20px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    text-shadow: 0 0 20px rgba(188, 253, 73, 0.4);
  }

  @keyframes checkBounce {
    0% {
      transform: scale(0);
      opacity: 0;
    }

    100% {
      transform: scale(1);
      opacity: 1;
    }
  }

  /* Wifi popover */
  .wifi-popover {
    position: fixed;
    width: 260px;
    background: #0e1610;
    border: 1px solid rgba(188, 253, 73, 0.15);
    border-radius: 12px;
    padding: 14px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8);
    z-index: 99999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    transform: translateY(-5px);
  }

  .wifi-popover.show {
    opacity: 1;
    pointer-events: all;
    transform: translateY(0);
  }

  .popover-title {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--green);
    margin-bottom: 8px;
  }

  .popover-text {
    font-size: 11px;
    color: var(--muted);
    margin-bottom: 4px;
  }

  .popover-text span {
    color: var(--text);
    font-family: var(--mono);
  }

  .popover-divider {
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    margin: 10px 0;
  }
</style>