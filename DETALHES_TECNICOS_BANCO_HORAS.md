# 🔧 Detalhes Técnicos: Integração Banco de Horas

## 📋 Visão Geral da Integração

Este documento detalha como o sistema de banco de horas será integrado ao código existente, mantendo compatibilidade e seguindo os padrões já estabelecidos.

---

## 🗂️ Estrutura de Arquivos

### **Novos Arquivos**

```
includes/
└── banco_horas_functions.php    # Funções auxiliares do banco de horas

api/
└── banco_horas/
    ├── saldo.php                # API para consultar saldo
    ├── historico.php            # API para consultar histórico
    └── ajustar.php              # API para ajustes manuais (opcional)
```

### **Arquivos Modificados**

```
pages/
├── horas_extras.php            # Adicionar escolha tipo pagamento + remover horas
├── colaborador_view.php        # Nova aba "Banco de Horas"
└── ocorrencias_add.php         # Opção desconto banco de horas

includes/
└── functions.php               # Adicionar helper get_saldo_banco_horas() (opcional)
```

---

## 💻 Implementação das Funções Auxiliares

### **Arquivo: `includes/banco_horas_functions.php`**

```php
<?php
/**
 * Funções Auxiliares - Sistema de Banco de Horas
 */

require_once __DIR__ . '/functions.php';

/**
 * Busca ou cria registro de saldo do colaborador
 */
function get_or_create_saldo_banco_horas($colaborador_id) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT * FROM banco_horas WHERE colaborador_id = ?");
    $stmt->execute([$colaborador_id]);
    $saldo = $stmt->fetch();
    
    if (!$saldo) {
        // Cria registro inicial com saldo zero
        $stmt = $pdo->prepare("
            INSERT INTO banco_horas (colaborador_id, saldo_horas, saldo_minutos)
            VALUES (?, 0.00, 0)
        ");
        $stmt->execute([$colaborador_id]);
        
        // Busca novamente
        $stmt = $pdo->prepare("SELECT * FROM banco_horas WHERE colaborador_id = ?");
        $stmt->execute([$colaborador_id]);
        $saldo = $stmt->fetch();
    }
    
    return $saldo;
}

/**
 * Busca saldo atual do colaborador
 */
function get_saldo_banco_horas($colaborador_id) {
    $saldo = get_or_create_saldo_banco_horas($colaborador_id);
    
    return [
        'saldo_horas' => (float)$saldo['saldo_horas'],
        'saldo_minutos' => (int)$saldo['saldo_minutos'],
        'saldo_total_horas' => (float)$saldo['saldo_horas'] + ($saldo['saldo_minutos'] / 60),
        'ultima_atualizacao' => $saldo['ultima_atualizacao']
    ];
}

/**
 * Adiciona horas ao banco de horas
 */
function adicionar_horas_banco($colaborador_id, $quantidade_horas, $origem, $origem_id, $motivo, $observacoes = '', $usuario_id = null, $data_movimentacao = null) {
    $pdo = getDB();
    
    if ($usuario_id === null && isset($_SESSION['usuario'])) {
        $usuario_id = $_SESSION['usuario']['id'];
    }
    
    if ($data_movimentacao === null) {
        $data_movimentacao = date('Y-m-d');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Busca saldo atual
        $saldo_atual = get_or_create_saldo_banco_horas($colaborador_id);
        $saldo_anterior = (float)$saldo_atual['saldo_horas'] + ($saldo_atual['saldo_minutos'] / 60);
        
        // Calcula novo saldo
        $saldo_posterior = $saldo_anterior + $quantidade_horas;
        
        // Converte para horas e minutos
        $horas_inteiras = floor($saldo_posterior);
        $minutos = ($saldo_posterior - $horas_inteiras) * 60;
        
        // Insere movimentação
        $stmt = $pdo->prepare("
            INSERT INTO banco_horas_movimentacoes (
                colaborador_id, tipo, origem, origem_id,
                quantidade_horas, saldo_anterior, saldo_posterior,
                motivo, observacoes, usuario_id, data_movimentacao
            ) VALUES (?, 'credito', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id,
            $origem,
            $origem_id,
            $quantidade_horas,
            $saldo_anterior,
            $saldo_posterior,
            $motivo,
            $observacoes,
            $usuario_id,
            $data_movimentacao
        ]);
        
        $movimentacao_id = $pdo->lastInsertId();
        
        // Atualiza saldo
        $stmt = $pdo->prepare("
            UPDATE banco_horas 
            SET saldo_horas = ?, saldo_minutos = ?, ultima_atualizacao = NOW()
            WHERE colaborador_id = ?
        ");
        $stmt->execute([$horas_inteiras, $minutos, $colaborador_id]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'movimentacao_id' => $movimentacao_id,
            'saldo_anterior' => $saldo_anterior,
            'saldo_posterior' => $saldo_posterior
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Remove horas do banco de horas
 */
function remover_horas_banco($colaborador_id, $quantidade_horas, $origem, $origem_id, $motivo, $observacoes = '', $usuario_id = null, $data_movimentacao = null, $permitir_saldo_negativo = true) {
    $pdo = getDB();
    
    if ($usuario_id === null && isset($_SESSION['usuario'])) {
        $usuario_id = $_SESSION['usuario']['id'];
    }
    
    if ($data_movimentacao === null) {
        $data_movimentacao = date('Y-m-d');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Busca saldo atual
        $saldo_atual = get_or_create_saldo_banco_horas($colaborador_id);
        $saldo_anterior = (float)$saldo_atual['saldo_horas'] + ($saldo_atual['saldo_minutos'] / 60);
        
        // Valida saldo (se não permitir negativo)
        if (!$permitir_saldo_negativo && $saldo_anterior < $quantidade_horas) {
            $pdo->rollBack();
            return [
                'success' => false,
                'error' => 'Saldo insuficiente. Saldo atual: ' . number_format($saldo_anterior, 2, ',', '.') . ' horas.'
            ];
        }
        
        // Calcula novo saldo
        $saldo_posterior = $saldo_anterior - $quantidade_horas;
        
        // Converte para horas e minutos
        $horas_inteiras = floor(abs($saldo_posterior));
        $minutos = (abs($saldo_posterior) - $horas_inteiras) * 60;
        
        // Se negativo, armazena como negativo nas horas
        if ($saldo_posterior < 0) {
            $horas_inteiras = -$horas_inteiras;
        }
        
        // Insere movimentação
        $stmt = $pdo->prepare("
            INSERT INTO banco_horas_movimentacoes (
                colaborador_id, tipo, origem, origem_id,
                quantidade_horas, saldo_anterior, saldo_posterior,
                motivo, observacoes, usuario_id, data_movimentacao
            ) VALUES (?, 'debito', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id,
            $origem,
            $origem_id,
            $quantidade_horas,
            $saldo_anterior,
            $saldo_posterior,
            $motivo,
            $observacoes,
            $usuario_id,
            $data_movimentacao
        ]);
        
        $movimentacao_id = $pdo->lastInsertId();
        
        // Atualiza saldo
        $stmt = $pdo->prepare("
            UPDATE banco_horas 
            SET saldo_horas = ?, saldo_minutos = ?, ultima_atualizacao = NOW()
            WHERE colaborador_id = ?
        ");
        $stmt->execute([$horas_inteiras, $minutos, $colaborador_id]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'movimentacao_id' => $movimentacao_id,
            'saldo_anterior' => $saldo_anterior,
            'saldo_posterior' => $saldo_posterior
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Calcula horas a descontar baseado na ocorrência
 */
function calcular_horas_desconto_ocorrencia($ocorrencia_id) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT o.*, t.codigo as tipo_codigo, c.jornada_diaria_horas
        FROM ocorrencias o
        INNER JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
        INNER JOIN colaboradores c ON o.colaborador_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$ocorrencia_id]);
    $ocorrencia = $stmt->fetch();
    
    if (!$ocorrencia) {
        return 0;
    }
    
    $tipo_codigo = $ocorrencia['tipo_codigo'];
    $jornada_diaria = $ocorrencia['jornada_diaria_horas'] ?? 8; // Padrão 8h
    
    // Se for falta
    if ($tipo_codigo === 'falta' || $tipo_codigo === 'ausencia_injustificada') {
        return $jornada_diaria;
    }
    
    // Se for atraso, converte minutos em horas
    if (in_array($tipo_codigo, ['atraso_entrada', 'atraso_almoco', 'atraso_cafe'])) {
        $minutos = $ocorrencia['tempo_atraso_minutos'] ?? 0;
        return $minutos / 60; // Converte minutos para horas
    }
    
    // Se for saída antecipada
    if ($tipo_codigo === 'saida_antecipada') {
        // Aqui poderia ter um campo específico, mas por enquanto usa tempo_atraso_minutos
        $minutos = $ocorrencia['tempo_atraso_minutos'] ?? 0;
        return $minutos / 60;
    }
    
    return 0;
}

/**
 * Desconta horas do banco por ocorrência
 */
function descontar_horas_banco_ocorrencia($ocorrencia_id, $usuario_id = null) {
    $pdo = getDB();
    
    // Busca dados da ocorrência
    $stmt = $pdo->prepare("
        SELECT o.*, c.nome_completo, t.nome as tipo_nome
        FROM ocorrencias o
        INNER JOIN colaboradores c ON o.colaborador_id = c.id
        LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
        WHERE o.id = ?
    ");
    $stmt->execute([$ocorrencia_id]);
    $ocorrencia = $stmt->fetch();
    
    if (!$ocorrencia) {
        return ['success' => false, 'error' => 'Ocorrência não encontrada'];
    }
    
    // Calcula horas a descontar
    $horas_descontar = calcular_horas_desconto_ocorrencia($ocorrencia_id);
    
    if ($horas_descontar <= 0) {
        return ['success' => false, 'error' => 'Não é possível calcular horas para este tipo de ocorrência'];
    }
    
    // Monta motivo
    $motivo = sprintf(
        'Desconto por %s - %s em %s',
        $ocorrencia['tipo_nome'] ?? 'ocorrência',
        $ocorrencia['nome_completo'],
        date('d/m/Y', strtotime($ocorrencia['data_ocorrencia']))
    );
    
    // Remove horas do banco
    $resultado = remover_horas_banco(
        $ocorrencia['colaborador_id'],
        $horas_descontar,
        'ocorrencia',
        $ocorrencia_id,
        $motivo,
        $ocorrencia['descricao'] ?? '',
        $usuario_id,
        $ocorrencia['data_ocorrencia']
    );
    
    if ($resultado['success']) {
        // Atualiza ocorrência com dados do banco de horas
        $stmt = $pdo->prepare("
            UPDATE ocorrencias 
            SET desconta_banco_horas = TRUE,
                horas_descontadas = ?,
                banco_horas_movimentacao_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $horas_descontar,
            $resultado['movimentacao_id'],
            $ocorrencia_id
        ]);
    }
    
    return $resultado;
}

/**
 * Busca histórico de movimentações do colaborador
 */
function get_historico_banco_horas($colaborador_id, $filtros = []) {
    $pdo = getDB();
    
    $where = ['m.colaborador_id = ?'];
    $params = [$colaborador_id];
    
    // Filtro por tipo
    if (!empty($filtros['tipo'])) {
        $where[] = 'm.tipo = ?';
        $params[] = $filtros['tipo'];
    }
    
    // Filtro por origem
    if (!empty($filtros['origem'])) {
        $where[] = 'm.origem = ?';
        $params[] = $filtros['origem'];
    }
    
    // Filtro por período
    if (!empty($filtros['data_inicio'])) {
        $where[] = 'm.data_movimentacao >= ?';
        $params[] = $filtros['data_inicio'];
    }
    
    if (!empty($filtros['data_fim'])) {
        $where[] = 'm.data_movimentacao <= ?';
        $params[] = $filtros['data_fim'];
    }
    
    $where_clause = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.nome as usuario_nome
        FROM banco_horas_movimentacoes m
        LEFT JOIN usuarios u ON m.usuario_id = u.id
        WHERE $where_clause
        ORDER BY m.created_at DESC
        LIMIT 1000
    ");
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}
```

