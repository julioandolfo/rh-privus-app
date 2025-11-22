# Exemplo de Refatoração do Menu

## Antes (Sistema Atual)

```php
<?php if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH'): ?>
    <!--begin:Menu item-->
    <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
        <span class="menu-link">
            <span class="menu-title">Estrutura</span>
        </span>
        <!-- ... -->
    </div>
<?php endif; ?>

<?php if ($usuario['role'] !== 'COLABORADOR'): ?>
    <!--begin:Menu item-->
    <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
        <span class="menu-link">
            <span class="menu-title">Colaboradores</span>
        </span>
        <!-- ... -->
    </div>
<?php endif; ?>

<?php if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH'): ?>
    <!--begin:Menu item-->
    <div class="menu-item">
        <a class="menu-link" href="promocoes.php">Promoções</a>
    </div>
<?php endif; ?>
```

## Depois (Sistema Proposto)

```php
<?php require_once __DIR__ . '/permissions.php'; ?>

<?php if (can_show_menu(['ADMIN', 'RH'])): ?>
    <!--begin:Menu item-->
    <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
        <span class="menu-link">
            <span class="menu-title">Estrutura</span>
        </span>
        <!-- ... -->
    </div>
<?php endif; ?>

<?php if (can_show_menu(['ADMIN', 'RH', 'GESTOR'])): ?>
    <!--begin:Menu item-->
    <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
        <span class="menu-link">
            <span class="menu-title">Colaboradores</span>
        </span>
        <!-- ... -->
    </div>
<?php endif; ?>

<?php if (can_show_menu(['ADMIN', 'RH'])): ?>
    <!--begin:Menu item-->
    <div class="menu-item">
        <a class="menu-link" href="promocoes.php">Promoções</a>
    </div>
<?php endif; ?>
```

## Vantagens

1. **Mais Legível**: `can_show_menu(['ADMIN', 'RH'])` é mais claro que `$usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH'`
2. **Consistente**: Usa a mesma função em todo o menu
3. **Fácil Manutenção**: Se precisar adicionar 'GESTOR' em algum lugar, só muda o array
4. **Centralizado**: A lógica de permissão está em `permissions.php`

## Exemplo Completo: Menu Colaborador

**Antes:**
```php
<?php if ($usuario['role'] === 'COLABORADOR'): ?>
    <div class="menu-item">
        <a class="menu-link" href="colaborador_view.php?id=<?= $usuario['colaborador_id'] ?>">
            <span class="menu-title">Meu Perfil</span>
        </a>
    </div>
    <div class="menu-item">
        <a class="menu-link" href="meus_pagamentos.php">
            <span class="menu-title">Meus Pagamentos</span>
        </a>
    </div>
<?php endif; ?>
```

**Depois:**
```php
<?php if (can_show_menu('COLABORADOR')): ?>
    <div class="menu-item">
        <a class="menu-link" href="colaborador_view.php?id=<?= $usuario['colaborador_id'] ?>">
            <span class="menu-title">Meu Perfil</span>
        </a>
    </div>
    <?php if (can_access_page('meus_pagamentos.php')): ?>
    <div class="menu-item">
        <a class="menu-link" href="meus_pagamentos.php">
            <span class="menu-title">Meus Pagamentos</span>
        </a>
    </div>
    <?php endif; ?>
<?php endif; ?>
```

Note que `can_access_page()` pode ser usado diretamente no menu para verificar se a página específica pode ser acessada, garantindo consistência entre menu e permissões reais.

