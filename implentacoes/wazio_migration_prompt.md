# 🚀 WAZIO: WhatsApp Chatbot Migration Prompt

Use este prompt para continuar o desenvolvimento do **WAZIO** em uma nova conta. Este documento contém o estado atual, a arquitetura e os próximos passos para o sistema de chatbot integrado com WhatsApp em tempo real, hospedado em nuvem.

---

## 🎯 Objetivo do Projeto
Criar o **WAZIO**, uma plataforma SaaS completa de atendimento e automação via WhatsApp (estilo BotConversa/ZapData), com:
1. **Super Inbox**: Interface de chat multi-agente em tempo real.
2. **Native Flow Engine**: Construtor de fluxos (Flow Builder) rodando nativamente em PHP/Postgres, sem dependência obrigatória de N8N para automações simples.
3. **IA Integrada**: Suporte a OpenAI, Gemini, Claude e DeepSeek para respostas inteligentes.
4. **Hospedagem Cloud**: Droplet DigitalOcean com Docker, Easypanel, N8N e PostgreSQL.

---

## 🛠️ Stack Tecnológica
- **Backend**: PHP 8.x (Vanila/Custom MVC).
- **Database**: PostgreSQL 15+ (para Flows e CRM) + JSON para dados de sessão/config.
- **Frontend**: HTML5, CSS3 (Modern Dark UI), JS (Lucide Icons, Inter/Syne Fonts).
- **Infraestrutura**: Docker, N8N (para webhooks complexos e pagamentos), Evolution API/UAZAPI (Motor do WhatsApp).
- **Comunicação**: SSE (Server-Sent Events) para tempo real.

---

## 🗄️ Schema Master & Setup

### 1. PostgreSQL (Tabela de Estado de Fluxo)
Esta tabela é vital para o **Native Flow Engine** saber em que parte do fluxo o lead está.
```sql
CREATE TABLE IF NOT EXISTS lead_flow_status (
    id SERIAL PRIMARY KEY,
    instance_id VARCHAR(50) NOT NULL,
    lead_phone VARCHAR(50) NOT NULL,
    current_flow_name VARCHAR(100),
    current_node_id VARCHAR(50),
    wait_until TIMESTAMP,
    variables JSONB DEFAULT '{}'::jsonb,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(instance_id, lead_phone)
);
```

### 2. Configurações (`config.php`)
```php
define('DB_HOST', '137.184.202.248'); // Servidor Easypanel
define('DB_NAME', 'criadordigital');
define('DB_USER', 'postgres');
define('DB_PASS', '2612167B98D188F2D15D854AD8AA7');
```

---

## 📘 WAZIO API Reference (Endpoints Principais)

| Categoria | Endpoint | Descrição |
| :--- | :--- | :--- |
| **Instância** | `GET /instance/connect` | QR Code para conexão. |
| **Mensagens** | `POST /sender/text` | Envio de texto com delay e link preview. |
| **Mídia** | `POST /sender/audio` | Envio de áudio (converte p/ Opus automaticamente). |
| **IA** | `POST /ai/knowledge` | Alimentar base de conhecimento vetorial. |
| **Webhooks** | `POST /chatbot/trigger` | Disparar fluxo manualmente para um lead. |

---

## 🏆 Super Inbox Specifications
A interface deve replicar a experiência do ZapData:
- **Coluna 1 (Lista)**: Filtros por etiquetas (Pagos, Ignorados, Não Lidos).
- **Coluna 2 (Chat)**: Bolhas de chat com status "Lido" (Check laranja), Player de áudio customizado, Mensagens de Sistema.
- **Coluna 3 (Detalhes)**: Timeline de eventos do lead, Campos customizados (`lead_field01` a `lead_field20`), Botão "Pausar Fluxo".

---

## 🧠 Native Flow Engine (O Cérebro)
**Objetivo**: Implementar o interpretador de fluxos 100% PHP.
- **Componente Core**: `FlowInterpreter.php`. Recebe o Webhook `message.upsert`.
- **Lógica**: Se o lead já está em fluxo (`lead_flow_status`), processa o próximo `current_node_id`. Se não, verifica se a mensagem bate com algum "Gatilho" (Trigger).
- **Tipos de blocos**:
    - `action_text`: Envio imediato pro WhatsApp.
    - `logic_wait`: Pausa e aguarda a próxima mensagem do cliente.
    - `logic_delay`: `sleep()` ou agenda via Cron.
    - `logic_condition`: IF/ELSE baseado em Tags ou Variáveis.

---

## 🚀 Próximos Passos (Instrução para a IA)

"Com base nestas informações, implemente o script `app/Services/FlowInterpreter.php`. Ele deve ser capaz de carregar um JSON de fluxo (que representa o grafo do builder), verificar o status do lead no Postgres e disparar a ação correspondente via cURL para a WAZIO_API. Certifique-se de tratar delays e o estado 'Aguardando Resposta'."

---
**WAZ.IO — Automate, Scale, Dominate.**
