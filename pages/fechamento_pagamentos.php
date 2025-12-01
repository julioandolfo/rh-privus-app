<?php
/**
 * Fechamento de Pagamentos - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('fechamento_pagamentos.php');

/**
 * Função auxiliar para log de fechamentos de bônus
 */
function log_fechamento_bonus($mensagem, $contexto = []) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/fechamento_bonus.log';
    $timestamp = date('Y-m-d H:i:s');
    $contexto_str = !empty($contexto) ? ' | Contexto: ' . json_encode($contexto) : '';
    $log_message = "[{$timestamp}] {$mensagem}{$contexto_str}" . PHP_EOL;
    
    @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Função auxiliar para log de fechamentos individuais
 */
function log_fechamento_individual($mensagem, $contexto = []) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/fechamento_individual.log';
    $timestamp = date('Y-m-d H:i:s');
    $contexto_str = !empty($contexto) ? ' | Contexto: ' . json_encode($contexto) : '';
    $log_message = "[{$timestamp}] {$mensagem}{$contexto_str}" . PHP_EOL;
    
    @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Verifica se um bônus já foi pago em fechamento extra no mesmo mês
 * Retorna array com IDs dos fechamentos extras que pagaram este bônus
 */
function verificar_bonus_ja_pago_extra($pdo, $tipo_bonus_id, $colaborador_id, $mes_referencia) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT fp.id as fechamento_id, fp.mes_referencia, fp.data_pagamento, fp.referencia_externa,
               tb.nome as tipo_bonus_nome, fb.valor as valor_pago
        FROM fechamentos_pagamento_bonus fb
        INNER JOIN fechamentos_pagamento fp ON fb.fechamento_pagamento_id = fp.id
        INNER JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
        WHERE fb.tipo_bonus_id = ?
        AND fb.colaborador_id = ?
        AND fp.tipo_fechamento = 'extra'
        AND fp.mes_referencia = ?
        AND fp.status != 'cancelado'
    ");
    $stmt->execute([$tipo_bonus_id, $colaborador_id, $mes_referencia]);
    return $stmt->fetchAll();
}

/**
 * Calcula desconto de bônus baseado nas ocorrências configuradas
 */
