<?php
/**
 * API para gerenciar pontos manualmente (ADMIN/GESTOR/RH)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/pontuacao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Verifica permissão (ADMIN, GESTOR ou RH)
$usuario = $_SESSION['usuario'];
$roles_permitidos = ['ADMIN', 'GESTOR', 'RH'];
if (!in_array($usuario['role'], $roles_permitidos)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    
    $action = $_POST['action'] ?? '';
    $colaborador_id = intval($_POST['colaborador_id'] ?? 0);
    
    if (empty($colaborador_id)) {
        throw new Exception('ID do colaborador é obrigatório');
    }
    
    // Verifica se o colaborador existe
    $stmt = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        throw new Exception('Colaborador não encontrado');
    }
    
    switch ($action) {
        case 'adicionar':
            $pontos = intval($_POST['pontos'] ?? 0);
            $descricao = trim($_POST['descricao'] ?? '');
            
            if ($pontos <= 0) {
                throw new Exception('Quantidade de pontos deve ser maior que zero');
            }
            
            if (empty($descricao)) {
                throw new Exception('Descrição/motivo é obrigatório');
            }
            
            $resultado = adicionar_pontos_manual($colaborador_id, $pontos, $descricao, $usuario['id']);
            echo json_encode($resultado);
            break;
            
        case 'remover':
            $pontos = intval($_POST['pontos'] ?? 0);
            $descricao = trim($_POST['descricao'] ?? '');
            
            if ($pontos <= 0) {
                throw new Exception('Quantidade de pontos deve ser maior que zero');
            }
            
            if (empty($descricao)) {
                throw new Exception('Descrição/motivo é obrigatório');
            }
            
            // Passa como negativo para remover
            $resultado = adicionar_pontos_manual($colaborador_id, -$pontos, $descricao, $usuario['id']);
            echo json_encode($resultado);
            break;
            
        case 'historico':
            $limite = intval($_POST['limite'] ?? 30);
            $historico = obter_historico_pontos($colaborador_id, $limite);
            $pontos_atuais = obter_pontos(null, $colaborador_id);
            
            echo json_encode([
                'success' => true,
                'historico' => $historico,
                'pontos' => $pontos_atuais
            ]);
            break;
            
        case 'saldo':
            $pontos_atuais = obter_pontos(null, $colaborador_id);
            echo json_encode([
                'success' => true,
                'pontos' => $pontos_atuais
            ]);
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
