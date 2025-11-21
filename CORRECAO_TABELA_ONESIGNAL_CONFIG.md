# 🔧 Correção: Tabela onesignal_config não existe

## ❌ Problema

Erro no console do navegador:
```
onesignal-init.js:62 Erro na resposta: 
{appId: null, safariWebId: null, message: 'Tabela onesignal_config não existe. Execute a migração primeiro.', error: 'Table not found'}

onesignal-init.js:185 Erro ao inicializar OneSignal: Error: Table not found
```

## 🔍 Causa Raiz

A tabela `onesignal_config` não existe no banco de dados. Esta tabela é necessária para armazenar as credenciais do OneSignal (App ID, REST API Key, Safari Web ID).

## ✅ Correção Implementada

### 1. Criação Automática da Tabela

O arquivo `api/onesignal/config.php` foi atualizado para **criar automaticamente** a tabela `onesignal_config` se ela não existir.

**Antes:**
```php
try {
    $stmt = $pdo->query("SELECT app_id, safari_web_id FROM onesignal_config ...");
} catch (PDOException $e) {
    // Apenas retornava erro
    echo json_encode(['error' => 'Table not found']);
}
```

**Depois:**
```php
// Verifica e cria a tabela se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'onesignal_config'");
    if ($stmt->rowCount() == 0) {
        // Cria a tabela automaticamente
        $pdo->exec("CREATE TABLE onesignal_config ...");
    }
} catch (PDOException $e) {
    // Tratamento de erros melhorado
}
```

## 📋 Arquivo Modificado

- ✅ `api/onesignal/config.php` - Agora cria a tabela automaticamente se não existir

## 🧪 Como Testar

### Teste 1: Verificar se Funciona Agora

1. **Recarregue a página** (Ctrl+Shift+R)
2. Abra o **Console** (F12)
3. **Não deve aparecer** mais o erro "Table not found"
4. Se OneSignal não estiver configurado, aparecerá mensagem: "OneSignal não configurado"

### Teste 2: Verificar Tabela no Banco

Execute no banco de dados:
```sql
SHOW TABLES LIKE 'onesignal_config';
```

Deve retornar a tabela `onesignal_config`.

### Teste 3: Verificar Estrutura da Tabela

```sql
DESCRIBE onesignal_config;
```

Deve mostrar:
- `id` (INT, AUTO_INCREMENT, PRIMARY KEY)
- `app_id` (VARCHAR(255))
- `rest_api_key` (VARCHAR(255))
- `safari_web_id` (VARCHAR(255), NULL)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

## 🔧 Criar Tabelas Manualmente (Se Necessário)

Se ainda der erro, você pode criar as tabelas manualmente:

### Opção 1: Via Script PHP

Acesse no navegador:
```
https://privus.com.br/rh/executar_migracao_onesignal.php
```

Ou:
```
https://privus.com.br/rh/criar_tabelas_onesignal.php
```

### Opção 2: Via SQL Direto

Execute no banco de dados (phpMyAdmin, HeidiSQL, etc):

```sql
-- Tabela para configurações do OneSignal
CREATE TABLE IF NOT EXISTS onesignal_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id VARCHAR(255) NOT NULL,
    rest_api_key VARCHAR(255) NOT NULL,
    safari_web_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para subscriptions do OneSignal (também necessária)
CREATE TABLE IF NOT EXISTS onesignal_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    player_id VARCHAR(255) NOT NULL UNIQUE,
    device_type VARCHAR(50),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_player_id (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Opção 3: Via Arquivo SQL

Execute o arquivo `migracao_onesignal.sql` no seu banco de dados.

## 📝 Próximos Passos

Após criar a tabela:

1. **Configure o OneSignal:**
   - Acesse: `https://privus.com.br/rh/pages/configuracoes_onesignal.php`
   - Preencha o **App ID** e **REST API Key** do OneSignal
   - Clique em **Salvar**

2. **Verifique se Funcionou:**
   - Recarregue qualquer página do sistema
   - Abra o Console (F12)
   - Deve aparecer: `✅ OneSignal inicializado` ou similar

## 🚨 Se Ainda Der Erro

1. **Verifique permissões do banco de dados:**
   - O usuário do banco precisa ter permissão para criar tabelas

2. **Verifique logs de erro do PHP:**
   - Procure por erros relacionados a `onesignal_config`

3. **Teste a API diretamente:**
   ```
   https://privus.com.br/rh/api/onesignal/config.php
   ```
   Deve retornar JSON (mesmo que com `appId: null` se não configurado)

---

**A correção foi aplicada. A tabela será criada automaticamente na próxima requisição!**