function calcular_desconto_bonus_ocorrencias($pdo, $tipo_bonus_id, $colaborador_id, $valor_bonus, $data_inicio, $data_fim) {
    // Busca configurações de desconto por ocorrências para este tipo de bônus
    $stmt = $pdo->prepare("
        SELECT tbo.*, to_cod.codigo as tipo_ocorrencia_codigo
        FROM tipos_bonus_ocorrencias tbo
        INNER JOIN tipos_ocorrencias to_cod ON tbo.tipo_ocorrencia_id = to_cod.id
        WHERE tbo.tipo_bonus_id = ? AND tbo.ativo = 1
    ");
    $stmt->execute([$tipo_bonus_id]);
    $configuracoes = $stmt->fetchAll();
    
    if (empty($configuracoes)) {
        return 0;
    }
    
    $desconto_total = 0;
    $detalhes_desconto = [];
    
    foreach ($configuracoes as $config) {
        // Verifica período anterior primeiro (se configurado)
        $verificar_periodo_anterior = !empty($config['verificar_periodo_anterior']);
        $periodo_anterior_meses = (int)($config['periodo_anterior_meses'] ?? 1);
        
        $total_ocorrencias_periodo_anterior = 0;
        $total_ocorrencias_periodo_atual = 0;
        
        // Se deve verificar período anterior
        if ($verificar_periodo_anterior) {
            // Calcula período anterior (meses anteriores ao início do fechamento)
            $data_inicio_anterior = date('Y-m-01', strtotime($data_inicio . ' -' . $periodo_anterior_meses . ' months'));
            $data_fim_anterior = date('Y-m-t', strtotime($data_inicio_anterior));
            
            // Busca ocorrências no período anterior
            $where_conditions_anterior = [
                "o.colaborador_id = ?",
                "o.data_ocorrencia >= ?",
                "o.data_ocorrencia <= ?",
                "o.tipo_ocorrencia_id = ?"
            ];
            $params_anterior = [$colaborador_id, $data_inicio_anterior, $data_fim_anterior, $config['tipo_ocorrencia_id']];
            
            if ($config['desconta_apenas_aprovadas']) {
                $where_conditions_anterior[] = "o.status_aprovacao = 'aprovada'";
            }
            
            if (!$config['desconta_banco_horas']) {
                $where_conditions_anterior[] = "(o.desconta_banco_horas = 0 OR o.desconta_banco_horas IS NULL)";
            }
            
            // Ignora ocorrências apenas informativas
            $where_conditions_anterior[] = "(o.apenas_informativa = 0 OR o.apenas_informativa IS NULL)";
            
            $sql_anterior = "SELECT COUNT(*) as total FROM ocorrencias o WHERE " . implode(' AND ', $where_conditions_anterior);
            $stmt_anterior = $pdo->prepare($sql_anterior);
            $stmt_anterior->execute($params_anterior);
            $resultado_anterior = $stmt_anterior->fetch();
            $total_ocorrencias_periodo_anterior = (int)($resultado_anterior['total'] ?? 0);
            
            // Se encontrou ocorrência no período anterior e tipo é 'total', zera o bônus completamente
            if ($total_ocorrencias_periodo_anterior > 0 && $config['tipo_desconto'] === 'total') {
                return $valor_bonus; // Retorna o valor total do bônus como desconto
            }
        }
        
        // Define período de busca (período atual do fechamento)
        $periodo_inicio = $data_inicio;
        $periodo_fim = $data_fim;
        
        if (!empty($config['periodo_dias'])) {
            // Se tem período específico, calcula a partir da data fim
            $periodo_inicio = date('Y-m-d', strtotime($data_fim . ' -' . $config['periodo_dias'] . ' days'));
        }
        
        // Monta condições da query para período atual
        $where_conditions = [
            "o.colaborador_id = ?",
            "o.data_ocorrencia >= ?",
            "o.data_ocorrencia <= ?",
            "o.tipo_ocorrencia_id = ?"
        ];
        $params = [$colaborador_id, $periodo_inicio, $periodo_fim, $config['tipo_ocorrencia_id']];
        
        // Filtro de aprovação
        if ($config['desconta_apenas_aprovadas']) {
            $where_conditions[] = "o.status_aprovacao = 'aprovada'";
        }
        
        // Filtro de banco de horas
        if (!$config['desconta_banco_horas']) {
            $where_conditions[] = "(o.desconta_banco_horas = 0 OR o.desconta_banco_horas IS NULL)";
        }
        
        // Ignora ocorrências apenas informativas
        $where_conditions[] = "(o.apenas_informativa = 0 OR o.apenas_informativa IS NULL)";
        
        // Busca ocorrências do tipo configurado no período atual
        $sql = "
            SELECT COUNT(*) as total_ocorrencias
            FROM ocorrencias o
            WHERE " . implode(' AND ', $where_conditions);
        
        $stmt_ocorrencias = $pdo->prepare($sql);
        $stmt_ocorrencias->execute($params);
        $resultado = $stmt_ocorrencias->fetch();
        $total_ocorrencias_periodo_atual = (int)($resultado['total_ocorrencias'] ?? 0);
        
        // Se tipo é 'total' e encontrou ocorrência no período atual, zera o bônus completamente
        if ($total_ocorrencias_periodo_atual > 0 && $config['tipo_desconto'] === 'total') {
            return $valor_bonus; // Retorna o valor total do bônus como desconto
        }
        
        // Se encontrou ocorrência no período anterior (mesmo que não seja tipo 'total'), também zera
        if ($total_ocorrencias_periodo_anterior > 0) {
            return $valor_bonus; // Retorna o valor total do bônus como desconto
        }
        
        // Se não encontrou ocorrências ou não é tipo 'total', calcula desconto normalmente
        if ($total_ocorrencias_periodo_atual > 0) {
            $desconto_config = 0;
            
            // Calcula desconto baseado no tipo
            if ($config['tipo_desconto'] === 'fixo' && !empty($config['valor_desconto'])) {
                // Desconto fixo por ocorrência
                $desconto_config = (float)$config['valor_desconto'] * $total_ocorrencias_periodo_atual;
            } elseif ($config['tipo_desconto'] === 'percentual' && !empty($config['valor_desconto'])) {
                // Desconto percentual do valor do bônus
                $percentual = (float)$config['valor_desconto'] / 100;
                $desconto_config = $valor_bonus * $percentual * $total_ocorrencias_periodo_atual;
            } else {
                // Desconto proporcional (divide por dias úteis do período)
                $dias_uteis_periodo = calcular_dias_uteis($periodo_inicio, $periodo_fim);
                if ($dias_uteis_periodo > 0) {
                    $valor_por_dia = $valor_bonus / $dias_uteis_periodo;
                    $desconto_config = $valor_por_dia * $total_ocorrencias_periodo_atual;
                }
            }
            
            $desconto_total += $desconto_config;
            
            $detalhes_desconto[] = [
                'tipo_ocorrencia_id' => $config['tipo_ocorrencia_id'],
                'tipo_ocorrencia_codigo' => $config['tipo_ocorrencia_codigo'],
                'total_ocorrencias' => $total_ocorrencias_periodo_atual,
                'total_ocorrencias_periodo_anterior' => $total_ocorrencias_periodo_anterior,
                'desconto' => $desconto_config,
                'tipo_desconto' => $config['tipo_desconto'],
                'verificar_periodo_anterior' => $verificar_periodo_anterior
            ];
        }
    }
    
    return $desconto_total;
}

/**
 * Calcula dias úteis entre duas datas (exclui sábados e domingos)
 */
function calcular_dias_uteis($data_inicio, $data_fim) {
    $inicio = new DateTime($data_inicio);
    $fim = new DateTime($data_fim);
    $dias_uteis = 0;
    
    while ($inicio <= $fim) {
        $dia_semana = (int)$inicio->format('w');
        // 0 = domingo, 6 = sábado
        if ($dia_semana != 0 && $dia_semana != 6) {
            $dias_uteis++;
        }
        $inicio->modify('+1 day');
    }
    
    return $dias_uteis > 0 ? $dias_uteis : 20; // Fallback para 20 dias se cálculo der 0
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'criar_fechamento') {
        $empresa_id = (int)($_POST['empresa_id'] ?? 0);
        $mes_referencia = $_POST['mes_referencia'] ?? '';
        $colaboradores_ids = $_POST['colaboradores'] ?? [];
        
        if (empty($empresa_id) || empty($mes_referencia) || empty($colaboradores_ids)) {
            redirect('fechamento_pagamentos.php', 'Preencha todos os campos obrigatórios!', 'error');
        }
        
        try {
            // Verifica se já existe fechamento REGULAR para este mês (permite múltiplos extras)
            $stmt = $pdo->prepare("SELECT id FROM fechamentos_pagamento WHERE empresa_id = ? AND mes_referencia = ? AND tipo_fechamento = 'regular'");
            $stmt->execute([$empresa_id, $mes_referencia]);
            if ($stmt->fetch()) {
                redirect('fechamento_pagamentos.php', 'Já existe um fechamento regular para este mês!', 'error');
            }
            
            // Cria fechamento regular
            $stmt = $pdo->prepare("
                INSERT INTO fechamentos_pagamento (empresa_id, tipo_fechamento, mes_referencia, data_fechamento, total_colaboradores, usuario_id, status)
                VALUES (?, 'regular', ?, CURDATE(), ?, ?, 'aberto')
            ");
            $stmt->execute([$empresa_id, $mes_referencia, count($colaboradores_ids), $usuario['id']]);
            $fechamento_id = $pdo->lastInsertId();
            
            // Busca período (primeiro e último dia do mês)
            $ano_mes = explode('-', $mes_referencia);
            $data_inicio = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
            $data_fim = date('Y-m-t', strtotime($data_inicio));
            
            $total_pagamento = 0;
            $total_horas_extras = 0;
            $bonus_excluidos_geral = []; // Armazena informações sobre bônus excluídos para mensagem
            
            // Adiciona colaboradores ao fechamento
            foreach ($colaboradores_ids as $colab_id) {
                $colab_id = (int)$colab_id;
                
                // Busca dados do colaborador
                $stmt = $pdo->prepare("SELECT salario FROM colaboradores WHERE id = ?");
                $stmt->execute([$colab_id]);
                $colab = $stmt->fetch();
                
                if (!$colab || !$colab['salario']) continue;
                
                $salario_base = $colab['salario'];
                
                // Busca horas extras do período (separando por tipo de pagamento)
                // Considera NULL como 'dinheiro' para compatibilidade com registros antigos
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN quantidade_horas ELSE 0 END), 0) as total_horas_dinheiro,
                        COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN valor_total ELSE 0 END), 0) as total_valor_dinheiro,
                        COALESCE(SUM(CASE WHEN tipo_pagamento = 'banco_horas' THEN quantidade_horas ELSE 0 END), 0) as total_horas_banco,
                        COALESCE(SUM(quantidade_horas), 0) as total_horas,
                        COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN valor_total ELSE 0 END), 0) as total_valor
                    FROM horas_extras
                    WHERE colaborador_id = ? AND data_trabalho >= ? AND data_trabalho <= ?
                ");
                $stmt->execute([$colab_id, $data_inicio, $data_fim]);
                $he_data = $stmt->fetch();
                
                $horas_extras = (float)($he_data['total_horas'] ?? 0);
                $horas_extras_dinheiro = (float)($he_data['total_horas_dinheiro'] ?? 0);
                $horas_extras_banco = (float)($he_data['total_horas_banco'] ?? 0);
                $valor_horas_extras = (float)($he_data['total_valor'] ?? 0);
                
                // Busca bônus ativos do colaborador no período
                // Considera o tipo de bônus: fixo (usa valor_fixo), variavel (usa valor do colaborador), informativo (não soma)
                $stmt = $pdo->prepare("
                    SELECT 
                        cb.*,
                        tb.tipo_valor,
                        tb.valor_fixo,
                        tb.nome as tipo_bonus_nome
                    FROM colaboradores_bonus cb
                    INNER JOIN tipos_bonus tb ON cb.tipo_bonus_id = tb.id
                    WHERE cb.colaborador_id = ?
                    AND (
                        (cb.data_inicio IS NULL AND cb.data_fim IS NULL)
                        OR (
                            (cb.data_inicio IS NULL OR cb.data_inicio <= ?)
                            AND (cb.data_fim IS NULL OR cb.data_fim >= ?)
                        )
                    )
                ");
                $stmt->execute([$colab_id, $data_fim, $data_inicio]);
                $bonus_list_calc = $stmt->fetchAll();
                
                // Se deve excluir bônus já pagos em extras, verifica cada bônus
                $bonus_ja_pagos_info = [];
                $bonus_excluidos_colab = [];
                if ($excluir_bonus_ja_pagos) {
                    // Busca nome do colaborador para mensagem
                    $stmt_colab_nome = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
                    $stmt_colab_nome->execute([$colab_id]);
                    $colab_nome_data = $stmt_colab_nome->fetch();
                    $colab_nome = $colab_nome_data['nome_completo'] ?? 'Colaborador #' . $colab_id;
                    
                    foreach ($bonus_list_calc as $idx => $bonus_calc) {
                        $bonus_pagos = verificar_bonus_ja_pago_extra($pdo, $bonus_calc['tipo_bonus_id'], $colab_id, $mes_referencia);
                        if (!empty($bonus_pagos)) {
                            $bonus_ja_pagos_info[$bonus_calc['tipo_bonus_id']] = $bonus_pagos;
                            $valor_bonus = 0;
                            if ($bonus_calc['tipo_valor'] === 'fixo') {
                                $valor_bonus = (float)($bonus_calc['valor_fixo'] ?? 0);
                            } else {
                                $valor_bonus = (float)($bonus_calc['valor'] ?? 0);
                            }
                            
                            $bonus_excluidos_colab[] = [
                                'tipo_bonus_nome' => $bonus_calc['tipo_bonus_nome'],
                                'valor' => $valor_bonus,
                                'fechamentos' => $bonus_pagos
                            ];
                            
                            // Adiciona à lista geral para mensagem
                            $bonus_excluidos_geral[] = [
                                'colaborador' => $colab_nome,
                                'tipo_bonus' => $bonus_calc['tipo_bonus_nome'],
                                'valor' => $valor_bonus,
                                'fechamento_id' => $bonus_pagos[0]['fechamento_id'] ?? null
                            ];
                            
                            // Remove da lista de cálculo
                            unset($bonus_list_calc[$idx]);
                        }
                    }
                    // Reindexa array
                    $bonus_list_calc = array_values($bonus_list_calc);
                }
                
                // Calcula total de bônus considerando o tipo e desconto por ocorrências configuradas
                $total_bonus = 0;
                foreach ($bonus_list_calc as $bonus_calc) {
                    $tipo_valor = $bonus_calc['tipo_valor'] ?? 'variavel';
                    
                    if ($tipo_valor === 'informativo') {
                        // Informativo não soma no total
                        continue;
                    }
                    
                    // Determina valor base do bônus
                    $valor_base = 0;
                    if ($tipo_valor === 'fixo') {
                        $valor_base = (float)($bonus_calc['valor_fixo'] ?? 0);
                    } else {
                        $valor_base = (float)($bonus_calc['valor'] ?? 0);
                    }
                    
                    // Calcula desconto por ocorrências configuradas
                    $desconto_ocorrencias = calcular_desconto_bonus_ocorrencias(
                        $pdo,
                        $bonus_calc['tipo_bonus_id'],
                        $colab_id,
                        $valor_base,
                        $data_inicio,
                        $data_fim
                    );
                    
                    // Limita desconto ao valor do bônus (não pode ficar negativo)
                    if ($desconto_ocorrencias > $valor_base) {
                        $desconto_ocorrencias = $valor_base;
                    }
                    
                    $total_bonus += ($valor_base - $desconto_ocorrencias);
                }
                
                // Busca ocorrências com desconto em R$ do período
                // Apenas ocorrências que têm valor_desconto > 0 e não desconta do banco de horas
                // Ignora ocorrências apenas informativas
                $stmt = $pdo->prepare("
                    SELECT SUM(valor_desconto) as total_descontos
                    FROM ocorrencias
                    WHERE colaborador_id = ?
                    AND data_ocorrencia >= ?
                    AND data_ocorrencia <= ?
                    AND valor_desconto > 0
                    AND (desconta_banco_horas = 0 OR desconta_banco_horas IS NULL)
                    AND (apenas_informativa = 0 OR apenas_informativa IS NULL)
                ");
                $stmt->execute([$colab_id, $data_inicio, $data_fim]);
                $ocorrencias_data = $stmt->fetch();
                $total_descontos_ocorrencias = $ocorrencias_data['total_descontos'] ?? 0;
                
                // Busca adiantamentos não descontados para este colaborador no mês de referência
                $adiantamentos_ids = [];
                $stmt = $pdo->prepare("
                    SELECT id, valor_descontar, observacoes
                    FROM fechamentos_pagamento_adiantamentos
                    WHERE colaborador_id = ?
                    AND mes_desconto = ?
                    AND descontado = 0
                ");
                $stmt->execute([$colab_id, $mes_referencia]);
                $adiantamentos = $stmt->fetchAll();
                
                $total_adiantamentos = 0;
                $adiantamentos_ids = [];
                foreach ($adiantamentos as $adiantamento) {
                    $total_adiantamentos += (float)$adiantamento['valor_descontar'];
                    $adiantamentos_ids[] = $adiantamento['id'];
                }
                
                // Adiciona adiantamentos aos descontos
                $total_descontos_ocorrencias += $total_adiantamentos;
                
                // Insere bônus no fechamento (incluindo informativos para registro)
                foreach ($bonus_list_calc as $bonus) {
                    $tipo_valor = $bonus['tipo_valor'] ?? 'variavel';
                    $valor_original = 0;
                    
                    if ($tipo_valor === 'fixo') {
                        // Usa valor fixo do tipo de bônus
                        $valor_original = (float)($bonus['valor_fixo'] ?? 0);
                    } elseif ($tipo_valor === 'variavel') {
                        // Usa valor do colaborador
                        $valor_original = (float)($bonus['valor'] ?? 0);
                    }
                    // Informativo: valor_original = 0 (não soma, mas registra)
                    
                    // Calcula desconto por ocorrências configuradas
                    $desconto_ocorrencias = calcular_desconto_bonus_ocorrencias(
                        $pdo,
                        $bonus['tipo_bonus_id'],
                        $colab_id,
                        $valor_original,
                        $data_inicio,
                        $data_fim
                    );
                    
                    // Limita desconto ao valor do bônus (não pode ficar negativo)
                    if ($desconto_ocorrencias > $valor_original) {
                        $desconto_ocorrencias = $valor_original;
                    }
                    
                    $valor_a_usar = $valor_original - $desconto_ocorrencias;
                    
                    // Busca detalhes do desconto para salvar em JSON
                    // Usa a mesma função de cálculo que já retorna os detalhes
                    $detalhes_array = [];
                    
                    // Busca configurações
                    $stmt_configs = $pdo->prepare("
                        SELECT tbo.*, to_cod.codigo as tipo_ocorrencia_codigo, to_cod.nome as tipo_ocorrencia_nome
                        FROM tipos_bonus_ocorrencias tbo
                        INNER JOIN tipos_ocorrencias to_cod ON tbo.tipo_ocorrencia_id = to_cod.id
                        WHERE tbo.tipo_bonus_id = ? AND tbo.ativo = 1
                    ");
                    $stmt_configs->execute([$bonus['tipo_bonus_id']]);
                    $configs_detalhes = $stmt_configs->fetchAll();
                    
                    foreach ($configs_detalhes as $config_det) {
                        $verificar_periodo_anterior = !empty($config_det['verificar_periodo_anterior']);
                        $periodo_anterior_meses = (int)($config_det['periodo_anterior_meses'] ?? 1);
                        
                        $total_periodo_anterior = 0;
                        $total_periodo_atual = 0;
                        
                        // Verifica período anterior se configurado
                        if ($verificar_periodo_anterior) {
                            $data_inicio_anterior = date('Y-m-01', strtotime($data_inicio . ' -' . $periodo_anterior_meses . ' months'));
                            $data_fim_anterior = date('Y-m-t', strtotime($data_inicio_anterior));
                            
                            $where_anterior = [
                                "o.colaborador_id = ?",
                                "o.data_ocorrencia >= ?",
                                "o.data_ocorrencia <= ?",
                                "o.tipo_ocorrencia_id = ?"
                            ];
                            $params_anterior = [$colab_id, $data_inicio_anterior, $data_fim_anterior, $config_det['tipo_ocorrencia_id']];
                            
                            if ($config_det['desconta_apenas_aprovadas']) {
                                $where_anterior[] = "o.status_aprovacao = 'aprovada'";
                            }
                            
                            if (!$config_det['desconta_banco_horas']) {
                                $where_anterior[] = "(o.desconta_banco_horas = 0 OR o.desconta_banco_horas IS NULL)";
                            }
                            
                            // Ignora ocorrências apenas informativas
                            $where_anterior[] = "(o.apenas_informativa = 0 OR o.apenas_informativa IS NULL)";
                            
                            $sql_anterior = "SELECT COUNT(*) as total FROM ocorrencias o WHERE " . implode(' AND ', $where_anterior);
                            $stmt_anterior = $pdo->prepare($sql_anterior);
                            $stmt_anterior->execute($params_anterior);
                            $result_anterior = $stmt_anterior->fetch();
                            $total_periodo_anterior = (int)($result_anterior['total'] ?? 0);
                        }
                        
                        // Verifica período atual
                        $where_atual = [
                            "o.colaborador_id = ?",
                            "o.data_ocorrencia >= ?",
                            "o.data_ocorrencia <= ?",
                            "o.tipo_ocorrencia_id = ?"
                        ];
                        $params_atual = [$colab_id, $data_inicio, $data_fim, $config_det['tipo_ocorrencia_id']];
                        
                        if ($config_det['desconta_apenas_aprovadas']) {
                            $where_atual[] = "o.status_aprovacao = 'aprovada'";
                        }
                        
                        if (!$config_det['desconta_banco_horas']) {
                            $where_atual[] = "(o.desconta_banco_horas = 0 OR o.desconta_banco_horas IS NULL)";
                        }
                        
                        // Ignora ocorrências apenas informativas
                        $where_atual[] = "(o.apenas_informativa = 0 OR o.apenas_informativa IS NULL)";
                        
                        $sql_atual = "SELECT COUNT(*) as total FROM ocorrencias o WHERE " . implode(' AND ', $where_atual);
                        $stmt_atual = $pdo->prepare($sql_atual);
                        $stmt_atual->execute($params_atual);
                        $result_atual = $stmt_atual->fetch();
                        $total_periodo_atual = (int)($result_atual['total'] ?? 0);
                        
                        // Adiciona aos detalhes se encontrou ocorrências
                        if ($total_periodo_anterior > 0 || $total_periodo_atual > 0) {
                            $detalhes_array[] = [
                                'tipo_ocorrencia_id' => $config_det['tipo_ocorrencia_id'],
                                'tipo_ocorrencia_codigo' => $config_det['tipo_ocorrencia_codigo'],
                                'tipo_ocorrencia_nome' => $config_det['tipo_ocorrencia_nome'],
                                'total_ocorrencias_periodo_atual' => $total_periodo_atual,
                                'total_ocorrencias_periodo_anterior' => $total_periodo_anterior,
                                'tipo_desconto' => $config_det['tipo_desconto'],
                                'valor_desconto' => $config_det['valor_desconto'],
                                'verificar_periodo_anterior' => $verificar_periodo_anterior,
                                'periodo_anterior_meses' => $periodo_anterior_meses
                            ];
                        }
                    }
                    
                    $detalhes_json = json_encode($detalhes_array);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_bonus 
                        (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, valor_original, desconto_ocorrencias, detalhes_desconto, observacoes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $observacao = $tipo_valor === 'fixo' 
                        ? 'Bônus automático (valor fixo do tipo)' 
                        : ($tipo_valor === 'informativo' 
                            ? 'Bônus informativo (não soma no total)' 
                            : 'Bônus automático do fechamento');
                    
                    if ($desconto_ocorrencias > 0) {
                        $total_ocorrencias_atual = array_sum(array_column($detalhes_array, 'total_ocorrencias_periodo_atual'));
                        $total_ocorrencias_anterior = array_sum(array_column($detalhes_array, 'total_ocorrencias_periodo_anterior'));
                        
                        if ($desconto_ocorrencias >= $valor_original) {
                            $observacao .= ' | Bônus zerado completamente';
                            if ($total_ocorrencias_anterior > 0) {
                                $observacao .= ' (ocorrência no período anterior)';
                            } elseif ($total_ocorrencias_atual > 0) {
                                $observacao .= ' (ocorrência no período atual)';
                            }
                        } else {
                            $observacao .= ' | Desconto por ' . $total_ocorrencias_atual . ' ocorrência(s) no período atual';
                            if ($total_ocorrencias_anterior > 0) {
                                $observacao .= ' e ' . $total_ocorrencias_anterior . ' no período anterior';
                            }
                            $observacao .= ': R$ ' . number_format($desconto_ocorrencias, 2, ',', '.');
                        }
                    }
                    
                    $stmt->execute([
                        $fechamento_id, 
                        $colab_id, 
                        $bonus['tipo_bonus_id'], 
                        $valor_a_usar,
                        $valor_original,
                        $desconto_ocorrencias,
                        $detalhes_json,
                        $observacao
                    ]);
                }
                
                $valor_total = $salario_base + $valor_horas_extras + $total_bonus - $total_descontos_ocorrencias;
                
                // Insere item (salva também informações sobre tipo de horas extras)
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento_itens 
                    (fechamento_id, colaborador_id, salario_base, horas_extras, valor_horas_extras, descontos, valor_total)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $fechamento_id, 
                    $colab_id, 
                    $salario_base, 
                    $horas_extras, 
                    $valor_horas_extras, 
                    $total_descontos_ocorrencias,
                    $valor_total
                ]);
                
                // Salva detalhes das horas extras (para exibição)
                $item_id = $pdo->lastInsertId();
                
                // Marca adiantamentos como descontados
                if (!empty($adiantamentos_ids)) {
                    $placeholders = implode(',', array_fill(0, count($adiantamentos_ids), '?'));
                    $stmt = $pdo->prepare("
                        UPDATE fechamentos_pagamento_adiantamentos 
                        SET descontado = 1, fechamento_desconto_id = ?
                        WHERE id IN ($placeholders)
                    ");
                    $params = array_merge([$fechamento_id], $adiantamentos_ids);
                    $stmt->execute($params);
                }
                if ($horas_extras > 0) {
                    // Busca detalhes das horas extras para salvar em JSON (se necessário no futuro)
                    $stmt_he_detalhes = $pdo->prepare("
                        SELECT tipo_pagamento, SUM(quantidade_horas) as total_horas, SUM(valor_total) as total_valor
                        FROM horas_extras
                        WHERE colaborador_id = ? AND data_trabalho >= ? AND data_trabalho <= ?
                        GROUP BY tipo_pagamento
                    ");
                    $stmt_he_detalhes->execute([$colab_id, $data_inicio, $data_fim]);
                    $he_detalhes = $stmt_he_detalhes->fetchAll();
                }
                
                $total_pagamento += $valor_total;
                $total_horas_extras += $valor_horas_extras;
            }
            
            // Atualiza totais do fechamento
            $stmt = $pdo->prepare("
                UPDATE fechamentos_pagamento 
                SET total_pagamento = ?, total_horas_extras = ?
                WHERE id = ?
            ");
            $stmt->execute([$total_pagamento, $total_horas_extras, $fechamento_id]);
            
            // Monta mensagem de sucesso
            $mensagem_sucesso = 'Fechamento criado com sucesso!';
            if ($excluir_bonus_ja_pagos && !empty($bonus_excluidos_geral)) {
                $total_excluidos = count($bonus_excluidos_geral);
                $mensagem_sucesso .= " {$total_excluidos} bônus já pagos em fechamentos extras foram excluídos automaticamente.";
            }
            
            redirect('fechamento_pagamentos.php?view=' . $fechamento_id, $mensagem_sucesso);
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao criar fechamento: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'atualizar_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $horas_extras = str_replace(',', '.', $_POST['horas_extras'] ?? '0');
        $valor_horas_extras = str_replace(['.', ','], ['', '.'], $_POST['valor_horas_extras'] ?? '0');
        $descontos = str_replace(['.', ','], ['', '.'], $_POST['descontos'] ?? '0');
        $adicionais = str_replace(['.', ','], ['', '.'], $_POST['adicionais'] ?? '0');
        $bonus_editados = $_POST['bonus_editados'] ?? [];
        
        try {
            // Busca item atual
            $stmt = $pdo->prepare("SELECT * FROM fechamentos_pagamento_itens WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                redirect('fechamento_pagamentos.php', 'Item não encontrado!', 'error');
            }
            
            // Processa bônus editados
            $total_bonus = 0;
            if (!empty($bonus_editados) && is_array($bonus_editados)) {
                // Remove bônus antigos do fechamento para este colaborador
                $stmt = $pdo->prepare("DELETE FROM fechamentos_pagamento_bonus WHERE fechamento_pagamento_id = ? AND colaborador_id = ?");
                $stmt->execute([$item['fechamento_id'], $item['colaborador_id']]);
                
                // Insere bônus editados
                foreach ($bonus_editados as $bonus_data) {
                    if (!empty($bonus_data['tipo_bonus_id']) && !empty($bonus_data['valor'])) {
                        $valor_bonus = str_replace(['.', ','], ['', '.'], $bonus_data['valor']);
                        $total_bonus += $valor_bonus;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO fechamentos_pagamento_bonus 
                            (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, observacoes)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $item['fechamento_id'],
                            $item['colaborador_id'],
                            (int)$bonus_data['tipo_bonus_id'],
                            $valor_bonus,
                            $bonus_data['observacoes'] ?? 'Bônus editado manualmente'
                        ]);
                    }
                }
            }
            
            $valor_total = $item['salario_base'] + $valor_horas_extras + $total_bonus - $descontos + $adicionais;
            
            // Atualiza item
            $stmt = $pdo->prepare("
                UPDATE fechamentos_pagamento_itens 
                SET horas_extras = ?, valor_horas_extras = ?, descontos = ?, adicionais = ?, valor_total = ?
                WHERE id = ?
            ");
            $stmt->execute([$horas_extras, $valor_horas_extras, $descontos, $adicionais, $valor_total, $item_id]);
            
            // Recalcula totais do fechamento
            $stmt = $pdo->prepare("
                SELECT SUM(valor_total) as total FROM fechamentos_pagamento_itens WHERE fechamento_id = ?
            ");
            $stmt->execute([$item['fechamento_id']]);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET total_pagamento = ? WHERE id = ?");
            $stmt->execute([$total, $item['fechamento_id']]);
            
            redirect('fechamento_pagamentos.php?view=' . $item['fechamento_id'], 'Item atualizado com sucesso!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao atualizar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'atualizar_item_bonus_especifico') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $fechamento_id = (int)($_POST['fechamento_id'] ?? 0);
        $tipo_bonus_id = (int)($_POST['tipo_bonus_id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        
        try {
            // Busca item atual
            $stmt = $pdo->prepare("SELECT * FROM fechamentos_pagamento_itens WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                redirect('fechamento_pagamentos.php', 'Item não encontrado!', 'error');
            }
            
            if (!$tipo_bonus_id) {
                redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Selecione um tipo de bônus!', 'error');
            }
            
            // Busca tipo de bônus
            $stmt = $pdo->prepare("SELECT * FROM tipos_bonus WHERE id = ?");
            $stmt->execute([$tipo_bonus_id]);
            $tipo_bonus = $stmt->fetch();
            
            if (!$tipo_bonus) {
                redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Tipo de bônus não encontrado!', 'error');
            }
            
            // Calcula valor do bônus
            $valor_bonus = 0;
            if ($tipo_bonus['tipo_valor'] === 'fixo') {
                $valor_bonus = (float)($tipo_bonus['valor_fixo'] ?? 0);
            } else {
                // Busca salário do colaborador para calcular valor variável
                $stmt = $pdo->prepare("SELECT salario FROM colaboradores WHERE id = ?");
                $stmt->execute([$item['colaborador_id']]);
                $colab = $stmt->fetch();
                if ($colab) {
                    $valor_bonus = (float)($tipo_bonus['valor'] ?? 0) * (float)($colab['salario'] ?? 0) / 100;
                }
            }
            
            // Remove bônus antigo
            $stmt = $pdo->prepare("DELETE FROM fechamentos_pagamento_bonus WHERE fechamento_pagamento_id = ? AND colaborador_id = ?");
            $stmt->execute([$fechamento_id, $item['colaborador_id']]);
            
            // Insere novo bônus
            $stmt = $pdo->prepare("
                INSERT INTO fechamentos_pagamento_bonus 
                (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, observacoes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fechamento_id,
                $item['colaborador_id'],
                $tipo_bonus_id,
                $valor_bonus,
                $motivo ?: 'Bônus específico editado'
            ]);
            
            // Atualiza item
            $stmt = $pdo->prepare("
                UPDATE fechamentos_pagamento_itens 
                SET valor_total = ?, motivo = ?
                WHERE id = ?
            ");
            $stmt->execute([$valor_bonus, $motivo, $item_id]);
            
            // Recalcula totais do fechamento
            $stmt = $pdo->prepare("
                SELECT SUM(valor_total) as total FROM fechamentos_pagamento_itens WHERE fechamento_id = ?
            ");
            $stmt->execute([$fechamento_id]);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET total_pagamento = ? WHERE id = ?");
            $stmt->execute([$total, $fechamento_id]);
            
            redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Bônus específico atualizado com sucesso!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao atualizar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'atualizar_item_bonus_grupal' || $action === 'atualizar_item_bonus_individual') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $fechamento_id = (int)($_POST['fechamento_id'] ?? 0);
        $tipo_bonus_id = !empty($_POST['tipo_bonus_id']) ? (int)$_POST['tipo_bonus_id'] : null;
        $valor_manual = str_replace(['.', ','], ['', '.'], $_POST['valor_manual'] ?? '0');
        $motivo = trim($_POST['motivo'] ?? '');
        
        try {
            // Busca item atual
            $stmt = $pdo->prepare("SELECT * FROM fechamentos_pagamento_itens WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                redirect('fechamento_pagamentos.php', 'Item não encontrado!', 'error');
            }
            
            if (empty($valor_manual) || (float)$valor_manual <= 0) {
                redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Informe um valor válido!', 'error');
            }
            
            if (empty($motivo)) {
                redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Informe o motivo!', 'error');
            }
            
            $valor_final = (float)$valor_manual;
            
            // Remove bônus antigo se existir
            $stmt = $pdo->prepare("DELETE FROM fechamentos_pagamento_bonus WHERE fechamento_pagamento_id = ? AND colaborador_id = ?");
            $stmt->execute([$fechamento_id, $item['colaborador_id']]);
            
            // Insere novo bônus se tiver tipo
            if ($tipo_bonus_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento_bonus 
                    (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, observacoes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $fechamento_id,
                    $item['colaborador_id'],
                    $tipo_bonus_id,
                    $valor_final,
                    $motivo
                ]);
            }
            
            // Atualiza item
            $stmt = $pdo->prepare("
                UPDATE fechamentos_pagamento_itens 
                SET valor_total = ?, valor_manual = ?, motivo = ?
                WHERE id = ?
            ");
            $stmt->execute([$valor_final, $valor_final, $motivo, $item_id]);
            
            // Recalcula totais do fechamento
            $stmt = $pdo->prepare("
                SELECT SUM(valor_total) as total FROM fechamentos_pagamento_itens WHERE fechamento_id = ?
            ");
            $stmt->execute([$fechamento_id]);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET total_pagamento = ? WHERE id = ?");
            $stmt->execute([$total, $fechamento_id]);
            
            $tipo_label = $action === 'atualizar_item_bonus_grupal' ? 'Bônus Grupal' : 'Bônus Individual';
            redirect('fechamento_pagamentos.php?view=' . $fechamento_id, $tipo_label . ' atualizado com sucesso!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao atualizar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'atualizar_item_adiantamento') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $fechamento_id = (int)($_POST['fechamento_id'] ?? 0);
        $valor_adiantamento = str_replace(['.', ','], ['', '.'], $_POST['valor_adiantamento'] ?? '0');
        $mes_desconto = $_POST['mes_desconto'] ?? '';
        $motivo = trim($_POST['motivo'] ?? '');
        
        try {
            // Busca item atual
            $stmt = $pdo->prepare("SELECT * FROM fechamentos_pagamento_itens WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                redirect('fechamento_pagamentos.php', 'Item não encontrado!', 'error');
            }
            
            if (empty($valor_adiantamento) || (float)$valor_adiantamento <= 0) {
                redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Informe um valor válido!', 'error');
            }
            
            if (empty($mes_desconto)) {
                redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Informe o mês de desconto!', 'error');
            }
            
            if (empty($motivo)) {
                redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Informe o motivo!', 'error');
            }
            
            $valor_final = (float)$valor_adiantamento;
            
            // Atualiza item
            $stmt = $pdo->prepare("
                UPDATE fechamentos_pagamento_itens 
                SET valor_total = ?, valor_manual = ?, motivo = ?
                WHERE id = ?
            ");
            $stmt->execute([$valor_final, $valor_final, $motivo, $item_id]);
            
            // Atualiza registro de adiantamento
            $stmt = $pdo->prepare("
                UPDATE fechamentos_pagamento_adiantamentos 
                SET valor_adiantamento = ?, valor_descontar = ?, mes_desconto = ?, observacoes = ?
                WHERE fechamento_pagamento_id = ? AND colaborador_id = ?
            ");
            $stmt->execute([
                $valor_final,
                $valor_final, // Por padrão desconta o valor total
                $mes_desconto,
                $motivo,
                $fechamento_id,
                $item['colaborador_id']
            ]);
            
            // Se não encontrou registro, cria um novo
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento_adiantamentos 
                    (fechamento_pagamento_id, colaborador_id, valor_adiantamento, valor_descontar, mes_desconto, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $fechamento_id,
                    $item['colaborador_id'],
                    $valor_final,
                    $valor_final,
                    $mes_desconto,
                    $motivo
                ]);
            }
            
            // Recalcula totais do fechamento
            $stmt = $pdo->prepare("
                SELECT SUM(valor_total) as total FROM fechamentos_pagamento_itens WHERE fechamento_id = ?
            ");
            $stmt->execute([$fechamento_id]);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET total_pagamento = ? WHERE id = ?");
            $stmt->execute([$total, $fechamento_id]);
            
            redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Adiantamento atualizado com sucesso!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao atualizar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'fechar') {
        $fechamento_id = (int)($_POST['fechamento_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET status = 'fechado' WHERE id = ?");
            $stmt->execute([$fechamento_id]);
            
            // Envia emails para cada colaborador do fechamento
            require_once __DIR__ . '/../includes/email_templates.php';
            $stmt_itens = $pdo->prepare("SELECT colaborador_id FROM fechamentos_pagamento_itens WHERE fechamento_id = ?");
            $stmt_itens->execute([$fechamento_id]);
            $itens = $stmt_itens->fetchAll();
            
            foreach ($itens as $item) {
                enviar_email_fechamento_pagamento($fechamento_id, $item['colaborador_id']);
            }
            
            redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Fechamento concluído!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao fechar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $fechamento_id = (int)($_POST['fechamento_id'] ?? 0);
        try {
            // Verifica permissão
            $stmt = $pdo->prepare("SELECT empresa_id, status FROM fechamentos_pagamento WHERE id = ?");
            $stmt->execute([$fechamento_id]);
            $fechamento = $stmt->fetch();
            
            if (!$fechamento) {
                redirect('fechamento_pagamentos.php', 'Fechamento não encontrado!', 'error');
            }
            
            if ($usuario['role'] !== 'ADMIN' && $fechamento['empresa_id'] != $usuario['empresa_id']) {
                redirect('fechamento_pagamentos.php', 'Você não tem permissão para excluir este fechamento!', 'error');
            }
            
            // Deleta fechamento (os itens serão deletados automaticamente por CASCADE)
            $stmt = $pdo->prepare("DELETE FROM fechamentos_pagamento WHERE id = ?");
            $stmt->execute([$fechamento_id]);
            
            redirect('fechamento_pagamentos.php', 'Fechamento excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'criar_fechamento_extra') {
        $empresa_id_input = $_POST['empresa_id'] ?? '';
        $tipo_fechamento = 'extra';
        $subtipo_fechamento = $_POST['subtipo_fechamento'] ?? '';
        $data_pagamento = $_POST['data_pagamento'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $referencia_externa = $_POST['referencia_externa'] ?? '';
        $mes_referencia = $_POST['mes_referencia'] ?? date('Y-m');
        
        // Validações básicas
        if (empty($empresa_id_input) || $empresa_id_input === '' || empty($subtipo_fechamento) || empty($data_pagamento)) {
            redirect('fechamento_pagamentos.php', 'Preencha todos os campos obrigatórios!', 'error');
        }
        
        // Se for "todas", empresa_id será determinado pelos colaboradores selecionados
        $todas_empresas = ($empresa_id_input === 'todas');
        $empresa_id = $todas_empresas ? null : (int)$empresa_id_input;
        
        // Valida subtipo
        $subtipos_validos = ['bonus_especifico', 'individual', 'grupal', 'adiantamento'];
        if (!in_array($subtipo_fechamento, $subtipos_validos)) {
            redirect('fechamento_pagamentos.php', 'Subtipo de fechamento inválido!', 'error');
        }
        
        $fechamentos_criados = []; // Inicializa para estar disponível no catch
        
        try {
            // Se for "todas empresas", precisa agrupar colaboradores por empresa
            if ($todas_empresas) {
                // Busca empresas dos colaboradores selecionados
                $colaboradores_input = [];
                if ($subtipo_fechamento === 'bonus_especifico' || $subtipo_fechamento === 'grupal') {
                    $colaboradores_input = $_POST['colaboradores'] ?? [];
                } elseif ($subtipo_fechamento === 'individual' || $subtipo_fechamento === 'adiantamento') {
                    $colab_id = (int)($_POST['colaborador_id'] ?? 0);
                    if ($colab_id) {
                        $colaboradores_input = [$colab_id];
                    }
                }
                
                if (empty($colaboradores_input)) {
                    redirect('fechamento_pagamentos.php', 'Selecione pelo menos um colaborador!', 'error');
                }
                
                // Agrupa colaboradores por empresa
                $colaboradores_por_empresa = [];
                foreach ($colaboradores_input as $colab_id) {
                    $colab_id = (int)$colab_id;
                    $stmt = $pdo->prepare("SELECT empresa_id FROM colaboradores WHERE id = ?");
                    $stmt->execute([$colab_id]);
                    $colab = $stmt->fetch();
                    if ($colab && $colab['empresa_id']) {
                        $emp_id = (int)$colab['empresa_id'];
                        if (!isset($colaboradores_por_empresa[$emp_id])) {
                            $colaboradores_por_empresa[$emp_id] = [];
                        }
                        $colaboradores_por_empresa[$emp_id][] = $colab_id;
                    }
                }
                
                if (empty($colaboradores_por_empresa)) {
                    redirect('fechamento_pagamentos.php', 'Nenhum colaborador válido encontrado!', 'error');
                }
                
                // Valida permissões: filtra apenas empresas que o usuário tem permissão
                $empresas_permitidas = [];
                if ($usuario['role'] === 'ADMIN') {
                    // ADMIN pode ver todas as empresas
                    $empresas_permitidas = array_keys($colaboradores_por_empresa);
                } elseif ($usuario['role'] === 'RH') {
                    // RH pode ter múltiplas empresas
                    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                        $empresas_permitidas = array_intersect(array_keys($colaboradores_por_empresa), $usuario['empresas_ids']);
                    } else {
                        $empresas_permitidas = [($usuario['empresa_id'] ?? 0)];
                    }
                } else {
                    // Outros usuários só podem ver sua própria empresa
                    $empresas_permitidas = [($usuario['empresa_id'] ?? 0)];
                }
                
                // Filtra colaboradores apenas para empresas permitidas
                $colaboradores_por_empresa_filtrado = [];
                foreach ($empresas_permitidas as $emp_id) {
                    if (isset($colaboradores_por_empresa[$emp_id])) {
                        $colaboradores_por_empresa_filtrado[$emp_id] = $colaboradores_por_empresa[$emp_id];
                    }
                }
                
                if (empty($colaboradores_por_empresa_filtrado)) {
                    redirect('fechamento_pagamentos.php', 'Você não tem permissão para criar fechamentos para essas empresas!', 'error');
                }
                
                // Cria um fechamento para cada empresa permitida
                $fechamentos_criados = [];
                foreach ($colaboradores_por_empresa_filtrado as $emp_id => $colabs_empresa) {
                    // Cria fechamento para esta empresa
                    $stmt = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento 
                        (empresa_id, tipo_fechamento, subtipo_fechamento, mes_referencia, data_fechamento, data_pagamento, descricao, referencia_externa, total_colaboradores, usuario_id, status)
                        VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, 0, ?, 'aberto')
                    ");
                    $stmt->execute([
                        $emp_id, 
                        $tipo_fechamento, 
                        $subtipo_fechamento, 
                        $mes_referencia,
                        $data_pagamento,
                        $descricao,
                        $referencia_externa,
                        $usuario['id']
                    ]);
                    $fechamento_id = $pdo->lastInsertId();
                    if (!$fechamento_id) {
                        throw new Exception("Erro ao criar fechamento: não foi possível obter o ID do fechamento criado");
                    }
                    
                    $fechamentos_criados[$fechamento_id] = $colabs_empresa; // Armazena colaboradores junto com fechamento_id
                }
            } else {
                // Cria fechamento único
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento 
                    (empresa_id, tipo_fechamento, subtipo_fechamento, mes_referencia, data_fechamento, data_pagamento, descricao, referencia_externa, total_colaboradores, usuario_id, status)
                    VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, 0, ?, 'aberto')
                ");
                $stmt->execute([
                    $empresa_id, 
                    $tipo_fechamento, 
                    $subtipo_fechamento, 
                    $mes_referencia,
                    $data_pagamento,
                    $descricao,
                    $referencia_externa,
                    $usuario['id']
                ]);
                $fechamento_id = $pdo->lastInsertId();
                if (!$fechamento_id) {
                    throw new Exception("Erro ao criar fechamento: não foi possível obter o ID do fechamento criado");
                }
                
                $fechamentos_criados = [$fechamento_id => null]; // null indica que deve usar $_POST
            }
            
            // Processa cada fechamento criado
            foreach ($fechamentos_criados as $fechamento_id => $colabs_fechamento) {
                try {
                    // $fechamento_id já é o ID correto (chave do array associativo)
                    // $colabs_fechamento será null se não for "todas empresas", ou será um array de colaboradores se for "todas"
                    
                    // Busca empresa_id do fechamento (caso tenha sido criado com "todas")
                    $stmt = $pdo->prepare("SELECT empresa_id FROM fechamentos_pagamento WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    $fechamento_data = $stmt->fetch();
                    
                    if (!$fechamento_data) {
                        // Fechamento não encontrado, pula
                        continue;
                    }
                
                $empresa_id = $fechamento_data['empresa_id'];
            
                $total_pagamento = 0;
                $colaboradores_ids = [];
                
                // Processa conforme o subtipo
                if ($subtipo_fechamento === 'bonus_especifico') {
                    // Bônus Específico: múltiplos colaboradores, um tipo de bônus
                    // Tenta usar o campo normal primeiro, depois o hidden
                    $tipo_bonus_id_raw = $_POST['tipo_bonus_id'] ?? '';
                    if (empty($tipo_bonus_id_raw)) {
                        $tipo_bonus_id_raw = $_POST['tipo_bonus_id_hidden'] ?? '';
                    }
                    $tipo_bonus_id = !empty($tipo_bonus_id_raw) ? (int)$tipo_bonus_id_raw : 0;
                    
                    // Coleta colaboradores: primeiro tenta usar os armazenados (caso "todas"), senão usa $_POST
                    if ($colabs_fechamento !== null && is_array($colabs_fechamento) && !empty($colabs_fechamento)) {
                        $colaboradores_ids = $colabs_fechamento;
                    } else {
                        // Tenta coletar do POST (pode ser array ou string)
                        $colaboradores_post = $_POST['colaboradores'] ?? [];
                        if (is_string($colaboradores_post)) {
                            $colaboradores_ids = [$colaboradores_post];
                        } elseif (is_array($colaboradores_post)) {
                            $colaboradores_ids = array_filter($colaboradores_post, function($v) { return !empty($v); });
                        } else {
                            $colaboradores_ids = [];
                        }
                    }
                    
                    $aplicar_descontos = isset($_POST['aplicar_descontos']) && $_POST['aplicar_descontos'] == '1';
                    
                    if (empty($tipo_bonus_id) || empty($colaboradores_ids)) {
                        // Não deleta o fechamento - apenas registra erro e continua
                        // Atualiza descrição do fechamento com erro
                        $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Fechamento criado mas sem colaboradores válidos ou tipo de bônus') WHERE id = ?");
                        $stmt->execute([$fechamento_id]);
                        continue;
                    }
                
                // Busca informações do tipo de bônus
                $stmt = $pdo->prepare("SELECT tipo_valor, valor_fixo, nome FROM tipos_bonus WHERE id = ?");
                $stmt->execute([$tipo_bonus_id]);
                $tipo_bonus = $stmt->fetch();
                
                if (!$tipo_bonus) {
                    // Não deleta - apenas atualiza descrição com erro
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Tipo de bônus não encontrado') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue; // Continua processando outros fechamentos
                }
                
                // Calcula período (mês de referência)
                $ano_mes = explode('-', $mes_referencia);
                $data_inicio = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
                $data_fim = date('Y-m-t', strtotime($data_inicio));
                
                foreach ($colaboradores_ids as $colab_id) {
                    $colab_id = (int)$colab_id;
                    
                    // Busca dados do colaborador
                    $stmt = $pdo->prepare("SELECT salario FROM colaboradores WHERE id = ?");
                    $stmt->execute([$colab_id]);
                    $colab = $stmt->fetch();
                    
                    if (!$colab) continue;
                    
                    // Determina valor do bônus
                    $valor_bonus = 0;
                    if ($tipo_bonus['tipo_valor'] === 'fixo') {
                        $valor_bonus = (float)($tipo_bonus['valor_fixo'] ?? 0);
                    } else {
                        // Busca valor do colaborador
                        $stmt = $pdo->prepare("SELECT valor FROM colaboradores_bonus WHERE colaborador_id = ? AND tipo_bonus_id = ? AND (data_inicio IS NULL OR data_inicio <= ?) AND (data_fim IS NULL OR data_fim >= ?) LIMIT 1");
                        $stmt->execute([$colab_id, $tipo_bonus_id, $data_fim, $data_inicio]);
                        $colab_bonus = $stmt->fetch();
                        $valor_bonus = $colab_bonus ? (float)$colab_bonus['valor'] : 0;
                    }
                    
                    // Calcula desconto por ocorrências se solicitado
                    $desconto_ocorrencias = 0;
                    $detalhes_desconto = [];
                    if ($aplicar_descontos && $valor_bonus > 0) {
                        $desconto_ocorrencias = calcular_desconto_bonus_ocorrencias($pdo, $tipo_bonus_id, $colab_id, $valor_bonus, $data_inicio, $data_fim);
                        if ($desconto_ocorrencias > $valor_bonus) {
                            $desconto_ocorrencias = $valor_bonus;
                        }
                    }
                    
                    $valor_final = $valor_bonus - $desconto_ocorrencias;
                    
                    // Insere item (sem salário, sem horas extras, apenas bônus)
                    $stmt = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_itens 
                        (fechamento_id, colaborador_id, salario_base, horas_extras, valor_horas_extras, descontos, valor_total, inclui_salario, inclui_horas_extras, inclui_bonus_automaticos, motivo)
                        VALUES (?, ?, 0, 0, 0, 0, ?, 0, 0, 0, ?)
                    ");
                    $stmt->execute([
                        $fechamento_id,
                        $colab_id,
                        $valor_final,
                        $descricao ?: 'Bônus específico: ' . $tipo_bonus['nome']
                    ]);
                    
                    // Insere bônus
                    $stmt = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_bonus 
                        (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, valor_original, desconto_ocorrencias, observacoes)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $obs = 'Bônus específico - Fechamento extra';
                    if ($desconto_ocorrencias > 0) {
                        $obs .= ' | Desconto por ocorrências: R$ ' . number_format($desconto_ocorrencias, 2, ',', '.');
                    }
                    $stmt->execute([
                        $fechamento_id,
                        $colab_id,
                        $tipo_bonus_id,
                        $valor_final,
                        $valor_bonus,
                        $desconto_ocorrencias,
                        $obs
                    ]);
                    
                    $total_pagamento += $valor_final;
                }
                
            } elseif ($subtipo_fechamento === 'individual') {
                // Log inicial do que está sendo recebido
                log_fechamento_individual("INÍCIO - Processar fechamento individual", [
                    'fechamento_id' => $fechamento_id,
                    'POST_colaborador_id' => $_POST['colaborador_id'] ?? null,
                    'POST_colaborador_id_hidden' => $_POST['colaborador_id_hidden'] ?? null,
                    'POST_tipo_bonus_id' => $_POST['tipo_bonus_id'] ?? null,
                    'POST_tipo_bonus_id_hidden_individual' => $_POST['tipo_bonus_id_hidden_individual'] ?? null,
                    'POST_valor_manual' => $_POST['valor_manual'] ?? null,
                    'POST_motivo' => $_POST['motivo'] ?? null,
                    'POST_motivo_hidden' => $_POST['motivo_hidden'] ?? null,
                    'colabs_fechamento' => $colabs_fechamento,
                    'POST_keys' => array_keys($_POST)
                ]);
                
                // Individual: um colaborador, valor livre ou tipo de bônus
                // Se tem colaboradores armazenados (caso "todas"), usa o primeiro, senão usa $_POST
                $colab_id = ($colabs_fechamento !== null && is_array($colabs_fechamento) && !empty($colabs_fechamento)) 
                    ? (int)$colabs_fechamento[0] 
                    : (int)($_POST['colaborador_id'] ?? 0);
                
                log_fechamento_individual("Colaborador inicial", [
                    'fechamento_id' => $fechamento_id,
                    'colab_id_inicial' => $colab_id,
                    'colabs_fechamento' => $colabs_fechamento
                ]);
                
                // Tenta usar o campo normal primeiro, depois o hidden
                $tipo_bonus_id_raw = $_POST['tipo_bonus_id'] ?? '';
                if (empty($tipo_bonus_id_raw)) {
                    $tipo_bonus_id_raw = $_POST['tipo_bonus_id_hidden_individual'] ?? '';
                }
                $tipo_bonus_id = !empty($tipo_bonus_id_raw) ? (int)$tipo_bonus_id_raw : null;
                
                // Tenta usar o campo normal primeiro, depois o hidden
                $valor_manual_raw = $_POST['valor_manual'] ?? '';
                if (empty($valor_manual_raw)) {
                    $valor_manual_raw = $_POST['valor_manual_hidden'] ?? '';
                }
                $valor_manual = str_replace(['.', ','], ['', '.'], $valor_manual_raw ?: '0');
                
                // Tenta usar o campo normal primeiro, depois o hidden
                $motivo = trim($_POST['motivo'] ?? '');
                if (empty($motivo)) {
                    $motivo = trim($_POST['motivo_hidden'] ?? '');
                }
                
                // Verifica se valor_manual é maior que 0 após conversão
                $valor_manual_float = (float)$valor_manual;
                
                // Debug: verifica o que está sendo recebido
                // Se colab_id está vazio, tenta buscar do POST novamente (incluindo hidden)
                if (empty($colab_id)) {
                    $colab_id = (int)($_POST['colaborador_id'] ?? 0);
                    if (empty($colab_id)) {
                        $colab_id = (int)($_POST['colaborador_id_hidden'] ?? 0);
                    }
                }
                
                // Se ainda estiver vazio e tiver colaboradores armazenados, usa o primeiro
                if (empty($colab_id) && $colabs_fechamento !== null && is_array($colabs_fechamento) && !empty($colabs_fechamento)) {
                    $colab_id = (int)$colabs_fechamento[0];
                }
                
                log_fechamento_individual("Valores coletados antes da validação", [
                    'fechamento_id' => $fechamento_id,
                    'colab_id' => $colab_id,
                    'colab_id_empty' => empty($colab_id),
                    'tipo_bonus_id' => $tipo_bonus_id,
                    'tipo_bonus_id_empty' => empty($tipo_bonus_id),
                    'valor_manual_raw' => $valor_manual_raw ?? '',
                    'valor_manual' => $valor_manual,
                    'valor_manual_float' => $valor_manual_float,
                    'valor_manual_float_le_zero' => ($valor_manual_float <= 0),
                    'motivo' => $motivo,
                    'motivo_empty' => empty($motivo)
                ]);
                
                if (empty($colab_id) || (empty($tipo_bonus_id) && ($valor_manual_float <= 0))) {
                    log_fechamento_individual("ERRO - Validação falhou", [
                        'fechamento_id' => $fechamento_id,
                        'colab_id' => $colab_id,
                        'tipo_bonus_id' => $tipo_bonus_id,
                        'valor_manual_float' => $valor_manual_float,
                        'erro_colab' => empty($colab_id),
                        'erro_valor_tipo' => (empty($tipo_bonus_id) && ($valor_manual_float <= 0))
                    ]);
                    
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Preencha colaborador e valor ou tipo de bônus') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue;
                }
                
                log_fechamento_individual("Validação OK - Continuando processamento", [
                    'fechamento_id' => $fechamento_id,
                    'colab_id' => $colab_id,
                    'tipo_bonus_id' => $tipo_bonus_id,
                    'valor_manual_float' => $valor_manual_float
                ]);
                
                if (empty($motivo)) {
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Campo motivo é obrigatório') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue;
                }
                
                $colaboradores_ids = [$colab_id];
                
                // Busca dados do colaborador
                $stmt = $pdo->prepare("SELECT salario FROM colaboradores WHERE id = ?");
                $stmt->execute([$colab_id]);
                $colab = $stmt->fetch();
                
                if (!$colab) {
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Colaborador não encontrado') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue;
                }
                
                $valor_final = 0;
                
                if ($tipo_bonus_id) {
                    // Usa tipo de bônus
                    $stmt = $pdo->prepare("SELECT tipo_valor, valor_fixo, nome FROM tipos_bonus WHERE id = ?");
                    $stmt->execute([$tipo_bonus_id]);
                    $tipo_bonus = $stmt->fetch();
                    
                    if ($tipo_bonus) {
                        if ($tipo_bonus['tipo_valor'] === 'fixo') {
                            $valor_final = (float)($tipo_bonus['valor_fixo'] ?? 0);
                        } else {
                            // Busca valor do colaborador ou usa valor manual se fornecido
                            if ($valor_manual > 0) {
                                $valor_final = (float)$valor_manual;
                            } else {
                                $ano_mes = explode('-', $mes_referencia);
                                $data_inicio = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
                                $data_fim = date('Y-m-t', strtotime($data_inicio));
                                $stmt = $pdo->prepare("SELECT valor FROM colaboradores_bonus WHERE colaborador_id = ? AND tipo_bonus_id = ? AND (data_inicio IS NULL OR data_inicio <= ?) AND (data_fim IS NULL OR data_fim >= ?) LIMIT 1");
                                $stmt->execute([$colab_id, $tipo_bonus_id, $data_fim, $data_inicio]);
                                $colab_bonus = $stmt->fetch();
                                $valor_final = $colab_bonus ? (float)$colab_bonus['valor'] : 0;
                            }
                        }
                    }
                } else {
                    // Valor livre
                    $valor_final = (float)$valor_manual;
                }
                
                // Insere item
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento_itens 
                    (fechamento_id, colaborador_id, salario_base, horas_extras, valor_horas_extras, descontos, valor_total, valor_manual, inclui_salario, inclui_horas_extras, inclui_bonus_automaticos, motivo)
                    VALUES (?, ?, 0, 0, 0, 0, ?, ?, 0, 0, 0, ?)
                ");
                $stmt->execute([
                    $fechamento_id,
                    $colab_id,
                    $valor_final,
                    $valor_final,
                    $motivo
                ]);
                
                // Insere bônus se tiver tipo
                if ($tipo_bonus_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_bonus 
                        (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, observacoes)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $fechamento_id,
                        $colab_id,
                        $tipo_bonus_id,
                        $valor_final,
                        $motivo
                    ]);
                }
                
                $total_pagamento = $valor_final;
                
            } elseif ($subtipo_fechamento === 'grupal') {
                // Grupal: múltiplos colaboradores, mesmo valor
                // Se tem colaboradores armazenados (caso "todas"), usa eles, senão usa $_POST
                $colaboradores_ids = ($colabs_fechamento !== null && is_array($colabs_fechamento)) 
                    ? $colabs_fechamento 
                    : ($_POST['colaboradores'] ?? []);
                // Tenta usar o campo normal primeiro, depois o hidden
                $tipo_bonus_id_raw = $_POST['tipo_bonus_id'] ?? '';
                if (empty($tipo_bonus_id_raw)) {
                    $tipo_bonus_id_raw = $_POST['tipo_bonus_id_hidden_grupal'] ?? '';
                }
                $tipo_bonus_id = !empty($tipo_bonus_id_raw) ? (int)$tipo_bonus_id_raw : null;
                $valor_manual = str_replace(['.', ','], ['', '.'], $_POST['valor_manual'] ?? '0');
                // Tenta usar o campo normal primeiro, depois o hidden
                $motivo = trim($_POST['motivo'] ?? '');
                if (empty($motivo)) {
                    $motivo = trim($_POST['motivo_hidden_grupal'] ?? '');
                }
                
                if (empty($colaboradores_ids) || (empty($tipo_bonus_id) && empty($valor_manual))) {
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Selecione colaboradores e informe valor ou tipo de bônus') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue;
                }
                
                if (empty($motivo)) {
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Campo motivo é obrigatório') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue;
                }
                
                $valor_final = 0;
                
                if ($tipo_bonus_id) {
                    // Usa tipo de bônus (mesmo valor para todos)
                    $stmt = $pdo->prepare("SELECT tipo_valor, valor_fixo, nome FROM tipos_bonus WHERE id = ?");
                    $stmt->execute([$tipo_bonus_id]);
                    $tipo_bonus = $stmt->fetch();
                    
                    if ($tipo_bonus) {
                        if ($tipo_bonus['tipo_valor'] === 'fixo') {
                            $valor_final = (float)($tipo_bonus['valor_fixo'] ?? 0);
                        } else {
                            $valor_final = (float)$valor_manual; // Valor manual fornecido
                        }
                    }
                } else {
                    // Valor livre (mesmo para todos)
                    $valor_final = (float)$valor_manual;
                }
                
                foreach ($colaboradores_ids as $colab_id) {
                    $colab_id = (int)$colab_id;
                    
                    // Busca dados do colaborador
                    $stmt = $pdo->prepare("SELECT salario FROM colaboradores WHERE id = ?");
                    $stmt->execute([$colab_id]);
                    $colab = $stmt->fetch();
                    
                    if (!$colab) continue;
                    
                    // Insere item
                    $stmt = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_itens 
                        (fechamento_id, colaborador_id, salario_base, horas_extras, valor_horas_extras, descontos, valor_total, valor_manual, inclui_salario, inclui_horas_extras, inclui_bonus_automaticos, motivo)
                        VALUES (?, ?, 0, 0, 0, 0, ?, ?, 0, 0, 0, ?)
                    ");
                    $stmt->execute([
                        $fechamento_id,
                        $colab_id,
                        $valor_final,
                        $valor_final,
                        $motivo
                    ]);
                    
                    // Insere bônus se tiver tipo
                    if ($tipo_bonus_id) {
                        $stmt = $pdo->prepare("
                            INSERT INTO fechamentos_pagamento_bonus 
                            (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, observacoes)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $fechamento_id,
                            $colab_id,
                            $tipo_bonus_id,
                            $valor_final,
                            $motivo
                        ]);
                    }
                    
                    $total_pagamento += $valor_final;
                }
                
            } elseif ($subtipo_fechamento === 'adiantamento') {
                // Adiantamento: um colaborador, valor livre, mês de desconto
                // Se tem colaboradores armazenados (caso "todas"), usa o primeiro, senão usa $_POST
                $colab_id = ($colabs_fechamento !== null && is_array($colabs_fechamento) && !empty($colabs_fechamento)) 
                    ? (int)$colabs_fechamento[0] 
                    : (int)($_POST['colaborador_id'] ?? 0);
                $valor_adiantamento = str_replace(['.', ','], ['', '.'], $_POST['valor_adiantamento'] ?? '0');
                $mes_desconto = $_POST['mes_desconto'] ?? '';
                // Tenta usar o campo normal primeiro, depois o hidden
                $motivo = trim($_POST['motivo'] ?? '');
                if (empty($motivo)) {
                    $motivo = trim($_POST['motivo_hidden_adiantamento'] ?? '');
                }
                
                if (empty($colab_id) || empty($valor_adiantamento) || empty($mes_desconto)) {
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Preencha colaborador, valor e mês de desconto') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue;
                }
                
                if (empty($motivo)) {
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Campo motivo é obrigatório') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue;
                }
                
                $valor_adiantamento = (float)$valor_adiantamento;
                $colaboradores_ids = [$colab_id];
                
                // Busca dados do colaborador
                $stmt = $pdo->prepare("SELECT salario FROM colaboradores WHERE id = ?");
                $stmt->execute([$colab_id]);
                $colab = $stmt->fetch();
                
                if (!$colab) {
                    $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET descricao = CONCAT(COALESCE(descricao, ''), ' | ERRO: Colaborador não encontrado') WHERE id = ?");
                    $stmt->execute([$fechamento_id]);
                    continue;
                }
                
                // Insere item
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento_itens 
                    (fechamento_id, colaborador_id, salario_base, horas_extras, valor_horas_extras, descontos, valor_total, valor_manual, inclui_salario, inclui_horas_extras, inclui_bonus_automaticos, motivo)
                    VALUES (?, ?, 0, 0, 0, 0, ?, ?, 0, 0, 0, ?)
                ");
                $stmt->execute([
                    $fechamento_id,
                    $colab_id,
                    $valor_adiantamento,
                    $valor_adiantamento,
                    $motivo
                ]);
                
                // Registra adiantamento para desconto futuro
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento_adiantamentos 
                    (fechamento_pagamento_id, colaborador_id, valor_adiantamento, valor_descontar, mes_desconto, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $fechamento_id,
                    $colab_id,
                    $valor_adiantamento,
                    $valor_adiantamento, // Por padrão desconta o valor total
                    $mes_desconto,
                    $motivo
                ]);
                
                $total_pagamento = $valor_adiantamento;
            }
            
                // Atualiza fechamento com totais
                // Garante que colaboradores_ids seja um array válido
                if (!is_array($colaboradores_ids)) {
                    $colaboradores_ids = [];
                }
                
                // Garante que total_pagamento seja um número válido
                $total_pagamento = (float)$total_pagamento;
                
                $stmt = $pdo->prepare("
                    UPDATE fechamentos_pagamento 
                    SET total_colaboradores = ?, total_pagamento = ?
                    WHERE id = ?
                ");
                $stmt->execute([count($colaboradores_ids), $total_pagamento, $fechamento_id]);
                
                // Envia notificações para colaboradores
                require_once __DIR__ . '/../includes/notificacoes.php';
                $subtipo_labels = [
                    'bonus_especifico' => 'Bônus Específico',
                    'individual' => 'Bônus Individual',
                    'grupal' => 'Bônus Grupal',
                    'adiantamento' => 'Adiantamento'
                ];
                $subtipo_label = $subtipo_labels[$subtipo_fechamento] ?? 'Pagamento Extra';
                
                foreach ($colaboradores_ids as $colab_id) {
                    try {
                        criar_notificacao(
                            null, // usuario_id será buscado internamente
                            $colab_id,
                            'fechamento_pagamento_extra',
                            'Novo Pagamento Extra',
                            "Você recebeu um {$subtipo_label} no valor de R$ " . number_format($total_pagamento / count($colaboradores_ids), 2, ',', '.'),
                            '../pages/meus_pagamentos.php',
                            $fechamento_id,
                            'pagamento'
                        );
                    } catch (Exception $e) {
                        // Erro ao criar notificação não bloqueia criação do fechamento
                    }
                }
                } catch (Exception $e) {
                    // Erro ao processar fechamento - continua processando outros fechamentos
                }
            } // Fim do loop de fechamentos
            
            // Pega o primeiro fechamento criado para redirecionar
            $fechamentos_criados_ids = array_keys($fechamentos_criados);
            $primeiro_fechamento = !empty($fechamentos_criados_ids) ? $fechamentos_criados_ids[0] : null;
            $total_criados = count($fechamentos_criados_ids);
            $mensagem = $total_criados > 1 
                ? "Fechamentos extras criados com sucesso! ({$total_criados} fechamentos criados)"
                : 'Fechamento extra criado com sucesso!';
            
            if ($primeiro_fechamento) {
                // Verifica se o fechamento existe antes de redirecionar
                $stmt_check = $pdo->prepare("SELECT id FROM fechamentos_pagamento WHERE id = ?");
                $stmt_check->execute([$primeiro_fechamento]);
                $check_result = $stmt_check->fetch();
                
                if ($check_result) {
                    redirect('fechamento_pagamentos.php?view=' . $primeiro_fechamento, $mensagem);
                } else {
                    // Se não encontrou, tenta redirecionar mesmo assim (pode ser problema de timing)
                    redirect('fechamento_pagamentos.php?view=' . $primeiro_fechamento, $mensagem);
                }
            } else {
                redirect('fechamento_pagamentos.php', 'Erro: Nenhum fechamento foi criado.');
            }
            
        } catch (PDOException $e) {
            // Não deleta fechamentos criados - deixa para o usuário decidir
            // Se houver fechamentos criados, tenta redirecionar para o primeiro
            if (!empty($fechamentos_criados)) {
                $fechamentos_ids = array_keys($fechamentos_criados);
                $primeiro_fechamento = $fechamentos_ids[0] ?? null;
                if ($primeiro_fechamento) {
                    redirect('fechamento_pagamentos.php?view=' . $primeiro_fechamento, 'Fechamento criado, mas houve erro no processamento. Verifique os detalhes.', 'warning');
                }
            }
            
            redirect('fechamento_pagamentos.php', 'Erro ao criar fechamento extra: ' . $e->getMessage(), 'error');
        } catch (Exception $e) {
            // Não deleta fechamentos criados - deixa para o usuário decidir
            // Se houver fechamentos criados, tenta redirecionar para o primeiro
            if (!empty($fechamentos_criados)) {
                $fechamentos_ids = array_keys($fechamentos_criados);
                $primeiro_fechamento = $fechamentos_ids[0] ?? null;
                if ($primeiro_fechamento) {
                    redirect('fechamento_pagamentos.php?view=' . $primeiro_fechamento, 'Fechamento criado, mas houve erro no processamento. Verifique os detalhes.', 'warning');
                }
            }
            
            redirect('fechamento_pagamentos.php', 'Erro ao criar fechamento extra: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca fechamentos
$where = [];
$params = [];

// Filtros
$filtro_tipo = $_GET['filtro_tipo'] ?? '';
$filtro_subtipo = $_GET['filtro_subtipo'] ?? '';
$filtro_data_pagamento = $_GET['filtro_data_pagamento'] ?? '';

if ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "f.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    } else {
        $where[] = "f.empresa_id = ?";
        $params[] = $usuario['empresa_id'] ?? 0;
    }
} elseif ($usuario['role'] !== 'ADMIN') {
    $where[] = "f.empresa_id = ?";
    $params[] = $usuario['empresa_id'] ?? 0;
}

// Aplica filtros
if ($filtro_tipo) {
    $where[] = "f.tipo_fechamento = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_subtipo) {
    $where[] = "f.subtipo_fechamento = ?";
    $params[] = $filtro_subtipo;
}

if ($filtro_data_pagamento) {
    $where[] = "f.data_pagamento = ?";
    $params[] = $filtro_data_pagamento;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT f.*, e.nome_fantasia as empresa_nome, u.nome as usuario_nome
    FROM fechamentos_pagamento f
    LEFT JOIN empresas e ON f.empresa_id = e.id
    LEFT JOIN usuarios u ON f.usuario_id = u.id
    $where_sql
    ORDER BY f.data_pagamento DESC, f.mes_referencia DESC, f.id DESC
");
$stmt->execute($params);
$fechamentos = $stmt->fetchAll();

// Calcula totais de pagamento para o card de informações
$total_folha = 0;
$total_bonus = 0;
$total_extras = 0;
$total_folha_bonus = 0;

// Busca total de folha (soma de salários de todos os colaboradores ativos)
$where_colaboradores = [];
$params_colaboradores = [];

if ($usuario['role'] === 'ADMIN') {
    // ADMIN vê todos
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where_colaboradores[] = "c.empresa_id IN ($placeholders)";
        $params_colaboradores = array_merge($params_colaboradores, $usuario['empresas_ids']);
    } else {
        $where_colaboradores[] = "c.empresa_id = ?";
        $params_colaboradores[] = $usuario['empresa_id'] ?? 0;
    }
} else {
    $where_colaboradores[] = "c.empresa_id = ?";
    $params_colaboradores[] = $usuario['empresa_id'] ?? 0;
}

$where_colaboradores[] = "c.status = 'ativo'";
$where_colaboradores[] = "c.salario IS NOT NULL AND c.salario > 0";

$where_colab_sql = !empty($where_colaboradores) ? 'WHERE ' . implode(' AND ', $where_colaboradores) : '';

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(c.salario), 0) as total_folha
    FROM colaboradores c
    $where_colab_sql
");
$stmt->execute($params_colaboradores);
$result_folha = $stmt->fetch();
$total_folha = (float)($result_folha['total_folha'] ?? 0);

// Busca total de bônus cadastrados nos colaboradores (ativos e não informativos)
$where_bonus = [];
$params_bonus = [];

if ($usuario['role'] === 'ADMIN') {
    // ADMIN vê todos
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where_bonus[] = "c.empresa_id IN ($placeholders)";
        $params_bonus = array_merge($params_bonus, $usuario['empresas_ids']);
    } else {
        $where_bonus[] = "c.empresa_id = ?";
        $params_bonus[] = $usuario['empresa_id'] ?? 0;
    }
} else {
    $where_bonus[] = "c.empresa_id = ?";
    $params_bonus[] = $usuario['empresa_id'] ?? 0;
}

$where_bonus[] = "c.status = 'ativo'";
$where_bonus[] = "tb.tipo_valor != 'informativo'";
// Verifica se o bônus está ativo (data_inicio e data_fim válidas ou NULL)
$where_bonus[] = "(cb.data_inicio IS NULL OR cb.data_inicio <= CURDATE())";
$where_bonus[] = "(cb.data_fim IS NULL OR cb.data_fim >= CURDATE())";
$where_bonus_sql = !empty($where_bonus) ? 'WHERE ' . implode(' AND ', $where_bonus) : '';

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(
        CASE 
            WHEN tb.tipo_valor = 'fixo' THEN COALESCE(tb.valor_fixo, 0)
            ELSE COALESCE(cb.valor, 0)
        END
    ), 0) as total_bonus
    FROM colaboradores_bonus cb
    INNER JOIN colaboradores c ON cb.colaborador_id = c.id
    INNER JOIN tipos_bonus tb ON cb.tipo_bonus_id = tb.id
    $where_bonus_sql
");
$stmt->execute($params_bonus);
$result_bonus = $stmt->fetch();
$total_bonus = (float)($result_bonus['total_bonus'] ?? 0);

// Busca total de horas extras cadastradas que ainda não foram pagas
// Horas extras não pagas são aquelas cuja data_trabalho não está dentro do período de nenhum fechamento fechado/pago
$where_extras = [];
$params_extras = [];

if ($usuario['role'] === 'ADMIN') {
    // ADMIN vê todos
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where_extras[] = "c.empresa_id IN ($placeholders)";
        $params_extras = array_merge($params_extras, $usuario['empresas_ids']);
    } else {
        $where_extras[] = "c.empresa_id = ?";
        $params_extras[] = $usuario['empresa_id'] ?? 0;
    }
} else {
    $where_extras[] = "c.empresa_id = ?";
    $params_extras[] = $usuario['empresa_id'] ?? 0;
}

$where_extras[] = "c.status = 'ativo'";
$where_extras[] = "(he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)"; // Apenas horas extras pagas em dinheiro

// Monta subquery para verificar fechamentos fechados/pagos
$where_fechamentos = ["fp.status IN ('fechado', 'pago')", "fp.tipo_fechamento = 'regular'"];
$params_fechamentos = [];

if ($usuario['role'] === 'ADMIN') {
    // ADMIN vê todos
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders_fp = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where_fechamentos[] = "fp.empresa_id IN ($placeholders_fp)";
        $params_fechamentos = array_merge($params_fechamentos, $usuario['empresas_ids']);
    } else {
        $where_fechamentos[] = "fp.empresa_id = ?";
        $params_fechamentos[] = $usuario['empresa_id'] ?? 0;
    }
} else {
    $where_fechamentos[] = "fp.empresa_id = ?";
    $params_fechamentos[] = $usuario['empresa_id'] ?? 0;
}

$where_extras_sql = !empty($where_extras) ? 'WHERE ' . implode(' AND ', $where_extras) : '';
$where_fechamentos_sql = 'WHERE ' . implode(' AND ', $where_fechamentos);

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(he.valor_total), 0) as total_extras
    FROM horas_extras he
    INNER JOIN colaboradores c ON he.colaborador_id = c.id
    $where_extras_sql
    AND NOT EXISTS (
        SELECT 1 
        FROM fechamentos_pagamento fp
        $where_fechamentos_sql
        AND he.data_trabalho >= DATE_FORMAT(fp.mes_referencia, '%Y-%m-01')
        AND he.data_trabalho <= LAST_DAY(fp.mes_referencia)
    )
");
$params_extras_exec = array_merge($params_extras, $params_fechamentos);
$stmt->execute($params_extras_exec);
$result_extras = $stmt->fetch();
$total_extras = (float)($result_extras['total_extras'] ?? 0);

// Calcula folha + bônus
$total_folha_bonus = $total_folha + $total_bonus;

// Busca empresas para o select
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) AND status = 'ativo' ORDER BY nome_fantasia");
        $stmt->execute($usuario['empresas_ids']);
        $empresas = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$usuario['empresa_id'] ?? 0]);
        $empresas = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$usuario['empresa_id'] ?? 0]);
    $empresas = $stmt->fetchAll();
}

// Se está visualizando um fechamento específico
$fechamento_view = null;
$itens_fechamento = [];
$bonus_por_colaborador = [];
if (isset($_GET['view'])) {
    $fechamento_id = (int)$_GET['view'];
    $stmt = $pdo->prepare("
        SELECT f.*, e.nome_fantasia as empresa_nome, u.nome as usuario_nome
        FROM fechamentos_pagamento f
        LEFT JOIN empresas e ON f.empresa_id = e.id
        LEFT JOIN usuarios u ON f.usuario_id = u.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fechamento_id]);
    $fechamento_view = $stmt->fetch();
    
    if ($fechamento_view) {
        // Verifica permissão
        if ($usuario['role'] !== 'ADMIN' && $fechamento_view['empresa_id'] != $usuario['empresa_id']) {
            redirect('fechamento_pagamentos.php', 'Você não tem permissão para visualizar este fechamento!', 'error');
        }
        
        // Busca itens (incluindo campos de documento)
        $stmt = $pdo->prepare("
            SELECT i.*, c.nome_completo as colaborador_nome, c.id as colaborador_id,
                   i.documento_anexo, i.documento_status, i.documento_data_envio,
                   i.documento_data_aprovacao, i.documento_observacoes,
                   u_aprovador.nome as aprovador_nome
            FROM fechamentos_pagamento_itens i
            INNER JOIN colaboradores c ON i.colaborador_id = c.id
            LEFT JOIN usuarios u_aprovador ON i.documento_aprovado_por = u_aprovador.id
            WHERE i.fechamento_id = ?
            ORDER BY c.nome_completo
        ");
        $stmt->execute([$fechamento_id]);
        $itens_fechamento = $stmt->fetchAll();
        
        // Calcula estatísticas de documentos
        $stats_pendentes = 0;
        $stats_enviados = 0;
        $stats_aprovados = 0;
        $stats_rejeitados = 0;
        
        foreach ($itens_fechamento as $item) {
            $status = $item['documento_status'] ?? 'pendente';
            if ($status === 'pendente') $stats_pendentes++;
            elseif ($status === 'enviado') $stats_enviados++;
            elseif ($status === 'aprovado') $stats_aprovados++;
            elseif ($status === 'rejeitado') $stats_rejeitados++;
        }
        
        // Busca período do fechamento para buscar bônus ativos
        $ano_mes = explode('-', $fechamento_view['mes_referencia']);
        $data_inicio_periodo = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
        $data_fim_periodo = date('Y-m-t', strtotime($data_inicio_periodo));
        
        // Busca bônus de cada colaborador no fechamento
        // Primeiro tenta buscar dos bônus salvos no fechamento
        foreach ($itens_fechamento as $item) {
            $stmt = $pdo->prepare("
                SELECT fb.*, tb.nome as tipo_bonus_nome, tb.tipo_valor, tb.valor_fixo,
                       COALESCE(fb.valor_original, fb.valor) as valor_original,
                       COALESCE(fb.desconto_ocorrencias, 0) as desconto_ocorrencias
                FROM fechamentos_pagamento_bonus fb
                INNER JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
                WHERE fb.fechamento_pagamento_id = ? AND fb.colaborador_id = ?
            ");
            $stmt->execute([$fechamento_id, $item['colaborador_id']]);
            $bonus_salvos = $stmt->fetchAll();
            
            // Se não encontrou bônus salvos no fechamento, busca bônus ativos do colaborador
            // Mas apenas se o fechamento inclui bônus automáticos (fechamentos extras podem não incluir)
            $inclui_bonus_automaticos_item = $item['inclui_bonus_automaticos'] ?? true;
            if (empty($bonus_salvos) && $inclui_bonus_automaticos_item) {
                $stmt = $pdo->prepare("
                    SELECT cb.*, tb.nome as tipo_bonus_nome, tb.tipo_valor, tb.valor_fixo
                    FROM colaboradores_bonus cb
                    INNER JOIN tipos_bonus tb ON cb.tipo_bonus_id = tb.id
                    WHERE cb.colaborador_id = ?
                    AND (
                        (cb.data_inicio IS NULL AND cb.data_fim IS NULL)
                        OR (
                            (cb.data_inicio IS NULL OR cb.data_inicio <= ?)
                            AND (cb.data_fim IS NULL OR cb.data_fim >= ?)
                        )
                    )
                ");
                $stmt->execute([$item['colaborador_id'], $data_fim_periodo, $data_inicio_periodo]);
                $bonus_salvos = $stmt->fetchAll();
                
                // Ajusta valores baseado no tipo e calcula desconto se necessário
                foreach ($bonus_salvos as &$bonus) {
                    $tipo_valor = $bonus['tipo_valor'] ?? 'variavel';
                    if ($tipo_valor === 'fixo') {
                        $bonus['valor'] = $bonus['valor_fixo'] ?? 0;
                    }
                    
                    // Inicializa campos de desconto se não existirem
                    if (!isset($bonus['valor_original']) || $bonus['valor_original'] === null) {
                        $bonus['valor_original'] = $bonus['valor'] ?? 0;
                    }
                    if (!isset($bonus['desconto_ocorrencias']) || $bonus['desconto_ocorrencias'] === null) {
                        // Se não tem desconto salvo, calcula agora (para fechamentos antigos)
                        $bonus['desconto_ocorrencias'] = calcular_desconto_bonus_ocorrencias(
                            $pdo,
                            $bonus['tipo_bonus_id'],
                            $item['colaborador_id'],
                            $bonus['valor_original'],
                            $data_inicio_periodo,
                            $data_fim_periodo
                        );
                        // Ajusta valor final
                        $bonus['valor'] = $bonus['valor_original'] - $bonus['desconto_ocorrencias'];
                    }
                }
            }
            
            $bonus_por_colaborador[$item['colaborador_id']] = $bonus_salvos;
        }
    }
}

// Busca tipos de bônus para o modal de edição
$stmt = $pdo->query("SELECT * FROM tipos_bonus WHERE status = 'ativo' ORDER BY nome");
$tipos_bonus = $stmt->fetchAll();

$page_title = 'Fechamento de Pagamentos';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Fechamento de Pagamentos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="colaboradores.php" class="text-muted text-hover-primary">Colaboradores</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Fechamento de Pagamentos</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        
        <?php if ($fechamento_view): ?>
        <!-- Visualização de fechamento específico -->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Fechamento - <?= date('m/Y', strtotime($fechamento_view['mes_referencia'] . '-01')) ?></h3>
                <div class="card-toolbar">
                    <?php if ($fechamento_view['status'] === 'aberto'): ?>
                    <form method="POST" style="display: inline;" id="form_fechar_<?= $fechamento_view['id'] ?>">
                        <input type="hidden" name="action" value="fechar">
                        <input type="hidden" name="fechamento_id" value="<?= $fechamento_view['id'] ?>">
                        <button type="button" class="btn btn-success" onclick="fecharFechamento(<?= $fechamento_view['id'] ?>)">
                            Fechar Fechamento
                        </button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-danger ms-2" onclick="deletarFechamento(<?= $fechamento_view['id'] ?>, '<?= date('m/Y', strtotime($fechamento_view['mes_referencia'] . '-01')) ?>')">
                        <i class="ki-duotone ki-trash fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Excluir
                    </button>
                    <a href="fechamento_pagamentos.php" class="btn btn-light ms-2">Voltar</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-7">
                    <div class="col-md-3">
                        <strong>Empresa:</strong> <?= htmlspecialchars($fechamento_view['empresa_nome']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Mês/Ano:</strong> <?= date('m/Y', strtotime($fechamento_view['mes_referencia'] . '-01')) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong> 
                        <?php if ($fechamento_view['status'] === 'aberto'): ?>
                            <span class="badge badge-light-warning">Aberto</span>
                        <?php elseif ($fechamento_view['status'] === 'fechado'): ?>
                            <span class="badge badge-light-info">Fechado</span>
                        <?php else: ?>
                            <span class="badge badge-light-success">Pago</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Total:</strong> <span class="text-success fw-bold">R$ <?= number_format($fechamento_view['total_pagamento'], 2, ',', '.') ?></span>
                    </div>
                </div>
                
                <?php 
                $tipo_fechamento_view = $fechamento_view['tipo_fechamento'] ?? 'regular';
                $subtipo_fechamento_view = $fechamento_view['subtipo_fechamento'] ?? '';
                $data_pagamento_view = $fechamento_view['data_pagamento'] ?? null;
                $referencia_externa_view = $fechamento_view['referencia_externa'] ?? '';
                $descricao_view = $fechamento_view['descricao'] ?? '';
                
                if ($tipo_fechamento_view === 'extra'): 
                ?>
                <div class="alert alert-primary d-flex align-items-center mb-7">
                    <i class="ki-duotone ki-information-5 fs-2x text-primary me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1">
                            <span class="badge badge-primary me-2">FECHAMENTO EXTRA</span>
                            <?php
                            $subtipo_labels = [
                                'bonus_especifico' => 'Bônus Específico',
                                'individual' => 'Bônus Individual',
                                'grupal' => 'Bônus Grupal',
                                'adiantamento' => 'Adiantamento'
                            ];
                            echo '<span class="badge badge-light-primary">' . htmlspecialchars($subtipo_labels[$subtipo_fechamento_view] ?? $subtipo_fechamento_view) . '</span>';
                            ?>
                        </div>
                        <?php if ($data_pagamento_view): ?>
                            <div class="text-gray-700"><strong>Data de Pagamento:</strong> <?= date('d/m/Y', strtotime($data_pagamento_view)) ?></div>
                        <?php endif; ?>
                        <?php if ($referencia_externa_view): ?>
                            <div class="text-gray-700"><strong>Referência:</strong> <?= htmlspecialchars($referencia_externa_view) ?></div>
                        <?php endif; ?>
                        <?php if ($descricao_view): ?>
                            <div class="text-gray-700 mt-2"><strong>Descrição:</strong> <?= nl2br(htmlspecialchars($descricao_view)) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($fechamento_view['status'] === 'fechado'): ?>
                <!--begin::Estatísticas de Documentos-->
                <div class="row g-3 mb-7">
                    <div class="col-md-3">
                        <div class="card bg-light-danger">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Pendentes</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= $stats_pendentes ?></span>
                                    </div>
                                    <i class="ki-duotone ki-time fs-1 text-danger">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-warning">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Enviados</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= $stats_enviados ?></span>
                                    </div>
                                    <i class="ki-duotone ki-file-up fs-1 text-warning">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Aprovados</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= $stats_aprovados ?></span>
                                    </div>
                                    <i class="ki-duotone ki-check-circle fs-1 text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-info">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Total Itens</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= count($itens_fechamento) ?></span>
                                    </div>
                                    <i class="ki-duotone ki-people fs-1 text-info">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Estatísticas de Documentos-->
                <?php endif; ?>
                
                <?php 
                $is_fechamento_extra = ($tipo_fechamento_view === 'extra');
                // Determina quais colunas mostrar baseado no primeiro item (todos têm mesma configuração)
                $primeiro_item = !empty($itens_fechamento) ? $itens_fechamento[0] : null;
                $inclui_salario = !$is_fechamento_extra || ($primeiro_item && ($primeiro_item['inclui_salario'] ?? true));
                $inclui_horas_extras = !$is_fechamento_extra || ($primeiro_item && ($primeiro_item['inclui_horas_extras'] ?? true));
                ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <?php if ($inclui_salario): ?>
                            <th>Salário Base</th>
                            <?php endif; ?>
                            <?php if ($inclui_horas_extras): ?>
                            <th>Horas Extras</th>
                            <th>Valor H.E.</th>
                            <?php endif; ?>
                            <th>Bônus</th>
                            <?php if ($inclui_salario): ?>
                            <th>Descontos</th>
                            <th>Adicionais</th>
                            <?php endif; ?>
                            <?php if ($is_fechamento_extra): ?>
                            <th>Motivo</th>
                            <?php endif; ?>
                            <th>Total</th>
                            <?php if ($fechamento_view['status'] === 'fechado' && !$is_fechamento_extra): ?>
                            <th>Documento</th>
                            <?php endif; ?>
                            <?php if ($fechamento_view['status'] === 'aberto'): ?>
                            <th>Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens_fechamento as $item): 
                            $bonus_colab = $bonus_por_colaborador[$item['colaborador_id']] ?? [];
                            // Calcula total de bônus considerando apenas os que não são informativos
                            // O valor já vem com desconto aplicado se houver
                            $total_bonus = 0;
                            foreach ($bonus_colab as $bonus_item) {
                                $tipo_valor = $bonus_item['tipo_valor'] ?? 'variavel';
                                if ($tipo_valor !== 'informativo') {
                                    // Usa o valor já descontado (se houver desconto por faltas, já está aplicado)
                                    $total_bonus += (float)($bonus_item['valor'] ?? 0);
                                }
                            }
                            
                            // Busca detalhes das horas extras apenas se for fechamento regular ou se incluir horas extras
                            $horas_dinheiro = 0;
                            $horas_banco = 0;
                            $valor_dinheiro = 0;
                            
                            if ($inclui_horas_extras && !$is_fechamento_extra) {
                                // Busca detalhes das horas extras para mostrar tipo de pagamento
                                // Considera NULL como 'dinheiro' para compatibilidade com registros antigos
                                $stmt_he = $pdo->prepare("
                                    SELECT 
                                        COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN quantidade_horas ELSE 0 END), 0) as horas_dinheiro,
                                        COALESCE(SUM(CASE WHEN tipo_pagamento = 'banco_horas' THEN quantidade_horas ELSE 0 END), 0) as horas_banco,
                                        COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN valor_total ELSE 0 END), 0) as valor_dinheiro
                                    FROM horas_extras
                                    WHERE colaborador_id = ? AND data_trabalho >= ? AND data_trabalho <= ?
                                ");
                                $stmt_he->execute([$item['colaborador_id'], $data_inicio_periodo, $data_fim_periodo]);
                                $he_detalhes = $stmt_he->fetch();
                                $horas_dinheiro = (float)($he_detalhes['horas_dinheiro'] ?? 0);
                                $horas_banco = (float)($he_detalhes['horas_banco'] ?? 0);
                                $valor_dinheiro = (float)($he_detalhes['valor_dinheiro'] ?? 0);
                            }
                            
                            // Calcula valor total
                            if ($is_fechamento_extra) {
                                // Para fechamentos extras, usa valor_total diretamente (já calculado)
                                $valor_total_com_bonus = $item['valor_total'];
                            } else {
                                $valor_total_com_bonus = $item['salario_base'] + $item['valor_horas_extras'] + $total_bonus + ($item['adicionais'] ?? 0) - ($item['descontos'] ?? 0);
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['colaborador_nome']) ?></strong>
                                <?php if (!empty($bonus_colab)): ?>
                                <br><small class="text-muted">
                                    <?php foreach ($bonus_colab as $bonus): ?>
                                    <span class="badge badge-light-success me-1"><?= htmlspecialchars($bonus['tipo_bonus_nome']) ?>: <?= formatar_moeda($bonus['valor']) ?></span>
                                    <?php endforeach; ?>
                                </small>
                                <?php endif; ?>
                                <?php if ($is_fechamento_extra && isset($item['valor_manual']) && $item['valor_manual'] > 0): ?>
                                <br><small class="text-info">Valor Manual: R$ <?= number_format($item['valor_manual'], 2, ',', '.') ?></small>
                                <?php endif; ?>
                            </td>
                            <?php if ($inclui_salario): ?>
                            <td>R$ <?= number_format($item['salario_base'], 2, ',', '.') ?></td>
                            <?php endif; ?>
                            <?php if ($inclui_horas_extras): ?>
                            <td>
                                <?php if ($horas_dinheiro > 0 || $horas_banco > 0): ?>
                                    <div class="d-flex flex-column">
                                        <?php if ($horas_dinheiro > 0): ?>
                                            <span class="text-success fw-bold">
                                                <?= number_format($horas_dinheiro, 2, ',', '.') ?>h
                                                <small class="text-muted">(R$)</small>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($horas_banco > 0): ?>
                                            <span class="text-info fw-bold">
                                                <?= number_format($horas_banco, 2, ',', '.') ?>h
                                                <small class="text-muted">(Banco)</small>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($horas_dinheiro > 0 && $horas_banco > 0): ?>
                                            <small class="text-muted mt-1">
                                                Total: <?= number_format($horas_dinheiro + $horas_banco, 2, ',', '.') ?>h
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">0h</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($valor_dinheiro > 0): ?>
                                    <span class="text-success fw-bold">R$ <?= number_format($valor_dinheiro, 2, ',', '.') ?></span>
                                <?php elseif ($horas_banco > 0): ?>
                                    <span class="text-info">
                                        <i class="ki-duotone ki-information-5 fs-7">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Banco de Horas
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">R$ 0,00</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?php 
                                // Separa bônus informativos dos que somam
                                $bonus_somam = [];
                                $bonus_informativos = [];
                                foreach ($bonus_colab as $bonus_item) {
                                    $tipo_valor = $bonus_item['tipo_valor'] ?? 'variavel';
                                    if ($tipo_valor === 'informativo') {
                                        $bonus_informativos[] = $bonus_item;
                                    } else {
                                        $bonus_somam[] = $bonus_item;
                                    }
                                }
                                ?>
                                <?php 
                                // Verifica se há descontos por ocorrências
                                $tem_desconto_ocorrencias = false;
                                $total_desconto_ocorrencias = 0;
                                foreach ($bonus_colab as $bonus_item) {
                                    if (!empty($bonus_item['desconto_ocorrencias']) && $bonus_item['desconto_ocorrencias'] > 0) {
                                        $tem_desconto_ocorrencias = true;
                                        $total_desconto_ocorrencias += (float)$bonus_item['desconto_ocorrencias'];
                                    }
                                }
                                ?>
                                <?php if ($total_bonus > 0 || !empty($bonus_informativos) || $tem_desconto_ocorrencias): ?>
                                    <?php if (!empty($bonus_colab)): ?>
                                    <a href="#" class="text-success fw-bold text-hover-primary" onclick="mostrarDetalhesBonus(<?= htmlspecialchars(json_encode([
                                        'colaborador_nome' => $item['colaborador_nome'],
                                        'bonus' => $bonus_colab,
                                        'total' => $total_bonus,
                                        'bonus_somam' => $bonus_somam,
                                        'bonus_informativos' => $bonus_informativos,
                                        'total_desconto_ocorrencias' => $total_desconto_ocorrencias
                                    ])) ?>); return false;" title="Clique para ver detalhes dos bônus">
                                        R$ <?= number_format($total_bonus, 2, ',', '.') ?>
                                        <?php if ($tem_desconto_ocorrencias): ?>
                                            <br><small class="text-danger">Desconto ocorrências: -R$ <?= number_format($total_desconto_ocorrencias, 2, ',', '.') ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($bonus_informativos)): ?>
                                            <span class="badge badge-light-info ms-1" title="Possui bônus informativos"><?= count($bonus_informativos) ?> info</span>
                                        <?php endif; ?>
                                    </a>
                                    <?php else: ?>
                                    <strong class="text-success">R$ <?= number_format($total_bonus, 2, ',', '.') ?></strong>
                                    <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">R$ 0,00</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($inclui_salario): ?>
                            <td class="text-danger fw-bold">R$ <?= number_format($item['descontos'] ?? 0, 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($item['adicionais'] ?? 0, 2, ',', '.') ?></td>
                            <?php endif; ?>
                            <?php if ($is_fechamento_extra): ?>
                            <td>
                                <?php if (!empty($item['motivo'])): ?>
                                    <small><?= nl2br(htmlspecialchars($item['motivo'])) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td><strong>R$ <?= number_format($valor_total_com_bonus, 2, ',', '.') ?></strong></td>
                            <?php if ($fechamento_view['status'] === 'aberto'): ?>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light-primary dropdown-toggle" type="button" id="dropdownAcoes_<?= $item['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        Ações
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownAcoes_<?= $item['id'] ?>">
                                        <li>
                                            <?php if ($is_fechamento_extra): ?>
                                                <?php if ($subtipo_fechamento_view === 'bonus_especifico'): ?>
                                                    <a class="dropdown-item" href="#" onclick="editarItemBonusEspecifico(<?= htmlspecialchars(json_encode($item)) ?>, '<?= $subtipo_fechamento_view ?>'); return false;">
                                                        <i class="ki-duotone ki-pencil fs-6 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Editar Bônus
                                                    </a>
                                                <?php elseif ($subtipo_fechamento_view === 'grupal'): ?>
                                                    <a class="dropdown-item" href="#" onclick="editarItemBonusGrupal(<?= htmlspecialchars(json_encode($item)) ?>, '<?= $subtipo_fechamento_view ?>'); return false;">
                                                        <i class="ki-duotone ki-pencil fs-6 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Editar Valor
                                                    </a>
                                                <?php elseif ($subtipo_fechamento_view === 'individual'): ?>
                                                    <a class="dropdown-item" href="#" onclick="editarItemBonusIndividual(<?= htmlspecialchars(json_encode($item)) ?>, '<?= $subtipo_fechamento_view ?>'); return false;">
                                                        <i class="ki-duotone ki-pencil fs-6 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Editar Valor
                                                    </a>
                                                <?php elseif ($subtipo_fechamento_view === 'adiantamento'): ?>
                                                    <a class="dropdown-item" href="#" onclick="editarItemAdiantamento(<?= htmlspecialchars(json_encode($item)) ?>, '<?= $subtipo_fechamento_view ?>'); return false;">
                                                        <i class="ki-duotone ki-pencil fs-6 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Editar Adiantamento
                                                    </a>
                                                <?php else: ?>
                                                    <a class="dropdown-item" href="#" onclick="editarItem(<?= htmlspecialchars(json_encode($item)) ?>); return false;">
                                                        <i class="ki-duotone ki-pencil fs-6 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Editar
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a class="dropdown-item" href="#" onclick="editarItem(<?= htmlspecialchars(json_encode($item)) ?>); return false;">
                                                    <i class="ki-duotone ki-pencil fs-6 me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Editar
                                                </a>
                                            <?php endif; ?>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="verDetalhesPagamento(<?= $fechamento_view['id'] ?>, <?= $item['colaborador_id'] ?>); return false;">
                                                <i class="ki-duotone ki-eye fs-6 me-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                Ver Detalhes
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                            <?php elseif ($fechamento_view['status'] === 'fechado' && !$is_fechamento_extra): ?>
                            <td>
                                <?php
                                $status_doc = $item['documento_status'] ?? 'pendente';
                                $badges = [
                                    'pendente' => '<span class="badge badge-light-danger">Pendente</span>',
                                    'enviado' => '<span class="badge badge-light-warning">Enviado</span>',
                                    'aprovado' => '<span class="badge badge-light-success">Aprovado</span>',
                                    'rejeitado' => '<span class="badge badge-light-danger">Rejeitado</span>'
                                ];
                                echo $badges[$status_doc] ?? '<span class="badge badge-light-secondary">-</span>';
                                ?>
                                <?php if (!empty($item['documento_anexo'])): ?>
                                    <br><button type="button" class="btn btn-sm btn-light-primary mt-1" 
                                            onclick="verDocumentoAdmin(<?= $fechamento_view['id'] ?>, <?= $item['id'] ?>)">
                                        <i class="ki-duotone ki-eye fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Ver
                                    </button>
                                <?php endif; ?>
                                <?php if ($status_doc === 'enviado'): ?>
                                    <br><button type="button" class="btn btn-sm btn-success mt-1" 
                                            onclick="aprovarDocumento(<?= $item['id'] ?>)">
                                        <i class="ki-duotone ki-check fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Aprovar
                                    </button>
                                    <br><button type="button" class="btn btn-sm btn-danger mt-1" 
                                            onclick="rejeitarDocumento(<?= $item['id'] ?>)">
                                        <i class="ki-duotone ki-cross fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Rejeitar
                                    </button>
                                <?php endif; ?>
                                <?php if ($item['documento_observacoes']): ?>
                                    <br><small class="text-muted" title="<?= htmlspecialchars($item['documento_observacoes']) ?>">
                                        <?= htmlspecialchars(mb_substr($item['documento_observacoes'], 0, 30)) ?>
                                        <?= mb_strlen($item['documento_observacoes']) > 30 ? '...' : '' ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <?php elseif ($fechamento_view['status'] === 'fechado' && $is_fechamento_extra): ?>
                            <td>
                                <button type="button" class="btn btn-sm btn-light-info" onclick="verDetalhesPagamento(<?= $fechamento_view['id'] ?>, <?= $item['colaborador_id'] ?>)">
                                    <i class="ki-duotone ki-eye fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Ver Detalhes
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Lista de fechamentos -->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <input type="text" data-kt-fechamento-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar fechamentos" />
                        </div>
                        <select class="form-select form-select-solid w-150px" id="filtro_tipo" onchange="aplicarFiltros()">
                            <option value="">Todos os Tipos</option>
                            <option value="regular" <?= ($filtro_tipo ?? '') === 'regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="extra" <?= ($filtro_tipo ?? '') === 'extra' ? 'selected' : '' ?>>Extra</option>
                        </select>
                        <select class="form-select form-select-solid w-180px" id="filtro_subtipo" onchange="aplicarFiltros()">
                            <option value="">Todos os Subtipos</option>
                            <option value="bonus_especifico" <?= ($filtro_subtipo ?? '') === 'bonus_especifico' ? 'selected' : '' ?>>Bônus Específico</option>
                            <option value="individual" <?= ($filtro_subtipo ?? '') === 'individual' ? 'selected' : '' ?>>Individual</option>
                            <option value="grupal" <?= ($filtro_subtipo ?? '') === 'grupal' ? 'selected' : '' ?>>Grupal</option>
                            <option value="adiantamento" <?= ($filtro_subtipo ?? '') === 'adiantamento' ? 'selected' : '' ?>>Adiantamento</option>
                        </select>
                        <input type="date" class="form-control form-control-solid w-180px" id="filtro_data_pagamento" value="<?= htmlspecialchars($filtro_data_pagamento ?? '') ?>" onchange="aplicarFiltros()" placeholder="Data Pagamento" />
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_fechamento">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Novo Fechamento Regular
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="abrirModalFechamentoExtra('bonus_especifico'); return false;">
                                <i class="ki-duotone ki-gift fs-5 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Bônus Específico
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="abrirModalFechamentoExtra('individual'); return false;">
                                <i class="ki-duotone ki-user fs-5 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Bônus Individual
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="abrirModalFechamentoExtra('grupal'); return false;">
                                <i class="ki-duotone ki-people fs-5 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Bônus Grupal
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="abrirModalFechamentoExtra('adiantamento'); return false;">
                                <i class="ki-duotone ki-wallet fs-5 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adiantamento
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_fechamentos_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-150px">Tipo</th>
                            <th class="min-w-150px">Empresa</th>
                            <th class="min-w-100px">Mês/Ano</th>
                            <th class="min-w-100px">Data Pagamento</th>
                            <th class="min-w-100px">Colaboradores</th>
                            <th class="min-w-120px">Total Pagamento</th>
                            <th class="min-w-120px">Total H.E.</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($fechamentos as $fechamento): 
                            $tipo_fechamento = $fechamento['tipo_fechamento'] ?? 'regular';
                            $subtipo_fechamento = $fechamento['subtipo_fechamento'] ?? '';
                            $data_pagamento = $fechamento['data_pagamento'] ?? null;
                            $referencia_externa = $fechamento['referencia_externa'] ?? '';
                        ?>
                        <tr>
                            <td><?= $fechamento['id'] ?></td>
                            <td>
                                <?php if ($tipo_fechamento === 'extra'): ?>
                                    <span class="badge badge-light-primary">EXTRA</span>
                                    <?php if ($subtipo_fechamento): ?>
                                        <br><small class="text-muted">
                                            <?php
                                            $subtipo_labels = [
                                                'bonus_especifico' => 'Bônus Específico',
                                                'individual' => 'Individual',
                                                'grupal' => 'Grupal',
                                                'adiantamento' => 'Adiantamento'
                                            ];
                                            echo htmlspecialchars($subtipo_labels[$subtipo_fechamento] ?? $subtipo_fechamento);
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary">REGULAR</span>
                                <?php endif; ?>
                                <?php if ($referencia_externa): ?>
                                    <br><small class="text-info"><?= htmlspecialchars($referencia_externa) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($fechamento['empresa_nome']) ?></td>
                            <td><?= date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')) ?></td>
                            <td>
                                <?php if ($data_pagamento): ?>
                                    <strong><?= date('d/m/Y', strtotime($data_pagamento)) ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $fechamento['total_colaboradores'] ?></td>
                            <td><strong>R$ <?= number_format($fechamento['total_pagamento'], 2, ',', '.') ?></strong></td>
                            <td>
                                <?php if ($tipo_fechamento === 'regular'): ?>
                                    R$ <?= number_format($fechamento['total_horas_extras'], 2, ',', '.') ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($fechamento['status'] === 'aberto'): ?>
                                    <span class="badge badge-light-warning">Aberto</span>
                                <?php elseif ($fechamento['status'] === 'fechado'): ?>
                                    <span class="badge badge-light-info">Fechado</span>
                                <?php else: ?>
                                    <span class="badge badge-light-success">Pago</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="fechamento_pagamentos.php?view=<?= $fechamento['id'] ?>" class="btn btn-sm btn-light-primary me-2">
                                    Ver
                                </a>
                                <button type="button" class="btn btn-sm btn-light-danger" onclick="deletarFechamento(<?= $fechamento['id'] ?>, '<?= date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')) ?>')">
                                    <i class="ki-duotone ki-trash fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Card de Informações de Pagamento -->
        <div class="card mt-5">
            <div class="card-header">
                <div class="card-title d-flex justify-content-between align-items-center flex-wrap">
                    <div class="align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Informações de Pagamento</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Resumo financeiro dos pagamentos</span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <select id="filtro_resumo_tipo" class="form-select form-select-sm w-auto">
                            <option value="total">Total</option>
                            <option value="empresa">Por Empresa</option>
                            <option value="setor">Por Setor</option>
                        </select>
                        <select id="filtro_resumo_id" class="form-select form-select-sm w-auto" style="display: none;">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body" id="resumo_pagamentos_body">
                <div class="row g-5 g-xl-8">
                    <!-- Total de Folha -->
                    <div class="col-xl-3">
                        <div class="card bg-light-primary h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <span class="text-gray-600 fw-semibold fs-6 d-block mb-2">Total de Folha</span>
                                    <span class="text-gray-800 fw-bold fs-2x">R$ <?= number_format($total_folha, 2, ',', '.') ?></span>
                                </div>
                                <div class="mt-3">
                                    <span class="text-gray-500 fs-7">Soma de todos os salários</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Apenas Bônus -->
                    <div class="col-xl-3">
                        <div class="card bg-light-success h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <span class="text-gray-600 fw-semibold fs-6 d-block mb-2">Apenas Bônus</span>
                                    <span class="text-gray-800 fw-bold fs-2x">R$ <?= number_format($total_bonus, 2, ',', '.') ?></span>
                                </div>
                                <div class="mt-3">
                                    <span class="text-gray-500 fs-7">Total de bônus pagos</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Extras Somados -->
                    <div class="col-xl-3">
                        <div class="card bg-light-info h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <span class="text-gray-600 fw-semibold fs-6 d-block mb-2">Extras Somados</span>
                                    <span class="text-gray-800 fw-bold fs-2x">R$ <?= number_format($total_extras, 2, ',', '.') ?></span>
                                </div>
                                <div class="mt-3">
                                    <span class="text-gray-500 fs-7">Total de horas extras pagas</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Folha + Bônus -->
                    <div class="col-xl-3">
                        <div class="card bg-light-warning h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <span class="text-gray-600 fw-semibold fs-6 d-block mb-2">Folha + Bônus</span>
                                    <span class="text-gray-800 fw-bold fs-2x">R$ <?= number_format($total_folha_bonus, 2, ',', '.') ?></span>
                                </div>
                                <div class="mt-3">
                                    <span class="text-gray-500 fs-7">Total de folha com bônus</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Geral -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card bg-light-dark h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="text-white fw-semibold fs-6 d-block mb-1">Total Geral</span>
                                        <span class="text-white-50 fs-7">Folha + Bônus + Extras</span>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-white fw-bold fs-2x">R$ <?= number_format($total_folha_bonus + $total_extras, 2, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Card de Informações de Pagamento -->
        
        <?php endif; ?>
        
        <script>
        // Dados para filtros
        const empresasData = <?= json_encode($empresas ?? []) ?>;
        let setoresData = [];
        
        // Busca setores quando necessário
        function buscarSetores(empresaId = null) {
            const url = empresaId 
                ? `../api/get_setores.php?empresa_id=${empresaId}`
                : '../api/get_setores.php';
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setoresData = data.data || [];
                        if (document.getElementById('filtro_resumo_tipo').value === 'setor') {
                            atualizarSelectFiltro();
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar setores:', error);
                });
        }
        
        // Atualiza o select de filtro baseado no tipo selecionado
        function atualizarSelectFiltro() {
            const tipoFiltro = document.getElementById('filtro_resumo_tipo').value;
            const selectId = document.getElementById('filtro_resumo_id');
            
            selectId.innerHTML = '<option value="">Selecione...</option>';
            
            if (tipoFiltro === 'total') {
                selectId.style.display = 'none';
                carregarResumo('total', null);
            } else if (tipoFiltro === 'empresa') {
                selectId.style.display = 'block';
                empresasData.forEach(empresa => {
                    const option = document.createElement('option');
                    option.value = empresa.id;
                    option.textContent = empresa.nome_fantasia;
                    selectId.appendChild(option);
                });
                carregarResumo('empresa', null);
            } else if (tipoFiltro === 'setor') {
                selectId.style.display = 'block';
                if (setoresData.length === 0) {
                    buscarSetores();
                } else {
                    setoresData.forEach(setor => {
                        const option = document.createElement('option');
                        option.value = setor.id;
                        option.textContent = `${setor.nome_setor}${setor.empresa_nome ? ' - ' + setor.empresa_nome : ''}`;
                        selectId.appendChild(option);
                    });
                }
                carregarResumo('setor', null);
            }
        }
        
        // Carrega o resumo de pagamentos
        function carregarResumo(tipo, filtroId) {
            const url = `../api/get_resumo_pagamentos.php?filtro_tipo=${tipo}${filtroId ? '&filtro_id=' + filtroId : ''}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        atualizarCards(data.data, tipo);
                    } else {
                        console.error('Erro ao carregar resumo:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar resumo:', error);
                });
        }
        
        // Atualiza os cards com os dados recebidos
        function atualizarCards(dados, tipo) {
            const container = document.getElementById('resumo_pagamentos_body');
            
            if (tipo === 'total') {
                // Exibe valores totais simples
                container.innerHTML = `
                    <div class="row g-5 g-xl-8">
                        <div class="col-xl-3">
                            <div class="card bg-light-primary h-100">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <span class="text-gray-600 fw-semibold fs-6 d-block mb-2">Total de Folha</span>
                                        <span class="text-gray-800 fw-bold fs-2x">R$ ${formatarMoeda(dados.total_folha)}</span>
                                    </div>
                                    <div class="mt-3">
                                        <span class="text-gray-500 fs-7">Soma de todos os salários</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card bg-light-success h-100">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <span class="text-gray-600 fw-semibold fs-6 d-block mb-2">Apenas Bônus</span>
                                        <span class="text-gray-800 fw-bold fs-2x">R$ ${formatarMoeda(dados.total_bonus)}</span>
                                    </div>
                                    <div class="mt-3">
                                        <span class="text-gray-500 fs-7">Total de bônus cadastrados</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card bg-light-info h-100">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <span class="text-gray-600 fw-semibold fs-6 d-block mb-2">Extras Somados</span>
                                        <span class="text-gray-800 fw-bold fs-2x">R$ ${formatarMoeda(dados.total_extras)}</span>
                                    </div>
                                    <div class="mt-3">
                                        <span class="text-gray-500 fs-7">Total de horas extras não pagas</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card bg-light-warning h-100">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <span class="text-gray-600 fw-semibold fs-6 d-block mb-2">Folha + Bônus</span>
                                        <span class="text-gray-800 fw-bold fs-2x">R$ ${formatarMoeda(dados.total_folha_bonus)}</span>
                                    </div>
                                    <div class="mt-3">
                                        <span class="text-gray-500 fs-7">Total de folha com bônus</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-5">
                        <div class="col-12">
                            <div class="card bg-light-dark h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-white fw-semibold fs-6 d-block mb-1">Total Geral</span>
                                            <span class="text-white-50 fs-7">Folha + Bônus + Extras</span>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-white fw-bold fs-2x">R$ ${formatarMoeda(dados.total_geral)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Exibe valores agrupados por empresa ou setor
                const items = dados.total_folha_bonus || [];
                const titulo = tipo === 'empresa' ? 'Empresa' : 'Setor';
                
                let html = '<div class="row g-5 g-xl-8">';
                
                // Cards individuais para cada item
                items.forEach((item, index) => {
                    const folha = dados.total_folha.find(d => d.id === item.id)?.valor || 0;
                    const bonus = dados.total_bonus.find(d => d.id === item.id)?.valor || 0;
                    const extras = dados.total_extras.find(d => d.id === item.id)?.valor || 0;
                    const geral = dados.total_geral.find(d => d.id === item.id)?.valor || 0;
                    
                    html += `
                        <div class="col-xl-6 mb-5">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">${item.nome}${item.empresa ? ' - ' + item.empresa : ''}</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="card bg-light-primary">
                                                <div class="card-body p-3">
                                                    <span class="text-gray-600 fw-semibold fs-7 d-block mb-1">Total de Folha</span>
                                                    <span class="text-gray-800 fw-bold fs-3">R$ ${formatarMoeda(folha)}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light-success">
                                                <div class="card-body p-3">
                                                    <span class="text-gray-600 fw-semibold fs-7 d-block mb-1">Apenas Bônus</span>
                                                    <span class="text-gray-800 fw-bold fs-3">R$ ${formatarMoeda(bonus)}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light-info">
                                                <div class="card-body p-3">
                                                    <span class="text-gray-600 fw-semibold fs-7 d-block mb-1">Extras Somados</span>
                                                    <span class="text-gray-800 fw-bold fs-3">R$ ${formatarMoeda(extras)}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light-warning">
                                                <div class="card-body p-3">
                                                    <span class="text-gray-600 fw-semibold fs-7 d-block mb-1">Folha + Bônus</span>
                                                    <span class="text-gray-800 fw-bold fs-3">R$ ${formatarMoeda(item.valor)}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="card bg-light-dark">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="text-white fw-semibold fs-6">Total Geral</span>
                                                        <span class="text-white fw-bold fs-2x">R$ ${formatarMoeda(geral)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                container.innerHTML = html;
            }
        }
        
        // Formata valor monetário
        function formatarMoeda(valor) {
            return parseFloat(valor || 0).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Event listeners
        document.getElementById('filtro_resumo_tipo').addEventListener('change', function() {
            atualizarSelectFiltro();
        });
        
        document.getElementById('filtro_resumo_id').addEventListener('change', function() {
            const tipo = document.getElementById('filtro_resumo_tipo').value;
            const filtroId = this.value || null;
            carregarResumo(tipo, filtroId);
        });
        
        // Inicializa ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            atualizarSelectFiltro();
        });
        </script>
        
    </div>
</div>
<!--end::Post-->

<?php if (isset($fechamento_view) && $fechamento_view): ?>
<!-- Modal Detalhes Bônus -->
<div class="modal fade" id="kt_modal_detalhes_bonus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="kt_modal_detalhes_bonus_titulo">Detalhes dos Bônus</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="kt_modal_detalhes_bonus_conteudo">
                    <!-- Conteúdo será preenchido via JavaScript -->
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes Completos do Pagamento -->
<div class="modal fade" id="kt_modal_detalhes_pagamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="kt_modal_detalhes_pagamento_titulo">Detalhes do Pagamento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="kt_modal_detalhes_pagamento_conteudo">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <div class="text-muted mt-3">Carregando detalhes...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!--begin::Modal - Criar Fechamento-->
<div class="modal fade" id="kt_modal_fechamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-750px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Fechamento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_fechamento_form" method="POST" class="form">
                    <input type="hidden" name="action" value="criar_fechamento">
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($empresas as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nome_fantasia']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Mês/Ano de Referência</label>
                            <input type="month" name="mes_referencia" class="form-control form-control-solid" value="<?= date('Y-m') ?>" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Colaboradores</label>
                            <div id="colaboradores_container" class="border rounded p-4" style="max-height: 300px; overflow-y: auto;">
                                <p class="text-muted">Selecione uma empresa primeiro</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="excluir_bonus_ja_pagos" id="excluir_bonus_ja_pagos" value="1" />
                                <label class="form-check-label fw-semibold fs-6" for="excluir_bonus_ja_pagos">
                                    Excluir bônus já pagos em fechamentos extras deste mês
                                </label>
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="ki-duotone ki-information-5 fs-6 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Se marcado, bônus que já foram pagos em fechamentos extras do mesmo mês serão automaticamente excluídos deste fechamento regular, evitando pagamento duplicado.
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Criar Fechamento</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Editar Item-->
<div class="modal fade" id="kt_modal_item" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Item</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_item_form" method="POST" class="form">
                    <input type="hidden" name="action" value="atualizar_item">
                    <input type="hidden" name="item_id" id="item_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador</label>
                            <input type="text" id="item_colaborador" class="form-control form-control-solid" readonly />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Salário Base</label>
                            <input type="text" id="item_salario_base" class="form-control form-control-solid" readonly />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Horas Extras</label>
                            <input type="text" name="horas_extras" id="item_horas_extras" class="form-control form-control-solid" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Valor Horas Extras</label>
                            <input type="text" name="valor_horas_extras" id="item_valor_horas_extras" class="form-control form-control-solid" />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Descontos</label>
                            <input type="text" name="descontos" id="item_descontos" class="form-control form-control-solid text-danger fw-bold" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Adicionais</label>
                            <input type="text" name="adicionais" id="item_adicionais" class="form-control form-control-solid" />
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="fw-semibold fs-6 mb-0">Bônus</label>
                            <button type="button" class="btn btn-sm btn-primary" onclick="adicionarBonusItem()">
                                <i class="ki-duotone ki-plus fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Bônus
                            </button>
                        </div>
                        <div id="bonus_container" class="border rounded p-4">
                            <p class="text-muted mb-0">Nenhum bônus adicionado</p>
                        </div>
                        <input type="hidden" name="bonus_editados" id="bonus_editados_json" value="[]">
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Editar Item Bonus Específico-->
<div class="modal fade" id="kt_modal_item_bonus_especifico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Bônus Específico</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_item_bonus_especifico_form" method="POST" class="form">
                    <input type="hidden" name="action" value="atualizar_item_bonus_especifico">
                    <input type="hidden" name="item_id" id="item_bonus_especifico_id">
                    <input type="hidden" name="fechamento_id" id="item_bonus_especifico_fechamento_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador</label>
                            <input type="text" id="item_bonus_especifico_colaborador" class="form-control form-control-solid" readonly />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Tipo de Bônus</label>
                            <select name="tipo_bonus_id" id="item_bonus_especifico_tipo_bonus" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($tipos_bonus as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Valor do Bônus</label>
                            <input type="text" id="item_bonus_especifico_valor" class="form-control form-control-solid" readonly />
                            <div class="form-text text-muted">O valor é calculado automaticamente baseado no tipo de bônus selecionado.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Motivo</label>
                            <textarea name="motivo" id="item_bonus_especifico_motivo" class="form-control form-control-solid" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Editar Item Adiantamento-->
<div class="modal fade" id="kt_modal_item_adiantamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Adiantamento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_item_adiantamento_form" method="POST" class="form">
                    <input type="hidden" name="action" value="atualizar_item_adiantamento">
                    <input type="hidden" name="item_id" id="item_adiantamento_id">
                    <input type="hidden" name="fechamento_id" id="item_adiantamento_fechamento_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador</label>
                            <input type="text" id="item_adiantamento_colaborador" class="form-control form-control-solid" readonly />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Valor do Adiantamento</label>
                            <input type="text" name="valor_adiantamento" id="item_adiantamento_valor" class="form-control form-control-solid" placeholder="0,00" required />
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Descontar em (Mês/Ano)</label>
                            <input type="month" name="mes_desconto" id="item_adiantamento_mes_desconto" class="form-control form-control-solid" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                            <textarea name="motivo" id="item_adiantamento_motivo" class="form-control form-control-solid" rows="3" required></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Editar Item Bonus Grupal/Individual-->
<div class="modal fade" id="kt_modal_item_bonus_valor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="item_bonus_valor_titulo">Editar Valor</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_item_bonus_valor_form" method="POST" class="form">
                    <input type="hidden" name="action" id="item_bonus_valor_action">
                    <input type="hidden" name="item_id" id="item_bonus_valor_id">
                    <input type="hidden" name="fechamento_id" id="item_bonus_valor_fechamento_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador</label>
                            <input type="text" id="item_bonus_valor_colaborador" class="form-control form-control-solid" readonly />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Tipo de Bônus (opcional)</label>
                            <select name="tipo_bonus_id" id="item_bonus_valor_tipo_bonus" class="form-select form-select-solid">
                                <option value="">Valor Livre</option>
                                <?php foreach ($tipos_bonus as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Valor</label>
                            <input type="text" name="valor_manual" id="item_bonus_valor_valor" class="form-control form-control-solid" placeholder="0,00" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                            <textarea name="motivo" id="item_bonus_valor_motivo" class="form-control form-control-solid" rows="3" required></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Editar Item Bonus Específico-->
<div class="modal fade" id="kt_modal_item_bonus_especifico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Bônus Específico</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_item_bonus_especifico_form" method="POST" class="form">
                    <input type="hidden" name="action" value="atualizar_item_bonus_especifico">
                    <input type="hidden" name="item_id" id="item_bonus_especifico_id">
                    <input type="hidden" name="fechamento_id" id="item_bonus_especifico_fechamento_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador</label>
                            <input type="text" id="item_bonus_especifico_colaborador" class="form-control form-control-solid" readonly />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Tipo de Bônus</label>
                            <select name="tipo_bonus_id" id="item_bonus_especifico_tipo_bonus" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($tipos_bonus as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Valor do Bônus</label>
                            <input type="text" id="item_bonus_especifico_valor" class="form-control form-control-solid" readonly />
                            <div class="form-text text-muted">O valor é calculado automaticamente baseado no tipo de bônus selecionado.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Motivo</label>
                            <textarea name="motivo" id="item_bonus_especifico_motivo" class="form-control form-control-solid" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Editar Item Adiantamento-->
<div class="modal fade" id="kt_modal_item_adiantamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Adiantamento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_item_adiantamento_form" method="POST" class="form">
                    <input type="hidden" name="action" value="atualizar_item_adiantamento">
                    <input type="hidden" name="item_id" id="item_adiantamento_id">
                    <input type="hidden" name="fechamento_id" id="item_adiantamento_fechamento_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador</label>
                            <input type="text" id="item_adiantamento_colaborador" class="form-control form-control-solid" readonly />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Valor do Adiantamento</label>
                            <input type="text" name="valor_adiantamento" id="item_adiantamento_valor" class="form-control form-control-solid" placeholder="0,00" required />
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Descontar em (Mês/Ano)</label>
                            <input type="month" name="mes_desconto" id="item_adiantamento_mes_desconto" class="form-control form-control-solid" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                            <textarea name="motivo" id="item_adiantamento_motivo" class="form-control form-control-solid" rows="3" required></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Editar Item Bonus Grupal/Individual-->
<div class="modal fade" id="kt_modal_item_bonus_valor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="item_bonus_valor_titulo">Editar Valor</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_item_bonus_valor_form" method="POST" class="form">
                    <input type="hidden" name="action" id="item_bonus_valor_action">
                    <input type="hidden" name="item_id" id="item_bonus_valor_id">
                    <input type="hidden" name="fechamento_id" id="item_bonus_valor_fechamento_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador</label>
                            <input type="text" id="item_bonus_valor_colaborador" class="form-control form-control-solid" readonly />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Tipo de Bônus (opcional)</label>
                            <select name="tipo_bonus_id" id="item_bonus_valor_tipo_bonus" class="form-select form-select-solid">
                                <option value="">Valor Livre</option>
                                <?php foreach ($tipos_bonus as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Valor</label>
                            <input type="text" name="valor_manual" id="item_bonus_valor_valor" class="form-control form-control-solid" placeholder="0,00" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                            <textarea name="motivo" id="item_bonus_valor_motivo" class="form-control form-control-solid" rows="3" required></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Criar Fechamento Extra-->
<div class="modal fade" id="kt_modal_fechamento_extra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-750px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="extra_titulo">Novo Fechamento Extra</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_fechamento_extra_form" method="POST" class="form">
                    <input type="hidden" name="action" value="criar_fechamento_extra">
                    <input type="hidden" name="subtipo_fechamento" id="extra_subtipo" value="">
                    <input type="hidden" name="template_id" id="extra_template_id" value="">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Usar Template (Opcional)</label>
                            <select id="extra_template_select" class="form-select form-select-solid">
                                <option value="">Nenhum - Criar manualmente</option>
                                <?php
                                // Busca templates ativos
                                $stmt_templates = $pdo->prepare("
                                    SELECT t.*, tb.nome as tipo_bonus_nome, e.nome_fantasia as empresa_nome
                                    FROM fechamentos_pagamento_extras_config t
                                    LEFT JOIN tipos_bonus tb ON t.tipo_bonus_id = tb.id
                                    LEFT JOIN empresas e ON t.empresa_id = e.id
                                    WHERE t.ativo = 1
                                    ORDER BY t.nome
                                ");
                                $stmt_templates->execute();
                                $templates_disponiveis = $stmt_templates->fetchAll();
                                foreach ($templates_disponiveis as $tmpl): 
                                ?>
                                <option value="<?= $tmpl['id'] ?>" 
                                        data-subtipo="<?= htmlspecialchars($tmpl['subtipo']) ?>"
                                        data-empresa-id="<?= $tmpl['empresa_id'] ?>"
                                        data-tipo-bonus-id="<?= $tmpl['tipo_bonus_id'] ?>"
                                        data-valor-padrao="<?= $tmpl['valor_padrao'] ?>"
                                        data-observacoes="<?= htmlspecialchars($tmpl['observacoes'] ?? '') ?>">
                                    <?= htmlspecialchars($tmpl['nome']) ?>
                                    <?php if ($tmpl['empresa_nome']): ?>
                                        (<?= htmlspecialchars($tmpl['empresa_nome']) ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted">Selecione um template para preencher automaticamente os campos</div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Empresa</label>
                            <select name="empresa_id" id="extra_empresa_id" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <option value="todas">Todas Empresas</option>
                                <?php foreach ($empresas as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nome_fantasia']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Mês/Ano de Referência</label>
                            <input type="month" name="mes_referencia" class="form-control form-control-solid" value="<?= date('Y-m') ?>" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Data de Pagamento</label>
                            <input type="date" name="data_pagamento" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" required />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Referência Externa</label>
                            <input type="text" name="referencia_externa" class="form-control form-control-solid" placeholder="Ex: Meta Q1 2024, Adiantamento Dezembro" />
                        </div>
                    </div>
                    
                    <!-- Campos Bônus Específico -->
                    <div class="campo-bonus-especifico" style="display: none;">
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <label class="required fw-semibold fs-6 mb-2">Tipo de Bônus</label>
                                <select name="tipo_bonus_id" id="extra_tipo_bonus_id" class="form-select form-select-solid" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($tipos_bonus as $tipo): ?>
                                    <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Campo hidden para garantir que o valor seja enviado -->
                                <input type="hidden" name="tipo_bonus_id_hidden" id="extra_tipo_bonus_id_hidden" value="">
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <label class="required fw-semibold fs-6 mb-2">Colaboradores</label>
                                <div id="extra_colaboradores_container" class="border rounded p-4" style="max-height: 300px; overflow-y: auto;">
                                    <p class="text-muted">Selecione uma empresa primeiro</p>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="aplicar_descontos" value="1" id="aplicar_descontos">
                                    <label class="form-check-label" for="aplicar_descontos">
                                        Aplicar descontos por ocorrências configuradas
                                    </label>
                                </div>
                                <div class="form-text text-muted mt-2">
                                    <i class="ki-duotone ki-information-5 fs-6 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Se marcado, o sistema aplicará automaticamente descontos no valor do bônus baseado nas ocorrências (faltas, atrasos, etc.) configuradas para este tipo de bônus. Os descontos podem ser proporcionais, fixos, percentuais ou totais, conforme configurado no cadastro do tipo de bônus.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos Individual -->
                    <div class="campo-individual" style="display: none;">
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                                <div id="extra_colaboradores_container" class="border rounded p-4">
                                    <p class="text-muted">Selecione uma empresa primeiro</p>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Tipo de Bônus (opcional)</label>
                                <select name="tipo_bonus_id" id="extra_tipo_bonus_id_individual" class="form-select form-select-solid">
                                    <option value="">Valor Livre</option>
                                    <?php foreach ($tipos_bonus as $tipo): ?>
                                    <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Campo hidden para garantir que o valor seja enviado -->
                                <input type="hidden" name="tipo_bonus_id_hidden_individual" id="extra_tipo_bonus_id_hidden_individual" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="required fw-semibold fs-6 mb-2">Valor</label>
                                <input type="text" name="valor_manual" id="extra_valor_manual_individual" class="form-control form-control-solid" placeholder="0,00" required />
                                <!-- Campo hidden para garantir que o valor seja enviado -->
                                <input type="hidden" name="valor_manual_hidden" id="extra_valor_manual_hidden_individual" value="">
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                                <textarea name="motivo" id="extra_motivo_individual" class="form-control form-control-solid" rows="3" required></textarea>
                                <!-- Campo hidden para garantir que o valor seja enviado -->
                                <input type="hidden" name="motivo_hidden" id="extra_motivo_hidden_individual" value="">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos Grupal -->
                    <div class="campo-grupal" style="display: none;">
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <label class="required fw-semibold fs-6 mb-2">Colaboradores</label>
                                <div id="extra_colaboradores_container" class="border rounded p-4" style="max-height: 300px; overflow-y: auto;">
                                    <p class="text-muted">Selecione uma empresa primeiro</p>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Tipo de Bônus (opcional)</label>
                                <select name="tipo_bonus_id" id="extra_tipo_bonus_id_grupal" class="form-select form-select-solid">
                                    <option value="">Valor Livre</option>
                                    <?php foreach ($tipos_bonus as $tipo): ?>
                                    <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Campo hidden para garantir que o valor seja enviado -->
                                <input type="hidden" name="tipo_bonus_id_hidden_grupal" id="extra_tipo_bonus_id_hidden_grupal" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="required fw-semibold fs-6 mb-2">Valor (mesmo para todos)</label>
                                <input type="text" name="valor_manual" class="form-control form-control-solid" placeholder="0,00" required />
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                                <textarea name="motivo" id="extra_motivo_grupal" class="form-control form-control-solid" rows="3" required></textarea>
                                <!-- Campo hidden para garantir que o valor seja enviado -->
                                <input type="hidden" name="motivo_hidden_grupal" id="extra_motivo_hidden_grupal" value="">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos Adiantamento -->
                    <div class="campo-adiantamento" style="display: none;">
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                                <div id="extra_colaboradores_container" class="border rounded p-4">
                                    <p class="text-muted">Selecione uma empresa primeiro</p>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-6">
                                <label class="required fw-semibold fs-6 mb-2">Valor do Adiantamento</label>
                                <input type="text" name="valor_adiantamento" class="form-control form-control-solid" placeholder="0,00" required />
                            </div>
                            <div class="col-md-6">
                                <label class="required fw-semibold fs-6 mb-2">Descontar em (Mês/Ano)</label>
                                <input type="month" name="mes_desconto" class="form-control form-control-solid" required />
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-12">
                                <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                                <textarea name="motivo" id="extra_motivo_adiantamento" class="form-control form-control-solid" rows="3" required></textarea>
                                <!-- Campo hidden para garantir que o valor seja enviado -->
                                <input type="hidden" name="motivo_hidden_adiantamento" id="extra_motivo_hidden_adiantamento" value="">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Descrição/Observações</label>
                            <textarea name="descricao" class="form-control form-control-solid" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Criar Fechamento Extra</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
// Carrega colaboradores ao selecionar empresa
document.getElementById('empresa_id')?.addEventListener('change', function() {
    const empresaId = this.value;
    const container = document.getElementById('colaboradores_container');
    
    if (!empresaId) {
        container.innerHTML = '<p class="text-muted">Selecione uma empresa primeiro</p>';
        return;
    }
    
    container.innerHTML = '<p class="text-muted">Carregando...</p>';
    
    fetch(`../api/get_colaboradores.php?empresa_id=${empresaId}&status=ativo&com_salario=1`)
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(colab => {
                html += `
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="colaboradores[]" value="${colab.id}" id="colab_${colab.id}">
                        <label class="form-check-label" for="colab_${colab.id}">
                            ${colab.nome_completo} - Salário: R$ ${parseFloat(colab.salario || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                        </label>
                    </div>
                `;
            });
            container.innerHTML = html || '<p class="text-muted">Nenhum colaborador encontrado</p>';
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger">Erro ao carregar colaboradores</p>';
        });
});

// Variável global para armazenar bônus do item
let bonusItemAtual = [];

// Editar item
function editarItem(item) {
    document.getElementById('item_id').value = item.id;
    document.getElementById('item_colaborador').value = item.colaborador_nome;
    document.getElementById('item_salario_base').value = 'R$ ' + parseFloat(item.salario_base || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('item_horas_extras').value = parseFloat(item.horas_extras || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('item_valor_horas_extras').value = parseFloat(item.valor_horas_extras || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('item_descontos').value = parseFloat(item.descontos || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('item_adicionais').value = parseFloat(item.adicionais || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Busca bônus do colaborador no fechamento
    const colaboradorId = item.colaborador_id;
    const fechamentoId = <?= isset($fechamento_view) && $fechamento_view ? $fechamento_view['id'] : 0 ?>;
    
    if (fechamentoId && colaboradorId) {
        // Busca bônus salvos no fechamento
        fetch(`../api/get_bonus_fechamento.php?fechamento_id=${fechamentoId}&colaborador_id=${colaboradorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    bonusItemAtual = data.data;
                    renderizarBonusContainer();
                } else {
                    bonusItemAtual = [];
                    renderizarBonusContainer();
                }
            })
            .catch(() => {
                bonusItemAtual = [];
                renderizarBonusContainer();
            });
    } else {
        bonusItemAtual = [];
        renderizarBonusContainer();
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_item'));
    modal.show();
    
    // Aplica máscaras após o modal ser exibido
    setTimeout(() => {
        aplicarMascarasItem();
    }, 300);
}

// Aplicar máscaras nos campos do modal de editar item
function aplicarMascarasItem() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
        jQuery('#item_valor_horas_extras').mask('#.##0,00', {reverse: true});
        jQuery('#item_descontos').mask('#.##0,00', {reverse: true});
        jQuery('#item_adicionais').mask('#.##0,00', {reverse: true});
        jQuery('#item_horas_extras').mask('#0,00', {reverse: true});
    }
}

// Editar item Bonus Específico
function editarItemBonusEspecifico(item, subtipo) {
    const fechamentoId = <?= isset($fechamento_view) && $fechamento_view ? $fechamento_view['id'] : 0 ?>;
    
    document.getElementById('item_bonus_especifico_id').value = item.id;
    document.getElementById('item_bonus_especifico_fechamento_id').value = fechamentoId;
    document.getElementById('item_bonus_especifico_colaborador').value = item.colaborador_nome;
    
    // Busca bônus do colaborador no fechamento
    if (fechamentoId && item.colaborador_id) {
        fetch(`../api/get_bonus_fechamento.php?fechamento_id=${fechamentoId}&colaborador_id=${item.colaborador_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    const bonus = data.data[0];
                    document.getElementById('item_bonus_especifico_tipo_bonus').value = bonus.tipo_bonus_id || '';
                    document.getElementById('item_bonus_especifico_valor').value = 'R$ ' + parseFloat(bonus.valor || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            })
            .catch(() => {
                // Erro ao buscar bônus
            });
    }
    
    // Preenche motivo se existir
    if (item.motivo) {
        document.getElementById('item_bonus_especifico_motivo').value = item.motivo;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_item_bonus_especifico'));
    modal.show();
}

// Editar item Bonus Grupal
function editarItemBonusGrupal(item, subtipo) {
    const fechamentoId = <?= isset($fechamento_view) && $fechamento_view ? $fechamento_view['id'] : 0 ?>;
    
    document.getElementById('item_bonus_valor_action').value = 'atualizar_item_bonus_grupal';
    document.getElementById('item_bonus_valor_id').value = item.id;
    document.getElementById('item_bonus_valor_fechamento_id').value = fechamentoId;
    document.getElementById('item_bonus_valor_colaborador').value = item.colaborador_nome;
    document.getElementById('item_bonus_valor_titulo').textContent = 'Editar Bônus Grupal';
    
    // Preenche valor manual
    if (item.valor_manual) {
        const valorFormatado = parseFloat(item.valor_manual).toFixed(2).replace('.', ',');
        document.getElementById('item_bonus_valor_valor').value = valorFormatado;
    }
    
    // Busca tipo de bônus se existir
    if (fechamentoId && item.colaborador_id) {
        fetch(`../api/get_bonus_fechamento.php?fechamento_id=${fechamentoId}&colaborador_id=${item.colaborador_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    const bonus = data.data[0];
                    if (bonus.tipo_bonus_id) {
                        document.getElementById('item_bonus_valor_tipo_bonus').value = bonus.tipo_bonus_id;
                    }
                }
            })
            .catch(() => {
                // Erro ao buscar bônus
            });
    }
    
    // Preenche motivo se existir
    if (item.motivo) {
        document.getElementById('item_bonus_valor_motivo').value = item.motivo;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_item_bonus_valor'));
    modal.show();
    
    // Aplica máscara
    setTimeout(() => {
        if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
            jQuery('#item_bonus_valor_valor').mask('#.##0,00', {reverse: true});
        }
    }, 300);
}

