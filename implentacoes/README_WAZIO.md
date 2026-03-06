# 🚀 CA Panel — Guia de Deploy (CloudPanel)

## Estrutura de Arquivos

```
/var/www/seu-dominio.com/
├── index.php              ← Página de Login
├── setup.php              ← Setup inicial (DELETE após usar!)
├── config.php             ← ⚙️ CONFIGURAÇÃO CENTRAL
├── users.json             ← Banco de usuários (gerado automaticamente)
├── .htaccess              ← Segurança Apache
├── api/
│   └── api.php            ← API REST central
├── admin/
│   └── dashboard.php      ← Painel do ADMINISTRADOR
├── user/
│   └── dashboard.php      ← Painel do USUÁRIO
├── logs/
│   └── users.log          ← Log de ações (criado automaticamente)
└── crm_workflow_v2.json ← Workflow n8n (importe no n8n)
```

---

## 📋 Passo a Passo de Deploy

### 1. Upload dos Arquivos

Faça upload de todos os arquivos PHP para a raiz do domínio no CloudPanel.

```bash
# Via SSH no servidor:
cd /var/www/seu-dominio.com/
# Suba os arquivos via FTP, SCP ou o gerenciador de arquivos do CloudPanel
```

### 2. Permissões

```bash
# Na raiz do projeto
chmod 755 admin/ user/ api/
chmod 644 *.php .htaccess
chmod 777 logs/          # ou 755 se o PHP rodar como o dono
touch logs/users.log
chmod 666 logs/users.log
touch users.json
chmod 666 users.json
```

### 3. Editar config.php

Abra `config.php` e ajuste:

```php
define('UAZAPI_URL',    'https://SUA-URL-UAZAPI.com');   // URL da sua Uazapi
define('ADMIN_TOKEN',   'SEU_ADMIN_TOKEN');               // Admin token Uazapi
define('PANEL_SECRET',  'TROQUE_ESTA_CHAVE_SECRETA');     // Chave para o n8n
define('GRUPO_CONTINGENCIA', '120363XXXXXX@g.us');        // Grupo de contingência
define('TOKEN_AVISOS',  'TOKEN_DO_CHIP_PRINCIPAL');       // Token do chip de avisos
```

### 4. Configurar Senha Admin

Acesse: `https://seu-dominio.com/setup.php`

1. Digite a senha desejada para o admin
2. Confirme e salve
3. **🔴 IMPORTANTE: Delete o setup.php após isso!**

```bash
rm /var/www/seu-dominio.com/setup.php
```

### 5. Primeiro Login

Acesse `https://seu-dominio.com/` e entre com:
- **Usuário:** `admin`
- **Senha:** a que você definiu no setup.php

---

## 👥 Gerenciar Usuários

No painel admin → Aba "Usuários" → "Novo Usuário":

| Campo | Descrição |
|-------|-----------|
| Username | Login do usuário |
| Nome | Nome exibido |
| Senha | Mínimo 6 caracteres |
| Perfil | `admin` (acesso total) ou `user` (limitado) |
| Instâncias | Para usuários do tipo `user`, selecione quais instâncias serão visíveis |

---

## 🔌 Workflow N8n

### Importar

1. Abra seu n8n
2. Menu → Import from JSON
3. Cole o conteúdo de `crm_workflow_v2.json`

### Configurar o node `⚙️ CONFIG GLOBAL`

| Variável | Valor |
|----------|-------|
| `ADMIN_TOKEN` | Seu admintoken da Uazapi |
| `BASE_URL` | URL base da Uazapi |
| `NUMERO_NOTIFICACAO` | Seu número pessoal (para alertas) |
| `TOKEN_AVISOS` | Token do chip que envia as mensagens |
| `GRUPO_CONTINGENCIA` | ID do grupo `120363XXX@g.us` |
| `PANEL_URL` | URL do seu painel PHP |
| `PANEL_SECRET` | Mesma chave do `config.php` |
| `TERMOS_IGNORADOS` | Nomes a ignorar no monitor (ex: `murilo,teste`) |

### Ativar o workflow

Clique em **Activate** no n8n. O monitor de 30 minutos passa a rodar automaticamente.

---

## 🔗 Endpoints da API (para integrar com outros sistemas)

### Autenticação
```http
POST /api/api.php?action=login
{ "username": "admin", "password": "senha" }
```

### Listar Instâncias
```http
GET /api/api.php?action=instancias
```

### Criar Instância
```http
POST /api/api.php?action=criar
{ "name": "minha_instancia", "webhookUrl": "https://..." }
```

### Reconectar
```http
POST /api/api.php?action=reconectar
{ "token": "TOKEN_DA_INSTANCIA" }
```

### Desconectar
```http
POST /api/api.php?action=desconectar
{ "token": "TOKEN_DA_INSTANCIA" }
```

### Excluir
```http
DELETE /api/api.php?action=excluir
{ "token": "TOKEN_DA_INSTANCIA", "name": "nome" }
```

### Configurar Proxy
```http
POST /api/api.php?action=proxy_set
{ "token": "TOKEN", "host": "proxy.host.com", "port": "8080", "protocol": "http" }
```

### Remover Proxy
```http
DELETE /api/api.php?action=proxy_del
{ "token": "TOKEN" }
```

### Criar Usuário
```http
POST /api/api.php?action=usuario_criar
{ "username": "joao", "password": "123456", "nome": "João", "role": "user", "instancias": ["chip1","chip2"] }
```

### Ver Logs
```http
GET /api/api.php?action=logs
```

---

## 🛡️ Segurança

- ✅ Senhas armazenadas com `password_hash()` (bcrypt)
- ✅ Sessões com TTL de 1 hora
- ✅ Controle de acesso por role (admin/user)
- ✅ Log de todas as ações em `logs/users.log`
- ✅ `users.json` bloqueado via `.htaccess`
- ✅ API integração n8n autenticada via `x-panel-secret`
- ⚠️ Delete o `setup.php` após o primeiro uso!
- ⚠️ Mantenha o `PANEL_SECRET` do `config.php` em segredo

---

## 📊 Fluxo do Monitor de Desconexão

```
[A cada 30 min]
    ↓
[Buscar todas instâncias Uazapi]
    ↓
[Filtrar desconectadas] → ignora termos configurados
    ↓
[Enviar alerta no GRUPO DE CONTINGÊNCIA]
    ↓
[Notificar Painel PHP via API] → registra no log
    ↓
[Enviar alerta no NÚMERO PESSOAL]
```

---

## ❓ Solução de Problemas

**API retorna 403:** Verifique se está logado ou se o `x-panel-secret` está correto.

**QR Code não aparece:** Confirme que o token da instância está correto e que a Uazapi está acessível.

**Monitor não envia alertas:** Verifique se o workflow n8n está ativo e se o `TOKEN_AVISOS` tem permissão de envio.

**`users.json` bloqueado:** É intencional. O arquivo só é acessado pelo PHP internamente.
