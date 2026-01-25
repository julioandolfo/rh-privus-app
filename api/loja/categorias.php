<?php
/**
 * API de categorias da loja
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/loja_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$pdo = getDB();

try {
    $action = $_REQUEST['action'] ?? 'listar';
    
    switch ($action) {
        case 'listar':
            $apenas_ativas = !isset($_GET['todas']);
            $categorias = loja_get_categorias($apenas_ativas);
            echo json_encode(['success' => true, 'categorias' => $categorias]);
            break;
            
        case 'salvar':
            if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
                throw new Exception('Sem permissão');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método inválido');
            }
            
            $id = intval($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $icone = trim($_POST['icone'] ?? 'ki-category');
            $cor = trim($_POST['cor'] ?? 'primary');
            $ordem = intval($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) && $_POST['ativo'] == '1' ? 1 : 0;
            
            if (empty($nome)) {
                throw new Exception('Nome é obrigatório');
            }
            
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE loja_categorias 
                    SET nome = ?, descricao = ?, icone = ?, cor = ?, ordem = ?, ativo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $descricao, $icone, $cor, $ordem, $ativo, $id]);
                echo json_encode(['success' => true, 'message' => 'Categoria atualizada!']);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO loja_categorias (nome, descricao, icone, cor, ordem, ativo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $icone, $cor, $ordem, $ativo]);
                echo json_encode(['success' => true, 'message' => 'Categoria criada!', 'id' => $pdo->lastInsertId()]);
            }
            break;
            
        case 'excluir':
            if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
                throw new Exception('Sem permissão');
            }
            
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID inválido');
            }
            
            // Verifica se tem produtos
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM loja_produtos WHERE categoria_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()['total'] > 0) {
                throw new Exception('Não é possível excluir categoria com produtos vinculados');
            }
            
            $stmt = $pdo->prepare("DELETE FROM loja_categorias WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Categoria excluída!']);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
