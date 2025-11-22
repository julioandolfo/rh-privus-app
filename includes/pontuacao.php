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