---

## 🔄 Modificações em `pages/horas_extras.php`

### **1. Adicionar campo no formulário (linha ~290)**

```php
<div class="row mb-7">
    <div class="col-md-12">
        <label class="required fw-semibold fs-6 mb-2">Tipo de Pagamento</label>
        <div class="form-check form-check-custom form-check-solid mb-3">
            <input class="form-check-input" type="radio" name="tipo_pagamento" 
                   id="tipo_pagamento_dinheiro" value="dinheiro" checked />
            <label class="form-check-label" for="tipo_pagamento_dinheiro">
                Pagar em R$ (dinheiro)
            </label>
        </div>
        <div class="form-check form-check-custom form-check-solid">
            <input class="form-check-input" type="radio" name="tipo_pagamento" 
                   id="tipo_pagamento_banco" value="banco_horas" />
            <label class="form-check-label" for="tipo_pagamento_banco">
                Adicionar ao Banco de Horas
            </label>
        </div>
    </div>
</div>

<!-- Mostrar saldo atual quando selecionar banco de horas -->
<div class="row mb-7" id="info_saldo_banco" style="display: none;">
    <div class="col-md-12">
        <div class="alert alert-info">
            <i class="ki-duotone ki-information-5 fs-2 me-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <strong>Saldo atual:</strong> <span id="saldo_atual_colaborador">-</span> horas
        </div>
    </div>
</div>
```