// Editar item Bonus Individual
function editarItemBonusIndividual(item, subtipo) {
    const fechamentoId = <?= isset($fechamento_view) && $fechamento_view ? $fechamento_view['id'] : 0 ?>;
    
    document.getElementById('item_bonus_valor_action').value = 'atualizar_item_bonus_individual';
    document.getElementById('item_bonus_valor_id').value = item.id;
    document.getElementById('item_bonus_valor_fechamento_id').value = fechamentoId;
    document.getElementById('item_bonus_valor_colaborador').value = item.colaborador_nome;
    document.getElementById('item_bonus_valor_titulo').textContent = 'Editar Bônus Individual';
    
    // Preenche valor manual
    if (item.valor_manual) {
        const valorFormatado = parseFloat(item.valor_manual).toFixed(2).replace('.', ',');
        document.getElementById('item_bonus_valor_valor').value = valorFormatado;
    }
    
    // Busca tipo de bônus se existir
    if (fechamentoId && item.colaborador_id) {
        fetch(`../api/get_bonus_fechamento.php?fechamento_id=${fechamentoId}&colaborador_id=${item.colaborador_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    const bonus = data.data[0];
                    if (bonus.tipo_bonus_id) {
                        document.getElementById('item_bonus_valor_tipo_bonus').value = bonus.tipo_bonus_id;
                    }
                }
            })
            .catch(() => {
                // Erro ao buscar bônus
            });
    }
    
    // Preenche motivo se existir
    if (item.motivo) {
        document.getElementById('item_bonus_valor_motivo').value = item.motivo;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_item_bonus_valor'));
    modal.show();
    
    // Aplica máscara
    setTimeout(() => {
        if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
            jQuery('#item_bonus_valor_valor').mask('#.##0,00', {reverse: true});
        }
    }, 300);
}

// Editar item Adiantamento
function editarItemAdiantamento(item, subtipo) {
    const fechamentoId = <?= isset($fechamento_view) && $fechamento_view ? $fechamento_view['id'] : 0 ?>;
    
    document.getElementById('item_adiantamento_id').value = item.id;
    document.getElementById('item_adiantamento_fechamento_id').value = fechamentoId;
    document.getElementById('item_adiantamento_colaborador').value = item.colaborador_nome;
    
    // Preenche valor do adiantamento
    if (item.valor_manual) {
        const valorFormatado = parseFloat(item.valor_manual).toFixed(2).replace('.', ',');
        document.getElementById('item_adiantamento_valor').value = valorFormatado;
    }
    
    // Busca dados do adiantamento (mes_desconto) do banco
    if (fechamentoId && item.colaborador_id) {
        // Busca mes_desconto da tabela fechamentos_pagamento_adiantamentos
        fetch(`../api/get_detalhes_pagamento.php?fechamento_id=${fechamentoId}&colaborador_id=${item.colaborador_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.adiantamento && data.data.adiantamento.mes_desconto) {
                    document.getElementById('item_adiantamento_mes_desconto').value = data.data.adiantamento.mes_desconto;
                }
            })
            .catch((error) => {
                console.error('Erro ao buscar mes_desconto:', error);
                // Erro ao buscar - deixa vazio para o usuário preencher
            });
    }
    
    // Preenche motivo se existir
    if (item.motivo) {
        document.getElementById('item_adiantamento_motivo').value = item.motivo;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_item_adiantamento'));
    modal.show();
    
    // Aplica máscara
    setTimeout(() => {
        if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
            jQuery('#item_adiantamento_valor').mask('#.##0,00', {reverse: true});
        }
    }, 300);
}

