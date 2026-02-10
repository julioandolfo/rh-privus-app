# 🔔 Push Notifications - Implementação nos Módulos

## ✅ Status da Implementação

| Módulo | Status | Localização | Ação que Dispara |
|--------|--------|-------------|------------------|
| **Promoções** | ✅ Implementado | `pages/promocoes.php` (linha 50-57) | Ao registrar promoção |
| **Ocorrências** | ✅ Implementado | `includes/ocorrencias_functions.php` (linha 438-451) | Ao registrar ocorrência |
| **Horas Extras** | ⏳ Implementar | `pages/aprovar_horas_extras.php` | Ao aprovar horas extras |
| **Fechamento Pagamento** | ⏳ Implementar | `pages/fechamento_pagamentos.php` | Ao fechar pagamento |
| **Comunicados** | ⏳ Implementar | `pages/comunicados.php` | Ao publicar comunicado |
| **Eventos** | ⏳ Implementar | `pages/eventos.php` | Ao convidar para evento |
| **Feedback** | ⏳ Implementar | `pages/solicitacoes_feedback.php` | Ao solicitar feedback |
| **Férias** | ⏳ Implementar | `pages/ferias.php` | Ao aprovar férias |
| **Documentos** | ⏳ Implementar | `pages/documentos_colaborador.php` | Ao fazer upload |
| **Cursos/LMS** | ⏳ Implementar | `pages/lms_atribuir_curso.php` | Ao atribuir curso |

---

## 📝 Código para Implementar nos Módulos

### 1. Horas Extras (`pages/aprovar_horas_extras.php` ou API de aprovação)

**Adicionar após aprovação da hora extra:**

```php
// ... código existente de aprovação ...
$stmt = $pdo->prepare("UPDATE horas_extras SET status = 'aprovada', aprovado_por = ?, data_aprovacao = NOW() WHERE id = ?");
$stmt->execute([$usuario['id'], $hora_extra_id]);

// Busca dados da hora extra
$stmt = $pdo->prepare("
    SELECT he.*, c.nome_completo
    FROM horas_extras he
    INNER JOIN colaboradores c ON he.colaborador_id = c.id
    WHERE he.id = ?
");
$stmt->execute([$hora_extra_id]);
$hora_extra = $stmt->fetch();

// ✅ Envia notificação push
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $hora_extra['colaborador_id'],
    'Horas Extras Aprovadas! ⏰',
    'Suas ' . number_format($hora_extra['quantidade_horas'], 2, ',', '.') . ' horas extras foram aprovadas e serão pagas.',
    'pages/meus_pagamentos.php',
    'horas_extras',
    $hora_extra_id,
    'hora_extra'
);
```

---

### 2. Fechamento de Pagamento (`pages/fechamento_pagamentos.php`)

**Adicionar após fechar pagamento para cada colaborador:**

```php
// ... código existente de fechamento ...
$stmt->execute([$fechamento_id, $colaborador_id, $salario_base, $valor_total]);

// ✅ Envia notificação push
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $colaborador_id,
    'Pagamento Processado 💰',
    'Seu pagamento de ' . $mes_referencia . ' foi processado. Valor: R$ ' . number_format($valor_total, 2, ',', '.'),
    'pages/meus_pagamentos.php',
    'fechamento_pagamento',
    $fechamento_id,
    'pagamento'
);
```

---

### 3. Comunicados (`pages/comunicados.php`)

**Adicionar após publicar comunicado:**

```php
// ... código existente de publicação ...
$comunicado_id = $pdo->lastInsertId();

// Busca colaboradores ativos
$stmt = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo'");
$colaboradores = $stmt->fetchAll();

// ✅ Envia push para todos os colaboradores
require_once __DIR__ . '/../includes/push_notifications.php';
foreach ($colaboradores as $colab) {
    enviar_push_colaborador(
        $colab['id'],
        'Novo Comunicado 📢',
        substr($titulo_comunicado, 0, 150) . '...',
        'pages/comunicados.php?id=' . $comunicado_id,
        'comunicado',
        $comunicado_id,
        'comunicado'
    );
}
```

---

### 4. Eventos (`pages/eventos.php` ou função de convite)

**Adicionar ao convidar colaboradores:**

```php
// ... código existente de convite ...

// ✅ Envia push para cada convidado
require_once __DIR__ . '/../includes/push_notifications.php';
foreach ($colaboradores_ids as $colab_id) {
    // Busca nome do colaborador
    $stmt = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
    $stmt->execute([$colab_id]);
    $colab = $stmt->fetch();
    
    enviar_push_colaborador(
        $colab_id,
        'Convite: ' . $evento['titulo'] . ' 📅',
        'Você foi convidado para um evento em ' . formatar_data($evento['data_evento']),
        'pages/meus_eventos.php',
        'evento',
        $evento_id,
        'evento'
    );
}
```

---

### 5. Solicitação de Feedback (`pages/solicitacoes_feedback.php`)

**Adicionar ao criar solicitação:**

```php
// ... código existente de criação ...
$solicitacao_id = $pdo->lastInsertId();

// Busca dados
$stmt = $pdo->prepare("
    SELECT sf.*, 
           avaliado.nome_completo as avaliado_nome,
           avaliador.nome_completo as avaliador_nome
    FROM solicitacoes_feedback sf
    INNER JOIN colaboradores avaliado ON sf.colaborador_avaliado_id = avaliado.id
    INNER JOIN colaboradores avaliador ON sf.colaborador_avaliador_id = avaliador.id
    WHERE sf.id = ?
");
$stmt->execute([$solicitacao_id]);
$solicitacao = $stmt->fetch();

// ✅ Envia push para o avaliador
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $solicitacao['colaborador_avaliador_id'],
    'Nova Solicitação de Feedback 💭',
    'Você foi solicitado a avaliar ' . $solicitacao['avaliado_nome'],
    'pages/responder_feedback.php?id=' . $solicitacao_id,
    'feedback',
    $solicitacao_id,
    'feedback_solicitacao'
);
```

