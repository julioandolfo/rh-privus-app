<?php
/**
 * Script para aprovar todas as solicitações de horas extras pendentes
 */

require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDB();
    $pdo->beginTransaction();
    
    // Busca todas as solicitações pendentes
    $stmt = $pdo->query("
        SELECT s.*, c.salario, e.percentual_hora_extra
        FROM solicitacoes_horas_extras s
        INNER JOIN colaboradores c ON s.colaborador_id = c.id
        LEFT JOIN empresas e ON c.empresa_id = e.id
        WHERE s.status = 'pendente'
    ");
    $solicitacoes = $stmt->fetchAll();
    
    $total_aprovadas = 0;
    
    foreach ($solicitacoes as $sol) {
        // Calcula valores
        $valor_hora = $sol['salario'] / 220;
        $percentual_adicional = $sol['percentual_hora_extra'] ?? 50.00;
        $valor_total = $valor_hora * $sol['quantidade_horas'] * (1 + ($percentual_adicional / 100));
        
        // Insere em horas_extras
        $stmt = $pdo->prepare("
            INSERT INTO horas_extras (
                colaborador_id, data_trabalho, quantidade_horas,
                valor_hora, percentual_adicional, valor_total,
                observacoes, usuario_id, tipo_pagamento
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'dinheiro')
        ");
        $stmt->execute([
            $sol['colaborador_id'],
            $sol['data_trabalho'],
            $sol['quantidade_horas'],
            $valor_hora,
            $percentual_adicional,
            $valor_total,
            'Solicitado pelo colaborador: ' . $sol['motivo'] . ' | Aprovado automaticamente em lote'
        ]);
        
        // Atualiza solicitação
        $stmt = $pdo->prepare("
            UPDATE solicitacoes_horas_extras 
            SET status = 'aprovada',
                observacoes_rh = 'Aprovado automaticamente em lote',
                usuario_aprovacao_id = 1,
                data_aprovacao = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$sol['id']]);
        
        $total_aprovadas++;
    }
    
    $pdo->commit();
    
    echo "✅ Sucesso! {$total_aprovadas} solicitações foram aprovadas e inseridas em horas_extras.\n";
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

