<?php
/**
 * API para salvar/criar manual individual
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];

// Apenas ADMIN, RH e GESTOR podem criar/editar
if (!in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para criar/editar manuais']);
    exit;
}

$manual_id = $_POST['manual_id'] ?? 0;
$titulo = trim($_POST['titulo'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$conteudo = $_POST['conteudo'] ?? '';
$status = $_POST['status'] ?? 'ativo';
$colaboradores_ids = $_POST['colaboradores_ids'] ?? [];

if (empty($titulo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Título é obrigatório']);
    exit;
}

if (empty($conteudo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Conteúdo é obrigatório']);
    exit;
}

if (!in_array($status, ['ativo', 'inativo'])) {
    $status = 'ativo';
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();
    
    if ($manual_id > 0) {
        // Edição
        $stmt = $pdo->prepare("
            UPDATE manuais_individuais 
            SET titulo = ?, descricao = ?, conteudo = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$titulo, $descricao, $conteudo, $status, $manual_id]);
        
        // Remove relacionamentos antigos
        $stmt = $pdo->prepare("DELETE FROM manuais_individuais_colaboradores WHERE manual_id = ?");
        $stmt->execute([$manual_id]);
    } else {
        // Criação
        $stmt = $pdo->prepare("
            INSERT INTO manuais_individuais (titulo, descricao, conteudo, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$titulo, $descricao, $conteudo, $status, $usuario['id']]);
        $manual_id = $pdo->lastInsertId();
    }
    
    // Adiciona colaboradores
    if (!empty($colaboradores_ids) && is_array($colaboradores_ids)) {
        $stmt = $pdo->prepare("
            INSERT INTO manuais_individuais_colaboradores (manual_id, colaborador_id, created_at)
            VALUES (?, ?, NOW())
        ");
        
        foreach ($colaboradores_ids as $colab_id) {
            if (!empty($colab_id)) {
                // Verifica se o usuário tem acesso ao colaborador
                if (can_access_colaborador($colab_id)) {
                    try {
                        $stmt->execute([$manual_id, $colab_id]);
                    } catch (PDOException $e) {
                        // Ignora duplicatas
                        if ($e->getCode() != 23000) {
                            throw $e;
                        }
                    }
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $manual_id > 0 ? 'Manual atualizado com sucesso' : 'Manual criado com sucesso',
        'manual_id' => $manual_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar manual: ' . $e->getMessage()
    ]);
}