// Renderizar container de bônus
function renderizarBonusContainer() {
    const container = document.getElementById('bonus_container');
    if (!container) return;
    
    if (bonusItemAtual.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">Nenhum bônus adicionado</p>';
        document.getElementById('bonus_editados_json').value = '[]';
        return;
    }
    
    let html = '';
    bonusItemAtual.forEach((bonus, index) => {
        const valorOriginal = parseFloat(bonus.valor_original || bonus.valor || 0);
        const descontoOcorrencias = parseFloat(bonus.desconto_ocorrencias || 0);
        const valorFinal = parseFloat(bonus.valor || 0);
        
        html += `
            <div class="d-flex align-items-center gap-3 mb-3 p-3 border rounded" data-bonus-index="${index}">
                <div class="flex-grow-1">
                    <select class="form-select form-select-sm mb-2 bonus_tipo" data-index="${index}" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($tipos_bonus as $tipo): ?>
                        <option value="<?= $tipo['id'] ?>" ${bonus.tipo_bonus_id == <?= $tipo['id'] ?> ? 'selected' : ''}>
                            <?= htmlspecialchars($tipo['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control bonus_valor" data-index="${index}" 
                               value="${valorFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" 
                               placeholder="0,00" required />
                    </div>
                    ${descontoOcorrencias > 0 ? `
                    <div class="alert alert-warning py-2 px-3 mb-0">
                        <small class="d-block">
                            <strong>Valor Original:</strong> R$ ${valorOriginal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </small>
                        <small class="d-block text-danger">
                            <strong>Desconto por Ocorrências:</strong> -R$ ${descontoOcorrencias.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </small>
                    </div>
                    ` : ''}
                </div>
                <button type="button" class="btn btn-sm btn-light-danger" onclick="removerBonusItem(${index})">
                    <i class="ki-duotone ki-trash fs-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
    atualizarBonusJSON();
    aplicarMascarasBonus();
}

// Adicionar novo bônus
function adicionarBonusItem() {
    bonusItemAtual.push({
        tipo_bonus_id: '',
        valor: 0,
        observacoes: ''
    });
    renderizarBonusContainer();
}

// Remover bônus
function removerBonusItem(index) {
    bonusItemAtual.splice(index, 1);
    renderizarBonusContainer();
}

// Atualizar JSON de bônus
function atualizarBonusJSON() {
    const bonusData = bonusItemAtual.map((bonus, index) => {
        const tipoSelect = document.querySelector(`.bonus_tipo[data-index="${index}"]`);
        const valorInput = document.querySelector(`.bonus_valor[data-index="${index}"]`);
        
        return {
            tipo_bonus_id: tipoSelect ? tipoSelect.value : '',
            valor: valorInput ? valorInput.value.replace(/[^0-9,]/g, '').replace(',', '.') : '0',
            observacoes: ''
        };
    }).filter(b => b.tipo_bonus_id && b.valor);
    
    document.getElementById('bonus_editados_json').value = JSON.stringify(bonusData);
}

// Aplicar máscaras nos campos de bônus
function aplicarMascarasBonus() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
        document.querySelectorAll('.bonus_valor').forEach(input => {
            jQuery(input).mask('#.##0,00', {reverse: true});
            jQuery(input).on('input', atualizarBonusJSON);
        });
        document.querySelectorAll('.bonus_tipo').forEach(select => {
            select.addEventListener('change', atualizarBonusJSON);
        });
    }
}

// Atualizar JSON ao submeter formulário
document.getElementById('kt_modal_item_form')?.addEventListener('submit', function(e) {
    atualizarBonusJSON();
    
    // Adiciona os bônus como campos hidden
    const bonusData = JSON.parse(document.getElementById('bonus_editados_json').value || '[]');
    bonusData.forEach((bonus, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `bonus_editados[${index}][tipo_bonus_id]`;
        input.value = bonus.tipo_bonus_id;
        this.appendChild(input);
        
        const inputValor = document.createElement('input');
        inputValor.type = 'hidden';
        inputValor.name = `bonus_editados[${index}][valor]`;
        inputValor.value = bonus.valor;
        this.appendChild(inputValor);
        
        const inputObs = document.createElement('input');
        inputObs.type = 'hidden';
        inputObs.name = `bonus_editados[${index}][observacoes]`;
        inputObs.value = bonus.observacoes || '';
        this.appendChild(inputObs);
    });
});

// Aplicar filtros
function aplicarFiltros() {
    const tipo = document.getElementById('filtro_tipo')?.value || '';
    const subtipo = document.getElementById('filtro_subtipo')?.value || '';
    const dataPagamento = document.getElementById('filtro_data_pagamento')?.value || '';
    
    const params = new URLSearchParams();
    if (tipo) params.append('filtro_tipo', tipo);
    if (subtipo) params.append('filtro_subtipo', subtipo);
    if (dataPagamento) params.append('filtro_data_pagamento', dataPagamento);
    
    window.location.href = 'fechamento_pagamentos.php' + (params.toString() ? '?' + params.toString() : '');
}

// Função para gerenciar atributos required baseado no subtipo
function gerenciarRequiredCampos(subtipo) {
    // Remove required de TODOS os campos primeiro (incluindo os que estão em divs ocultas)
    document.querySelectorAll('input[name="valor_manual"], input[name="valor_adiantamento"], input[name="mes_desconto"], textarea[name="motivo"]').forEach(input => {
        input.removeAttribute('required');
    });
    
    // Remove required de campos dentro de divs ocultas
    document.querySelectorAll('.campo-bonus-especifico, .campo-individual, .campo-grupal, .campo-adiantamento').forEach(div => {
        if (div.style.display === 'none' || !div.offsetParent) {
            div.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => {
                input.removeAttribute('required');
            });
        }
    });
    
    // Adiciona required apenas nos campos visíveis conforme o subtipo
    if (subtipo === 'individual') {
        // Individual: valor_manual e motivo são obrigatórios
        document.querySelectorAll('.campo-individual input[name="valor_manual"]').forEach(input => {
            if (input.closest('.campo-individual').style.display !== 'none') {
                input.setAttribute('required', 'required');
            }
        });
        document.querySelectorAll('.campo-individual textarea[name="motivo"]').forEach(input => {
            if (input.closest('.campo-individual').style.display !== 'none') {
                input.setAttribute('required', 'required');
            }
        });
    } else if (subtipo === 'grupal') {
        // Grupal: valor_manual e motivo são obrigatórios
        document.querySelectorAll('.campo-grupal input[name="valor_manual"]').forEach(input => {
            if (input.closest('.campo-grupal').style.display !== 'none') {
                input.setAttribute('required', 'required');
            }
        });
        document.querySelectorAll('.campo-grupal textarea[name="motivo"]').forEach(input => {
            if (input.closest('.campo-grupal').style.display !== 'none') {
                input.setAttribute('required', 'required');
            }
        });
    } else if (subtipo === 'adiantamento') {
        // Adiantamento: valor_adiantamento, mes_desconto e motivo são obrigatórios
        document.querySelectorAll('.campo-adiantamento input[name="valor_adiantamento"]').forEach(input => {
            if (input.closest('.campo-adiantamento').style.display !== 'none') {
                input.setAttribute('required', 'required');
            }
        });
        document.querySelectorAll('.campo-adiantamento input[name="mes_desconto"]').forEach(input => {
            if (input.closest('.campo-adiantamento').style.display !== 'none') {
                input.setAttribute('required', 'required');
            }
        });
        document.querySelectorAll('.campo-adiantamento textarea[name="motivo"]').forEach(input => {
            if (input.closest('.campo-adiantamento').style.display !== 'none') {
                input.setAttribute('required', 'required');
            }
        });
    }
    // bonus_especifico não precisa de required em valor_manual, valor_adiantamento ou mes_desconto
}

// Abrir modal de fechamento extra
function abrirModalFechamentoExtra(subtipo) {
    // Esconde todos os campos específicos
    document.querySelectorAll('.campo-bonus-especifico, .campo-individual, .campo-grupal, .campo-adiantamento').forEach(el => {
        el.style.display = 'none';
    });
    
    // Mostra campos conforme subtipo
    if (subtipo === 'bonus_especifico') {
        document.querySelectorAll('.campo-bonus-especifico').forEach(el => el.style.display = 'block');
        document.getElementById('extra_titulo').textContent = 'Novo Fechamento Extra - Bônus Específico';
    } else if (subtipo === 'individual') {
        document.querySelectorAll('.campo-individual').forEach(el => el.style.display = 'block');
        document.getElementById('extra_titulo').textContent = 'Novo Fechamento Extra - Individual';
    } else if (subtipo === 'grupal') {
        document.querySelectorAll('.campo-grupal').forEach(el => el.style.display = 'block');
        document.getElementById('extra_titulo').textContent = 'Novo Fechamento Extra - Grupal';
    } else if (subtipo === 'adiantamento') {
        document.querySelectorAll('.campo-adiantamento').forEach(el => el.style.display = 'block');
        document.getElementById('extra_titulo').textContent = 'Novo Fechamento Extra - Adiantamento';
    }
    
    // Gerencia atributos required
    gerenciarRequiredCampos(subtipo);
    
    // Limpa formulário
    document.getElementById('kt_modal_fechamento_extra_form').reset();
    
    // Define o subtipo novamente após o reset (para não perder o valor)
    document.getElementById('extra_subtipo').value = subtipo;
    document.getElementById('extra_template_id').value = '';
    document.getElementById('extra_template_select').value = '';
    
    // Limpa todos os containers de colaboradores (existem múltiplos)
    document.querySelectorAll('#extra_colaboradores_container').forEach(container => {
        container.innerHTML = '<p class="text-muted">Selecione uma empresa primeiro</p>';
    });
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_fechamento_extra'));
    modal.show();
}

// Quando template é selecionado, preenche campos automaticamente
document.getElementById('extra_template_select')?.addEventListener('change', function() {
    const templateId = this.value;
    const option = this.options[this.selectedIndex];
    
    if (!templateId) {
        // Limpa campos se nenhum template selecionado
        // Remove required de todos os campos ocultos
        document.querySelectorAll('.campo-bonus-especifico, .campo-individual, .campo-grupal, .campo-adiantamento').forEach(div => {
            if (div.style.display === 'none' || !div.offsetParent) {
                div.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => {
                    input.removeAttribute('required');
                });
            }
        });
        return;
    }
    
    const subtipo = option.getAttribute('data-subtipo');
    const empresaId = option.getAttribute('data-empresa-id');
    const tipoBonusId = option.getAttribute('data-tipo-bonus-id');
    const valorPadrao = option.getAttribute('data-valor-padrao');
    const observacoes = option.getAttribute('data-observacoes');
    
    // Define subtipo e mostra campos correspondentes
    document.getElementById('extra_subtipo').value = subtipo;
    document.getElementById('extra_template_id').value = templateId;
    
    // Esconde todos os campos específicos
    document.querySelectorAll('.campo-bonus-especifico, .campo-individual, .campo-grupal, .campo-adiantamento').forEach(el => {
        el.style.display = 'none';
    });
    
    // Mostra campos conforme subtipo do template
    if (subtipo === 'bonus_especifico') {
        document.querySelectorAll('.campo-bonus-especifico').forEach(el => el.style.display = 'block');
        document.getElementById('extra_titulo').textContent = 'Novo Fechamento Extra - Bônus Específico';
    } else if (subtipo === 'individual') {
        document.querySelectorAll('.campo-individual').forEach(el => el.style.display = 'block');
        document.getElementById('extra_titulo').textContent = 'Novo Fechamento Extra - Individual';
    } else if (subtipo === 'grupal') {
        document.querySelectorAll('.campo-grupal').forEach(el => el.style.display = 'block');
        document.getElementById('extra_titulo').textContent = 'Novo Fechamento Extra - Grupal';
    } else if (subtipo === 'adiantamento') {
        document.querySelectorAll('.campo-adiantamento').forEach(el => el.style.display = 'block');
        document.getElementById('extra_titulo').textContent = 'Novo Fechamento Extra - Adiantamento';
    }
    
    // Gerencia atributos required
    gerenciarRequiredCampos(subtipo);
    
    // Preenche empresa se definida no template
    if (empresaId) {
        document.getElementById('extra_empresa_id').value = empresaId;
        // Dispara evento change para carregar colaboradores
        document.getElementById('extra_empresa_id').dispatchEvent(new Event('change'));
    }
    
    // Preenche tipo de bônus se definido
    if (tipoBonusId) {
        const tipoBonusSelects = document.querySelectorAll('select[name="tipo_bonus_id"]');
        tipoBonusSelects.forEach(select => {
            select.value = tipoBonusId;
            // Atualiza campo hidden correspondente
            if (select.id === 'extra_tipo_bonus_id') {
                const hiddenField = document.getElementById('extra_tipo_bonus_id_hidden');
                if (hiddenField) {
                    hiddenField.value = tipoBonusId;
                }
            } else if (select.id === 'extra_tipo_bonus_id_individual') {
                const hiddenFieldIndividual = document.getElementById('extra_tipo_bonus_id_hidden_individual');
                if (hiddenFieldIndividual) {
                    hiddenFieldIndividual.value = tipoBonusId;
                }
            } else if (select.id === 'extra_tipo_bonus_id_grupal') {
                const hiddenFieldGrupal = document.getElementById('extra_tipo_bonus_id_hidden_grupal');
                if (hiddenFieldGrupal) {
                    hiddenFieldGrupal.value = tipoBonusId;
                }
            }
        });
    }
    
    // Preenche valor padrão se definido
    if (valorPadrao) {
        const valorInputs = document.querySelectorAll('input[name="valor_manual"], input[name="valor_adiantamento"]');
        valorInputs.forEach(input => {
            const valorFormatado = parseFloat(valorPadrao).toFixed(2).replace('.', ',');
            input.value = valorFormatado;
            // Aplica máscara se disponível
            if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
                jQuery(input).mask('#.##0,00', {reverse: true});
            }
        });
    }
    
    // Preenche observações/descrição se definida
    if (observacoes) {
        const descricaoInput = document.querySelector('textarea[name="descricao"]');
        if (descricaoInput) {
            descricaoInput.value = observacoes;
        }
    }
});

// Aplicar máscaras nos campos de valor do modal extra
function aplicarMascarasExtra() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
        jQuery('input[name="valor_manual"]').mask('#.##0,00', {reverse: true});
        jQuery('input[name="valor_adiantamento"]').mask('#.##0,00', {reverse: true});
    }
}

// Aplicar máscaras quando o modal for aberto
document.getElementById('kt_modal_fechamento_extra')?.addEventListener('shown.bs.modal', function() {
    setTimeout(aplicarMascarasExtra, 300);
});

// Garantir que campos ocultos não sejam validados ao submeter o formulário
document.getElementById('kt_modal_fechamento_extra_form')?.addEventListener('submit', function(e) {
    // Remove required de todos os campos ocultos antes da validação
    const subtipo = document.getElementById('extra_subtipo')?.value || '';
    
    // PRIMEIRO: Validação para bonus_especifico ANTES de remover required
    if (subtipo === 'bonus_especifico') {
        // Tenta encontrar o campo de várias formas
        let tipoBonusSelect = document.getElementById('extra_tipo_bonus_id');
        if (!tipoBonusSelect) {
            tipoBonusSelect = document.querySelector('.campo-bonus-especifico select[name="tipo_bonus_id"]');
        }
        if (!tipoBonusSelect) {
            // Fallback: busca em todo o formulário
            tipoBonusSelect = document.querySelector('#kt_modal_fechamento_extra_form select[name="tipo_bonus_id"]');
        }
        
        // Verifica também o campo hidden
        const tipoBonusHidden = document.getElementById('extra_tipo_bonus_id_hidden');
        const valorSelect = tipoBonusSelect?.value || '';
        const valorHidden = tipoBonusHidden?.value || '';
        const valorFinal = valorSelect || valorHidden;
        
        if (tipoBonusSelect) {
            const campoBonusEspecifico = tipoBonusSelect.closest('.campo-bonus-especifico');
            const campoVisivel = campoBonusEspecifico && campoBonusEspecifico.style.display !== 'none' && campoBonusEspecifico.offsetParent !== null;
            
            // Se o campo está visível mas vazio, impede o envio IMEDIATAMENTE
            if (campoVisivel && (!valorFinal || valorFinal === '' || valorFinal === '0')) {
                e.preventDefault();
                e.stopPropagation();
                Swal.fire({
                    title: 'Atenção',
                    text: 'Selecione o tipo de bônus antes de continuar!',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Atualiza o campo hidden antes de enviar
            if (tipoBonusHidden && valorSelect) {
                tipoBonusHidden.value = valorSelect;
            }
        }
    }
    
    // Remove required de campos que não pertencem ao subtipo atual
    if (subtipo !== 'individual') {
        document.querySelectorAll('.campo-individual input[required], .campo-individual textarea[required]').forEach(input => {
            input.removeAttribute('required');
        });
    }
    if (subtipo !== 'grupal') {
        document.querySelectorAll('.campo-grupal input[required], .campo-grupal textarea[required]').forEach(input => {
            input.removeAttribute('required');
        });
    }
    if (subtipo !== 'adiantamento') {
        document.querySelectorAll('.campo-adiantamento input[required], .campo-adiantamento textarea[required]').forEach(input => {
            input.removeAttribute('required');
        });
    }
    
    // Remove required de campos dentro de divs ocultas
    document.querySelectorAll('.campo-bonus-especifico, .campo-individual, .campo-grupal, .campo-adiantamento').forEach(div => {
        if (div.style.display === 'none' || !div.offsetParent) {
            div.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => {
                input.removeAttribute('required');
            });
        }
    });
    
    // Validação manual: verifica se colaboradores foram selecionados
    if (subtipo === 'bonus_especifico' || subtipo === 'grupal') {
        const colaboradoresSelecionados = document.querySelectorAll('input[name="colaboradores[]"]:checked');
        if (colaboradoresSelecionados.length === 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Atenção',
                text: 'Selecione pelo menos um colaborador!',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }
    } else if (subtipo === 'individual' || subtipo === 'adiantamento') {
        const colaboradorSelect = document.getElementById('extra_colaborador_id');
        const colaboradorHidden = document.getElementById('extra_colaborador_id_hidden');
        const colaboradorSelecionado = colaboradorSelect?.value || '';
        
        // Atualiza campo hidden antes de validar
        if (colaboradorSelect && colaboradorHidden) {
            colaboradorHidden.value = colaboradorSelecionado;
        }
        
        if (!colaboradorSelecionado) {
            e.preventDefault();
            Swal.fire({
                title: 'Atenção',
                text: 'Selecione um colaborador!',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }
    }
    
    // Validação FINAL para bonus_especifico: verifica novamente antes de enviar
    if (subtipo === 'bonus_especifico') {
        // Tenta encontrar o campo de várias formas
        let tipoBonusSelect = document.getElementById('extra_tipo_bonus_id');
        if (!tipoBonusSelect) {
            tipoBonusSelect = document.querySelector('.campo-bonus-especifico select[name="tipo_bonus_id"]');
        }
        if (!tipoBonusSelect) {
            // Fallback: busca em todo o formulário
            tipoBonusSelect = document.querySelector('#kt_modal_fechamento_extra_form select[name="tipo_bonus_id"]');
        }
        
        // Verifica também o campo hidden
        const tipoBonusHidden = document.getElementById('extra_tipo_bonus_id_hidden');
        const valorSelect = tipoBonusSelect?.value || '';
        const valorHidden = tipoBonusHidden?.value || '';
        const valorFinal = valorSelect || valorHidden;
        
        if (tipoBonusSelect) {
            const campoBonusEspecifico = tipoBonusSelect.closest('.campo-bonus-especifico');
            const campoVisivel = campoBonusEspecifico && campoBonusEspecifico.style.display !== 'none' && campoBonusEspecifico.offsetParent !== null;
            
            // Atualiza o campo hidden antes de enviar
            if (tipoBonusHidden && valorSelect) {
                tipoBonusHidden.value = valorSelect;
            }
            
            // Se o campo está visível mas vazio, impede o envio
            if (campoVisivel && (!valorFinal || valorFinal === '' || valorFinal === '0')) {
                e.preventDefault();
                e.stopPropagation();
                Swal.fire({
                    title: 'Atenção',
                    text: 'Selecione o tipo de bônus!',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return false;
            }
        }
    }
    
    // Atualiza campo hidden do individual antes de enviar (tipo_bonus_id é opcional no individual)
    if (subtipo === 'individual') {
        const tipoBonusSelectIndividual = document.getElementById('extra_tipo_bonus_id_individual');
        const tipoBonusHiddenIndividual = document.getElementById('extra_tipo_bonus_id_hidden_individual');
        
        if (tipoBonusSelectIndividual && tipoBonusHiddenIndividual) {
            // Atualiza o campo hidden antes de enviar
            tipoBonusHiddenIndividual.value = tipoBonusSelectIndividual.value || '';
        }
    }
    
    // Atualiza campo hidden do grupal antes de enviar (tipo_bonus_id é opcional no grupal)
    if (subtipo === 'grupal') {
        const tipoBonusSelectGrupal = document.getElementById('extra_tipo_bonus_id_grupal');
        const tipoBonusHiddenGrupal = document.getElementById('extra_tipo_bonus_id_hidden_grupal');
        
        if (tipoBonusSelectGrupal && tipoBonusHiddenGrupal) {
            // Atualiza o campo hidden antes de enviar
            tipoBonusHiddenGrupal.value = tipoBonusSelectGrupal.value || '';
        }
    }
    
    // Atualiza campo hidden do colaborador antes de enviar (individual/adiantamento)
    if (subtipo === 'individual' || subtipo === 'adiantamento') {
        const colaboradorSelect = document.getElementById('extra_colaborador_id');
        const colaboradorHidden = document.getElementById('extra_colaborador_id_hidden');
        if (colaboradorSelect && colaboradorHidden) {
            colaboradorHidden.value = colaboradorSelect.value || '';
        }
    }
    
    // Atualiza campo hidden do valor_manual antes de enviar (individual)
    if (subtipo === 'individual') {
        const valorManualField = document.getElementById('extra_valor_manual_individual');
        const valorManualHidden = document.getElementById('extra_valor_manual_hidden_individual');
        if (valorManualField && valorManualHidden) {
            valorManualHidden.value = valorManualField.value || '';
        }
    }
    
    // Atualiza campos hidden do motivo antes de enviar
    if (subtipo === 'individual') {
        const motivoField = document.getElementById('extra_motivo_individual');
        const motivoHidden = document.getElementById('extra_motivo_hidden_individual');
        if (motivoField && motivoHidden) {
            motivoHidden.value = motivoField.value || '';
        }
    } else if (subtipo === 'grupal') {
        const motivoField = document.getElementById('extra_motivo_grupal');
        const motivoHidden = document.getElementById('extra_motivo_hidden_grupal');
        if (motivoField && motivoHidden) {
            motivoHidden.value = motivoField.value || '';
        }
    } else if (subtipo === 'adiantamento') {
        const motivoField = document.getElementById('extra_motivo_adiantamento');
        const motivoHidden = document.getElementById('extra_motivo_hidden_adiantamento');
        if (motivoField && motivoHidden) {
            motivoHidden.value = motivoField.value || '';
        }
    }
});

// Carrega colaboradores para fechamento extra
document.getElementById('extra_empresa_id')?.addEventListener('change', function() {
    const empresaId = this.value;
    const subtipo = document.getElementById('extra_subtipo').value;
    
    if (!empresaId || empresaId === '') {
        // Limpa todos os containers visíveis
        document.querySelectorAll('#extra_colaboradores_container').forEach(container => {
            if (container.closest('.campo-individual, .campo-adiantamento, .campo-bonus-especifico, .campo-grupal')?.style.display !== 'none') {
                container.innerHTML = '<p class="text-muted">Selecione uma empresa primeiro</p>';
            }
        });
        return;
    }
    
    // Encontra o container correto baseado no subtipo e campo visível
    let container = null;
    if (subtipo === 'individual') {
        container = document.querySelector('.campo-individual #extra_colaboradores_container');
    } else if (subtipo === 'adiantamento') {
        container = document.querySelector('.campo-adiantamento #extra_colaboradores_container');
    } else if (subtipo === 'bonus_especifico' || subtipo === 'grupal') {
        container = document.querySelector(`.campo-${subtipo === 'bonus_especifico' ? 'bonus-especifico' : 'grupal'} #extra_colaboradores_container`);
    }
    
    // Fallback: pega o primeiro container visível
    if (!container) {
        const containers = document.querySelectorAll('#extra_colaboradores_container');
        for (let c of containers) {
            if (c.closest('.campo-individual, .campo-adiantamento, .campo-bonus-especifico, .campo-grupal')?.style.display !== 'none') {
                container = c;
                break;
            }
        }
    }
    
    if (!container) {
        return;
    }
    
    container.innerHTML = '<p class="text-muted">Carregando...</p>';
    
    // Se selecionou "Todas Empresas", não passa empresa_id na requisição
    const url = empresaId === 'todas' 
        ? '../api/get_colaboradores.php?status=ativo'
        : `../api/get_colaboradores.php?empresa_id=${empresaId}&status=ativo`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            let html = '';
            if (subtipo === 'individual' || subtipo === 'adiantamento') {
                // Select único
                html = '<select name="colaborador_id" id="extra_colaborador_id" class="form-select form-select-solid" required>';
                html += '<option value="">Selecione...</option>';
                data.forEach(colab => {
                    html += `<option value="${colab.id}">${colab.nome_completo}${colab.empresa_nome ? ' - ' + colab.empresa_nome : ''}</option>`;
                });
                html += '</select>';
                // Campo hidden para garantir que o valor seja enviado
                html += '<input type="hidden" name="colaborador_id_hidden" id="extra_colaborador_id_hidden" value="">';
            } else {
                // Checkboxes múltiplos
                data.forEach(colab => {
                    html += `
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="colaboradores[]" value="${colab.id}" id="extra_colab_${colab.id}">
                            <label class="form-check-label" for="extra_colab_${colab.id}">
                                ${colab.nome_completo}${colab.empresa_nome ? ' <small class="text-muted">(' + colab.empresa_nome + ')</small>' : ''}
                            </label>
                        </div>
                    `;
                });
            }
            container.innerHTML = html || '<p class="text-muted">Nenhum colaborador encontrado</p>';
            
            // Verifica duplicações após carregar colaboradores
            verificarDuplicacoes();
        })
        .catch((error) => {
            container.innerHTML = '<p class="text-danger">Erro ao carregar colaboradores</p>';
        });
});

