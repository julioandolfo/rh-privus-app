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
    
    // Se for atraso, verifica se considera dia inteiro
    if (in_array($tipo_codigo, ['atraso_entrada', 'atraso_almoco', 'atraso_cafe'])) {
        // Se marcou para considerar como dia inteiro, retorna jornada diária
        if (!empty($ocorrencia['considera_dia_inteiro']) && $ocorrencia['considera_dia_inteiro'] == 1) {
            return $jornada_diaria;
        }
        // Senão, converte minutos em horas
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

/**
 * Busca dados para gráfico de evolução do saldo
 */
function get_dados_grafico_banco_horas($colaborador_id, $periodo_dias = 30) {
    $pdo = getDB();
    
    $data_inicio = date('Y-m-d', strtotime("-$periodo_dias days"));
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(data_movimentacao) as data,
            SUM(CASE WHEN tipo = 'credito' THEN quantidade_horas ELSE 0 END) as creditos,
            SUM(CASE WHEN tipo = 'debito' THEN quantidade_horas ELSE 0 END) as debitos,
            MAX(saldo_posterior) as saldo_final_dia
        FROM banco_horas_movimentacoes
        WHERE colaborador_id = ? 
        AND data_movimentacao >= ?
        GROUP BY DATE(data_movimentacao)
        ORDER BY data ASC
    ");
    $stmt->execute([$colaborador_id, $data_inicio]);
    
    return $stmt->fetchAll();
}

