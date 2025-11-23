<?php
/**
 * API: Excluir Candidatura
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $candidatura_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$candidatura_id) {
        throw new Exception('Candidatura não informada');
    }
    
    // Busca candidatura
    $stmt = $pdo->prepare("
        SELECT c.*, v.empresa_id, v.titulo as vaga_titulo, cand.nome_completo
        FROM candidaturas c
        INNER JOIN vagas v ON c.vaga_id = v.id
        INNER JOIN candidatos cand ON c.candidato_id = cand.id
        WHERE c.id = ?
    ");
    $stmt->execute([$candidatura_id]);
    $candidatura = $stmt->fetch();
    
    if (!$candidatura) {
        throw new Exception('Candidatura não encontrada');
    }
    
    // Verifica permissão de empresa
    if (!can_access_empresa($candidatura['empresa_id'])) {
        throw new Exception('Você não tem permissão para excluir esta candidatura');
    }
    
    // Verifica se há processo de onboarding vinculado
    $stmt = $pdo->prepare("SELECT id FROM onboarding WHERE candidatura_id = ?");
    $stmt->execute([$candidatura_id]);
    $onboarding = $stmt->fetch();
    
    // Verifica se há entrevistas agendadas ou realizadas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM entrevistas WHERE candidatura_id = ?");
    $stmt->execute([$candidatura_id]);
    $total_entrevistas = $stmt->fetchColumn();
    
    // Verifica total de etapas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM candidaturas_etapas WHERE candidatura_id = ?");
    $stmt->execute([$candidatura_id]);
    $total_etapas = $stmt->fetchColumn();
    
    // Inicia transação
    $pdo->beginTransaction();
    
    try {
        // Se houver onboarding, pode querer excluir também ou apenas avisar
        // Por enquanto, vamos apenas excluir a candidatura (o onboarding pode ficar órfão ou ser tratado separadamente)
        
        // Exclui candidatura (CASCADE vai excluir automaticamente):
        // - candidaturas_anexos
        // - candidaturas_etapas
        // - entrevistas
        // - formularios_cultura_respostas
        // - candidaturas_historico
        // - candidaturas_comentarios
        
        $stmt = $pdo->prepare("DELETE FROM candidaturas WHERE id = ?");
        $stmt->execute([$candidatura_id]);
        
        // Se houver onboarding vinculado, pode excluir também ou apenas avisar
        // Por enquanto, vamos deixar o onboarding (pode ser útil manter histórico)
        // Mas podemos excluir se necessário:
        // if ($onboarding) {
        //     $stmt = $pdo->prepare("DELETE FROM onboarding WHERE candidatura_id = ?");
        //     $stmt->execute([$candidatura_id]);
        // }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Candidatura excluída com sucesso',
            'dados_excluidos' => [
                'entrevistas' => $total_entrevistas,
                'etapas' => $total_etapas,
                'tem_onboarding' => !empty($onboarding)
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    // Garante que não há transação aberta
    try {
        $pdo = getDB();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackEx) {
        error_log('Erro ao fazer rollback: ' . $rollbackEx->getMessage());
    }
    
    http_response_code(400);
    error_log('Erro PDO ao excluir candidatura: ' . $e->getMessage());
    
    $mensagem = $e->getMessage();
    if (strpos($mensagem, 'Lock wait timeout') !== false || strpos($mensagem, '1205') !== false) {
        $mensagem = 'O banco de dados está ocupado. Por favor, aguarde alguns segundos e tente novamente.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir candidatura: ' . $mensagem
    ]);
} catch (Exception $e) {
    // Garante que não há transação aberta
    try {
        $pdo = getDB();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackEx) {
        error_log('Erro ao fazer rollback: ' . $rollbackEx->getMessage());
    }
    
    http_response_code(400);
    error_log('Erro ao excluir candidatura: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