// Atualiza campo hidden quando tipo_bonus_id muda (bonus_especifico)
document.getElementById('extra_tipo_bonus_id')?.addEventListener('change', function() {
    const hiddenField = document.getElementById('extra_tipo_bonus_id_hidden');
    if (hiddenField) {
        hiddenField.value = this.value || '';
    }
});

// Atualiza campo hidden quando tipo_bonus_id muda (individual)
document.getElementById('extra_tipo_bonus_id_individual')?.addEventListener('change', function() {
    const hiddenField = document.getElementById('extra_tipo_bonus_id_hidden_individual');
    if (hiddenField) {
        hiddenField.value = this.value || '';
    }
});

// Atualiza campo hidden quando tipo_bonus_id muda (grupal)
document.getElementById('extra_tipo_bonus_id_grupal')?.addEventListener('change', function() {
    const hiddenField = document.getElementById('extra_tipo_bonus_id_hidden_grupal');
    if (hiddenField) {
        hiddenField.value = this.value || '';
    }
});

// Atualiza campo hidden quando template é selecionado
document.getElementById('extra_template_select')?.addEventListener('change', function() {
    setTimeout(() => {
        // Atualiza campo bonus_especifico
        const tipoBonusSelect = document.getElementById('extra_tipo_bonus_id');
        const hiddenField = document.getElementById('extra_tipo_bonus_id_hidden');
        if (tipoBonusSelect && hiddenField) {
            hiddenField.value = tipoBonusSelect.value || '';
        }
        
        // Atualiza campo individual
        const tipoBonusSelectIndividual = document.getElementById('extra_tipo_bonus_id_individual');
        const hiddenFieldIndividual = document.getElementById('extra_tipo_bonus_id_hidden_individual');
        if (tipoBonusSelectIndividual && hiddenFieldIndividual) {
            hiddenFieldIndividual.value = tipoBonusSelectIndividual.value || '';
        }
        
        // Atualiza campo grupal
        const tipoBonusSelectGrupal = document.getElementById('extra_tipo_bonus_id_grupal');
        const hiddenFieldGrupal = document.getElementById('extra_tipo_bonus_id_hidden_grupal');
        if (tipoBonusSelectGrupal && hiddenFieldGrupal) {
            hiddenFieldGrupal.value = tipoBonusSelectGrupal.value || '';
        }
    }, 100);
});