### **2. Modificar processamento POST (linha ~20)**

```php
if ($action === 'add') {
    $colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
    $data_trabalho = $_POST['data_trabalho'] ?? date('Y-m-d');
    $quantidade_horas = str_replace(',', '.', $_POST['quantidade_horas'] ?? '0');
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    $tipo_pagamento = $_POST['tipo_pagamento'] ?? 'dinheiro'; // NOVO
    
    if (empty($colaborador_id) || empty($quantidade_horas) || $quantidade_horas <= 0) {
        redirect('horas_extras.php', 'Preencha os campos obrigatórios!', 'error');
    }
    
    try {
        require_once __DIR__ . '/../includes/banco_horas_functions.php';
        
        if ($tipo_pagamento === 'banco_horas') {
            // Adiciona ao banco de horas
            $motivo = sprintf(
                'Hora extra trabalhada em %s',
                date('d/m/Y', strtotime($data_trabalho))
            );
            
            $resultado = adicionar_horas_banco(
                $colaborador_id,
                $quantidade_horas,
                'hora_extra',
                null, // Será atualizado após inserir hora_extra
                $motivo,
                $observacoes,
                $usuario['id'],
                $data_trabalho
            );
            
            if (!$resultado['success']) {
                redirect('horas_extras.php', 'Erro ao adicionar ao banco de horas: ' . $resultado['error'], 'error');
            }
            
            // Insere hora extra com tipo banco_horas
            $stmt = $pdo->prepare("
                INSERT INTO horas_extras (
                    colaborador_id, data_trabalho, quantidade_horas, 
                    valor_hora, percentual_adicional, valor_total, 
                    observacoes, usuario_id, tipo_pagamento, banco_horas_movimentacao_id
                ) VALUES (?, ?, ?, 0, 0, 0, ?, ?, 'banco_horas', ?)
            ");
            $stmt->execute([
                $colaborador_id, 
                $data_trabalho, 
                $quantidade_horas,
                $observacoes, 
                $usuario['id'],
                $resultado['movimentacao_id']
            ]);
            
            redirect('horas_extras.php', 'Hora extra adicionada ao banco de horas com sucesso!');
            
        } else {
            // Comportamento atual (pagar em dinheiro)
            // ... código existente ...
            
            // Insere hora extra com tipo dinheiro
            $stmt = $pdo->prepare("
                INSERT INTO horas_extras (
                    colaborador_id, data_trabalho, quantidade_horas, valor_hora, 
                    percentual_adicional, valor_total, observacoes, usuario_id, tipo_pagamento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'dinheiro')
            ");
            // ... resto do código ...
        }
    } catch (PDOException $e) {
        redirect('horas_extras.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
    }
}
```

