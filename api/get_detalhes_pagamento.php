<?php
/**
 * API para buscar detalhes completos de um pagamento de colaborador
 */

// Desabilita exibição de erros para não quebrar o JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];

    $fechamento_id = (int)($_GET['fechamento_id'] ?? 0);
    $colaborador_id = (int)($_GET['colaborador_id'] ?? 0);

    if (empty($fechamento_id) || empty($colaborador_id)) {
        echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Verifica permissão
    if ($usuario['role'] === 'COLABORADOR') {
        // Colaborador só pode ver seus próprios pagamentos
        if ($usuario['colaborador_id'] != $colaborador_id) {
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para visualizar este pagamento']);
            exit;
        }
    } elseif ($usuario['role'] !== 'ADMIN') {
        // RH e outros devem ter acesso à empresa do colaborador
        $stmt = $pdo->prepare("SELECT empresa_id FROM colaboradores WHERE id = ?");
        $stmt->execute([$colaborador_id]);
        $colab = $stmt->fetch();
        
        if (!$colab || ($usuario['empresa_id'] != $colab['empresa_id'] && !in_array($colab['empresa_id'], $usuario['empresas_ids'] ?? []))) {
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para visualizar este pagamento']);
            exit;
        }
    }
    
    // Busca dados do fechamento (incluindo campos de fechamento extra)
    $stmt = $pdo->prepare("
        SELECT f.*, e.nome_fantasia as empresa_nome
        FROM fechamentos_pagamento f
        LEFT JOIN empresas e ON f.empresa_id = e.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fechamento_id]);
    $fechamento = $stmt->fetch();
    
    // Busca adiantamentos relacionados (se houver)
    $adiantamentos_info = [];
    if ($fechamento['tipo_fechamento'] === 'extra' && $fechamento['subtipo_fechamento'] === 'adiantamento') {
        $stmt = $pdo->prepare("
            SELECT * FROM fechamentos_pagamento_adiantamentos
            WHERE fechamento_pagamento_id = ? AND colaborador_id = ?
        ");
        $stmt->execute([$fechamento_id, $colaborador_id]);
        $adiantamento = $stmt->fetch();
        if ($adiantamento) {
            $adiantamentos_info = [
                'valor_adiantamento' => (float)$adiantamento['valor_adiantamento'],
                'valor_descontar' => (float)$adiantamento['valor_descontar'],
                'mes_desconto' => $adiantamento['mes_desconto'],
                'descontado' => (bool)$adiantamento['descontado'],
                'fechamento_desconto_id' => $adiantamento['fechamento_desconto_id'],
                'observacoes' => $adiantamento['observacoes']
            ];
        }
    }
    
    // Busca adiantamentos pendentes do colaborador (para mostrar em fechamentos regulares)
    $adiantamentos_pendentes = [];
    if ($fechamento['tipo_fechamento'] === 'regular') {
        $stmt = $pdo->prepare("
            SELECT fa.*, f.mes_referencia as fechamento_mes_referencia
            FROM fechamentos_pagamento_adiantamentos fa
            INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
            WHERE fa.colaborador_id = ?
            AND fa.mes_desconto = ?
            AND fa.descontado = 0
        ");
        $stmt->execute([$colaborador_id, $fechamento['mes_referencia']]);
        $adiantamentos_pendentes = $stmt->fetchAll();
    }
    
    if (!$fechamento) {
        echo json_encode(['success' => false, 'message' => 'Fechamento não encontrado']);
        exit;
    }
    
    // Busca dados do colaborador
    $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        echo json_encode(['success' => false, 'message' => 'Colaborador não encontrado']);
        exit;
    }
    
    // Busca item do fechamento
    $stmt = $pdo->prepare("
        SELECT * FROM fechamentos_pagamento_itens
        WHERE fechamento_id = ? AND colaborador_id = ?
    ");
    $stmt->execute([$fechamento_id, $colaborador_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item não encontrado']);
        exit;
    }
    
    // Calcula período do fechamento
    $ano_mes = explode('-', $fechamento['mes_referencia']);
    $data_inicio_periodo = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
    $data_fim_periodo = date('Y-m-t', strtotime($data_inicio_periodo));
    
    // Busca horas extras detalhadas (resumo)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN quantidade_horas ELSE 0 END), 0) as horas_dinheiro,
            COALESCE(SUM(CASE WHEN tipo_pagamento = 'banco_horas' THEN quantidade_horas ELSE 0 END), 0) as horas_banco,
            COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN valor_total ELSE 0 END), 0) as valor_dinheiro
        FROM horas_extras
        WHERE colaborador_id = ? AND data_trabalho >= ? AND data_trabalho <= ?
    ");
    $stmt->execute([$colaborador_id, $data_inicio_periodo, $data_fim_periodo]);
    $horas_extras_resumo = $stmt->fetch();
    
    // Busca registros individuais de horas extras
    $stmt = $pdo->prepare("
        SELECT 
            data_trabalho,
            quantidade_horas,
            valor_hora,
            percentual_adicional,
            valor_total,
            tipo_pagamento,
            observacoes
        FROM horas_extras
        WHERE colaborador_id = ? AND data_trabalho >= ? AND data_trabalho <= ?
        ORDER BY data_trabalho DESC
    ");
    $stmt->execute([$colaborador_id, $data_inicio_periodo, $data_fim_periodo]);
    $horas_extras_registros = $stmt->fetchAll();
    
    // Busca bônus
    $stmt = $pdo->prepare("
        SELECT 
            fb.*, 
            tb.nome as tipo_bonus_nome, 
            tb.tipo_valor, 
            tb.valor_fixo,
            COALESCE(fb.valor_original, fb.valor) as valor_original,
            COALESCE(fb.desconto_ocorrencias, 0) as desconto_ocorrencias,
            fb.detalhes_desconto
        FROM fechamentos_pagamento_bonus fb
        INNER JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
        WHERE fb.fechamento_pagamento_id = ? AND fb.colaborador_id = ?
        ORDER BY tb.nome
    ");
    $stmt->execute([$fechamento_id, $colaborador_id]);
    $bonus_list = $stmt->fetchAll();
    
    // Processa detalhes de desconto dos bônus
    foreach ($bonus_list as &$bonus) {
        if (!empty($bonus['detalhes_desconto'])) {
            $bonus['detalhes_desconto_array'] = json_decode($bonus['detalhes_desconto'], true);
        } else {
            $bonus['detalhes_desconto_array'] = [];
        }
    }
    
    // Busca ocorrências com desconto em dinheiro do período
    // Ignora ocorrências apenas informativas
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            to_ocorrencia.nome as tipo_ocorrencia_nome,
            to_ocorrencia.codigo as tipo_ocorrencia_codigo
        FROM ocorrencias o
        INNER JOIN tipos_ocorrencias to_ocorrencia ON o.tipo_ocorrencia_id = to_ocorrencia.id
        WHERE o.colaborador_id = ?
        AND o.data_ocorrencia >= ?
        AND o.data_ocorrencia <= ?
        AND o.valor_desconto > 0
        AND (o.desconta_banco_horas = 0 OR o.desconta_banco_horas IS NULL)
        AND (o.apenas_informativa = 0 OR o.apenas_informativa IS NULL)
        ORDER BY o.data_ocorrencia DESC
    ");
    $stmt->execute([$colaborador_id, $data_inicio_periodo, $data_fim_periodo]);
    $ocorrencias_desconto = $stmt->fetchAll();
    
    // Monta resposta
    $response = [
        'success' => true,
        'data' => [
            'fechamento' => [
                'id' => $fechamento['id'],
                'tipo_fechamento' => $fechamento['tipo_fechamento'] ?? 'regular',
                'subtipo_fechamento' => $fechamento['subtipo_fechamento'] ?? null,
                'mes_referencia' => $fechamento['mes_referencia'],
                'mes_referencia_formatado' => date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')),
                'data_fechamento' => $fechamento['data_fechamento'],
                'data_fechamento_formatada' => date('d/m/Y', strtotime($fechamento['data_fechamento'])),
                'data_pagamento' => $fechamento['data_pagamento'] ?? null,
                'data_pagamento_formatada' => $fechamento['data_pagamento'] ? date('d/m/Y', strtotime($fechamento['data_pagamento'])) : null,
                'referencia_externa' => $fechamento['referencia_externa'] ?? null,
                'descricao' => $fechamento['descricao'] ?? null,
                'empresa_nome' => $fechamento['empresa_nome'],
                'status' => $fechamento['status']
            ],
            'colaborador' => [
                'id' => $colaborador['id'],
                'nome_completo' => $colaborador['nome_completo'],
                'cpf' => $colaborador['cpf'],
                'cargo' => $colaborador['cargo']
            ],
            'periodo' => [
                'inicio' => $data_inicio_periodo,
                'fim' => $data_fim_periodo,
                'inicio_formatado' => date('d/m/Y', strtotime($data_inicio_periodo)),
                'fim_formatado' => date('d/m/Y', strtotime($data_fim_periodo))
            ],
            'item' => [
                'salario_base' => (float)$item['salario_base'],
                'horas_extras' => (float)$item['horas_extras'],
                'valor_horas_extras' => (float)$item['valor_horas_extras'],
                'descontos' => (float)($item['descontos'] ?? 0),
                'adicionais' => (float)($item['adicionais'] ?? 0),
                'valor_total' => (float)$item['valor_total'],
                'valor_manual' => isset($item['valor_manual']) ? (float)$item['valor_manual'] : null,
                'motivo' => $item['motivo'] ?? null,
                'inclui_salario' => isset($item['inclui_salario']) ? (bool)$item['inclui_salario'] : true,
                'inclui_horas_extras' => isset($item['inclui_horas_extras']) ? (bool)$item['inclui_horas_extras'] : true,
                'inclui_bonus_automaticos' => isset($item['inclui_bonus_automaticos']) ? (bool)$item['inclui_bonus_automaticos'] : true
            ],
            'horas_extras' => [
                'total_horas' => (float)$item['horas_extras'],
                'valor_total' => (float)$item['valor_horas_extras'],
                'registros' => $horas_extras_registros,
                'resumo' => [
                    'horas_dinheiro' => (float)($horas_extras_resumo['horas_dinheiro'] ?? 0),
                    'horas_banco' => (float)($horas_extras_resumo['horas_banco'] ?? 0),
                    'valor_dinheiro' => (float)($horas_extras_resumo['valor_dinheiro'] ?? 0)
                ]
            ],
            'bonus' => [
                'lista' => $bonus_list,
                'total' => array_sum(array_map(function($b) {
                    $tipo_valor = $b['tipo_valor'] ?? 'variavel';
                    return $tipo_valor === 'informativo' ? 0 : (float)($b['valor'] ?? 0);
                }, $bonus_list)),
                'total_desconto_ocorrencias' => array_sum(array_map(function($b) {
                    return (float)($b['desconto_ocorrencias'] ?? 0);
                }, $bonus_list))
            ],
            'ocorrencias' => [
                'descontos' => $ocorrencias_desconto,
                'total_descontos' => array_sum(array_column($ocorrencias_desconto, 'valor_desconto'))
            ],
            'documento' => [
                'status' => $item['documento_status'] ?? 'pendente',
                'anexo' => $item['documento_anexo'] ?? null,
                'data_envio' => $item['documento_data_envio'] ?? null,
                'data_aprovacao' => $item['documento_data_aprovacao'] ?? null,
                'observacoes' => $item['documento_observacoes'] ?? null
            ],
            'adiantamento' => $adiantamentos_info,
            'adiantamentos_pendentes' => array_map(function($a) {
                return [
                    'id' => $a['id'],
                    'valor_adiantamento' => (float)$a['valor_adiantamento'],
                    'valor_descontar' => (float)$a['valor_descontar'],
                    'mes_desconto' => $a['mes_desconto'],
                    'fechamento_mes_referencia' => $a['fechamento_mes_referencia'],
                    'observacoes' => $a['observacoes']
                ];
            }, $adiantamentos_pendentes)
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Erro PDO em get_detalhes_pagamento: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao buscar dados: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode()
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro em get_detalhes_pagamento: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao buscar dados: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    error_log('Erro fatal em get_detalhes_pagamento: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro fatal ao buscar dados: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