// Atualiza campo hidden do colaborador quando muda (individual/adiantamento)
document.getElementById('extra_colaborador_id')?.addEventListener('change', function() {
    const hiddenField = document.getElementById('extra_colaborador_id_hidden');
    if (hiddenField) {
        hiddenField.value = this.value || '';
    }
});

// Atualiza campo hidden do valor_manual quando muda (individual)
document.getElementById('extra_valor_manual_individual')?.addEventListener('input', function() {
    const hiddenField = document.getElementById('extra_valor_manual_hidden_individual');
    if (hiddenField) {
        hiddenField.value = this.value || '';
    }
});

// Atualiza campos hidden do motivo quando mudam
document.getElementById('extra_motivo_individual')?.addEventListener('input', function() {
    const hiddenField = document.getElementById('extra_motivo_hidden_individual');
    if (hiddenField) {
        hiddenField.value = this.value || '';
    }
});

document.getElementById('extra_motivo_grupal')?.addEventListener('input', function() {
    const hiddenField = document.getElementById('extra_motivo_hidden_grupal');
    if (hiddenField) {
        hiddenField.value = this.value || '';
    }
});

document.getElementById('extra_motivo_adiantamento')?.addEventListener('input', function() {
    const hiddenField = document.getElementById('extra_motivo_hidden_adiantamento');
    if (hiddenField) {
        hiddenField.value = this.value || '';
    }
});