### **3. Adicionar ação "remover_horas"**

```php
} elseif ($action === 'remover_horas') {
    $colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
    $quantidade_horas = str_replace(',', '.', $_POST['quantidade_horas'] ?? '0');
    $motivo = sanitize($_POST['motivo'] ?? '');
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    
    if (empty($colaborador_id) || empty($quantidade_horas) || $quantidade_horas <= 0 || empty($motivo)) {
        redirect('horas_extras.php', 'Preencha os campos obrigatórios!', 'error');
    }
    
    try {
        require_once __DIR__ . '/../includes/banco_horas_functions.php';
        
        $resultado = remover_horas_banco(
            $colaborador_id,
            $quantidade_horas,
            'remocao_manual',
            null,
            $motivo,
            $observacoes,
            $usuario['id']
        );
        
        if ($resultado['success']) {
            redirect('horas_extras.php', 'Horas removidas do banco com sucesso!');
        } else {
            redirect('horas_extras.php', 'Erro: ' . $resultado['error'], 'error');
        }
    } catch (Exception $e) {
        redirect('horas_extras.php', 'Erro ao remover horas: ' . $e->getMessage(), 'error');
    }
}
```

### **4. Adicionar coluna "Tipo" na tabela**

```php
<th class="min-w-100px">Tipo</th>  // Adicionar no thead

// No tbody:
<td>
    <?php if ($he['tipo_pagamento'] === 'banco_horas'): ?>
        <span class="badge badge-info">Banco de Horas</span>
    <?php else: ?>
        <span class="badge badge-success">R$</span>
    <?php endif; ?>
</td>
```

