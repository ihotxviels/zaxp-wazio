<?php
require_once __DIR__ . '/../layouts/admin_header.php';

if (!isset($_SESSION['user'])) {
    header("Location: /wazio/");
    exit;
}

$fileToEdit = $_GET['file'] ?? '';
$initialFlowData = 'null';

if (!empty($fileToEdit)) {
    $flowPath = __DIR__ . '/../../core/database/flows/' . basename($fileToEdit);
    if (file_exists($flowPath)) {
        $initialFlowData = file_get_contents($flowPath);
    }
}

// Pegar lista de instâncias disponíveis do usuário
$userInstances = $_SESSION['user']['instancias'] ?? [];
?>

<div class="flow-editor-container">
    <!-- BARRA LATERAL ESQUERDA: COMPONENTES -->
    <aside class="flow-editor-sidebar">
        <div class="sidebar-title">
            <i data-lucide="plus-circle" width="18"></i> BLOCOS ZAPDATA
        </div>

        <div class="component-group">
            <label>GATILHOS</label>
            <div class="node-item" draggable="true" data-type="trigger_message">
                <i data-lucide="message-square" width="16"></i> Palavra-Chave
            </div>
            <div class="node-item" draggable="true" data-type="trigger_new">
                <i data-lucide="user-plus" width="16"></i> Novo Lead (Ads)
            </div>
        </div>

        <div class="component-group">
            <label>AÇÕES MSG</label>
            <div class="node-item" draggable="true" data-type="action_text">
                <i data-lucide="type" width="16"></i> Enviar Texto
            </div>
            <div class="node-item" draggable="true" data-type="action_image">
                <i data-lucide="image" width="16"></i> Imagem
            </div>
            <div class="node-item" draggable="true" data-type="action_video">
                <i data-lucide="film" width="16"></i> Vídeo
            </div>
            <div class="node-item" draggable="true" data-type="action_audio">
                <i data-lucide="mic" width="16"></i> Áudio (Gravando)
            </div>
            <div class="node-item" draggable="true" data-type="action_document">
                <i data-lucide="file-text" width="16"></i> Documento
            </div>
            <div class="node-item" draggable="true" data-type="action_sticker">
                <i data-lucide="sticker" width="16"></i> Figurinha
            </div>
            <div class="node-item" draggable="true" data-type="action_contact">
                <i data-lucide="user" width="16"></i> Contato
            </div>
            <div class="node-item" draggable="true" data-type="action_interactive">
                <i data-lucide="mouse-pointer-click" width="16"></i> Msg. Interativa
            </div>
        </div>

        <div class="component-group">
            <label>LÓGICA</label>
            <div class="node-item" draggable="true" data-type="logic_delay">
                <i data-lucide="timer" width="16"></i> Delay
            </div>
            <div class="node-item" draggable="true" data-type="logic_wait">
                <i data-lucide="clock" width="16"></i> Aguardar Resposta
            </div>
            <div class="node-item" draggable="true" data-type="logic_condition">
                <i data-lucide="split" width="16"></i> Condição (IF/ELSE)
            </div>
            <div class="node-item" draggable="true" data-type="logic_random">
                <i data-lucide="dices" width="16"></i> Randomizador (A/B)
            </div>
        </div>

        <div class="component-group">
            <label>ZAPDATA CRM</label>
            <div class="node-item" draggable="true" data-type="action_pixel">
                <i data-lucide="target" width="16"></i> Pixel (FB/API)
            </div>
            <div class="node-item" draggable="true" data-type="action_tag">
                <i data-lucide="tag" width="16"></i> Etiquetas
            </div>
            <div class="node-item" draggable="true" data-type="logic_payment">
                <i data-lucide="credit-card" width="16"></i> Identificar Pgto
            </div>
            <div class="node-item" draggable="true" data-type="action_pix">
                <i data-lucide="qr-code" width="16"></i> Gerar PIX (Gateway)
            </div>
            <div class="node-item" draggable="true" data-type="action_boleto">
                <i data-lucide="file-text" width="16"></i> Gerar Boleto
            </div>
            <div class="node-item" draggable="true" data-type="action_notify">
                <i data-lucide="bell" width="16"></i> Notificar Admin
            </div>
            <div class="node-item" draggable="true" data-type="action_jump">
                <i data-lucide="git-merge" width="16"></i> Conectar Fluxo
            </div>
        </div>
    </aside>

    <!-- CANVAS CENTRAL -->
    <main class="flow-canvas-wrapper" id="canvasWrapper">
        <div class="canvas-controls">
            <button class="btn btn-ghost btn-sm" onclick="zoomIn()"><i data-lucide="zoom-in" width="16"></i></button>
            <button class="btn btn-ghost btn-sm" onclick="zoomOut()"><i data-lucide="zoom-out" width="16"></i></button>
            <button class="btn btn-ghost btn-sm" onclick="resetView()"><i data-lucide="maximize"
                    width="16"></i></button>
        </div>

        <div class="canvas-bg"></div>
        <div id="flowCanvas" class="flow-canvas">
            <!-- Os nodes serão inseridos aqui dinamicamente -->
            <svg id="edgeContainer" class="edge-container"></svg>
        </div>
    </main>

    <!-- BARRA LATERAL DIREITA: PROPRIEDADES -->
    <aside class="flow-properties-sidebar" id="propertiesSidebar">
        <div class="sidebar-title">
            <i data-lucide="settings" width="18"></i> CONFIGURAÇÕES
        </div>
        <div id="propertiesContent" class="properties-content">
            <p class="empty-msg">Selecione um bloco para editar.</p>
        </div>
    </aside>

    <!-- CABEÇALHO DO EDITOR -->
    <div class="flow-editor-header">
        <div style="display:flex; align-items:center; gap:15px;">
            <button class="btn btn-ghost" onclick="window.location.href='/wazio/fluxos'"><i data-lucide="arrow-left"
                    width="18"></i></button>
            <h3 style="color:#fff; margin:0; font-family:var(--font-ui);">Editor de Fluxo</h3>
            <div
                style="display:flex; align-items:center; gap:10px; margin-left:20px; border-left:1px solid #333; padding-left:20px;">
                <input type="text" id="flowNameInput" class="form-input" placeholder="Nome do Fluxo"
                    style="width:200px; padding:5px 10px; height:36px;" value="Vendas Automaticas">
                <select id="flowInstanceInput" class="form-input" style="width:200px; padding:5px 10px; height:36px;">
                    <option value="">Selecione a Instância...</option>
                    <?php foreach ($userInstances as $uInst): ?>
                        <option value="<?= htmlspecialchars($uInst) ?>"><?= htmlspecialchars($uInst) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-ghost" onclick="testFlow()"><i data-lucide="play" width="16"></i> Testar no
                Whats</button>
            <button class="btn btn-primary" onclick="saveFlow()" style="box-shadow:0 0 15px rgba(188,253,73,0.3);"><i
                    data-lucide="save" width="16"></i> SALVAR FLUXO</button>
        </div>
    </div>