// Função para verificar duplicações de bônus
function verificarDuplicacoes() {
    const mesReferencia = document.querySelector('input[name="mes_referencia"]')?.value;
    const subtipo = document.getElementById('extra_subtipo')?.value;
    const tipoBonusId = document.querySelector('select[name="tipo_bonus_id"]')?.value;
    const colaboradores = [];
    
    // Coleta colaboradores selecionados
    if (subtipo === 'individual' || subtipo === 'adiantamento') {
        const colabId = document.getElementById('extra_colaborador_id')?.value;
        if (colabId) colaboradores.push(colabId);
    } else {
        document.querySelectorAll('input[name="colaboradores[]"]:checked').forEach(cb => {
            colaboradores.push(cb.value);
        });
    }
    
    if (!mesReferencia || !subtipo || colaboradores.length === 0) {
        return;
    }
    
    // Busca fechamentos extras existentes
    fetch(`../api/verificar_duplicacao_bonus.php?mes=${mesReferencia}&subtipo=${subtipo}&tipo_bonus_id=${tipoBonusId || ''}&colaboradores=${colaboradores.join(',')}`)
        .then(r => r.json())
        .then(data => {
            if (data.duplicacoes && data.duplicacoes.length > 0) {
                let mensagem = '⚠️ Atenção: Foram encontrados fechamentos extras similares neste mês:\n\n';
                data.duplicacoes.forEach(dup => {
                    mensagem += `• ${dup.tipo_bonus_nome || 'Bônus'} - ${dup.colaborador_nome}\n`;
                    mensagem += `  Fechamento #${dup.fechamento_id} - R$ ${parseFloat(dup.valor).toFixed(2).replace('.', ',')}\n\n`;
                });
                mensagem += 'Deseja continuar mesmo assim?';
                
                Swal.fire({
                    title: 'Possível Duplicação',
                    text: mensagem,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sim, continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (!result.isConfirmed) {
                        // Usuário cancelou, pode limpar campos ou manter
                    }
                });
            }
        })
        .catch(() => {
            // Erro silencioso - não bloqueia criação
        });
}

// Adiciona listener para verificar duplicações quando campos mudam
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('kt_modal_fechamento_extra_form');
    if (form) {
        // Verifica ao mudar mês de referência
        form.querySelector('input[name="mes_referencia"]')?.addEventListener('change', verificarDuplicacoes);
        
        // Verifica ao mudar tipo de bônus
        form.querySelectorAll('select[name="tipo_bonus_id"]').forEach(select => {
            select.addEventListener('change', verificarDuplicacoes);
        });
        
        // Verifica ao selecionar/desselecionar colaboradores
        form.addEventListener('change', function(e) {
            if (e.target.matches('input[name="colaboradores[]"], #extra_colaborador_id')) {
                setTimeout(verificarDuplicacoes, 500); // Delay para evitar muitas requisições
            }
        });
    }
});

// DataTables
var KTFechamentosList = function() {
    var initDatatable = function() {
        const table = document.getElementById('kt_fechamentos_table');
        if (!table) return;
        
        const datatable = $(table).DataTable({
            "info": true,
            "order": [],
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });
        
        const filterSearch = document.querySelector('[data-kt-fechamento-table-filter="search"]');
        if (filterSearch) {
            filterSearch.addEventListener('keyup', function(e) {
                datatable.search(e.target.value).draw();
            });
        }
    };
    
    return {
        init: function() {
            initDatatable();
        }
    };
}();

function waitForDependencies() {
    if (typeof jQuery !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        KTFechamentosList.init();
    } else {
        setTimeout(waitForDependencies, 100);
    }
}
waitForDependencies();

// Deletar fechamento
function deletarFechamento(id, mesAno) {
    Swal.fire({
        text: `Tem certeza que deseja excluir o fechamento de ${mesAno}? Esta ação não pode ser desfeita!`,
        icon: "warning",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, excluir!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-danger",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="fechamento_id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Mostrar detalhes dos bônus
function mostrarDetalhesBonus(dados) {
    const titulo = document.getElementById('kt_modal_detalhes_bonus_titulo');
    const conteudo = document.getElementById('kt_modal_detalhes_bonus_conteudo');
    
    titulo.textContent = `Bônus de ${dados.colaborador_nome}`;
    
    let html = '<div class="mb-7">';
    html += '<div class="d-flex justify-content-between align-items-center mb-5">';
    html += '<h4 class="fw-bold text-gray-800">Total de Bônus (somados)</h4>';
    html += '<span class="text-success fw-bold fs-2">R$ ' + parseFloat(dados.total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
    html += '</div>';
    
    // Mostra total de desconto por ocorrências se houver
    if (dados.total_desconto_ocorrencias && dados.total_desconto_ocorrencias > 0) {
        html += '<div class="alert alert-warning d-flex align-items-center mb-5">';
        html += '<i class="ki-duotone ki-information-5 fs-2x text-warning me-3">';
        html += '<span class="path1"></span>';
        html += '<span class="path2"></span>';
        html += '<span class="path3"></span>';
        html += '</i>';
        html += '<div>';
        html += '<strong>Desconto Total por Ocorrências:</strong> ';
        html += '<span class="fw-bold text-danger fs-3">-R$ ' + parseFloat(dados.total_desconto_ocorrencias || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
        html += '</div>';
        html += '</div>';
    }
    
    // Mostra bônus que somam no total
    if (dados.bonus_somam && dados.bonus_somam.length > 0) {
        html += '<h5 class="fw-bold mb-3">Bônus que Somam no Total</h5>';
        html += '<div class="table-responsive mb-5">';
        html += '<table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">';
        html += '<thead>';
        html += '<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">';
        html += '<th class="min-w-150px">Tipo de Bônus</th>';
        html += '<th class="min-w-100px">Tipo</th>';
        html += '<th class="min-w-100px text-end">Valor</th>';
        html += '<th class="min-w-100px">Data Início</th>';
        html += '<th class="min-w-100px">Data Fim</th>';
        html += '<th class="min-w-200px">Observações</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        dados.bonus_somam.forEach(function(bonus) {
            const tipoValor = bonus.tipo_valor || 'variavel';
            const tipoLabel = tipoValor === 'fixo' ? 'Valor Fixo' : 'Variável';
            const tipoBadge = tipoValor === 'fixo' ? 'primary' : 'success';
            const valorOriginal = parseFloat(bonus.valor_original || bonus.valor || 0);
            const descontoOcorrencias = parseFloat(bonus.desconto_ocorrencias || 0);
            const valorFinal = parseFloat(bonus.valor || 0);
            
            html += '<tr>';
            html += '<td><span class="fw-bold text-gray-800">' + (bonus.tipo_bonus_nome || '-') + '</span></td>';
            html += '<td><span class="badge badge-light-' + tipoBadge + '">' + tipoLabel + '</span></td>';
            html += '<td class="text-end">';
            if (descontoOcorrencias > 0) {
                html += '<div class="d-flex flex-column align-items-end">';
                html += '<span class="text-muted text-decoration-line-through fs-7">R$ ' + valorOriginal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
                html += '<span class="text-danger fs-7">-R$ ' + descontoOcorrencias.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
                html += '<span class="fw-bold text-success">R$ ' + valorFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
                html += '</div>';
            } else {
                html += '<span class="fw-bold text-success">R$ ' + valorFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
            }
            html += '</td>';
            html += '<td>' + (bonus.data_inicio ? new Date(bonus.data_inicio + 'T00:00:00').toLocaleDateString('pt-BR') : '<span class="text-muted">Permanente</span>') + '</td>';
            html += '<td>' + (bonus.data_fim ? new Date(bonus.data_fim + 'T00:00:00').toLocaleDateString('pt-BR') : '<span class="text-muted">Permanente</span>') + '</td>';
            html += '<td>' + (bonus.observacoes || '-') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
    }
    
    // Mostra bônus informativos
    if (dados.bonus_informativos && dados.bonus_informativos.length > 0) {
        html += '<h5 class="fw-bold mb-3 text-info">Bônus Informativos (não somam no total)</h5>';
        html += '<div class="table-responsive mb-5">';
        html += '<table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">';
        html += '<thead>';
        html += '<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">';
        html += '<th class="min-w-150px">Tipo de Bônus</th>';
        html += '<th class="min-w-100px">Data Início</th>';
        html += '<th class="min-w-100px">Data Fim</th>';
        html += '<th class="min-w-200px">Observações</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        dados.bonus_informativos.forEach(function(bonus) {
            html += '<tr>';
            html += '<td><span class="fw-bold text-gray-800">' + (bonus.tipo_bonus_nome || '-') + '</span></td>';
            html += '<td>' + (bonus.data_inicio ? new Date(bonus.data_inicio + 'T00:00:00').toLocaleDateString('pt-BR') : '<span class="text-muted">Permanente</span>') + '</td>';
            html += '<td>' + (bonus.data_fim ? new Date(bonus.data_fim + 'T00:00:00').toLocaleDateString('pt-BR') : '<span class="text-muted">Permanente</span>') + '</td>';
            html += '<td>' + (bonus.observacoes || '-') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
    }
    
    if ((!dados.bonus_somam || dados.bonus_somam.length === 0) && (!dados.bonus_informativos || dados.bonus_informativos.length === 0)) {
        html += '<div class="alert alert-info">Nenhum bônus encontrado.</div>';
    }
    
    html += '</div>';
    
    conteudo.innerHTML = html;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_detalhes_bonus'));
    modal.show();
}

// Fechar fechamento
function fecharFechamento(id) {
    Swal.fire({
        text: "Tem certeza que deseja fechar este fechamento?",
        icon: "question",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, fechar!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-success",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            document.getElementById('form_fechar_' + id).submit();
        }
    });
}

// Aprovar documento
function aprovarDocumento(itemId) {
    Swal.fire({
        title: 'Aprovar Documento?',
        text: 'Tem certeza que deseja aprovar este documento?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, aprovar',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: "btn fw-bold btn-success",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('acao', 'aprovar');
            formData.append('observacoes', '');
            
            fetch('../api/aprovar_documento_pagamento.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro', 'Erro ao processar solicitação', 'error');
            });
        }
    });
}

// Rejeitar documento
function rejeitarDocumento(itemId) {
    Swal.fire({
        title: 'Rejeitar Documento',
        input: 'textarea',
        inputLabel: 'Motivo da rejeição',
        inputPlaceholder: 'Digite o motivo da rejeição...',
        inputAttributes: {
            'aria-label': 'Digite o motivo da rejeição'
        },
        showCancelButton: true,
        confirmButtonText: 'Rejeitar',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: "btn fw-bold btn-danger",
            cancelButton: "btn fw-bold btn-active-light-primary"
        },
        inputValidator: (value) => {
            if (!value) {
                return 'O motivo da rejeição é obrigatório!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('acao', 'rejeitar');
            formData.append('observacoes', result.value);
            
            fetch('../api/aprovar_documento_pagamento.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro', 'Erro ao processar solicitação', 'error');
            });
        }
    });
}