---

## 🔄 Modificações em `pages/ocorrencias_add.php`

### **Adicionar campo no formulário (após linha ~400)**

```php
<?php
// Verifica se tipo de ocorrência permite desconto de banco de horas
$permite_desconto_banco = false;
if ($tipo_ocorrencia_id) {
    $stmt_check = $pdo->prepare("
        SELECT codigo FROM tipos_ocorrencias 
        WHERE id = ? AND codigo IN ('falta', 'ausencia_injustificada', 'atraso_entrada', 'atraso_almoco', 'atraso_cafe', 'saida_antecipada')
    ");
    $stmt_check->execute([$tipo_ocorrencia_id]);
    $permite_desconto_banco = $stmt_check->fetch() !== false;
}
?>

<?php if ($permite_desconto_banco): ?>
<div class="row mb-7">
    <div class="col-md-12">
        <div class="card card-flush bg-light-warning">
            <div class="card-body">
                <div class="form-check form-check-custom form-check-solid mb-3">
                    <input class="form-check-input" type="checkbox" name="desconta_banco_horas" 
                           id="desconta_banco_horas" value="1" />
                    <label class="form-check-label fw-bold" for="desconta_banco_horas">
                        Descontar do Banco de Horas
                    </label>
                </div>
                <div id="info_desconto_banco" style="display: none;">
                    <div class="d-flex flex-column gap-2">
                        <div>
                            <strong>Saldo atual:</strong> 
                            <span id="saldo_atual_ocorrencia">-</span> horas
                        </div>
                        <div>
                            <strong>Horas a descontar:</strong> 
                            <span id="horas_descontar_ocorrencia">-</span> horas
                        </div>
                        <div>
                            <strong>Saldo após:</strong> 
                            <span id="saldo_apos_ocorrencia">-</span> horas
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
```

### **Modificar processamento POST (após linha ~140)**

```php
$desconta_banco_horas = isset($_POST['desconta_banco_horas']) && $_POST['desconta_banco_horas'] == '1';

// ... código existente de inserção da ocorrência ...

$ocorrencia_id = $pdo->lastInsertId();

// Se marcou para descontar do banco de horas
if ($desconta_banco_horas) {
    require_once __DIR__ . '/../includes/banco_horas_functions.php';
    
    $resultado = descontar_horas_banco_ocorrencia($ocorrencia_id, $usuario['id']);
    
    if (!$resultado['success']) {
        // Log erro mas não impede criação da ocorrência
        error_log('Erro ao descontar banco de horas: ' . $resultado['error']);
    }
} else {
    // Comportamento atual: calcula desconto em dinheiro
    if ($tipo_ocorrencia_data && $tipo_ocorrencia_data['calcula_desconto']) {
        $valor_desconto = calcular_desconto_ocorrencia($ocorrencia_id);
        if ($valor_desconto > 0) {
            $stmt = $pdo->prepare("UPDATE ocorrencias SET valor_desconto = ? WHERE id = ?");
            $stmt->execute([$valor_desconto, $ocorrencia_id]);
        }
    }
}
```

---

## 📊 Exemplo de Query para Histórico em `colaborador_view.php`

```php
// Busca saldo atual
require_once __DIR__ . '/../includes/banco_horas_functions.php';
$saldo = get_saldo_banco_horas($id);

// Busca histórico
$historico = get_historico_banco_horas($id, [
    'data_inicio' => date('Y-m-01'), // Mês atual
    'data_fim' => date('Y-m-t')
]);
```

---

## 🎯 Considerações Importantes

### **1. Compatibilidade com Dados Existentes**

- Todas as horas extras existentes terão `tipo_pagamento = 'dinheiro'` (padrão)
- Não há necessidade de migração de dados existentes
- Sistema funciona normalmente mesmo sem saldo inicial

### **2. Performance**

- Índices criados nas tabelas garantem performance
- Queries otimizadas com JOINs adequados
- Limite de 1000 registros no histórico (ajustável)

### **3. Segurança**

- Validação de permissões mantida
- Sanitização de inputs
- Transações para garantir consistência
- Validação de saldo antes de debitar

### **4. Extensibilidade**

- Funções modulares e reutilizáveis
- Fácil adicionar novos tipos de origem
- Estrutura preparada para futuras melhorias

---

## ✅ Próximos Passos

1. Criar script SQL de migração
2. Implementar funções auxiliares
3. Modificar páginas existentes
4. Testar todos os fluxos
5. Adicionar validações e tratamento de erros
6. Documentar para usuários finais