</div>

<div id="editorToast" class="editor-toast">Ação concluída</div>

<style>
    .flow-editor-container {
        position: fixed;
        inset: 0;
        z-index: 1000;
        background: #050705;
        display: grid;
        grid-template-columns: 260px 1fr 300px;
        grid-template-rows: 70px 1fr;
        user-select: none;
    }

    .flow-editor-header {
        grid-column: 1 / span 3;
        background: rgba(0, 0, 0, 0.8);
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 25px;
        backdrop-filter: blur(10px);
    }

    /* SIDEBARS */
    .flow-editor-sidebar,
    .flow-properties-sidebar {
        background: #0c0f0c;
        border-right: 1px solid var(--border);
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        overflow-y: auto;
    }

    .flow-properties-sidebar {
        border-right: none;
        border-left: 1px solid var(--border);
    }

    .sidebar-title {
        color: var(--green);
        font-family: var(--font-ui);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 2px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
    }

    .component-group label {
        display: block;
        font-size: 9px;
        color: var(--muted);
        margin-bottom: 10px;
        font-family: var(--mono);
    }

    .node-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border);
        padding: 12px;
        border-radius: 8px;
        color: #fff;
        font-size: 13px;
        margin-bottom: 8px;
        cursor: grab;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.2s;
    }

    .node-item:hover {
        border-color: var(--green);
        background: rgba(188, 253, 73, 0.05);
        transform: translateX(5px);
    }

    /* CANVAS */
    .flow-canvas-wrapper {
        position: relative;
        overflow: hidden;
        background-color: #080a08;
    }

    .canvas-bg {
        position: absolute;
        width: 10000px;
        height: 10000px;
        background-image:
            radial-gradient(rgba(188, 253, 73, 0.05) 1px, transparent 0),
            radial-gradient(rgba(188, 253, 73, 0.02) 1px, transparent 0);
        background-size: 40px 40px, 200px 200px;
        pointer-events: none;
        transform-origin: 0 0;
    }

    .flow-canvas {
        position: absolute;
        width: 0;
        height: 0;
        overflow: visible;
        transform-origin: 0 0;
    }

    .edge-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 10000px;
        height: 10000px;
        pointer-events: none;
        overflow: visible;
        z-index: -1;
    }

    .canvas-controls {
        position: absolute;
        bottom: 30px;
        right: 30px;
        display: flex;
        gap: 10px;
        z-index: 100;
        background: rgba(0, 0, 0, 0.5);
        padding: 5px;
        border-radius: 8px;
        border: 1px solid var(--border);
        backdrop-filter: blur(5px);
    }

    /* NODES */
    .canvas-node {
        position: absolute;
        width: 250px;
        background: #111;
        border: 1px solid var(--border);
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        display: flex;
        flex-direction: column;
        transition: box-shadow 0.2s, border-color 0.2s;
    }

    .canvas-node.selected {
        border-color: var(--green);
        box-shadow: 0 0 0 2px rgba(188, 253, 73, 0.2), 0 10px 30px rgba(0, 0, 0, 0.8);
    }

    .node-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 15px;
        border-bottom: 1px solid var(--border);
        font-family: var(--font-ui);
        font-size: 13px;
        color: #fff;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 12px 12px 0 0;
        cursor: grab;
    }

    .node-header:active {
        cursor: grabbing;
    }

    .node-icon-wrapper {
        background: rgba(188, 253, 73, 0.1);
        color: var(--green);
        padding: 5px;
        border-radius: 6px;
        display: flex;
    }

    .node-body {
        padding: 15px;
        font-size: 12px;
        color: var(--muted);
        line-height: 1.4;
        background: #0a0a0a;
        border-radius: 0 0 12px 12px;
        min-height: 30px;
        word-wrap: break-word;
    }

    /* PORTS */
    .port {
        width: 14px;
        height: 14px;
        background: #111;
        border-radius: 50%;
        position: absolute;
        border: 2px solid var(--muted);
        cursor: crosshair;
        z-index: 5;
        transition: all 0.2s;
    }

    .port:hover {
        background: var(--green);
        border-color: var(--green);
        transform: scale(1.2) translateY(-50%);
        box-shadow: 0 0 10px rgba(188, 253, 73, 0.5);
    }

    .port.connected {
        background: var(--green);
        border-color: var(--green);
    }

    .port-in {
        left: -7px;
        top: 50%;
        transform: translateY(-50%);
    }

    .port-out {
        right: -7px;
        top: 50%;
        transform: translateY(-50%);
    }

    /* SVG EDGES */
    .edge-path {
        fill: none;
        stroke: var(--muted);
        stroke-width: 2.5;
        transition: stroke 0.2s, stroke-width 0.2s;
        cursor: pointer;
        pointer-events: stroke;
    }

    .edge-path:hover,
    .edge-path.selected {
        stroke: var(--green);
        stroke-width: 3.5;
        filter: drop-shadow(0 0 5px rgba(188, 253, 73, 0.5));
    }

    .edge-drawing {
        stroke: var(--green);
        stroke-dasharray: 5, 5;
        animation: dash 1s linear infinite;
        pointer-events: none;
    }

    @keyframes dash {
        to {
            stroke-dashoffset: -10;
        }
    }

    .properties-content .empty-msg {
        text-align: center;
        color: var(--muted);
        font-size: 12px;
        margin-top: 50px;
    }

    .editor-toast {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--green);
        color: #000;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-family: var(--font-ui);
        z-index: 9999;
        display: none;
        box-shadow: 0 5px 15px rgba(188, 253, 73, 0.3);
    }
