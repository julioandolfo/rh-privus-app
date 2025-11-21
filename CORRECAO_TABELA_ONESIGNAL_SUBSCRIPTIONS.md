# 🔧 Correção: Tabela onesignal_subscriptions não existe

## ❌ Problema

Erro no console do navegador:
```
POST https://privus.com.br/rh/api/onesignal/subscribe.php 500 (Internal Server Error)
Erro ao registrar subscription: SQLSTATE[42S02]: Base table or view not found: 
1146 Table 'privus_rh.onesignal_subscriptions' doesn't exist
```

## 🔍 Causa Raiz

A tabela `onesignal_subscriptions` não existe no banco de dados. Esta tabela é necessária para armazenar as subscriptions (registros) dos players do OneSignal vinculados aos usuários/colaboradores.

## ✅ Correção Implementada

### Criação Automática da Tabela

O arquivo `api/onesignal/subscribe.php` foi atualizado para **criar automaticamente** a tabela `onesignal_subscriptions` se ela não existir.

**O que foi adicionado:**
```php
// Verifica e cria a tabela onesignal_subscriptions se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'onesignal_subscriptions'");
    if ($stmt->rowCount() == 0) {
        // Cria a tabela automaticamente
        $pdo->exec("CREATE TABLE onesignal_subscriptions ...");
        
        // Tenta adicionar FOREIGN KEY se as tabelas existirem
        // (ignora se não conseguir)
    }
} catch (PDOException $e) {
    // Tratamento de erros
}
```

**Características:**
- ✅ Cria a tabela automaticamente se não existir
- ✅ Tenta adicionar FOREIGN KEY se as tabelas referenciadas existirem
- ✅ Ignora erros se FOREIGN KEY não puder ser criado (tabelas podem não existir ainda)
- ✅ Funciona mesmo se as tabelas `usuarios` ou `colaboradores` não existirem

## 📋 Arquivo Modificado

- ✅ `api/onesignal/subscribe.php` - Agora cria a tabela automaticamente se não existir

## 🧪 Como Testar

### Teste 1: Verificar se Funciona Agora

1. **Recarregue a página** (Ctrl+Shift+R)
2. Abra o **Console** (F12)
3. **Não deve aparecer** mais o erro "Table not found"
4. Deve aparecer: `✅ Player registrado com sucesso` ou similar

### Teste 2: Verificar Tabela no Banco

Execute no banco de dados:
```sql
SHOW TABLES LIKE 'onesignal_subscriptions';
```

Deve retornar a tabela `onesignal_subscriptions`.

### Teste 3: Verificar Estrutura da Tabela

```sql
DESCRIBE onesignal_subscriptions;
```

Deve mostrar:
- `id` (INT, AUTO_INCREMENT, PRIMARY KEY)
- `usuario_id` (INT, NULL)
- `colaborador_id` (INT, NULL)
- `player_id` (VARCHAR(255), UNIQUE)
- `device_type` (VARCHAR(50))
- `user_agent` (VARCHAR(500))
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### Teste 4: Verificar Subscription Registrada

Execute no banco:
```sql
SELECT * FROM onesignal_subscriptions;
```

Deve mostrar pelo menos um registro com o Player ID que foi registrado.

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
-- Tabela para subscriptions do OneSignal
CREATE TABLE IF NOT EXISTS onesignal_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    player_id VARCHAR(255) NOT NULL UNIQUE,
    device_type VARCHAR(50),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_player_id (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adiciona FOREIGN KEY se as tabelas existirem (opcional)
ALTER TABLE onesignal_subscriptions 
    ADD CONSTRAINT fk_usuario FOREIGN KEY (usuario_id) 
    REFERENCES usuarios(id) ON DELETE CASCADE;

ALTER TABLE onesignal_subscriptions 
    ADD CONSTRAINT fk_colaborador FOREIGN KEY (colaborador_id) 
    REFERENCES colaboradores(id) ON DELETE CASCADE;
```

### Opção 3: Via Arquivo SQL

Execute o arquivo `migracao_onesignal.sql` no seu banco de dados.

## 📝 Próximos Passos

Após criar a tabela:

1. **Recarregue a página** do sistema
2. **Faça login** normalmente
3. **Aguarde alguns segundos** para o OneSignal registrar o player
4. **Verifique o console** - deve aparecer mensagem de sucesso
5. **Verifique no banco** se a subscription foi registrada

## 🔍 Verificar se Funcionou

Execute no console do navegador (F12):
```javascript
// Verifica se há erros
// Deve aparecer: ✅ Player registrado com sucesso
```

Execute no banco de dados:
```sql
SELECT COUNT(*) as total FROM onesignal_subscriptions;
```

Deve retornar pelo menos 1 registro.

## 🚨 Se Ainda Der Erro

1. **Verifique permissões do banco de dados:**
   - O usuário do banco precisa ter permissão para criar tabelas

2. **Verifique logs de erro do PHP:**
   - Procure por erros relacionados a `onesignal_subscriptions`

3. **Teste a API diretamente:**
   - Faça uma requisição POST para `https://privus.com.br/rh/api/onesignal/subscribe.php`
   - Deve retornar JSON com `success: true`

4. **Verifique se as tabelas referenciadas existem:**
   ```sql
   SHOW TABLES LIKE 'usuarios';
   SHOW TABLES LIKE 'colaboradores';
   ```

---

**A correção foi aplicada. A tabela será criada automaticamente na próxima tentativa de registro!**

