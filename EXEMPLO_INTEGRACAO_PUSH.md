# 💡 Exemplo Prático: Integrar Push Notifications

## 🎯 Como Usar as Funções Helper

### Exemplo 1: Notificar Colaborador ao Criar Ocorrência

**Arquivo:** `pages/ocorrencias_add.php`

**Localização:** Após criar a ocorrência (linha ~84)

```php
// ... código existente de criação de ocorrência ...

$ocorrencia_id = $pdo->lastInsertId();

// Envia email (já existe)
require_once __DIR__ . '/../includes/email_templates.php';
enviar_email_ocorrencia($ocorrencia_id);

// ✅ NOVO: Envia notificação push
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $colaborador_id,
    'Nova Ocorrência Registrada',
    'Uma nova ocorrência foi registrada no seu perfil. Clique para ver detalhes.',
    '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id
);

redirect('colaborador_view.php?id=' . $colaborador_id, 'Ocorrência registrada com sucesso!');
```

---

### Exemplo 2: Notificar ao Aprovar Promoção

**Arquivo:** `pages/promocoes.php` (ou onde aprova promoções)

```php
// Após aprovar promoção
require_once __DIR__ . '/../includes/push_notifications.php';

enviar_push_colaborador(
    $colaborador_id,
    'Promoção Aprovada! 🎉',
    'Parabéns! Sua promoção foi aprovada.',
    '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id
);
```

---

### Exemplo 3: Notificar Múltiplos Colaboradores (Setor)

```php
require_once __DIR__ . '/../includes/push_notifications.php';

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE setor_id = ? AND status = 'ativo'");
$stmt->execute([$setor_id]);
$colaboradores = $stmt->fetchAll(PDO::FETCH_COLUMN);

enviar_push_colaboradores(
    $colaboradores,
    'Reunião de Setor',
    'Reunião marcada para amanhã às 14h na sala de reuniões.',
    '/rh-privus/pages/dashboard.php'
);
```

---

### Exemplo 4: Notificar Usuário Específico

```php
require_once __DIR__ . '/../includes/push_notifications.php';

enviar_push_usuario(
    $usuario_id,
    'Nova Tarefa',
    'Você recebeu uma nova tarefa para revisar.',
    '/rh-privus/pages/dashboard.php'
);
```

---

## 🔧 Funções Disponíveis

### `enviar_push_colaborador($colaborador_id, $titulo, $mensagem, $url = null)`

Envia notificação para um colaborador específico.

**Parâmetros:**
- `$colaborador_id` (int) - ID do colaborador
- `$titulo` (string) - Título da notificação
- `$mensagem` (string) - Mensagem da notificação
- `$url` (string, opcional) - URL para abrir ao clicar

**Retorno:**
```php
[
    'success' => true/false,
    'enviadas' => 1,
    'message' => 'Notificação enviada com sucesso'
]
```

---

### `enviar_push_usuario($usuario_id, $titulo, $mensagem, $url = null)`

Envia notificação para um usuário específico.

**Parâmetros:** Mesmos de `enviar_push_colaborador`

---

### `enviar_push_colaboradores($colaboradores_ids, $titulo, $mensagem, $url = null)`

Envia notificação para múltiplos colaboradores.

**Parâmetros:**
- `$colaboradores_ids` (array) - Array com IDs dos colaboradores
- Demais parâmetros iguais

**Retorno:**
```php
[
    'success' => true/false,
    'enviadas' => 5, // Quantidade enviada
    'falhas' => 0    // Quantidade que falhou
]
```

---

## 📝 Exemplos de Uso em Diferentes Cenários

### Cenário 1: Ocorrência Criada

```php
// Em pages/ocorrencias_add.php
enviar_push_colaborador(
    $colaborador_id,
    'Nova Ocorrência',
    'Uma nova ocorrência foi registrada no seu perfil',
    '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id
);
```

### Cenário 2: Fechamento de Pagamento

```php
// Em pages/fechamento_pagamentos.php
enviar_push_colaborador(
    $colaborador_id,
    'Pagamento Processado',
    'Seu pagamento foi processado e estará disponível em breve',
    '/rh-privus/pages/colaborador_view.php?id=' . $colaborador_id
);
```

### Cenário 3: Lembrete de Ponto

```php
// Em algum cron job ou tarefa agendada
$pdo = getDB();
$stmt = $pdo->query("SELECT id FROM colaboradores WHERE status = 'ativo'");
$colaboradores = $stmt->fetchAll(PDO::FETCH_COLUMN);

enviar_push_colaboradores(
    $colaboradores,
    'Lembrete: Fechar Ponto',
    'Não esqueça de fechar seu ponto hoje!',
    '/rh-privus/pages/dashboard.php'
);
```

---

## 🎯 Boas Práticas

### 1. Sempre Trate Erros

```php
$result = enviar_push_colaborador($colaborador_id, $titulo, $mensagem);

if (!$result['success']) {
    // Log do erro, mas não interrompe o fluxo
    error_log('Erro ao enviar push: ' . $result['message']);
}
```

### 2. Use URLs Absolutas Quando Possível

```php
// ✅ Bom
enviar_push_colaborador($id, 'Título', 'Mensagem', '/rh-privus/pages/dashboard.php');

// ✅ Melhor (usa função helper)
$url = get_base_url() . '/pages/dashboard.php';
enviar_push_colaborador($id, 'Título', 'Mensagem', $url);
```

### 3. Mensagens Claras e Concisas

```php
// ✅ Bom
enviar_push_colaborador($id, 'Nova Ocorrência', 'Uma ocorrência foi registrada');

// ❌ Evite
enviar_push_colaborador($id, 'Ocorrência', 'Ocorrência');
```

---

## 🧪 Testar Notificações

### Teste Manual via PHP

Crie `test_push.php`:

```php
<?php
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/push_notifications.php';

// Simula usuário logado
$_SESSION['usuario'] = [
    'id' => 1,
    'role' => 'ADMIN'
];

// Teste: Envia para colaborador ID 1
$result = enviar_push_colaborador(
    1,
    'Teste de Notificação',
    'Esta é uma notificação de teste!',
    '/rh-privus/pages/dashboard.php'
);

echo "<pre>";
print_r($result);
echo "</pre>";
```

Acesse: `http://localhost/rh-privus/test_push.php`

---

## ✅ Checklist de Integração

- [ ] Incluir `push_notifications.php` no arquivo
- [ ] Chamar função após ação relevante
- [ ] Testar notificação manualmente
- [ ] Verificar se colaborador recebe
- [ ] Tratar erros adequadamente

---

**Pronto para integrar!** 🚀

