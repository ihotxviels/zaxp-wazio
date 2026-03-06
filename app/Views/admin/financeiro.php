<?php
require_once __DIR__ . '/../../../config.php';
$user = requer_login();
require_once __DIR__ . '/../layouts/financeiro_header.php';

if ($role !== 'admin' && !in_array('financeiro', $modulos_permitidos)) {
  header('Location: /wazio/dashboard');
  exit;
}
?>

<div class="topbar" style="padding: 10px 20px;">
  <div class="topbar-left">
    <button onclick="window.location.href='/wazio/dashboard'" class="btn btn-ghost"
      style="padding: 6px 10px; font-size: 14px; background:#080c09; border:1px solid var(--border); box-shadow: inset 0 2px 5px rgba(0,0,0,0.8);">
      <i data-lucide="arrow-left" width="16" height="16"></i>
    </button>
    <span class="topbar-title" id="topTitle" style="margin-left: 12px;">
      <i data-lucide="arrow-down-up" width="18" style="color:var(--green)"></i> WAZ.IO <span class="glow-text"
        style="color:var(--green); text-shadow: 0 0 10px rgba(188,253,73,0.6);">///</span> FINANCEIRO
    </span>
  </div>
  <div class="topbar-right">
    <button class="btn btn-ghost" onclick="carregarFinanceiro()"
      style="padding: 8px 16px; background:#080c09; border:1px solid var(--border); box-shadow: inset 0 2px 5px rgba(0,0,0,0.8);">
      <i data-lucide="refresh-cw" width="14"></i> ATUALIZAR
    </button>
    <button class="btn btn-primary" onclick="abrirModalTransacao()" style="padding: 8px 16px;">
      <i data-lucide="plus" width="14"></i> LANÇAMENTO
    </button>
  </div>
</div>

