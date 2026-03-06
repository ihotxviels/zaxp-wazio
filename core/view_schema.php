<?php
require_once __DIR__ . '/../config.php';

// Estilos Wazio.io Premium — Foco em Blocos e Clareza
$css = "
<style>
    :root {
        --bg: #0b0f1a;
        --card: #161b22;
        --border: #30363d;
        --primary: #bcfd49; /* Verde Wazio */
        --text: #c9d1d9;
        --muted: #8b949e;
        --accent: #58a6ff;
    }
    body { 
        background-color: var(--bg); 
        color: var(--text); 
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; 
        margin: 0; 
        padding: 40px; 
        line-height: 1.5;
    }
    .container { max-width: 1000px; margin: 0 auto; }
    h1 { color: var(--primary); font-size: 2.5rem; margin-bottom: 5px; letter-spacing: -1px; }
    p.subtitle { color: var(--muted); margin-bottom: 40px; font-size: 1.1rem; }
    
    .module-group { margin-bottom: 60px; }
    .module-title { 
        font-size: 1.2rem; 
        color: var(--accent); 
        text-transform: uppercase; 
        letter-spacing: 2px; 
        margin-bottom: 20px; 
        border-left: 4px solid var(--accent); 
        padding-left: 15px;
    }

    .table-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0;
        margin-bottom: 30px;
        overflow: hidden;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
    }
    .table-header {
        background: rgba(48, 54, 61, 0.3);
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
    }
    .table-name { font-size: 1.4rem; font-weight: 700; color: var(--primary); font-family: monospace; }
    .table-description { color: var(--muted); font-size: 0.95rem; margin-top: 5px; }
    
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; color: var(--muted); font-size: 0.75rem; text-transform: uppercase; padding: 12px 24px; background: rgba(0,0,0,0.2); }
    td { padding: 12px 24px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
    tr:last-child td { border-bottom: none; }
    
    .badge {
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: bold;
        display: inline-block;
        margin-right: 5px;
    }
    .pk { background: rgba(188, 253, 73, 0.15); color: var(--primary); border: 1px solid rgba(188, 253, 73, 0.3); }
    .fk { background: rgba(81, 166, 255, 0.15); color: var(--accent); border: 1px solid rgba(81, 166, 255, 0.3); }
    .type { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; color: #ff7b72; font-size: 0.85rem; }
    .comment { color: var(--muted); }
    .storage-note { 
        background: rgba(188, 253, 73, 0.05); 
        padding: 10px 24px; 
        font-size: 0.85rem; 
        color: var(--primary); 
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
    }
    .storage-note:before { content: '📂'; margin-right: 10px; }
</style>
";

echo $css;
echo "<div class='container'>";
echo "<h1>Wazio.io Data Dictionary</h1>";
echo "<p class='subtitle'>Estrutura Profissional Multi-tenant (ZapData Styled)</p>";

try {
    $pdo = get_db_connection();
    if (!$pdo)
        throw new Exception("Conexão falhou.");

    $modules = [
        'GESTÃO DE CONTAS (Core)' => [
            'crm_instances' => [
                'desc' => 'Cadastro de instâncias WhatsApp e credenciais de API.',
                'storage' => 'Tokens de acesso e status de conexão persistidos para automação.'
            ],
            'crm_push_tokens' => [
                'desc' => 'Tokens de WebPush e OneSignal para alertas do painel.',
                'storage' => 'Mapeia ID do usuário SaaS -> IDs de dispositivos móveis/desktop.'
            ]
        ],
        'CRM & STORAGE DE MÍDIA' => [
            'crm_contacts' => [
                'desc' => 'Base de leads com rastreio de anúncios (Facebook/Instagram Ads).',
                'storage' => 'Armazena fotos de perfil (URL) e metadados de tráfego pago.'
            ],
            'crm_messages' => [
                'desc' => 'Histórico completo de chats e interações.',
                'storage' => 'Mapeia arquivos locais em /public/storage/media/ e URLs externas.'
            ],
            'crm_labels' => [
                'desc' => 'Sincronização de tags oficiais do WhatsApp.',
                'storage' => 'Permite filtrar leads por categoria no atendimento.'
            ]
        ],
        'MOTOR DE AUTOMAÇÃO (Engines)' => [
            'crm_funnels' => [
                'desc' => 'Definição de fluxos complexos (Drag & Drop).',
                'storage' => 'Nodes (Ações) e Edges (Conexões) salvos em formato JSONB.'
            ],
            'crm_funnel_progress' => [
                'desc' => 'Estado em tempo real dos leads nos funis.',
                'storage' => 'Garante que o motor PHP saiba onde parar e continuar fluxos longos.'
            ]
        ],
        'BI & FINANCEIRO' => [
            'crm_finance' => [
                'desc' => 'Módulo de BI para controle de ROI e faturamento.',
                'storage' => 'Registros financeiros isolados por usuário (Multi-tenant).'
            ]
        ]
    ];

    foreach ($modules as $moduleName => $tables) {
        echo "<div class='module-group'>";
        echo "<div class='module-title'>$moduleName</div>";

        foreach ($tables as $tableName => $meta) {
            echo "<div class='table-card'>";
            echo "<div class='table-header'>";
            echo "<div class='table-name'>$tableName</div>";
            echo "<div class='table-description'>{$meta['desc']}</div>";
            echo "</div>";

            echo "<table>";
            echo "<thead><tr><th>Campo</th><th>Tipo</th><th>Flags</th><th>Descrição Técnica</th></tr></thead>";
            echo "<tbody>";

            $stmt = $pdo->prepare("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position");
            $stmt->execute([$tableName]);
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($cols as $c) {
                $name = $c['column_name'];
                $flags = [];
                if ($name === 'id')
                    $flags[] = "<span class='badge pk'>PK</span>";
                if (strpos($name, '_id') !== false && $name !== 'id')
                    $flags[] = "<span class='badge fk'>FK</span>";
                if ($c['is_nullable'] === 'NO')
                    $flags[] = "<span class='badge' style='background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.2)'>NN</span>";

                $comment = "";
                if ($name === 'user_id')
                    $comment = "Primeira coluna: Isolamento total entre clientes SaaS.";
                if ($name === 'instance_name')
                    $comment = "Identificador único da conexão Evolution/Uazapi.";
                if ($name === 'phone')
                    $comment = "Número internacional formatado do lead.";
                if ($name === 'nodes')
                    $comment = "Conjunto de blocos de ação (JSONB).";
                if ($name === 'media_url')
                    $comment = "Caminho físico ou URL da mídia processada.";

                echo "<tr>";
                echo "<td><b style='color:#adbac7'>$name</b></td>";
                echo "<td class='type'>{$c['data_type']}</td>";
                echo "<td>" . implode(' ', $flags) . "</td>";
                echo "<td class='comment'>$comment</td>";
                echo "</tr>";
            }

            echo "</tbody></table>";
            if (!empty($meta['storage'])) {
                echo "<div class='storage-note'><b>Uso de Storage:</b> {$meta['storage']}</div>";
            }
            echo "</div>";
        }
        echo "</div>";
    }

    echo "<div style='text-align:center; margin-top: 50px;'>";
    echo "<a href='schema_master_setup.php' style='color:var(--primary); text-decoration:none; font-weight:bold; border: 1px solid var(--primary); padding: 12px 24px; border-radius: 8px;'>← RETORNAR AO SETUP MASTER</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
echo "</div>";