// Ver detalhes completos do pagamento
function verDetalhesPagamento(fechamentoId, colaboradorId) {
    const titulo = document.getElementById('kt_modal_detalhes_pagamento_titulo');
    const conteudo = document.getElementById('kt_modal_detalhes_pagamento_conteudo');
    
    // Mostra loading
    conteudo.innerHTML = `
        <div class="text-center py-10">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <div class="text-muted mt-3">Carregando detalhes...</div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_detalhes_pagamento'));
    modal.show();
    
    // Busca dados via API
    fetch(`../api/get_detalhes_pagamento.php?fechamento_id=${fechamentoId}&colaborador_id=${colaboradorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const d = data.data;
                
                titulo.textContent = `Detalhes do Pagamento - ${d.colaborador.nome_completo}`;
                
                let html = `
                    <div class="mb-10">
                        <!-- Informações do Fechamento -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Informações do Fechamento</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Mês/Ano de Referência:</strong><br>
                                        <span class="text-gray-800">${d.fechamento.mes_referencia_formatado}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Data de Fechamento:</strong><br>
                                        <span class="text-gray-800">${d.fechamento.data_fechamento_formatada}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Empresa:</strong><br>
                                        <span class="text-gray-800">${d.fechamento.empresa_nome}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Período:</strong><br>
                                        <span class="text-gray-800">${d.periodo.inicio_formatado} até ${d.periodo.fim_formatado}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações do Colaborador -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Colaborador</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Nome:</strong><br>
                                        <span class="text-gray-800">${d.colaborador.nome_completo}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>CPF:</strong><br>
                                        <span class="text-gray-800">${d.colaborador.cpf || '-'}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Cargo:</strong><br>
                                        <span class="text-gray-800">${d.colaborador.cargo || '-'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resumo Financeiro -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Resumo Financeiro</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed gy-4">
                                        <tbody>
                                            <tr>
                                                <td class="fw-bold">Salário Base</td>
                                                <td class="text-end">
                                                    <span class="text-gray-800 fw-bold">R$ ${parseFloat(d.item.salario_base).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Horas Extras (Total)</td>
                                                <td class="text-end">
                                                    <span class="text-gray-800 fw-bold">${parseFloat(d.item.horas_extras).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Valor Horas Extras</td>
                                                <td class="text-end">
                                                    <span class="text-success fw-bold">R$ ${parseFloat(d.item.valor_horas_extras).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Total de Bônus</td>
                                                <td class="text-end">
                                                    <span class="text-success fw-bold">R$ ${parseFloat(d.bonus.total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ${d.bonus.total_desconto_ocorrencias > 0 ? `
                                            <tr>
                                                <td class="fw-bold text-danger">Desconto por Ocorrências (Bônus)</td>
                                                <td class="text-end">
                                                    <span class="text-danger fw-bold">-R$ ${parseFloat(d.bonus.total_desconto_ocorrencias).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ` : ''}
                                            ${d.ocorrencias.total_descontos > 0 ? `
                                            <tr>
                                                <td class="fw-bold text-danger">Descontos (Ocorrências)</td>
                                                <td class="text-end">
                                                    <span class="text-danger fw-bold">-R$ ${parseFloat(d.ocorrencias.total_descontos).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ` : ''}
                                            ${d.item.descontos > 0 ? `
                                            <tr>
                                                <td class="fw-bold text-danger">Outros Descontos</td>
                                                <td class="text-end">
                                                    <span class="text-danger fw-bold">-R$ ${parseFloat(d.item.descontos).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ` : ''}
                                            ${d.item.adicionais > 0 ? `
                                            <tr>
                                                <td class="fw-bold text-success">Adicionais</td>
                                                <td class="text-end">
                                                    <span class="text-success fw-bold">+R$ ${parseFloat(d.item.adicionais).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ` : ''}
                                            <tr class="border-top border-2">
                                                <td class="fw-bold fs-4">Valor Total</td>
                                                <td class="text-end">
                                                    <span class="text-success fw-bold fs-3">R$ ${parseFloat(d.item.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detalhes de Horas Extras -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Detalhes de Horas Extras</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-5">
                                    <h5 class="fw-bold mb-3">Resumo</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-success rounded">
                                                <i class="ki-duotone ki-money fs-2x text-success me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Horas Pagas em R$</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.resumo.horas_dinheiro).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                    <div class="text-success fs-6">R$ ${parseFloat(d.horas_extras.resumo.valor_dinheiro).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-info rounded">
                                                <i class="ki-duotone ki-time fs-2x text-info me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Horas em Banco</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.resumo.horas_banco).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-primary rounded">
                                                <i class="ki-duotone ki-chart-simple fs-2x text-primary me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Total de Horas</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.total_horas).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${d.horas_extras.registros.length > 0 ? `
                                <h5 class="fw-bold mb-3">Registros Individuais</h5>
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed gy-4">
                                        <thead>
                                            <tr class="fw-bold">
                                                <th>Data</th>
                                                <th>Quantidade</th>
                                                <th>Valor/Hora</th>
                                                <th>% Adicional</th>
                                                <th>Valor Total</th>
                                                <th>Tipo</th>
                                                <th>Observações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${d.horas_extras.registros.map(he => `
                                                <tr>
                                                    <td>${new Date(he.data_trabalho + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                                    <td>${parseFloat(he.quantidade_horas).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</td>
                                                    <td>R$ ${parseFloat(he.valor_hora || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                    <td>${parseFloat(he.percentual_adicional || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
                                                    <td class="fw-bold">R$ ${parseFloat(he.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                    <td>
                                                        ${he.tipo_pagamento === 'banco_horas' 
                                                            ? '<span class="badge badge-light-info">Banco de Horas</span>' 
                                                            : '<span class="badge badge-light-success">Dinheiro</span>'}
                                                    </td>
                                                    <td>${he.observacoes || '-'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                ` : '<p class="text-muted">Nenhuma hora extra registrada neste período.</p>'}
                            </div>
                        </div>
                        
                        <!-- Detalhes de Bônus -->
                        ${d.bonus.lista.length > 0 ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Detalhes de Bônus</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed gy-4">
                                        <thead>
                                            <tr class="fw-bold">
                                                <th>Tipo de Bônus</th>
                                                <th>Tipo</th>
                                                <th>Valor Original</th>
                                                <th>Desconto Ocorrências</th>
                                                <th>Valor Final</th>
                                                <th>Observações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${d.bonus.lista.map(b => {
                                                const tipoValor = b.tipo_valor || 'variavel';
                                                const tipoLabel = tipoValor === 'fixo' ? 'Valor Fixo' : tipoValor === 'informativo' ? 'Informativo' : 'Variável';
                                                const tipoBadge = tipoValor === 'fixo' ? 'primary' : tipoValor === 'informativo' ? 'info' : 'success';
                                                const valorOriginal = parseFloat(b.valor_original || b.valor || 0);
                                                const descontoOcorrencias = parseFloat(b.desconto_ocorrencias || 0);
                                                const valorFinal = parseFloat(b.valor || 0);
                                                
                                                return `
                                                    <tr>
                                                        <td class="fw-bold">${b.tipo_bonus_nome}</td>
                                                        <td><span class="badge badge-light-${tipoBadge}">${tipoLabel}</span></td>
                                                        <td>R$ ${valorOriginal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        <td class="text-danger">${descontoOcorrencias > 0 ? '-R$ ' + descontoOcorrencias.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
                                                        <td class="fw-bold text-success">R$ ${valorFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        <td>${b.observacoes || '-'}</td>
                                                    </tr>
                                                `;
                                            }).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                
                                ${d.bonus.lista.some(b => b.detalhes_desconto_array && b.detalhes_desconto_array.length > 0) ? `
                                <div class="separator separator-dashed my-5"></div>
                                <h5 class="fw-bold mb-3">Detalhes de Descontos por Ocorrências</h5>
                                ${d.bonus.lista.filter(b => b.detalhes_desconto_array && b.detalhes_desconto_array.length > 0).map(b => `
                                    <div class="mb-5">
                                        <h6 class="fw-bold text-gray-800 mb-3">${b.tipo_bonus_nome}</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-row-bordered">
                                                <thead>
                                                    <tr class="fw-bold fs-7">
                                                        <th>Ocorrência</th>
                                                        <th>Período Atual</th>
                                                        <th>Período Anterior</th>
                                                        <th>Tipo Desconto</th>
                                                        <th>Desconto Aplicado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${b.detalhes_desconto_array.map(det => `
                                                        <tr>
                                                            <td>${det.tipo_ocorrencia_nome || det.tipo_ocorrencia_codigo || '-'}</td>
                                                            <td>${det.total_ocorrencias_periodo_atual || 0} ocorrência(s)</td>
                                                            <td>${det.total_ocorrencias_periodo_anterior || 0} ocorrência(s)</td>
                                                            <td>
                                                                ${det.tipo_desconto === 'total' ? '<span class="badge badge-light-danger">Valor Total</span>' : 
                                                                  det.tipo_desconto === 'fixo' ? '<span class="badge badge-light-warning">Fixo</span>' :
                                                                  det.tipo_desconto === 'percentual' ? '<span class="badge badge-light-info">Percentual</span>' :
                                                                  '<span class="badge badge-light-primary">Proporcional</span>'}
                                                            </td>
                                                            <td class="text-danger fw-bold">-R$ ${parseFloat(det.desconto_aplicado || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                `).join('')}
                                ` : ''}
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Ocorrências com Desconto -->
                        ${d.ocorrencias.descontos.length > 0 ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Ocorrências com Desconto em R$</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed gy-4">
                                        <thead>
                                            <tr class="fw-bold">
                                                <th>Data</th>
                                                <th>Tipo</th>
                                                <th>Descrição</th>
                                                <th>Valor Desconto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${d.ocorrencias.descontos.map(occ => `
                                                <tr>
                                                    <td>${new Date(occ.data_ocorrencia + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                                    <td><span class="badge badge-light-danger">${occ.tipo_ocorrencia_nome || occ.tipo_ocorrencia_codigo || '-'}</span></td>
                                                    <td>${occ.descricao || '-'}</td>
                                                    <td class="text-danger fw-bold">-R$ ${parseFloat(occ.valor_desconto || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Status do Documento -->
                        ${d.documento.status ? `
                        <div class="card card-flush">
                            <div class="card-header">
                                <h3 class="card-title">Status do Documento</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Status:</strong><br>
                                        ${d.documento.status === 'aprovado' ? '<span class="badge badge-light-success">Aprovado</span>' :
                                          d.documento.status === 'enviado' ? '<span class="badge badge-light-warning">Enviado</span>' :
                                          d.documento.status === 'rejeitado' ? '<span class="badge badge-light-danger">Rejeitado</span>' :
                                          '<span class="badge badge-light-secondary">Pendente</span>'}
                                    </div>
                                    ${d.documento.data_envio ? `
                                    <div class="col-md-6 mb-3">
                                        <strong>Data de Envio:</strong><br>
                                        <span class="text-gray-800">${new Date(d.documento.data_envio).toLocaleString('pt-BR')}</span>
                                    </div>
                                    ` : ''}
                                    ${d.documento.data_aprovacao ? `
                                    <div class="col-md-6 mb-3">
                                        <strong>Data de Aprovação:</strong><br>
                                        <span class="text-gray-800">${new Date(d.documento.data_aprovacao).toLocaleString('pt-BR')}</span>
                                    </div>
                                    ` : ''}
                                    ${d.documento.observacoes ? `
                                    <div class="col-md-12 mb-3">
                                        <strong>Observações:</strong><br>
                                        <span class="text-gray-800">${d.documento.observacoes}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                conteudo.innerHTML = html;
            } else {
                conteudo.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="ki-duotone ki-information-5 fs-2x me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        ${data.message || 'Erro ao carregar detalhes do pagamento'}
                    </div>
                `;
            }
        })
        .catch(error => {
            conteudo.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2x me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Erro ao carregar detalhes do pagamento
                </div>
            `;
        });
}

// Ver detalhes completos do pagamento
function verDetalhesPagamento(fechamentoId, colaboradorId) {
    const titulo = document.getElementById('kt_modal_detalhes_pagamento_titulo');
    const conteudo = document.getElementById('kt_modal_detalhes_pagamento_conteudo');
    
    // Mostra loading
    conteudo.innerHTML = `
        <div class="text-center py-10">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <div class="text-muted mt-3">Carregando detalhes...</div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_detalhes_pagamento'));
    modal.show();
    
    // Busca dados via API
    fetch(`../api/get_detalhes_pagamento.php?fechamento_id=${fechamentoId}&colaborador_id=${colaboradorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const d = data.data;
                
                titulo.textContent = `Detalhes do Pagamento - ${d.colaborador.nome_completo}`;
                
                let html = `
                    <div class="mb-10">
                        <!-- Informações do Fechamento -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Informações do Fechamento</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Mês/Ano de Referência:</strong><br>
                                        <span class="text-gray-800">${d.fechamento.mes_referencia_formatado}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Data de Fechamento:</strong><br>
                                        <span class="text-gray-800">${d.fechamento.data_fechamento_formatada}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Empresa:</strong><br>
                                        <span class="text-gray-800">${d.fechamento.empresa_nome}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Período:</strong><br>
                                        <span class="text-gray-800">${d.periodo.inicio_formatado} até ${d.periodo.fim_formatado}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações do Colaborador -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Colaborador</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Nome:</strong><br>
                                        <span class="text-gray-800">${d.colaborador.nome_completo}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>CPF:</strong><br>
                                        <span class="text-gray-800">${d.colaborador.cpf || '-'}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Cargo:</strong><br>
                                        <span class="text-gray-800">${d.colaborador.cargo || '-'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resumo Financeiro -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Resumo Financeiro</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed gy-4">
                                        <tbody>
                                            <tr>
                                                <td class="fw-bold">Salário Base</td>
                                                <td class="text-end">
                                                    <span class="text-gray-800 fw-bold">R$ ${parseFloat(d.item.salario_base).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Horas Extras (Total)</td>
                                                <td class="text-end">
                                                    <span class="text-gray-800 fw-bold">${parseFloat(d.item.horas_extras).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Valor Horas Extras</td>
                                                <td class="text-end">
                                                    <span class="text-success fw-bold">R$ ${parseFloat(d.item.valor_horas_extras).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Total de Bônus</td>
                                                <td class="text-end">
                                                    <span class="text-success fw-bold">R$ ${parseFloat(d.bonus.total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ${d.bonus.total_desconto_ocorrencias > 0 ? `
                                            <tr>
                                                <td class="fw-bold text-danger">Desconto por Ocorrências (Bônus)</td>
                                                <td class="text-end">
                                                    <span class="text-danger fw-bold">-R$ ${parseFloat(d.bonus.total_desconto_ocorrencias).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ` : ''}
                                            ${d.ocorrencias.total_descontos > 0 ? `
                                            <tr>
                                                <td class="fw-bold text-danger">Descontos (Ocorrências)</td>
                                                <td class="text-end">
                                                    <span class="text-danger fw-bold">-R$ ${parseFloat(d.ocorrencias.total_descontos).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ` : ''}
                                            ${d.item.descontos > 0 ? `
                                            <tr>
                                                <td class="fw-bold text-danger">Outros Descontos</td>
                                                <td class="text-end">
                                                    <span class="text-danger fw-bold">-R$ ${parseFloat(d.item.descontos).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ` : ''}
                                            ${d.item.adicionais > 0 ? `
                                            <tr>
                                                <td class="fw-bold text-success">Adicionais</td>
                                                <td class="text-end">
                                                    <span class="text-success fw-bold">+R$ ${parseFloat(d.item.adicionais).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                            ` : ''}
                                            <tr class="border-top border-2">
                                                <td class="fw-bold fs-4">Valor Total</td>
                                                <td class="text-end">
                                                    <span class="text-success fw-bold fs-3">R$ ${parseFloat(d.item.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detalhes de Horas Extras -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Detalhes de Horas Extras</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-5">
                                    <h5 class="fw-bold mb-3">Resumo</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-success rounded">
                                                <i class="ki-duotone ki-money fs-2x text-success me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Horas Pagas em R$</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.resumo.horas_dinheiro).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                    <div class="text-success fs-6">R$ ${parseFloat(d.horas_extras.resumo.valor_dinheiro).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-info rounded">
                                                <i class="ki-duotone ki-time fs-2x text-info me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Horas em Banco</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.resumo.horas_banco).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-primary rounded">
                                                <i class="ki-duotone ki-chart-simple fs-2x text-primary me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Total de Horas</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.total_horas).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${d.horas_extras.registros.length > 0 ? `
                                <h5 class="fw-bold mb-3">Registros Individuais</h5>
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed gy-4">
                                        <thead>
                                            <tr class="fw-bold">
                                                <th>Data</th>
                                                <th>Quantidade</th>
                                                <th>Valor/Hora</th>
                                                <th>% Adicional</th>
                                                <th>Valor Total</th>
                                                <th>Tipo</th>
                                                <th>Observações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${d.horas_extras.registros.map(he => `
                                                <tr>
                                                    <td>${new Date(he.data_trabalho + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                                    <td>${parseFloat(he.quantidade_horas).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</td>
                                                    <td>R$ ${parseFloat(he.valor_hora || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                    <td>${parseFloat(he.percentual_adicional || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
                                                    <td class="fw-bold">R$ ${parseFloat(he.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                    <td>
                                                        ${he.tipo_pagamento === 'banco_horas' 
                                                            ? '<span class="badge badge-light-info">Banco de Horas</span>' 
                                                            : '<span class="badge badge-light-success">Dinheiro</span>'}
                                                    </td>
                                                    <td>${he.observacoes || '-'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                ` : '<p class="text-muted">Nenhuma hora extra registrada neste período.</p>'}
                            </div>
                        </div>
                        
                        <!-- Detalhes de Bônus -->
                        ${d.bonus.lista.length > 0 ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Detalhes de Bônus</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed gy-4">
                                        <thead>
                                            <tr class="fw-bold">
                                                <th>Tipo de Bônus</th>
                                                <th>Tipo</th>
                                                <th>Valor Original</th>
                                                <th>Desconto Ocorrências</th>
                                                <th>Valor Final</th>
                                                <th>Observações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${d.bonus.lista.map(b => {
                                                const tipoValor = b.tipo_valor || 'variavel';
                                                const tipoLabel = tipoValor === 'fixo' ? 'Valor Fixo' : tipoValor === 'informativo' ? 'Informativo' : 'Variável';
                                                const tipoBadge = tipoValor === 'fixo' ? 'primary' : tipoValor === 'informativo' ? 'info' : 'success';
                                                const valorOriginal = parseFloat(b.valor_original || b.valor || 0);
                                                const descontoOcorrencias = parseFloat(b.desconto_ocorrencias || 0);
                                                const valorFinal = parseFloat(b.valor || 0);
                                                
                                                return `
                                                    <tr>
                                                        <td class="fw-bold">${b.tipo_bonus_nome}</td>
                                                        <td><span class="badge badge-light-${tipoBadge}">${tipoLabel}</span></td>
                                                        <td>R$ ${valorOriginal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        <td class="text-danger">${descontoOcorrencias > 0 ? '-R$ ' + descontoOcorrencias.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
                                                        <td class="fw-bold text-success">R$ ${valorFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        <td>${b.observacoes || '-'}</td>
                                                    </tr>
                                                `;
                                            }).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                
                                ${d.bonus.lista.some(b => b.detalhes_desconto_array && b.detalhes_desconto_array.length > 0) ? `
                                <div class="separator separator-dashed my-5"></div>
                                <h5 class="fw-bold mb-3">Detalhes de Descontos por Ocorrências</h5>
                                ${d.bonus.lista.filter(b => b.detalhes_desconto_array && b.detalhes_desconto_array.length > 0).map(b => `
                                    <div class="mb-5">
                                        <h6 class="fw-bold text-gray-800 mb-3">${b.tipo_bonus_nome}</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-row-bordered">
                                                <thead>
                                                    <tr class="fw-bold fs-7">
                                                        <th>Ocorrência</th>
                                                        <th>Período Atual</th>
                                                        <th>Período Anterior</th>
                                                        <th>Tipo Desconto</th>
                                                        <th>Desconto Aplicado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${b.detalhes_desconto_array.map(det => `
                                                        <tr>
                                                            <td>${det.tipo_ocorrencia_nome || det.tipo_ocorrencia_codigo || '-'}</td>
                                                            <td>${det.total_ocorrencias_periodo_atual || 0} ocorrência(s)</td>
                                                            <td>${det.total_ocorrencias_periodo_anterior || 0} ocorrência(s)</td>
                                                            <td>
                                                                ${det.tipo_desconto === 'total' ? '<span class="badge badge-light-danger">Valor Total</span>' : 
                                                                  det.tipo_desconto === 'fixo' ? '<span class="badge badge-light-warning">Fixo</span>' :
                                                                  det.tipo_desconto === 'percentual' ? '<span class="badge badge-light-info">Percentual</span>' :
                                                                  '<span class="badge badge-light-primary">Proporcional</span>'}
                                                            </td>
                                                            <td class="text-danger fw-bold">-R$ ${parseFloat(det.desconto_aplicado || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                `).join('')}
                                ` : ''}
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Ocorrências com Desconto -->
                        ${d.ocorrencias.descontos.length > 0 ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Ocorrências com Desconto em R$</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered table-row-dashed gy-4">
                                        <thead>
                                            <tr class="fw-bold">
                                                <th>Data</th>
                                                <th>Tipo</th>
                                                <th>Descrição</th>
                                                <th>Valor Desconto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${d.ocorrencias.descontos.map(occ => `
                                                <tr>
                                                    <td>${new Date(occ.data_ocorrencia + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                                    <td><span class="badge badge-light-danger">${occ.tipo_ocorrencia_nome || occ.tipo_ocorrencia_codigo || '-'}</span></td>
                                                    <td>${occ.descricao || '-'}</td>
                                                    <td class="text-danger fw-bold">-R$ ${parseFloat(occ.valor_desconto || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Status do Documento -->
                        ${d.documento.status ? `
                        <div class="card card-flush">
                            <div class="card-header">
                                <h3 class="card-title">Status do Documento</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Status:</strong><br>
                                        ${d.documento.status === 'aprovado' ? '<span class="badge badge-light-success">Aprovado</span>' :
                                          d.documento.status === 'enviado' ? '<span class="badge badge-light-warning">Enviado</span>' :
                                          d.documento.status === 'rejeitado' ? '<span class="badge badge-light-danger">Rejeitado</span>' :
                                          '<span class="badge badge-light-secondary">Pendente</span>'}
                                    </div>
                                    ${d.documento.data_envio ? `
                                    <div class="col-md-6 mb-3">
                                        <strong>Data de Envio:</strong><br>
                                        <span class="text-gray-800">${new Date(d.documento.data_envio).toLocaleString('pt-BR')}</span>
                                    </div>
                                    ` : ''}
                                    ${d.documento.data_aprovacao ? `
                                    <div class="col-md-6 mb-3">
                                        <strong>Data de Aprovação:</strong><br>
                                        <span class="text-gray-800">${new Date(d.documento.data_aprovacao).toLocaleString('pt-BR')}</span>
                                    </div>
                                    ` : ''}
                                    ${d.documento.observacoes ? `
                                    <div class="col-md-12 mb-3">
                                        <strong>Observações:</strong><br>
                                        <span class="text-gray-800">${d.documento.observacoes}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                conteudo.innerHTML = html;
            } else {
                conteudo.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="ki-duotone ki-information-5 fs-2x me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        ${data.message || 'Erro ao carregar detalhes do pagamento'}
                    </div>
                `;
            }
        })
        .catch(error => {
            conteudo.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2x me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Erro ao carregar detalhes do pagamento
                </div>
            `;
        });
}

// Ver documento (admin)
function verDocumentoAdmin(fechamentoId, itemId) {
    fetch(`../api/get_documento_pagamento.php?fechamento_id=${fechamentoId}&item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const doc = data.data;
                const isImage = doc.is_image;
                
                let html = '';
                if (isImage) {
                    html = `
                        <div class="text-center">
                            <img src="../${doc.documento_anexo}" class="img-fluid" alt="Documento" style="max-height: 600px;">
                        </div>
                        <div class="mt-5">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Status:</span>
                                <span class="fw-bold">${doc.documento_status === 'aprovado' ? 'Aprovado' : doc.documento_status === 'rejeitado' ? 'Rejeitado' : 'Enviado'}</span>
                            </div>
                            ${doc.documento_data_envio ? `<div class="d-flex justify-content-between mb-2"><span class="text-muted">Data Envio:</span><span>${new Date(doc.documento_data_envio).toLocaleString('pt-BR')}</span></div>` : ''}
                            ${doc.documento_data_aprovacao ? `<div class="d-flex justify-content-between mb-2"><span class="text-muted">Data Aprovação:</span><span>${new Date(doc.documento_data_aprovacao).toLocaleString('pt-BR')}</span></div>` : ''}
                            ${doc.documento_observacoes ? `<div class="mt-3"><strong>Observações:</strong><div class="text-gray-600">${doc.documento_observacoes}</div></div>` : ''}
                        </div>
                    `;
                } else {
                    html = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-file fs-3x text-primary mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-gray-600 mb-3">Clique em "Download" para baixar o documento</div>
                            <div class="text-muted fs-7 mb-5">${doc.documento_nome || 'documento'}</div>
                            <div class="text-start">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Status:</span>
                                    <span class="fw-bold">${doc.documento_status === 'aprovado' ? 'Aprovado' : doc.documento_status === 'rejeitado' ? 'Rejeitado' : 'Enviado'}</span>
                                </div>
                                ${doc.documento_data_envio ? `<div class="d-flex justify-content-between mb-2"><span class="text-muted">Data Envio:</span><span>${new Date(doc.documento_data_envio).toLocaleString('pt-BR')}</span></div>` : ''}
                                ${doc.documento_data_aprovacao ? `<div class="d-flex justify-content-between mb-2"><span class="text-muted">Data Aprovação:</span><span>${new Date(doc.documento_data_aprovacao).toLocaleString('pt-BR')}</span></div>` : ''}
                                ${doc.documento_observacoes ? `<div class="mt-3"><strong>Observações:</strong><div class="text-gray-600">${doc.documento_observacoes}</div></div>` : ''}
                            </div>
                        </div>
                    `;
                }
                
                Swal.fire({
                    title: 'Documento',
                    html: html,
                    width: isImage ? '80%' : '700px',
                    showCancelButton: true,
                    confirmButtonText: 'Download',
                    cancelButtonText: 'Fechar',
                    buttonsStyling: false,
                    customClass: {
                        popup: 'text-start',
                        confirmButton: "btn fw-bold btn-primary",
                        cancelButton: "btn fw-bold btn-active-light-primary"
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open('../' + doc.documento_anexo, '_blank');
                    }
                });
            } else {
                Swal.fire('Erro', data.message || 'Erro ao carregar documento', 'error');
            }
        })
        .catch(error => {
            Swal.fire('Erro', 'Erro ao carregar documento', 'error');
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