---

### 6. Férias (`pages/ferias.php`)

**Adicionar ao aprovar solicitação:**

```php
// ... código existente de aprovação ...
$stmt = $pdo->prepare("UPDATE ferias SET status = 'aprovada', aprovado_por = ?, data_aprovacao = NOW() WHERE id = ?");
$stmt->execute([$usuario['id'], $ferias_id]);

// Busca dados das férias
$stmt = $pdo->prepare("
    SELECT f.*, c.nome_completo
    FROM ferias f
    INNER JOIN colaboradores c ON f.colaborador_id = c.id
    WHERE f.id = ?
");
$stmt->execute([$ferias_id]);
$ferias = $stmt->fetch();

// ✅ Envia push
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $ferias['colaborador_id'],
    'Férias Aprovadas! 🏖️',
    'Suas férias de ' . formatar_data($ferias['data_inicio']) . ' até ' . formatar_data($ferias['data_fim']) . ' foram aprovadas!',
    'pages/minhas_ferias.php',
    'ferias',
    $ferias_id,
    'ferias'
);
```

---

### 7. Documentos (`pages/documentos_colaborador.php`)

**Adicionar ao fazer upload:**

```php
// ... código existente de upload ...
$documento_id = $pdo->lastInsertId();

// ✅ Envia push
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $colaborador_id,
    'Novo Documento Disponível 📄',
    'Um novo documento foi adicionado: ' . $nome_documento,
    'pages/meus_documentos.php',
    'documento',
    $documento_id,
    'documento'
);
```

---

### 8. Cursos/LMS (`pages/lms_atribuir_curso.php`)

**Adicionar ao atribuir curso:**

```php
// ... código existente de atribuição ...
$atribuicao_id = $pdo->lastInsertId();

// Busca dados do curso
$stmt = $pdo->prepare("SELECT titulo FROM lms_cursos WHERE id = ?");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();

// ✅ Envia push
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $colaborador_id,
    'Novo Curso Atribuído 📚',
    'O curso "' . $curso['titulo'] . '" foi atribuído para você',
    'pages/meus_cursos.php',
    'curso',
    $atribuicao_id,
    'lms_atribuicao'
);
```

---

### 9. Aniversários (Automático via Cron)

**Criar arquivo: `cron/enviar_parabens_aniversario.php`**

```php
<?php
/**
 * Cron: Envia parabéns de aniversário
 * Executar diariamente às 9h
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/push_notifications.php';

$pdo = getDB();

// Busca aniversariantes do dia
$stmt = $pdo->query("
    SELECT id, nome_completo 
    FROM colaboradores 
    WHERE DAY(data_nascimento) = DAY(CURDATE()) 
    AND MONTH(data_nascimento) = MONTH(CURDATE())
    AND status = 'ativo'
");
$aniversariantes = $stmt->fetchAll();

foreach ($aniversariantes as $aniv) {
    // Envia push de parabéns
    enviar_push_colaborador(
        $aniv['id'],
        'Feliz Aniversário! 🎂',
        'A equipe RH Privus deseja um feliz aniversário! Que seu dia seja especial!',
        'pages/dashboard.php',
        'aniversario',
        null,
        null
    );
}

echo "Parabéns enviados para " . count($aniversariantes) . " aniversariantes.\n";
```

---

## 🎯 Padrão de Implementação

Para qualquer novo módulo que queira adicionar push, use este padrão:

```php
require_once __DIR__ . '/../includes/push_notifications.php';

enviar_push_colaborador(
    $colaborador_id,           // ID do colaborador
    'Título com Emoji 🎉',     // Título curto e claro
    'Mensagem detalhada',      // Mensagem completa
    'pages/destino.php',       // URL de destino
    'tipo_notificacao',        // Tipo (promocao, ocorrencia, etc)
    $referencia_id,            // ID do item criado/modificado
    'tipo_referencia'          // Tipo da referência
);
```

---

## ✅ Checklist de Implementação

Ao adicionar push em um módulo:

- [ ] Incluir `push_notifications.php`
- [ ] Chamar função após ação concluída com sucesso
- [ ] Usar título claro com emoji (máx 50 caracteres)
- [ ] Mensagem descritiva (máx 200 caracteres)
- [ ] URL de destino correta
- [ ] Tipo e referência definidos
- [ ] Tratar erros com try-catch se necessário
- [ ] Testar o envio

---

## 📊 Onde Encontrar os Arquivos

```
rh-privus/
├── pages/
│   ├── promocoes.php                    ✅ COM PUSH
│   ├── aprovar_horas_extras.php        ⏳ ADICIONAR
│   ├── fechamento_pagamentos.php       ⏳ ADICIONAR
│   ├── comunicados.php                 ⏳ ADICIONAR
│   ├── eventos.php                     ⏳ ADICIONAR
│   ├── solicitacoes_feedback.php       ⏳ ADICIONAR
│   ├── ferias.php                      ⏳ ADICIONAR
│   ├── documentos_colaborador.php      ⏳ ADICIONAR
│   └── lms_atribuir_curso.php          ⏳ ADICIONAR
├── includes/
│   └── ocorrencias_functions.php       ✅ COM PUSH
└── cron/
    └── enviar_parabens_aniversario.php  ⏳ CRIAR
```

---

**🎉 Sistema de Push implementado e pronto para expansão em todos os módulos!**

**⏱️ Tempo estimado por módulo: 5-10 minutos**
