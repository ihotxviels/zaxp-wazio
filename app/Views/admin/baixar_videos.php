<?php
header("Location: https://anydownloader.com/en/");
exit;
?>
<?php require_once __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="topbar">
    <div class="topbar-left">
        <span class="topbar-title">
            <i data-lucide="download-cloud" width="18" style="color:var(--green)"></i> FERRAMENTAS <span
                class="glow-text" style="color:var(--green);">///</span> DOWNLOADER MULTIMÍDIA
        </span>
    </div>
</div>

<div class="content">
    <div class="section">
        <div class="section-head">
            <div class="section-title">Extração de Conteúdo (No Watermark)</div>
        </div>
        <div class="section-body" style="padding:40px; text-align:center;">
            <p style="color:var(--muted); max-width:800px; margin:0 auto 30px; line-height:1.6;">
                Baixe vídeos em alta resolução do **YouTube, TikTok e Kwai** sem marcas d'água.
                Basta colar o link e nosso fluxo Wazio/N8N processará a extração premium.
            </p>

            <div class="url-input-container">
                <div class="platform-badges">
                    <span class="p-badge yt"><i data-lucide="youtube" width="14"></i> YouTube</span>
                    <span class="p-badge tk"><i data-lucide="music" width="14"></i> TikTok</span>
                    <span class="p-badge kw"><i data-lucide="video" width="14"></i> Kwai</span>
                </div>
                <div class="mode-selector-container">
                    <div class="mode-selector">
                        <button class="mode-btn active" onclick="switchDlMode('individual', this)">
                            <i data-lucide="video" width="14"></i> INDIVIDUAL
                        </button>
                        <button class="mode-btn" onclick="switchDlMode('massa', this)">
                            <i data-lucide="layers" width="14"></i> EM MASSA
                        </button>
                    </div>
                </div>

                <!-- INDIVIDUAL -->
                <div id="wrapperIndividual">
                    <div class="metal-input-wrapper">
                        <input type="text" id="videoUrlInd" placeholder="Cole a URL do vídeo aqui..."
                            class="metal-input">
                        <button class="btn btn-primary btn-glow" id="btnDownloadInd" onclick="iniciarDownloadInd()"
                            style="padding: 0 20px; font-weight: 800;">
                            <i data-lucide="search" width="18"></i> BUSCAR
                        </button>
                    </div>

                    <div id="downloadOptionsInd" style="display:none; margin-top:40px; text-align:left;">
                        <h4
                            style="color:var(--green); font-family:var(--mono); font-size:12px; margin-bottom:15px; letter-spacing:1px;">
                            RESULTADOS ENCONTRADOS:</h4>

                        <div id="videoPreviewWrapper"
                            style="display:none; margin-bottom: 20px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; padding: 15px; display: flex; gap: 15px; align-items: center;">
                            <img id="videoThumb" src=""
                                style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);" />
                            <div style="flex: 1; overflow: hidden;">
                                <h5 id="videoTitle"
                                    style="color:#fff; margin:0 0 5px 0; font-size:14px; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">
                                    Carregando titulo...</h5>
                                <span style="font-size:11px; color:var(--muted); font-family:var(--mono);">PRONTO PARA
                                    DOWNLOAD</span>
                            </div>
                        </div>

                        <div class="options-grid">
                            <div class="res-card">
                                <div class="res-info">
                                    <span class="res-tag">MP4</span>
                                    <span class="res-val">Alta Qualidade (HD/FHD)</span>
                                </div>
                                <button class="btn btn-ghost btn-sm" id="btnIndVideo"><i data-lucide="download"
                                        width="14"></i> BAIXANDO...</button>
                            </div>
                            <div class="res-card">
                                <div class="res-info">
                                    <span class="res-tag">MP3</span>
                                    <span class="res-val">Somente Áudio</span>
                                </div>
                                <button class="btn btn-ghost btn-sm" id="btnIndAudio"><i data-lucide="music"
                                        width="14"></i> AGUARDE...</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EM MASSA -->
                <div id="wrapperMassa" style="display:none;">
                    <div class="metal-input-wrapper" style="flex-direction: column;">
                        <textarea id="videoUrlMass" placeholder="Cole várias URLs (uma por linha)..."
                            class="metal-input mass-input"></textarea>
                        <div class="mass-controls"
                            style="margin-top: 20px; display: flex; align-items: center; gap: 15px;">
                            <button class="btn btn-primary btn-glow" id="btnDownloadMass"
                                onclick="iniciarProcessamentoMassa()" style="flex:1;">
                                <i data-lucide="download" width="18"></i> INICIAR EXTRAÇÃO EM FILA
                            </button>
                            <div
                                style="display: flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.3); padding: 8px 15px; border-radius: 8px; border: 1px solid var(--border);">
                                <input type="checkbox" id="zipMassMode"
                                    style="cursor:pointer; width:18px; height:18px; accent-color: var(--green);">
                                <label for="zipMassMode"
                                    style="font-size: 12px; color: var(--green); cursor:pointer; font-weight: 600;">EMPACOTAR
                                    EM ZIP (N8N)</label>
                            </div>
                        </div>
                    </div>

                    <div id="downloadResultsMass" style="display:none; margin-top:40px; text-align:left;">
                        <h4
                            style="color:var(--green); font-family:var(--mono); font-size:12px; margin-bottom:15px; letter-spacing:1px;">
                            FILA DE EXTRAÇÃO:</h4>
                        <div id="resultsGridMass" class="options-grid"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CONTAINER DE SINO TOAST -->
