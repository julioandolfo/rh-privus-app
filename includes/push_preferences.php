<?php
/**
 * Funções Helper para Preferências de Notificações Push
 */

require_once __DIR__ . '/functions.php';

/**
 * Verifica se o usuário/colaborador tem notificações push ativas para um tipo específico
 * 
 * @param int|null $usuario_id ID do usuário
 * @param int|null $colaborador_id ID do colaborador
 * @param string $tipo_notificacao Tipo da notificação (ex: 'feedback_recebido')
 * @return bool True se ativo, False se desativado ou não configurado (padrão: ativo)
 */
function verificar_preferencia_push($usuario_id, $colaborador_id, $tipo_notificacao) {
    try {
        $pdo = getDB();
        
        // Se não tem nenhum ID, retorna false
        if (!$usuario_id && !$colaborador_id) {
            return false;
        }
        
        // Busca preferência específica
        $where_conditions = [];
        $params = [$tipo_notificacao];
        
        if ($usuario_id) {
            $where_conditions[] = "usuario_id = ?";
            $params[] = $usuario_id;
        } else {
            $where_conditions[] = "usuario_id IS NULL";
        }
        
        if ($colaborador_id) {
            $where_conditions[] = "colaborador_id = ?";
            $params[] = $colaborador_id;
        } else {
            $where_conditions[] = "colaborador_id IS NULL";
        }
        
        $where_sql = implode(" AND ", $where_conditions);
        
        $stmt = $pdo->prepare("
            SELECT ativo 
            FROM push_notification_preferences 
            WHERE tipo_notificacao = ? 
            AND $where_sql
            LIMIT 1
        ");
        $stmt->execute($params);
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se não encontrou preferência, retorna true (padrão: ativo)
        if (!$pref) {
            return true;
        }
        
        return (bool)$pref['ativo'];
        
    } catch (Exception $e) {
        // Em caso de erro, retorna true (padrão: ativo) para não bloquear notificações
        error_log("Erro ao verificar preferência push: " . $e->getMessage());
        return true;
    }
}

