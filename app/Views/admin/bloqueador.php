<?php require_once __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="topbar">
    <div class="topbar-left">
        <span class="topbar-title">
            <i data-lucide="shield-ban" width="18" style="color:var(--green)"></i> ATENDIMENTO <span class="glow-text"
                style="color:var(--green);">///</span> BLOQUEADOR INTELIGENTE
        </span>
    </div>
</div>

<div class="content">
    <div class="section">
        <div class="section-head">
            <div class="section-title">Proteção & Filtro de Spam (Shield)</div>
            <div class="section-actions">
                <button class="btn btn-ghost"><i data-lucide="history" width="14"></i> Logs de Bloqueio</button>
            </div>
        </div>
        <div class="section-body" style="padding:40px; text-align:center;">
            <p style="color:var(--muted); max-width:800px; margin:0 auto 30px; line-height:1.6;">
                Gerencie listas negras, bloqueie contatos por comportamento ou etiqueta e
                configure regras automáticas de proteção para suas instâncias.
            </p>

            <div class="shield-display" style="margin-bottom:40px; position:relative; display:inline-block;">
                <div class="shield-aura"></div>
                <i data-lucide="shield-check" width="80"
                    style="color:var(--green); filter:drop-shadow(0 0 20px rgba(188,253,73,0.4));"></i>
            </div>

            <div class="grid-tools"
                style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; text-align:left;">
                <div class="card-glass">
                    <div class="c-icon"><i data-lucide="ban" width="20"></i></div>
                    <h5>Blacklist Global</h5>
                    <p>Números bloqueados em todas as instâncias simultaneamente.</p>
                    <button class="btn btn-ghost btn-sm" style="margin-top:10px;">GERENCIAR</button>
                </div>
                <div class="card-glass">
                    <div class="c-icon"><i data-lucide="tag" width="20"></i></div>
                    <h5>Filtro por Etiqueta</h5>
                    <p>Bloqueio automático baseado em tags do sistema ou N8N.</p>
                    <button class="btn btn-ghost btn-sm" style="margin-top:10px;">CONFIGURAR</button>
                </div>
                <div class="card-glass">
                    <div class="c-icon"><i data-lucide="webhook" width="20"></i></div>
                    <h5>Regras Webhook</h5>
                    <p>Integração total para disparar ações de bloqueio via API.</p>
                    <button class="btn btn-ghost btn-sm" style="margin-top:10px;">ATIVAR</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .shield-aura {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 120px;
        height: 120px;
        background: radial-gradient(circle, rgba(188, 253, 73, 0.1) 0%, transparent 70%);
        transform: translate(-50%, -50%);
        animation: pulse-shield 3s infinite;
    }

    @keyframes pulse-shield {

        0%,
        100% {
            transform: translate(-50%, -50%) scale(1);
            opacity: 0.5;
        }

        50% {
            transform: translate(-50%, -50%) scale(1.5);
            opacity: 0.2;
        }
    }

    .card-glass {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border);
        padding: 25px;
        border-radius: 16px;
        transition: all 0.3s ease;
    }

    .card-glass:hover {
        border-color: var(--green);
        transform: translateY(-5px);
    }

    .c-icon {
        color: var(--green);
        margin-bottom: 15px;
    }

    .card-glass h5 {
        color: #fff;
        font-size: 15px;
        margin-bottom: 8px;
    }

    .card-glass p {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.5;
    }
</style>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>