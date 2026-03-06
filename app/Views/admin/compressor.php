<?php
header("Location: https://www.comprimirvideo.com.br/");
exit;
?>
<?php require_once __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="topbar">
    <div class="topbar-left">
        <span class="topbar-title">
            <i data-lucide="minimize" width="18" style="color:var(--green)"></i> FERRAMENTAS <span class="glow-text"
                style="color:var(--green);">///</span> COMPRESSOR DE VÍDEO
        </span>
    </div>
</div>

<div class="content">
    <div class="section">
        <div class="section-head">
            <div class="section-title">Otimização de Mídia (WhatsApp Ready)</div>
        </div>
        <div class="section-body" style="padding:40px; text-align:center;">
            <p style="color:var(--muted); max-width:800px; margin:0 auto 30px; line-height:1.6;">
                Reduza o tamanho dos seus vídeos sem perda perceptível de qualidade.<br>
                Fomos redirecionados para a ferramenta especializada externa para ter ganho na performance operacional
                da máquina.
            </p>

            <button class="btn btn-primary" onclick="window.open('https://www.comprimirvideo.com.br/', '_blank')"
                style="margin-top:40px; padding: 15px 40px; font-size:16px; box-shadow: 0 0 20px rgba(188,253,73,0.3); color: #000;">
                <i data-lucide="external-link" width="18"></i> ACESSAR FERRAMENTA
            </button>
        </div>
    </div>
</div>

<style>
    .btn-primary:hover {
        background: linear-gradient(180deg, #bcfd49 0%, #a2d149 100%) !important;
        box-shadow: 0 0 20px rgba(188, 253, 73, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.5) !important;
        transform: translateY(-2px);
    }
</style>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>