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
    
    $candidatura_id = trim($_POST['candidatura_id'] ?? '');
    $coluna_codigo = trim($_POST['coluna_codigo'] ?? '');
    $is_entrevista = !empty($_POST['is_entrevista']) && $_POST['is_entrevista'] === '1';
    
    if (empty($candidatura_id) || empty($coluna_codigo)) {
        throw new Exception('Candidatura/Entrevista e coluna são obrigatórios');
    }
    
    // Verifica se coluna existe
    $stmt = $pdo->prepare("SELECT id, nome FROM kanban_colunas WHERE codigo = ? AND ativo = 1");
    $stmt->execute([$coluna_codigo]);
    $coluna = $stmt->fetch();
    
    if (!$coluna) {
        throw new Exception('Coluna inválida');
    }
    
    if ($is_entrevista) {
        // É uma entrevista manual
        $entrevista_id = (int)str_replace('entrevista_', '', $candidatura_id);
        
        // Busca entrevista
        $stmt = $pdo->prepare("
            SELECT e.*, v.empresa_id
            FROM entrevistas e
            LEFT JOIN vagas v ON e.vaga_id_manual = v.id
            WHERE e.id = ? AND e.candidatura_id IS NULL
        ");
        $stmt->execute([$entrevista_id]);
        $entrevista = $stmt->fetch();
        
        if (!$entrevista) {
            throw new Exception('Entrevista não encontrada');
        }
        
        // Verifica permissão
        if ($usuario['role'] === 'RH' && $entrevista['empresa_id'] && !can_access_empresa($entrevista['empresa_id'])) {
            throw new Exception('Você não tem permissão para esta entrevista');
        }
        
        $coluna_anterior = $entrevista['coluna_kanban'];
        
        // Atualiza entrevista
        $stmt = $pdo->prepare("
            UPDATE entrevistas 
            SET coluna_kanban = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$coluna_codigo, $entrevista_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Entrevista movida com sucesso'
        ]);
    } else {
        // É uma candidatura normal
        $candidatura_id_int = (int)$candidatura_id;
        
        // Busca candidatura
        $stmt = $pdo->prepare("
            SELECT c.*, v.empresa_id
            FROM candidaturas c
            INNER JOIN vagas v ON c.vaga_id = v.id
            WHERE c.id = ?
        ");
        $stmt->execute([$candidatura_id_int]);
        $candidatura = $stmt->fetch();
        
        if (!$candidatura) {
            throw new Exception('Candidatura não encontrada');
        }
        
        // Verifica permissão
        if ($usuario['role'] === 'RH' && !can_access_empresa($candidatura['empresa_id'])) {
            throw new Exception('Você não tem permissão para esta candidatura');
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
        $stmt->execute([$coluna_codigo, $etapa_id, $candidatura_id_int]);
        
        // Registra histórico
        registrar_historico_candidatura(
            $candidatura_id_int,
            'moved_kanban',
            $usuario['id'],
            'coluna_kanban',
            $coluna_anterior,
            $coluna_codigo,
            "Movido de '{$coluna_anterior}' para '{$coluna_codigo}'"
        );
        
        // Executa automações da nova coluna
        executar_automatizacoes_kanban($candidatura_id_int, $coluna_codigo);
        
        echo json_encode([
            'success' => true,
            'message' => 'Candidatura movida com sucesso'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

