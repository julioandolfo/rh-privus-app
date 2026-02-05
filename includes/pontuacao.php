<?php
/**
 * Sistema de Pontuação
 * Funções para gerenciar pontuação por ações
 */

require_once __DIR__ . '/functions.php';

/**
 * Adiciona pontos para uma ação
 * 
 * @param string $acao Nome da ação (ex: 'registrar_emocao', 'postar_feed')
 * @param int|null $usuario_id ID do usuário
 * @param int|null $colaborador_id ID do colaborador
 * @param int|null $referencia_id ID de referência (opcional)
 * @param string|null $referencia_tipo Tipo de referência (opcional)
 * @return bool True se pontos foram adicionados
 */
function adicionar_pontos($acao, $usuario_id = null, $colaborador_id = null, $referencia_id = null, $referencia_tipo = null) {
    try {
        $pdo = getDB();
        
        // Busca pontos da ação
        $stmt = $pdo->prepare("SELECT pontos FROM pontos_config WHERE acao = ? AND ativo = 1");
        $stmt->execute([$acao]);
        $config = $stmt->fetch();
        
        if (!$config || $config['pontos'] <= 0) {
            return false; // Ação não configurada ou sem pontos
        }
        
        $pontos = $config['pontos'];
        $data_registro = date('Y-m-d');
        
        // Verifica se já ganhou pontos hoje para esta ação (para evitar duplicação)
        if ($acao === 'acesso_diario') {
            if ($usuario_id) {
                $stmt = $pdo->prepare("SELECT id FROM pontos_historico WHERE usuario_id = ? AND acao = ? AND data_registro = ?");
                $stmt->execute([$usuario_id, $acao, $data_registro]);
            } else if ($colaborador_id) {
                $stmt = $pdo->prepare("SELECT id FROM pontos_historico WHERE colaborador_id = ? AND acao = ? AND data_registro = ?");
                $stmt->execute([$colaborador_id, $acao, $data_registro]);
            } else {
                return false;
            }
            
            if ($stmt->fetch()) {
                return false; // Já ganhou pontos hoje por acesso
            }
        }
        
        // Insere no histórico
        $stmt = $pdo->prepare("
            INSERT INTO pontos_historico (usuario_id, colaborador_id, acao, pontos, referencia_id, referencia_tipo, data_registro)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $colaborador_id, $acao, $pontos, $referencia_id, $referencia_tipo, $data_registro]);
        
        // Atualiza total de pontos
        atualizar_total_pontos($usuario_id, $colaborador_id);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erro ao adicionar pontos: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza o total de pontos de um usuário/colaborador
 */
function atualizar_total_pontos($usuario_id = null, $colaborador_id = null) {
    try {
        $pdo = getDB();
        
        // Calcula totais
        $where = [];
        $params = [];
        
        if ($usuario_id) {
            $where[] = "usuario_id = ?";
            $params[] = $usuario_id;
        } else if ($colaborador_id) {
            $where[] = "colaborador_id = ?";
            $params[] = $colaborador_id;
        } else {
            return false;
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Total geral
        $stmt = $pdo->prepare("SELECT SUM(pontos) as total FROM pontos_historico WHERE $where_sql");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Total do mês
        $stmt = $pdo->prepare("SELECT SUM(pontos) as total FROM pontos_historico WHERE $where_sql AND MONTH(data_registro) = MONTH(CURDATE()) AND YEAR(data_registro) = YEAR(CURDATE())");
        $stmt->execute($params);
        $mes = $stmt->fetch()['total'] ?? 0;
        
        // Total da semana
        $stmt = $pdo->prepare("SELECT SUM(pontos) as total FROM pontos_historico WHERE $where_sql AND YEARWEEK(data_registro) = YEARWEEK(CURDATE())");
        $stmt->execute($params);
        $semana = $stmt->fetch()['total'] ?? 0;
        
        // Total do dia
        $stmt = $pdo->prepare("SELECT SUM(pontos) as total FROM pontos_historico WHERE $where_sql AND data_registro = CURDATE()");
        $stmt->execute($params);
        $dia = $stmt->fetch()['total'] ?? 0;
        
        // Insere ou atualiza registro
        if ($usuario_id) {
            $stmt = $pdo->prepare("
                INSERT INTO pontos_total (usuario_id, pontos_totais, pontos_mes, pontos_semana, pontos_dia)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    pontos_totais = VALUES(pontos_totais),
                    pontos_mes = VALUES(pontos_mes),
                    pontos_semana = VALUES(pontos_semana),
                    pontos_dia = VALUES(pontos_dia)
            ");
            $stmt->execute([$usuario_id, $total, $mes, $semana, $dia]);
        } else if ($colaborador_id) {
            $stmt = $pdo->prepare("
                INSERT INTO pontos_total (colaborador_id, pontos_totais, pontos_mes, pontos_semana, pontos_dia)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    pontos_totais = VALUES(pontos_totais),
                    pontos_mes = VALUES(pontos_mes),
                    pontos_semana = VALUES(pontos_semana),
                    pontos_dia = VALUES(pontos_dia)
            ");
            $stmt->execute([$colaborador_id, $total, $mes, $semana, $dia]);
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erro ao atualizar total de pontos: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém pontos de um usuário/colaborador
 */
function obter_pontos($usuario_id = null, $colaborador_id = null) {
    try {
        $pdo = getDB();
        
        if ($usuario_id) {
            $stmt = $pdo->prepare("SELECT * FROM pontos_total WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
        } else if ($colaborador_id) {
            $stmt = $pdo->prepare("SELECT * FROM pontos_total WHERE colaborador_id = ?");
            $stmt->execute([$colaborador_id]);
        } else {
            return [
                'pontos_totais' => 0,
                'pontos_mes' => 0,
                'pontos_semana' => 0,
                'pontos_dia' => 0
            ];
        }
        
        $pontos = $stmt->fetch();
        
        if (!$pontos) {
            return [
                'pontos_totais' => 0,
                'pontos_mes' => 0,
                'pontos_semana' => 0,
                'pontos_dia' => 0
            ];
        }
        
        return $pontos;
        
    } catch (PDOException $e) {
        error_log("Erro ao obter pontos: " . $e->getMessage());
        return [
            'pontos_totais' => 0,
            'pontos_mes' => 0,
            'pontos_semana' => 0,
            'pontos_dia' => 0
        ];
    }
}

/**
 * Adiciona pontos manualmente (para uso administrativo)
 * 
 * @param int $colaborador_id ID do colaborador
 * @param int $pontos Quantidade de pontos (positivo para adicionar, negativo para remover)
 * @param string $descricao Descrição/motivo da alteração
 * @param int $usuario_admin_id ID do usuário admin que fez a alteração
 * @return array Array com success e message
 */
function adicionar_pontos_manual($colaborador_id, $pontos, $descricao, $usuario_admin_id) {
    try {
        $pdo = getDB();
        
        if (empty($pontos) || $pontos == 0) {
            return ['success' => false, 'message' => 'Quantidade de pontos inválida'];
        }
        
        if (empty($descricao)) {
            return ['success' => false, 'message' => 'Descrição é obrigatória'];
        }
        
        $data_registro = date('Y-m-d');
        $acao = $pontos > 0 ? 'ajuste_manual_credito' : 'ajuste_manual_debito';
        
        // Insere no histórico
        $stmt = $pdo->prepare("
            INSERT INTO pontos_historico (usuario_id, colaborador_id, acao, pontos, referencia_id, referencia_tipo, data_registro)
            VALUES (NULL, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$colaborador_id, $acao, $pontos, $usuario_admin_id, 'ajuste_manual:' . $descricao, $data_registro]);
        
        // Atualiza total de pontos
        atualizar_total_pontos(null, $colaborador_id);
        
        // Busca novo total
        $novos_pontos = obter_pontos(null, $colaborador_id);
        
        return [
            'success' => true, 
            'message' => $pontos > 0 ? "Adicionados $pontos pontos com sucesso!" : "Removidos " . abs($pontos) . " pontos com sucesso!",
            'pontos_totais' => $novos_pontos['pontos_totais']
        ];
        
    } catch (PDOException $e) {
        error_log("Erro ao adicionar pontos manualmente: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao processar a operação'];
    }
}

/**
 * Adiciona pontos para conclusão de curso (com valor específico do curso)
 * 
 * @param int $colaborador_id ID do colaborador
 * @param int $curso_id ID do curso
 * @param int $pontos_recompensa Quantidade de pontos configurada no curso
 * @return bool True se pontos foram adicionados
 */
function adicionar_pontos_curso($colaborador_id, $curso_id, $pontos_recompensa) {
    try {
        if ($pontos_recompensa <= 0) {
            return false;
        }
        
        $pdo = getDB();
        $data_registro = date('Y-m-d');
        
        // Verifica se já ganhou pontos por este curso
        $stmt = $pdo->prepare("
            SELECT id FROM pontos_historico 
            WHERE colaborador_id = ? AND acao = 'concluir_curso' AND referencia_id = ? AND referencia_tipo = 'curso'
        ");
        $stmt->execute([$colaborador_id, $curso_id]);
        if ($stmt->fetch()) {
            return false; // Já ganhou pontos por este curso
        }
        
        // Insere no histórico
        $stmt = $pdo->prepare("
            INSERT INTO pontos_historico (usuario_id, colaborador_id, acao, pontos, referencia_id, referencia_tipo, data_registro)
            VALUES (NULL, ?, 'concluir_curso', ?, ?, 'curso', ?)
        ");
        $stmt->execute([$colaborador_id, $pontos_recompensa, $curso_id, $data_registro]);
        
        // Atualiza total de pontos
        atualizar_total_pontos(null, $colaborador_id);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erro ao adicionar pontos de curso: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém histórico de pontos de um colaborador
 */
function obter_historico_pontos($colaborador_id, $limite = 30) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT ph.*, pc.descricao as acao_descricao
            FROM pontos_historico ph
            LEFT JOIN pontos_config pc ON ph.acao = pc.acao
            WHERE ph.colaborador_id = ?
            ORDER BY ph.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$colaborador_id, $limite]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Erro ao obter histórico de pontos: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém ranking de pontos
 */
function obter_ranking_pontos($periodo = 'mes', $limite = 10) {
    try {
        $pdo = getDB();
        
        $campo = 'pontos_mes';
        if ($periodo === 'semana') {
            $campo = 'pontos_semana';
        } else if ($periodo === 'dia') {
            $campo = 'pontos_dia';
        } else if ($periodo === 'total') {
            $campo = 'pontos_totais';
        }
        
        $sql = "
            SELECT 
                pt.*,
                COALESCE(u.nome, c.nome_completo) as nome,
                COALESCE(u.email, c.email) as email,
                c.foto,
                CASE 
                    WHEN pt.usuario_id IS NOT NULL THEN 'usuario'
                    ELSE 'colaborador'
                END as tipo
            FROM pontos_total pt
            LEFT JOIN usuarios u ON pt.usuario_id = u.id
            LEFT JOIN colaboradores c ON pt.colaborador_id = c.id OR (pt.usuario_id = u.id AND u.colaborador_id = c.id)
            WHERE pt.$campo > 0
            ORDER BY pt.$campo DESC
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limite]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Erro ao obter ranking: " . $e->getMessage());
        return [];
    }
}

// =============================================
// FUNÇÕES PARA SALDO EM R$ (DINHEIRO/CRÉDITOS)
// =============================================

/**
 * Obtém saldo em R$ de um colaborador
 * 
 * @param int $colaborador_id ID do colaborador
 * @return float Saldo em R$
 */
function obter_saldo_dinheiro($colaborador_id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COALESCE(saldo_dinheiro, 0) as saldo FROM pontos_total WHERE colaborador_id = ?");
        $stmt->execute([$colaborador_id]);
        $result = $stmt->fetch();
        return $result ? floatval($result['saldo']) : 0.00;
    } catch (PDOException $e) {
        error_log("Erro ao obter saldo em dinheiro: " . $e->getMessage());
        return 0.00;
    }
}

/**
 * Adiciona/Remove saldo em R$ manualmente (para uso administrativo)
 * 
 * @param int $colaborador_id ID do colaborador
 * @param float $valor Valor em R$ (positivo para adicionar, negativo para remover)
 * @param string $descricao Descrição/motivo da alteração
 * @param int $usuario_admin_id ID do usuário admin que fez a alteração
 * @param string|null $referencia_tipo Tipo de referência (opcional)
 * @param int|null $referencia_id ID de referência (opcional)
 * @return array Array com success e message
 */
function gerenciar_saldo_dinheiro($colaborador_id, $valor, $descricao, $usuario_admin_id, $referencia_tipo = null, $referencia_id = null) {
    try {
        $pdo = getDB();
        
        $valor = floatval($valor);
        
        if ($valor == 0) {
            return ['success' => false, 'message' => 'Valor inválido'];
        }
        
        if (empty($descricao)) {
            return ['success' => false, 'message' => 'Descrição é obrigatória'];
        }
        
        // Busca saldo atual
        $saldo_anterior = obter_saldo_dinheiro($colaborador_id);
        $saldo_posterior = $saldo_anterior + $valor;
        
        // Verifica se terá saldo negativo (não permitido para débitos)
        if ($saldo_posterior < 0) {
            return ['success' => false, 'message' => 'Saldo insuficiente. Saldo atual: R$ ' . number_format($saldo_anterior, 2, ',', '.')];
        }
        
        $pdo->beginTransaction();
        
        try {
            // Garante que existe registro na tabela pontos_total
            $stmt = $pdo->prepare("
                INSERT INTO pontos_total (colaborador_id, saldo_dinheiro)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE saldo_dinheiro = ?
            ");
            $stmt->execute([$colaborador_id, $saldo_posterior, $saldo_posterior]);
            
            // Registra no histórico
            $tipo = $valor > 0 ? 'credito' : 'debito';
            $stmt = $pdo->prepare("
                INSERT INTO saldo_dinheiro_historico 
                (colaborador_id, tipo, valor, saldo_anterior, saldo_posterior, descricao, referencia_tipo, referencia_id, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $colaborador_id, 
                $tipo, 
                $valor, 
                $saldo_anterior, 
                $saldo_posterior, 
                $descricao, 
                $referencia_tipo, 
                $referencia_id, 
                $usuario_admin_id
            ]);
            
            $pdo->commit();
            
            $mensagem = $valor > 0 
                ? 'Crédito de R$ ' . number_format($valor, 2, ',', '.') . ' adicionado com sucesso!'
                : 'Débito de R$ ' . number_format(abs($valor), 2, ',', '.') . ' realizado com sucesso!';
            
            return [
                'success' => true,
                'message' => $mensagem,
                'saldo_anterior' => $saldo_anterior,
                'saldo_atual' => $saldo_posterior
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao gerenciar saldo em dinheiro: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao processar a operação'];
    }
}

/**
 * Obtém histórico de movimentações de saldo em R$
 * 
 * @param int $colaborador_id ID do colaborador
 * @param int $limite Limite de registros
 * @return array Histórico de movimentações
 */
function obter_historico_saldo_dinheiro($colaborador_id, $limite = 50) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT h.*, u.nome as usuario_nome
            FROM saldo_dinheiro_historico h
            LEFT JOIN usuarios u ON h.usuario_id = u.id
            WHERE h.colaborador_id = ?
            ORDER BY h.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$colaborador_id, $limite]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Erro ao obter histórico de saldo em dinheiro: " . $e->getMessage());
        return [];
    }
}

/**
 * Debita saldo em R$ para resgate na loja
 * 
 * @param int $colaborador_id ID do colaborador
 * @param float $valor Valor a debitar
 * @param int $resgate_id ID do resgate na loja
 * @return array Array com success e message
 */
function debitar_saldo_loja($colaborador_id, $valor, $resgate_id) {
    return gerenciar_saldo_dinheiro(
        $colaborador_id,
        -abs($valor),
        'Resgate na Loja de Pontos',
        null, // Sistema automático
        'loja_resgate',
        $resgate_id
    );
}

/**
 * Estorna saldo em R$ de resgate cancelado/rejeitado
 * 
 * @param int $colaborador_id ID do colaborador
 * @param float $valor Valor a estornar
 * @param int $resgate_id ID do resgate na loja
 * @param int $usuario_id ID do usuário que cancelou
 * @return array Array com success e message
 */
function estornar_saldo_loja($colaborador_id, $valor, $resgate_id, $usuario_id) {
    return gerenciar_saldo_dinheiro(
        $colaborador_id,
        abs($valor),
        'Estorno de resgate cancelado/rejeitado',
        $usuario_id,
        'loja_resgate_estorno',
        $resgate_id
    );
}

