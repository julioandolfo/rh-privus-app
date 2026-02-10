# 🚀 Guia Rápido: Como Adicionar Notificação Push em Qualquer Módulo

## 📋 Template Básico

Copie e cole este código onde você quer enviar notificação push:

```php
// Inclui o sistema de push
require_once __DIR__ . '/../includes/push_notifications.php';

// Envia notificação push
$push_result = enviar_push_colaborador(
    $colaborador_id,                    // ID do colaborador
    'Título da Notificação',            // Título curto e claro
    'Mensagem explicativa completa',    // Mensagem detalhada
    'pages/pagina_destino.php',         // URL de referência
    'tipo_notificacao',                 // Tipo (veja lista abaixo)
    $referencia_id,                     // ID do item criado/modificado
    'tipo_referencia'                   // Tipo da referência
);

// Opcional: Log do resultado
if ($push_result['success']) {
    // Sucesso
} else {
    // Erro: $push_result['message']
}
```

---

## 📝 Tipos de Notificação Sugeridos

| Tipo | Descrição | Ícone Sugerido |
|------|-----------|----------------|
| `promocao` | Promoção de colaborador | 🎉 |
| `ocorrencia` | Nova ocorrência | ⚠️ |
| `horas_extras` | Horas extras | ⏰ |
| `fechamento_pagamento` | Fechamento de pagamento | 💰 |
| `evento` | Convite para evento | 📅 |
| `comunicado` | Novo comunicado | 📢 |
| `feedback` | Solicitação de feedback | 💭 |
| `curso` | Curso atribuído | 📚 |
| `documento` | Novo documento | 📄 |
| `beneficio` | Benefício concedido | 🎁 |
| `ferias` | Férias aprovadas | 🏖️ |
| `aniversario` | Aniversário | 🎂 |
| `geral` | Notificação geral | 🔔 |

---

## 💡 Exemplos Práticos

### 1. Ocorrências (`pages/ocorrencias_add.php`)

**Onde adicionar:** Logo após criar a ocorrência

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
    'Nova Ocorrência Registrada ⚠️',
    'Uma ocorrência foi registrada no seu perfil. Tipo: ' . $tipo_ocorrencia,
    'pages/colaborador_view.php?id=' . $colaborador_id,
    'ocorrencia',
    $ocorrencia_id,
    'ocorrencia'
);

redirect('colaborador_view.php?id=' . $colaborador_id, 'Ocorrência registrada com sucesso!');
```

---

### 2. Horas Extras (`pages/horas_extras.php`)

**Onde adicionar:** Logo após aprovar horas extras

```php
// ... código existente de aprovação ...
$stmt = $pdo->prepare("UPDATE horas_extras SET status = 'aprovada' WHERE id = ?");
$stmt->execute([$hora_extra_id]);

// Busca dados da hora extra
$stmt = $pdo->prepare("SELECT * FROM horas_extras WHERE id = ?");
$stmt->execute([$hora_extra_id]);
$hora_extra = $stmt->fetch();

// ✅ Envia notificação push
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $hora_extra['colaborador_id'],
    'Horas Extras Aprovadas ⏰',
    'Suas ' . $hora_extra['quantidade_horas'] . ' horas extras foram aprovadas!',
    'pages/meus_pagamentos.php',
    'horas_extras',
    $hora_extra_id,
    'hora_extra'
);

redirect('horas_extras.php', 'Horas extras aprovadas!');
```

---

### 3. Fechamento de Pagamento (`pages/fechamento_pagamentos.php`)

**Onde adicionar:** Ao fechar pagamento para um colaborador

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

### 4. Comunicados (`pages/comunicados.php`)

**Onde adicionar:** Ao publicar comunicado

```php
// ... código existente de publicação ...
$comunicado_id = $pdo->lastInsertId();

// Busca todos colaboradores
$stmt = $pdo->query("SELECT id FROM colaboradores WHERE status = 'ativo'");
$colaboradores = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ✅ Envia push para todos
require_once __DIR__ . '/../includes/push_notifications.php';
foreach ($colaboradores as $colab_id) {
    enviar_push_colaborador(
        $colab_id,
        'Novo Comunicado 📢',
        substr($comunicado['titulo'], 0, 100) . '...',
        'pages/comunicados.php?id=' . $comunicado_id,
        'comunicado',
        $comunicado_id,
        'comunicado'
    );
}
```

---

### 5. Eventos (`pages/eventos.php`)

**Onde adicionar:** Ao convidar colaboradores para evento

```php
// ... código existente de convite ...

