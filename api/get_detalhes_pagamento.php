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
    
    // Busca TODOS os adiantamentos pendentes do colaborador (independente do mês)
    $adiantamentos_pendentes = [];
    $stmt = $pdo->prepare("
        SELECT fa.*, f.mes_referencia as fechamento_mes_referencia, f.data_fechamento as fechamento_data
        FROM fechamentos_pagamento_adiantamentos fa
        INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
        WHERE fa.colaborador_id = ?
        AND fa.descontado = 0
        ORDER BY fa.mes_desconto ASC, f.data_fechamento DESC
    ");
    $stmt->execute([$colaborador_id]);
    $adiantamentos_pendentes = $stmt->fetchAll();
    
    // Busca adiantamentos descontados neste fechamento
    $adiantamentos_descontados = [];
    if ($fechamento['tipo_fechamento'] === 'regular') {
        // Primeiro tenta buscar pelo fechamento_desconto_id
        $stmt = $pdo->prepare("
            SELECT fa.*, f.mes_referencia as fechamento_mes_referencia, f.data_fechamento as fechamento_data
            FROM fechamentos_pagamento_adiantamentos fa
            INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
            WHERE fa.colaborador_id = ?
            AND fa.fechamento_desconto_id = ?
            AND fa.descontado = 1
            ORDER BY f.data_fechamento DESC
        ");
        $stmt->execute([$colaborador_id, $fechamento_id]);
        $adiantamentos_descontados = $stmt->fetchAll();
        
        // Se não encontrou, tenta buscar pelo mês de desconto
        // (pode ser que o fechamento_desconto_id não foi salvo corretamente)
        if (empty($adiantamentos_descontados)) {
            $stmt = $pdo->prepare("
                SELECT fa.*, f.mes_referencia as fechamento_mes_referencia, f.data_fechamento as fechamento_data
                FROM fechamentos_pagamento_adiantamentos fa
                INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
                WHERE fa.colaborador_id = ?
                AND fa.mes_desconto = ?
                AND fa.descontado = 1
                ORDER BY f.data_fechamento DESC
            ");
            $stmt->execute([$colaborador_id, $fechamento['mes_referencia']]);
            $adiantamentos_descontados = $stmt->fetchAll();
        }
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
    
    // Para fechamentos extras individuais/grupais sem tipo de bônus, cria um registro virtual
    // usando o valor_manual do item para aparecer na listagem
    if (empty($bonus_list) && $fechamento['tipo_fechamento'] === 'extra' && 
        ($fechamento['subtipo_fechamento'] === 'individual' || $fechamento['subtipo_fechamento'] === 'grupal')) {
        $valor_manual = (float)($item['valor_manual'] ?? $item['valor_total'] ?? 0);
        if ($valor_manual > 0) {
            // Cria um registro virtual de bônus para exibição
            $bonus_list[] = [
                'id' => null,
                'fechamento_pagamento_id' => $fechamento_id,
                'colaborador_id' => $colaborador_id,
                'tipo_bonus_id' => null,
                'tipo_bonus_nome' => 'Valor Livre',
                'tipo_valor' => 'variavel',
                'valor_fixo' => null,
                'valor' => $valor_manual,
                'valor_original' => $valor_manual,
                'desconto_ocorrencias' => 0,
                'detalhes_desconto' => null,
                'detalhes_desconto_array' => [],
                'observacoes' => $item['motivo'] ?? ''
            ];
        }
    }
    
    // Para fechamentos regulares, busca também fechamentos extras individuais/grupais abertos
    // que ainda não foram pagos e devem aparecer como bônus
    if ($fechamento['tipo_fechamento'] === 'regular') {
        // Busca fechamentos extras individuais/grupais abertos para este colaborador no mesmo período
        $stmt_extras = $pdo->prepare("
            SELECT fp.id, fp.subtipo_fechamento, fp.mes_referencia, fp.data_fechamento,
                   fpi.valor_total, fpi.valor_manual, fpi.motivo
            FROM fechamentos_pagamento fp
            INNER JOIN fechamentos_pagamento_itens fpi ON fp.id = fpi.fechamento_id
            WHERE fp.tipo_fechamento = 'extra'
            AND (fp.subtipo_fechamento = 'individual' OR fp.subtipo_fechamento = 'grupal')
            AND fp.status = 'aberto'
            AND fpi.colaborador_id = ?
            AND fp.mes_referencia = ?
            ORDER BY fp.data_fechamento DESC
        ");
        $stmt_extras->execute([$colaborador_id, $fechamento['mes_referencia']]);
        $fechamentos_extras = $stmt_extras->fetchAll();
        
        // Para cada fechamento extra aberto, adiciona como bônus virtual
        foreach ($fechamentos_extras as $extra) {
            $valor_extra = (float)($extra['valor_total'] ?? 0);
            if ($valor_extra > 0) {
                // Adiciona um registro virtual de bônus para este fechamento extra
                $bonus_list[] = [
                    'id' => null,
                    'fechamento_pagamento_id' => $fechamento_id,
                    'colaborador_id' => $colaborador_id,
                    'tipo_bonus_id' => null,
                    'tipo_bonus_nome' => 'Bônus Extra (' . ucfirst($extra['subtipo_fechamento']) . ')',
                    'tipo_valor' => 'variavel',
                    'valor_fixo' => null,
                    'valor' => $valor_extra,
                    'valor_original' => $valor_extra,
                    'desconto_ocorrencias' => 0,
                    'detalhes_desconto' => null,
                    'detalhes_desconto_array' => [],
                    'observacoes' => ($extra['motivo'] ?? '') . ' | Fechamento Extra #' . $extra['id'] . ' (Aberto)',
                    'fechamento_extra_id' => $extra['id'],
                    'fechamento_extra_tipo' => $extra['subtipo_fechamento']
                ];
            }
        }
    }
    
    // Processa detalhes de desconto dos bônus
    foreach ($bonus_list as &$bonus) {
        if (!empty($bonus['detalhes_desconto'])) {
            $bonus['detalhes_desconto_array'] = json_decode($bonus['detalhes_desconto'], true);
        } else {
            $bonus['detalhes_desconto_array'] = [];
        }
        
        // Garante que os campos numéricos existam e sejam numéricos
        if (!isset($bonus['valor'])) {
            $bonus['valor'] = 0;
        }
        if (!isset($bonus['valor_original'])) {
            $bonus['valor_original'] = $bonus['valor'];
        }
        if (!isset($bonus['desconto_ocorrencias'])) {
            $bonus['desconto_ocorrencias'] = 0;
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
    
    // Busca flags do colaborador (ativas e expiradas para mostrar histórico)
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.tipo_flag,
            f.data_flag,
            f.data_validade,
            f.status,
            f.observacoes,
            o.data_ocorrencia,
            o.id as ocorrencia_id,
            to_tipo.nome as tipo_ocorrencia_nome,
            to_tipo.codigo as tipo_ocorrencia_codigo,
            u.nome as created_by_nome
        FROM ocorrencias_flags f
        LEFT JOIN ocorrencias o ON f.ocorrencia_id = o.id
        LEFT JOIN tipos_ocorrencias to_tipo ON o.tipo_ocorrencia_id = to_tipo.id
        LEFT JOIN usuarios u ON f.created_by = u.id
        WHERE f.colaborador_id = ?
        ORDER BY f.data_flag DESC, f.status ASC
    ");
    $stmt->execute([$colaborador_id]);
    $flags = $stmt->fetchAll();
    
    // Formata flags
    $flags_formatadas = array_map(function($flag) {
        $tipo_flag_label = '';
        switch($flag['tipo_flag']) {
            case 'falta_nao_justificada':
                $tipo_flag_label = 'Falta Não Justificada';
                break;
            case 'falta_compromisso_pessoal':
                $tipo_flag_label = 'Falta Compromisso Pessoal';
                break;
            case 'ma_conduta':
                $tipo_flag_label = 'Má Conduta';
                break;
            default:
                $tipo_flag_label = ucfirst(str_replace('_', ' ', $flag['tipo_flag']));
        }
        
        return [
            'id' => $flag['id'],
            'tipo_flag' => $flag['tipo_flag'],
            'tipo_flag_label' => $tipo_flag_label,
            'data_flag' => $flag['data_flag'],
            'data_flag_formatada' => date('d/m/Y', strtotime($flag['data_flag'])),
            'data_validade' => $flag['data_validade'],
            'data_validade_formatada' => date('d/m/Y', strtotime($flag['data_validade'])),
            'status' => $flag['status'],
            'status_label' => $flag['status'] === 'ativa' ? 'Ativa' : 'Expirada',
            'observacoes' => $flag['observacoes'],
            'data_ocorrencia' => $flag['data_ocorrencia'],
            'data_ocorrencia_formatada' => $flag['data_ocorrencia'] ? date('d/m/Y', strtotime($flag['data_ocorrencia'])) : null,
            'tipo_ocorrencia_nome' => $flag['tipo_ocorrencia_nome'],
            'tipo_ocorrencia_codigo' => $flag['tipo_ocorrencia_codigo'],
            'created_by_nome' => $flag['created_by_nome'],
            'dias_para_expirar' => $flag['status'] === 'ativa' ? max(0, (strtotime($flag['data_validade']) - strtotime(date('Y-m-d'))) / (60*60*24)) : null
        ];
    }, $flags);
    
    // Separa flags ativas e expiradas
    $flags_ativas = array_filter($flags_formatadas, function($f) { return $f['status'] === 'ativa'; });
    $flags_expiradas = array_filter($flags_formatadas, function($f) { return $f['status'] === 'expirada'; });
    
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
                'valor_total_original' => (float)$item['valor_total'], // Valor original do banco
                'valor_total' => (float)$item['valor_total'], // Será recalculado abaixo se necessário
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
                    // Não soma bônus informativos
                    if ($tipo_valor === 'informativo') {
                        return 0;
                    }
                    // Soma o valor do bônus (já descontado se houver desconto por ocorrências)
                    return (float)($b['valor'] ?? 0);
                }, $bonus_list)),
                'total_desconto_ocorrencias' => array_sum(array_map(function($b) {
                    return (float)($b['desconto_ocorrencias'] ?? 0);
                }, $bonus_list)),
                'total_original' => array_sum(array_map(function($b) {
                    $tipo_valor = $b['tipo_valor'] ?? 'variavel';
                    // Não soma bônus informativos
                    if ($tipo_valor === 'informativo') {
                        return 0;
                    }
                    return (float)($b['valor_original'] ?? $b['valor'] ?? 0);
                }, $bonus_list))
            ],
            'ocorrencias' => [
                'descontos' => $ocorrencias_desconto,
                'total_descontos' => array_sum(array_column($ocorrencias_desconto, 'valor_desconto'))
            ],
            'flags' => [
                'todas' => $flags_formatadas,
                'ativas' => array_values($flags_ativas),
                'expiradas' => array_values($flags_expiradas),
                'total_ativas' => count($flags_ativas),
                'total_expiradas' => count($flags_expiradas)
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
                    'mes_desconto_formatado' => $a['mes_desconto'] ? date('m/Y', strtotime($a['mes_desconto'] . '-01')) : null,
                    'fechamento_mes_referencia' => $a['fechamento_mes_referencia'],
                    'fechamento_data' => $a['fechamento_data'] ? date('d/m/Y', strtotime($a['fechamento_data'])) : null,
                    'observacoes' => $a['observacoes']
                ];
            }, $adiantamentos_pendentes),
            'adiantamentos_descontados' => array_map(function($a) {
                return [
                    'id' => $a['id'],
                    'valor_adiantamento' => (float)$a['valor_adiantamento'],
                    'valor_descontar' => (float)$a['valor_descontar'],
                    'mes_desconto' => $a['mes_desconto'],
                    'mes_desconto_formatado' => $a['mes_desconto'] ? date('m/Y', strtotime($a['mes_desconto'] . '-01')) : null,
                    'fechamento_mes_referencia' => $a['fechamento_mes_referencia'],
                    'fechamento_data' => $a['fechamento_data'] ? date('d/m/Y', strtotime($a['fechamento_data'])) : null,
                    'observacoes' => $a['observacoes']
                ];
            }, $adiantamentos_descontados)
        ]
    ];
    
    // Recalcula o valor total se houver bônus virtuais (fechamentos extras) ou adiantamentos
    // Para fechamentos regulares, o valor total deve incluir os bônus extras e descontos de adiantamentos
    if ($fechamento['tipo_fechamento'] === 'regular') {
        $total_bonus_calculado = $response['data']['bonus']['total'];
        
        // Calcula total de adiantamentos descontados
        $total_adiantamentos_descontados = 0;
        if (!empty($response['data']['adiantamentos_descontados'])) {
            foreach ($response['data']['adiantamentos_descontados'] as $ad) {
                $total_adiantamentos_descontados += (float)$ad['valor_descontar'];
            }
        }
        
        // Verifica se os adiantamentos já estão incluídos no campo descontos
        // Quando um fechamento regular é criado, os adiantamentos são somados ao campo descontos
        // Então, se há adiantamentos descontados neste fechamento, eles já devem estar incluídos
        $descontos_originais = (float)$response['data']['item']['descontos'];
        
        // Se há adiantamentos descontados e o campo descontos é menor que eles,
        // significa que os adiantamentos não estão incluídos (caso raro de fechamento antigo)
        // Caso contrário, assume que já estão incluídos (comportamento padrão)
        if ($total_adiantamentos_descontados > 0 && $descontos_originais < $total_adiantamentos_descontados) {
            // Adiantamentos não estão incluídos, soma ao total
            $descontos_totais = $descontos_originais + $total_adiantamentos_descontados;
        } else {
            // Adiantamentos já estão incluídos no campo descontos (comportamento padrão)
            // ou não há adiantamentos para descontar
            $descontos_totais = $descontos_originais;
        }
        
        // Mantém o campo descontos original para exibição (sem duplicar)
        // O total será calculado separadamente para o cálculo do valor_total
        $response['data']['item']['descontos_original'] = $descontos_originais;
        $response['data']['item']['descontos'] = $descontos_totais;
        
        // Recalcula: salario_base + horas_extras + bonus + adicionais - descontos
        $valor_total_recalculado = 
            $response['data']['item']['salario_base'] + 
            $response['data']['item']['valor_horas_extras'] + 
            $total_bonus_calculado + 
            $response['data']['item']['adicionais'] - 
            $descontos_totais;
        
        $response['data']['item']['valor_total'] = $valor_total_recalculado;
    }
    
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