</style>

<script>
    const generateId = () => Math.random().toString(36).substr(2, 9);
    const showEditorToast = (msg) => {
        const el = document.getElementById('editorToast');
        el.innerText = msg;
        el.style.display = 'block';
        setTimeout(() => el.style.display = 'none', 2000);
    };

    // Engine State
    const initialFlowData = <?= $initialFlowData ?>;

    const FlowEngine = {
        nodes: initialFlowData ? initialFlowData.nodes : [],
        edges: initialFlowData ? initialFlowData.edges : [],
        selectedNodes: new Set(),
        selectedEdges: new Set(),

        zoom: 1,
        pan: { x: window.innerWidth / 3, y: window.innerHeight / 4 },

        isPanning: false,
        isDraggingNode: false,
        isDrawingEdge: false,
        dragStartPos: { x: 0, y: 0 },
        drawingEdgeData: null,

        wrapper: document.getElementById('canvasWrapper'),
        canvas: document.getElementById('flowCanvas'),
        svgCanvas: document.getElementById('edgeContainer'),
        bgCanvas: document.querySelector('.canvas-bg')
    };

    const NodeTypes = {
        // Gatilhos
        trigger_message: { name: 'Palavra-Chave', icon: 'message-square', color: '#ff6b6b' },
        trigger_new: { name: 'Novo Lead (Anúncio)', icon: 'user-plus', color: '#ff6b6b' }, // Novo
        // Ações Msg
        action_text: { name: 'Texto', icon: 'type', color: '#bcfd49' },
        action_image: { name: 'Imagem', icon: 'image', color: '#bcfd49' },
        action_video: { name: 'Vídeo', icon: 'film', color: '#bcfd49' },
        action_audio: { name: 'Áudio (Gravado)', icon: 'mic', color: '#bcfd49' },
        action_document: { name: 'Documento', icon: 'file-text', color: '#bcfd49' },
        action_sticker: { name: 'Figurinha', icon: 'sticker', color: '#bcfd49' },
        action_contact: { name: 'Contato', icon: 'user', color: '#bcfd49' },
        action_interactive: { name: 'Msg. Interativa', icon: 'mouse-pointer-click', color: '#bcfd49' },
        // Lógica
        logic_delay: { name: 'Delay (Pausa)', icon: 'timer', color: '#4dabf7' },
        logic_wait: { name: 'Aguardar Resp.', icon: 'clock', color: '#4dabf7' },
        logic_condition: { name: 'Condição (IF)', icon: 'split', color: '#ae3ec9' },
        logic_random: { name: 'Randomizador', icon: 'dices', color: '#ae3ec9' },
        // CRM Avançado
        action_pixel: { name: 'Pixel (Ads)', icon: 'target', color: '#f59f00' },
        action_tag: { name: 'Etiquetas', icon: 'tag', color: '#f59f00' },
        logic_payment: { name: 'Status Pgto', icon: 'credit-card', color: '#f59f00' },
        action_pix: { name: 'Chave PIX', icon: 'qr-code', color: '#f59f00' },
        action_boleto: { name: 'Gerar Boleto', icon: 'file-text', color: '#f59f00' },
        action_notify: { name: 'Notificar Admin', icon: 'bell', color: '#f59f00' },
        action_jump: { name: 'Conectar Fluxo', icon: 'git-merge', color: '#f59f00' }
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) lucide.createIcons();
        initEvents();
        // Demo Node
        addNode('trigger_message', 100, 100);
        applyTransform();
    });

    function initEvents() {
        const { wrapper, canvas } = FlowEngine;

        // Viewport Dragging (Pan)
        wrapper.addEventListener('mousedown', (e) => {
            if (e.button !== 0 && e.button !== 1) return;

            // Check if clicking on empty space
            if (e.target === wrapper || e.target === canvas || e.target === FlowEngine.bgCanvas || e.target.tagName === 'svg') {
                if (!e.shiftKey) clearSelection();
                FlowEngine.isPanning = true;
                FlowEngine.dragStartPos = { x: e.clientX, y: e.clientY };
                wrapper.style.cursor = 'grabbing';
            }
        });

        window.addEventListener('mousemove', (e) => {
            if (FlowEngine.isPanning) {
                const dx = e.clientX - FlowEngine.dragStartPos.x;
                const dy = e.clientY - FlowEngine.dragStartPos.y;
                FlowEngine.pan.x += dx;
                FlowEngine.pan.y += dy;
                FlowEngine.dragStartPos = { x: e.clientX, y: e.clientY };
                applyTransform();
            }

            if (FlowEngine.isDraggingNode) {
                const dx = (e.clientX - FlowEngine.dragStartPos.x) / FlowEngine.zoom;
                const dy = (e.clientY - FlowEngine.dragStartPos.y) / FlowEngine.zoom;

                FlowEngine.selectedNodes.forEach(id => {
                    const node = FlowEngine.nodes.find(n => n.id === id);
                    if (node) {
                        node.x += dx;
                        node.y += dy;
                    }
                });

                FlowEngine.dragStartPos = { x: e.clientX, y: e.clientY };
                render();
            }

            if (FlowEngine.isDrawingEdge) {
                const bounds = canvas.getBoundingClientRect();
                FlowEngine.drawingEdgeData.currentX = (e.clientX - FlowEngine.pan.x) / FlowEngine.zoom;
                FlowEngine.drawingEdgeData.currentY = (e.clientY - FlowEngine.pan.y) / FlowEngine.zoom;
                drawEdges();
            }
        });

        window.addEventListener('mouseup', (e) => {
            FlowEngine.isPanning = false;
            FlowEngine.isDraggingNode = false;
            wrapper.style.cursor = 'default';

            if (FlowEngine.isDrawingEdge) {
                FlowEngine.isDrawingEdge = false;
                FlowEngine.drawingEdgeData = null;
                drawEdges();
            }
        });

        wrapper.addEventListener('wheel', (e) => {
            e.preventDefault();
            const zoomDelta = e.deltaY > 0 ? -0.1 : 0.1;

            const oldZoom = FlowEngine.zoom;
            let newZoom = oldZoom + zoomDelta;
            newZoom = Math.min(Math.max(0.3, newZoom), 2);

            const bounds = wrapper.getBoundingClientRect();
            const mouseX = e.clientX - bounds.left;
            const mouseY = e.clientY - bounds.top;

            FlowEngine.pan.x = mouseX - (mouseX - FlowEngine.pan.x) * (newZoom / oldZoom);
            FlowEngine.pan.y = mouseY - (mouseY - FlowEngine.pan.y) * (newZoom / oldZoom);
            FlowEngine.zoom = newZoom;

            applyTransform();
        });

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                deleteSelected();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                duplicateSelected();
            }
        });

        function setupDragAndDrop() {
            const wrapper = FlowEngine.wrapper;

            // Items da paleta (esquerda)
            document.querySelectorAll('.node-item').forEach(item => {
                item.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('type', item.dataset.type);
                    item.classList.add('dragging-source');
                });
                item.addEventListener('dragend', () => {
                    item.classList.remove('dragging-source');
                });
            });

            wrapper.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
            });

            wrapper.addEventListener('drop', (e) => {
                e.preventDefault();
                const type = e.dataTransfer.getData('type');
                if (!type) return;

                const bounds = wrapper.getBoundingClientRect();
                // Calcular posição real do drop levando em conta pan e zoom
                const x = (e.clientX - bounds.left - FlowEngine.pan.x) / FlowEngine.zoom;
                const y = (e.clientY - bounds.top - FlowEngine.pan.y) / FlowEngine.zoom;

                addNode(type, x, y);
            });
        }

        function setupCanvasInteraction() {
            const wrapper = FlowEngine.wrapper;

            // PANNING (Mover o Canvas)
            wrapper.addEventListener('mousedown', (e) => {
                if (e.target === wrapper || e.target.id === 'flowCanvas' || e.target.id === 'bgCanvas') {
                    FlowEngine.isPanning = true;
                    FlowEngine.dragStartPos = { x: e.clientX, y: e.clientY };
                    wrapper.style.cursor = 'grabbing';
                }
            });

            window.addEventListener('mousemove', (e) => {
                // Mover nó(s) selecionado(s)
                if (FlowEngine.isDraggingNode && FlowEngine.selectedNodes.size > 0) {
                    const dx = (e.clientX - FlowEngine.dragStartPos.x) / FlowEngine.zoom;
                    const dy = (e.clientY - FlowEngine.dragStartPos.y) / FlowEngine.zoom;

                    FlowEngine.selectedNodes.forEach(id => {
                        const node = FlowEngine.nodes.find(n => n.id === id);
                        if (node) {
                            node.x += dx;
                            node.y += dy;
                        }
                    });

                    FlowEngine.dragStartPos = { x: e.clientX, y: e.clientY };
                    renderNodes();
                    drawEdges();
                }

                // Panning do canvas
                if (FlowEngine.isPanning) {
                    const dx = e.clientX - FlowEngine.dragStartPos.x;
                    const dy = e.clientY - FlowEngine.dragStartPos.y;

                    FlowEngine.pan.x += dx;
                    FlowEngine.pan.y += dy;
                    FlowEngine.dragStartPos = { x: e.clientX, y: e.clientY };
                    applyTransform();
                }

                // Desenhando nova conexão (Edge)
                if (FlowEngine.isDrawingEdge && FlowEngine.drawingEdgeData) {
                    const bounds = wrapper.getBoundingClientRect();
                    FlowEngine.drawingEdgeData.currentX = (e.clientX - bounds.left - FlowEngine.pan.x) / FlowEngine.zoom;
                    FlowEngine.drawingEdgeData.currentY = (e.clientY - bounds.top - FlowEngine.pan.y) / FlowEngine.zoom;
                    drawEdges();
                }
            });

            window.addEventListener('mouseup', () => {
                FlowEngine.isPanning = false;
                FlowEngine.isDraggingNode = false;
                FlowEngine.isDrawingEdge = false;
                FlowEngine.drawingEdgeData = null;
                wrapper.style.cursor = 'crosshair';
                drawEdges(); // Limpa linha temporária
            });

            // ZOOM (Scroll do Mouse)
            wrapper.addEventListener('wheel', (e) => {
                e.preventDefault();
                const delta = e.deltaY > 0 ? 0.9 : 1.1;
                const newZoom = Math.min(Math.max(FlowEngine.zoom * delta, 0.2), 3);

                // Zoom centralizado no mouse
                const bounds = wrapper.getBoundingClientRect();
                const mouseX = e.clientX - bounds.left;
                const mouseY = e.clientY - bounds.top;

                FlowEngine.pan.x = mouseX - (mouseX - FlowEngine.pan.x) * (newZoom / FlowEngine.zoom);
                FlowEngine.pan.y = mouseY - (mouseY - FlowEngine.pan.y) * (newZoom / FlowEngine.zoom);

                FlowEngine.zoom = newZoom;
                applyTransform();
            }, { passive: false });
        }

        function addNode(type, x, y) {
            const id = 'node_' + generateId();
            const conf = NodeTypes[type];
            FlowEngine.nodes.push({
                id, type, x, y,
                data: { label: conf.name, content: 'Editar este bloco...' }
            });

            clearSelection();
            FlowEngine.selectedNodes.add(id);
            render();
            updatePropertiesPanel();
        }

        function deleteSelected() {
            if (FlowEngine.selectedNodes.size === 0 && FlowEngine.selectedEdges.size === 0) return;

            FlowEngine.selectedEdges.forEach(id => {
                FlowEngine.edges = FlowEngine.edges.filter(e => e.id !== id);
            });

            FlowEngine.selectedNodes.forEach(id => {
                FlowEngine.nodes = FlowEngine.nodes.filter(n => n.id !== id);
                FlowEngine.edges = FlowEngine.edges.filter(e => e.source !== id && e.target !== id);
            });

            clearSelection();
            render();
            updatePropertiesPanel();
            showEditorToast('Removido');
        }

        function duplicateSelected() {
            if (FlowEngine.selectedNodes.size === 0) return;

            const newNodes = [];
            FlowEngine.selectedNodes.forEach(id => {
                const original = FlowEngine.nodes.find(n => n.id === id);
                if (original) {
                    newNodes.push({
                        ...JSON.parse(JSON.stringify(original)),
                        id: 'node_' + generateId(),
                        x: original.x + 40,
                        y: original.y + 40
                    });
                }
            });

            FlowEngine.nodes.push(...newNodes);
            clearSelection();
            newNodes.forEach(n => FlowEngine.selectedNodes.add(n.id));
            render();
            showEditorToast('Duplicado');
        }

        function clearSelection() {
            FlowEngine.selectedNodes.clear();
            FlowEngine.selectedEdges.clear();
            document.querySelectorAll('.canvas-node').forEach(el => el.classList.remove('selected'));
            updatePropertiesPanel();
            drawEdges();
        }

        function applyTransform() {
            FlowEngine.canvas.style.transform = `translate(${FlowEngine.pan.x}px, ${FlowEngine.pan.y}px) scale(${FlowEngine.zoom})`;
            FlowEngine.bgCanvas.style.transform = `translate(${FlowEngine.pan.x}px, ${FlowEngine.pan.y}px) scale(${FlowEngine.zoom})`;
        }

        function render() {
            renderNodes();
            drawEdges();
        }

        function renderNodes() {
            const activeIds = FlowEngine.nodes.map(n => n.id);
            document.querySelectorAll('.canvas-node').forEach(el => {
                if (!activeIds.includes(el.id)) el.remove();
            });

            FlowEngine.nodes.forEach(node => {
                let el = document.getElementById(node.id);
                const conf = NodeTypes[node.type];

                if (!el) {
                    el = document.createElement('div');
                    el.className = 'canvas-node';
                    el.id = node.id;

                    el.innerHTML = `
                    <div class="node-header" style="color: ${conf.color}">
                        <div class="node-icon-wrapper" style="background: ${conf.color}22; color: ${conf.color}">
                            <i data-lucide="${conf.icon}" width="14"></i>
                        </div>
                        <span class="l-title">${node.data.label}</span>
                    </div>
                    <div class="node-body l-content">${node.data.content}</div>
                    ${!node.type.startsWith('trigger') ? '<div class="port port-in" data-port="in"></div>' : ''}
                    <div class="port port-out" data-port="out"></div>
                `;

                    el.addEventListener('mousedown', (e) => {
                        if (e.target.classList.contains('port')) return;

                        if (!e.shiftKey && !FlowEngine.selectedNodes.has(node.id)) clearSelection();
                        FlowEngine.selectedNodes.add(node.id);
                        el.classList.add('selected');
                        updatePropertiesPanel();

                        if (e.target.closest('.node-header')) {
                            FlowEngine.isDraggingNode = true;
                            FlowEngine.dragStartPos = { x: e.clientX, y: e.clientY };
                        }
                        e.stopPropagation();
                    });

                    const outPort = el.querySelector('.port-out');
                    if (outPort) {
                        outPort.addEventListener('mousedown', (e) => {
                            e.stopPropagation();
                            // Calulate local canvas coords for port
                            const rect = outPort.getBoundingClientRect();
                            const startX = ((rect.left + rect.width / 2) - FlowEngine.pan.x) / FlowEngine.zoom;
                            const startY = ((rect.top + rect.height / 2) - FlowEngine.pan.y) / FlowEngine.zoom;

                            FlowEngine.isDrawingEdge = true;
                            FlowEngine.drawingEdgeData = { sourceId: node.id, startX, startY, currentX: startX, currentY: startY };
                        });
                    }

                    const inPort = el.querySelector('.port-in');
                    if (inPort) {
                        inPort.addEventListener('mouseup', (e) => {
                            if (FlowEngine.isDrawingEdge && FlowEngine.drawingEdgeData.sourceId !== node.id) {
                                FlowEngine.edges.push({
                                    id: 'edge_' + generateId(),
                                    source: FlowEngine.drawingEdgeData.sourceId,
                                    target: node.id
                                });
                            }
                        });
                    }

                    FlowEngine.canvas.appendChild(el);
                    if (window.lucide) lucide.createIcons({ root: el });
                } else {
                    el.querySelector('.l-title').innerText = node.data.label;
                    el.querySelector('.l-content').innerText = node.data.content;
                }

                el.style.left = node.x + 'px';
                el.style.top = node.y + 'px';
                if (FlowEngine.selectedNodes.has(node.id)) el.classList.add('selected');
                else el.classList.remove('selected');

                const hasIn = FlowEngine.edges.some(e => e.target === node.id);
                const hasOut = FlowEngine.edges.some(e => e.source === node.id);
                if (el.querySelector('.port-in')) el.querySelector('.port-in').classList.toggle('connected', hasIn);
                if (el.querySelector('.port-out')) el.querySelector('.port-out').classList.toggle('connected', hasOut);
            });
        }

        function getBezierPath(sx, sy, tx, ty) {
            const dx = Math.abs(tx - sx);
            const controlOffset = Math.max(dx * 0.5, 60);
            return `M ${sx} ${sy} C ${sx + controlOffset} ${sy}, ${tx - controlOffset} ${ty}, ${tx} ${ty}`;
        }

        function drawEdges() {
            let svgContent = '';

            FlowEngine.edges.forEach(edge => {
                const srcNode = document.getElementById(edge.source);
                const tgtNode = document.getElementById(edge.target);
                if (!srcNode || !tgtNode) return;

                const sRect = srcNode.querySelector('.port-out').getBoundingClientRect();
                const tRect = tgtNode.querySelector('.port-in').getBoundingClientRect();

                const sx = ((sRect.left + sRect.width / 2) - FlowEngine.pan.x) / FlowEngine.zoom;
                const sy = ((sRect.top + sRect.height / 2) - FlowEngine.pan.y) / FlowEngine.zoom;
                const tx = ((tRect.left + tRect.width / 2) - FlowEngine.pan.x) / FlowEngine.zoom;
                const ty = ((tRect.top + tRect.height / 2) - FlowEngine.pan.y) / FlowEngine.zoom;

                const isSelected = FlowEngine.selectedEdges.has(edge.id);
                svgContent += `<path id="${edge.id}" class="edge-path ${isSelected ? 'selected' : ''}" d="${getBezierPath(sx, sy, tx, ty)}" />`;
                // Larger invisible hit area
                svgContent += `<path data-edge="${edge.id}" class="edge-hitbox" d="${getBezierPath(sx, sy, tx, ty)}" style="stroke:transparent; stroke-width:25px; fill:none; cursor:pointer;" />`;
            });

            if (FlowEngine.isDrawingEdge && FlowEngine.drawingEdgeData) {
                const d = FlowEngine.drawingEdgeData;
                svgContent += `<path class="edge-path edge-drawing" d="${getBezierPath(d.startX, d.startY, d.currentX, d.currentY)}" />`;
            }

            FlowEngine.svgCanvas.innerHTML = svgContent;

            document.querySelectorAll('.edge-hitbox').forEach(el => {
                el.addEventListener('mousedown', (e) => {
                    const edgeId = el.getAttribute('data-edge');
                    if (!e.shiftKey) clearSelection();
                    FlowEngine.selectedEdges.add(edgeId);
                    e.stopPropagation();
                    drawEdges(); // re-render to show selection
                    updatePropertiesPanel();
                });
            });
        }

        function updatePropertiesPanel() {
            const panel = document.getElementById('propertiesContent');
            if (FlowEngine.selectedNodes.size === 1) {
                const id = Array.from(FlowEngine.selectedNodes)[0];
                const node = FlowEngine.nodes.find(n => n.id === id);

                let extraFields = '';

                // Renderização Condicional Específica p/ Bloco Logic Condition (Ex: Filtro VIP/Comprador)
                if (node.type === 'logic_condition') {
                    extraFields = `
                    <div class="form-group" style="margin-top:15px; background:rgba(174, 62, 201, 0.1); padding:10px; border-radius:8px; border:1px solid rgba(174, 62, 201, 0.3);">
                        <label class="form-label" style="color:#ae3ec9;"><i data-lucide="filter" width="14"></i> Regra da Condição</label>
                        <select class="form-input" style="margin-bottom:10px;">
                            <option value="has_tag">Lead POSSUI a Etiqueta</option>
                            <option value="not_has_tag">Lead NÃO POSSUI a Etiqueta</option>
                            <option value="is_ddd">DDD Igual a</option>
                        </select>
                        <input type="text" class="form-input" placeholder="Ex: Já comprou, VIP, 11..." value="${node.data.conditionValue || ''}" id="propConditionValue">
                        <small style="color:var(--muted); font-size:10px; display:block; margin-top:5px;">Se a regra for atendida, o fluxo segue pela linha Verde (Caminho A). Senão, segue pela linha Vermelha (Caminho B).</small>
                    </div>
                `;
                }

                panel.innerHTML = `
                <div class="form-group">
                    <label class="form-label" style="font-size:10px; color:var(--muted); font-family:var(--mono);">[ID: ${node.id}]</label>
                </div>
                <div class="form-group">
                    <label class="form-label">Nome da Etapa</label>
                    <input type="text" class="form-input" id="propName" value="${node.data.label}">
                </div>
                <div class="form-group">
                    <label class="form-label">Conteúdo / Descrição</label>
                    <textarea class="form-input" id="propContent" style="height:120px; font-family:var(--font-ui); font-size:12px;">${node.data.content}</textarea>
                </div>
                ${extraFields}
                <button class="btn" onclick="deleteSelected()" style="width:100%; background:transparent; border:1px solid var(--red); color:var(--red); margin-top:15px;">EXCLUIR BLOCO</button>
            `;
                if (window.lucide) lucide.createIcons();

                document.getElementById('propName').addEventListener('input', (e) => {
                    node.data.label = e.target.value;
                    renderNodes();
                });
                document.getElementById('propContent').addEventListener('input', (e) => {
                    node.data.content = e.target.value;
                    renderNodes();
                });

                const condInput = document.getElementById('propConditionValue');
                if (condInput) {
                    condInput.addEventListener('input', (e) => {
                        node.data.conditionValue = e.target.value;
                    });
                }
            }
            else if (FlowEngine.selectedEdges.size === 1) {
                panel.innerHTML = `
                <div class="form-group" style="text-align:center; margin-top:20px;">
                    <label class="form-label" style="color:var(--green);"><i data-lucide="link"></i> Conexão Selecionada</label>
                    <p style="font-size:11px; color:var(--muted);">Esta linha direciona o cliente para o próximo passo no funil.</p>
                </div>
                <button class="btn" onclick="deleteSelected()" style="width:100%; background:transparent; border:1px solid var(--red); color:var(--red); margin-top:10px;">REMOVER CONEXÃO</button>
            `;
                if (window.lucide) lucide.createIcons();
            }
            else if (FlowEngine.selectedNodes.size > 1) {
                panel.innerHTML = `<p class="empty-msg">${FlowEngine.selectedNodes.size} blocos selecionados</p>
            <button class="btn btn-primary" onclick="duplicateSelected()" style="width:100%; margin-top:20px;"><i data-lucide="copy"></i> DUPLICAR TODOS</button>
            <button class="btn" onclick="deleteSelected()" style="width:100%; margin-top:10px; background:transparent; border:1px solid var(--red); color:var(--red);">EXCLUIR TODOS</button>`;
                if (window.lucide) lucide.createIcons();
            }
            else {
                panel.innerHTML = `<p class="empty-msg">Selecione um bloco ou linha para editar.</p>`;
            }
        }

        function zoomIn() { FlowEngine.wrapper.dispatchEvent(new WheelEvent('wheel', { deltaY: -100, clientX: window.innerWidth / 2, clientY: window.innerHeight / 2 })); }
        function zoomOut() { FlowEngine.wrapper.dispatchEvent(new WheelEvent('wheel', { deltaY: 100, clientX: window.innerWidth / 2, clientY: window.innerHeight / 2 })); }
        function resetView() {
            FlowEngine.zoom = 1;
            FlowEngine.pan = { x: window.innerWidth / 4, y: 150 };
            applyTransform();
        }

        async function saveFlow() {
            const flowName = document.getElementById('flowNameInput').value;
            const instanceId = document.getElementById('flowInstanceInput').value;

            if (!instanceId) {
                showEditorToast('Selecione uma instância antes de salvar!', 'error');
                return;
            }

            if (!flowName) {
                showEditorToast('Dê um nome ao fluxo.', 'error');
                return;
            }

            if (FlowEngine.nodes.length === 0) {
                showEditorToast('Você precisa arrastar ao menos 1 bloco.', 'error');
                return;
            }

            const payload = {
                instance_id: instanceId,
                name: flowName,
                is_active: true,
                nodes: FlowEngine.nodes,
                edges: FlowEngine.edges
            };

            try {
                showEditorToast('Salvando...');
                const response = await fetch('/wazio/index.php?route=api&action=save_flow', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const res = await response.json();
                if (res.ok) {
                    showEditorToast('✅ Fluxo salvo com sucesso e enviado p/ Banco/N8N!');
                    setTimeout(() => window.location.href = '/wazio/fluxos', 1500);
                } else {
                    showEditorToast('Erro: ' + res.erro, 'error');
                }
            } catch (e) {
                showEditorToast('Erro de Conexão com Servidor.', 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setupDragAndDrop();
            setupCanvasInteraction();

            if (FlowEngine.nodes.length > 0) {
                renderNodes();
                drawEdges();
                if (initialFlowData && initialFlowData.name) {
                    document.getElementById('flowNameInput').value = initialFlowData.name;
                }
                if (initialFlowData && initialFlowData.instance_id) {
                    document.getElementById('flowInstanceInput').value = initialFlowData.instance_id;
                }
            }
        });

        function showEditorToast(msg, type = 'success') {
            const t = document.getElementById('editorToast');
            t.innerText = msg;
            if (type === 'error') {
                t.style.background = 'var(--red)';
            } else {
                t.style.background = 'var(--green)';
                t.style.color = '#000';
            }
            t.style.display = 'block';
            setTimeout(() => t.style.display = 'none', 3000);
        }
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>