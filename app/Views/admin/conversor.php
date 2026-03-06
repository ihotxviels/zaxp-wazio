<?php
header("Location: https://www.freeconvert.com/pt");
exit;
?>
<?php require_once __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="topbar">
    <div class="topbar-left">
        <span class="topbar-title">
            <i data-lucide="repeat" width="18" style="color:var(--green)"></i> FERRAMENTAS <span class="glow-text"
                style="color:var(--green);">///</span> CONVERSOR UNIVERSAL
        </span>
    </div>
</div>

<div class="content">
    <div class="section">
        <div class="section-head">
            <div class="section-title">Transcodificação de Formatos (High Speed)</div>
        </div>
        <div class="section-body" style="padding:40px; text-align:center;">
            <p style="color:var(--muted); max-width:800px; margin:0 auto 30px; line-height:1.6;">
                Converta seus arquivos de mídia instantaneamente. Suporte total para
                **MOV para MP4, OGG/WAV para MP3, OPUS para MP3** e muito mais.
            </p>

            <div id="dropZoneConv" class="drop-zone-premium">
                <div class="metal-shine"></div>
                <i data-lucide="file-video" width="48" class="icon-glow"></i>
                <h3 class="drop-title">Arraste seus arquivos para converter</h3>
                <p class="drop-info">Multi-formato: MOV, OGG, WAV, OPUS, WEBM, FLAC...</p>
                <input type="file" id="convInput" multiple style="display:none;">
            </div>

            <div class="conv-controls"
                style="margin-top:30px; display:flex; justify-content:center; align-items:center; gap:20px;">
                <div class="control-box">
                    <label>CONVERTER PARA:</label>
                    <div class="format-chips">
                        <button class="f-chip active">MP4 (Vídeo)</button>
                        <button class="f-chip">MP3 (Áudio)</button>
                        <button class="f-chip">OGG (WhatsApp)</button>
                    </div>
                </div>
            </div>

            <div id="fileQueue"
                style="display:none; margin-top:30px; text-align:left; max-width:600px; margin-left:auto; margin-right:auto;">
                <div class="file-item-metal">
                    <div class="f-info">
                        <i data-lucide="file" width="14"></i>
                        <span>video_01.mov</span>
                    </div>
                    <div class="f-status">AGUARDANDO...</div>
                </div>
            </div>

            <button class="btn btn-primary" onclick="window.open('https://www.freeconvert.com/pt', '_blank')"
                style="margin-top:30px; width:250px; padding:12px; color: #000; font-weight: bold;">
                <i data-lucide="external-link" width="16"></i> ACESSAR CONVERSOR
            </button>
        </div>
    </div>
</div>

<style>
    .format-chips {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .f-chip {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border);
        color: var(--muted);
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 800;
        font-family: var(--mono);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .f-chip.active {
        background: var(--green);
        color: #000;
        border-color: var(--green);
        box-shadow: 0 0 15px rgba(188, 253, 73, 0.3);
    }

    .file-item-metal {
        background: #000;
        border: 1px solid var(--border);
        padding: 12px 18px;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .f-info {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #fff;
        font-size: 13px;
    }

    .f-status {
        color: var(--yellow);
        font-family: var(--mono);
        font-size: 10px;
        font-weight: 800;
    }

    .drop-zone-premium {
        border: 2px solid var(--border);
        border-radius: 24px;
        padding: 60px 20px;
        background: linear-gradient(145deg, rgba(20, 25, 20, 1) 0%, rgba(8, 12, 9, 1) 100%);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: all 0.4s ease;
    }

    .metal-shine {
        position: absolute;
        top: -100%;
        left: -100%;
        width: 300%;
        height: 300%;
        background: linear-gradient(45deg, transparent 45%, rgba(188, 253, 73, 0.05) 50%, transparent 55%);
        animation: metal-flow 6s infinite linear;
    }

    @keyframes metal-flow {
        0% {
            transform: translate(0, 0);
        }

        100% {
            transform: translate(30%, 30%);
        }
    }

    .icon-glow {
        color: var(--green);
        filter: drop-shadow(0 0 10px rgba(188, 253, 73, 0.5));
        margin-bottom: 20px;
    }
</style>

<script>
    // Helper p/ toast (Push Notification)
    window.toast = function (msg, tipo = 'info') {
        const el = document.createElement('div');
        el.className = `toast-msg ${tipo}`;
        const icon = tipo === 'ok' ? 'check-circle' : (tipo === 'erro' ? 'x-circle' : 'info');
        el.innerHTML = `<i data-lucide="${icon}" width="16"></i> <span>${msg}</span>`;

        let toastContainer = document.getElementById('toast');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast';
            document.body.appendChild(toastContainer);
        }

        toastContainer.appendChild(el);
        if (window.lucide) lucide.createIcons();
        setTimeout(() => el.remove(), 4000);
    };

    const dropZone = document.getElementById('dropZoneConv');
    const input = document.getElementById('convInput');
    let selectedFormat = 'mp4';

    document.querySelectorAll('.f-chip').forEach(chip => {
        chip.onclick = () => {
            document.querySelectorAll('.f-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            selectedFormat = chip.innerText.split(' ')[0].toLowerCase();
        };
    });

    dropZone.onclick = () => input.click();
    input.onchange = (e) => {
        if (e.target.files.length) {
            document.getElementById('fileQueue').style.display = 'block';
            const list = document.getElementById('fileQueue');
            list.innerHTML = Array.from(e.target.files).map(f => `
                <div class="file-item-metal">
                    <div class="f-info"><i data-lucide="file" width="14"></i><span>${f.name}</span></div>
                    <div class="f-status">PRONTO</div>
                </div>
            `).join('');
            if (window.lucide) lucide.createIcons();
            toast('Arquivos adicionados à fila!', 'ok');
        }
    };

    async function converterTudo() {
        if (!input.files.length) return toast('Selecione arquivos primeiro', 'erro');

        toast('Enviando para o Conversor...', 'info');

        const files = input.files;
        let sucesso = 0;
        let falha = 0;

        for (let i = 0; i < files.length; i++) {
            toast(`Processando arquivo ${i + 1}/${files.length}...`, 'info');
            const formData = new FormData();
            formData.append('file', files[i]);
            formData.append('format', selectedFormat);

            try {
                const res = await fetch('/wazio/api/convert.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.url || data.ok) {
                    sucesso++;
                    // Optional auto download logic here
                } else {
                    falha++;
                }
            } catch (e) {
                falha++;
            }
        }

        toast(`Conversão concluída! ${sucesso} sucesso(s), ${falha} falha(s).`, sucesso > 0 ? 'ok' : 'erro');
    }
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>