<div id="toast"></div>

<style>
    /* === BLACK MODE WAR GREEN AESTHETICS === */
    :root {
        --bg-video: #060907;
        --card-video: #0a0e0b;
        --border-video: rgba(188, 253, 73, 0.15);
        --green: #bcfd49;
        --green-neon: #bcfd49;
        --green-glow: rgba(188, 253, 73, 0.1);
        --text-muted: #8b9990;
    }

    body {
        background-color: var(--bg-video) !important;
    }

    .topbar {
        background: var(--card-video) !important;
        border-bottom: 1px solid var(--border-video) !important;
    }

    .section {
        background: var(--card-video) !important;
        border: 1px solid var(--border-video) !important;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.9) !important;
        border-radius: 16px;
        overflow: hidden;
    }

    .section-head {
        border-bottom: 1px solid var(--border-video) !important;
        background: rgba(188, 253, 73, 0.02);
    }

    .section-title {
        color: var(--green-neon) !important;
        text-shadow: 0 0 10px var(--green-glow) !important;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 800;
    }

    .section-body p {
        color: var(--text-muted) !important;
    }

    .mode-selector-container {
        display: flex;
        justify-content: center;
        margin-bottom: 25px;
    }

    .mode-selector {
        background: rgba(0, 0, 0, 0.5);
        border: 1px solid var(--border-video);
        padding: 4px;
        border-radius: 12px;
        display: flex;
        gap: 4px;
        box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.8);
    }

    .mode-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .mode-btn.active {
        background: var(--green-neon);
        color: #000;
        box-shadow: 0 0 15px var(--green-glow);
    }

    .url-input-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .platform-badges {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-bottom: 20px;
    }

    .p-badge {
        font-size: 10px;
        font-weight: 800;
        font-family: var(--mono);
        padding: 4px 12px;
        border-radius: 100px;
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border);
        color: var(--muted);
    }

    .p-badge.yt {
        background: #ff0000;
        color: #fff;
        border-color: #ff0000;
    }

    .p-badge.tk {
        background: #000;
        color: #fff;
        border-color: #333;
    }

    .p-badge.kw {
        background: #ff7700;
        color: #fff;
        border-color: #ff7700;
    }

    .metal-input-wrapper {
        display: flex;
        background: #020302;
        border: 1px solid var(--border-video);
        border-radius: 16px;
        padding: 8px;
        gap: 10px;
        box-shadow: inset 0 4px 15px rgba(0, 0, 0, 0.9), 0 0 20px rgba(0, 0, 0, 0.5);
    }

    .metal-input {
        flex: 1;
        background: transparent;
        border: none;
        color: var(--green-neon);
        padding: 10px 15px;
        outline: none;
        font-size: 15px;
        font-family: var(--mono);
    }

    .metal-input::placeholder {
        color: #33443a;
    }

    .btn-glow {
        background: var(--green-neon) !important;
        color: #000 !important;
        border: none !important;
        box-shadow: 0 0 15px var(--green-glow) !important;
        text-shadow: none !important;
    }

    .btn-glow:hover {
        box-shadow: 0 0 25px var(--green-neon) !important;
        transform: translateY(-2px);
    }

    .btn-glow:hover {
        box-shadow: 0 0 20px rgba(188, 253, 73, 0.4);
    }

    .options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }

    .res-card {
        background: #040605;
        border: 1px solid var(--border-video);
        padding: 15px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
    }

    .res-card:hover {
        border-color: var(--green-neon);
        background: rgba(188, 253, 73, 0.05);
        box-shadow: 0 5px 20px rgba(188, 253, 73, 0.1);
        transform: translateY(-2px);
    }

    .res-tag {
        background: var(--green-neon);
        color: #000;
        font-size: 9px;
        font-weight: 900;
        padding: 3px 8px;
        border-radius: 4px;
        margin-right: 8px;
        letter-spacing: 0.5px;
        box-shadow: 0 0 8px var(--green-glow);
    }

    .res-val {
        color: #fff;
        font-size: 13px;
        font-family: var(--font-ui);
    }

    .btn-ghost {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.01) 100%) !important;
        color: #fff !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 2px 4px rgba(0, 0, 0, 0.5) !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
        transition: all 0.3s ease;
    }

    .btn-ghost:hover {
        background: linear-gradient(180deg, rgba(188, 253, 73, 0.15) 0%, rgba(188, 253, 73, 0.05) 100%) !important;
        color: var(--green-neon) !important;
        border-color: rgba(188, 253, 73, 0.3) !important;
        box-shadow: inset 0 1px 0 rgba(188, 253, 73, 0.2), 0 0 15px rgba(188, 253, 73, 0.1) !important;
    }

    .btn-glow {
        background: linear-gradient(180deg, #a2d149 0%, var(--green-neon) 100%) !important;
        color: #040605 !important;
        border: 1px solid rgba(142, 189, 60, 0.6) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 0 15px var(--green-glow) !important;
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.2) !important;
    }

    .btn-glow:hover {
        background: linear-gradient(180deg, #bcfd49 0%, #a2d149 100%) !important;
        box-shadow: 0 0 20px var(--green-neon), inset 0 1px 0 rgba(255, 255, 255, 0.5) !important;
        transform: translateY(-2px);
    }

    .mass-input {
        min-height: 80px;
        resize: vertical;
        border-radius: 10px;
    }

    .btn-mass-action {
        width: 100%;
        font-weight: 800;
        padding: 15px;
    }

    /* === RESPONSIVIDADE MOBILE === */
    @media (max-width: 768px) {
        .section-body {
            padding: 20px !important;
        }

        .platform-badges {
            flex-wrap: wrap;
        }

        .metal-input-wrapper {
            flex-direction: column;
            border-radius: 12px;
        }

        .metal-input {
            width: 100%;
            font-size: 13px;
            padding: 12px;
        }

        #btnDownloadInd {
            width: 100%;
            padding: 12px !important;
        }

        #videoPreviewWrapper {
            flex-direction: column;
            text-align: center;
        }

        #videoThumb {
            width: 100% !important;
            height: auto !important;
            aspect-ratio: 16/9;
        }

        .res-card {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }

        .res-card .btn-ghost {
            width: 100%;
        }
    }
