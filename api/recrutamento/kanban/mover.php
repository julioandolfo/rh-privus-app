<?php
/**
 * API: Mover Candidatura no Kanban
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/recrutamento_functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $candidatura_id = (int)($_POST['candidatura_id'] ?? 0);
    $coluna_codigo = trim($_POST['coluna_codigo'] ?? '');
    
    if (empty($candidatura_id) || empty($coluna_codigo)) {
        throw new Exception('Candidatura e coluna são obrigatórios');
    }
    
    // Busca candidatura
    $stmt = $pdo->prepare("
        SELECT c.*, v.empresa_id
        FROM candidaturas c
        INNER JOIN vagas v ON c.vaga_id = v.id
        WHERE c.id = ?
    ");
    $stmt->execute([$candidatura_id]);
    $candidatura = $stmt->fetch();
    
    if (!$candidatura) {
        throw new Exception('Candidatura não encontrada');
    }
    
    // Verifica permissão
    if ($usuario['role'] === 'RH' && !can_access_empresa($candidatura['empresa_id'])) {
        throw new Exception('Você não tem permissão para esta candidatura');
    }
    
    // Verifica se coluna existe
    $stmt = $pdo->prepare("SELECT id, nome FROM kanban_colunas WHERE codigo = ? AND ativo = 1");
    $stmt->execute([$coluna_codigo]);
    $coluna = $stmt->fetch();
    
    if (!$coluna) {
        throw new Exception('Coluna inválida');
    }
    
    // Busca etapa correspondente (se houver)
    $etapa_id = null;
    $stmt = $pdo->prepare("SELECT id FROM processo_seletivo_etapas WHERE codigo = ? LIMIT 1");
    $stmt->execute([$coluna_codigo]);
    $etapa = $stmt->fetch();
    if ($etapa) {
        $etapa_id = $etapa['id'];
    }
    
    $coluna_anterior = $candidatura['coluna_kanban'];
    
    // Atualiza candidatura
    $stmt = $pdo->prepare("
        UPDATE candidaturas 
        SET coluna_kanban = ?, etapa_atual_id = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$coluna_codigo, $etapa_id, $candidatura_id]);
    
    // Registra histórico
    registrar_historico_candidatura(
        $candidatura_id,
        'moved_kanban',
        $usuario['id'],
        'coluna_kanban',
        $coluna_anterior,
        $coluna_codigo,
        "Movido de '{$coluna_anterior}' para '{$coluna_codigo}'"
    );
    
    // Executa automações da nova coluna
    executar_automatizacoes_kanban($candidatura_id, $coluna_codigo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Candidatura movida com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