// ✅ Envia push para cada convidado
require_once __DIR__ . '/../includes/push_notifications.php';
foreach ($colaboradores_ids as $colab_id) {
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

### 6. Solicitação de Feedback (`pages/solicitacoes_feedback.php`)

**Onde adicionar:** Ao criar solicitação de feedback

```php
// ... código existente de criação ...
$solicitacao_id = $pdo->lastInsertId();

// ✅ Envia push para o avaliador
require_once __DIR__ . '/../includes/push_notifications.php';
enviar_push_colaborador(
    $avaliador_id,
    'Nova Solicitação de Feedback 💭',
    'Você foi solicitado a avaliar ' . $avaliado_nome,
    'pages/responder_feedback.php?id=' . $solicitacao_id,
    'feedback',
    $solicitacao_id,
    'feedback_solicitacao'
);
```

---

### 7. Cursos/Treinamentos (`pages/lms_atribuir_curso.php`)

**Onde adicionar:** Ao atribuir curso para colaborador

```php
// ... código existente de atribuição ...
$atribuicao_id = $pdo->lastInsertId();

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

### 8. Documentos (`pages/documentos_colaborador.php`)

**Onde adicionar:** Ao fazer upload de documento para colaborador

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

### 9. Férias Aprovadas (`pages/ferias.php`)

**Onde adicionar:** Ao aprovar solicitação de férias

```php
// ... código existente de aprovação ...
$stmt = $pdo->prepare("UPDATE ferias SET status = 'aprovada' WHERE id = ?");
$stmt->execute([$ferias_id]);

// Busca dados
$stmt = $pdo->prepare("SELECT * FROM ferias WHERE id = ?");
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

### 10. Aniversário (Automático via Cron)

**Arquivo:** `cron/enviar_parabens_aniversario.php`

```php
<?php
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
    // ✅ Envia push de parabéns
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
```

---

## ✅ Checklist Rápido

Ao adicionar push em um novo módulo, verifique:

- [ ] Incluiu `push_notifications.php`
- [ ] Passou `$colaborador_id` correto
- [ ] Título claro e curto (máx 50 caracteres)
- [ ] Mensagem descritiva (máx 200 caracteres)
- [ ] URL de destino correta
- [ ] Tipo de notificação definido
- [ ] ID de referência (se aplicável)
- [ ] Testou o envio

---

## 🔧 Dicas Importantes

### ✅ FAÇA:
- Use títulos curtos e claros
- Inclua emojis para chamar atenção (opcional)
- Seja específico na mensagem
- Sempre passe a URL de destino
- Teste antes de enviar para todos

### ❌ NÃO FAÇA:
- Enviar push sem registrar no banco primeiro
- Usar mensagens muito longas (quebram no mobile)
- Esquecer de validar se colaborador existe
- Enviar múltiplas notificações duplicadas
- Esquecer de tratar erros

---

## 📊 Monitoramento

Para ver estatísticas das notificações enviadas:

```sql
-- Total de push enviados hoje
SELECT COUNT(*) FROM notificacoes_push 
WHERE DATE(created_at) = CURDATE();

-- Push por tipo
SELECT tipo, COUNT(*) as total 
FROM notificacoes_sistema 
WHERE DATE(created_at) = CURDATE()
GROUP BY tipo;

-- Taxa de visualização
SELECT 
    COUNT(*) as total_enviados,
    SUM(visualizada) as total_visualizados,
    ROUND(SUM(visualizada) / COUNT(*) * 100, 2) as taxa_visualizacao
FROM notificacoes_push
WHERE enviado = 1;
```

---

## 🎯 Ordem de Implementação Sugerida

1. **Alta Prioridade** (Impacto imediato):
   - ✅ Promoções (já feito)
   - Ocorrências
   - Horas Extras
   - Fechamento de Pagamento

2. **Média Prioridade** (Bom ter):
   - Eventos
   - Comunicados
   - Solicitação de Feedback
   - Férias

3. **Baixa Prioridade** (Nice to have):
   - Documentos
   - Cursos/Treinamentos
   - Aniversários
   - Benefícios

---

**🚀 Pronto! Agora você pode adicionar notificações push em qualquer módulo do sistema!**
