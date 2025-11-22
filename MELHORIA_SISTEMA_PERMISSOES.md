# Melhoria do Sistema de Permissões

## Problemas Identificados no Sistema Atual

### 1. **Inconsistência na Verificação de Permissões**
- Algumas páginas usam `check_permission()` (ex: `hierarquia.php`, `fechamento_pagamentos.php`)
- Outras verificam diretamente `$_SESSION['usuario']['role']` (ex: `dashboard.php`, `minha_conta.php`)
- Menu usa verificações diretas espalhadas pelo código

### 2. **Duplicação de Código**
- Mesma lógica de verificação repetida em vários lugares
- Verificações complexas para colaboradores sem usuário vinculado (`empty($usuario_id)`)
- Lógica de permissão espalhada entre menu e páginas

### 3. **Manutenibilidade**
- Adicionar nova página requer modificar múltiplos arquivos
- Mudanças nas regras de permissão exigem alterações em vários pontos
- Difícil rastrear quais roles podem acessar cada página

### 4. **Segurança**
- Verificações inconsistentes podem deixar brechas
- Não há validação centralizada
- Difícil auditar permissões

## Solução Proposta

### Sistema Centralizado de Permissões

Criado arquivo `includes/permissions.php` com:

1. **Mapeamento Centralizado**: Todas as permissões de páginas em um único lugar
2. **Funções Reutilizáveis**: Funções padronizadas para verificação
3. **Facilidade de Manutenção**: Adicionar/modificar permissões em um único arquivo
4. **Consistência**: Mesma lógica em menu e páginas

### Funções Principais

#### `can_access_page($page)`
Verifica se o usuário pode acessar uma página específica.

#### `require_page_permission($page, $redirect_page, $message)`
Verifica permissão e redireciona se não tiver acesso (para uso em páginas).

#### `has_role($roles)`
Verifica se o usuário tem um dos roles especificados (para uso em menus e condicionais).

#### `can_show_menu($roles)`
Alias para `has_role()` com nome mais semântico para menus.

#### `is_colaborador()` e `is_colaborador_sem_usuario()`
Funções auxiliares para verificar tipo de colaborador.

## Como Usar

### Em Páginas PHP

**Antes:**
```php
require_login();

if (!check_permission('ADMIN') && !check_permission('RH')) {
    redirect('dashboard.php', 'Você não tem permissão para acessar esta página.', 'error');
}
```

**Depois:**
```php
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('fechamento_pagamentos.php');
// ou
require_page_permission(get_current_page());
```

### Em Menus

**Antes:**
```php
<?php if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH'): ?>
    <!-- Menu item -->
<?php endif; ?>
```

**Depois:**
```php
<?php require_once __DIR__ . '/permissions.php'; ?>
<?php if (can_show_menu(['ADMIN', 'RH'])): ?>
    <!-- Menu item -->
<?php endif; ?>
```

### Em Condicionais de Código

**Antes:**
```php
if ($usuario['role'] === 'COLABORADOR' && empty($usuario_id) && !empty($colaborador_id)) {
    // código
}
```

**Depois:**
```php
if (is_colaborador_sem_usuario()) {
    // código
}
```

## Vantagens

1. ✅ **Centralização**: Todas as permissões em um único arquivo
2. ✅ **Consistência**: Mesma lógica em todo o sistema
3. ✅ **Manutenibilidade**: Fácil adicionar/modificar permissões
4. ✅ **Legibilidade**: Código mais limpo e semântico
5. ✅ **Segurança**: Validação centralizada reduz brechas
6. ✅ **Testabilidade**: Mais fácil testar lógica de permissões

## Migração

A migração pode ser feita gradualmente:
1. Criar `includes/permissions.php` (✅ Feito)
2. Atualizar menu para usar novas funções
3. Atualizar páginas uma a uma
4. Remover função `check_permission()` antiga quando não for mais usada

## Exemplo de Mapeamento

```php
'fechamento_pagamentos.php' => ['ADMIN', 'RH'],
'meus_pagamentos.php' => ['COLABORADOR'],
'dashboard.php' => ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'],
```

Isso torna explícito quais roles podem acessar cada página, facilitando auditoria e manutenção.

