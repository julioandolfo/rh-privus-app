<?php
/**
 * API para verificar possíveis duplicações de bônus em fechamentos extras
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDB();
    
    $mes = $_GET['mes'] ?? '';
    $subtipo = $_GET['subtipo'] ?? '';
    $tipo_bonus_id = $_GET['tipo_bonus_id'] ?? null;
    $colaboradores_ids = !empty($_GET['colaboradores']) ? explode(',', $_GET['colaboradores']) : [];
    
    if (empty($mes) || empty($subtipo) || empty($colaboradores_ids)) {
        echo json_encode(['duplicacoes' => []]);
        exit;
    }
    
    $duplicacoes = [];
    
    // Busca fechamentos extras do mesmo mês e subtipo
    $where = [
        "fp.tipo_fechamento = 'extra'",
        "fp.subtipo_fechamento = ?",
        "fp.mes_referencia = ?",
        "fp.status != 'cancelado'"
    ];
    $params = [$subtipo, $mes];
    
    // Se tem tipo de bônus, verifica bônus específicos
    if ($tipo_bonus_id && $subtipo === 'bonus_especifico') {
        $placeholders = implode(',', array_fill(0, count($colaboradores_ids), '?'));
        $sql = "
            SELECT DISTINCT
                fp.id as fechamento_id,
                fb.tipo_bonus_id,
                fb.colaborador_id,
                tb.nome as tipo_bonus_nome,
                c.nome_completo as colaborador_nome,
                fb.valor
            FROM fechamentos_pagamento_bonus fb
            INNER JOIN fechamentos_pagamento fp ON fb.fechamento_pagamento_id = fp.id
            INNER JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
            INNER JOIN colaboradores c ON fb.colaborador_id = c.id
            WHERE fp.tipo_fechamento = 'extra'
            AND fp.subtipo_fechamento = ?
            AND fp.mes_referencia = ?
            AND fp.status != 'cancelado'
            AND fb.tipo_bonus_id = ?
            AND fb.colaborador_id IN ($placeholders)
        ";
        $params_bonus = array_merge([$subtipo, $mes, $tipo_bonus_id], $colaboradores_ids);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params_bonus);
        $duplicacoes = $stmt->fetchAll();
        
    } else {
        // Para outros subtipos, verifica se há fechamentos para os mesmos colaboradores
        $placeholders = implode(',', array_fill(0, count($colaboradores_ids), '?'));
        $sql = "
            SELECT DISTINCT
                fp.id as fechamento_id,
                fpi.colaborador_id,
                c.nome_completo as colaborador_nome,
                fpi.valor_total as valor,
                fp.subtipo_fechamento
            FROM fechamentos_pagamento_itens fpi
            INNER JOIN fechamentos_pagamento fp ON fpi.fechamento_id = fp.id
            INNER JOIN colaboradores c ON fpi.colaborador_id = c.id
            WHERE fp.tipo_fechamento = 'extra'
            AND fp.subtipo_fechamento = ?
            AND fp.mes_referencia = ?
            AND fp.status != 'cancelado'
            AND fpi.colaborador_id IN ($placeholders)
        ";
        $params_items = array_merge([$subtipo, $mes], $colaboradores_ids);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params_items);
        $duplicacoes = $stmt->fetchAll();
    }
    
    echo json_encode(['duplicacoes' => $duplicacoes], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

