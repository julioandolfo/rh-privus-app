<?php
/**
 * API: Atualizar Formulário de Cultura
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    $formulario_id = (int)($_POST['formulario_id'] ?? 0);
    
    if (!$formulario_id) {
        throw new Exception('Formulário não informado');
    }
    
    // Verifica se o formulário existe
    $stmt = $pdo->prepare("SELECT id FROM formularios_cultura WHERE id = ?");
    $stmt->execute([$formulario_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Formulário não encontrado');
    }
    
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $etapa_id = !empty($_POST['etapa_id']) ? (int)$_POST['etapa_id'] : null;
    $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    // Se etapa_id foi informado, verifica se existe
    if ($etapa_id) {
        $stmt = $pdo->prepare("SELECT id FROM processo_seletivo_etapas WHERE id = ? AND vaga_id IS NULL");
        $stmt->execute([$etapa_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Etapa não encontrada');
        }
    }
    
    $stmt = $pdo->prepare("
        UPDATE formularios_cultura SET
        nome = ?,
        descricao = ?,
        etapa_id = ?,
        ativo = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $nome,
        $descricao ?: null,
        $etapa_id,
        $ativo,
        $formulario_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Formulário atualizado com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