</style>

<script>
    let dlMode = 'individual';

    function switchDlMode(mode, btn) {
        dlMode = mode;
        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.getElementById('wrapperIndividual').style.display = mode === 'individual' ? 'block' : 'none';
        document.getElementById('wrapperMassa').style.display = mode === 'massa' ? 'block' : 'none';
    }

    window.abrirModalAlert = function (title, msg, tipo = 'info') {
        let m = document.getElementById('modalNativeAlert');
        if (!m) {
            const mHtml = `
            <div id="modalNativeAlert" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:99999; align-items:center; justify-content:center; padding:15px; opacity:0; transition:opacity 0.2s ease;">
                <div class="modal-content" style="background:var(--card2); border:1px solid var(--border); border-radius:12px; width:100%; max-width:400px; box-shadow:0 15px 40px rgba(0,0,0,0.9); transform:translateY(20px); transition:all 0.3s ease;">
                    <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,0.05); padding:15px 20px; display:flex; justify-content:space-between; align-items:center;">
                        <h2 id="modalAlertTitle" style="font-size:14px; font-weight:800; font-family:var(--font-ui); display:flex; align-items:center; gap:8px; margin:0; color:#fff;"></h2>
                        <button type="button" onclick="fecharModalAlert()" style="background:none; border:none; color:var(--muted); cursor:pointer; padding:4px;"><i data-lucide="x" width="18"></i></button>
                    </div>
                    <div class="modal-body" style="padding:20px;">
                        <p id="modalAlertMsg" style="color:var(--muted); font-size:13px; line-height:1.6; margin:0;"></p>
                    </div>
                    <div class="modal-footer" style="padding:15px 20px; border-top:1px solid rgba(255,255,255,0.05); display:flex; justify-content:flex-end;">
                        <button type="button" onclick="fecharModalAlert()" class="btn btn-primary" style="background:var(--green); color:#080c09; border:none; padding:8px 24px; border-radius:6px; font-weight:800; font-size:12px; cursor:pointer;">OK, ENTENDI</button>
                    </div>
                </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', mHtml);
            m = document.getElementById('modalNativeAlert');
        }

        const icon = tipo === 'erro' ? '<i data-lucide="alert-triangle" width="16" style="color:var(--red);"></i>' : '<i data-lucide="info" width="16" style="color:var(--green);"></i>';
        document.getElementById('modalAlertTitle').innerHTML = `${icon} ${title}`;
        document.getElementById('modalAlertMsg').innerHTML = msg;

        m.style.display = 'flex';
        setTimeout(() => {
            m.style.opacity = '1';
            m.querySelector('.modal-content').style.transform = 'translateY(0)';
            if (window.lucide) lucide.createIcons();
        }, 10);
    };

    window.fecharModalAlert = function () {
        const m = document.getElementById('modalNativeAlert');
        if (m) {
            m.style.opacity = '0';
            m.querySelector('.modal-content').style.transform = 'translateY(20px)';
            setTimeout(() => { m.style.display = 'none'; }, 200);
        }
    };

    // Helper p/ toast (Push Notification) ou modal nativo se for erro crítico grande
    function alertMsg(msg, tipo = 'info') {
        if (tipo === 'erro' && msg.length > 50) {
            window.abrirModalAlert('Aviso do Sistema', msg, tipo);
        } else {
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
        }
    }

    // FORÇAR DOWNLOAD VIA BLOB FETCH PARA NÃO ABRIR NOVA GUIA (BYPASS DE CORS)
    window.forcarDownload = async function (urlMidia, nomeArquivo, btnRef) {
        if (!urlMidia) return;

        let origHtml = btnRef ? btnRef.innerHTML : '';
        if (btnRef) {
            btnRef.innerHTML = '<i data-lucide="loader-2" class="spin" width="14"></i> TRANSFERINDO...';
            btnRef.style.pointerEvents = 'none';
            if (window.lucide) lucide.createIcons();
        }

        try {
            const res = await fetch(urlMidia);
            const blob = await res.blob();
            const blobUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = blobUrl;
            a.download = nomeArquivo;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(blobUrl);
            a.remove();
        } catch (e) {
            window.abrirModalAlert('CORS Block', 'Esta mídia está protegida contra proxy. Vamos abrí-la diretamente numa nova guia para você baixar pelo navegador.', 'info');
            window.open(urlMidia, '_blank');
        }

        if (btnRef) {
            btnRef.innerHTML = origHtml;
            btnRef.style.pointerEvents = 'auto';
            if (window.lucide) lucide.createIcons();
        }
    };

    // FUNÇÃO QUE EXTRAI SÓ ÁUDIO INDEPENDENTE DEPOIS DA BUSCA (PARA MP3 NÃO CARREGADOS NO INÍCIO)
    window.baixarAudioAsync = async function (urlFront, defaultTitle, btnA) {
        const origHtml = btnA.innerHTML;
        btnA.innerHTML = '<i data-lucide="loader-2" class="spin" width="14"></i> EXTRAINDO...';
        btnA.style.pointerEvents = 'none';
        if (window.lucide) lucide.createIcons();

        try {
            // Requisita Bridge PHP 
            const urlBridge = '/wazio/api/download_video.php';
            const formData = new FormData();
            formData.append('url', urlFront);
            const res = await fetch(urlBridge, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            // Lendo padrão da API Media
            const isOk = !data.error;
            const dUrl = data.url || data.media_url || data.result;

            if (isOk && dUrl) {
                await window.forcarDownload(dUrl, data.titulo || defaultTitle, btnA);
            } else {
                alertMsg('O Áudio não pôde ser extraído deste vídeo.', 'erro');
                btnA.innerHTML = origHtml;
                btnA.style.pointerEvents = 'auto';
            }
        } catch (e) {
            alertMsg('Falha de rede ao buscar áudio', 'erro');
            btnA.innerHTML = origHtml;
            btnA.style.pointerEvents = 'auto';
        }
    };

    // MODO INDIVIDUAL
    async function iniciarDownloadInd() {
        const url = document.getElementById('videoUrlInd').value.trim();
        if (!url) return alertMsg('Por favor, insira uma URL válida', 'erro');

        const btn = document.getElementById('btnDownloadInd');
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="spin" width="18"></i> BUSCANDO...';
        if (window.lucide) lucide.createIcons();

        document.getElementById('downloadOptionsInd').style.display = 'block';
        document.getElementById('videoPreviewWrapper').style.display = 'none';

        const btnV = document.getElementById('btnIndVideo');
        const btnA = document.getElementById('btnIndAudio');

        const loadingHTML = '<i data-lucide="loader-2" class="spin" width="14"></i> Processando...';

        btnV.innerHTML = loadingHTML; btnV.disabled = true; btnV.style.color = ''; btnV.className = 'btn btn-ghost btn-sm';
        btnA.innerHTML = loadingHTML; btnA.disabled = true; btnA.style.color = ''; btnA.className = 'btn btn-ghost btn-sm';
        if (window.lucide) lucide.createIcons();

        try {
            const urlBridge = '/wazio/api/download_video.php';
            const formData = new FormData();
            formData.append('url', url);

            const res = await fetch(urlBridge, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();
            const isOk = !data.error;

            // Suporte para o retorno do Node
            const dUrl = data.url || data.media_url || data.result;
            const thumbUrl = data.thumbnail || 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=400';
            const titleStr = data.titulo || data.title || 'Vídeo Local';

            if (isOk && dUrl) {
                alertMsg('Mídia encontrada!', 'ok');

                // Exibe Preview
                document.getElementById('videoPreviewWrapper').style.display = 'flex';
                document.getElementById('videoThumb').src = thumbUrl;
                document.getElementById('videoTitle').innerText = titleStr;

                // Ícone da plataforma baseado na URL extraída
                let pIcon = 'download';
                if (url.includes('youtu')) pIcon = 'youtube';
                if (url.includes('tiktok')) pIcon = 'music'; // 'music' é o ícone adotado para TikTok
                if (url.includes('kwai')) pIcon = 'video';

                // Destrava Botão Principal (MP4 Qualidade Nativa)
                btnV.innerHTML = `<i data-lucide="${pIcon}" width="14"></i> BAIXAR MP4`;
                btnV.disabled = false;
                btnV.className = 'btn btn-glow btn-sm';
                btnV.onclick = () => window.forcarDownload(dUrl, titleStr, btnV);

                // AUDIO ONLY (Extração Oficial)
                btnA.innerHTML = `<i data-lucide="music" width="14"></i> EXTRAIR ÁUDIO`;
                btnA.disabled = false;
                btnA.className = 'btn btn-glow btn-sm';
                btnA.onclick = () => window.baixarAudioAsync(url, titleStr.replace('.mp4', '.mp3'), btnA);

            } else {
                alertMsg(data.erro || 'Erro ao processar download pelo N8N', 'erro');
                const errHTML = '<i data-lucide="x-circle" width="14"></i> FALHOU';
                btnV.innerHTML = errHTML; btnA.innerHTML = errHTML;
                btnV.style.color = '#ff4444'; btnA.style.color = '#ff4444';
            }
        } catch (e) {
            alertMsg('Falha de conexão com o servidor', 'erro');
            const errHTML = '<i data-lucide="alert-triangle" width="14"></i> ERRO';
            btnV.innerHTML = errHTML; btnA.innerHTML = errHTML;
            btnV.style.color = '#ff4444'; btnA.style.color = '#ff4444';
        }

        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="search" width="18"></i> BUSCAR';
        if (window.lucide) lucide.createIcons();
    }

    // MODO MASSA
    async function iniciarDownloadMass() {
        const text = document.getElementById('videoUrlMass').value.trim();
        if (!text) return alertMsg('Por favor, insira as URLs.', 'erro');

        const urls = text.split('\n').map(u => u.trim()).filter(u => u);
        if (!urls.length) return alertMsg('Insira ao menos uma URL válida', 'erro');

        document.getElementById('downloadResultsMass').style.display = 'block';
        const grid = document.getElementById('resultsGridMass');
        const btnMain = document.getElementById('btnDownloadMass');

        btnMain.disabled = true;
        btnMain.innerHTML = '<i data-lucide="loader-2" class="spin" width="18"></i> PROCESSANDO FILA...';

        const queueItems = [];
        grid.innerHTML = '';
        urls.forEach((url, i) => {
            const id = 'dl_' + Date.now() + '_' + i;
            let tag = 'URL';
            if (url.includes('youtu')) tag = 'YT';
            if (url.includes('tiktok')) tag = 'TK';
            if (url.includes('kwai')) tag = 'KW';

            grid.innerHTML += `
                <div class="res-card" id="${id}">
                    <div class="res-info" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:65%;">
                        <span class="res-tag">${tag}</span>
                        <span class="res-val" style="font-size:11px;" title="${url}">${url}</span>
                    </div>
                    <button class="btn btn-ghost btn-sm" id="btn_${id}" disabled>
                        <i data-lucide="loader-2" class="spin" width="14"></i> PENDENTE
                    </button>
                </div>
            `;
            queueItems.push({ id, url });
        });

        if (window.lucide) lucide.createIcons();
        document.getElementById('videoUrlMass').value = '';
        alertMsg(`Iniciando extração em lote (${queueItems.length} links)`, 'info');

        const zipMode = document.getElementById('zipMassMode').checked;
        const n8nWebhookURL = 'https://criadordigital-n8n-webhook.7phgib.easypanel.host/webhook/extracao-hibrida';

        if (zipMode) {
            btnMain.disabled = true;
            btnMain.innerHTML = `<i data-lucide="loader-2" class="spin" width="14"></i> GERANDO ZIP NO N8N...`;
            alertMsg(`Processando ${lines.length} links em massa (ZIP)...`, 'info');

            try {
                const res = await fetch(n8nWebhookURL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ urls: lines.filter(l => l.trim()), audio: false })
                });

                if (res.ok) {
                    const blob = await res.blob();
                    const urlZip = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = urlZip;
                    a.download = `UAZAPI_LOTE_${new Date().getTime()}.zip`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(urlZip);
                    alertMsg('ZIP gerado e baixado com sucesso!', 'ok');
                } else {
                    alertMsg('Erro ao gerar ZIP no N8N.', 'erro');
                }
            } catch (e) {
                alertMsg('Erro de rede ao conectar com o N8N.', 'erro');
            }

            btnMain.disabled = false;
            btnMain.innerHTML = '<i data-lucide="download" width="18"></i> INICIAR EXTRAÇÃO EM FILA';
            return;
        }

        // --- MODO TRADICIONAL (FILA INDIVIDUAL) ---
        let sucesso = 0;
        let falha = 0;

        for (const item of queueItems) {
            const btn = document.getElementById(`btn_${item.id}`);
            btn.innerHTML = `<i data-lucide="loader-2" class="spin" width="14"></i> BUSCANDO...`;
            if (window.lucide) lucide.createIcons();

            try {
                const formData = new FormData();
                formData.append('url', item.url);

                const res = await fetch('/wazio/api/download_video.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                const isOk = !data.error;
                const dUrl = data.url || data.media_url || data.result;

                if (isOk && dUrl) {
                    btn.disabled = false;
                    btn.innerHTML = `<i data-lucide="download" width="14"></i> BAIXAR`;
                    btn.onclick = () => window.forcarDownload(dUrl, data.titulo || 'Midia.mp4', btn);
                    btn.style.color = 'var(--green)';
                    btn.style.borderColor = 'var(--green)';
                    sucesso++;
                    window.forcarDownload(dUrl, data.titulo || 'Midia.mp4', null);
                } else {
                    btn.innerHTML = `FALHOU`;
                    btn.style.color = '#ff4444';
                    falha++;
                }
            } catch (e) {
                btn.innerHTML = `ERRO REDE`;
                btn.style.color = '#ff4444';
                falha++;
            }
        }

        btnMain.disabled = false;
        btnMain.innerHTML = '<i data-lucide="download" width="18"></i> INICIAR EXTRAÇÃO EM FILA';
        if (window.lucide) lucide.createIcons();

        alertMsg(`Fila concluída! ${sucesso} sucesso(s), ${falha} falha(s).`, sucesso > 0 ? 'ok' : 'erro');
    }
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>