<div class="content">

  <style>
    /* ── KPIs FINANCEIRO ── */
    .fin-kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 12px;
      margin-bottom: 20px;
    }

    .fin-kpi {
      background: linear-gradient(180deg, #101710 0%, #070c08 100%);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px;
      position: relative;
      overflow: hidden;
      transition: 0.2s;
    }

    .fin-kpi:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, .5);
    }

    .fin-kpi::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: var(--kpi-clr, var(--green));
    }

    .fin-kpi-label {
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--muted);
      font-family: var(--font-ui);
      margin-bottom: 8px;
    }

    .fin-kpi-val {
      font-size: 22px;
      font-weight: 800;
      font-family: var(--mono);
      color: var(--kpi-clr, var(--green));
      text-shadow: 0 0 20px rgba(var(--kpi-rgb, 188 253 73) / .2);
    }

    .fin-kpi-icon {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      opacity: .12;
      color: var(--kpi-clr, var(--green));
    }

    /* ── CHART BOX ── */
    .fin-chart-section {
      background: linear-gradient(180deg, #0f1712 0%, #080c09 100%);
      border: 1px solid var(--border);
      border-radius: 12px;
      margin-bottom: 20px;
      overflow: hidden;
    }

    .fin-chart-header {
      padding: 14px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .fin-chart-title {
      font-family: var(--font-ui);
      font-size: 11px;
      font-weight: 800;
      color: var(--green);
      text-transform: uppercase;
      letter-spacing: .06em;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .fin-chart-body {
      padding: 20px;
      width: 100%;
      overflow-x: auto;
    }

    .fin-chart-body canvas {
      min-width: 600px;
    }

    /* ── TABS ── */
    .fin-view {
      display: none;
    }

    .fin-view.active {
      display: block;
    }

    /* Ajuste para Lucro no Mobile */
    @media (max-width: 600px) {
      .fin-kpi-grid {
        grid-template-columns: 1fr 1fr;
      }

      .fin-kpi.lucro-unico {
        grid-column: 1 / -1;
      }
    }

    /* ── TERMINAL STYLES ── */
    .fin-tabs {
      display: flex;
      gap: 6px;
      margin-bottom: 14px;
    }

    .fin-tab {
      flex: 1;
      padding: 9px 10px;
      background: linear-gradient(180deg, #141b17 0%, #080c09 100%);
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: var(--font-ui);
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      color: var(--muted);
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .fin-tab.active-in {
      border-color: rgba(188, 253, 73, .4);
      color: var(--green);
      background: linear-gradient(180deg, rgba(188, 253, 73, .08) 0%, rgba(188, 253, 73, .02) 100%);
      box-shadow: 0 0 12px rgba(188, 253, 73, .1);
    }

    .fin-tab.active-out {
      border-color: rgba(248, 113, 113, .4);
      color: var(--red);
      background: linear-gradient(180deg, rgba(248, 113, 113, .08) 0%, rgba(248, 113, 113, .02) 100%);
      box-shadow: 0 0 12px rgba(248, 113, 113, .1);
    }

    .fin-tab.active-all {
      border-color: rgba(56, 189, 248, .4);
      color: var(--blue);
      background: linear-gradient(180deg, rgba(56, 189, 248, .08) 0%, rgba(56, 189, 248, .02) 100%);
      box-shadow: 0 0 12px rgba(56, 189, 248, .1);
    }

    .fin-terminal {
      background: #050806;
      border: 1px solid var(--border);
      border-radius: 10px;
      overflow: hidden;
      box-shadow: inset 0 2px 10px rgba(0, 0, 0, .9), 0 10px 30px rgba(0, 0, 0, .6);
    }

    .fin-term-header {
      background: #0a0f0c;
      padding: 10px 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 1px solid var(--border);
    }

    .fin-term-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
    }

    .fin-term-dot.r {
      background: #ff5f56;
      box-shadow: 0 0 8px rgba(255, 95, 86, .5);
    }

    .fin-term-dot.y {
      background: #ffbd2e;
      box-shadow: 0 0 8px rgba(255, 189, 46, .5);
    }

    .fin-term-dot.g {
      background: #27c93f;
      box-shadow: 0 0 8px rgba(39, 201, 63, .5);
    }

    .fin-term-title {
      margin-left: 8px;
      color: #4a6650;
      font-size: 11px;
      font-weight: 700;
      font-family: var(--mono);
      flex: 1;
    }

    .fin-term-body {
      padding: 14px;
      max-height: 460px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .fin-row {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 11px 13px;
      background: linear-gradient(90deg, rgba(255, 255, 255, .02) 0%, transparent 100%);
      border: 1px solid var(--border);
      border-radius: 8px;
      transition: all .2s;
      animation: slideInRow .35s ease both;
    }

    .fin-row:hover {
      background: linear-gradient(90deg, rgba(255, 255, 255, .04) 0%, transparent 100%);
      transform: translateX(2px);
    }

    @keyframes slideInRow {
      from {
        opacity: 0;
        transform: translateX(-10px)
      }

      to {
        opacity: 1;
        transform: translateX(0)
      }
    }

    .fin-icon {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .fin-icon.in {
      background: rgba(188, 253, 73, .1);
      border: 1px solid rgba(188, 253, 73, .2);
      color: var(--green);
    }

    .fin-icon.out {
      background: rgba(248, 113, 113, .1);
      border: 1px solid rgba(248, 113, 113, .2);
      color: var(--red);
    }

    .fin-icon.pend {
      background: rgba(250, 204, 21, .1);
      border: 1px solid rgba(250, 204, 21, .2);
      color: var(--yellow);
    }

    .fin-desc {
      flex: 1;
      min-width: 0;
    }

    .fin-desc-title {
      font-family: var(--font-ui);
      font-size: 12px;
      font-weight: 700;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .fin-desc-meta {
      font-family: var(--mono);
      font-size: 10px;
      color: var(--muted);
      margin-top: 2px;
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
    }

    .fin-status-badge {
      display: inline-flex;
      align-items: center;
      padding: 1px 7px;
      border-radius: 12px;
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
    }

    .fin-status-badge.pago {
      background: rgba(188, 253, 73, .1);
      color: var(--green);
    }

    .fin-status-badge.pendente {
      background: rgba(250, 204, 21, .1);
      color: var(--yellow);
    }

    .fin-cat-badge {
      display: inline-flex;
      align-items: center;
      padding: 1px 7px;
      border-radius: 12px;
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      background: rgba(56, 189, 248, .1);
      color: var(--blue);
    }

    .fin-value {
      font-family: var(--mono);
      font-size: 14px;
      font-weight: 800;
      text-align: right;
      white-space: nowrap;
    }

    .fin-value.in {
      color: var(--green);
      text-shadow: 0 0 8px rgba(188, 253, 73, .3);
    }

    .fin-value.out {
      color: var(--red);
      text-shadow: 0 0 8px rgba(248, 113, 113, .3);
    }

    .fin-value.pend {
      color: var(--yellow);
      text-shadow: 0 0 8px rgba(250, 204, 21, .3);
    }

    .fin-actions {
      display: flex;
      gap: 4px;
      flex-shrink: 0;
    }

    .fin-btn-act {
      background: transparent;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 5px;
      border-radius: 4px;
      transition: .2s;
      display: flex;
      align-items: center;
    }

    .fin-btn-act:hover {
      background: rgba(255, 255, 255, .05);
      color: #fff;
    }

    .fin-btn-act.del:hover {
      color: var(--red);
      background: rgba(248, 113, 113, .1);
    }

    .fin-empty {
      padding: 40px;
      text-align: center;
      color: var(--muted);
      font-family: var(--font-ui);
      font-size: 13px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
    }

    select.form-select {
      width: 100%;
      background: linear-gradient(180deg, #141d16 0%, #030504 100%);
      border: 1px solid var(--border);
      border-radius: 6px;
      color: var(--green);
      font-size: 11px;
      font-weight: 700;
      padding: 0 10px;
      height: 34px;
      outline: none;
      font-family: var(--font-ui);
      text-transform: uppercase;
      cursor: pointer;
      appearance: none;
      -webkit-appearance: none;
    }

    /* ── TIPO TOGGLE (Entrada / Saída) ── */
    .tipo-toggle-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-bottom: 4px;
    }

    .tipo-toggle-btn {
      padding: 12px 8px;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: linear-gradient(180deg, #141d16 0%, #080c09 100%);
      color: var(--muted);
      font-family: var(--font-ui);
      font-size: 11px;
      font-weight: 800;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      transition: 0.2s;
      text-transform: uppercase;
    }

    .tipo-toggle-btn.active-entrada {
      border-color: rgba(188, 253, 73, .6);
      background: linear-gradient(180deg, rgba(188, 253, 73, .12) 0%, rgba(188, 253, 73, .04) 100%);
      color: var(--green);
      box-shadow: 0 0 20px rgba(188, 253, 73, .1);
    }

    .tipo-toggle-btn.active-saida {
      border-color: rgba(248, 113, 113, .6);
      background: linear-gradient(180deg, rgba(248, 113, 113, .12) 0%, rgba(248, 113, 113, .04) 100%);
      color: var(--red);
      box-shadow: 0 0 20px rgba(248, 113, 113, .1);
    }

    /* ── option-pill selectors ── */
    .opt-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 6px;
    }

    .opt-pill {
      padding: 5px 12px;
      border-radius: 20px;
      border: 1px solid var(--border);
      background: rgba(255, 255, 255, .03);
      font-family: var(--font-ui);
      font-size: 10px;
      font-weight: 800;
      color: var(--muted);
      cursor: pointer;
      transition: 0.15s;
      text-transform: uppercase;
    }

    .opt-pill:hover {
      border-color: rgba(188, 253, 73, .3);
      color: var(--text);
    }

    .opt-pill.selected {
      border-color: rgba(188, 253, 73, .6);
      background: rgba(188, 253, 73, .08);
      color: var(--green);
      box-shadow: 0 0 10px rgba(188, 253, 73, .1);
    }
  </style>

  <!-- ── DASHBOARD (KPIs + Chart) ── -->
  <div id="viewDashboard" class="fin-view active">

    <!-- KPIs -->
    <div class="fin-kpi-grid" id="kpiGrid">
      <div class="fin-kpi" style="--kpi-clr:var(--green);--kpi-rgb:188 253 73;">
        <div class="fin-kpi-label">Entradas (Mês)</div>
        <div class="fin-kpi-val" id="kpiReceitas">R$ 0,00</div>
        <div class="fin-kpi-icon"><i data-lucide="trending-up" width="42"></i></div>
      </div>
      <div class="fin-kpi" style="--kpi-clr:var(--red);--kpi-rgb:248 113 113;">
        <div class="fin-kpi-label">Tráfego Pago</div>
        <div class="fin-kpi-val" id="kpiTrafego">R$ 0,00</div>
        <div class="fin-kpi-icon"><i data-lucide="radio-tower" width="42"></i></div>
      </div>
      <div class="fin-kpi" style="--kpi-clr:#f97316;--kpi-rgb:249 115 22;">
        <div class="fin-kpi-label">Saídas (Mês)</div>
        <div class="fin-kpi-val" id="kpiDespesas">R$ 0,00</div>
        <div class="fin-kpi-icon"><i data-lucide="trending-down" width="42"></i></div>
      </div>
      <div class="fin-kpi" style="--kpi-clr:var(--yellow);--kpi-rgb:250 204 21;">
        <div class="fin-kpi-label">A Receber</div>
        <div class="fin-kpi-val" id="kpiPendente">R$ 0,00</div>
        <div class="fin-kpi-icon"><i data-lucide="clock" width="42"></i></div>
      </div>
      <div class="fin-kpi lucro-unico" style="--kpi-clr:var(--blue);--kpi-rgb:56 189 248;">
        <div class="fin-kpi-label">Lucro Líquido</div>
        <div class="fin-kpi-val" id="kpiSaldo">R$ 0,00</div>
        <div class="fin-kpi-icon"><i data-lucide="wallet" width="42"></i></div>
      </div>
    </div>

    <!-- Chart -->
    <div class="fin-chart-section">
      <div class="fin-chart-header">
        <div class="fin-chart-title">
          <i data-lucide="bar-chart-2" width="16"></i> Faturamento — Últimos 6 Meses
        </div>
        <div style="position:relative;">
          <button id="btnFilterPeriodo" class="btn btn-ghost" onclick="toggleFinFilter()"
            style="padding: 6px 12px; font-size: 11px; height: 28px; background: rgba(255,255,255,0.03); border:1px solid var(--border);">
            <i data-lucide="filter" width="12"></i> <span id="labelFiltro">MÊS TODO</span>
          </button>

          <div id="finFilterDropdown" class="filter-dropdown" style="width: 150px; right: 0; left: auto;">
            <div class="filter-opt" onclick="setPer('hoje', 'HOJE')">Hoje</div>
            <div class="filter-opt" onclick="setPer('ontem', 'ONTEM')">Ontem</div>
            <div class="filter-opt" onclick="setPer('7d', 'ÚLT. 7 DIAS')">Últimos 7 dias</div>
            <div class="filter-opt" onclick="setPer('mes', 'MÊS TODO')">Mês Todo</div>
            <div class="filter-opt" onclick="setPer('6m', '6 MESES')">6 Meses</div>
            <div class="filter-opt" onclick="setPer('12m', '12 MESES')">12 Meses</div>
            <div class="filter-opt" onclick="setPer('ws', 'TEMPO TODO')">Tempo Todo</div>
          </div>
          <input type="hidden" id="filterMes" value="mes">
        </div>
      </div>
      <div class="fin-chart-body">
        <canvas id="finChart" height="140"></canvas>
      </div>
    </div>

  </div><!-- end viewDashboard -->

  <!-- ── ATUALIZAÇÕES (todas) ── -->
  <div id="viewAtualizacoes" class="fin-view">
    <div class="fin-terminal">
      <div class="fin-term-header">
        <div class="fin-term-dot r"></div>
        <div class="fin-term-dot y"></div>
        <div class="fin-term-dot g"></div>
        <div class="fin-term-title">root@wazio:~# cat /var/log/financeiro.log</div>
        <button class="btn btn-primary" onclick="abrirModalTransacao()"
          style="height:26px;font-size:10px;padding:0 10px;"><i data-lucide="plus" width="11"></i> NOVO</button>
      </div>
      <div class="fin-term-body" id="listaAtualizacoes">
        <div class="fin-empty">
          <div class="spin"
            style="width:32px;height:32px;border:2px solid rgba(188,253,73,.2);border-top-color:var(--green);border-radius:50%;">
          </div>Carregando...
        </div>
      </div>
    </div>
  </div>

  <!-- ── ENTRADAS ── -->
  <div id="viewEntradas" class="fin-view">
    <div class="fin-terminal">
      <div class="fin-term-header">
        <div class="fin-term-dot r"></div>
        <div class="fin-term-dot y"></div>
        <div class="fin-term-dot g"></div>
        <div class="fin-term-title" style="color: var(--green);">root@wazio:~# grep "ENTRADA" financeiro.log</div>
        <button class="btn btn-primary" onclick="abrirModalTransacao('receita')"
          style="height:26px;font-size:10px;padding:0 10px;"><i data-lucide="plus" width="11"></i> NOVA ENTRADA</button>
      </div>
      <div class="fin-term-body" id="listaEntradas">
        <div class="fin-empty">
          <div class="spin"
            style="width:32px;height:32px;border:2px solid rgba(188,253,73,.2);border-top-color:var(--green);border-radius:50%;">
          </div>Carregando...
        </div>
      </div>
    </div>
  </div>

  <!-- ── SAÍDAS ── -->
  <div id="viewSaidas" class="fin-view">
    <div class="fin-terminal">
      <div class="fin-term-header">
        <div class="fin-term-dot r"></div>
        <div class="fin-term-dot y"></div>
        <div class="fin-term-dot g"></div>
        <div class="fin-term-title" style="color: var(--red);">root@wazio:~# grep "SAÍDA" financeiro.log</div>
        <button class="btn btn-primary" onclick="abrirModalTransacao('despesa')"
          style="height:26px;font-size:10px;padding:0 10px;"><i data-lucide="plus" width="11"></i> NOVA SAÍDA</button>
      </div>
      <div class="fin-term-body" id="listaSaidas">
        <div class="fin-empty">
          <div class="spin"
            style="width:32px;height:32px;border:2px solid rgba(248,113,113,.2);border-top-color:var(--red);border-radius:50%;">
          </div>Carregando...
        </div>
      </div>
    </div>
  </div>

</div><!-- end .content -->

<!-- MODAL TRANSAÇÃO REDESENHADO -->
<div class="modal-overlay" id="modalTransacao">
  <div class="modal modal-anim" style="max-width:420px;">
    <button class="modal-close" onclick="closeModal('modalTransacao')"><i data-lucide="x" width="18"></i></button>
    <h3 id="modalTransacaoTitle"><i data-lucide="dollar-sign" width="18"></i> Lançamento</h3>
    <input type="hidden" id="transacaoId">

    <!-- TIPO: Entrada / Saída com ícones -->
    <div class="form-group">
      <label class="form-label">Tipo</label>
      <div class="tipo-toggle-group">
        <button type="button" class="tipo-toggle-btn active-entrada" id="btnTipoEntrada"
          onclick="selecionarTipo('receita')">
          <i data-lucide="arrow-down-to-line" width="22"></i> ENTRADA
        </button>
        <button type="button" class="tipo-toggle-btn" id="btnTipoSaida" onclick="selecionarTipo('despesa')">
          <i data-lucide="arrow-up-from-line" width="22"></i> SAÍDA
        </button>
      </div>
      <input type="hidden" id="transacaoTipo" value="receita">
    </div>

    <!-- CATEGORIA (condicional por tipo) -->
    <div class="form-group">
      <label class="form-label" id="labelCategoria">Categoria</label>
      <div class="opt-pills" id="pillsCategoria"></div>
      <input type="hidden" id="transacaoCategoria" value="">
    </div>

    <div class="form-group">
      <label class="form-label">Descrição</label>
      <input class="form-input" id="transacaoDesc" placeholder="Ex: Pagamento Cliente X">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Valor (R$)</label>
        <input type="text" class="form-input" id="transacaoValor" placeholder="0,00" inputmode="numeric">
      </div>
      <div class="form-group">
        <label class="form-label">Data</label>
        <input type="date" class="form-input" id="transacaoData" style="color-scheme: dark;">
      </div>
    </div>

    <!-- STATUS via pills verdes -->
    <div class="form-group">
      <label class="form-label">Status</label>
      <div class="opt-pills" id="pillsStatus">
        <div class="opt-pill selected" data-val="pago" onclick="selecionarPill(this, 'transacaoStatus', 'pillsStatus')">
          ✓ PAGO / RECEBIDO</div>
        <div class="opt-pill" data-val="pendente" onclick="selecionarPill(this, 'transacaoStatus', 'pillsStatus')">⏳
          PENDENTE</div>
      </div>
      <input type="hidden" id="transacaoStatus" value="pago">
    </div>

    <div style="display:flex;gap:10px;margin-top:20px;">
      <button class="btn btn-cancel" onclick="closeModal('modalTransacao')"><i data-lucide="x" width="14"></i>
        Cancelar</button>
      <button class="btn btn-primary" onclick="salvarTransacao()" style="flex:1;"><i data-lucide="save" width="14"></i>
        Salvar Lançamento</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    // Dropdown de filtro clique fora
    document.addEventListener('click', (e) => {
      const dp = document.getElementById('finFilterDropdown');
      const btn = document.getElementById('btnFilterPeriodo');
      if (dp && dp.classList.contains('show') && !dp.contains(e.target) && !btn.contains(e.target)) {
        dp.classList.remove('show');
      }
    });
  });

  function toggleFinFilter() {
    document.getElementById('finFilterDropdown').classList.toggle('show');
  }

  window.setPer = function (val, text) {
    document.getElementById('labelFiltro').innerText = text;
    document.getElementById('filterMes').value = val;
    document.getElementById('finFilterDropdown').classList.remove('show');
    carregarFinanceiro();
  };

  function toggleMobileMenu(forceClose = false) {
    const menu = document.getElementById('mobDropdown');
    const overlay = document.getElementById('mainOverlay');
    if (!menu) return;
    const isOpen = menu.classList.contains('open') && !forceClose;
    if (isOpen || forceClose) {
      menu.classList.remove('open');
      overlay && overlay.classList.remove('show');
    } else {
      menu.classList.add('open');
      overlay && overlay.classList.add('show');
    }
  }

  // ── CATEGORIAS POR TIPO ──
  const CATEGORIAS = {
    receita: ['PLATAFORMA', 'SAQUES', 'OUTROS'],
    despesa: ['TRÁFEGO PAGO', 'FERRAMENTAS', 'CHIPS', 'OUTROS']
  };

  window.selecionarTipo = function (tipo) {
    document.getElementById('transacaoTipo').value = tipo;
    document.getElementById('btnTipoEntrada').className = 'tipo-toggle-btn' + (tipo === 'receita' ? ' active-entrada' : '');
    document.getElementById('btnTipoSaida').className = 'tipo-toggle-btn' + (tipo === 'despesa' ? ' active-saida' : '');
    renderPillsCategoria(tipo);
    if (typeof lucide !== 'undefined') lucide.createIcons();
  };

  window.renderPillsCategoria = function (tipo) {
    const container = document.getElementById('pillsCategoria');
    const categorias = CATEGORIAS[tipo] || [];
    container.innerHTML = categorias.map((c, i) =>
      `<div class="opt-pill${i === 0 ? ' selected' : ''}" data-val="${c}" onclick="selecionarPill(this, 'transacaoCategoria', 'pillsCategoria')">${c}</div>`
    ).join('');
    document.getElementById('transacaoCategoria').value = categorias[0] || '';
  };

  window.selecionarPill = function (el, hiddenId, groupId) {
    document.querySelectorAll(`#${groupId} .opt-pill`).forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById(hiddenId).value = el.dataset.val;
  };

  // ── MÁSCARA DE MOEDA ──
  document.addEventListener('DOMContentLoaded', () => {
    const valorInput = document.getElementById('transacaoValor');
    if (valorInput) {
      valorInput.addEventListener('input', function (e) {
        let val = this.value.replace(/\D/g, '');
        if (!val) { this.value = ''; return; }
        val = (parseInt(val, 10) / 100).toFixed(2);
        this.value = val.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      });
    }
  });

  // ── VIEW SWITCHER (chamado pela sidebar) ──
  window.mudarView = function (view, navEl) {
    // oculta todas as views
    document.querySelectorAll('.fin-view').forEach(v => v.classList.remove('active'));
    // ativa a view alvo
    const el = document.getElementById('view' + view);
    if (el) el.classList.add('active');

    // atualiza título
    const titles = {
      Dashboard: 'FINANCEIRO',
      Atualizacoes: 'ATUALIZAÇÕES',
      Entradas: 'ENTRADAS',
      Saidas: 'SAÍDAS'
    };
    document.getElementById('topTitleSuffix').textContent = titles[view] || 'FINANCEIRO';

    // sync sidebar
    document.querySelectorAll('.sidebar .nav-item').forEach(n => n.classList.remove('active'));
    if (navEl) navEl.classList.add('active');

    // re-render listas se necessário
    if (view !== 'Dashboard') renderTabela();
    if (window.lucide) lucide.createIcons();
  };

  // Compatibilidade com o header antigo
  window.setFinTabSide = function (tipo, navEl) {
    const map = { all: 'Atualizacoes', receita: 'Entradas', despesa: 'Saidas' };
    window.mudarView(map[tipo] || 'Dashboard', navEl);
  };
</script>
<script src="/wazio/public/js/financeiro.js?v=<?= time() ?>"></script>
</body>

</html>