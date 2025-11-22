<?php
/**
 * API para Listar Reuniões 1:1
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $lider_id = !empty($_GET['lider_id']) ? (int)$_GET['lider_id'] : null;
    $liderado_id = !empty($_GET['liderado_id']) ? (int)$_GET['liderado_id'] : null;
    $status = $_GET['status'] ?? null;
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim = $_GET['data_fim'] ?? null;
    
    $where = ["1=1"];
    $params = [];
    
    if ($lider_id) {
        $where[] = "r.lider_id = ?";
        $params[] = $lider_id;
    }
    
    if ($liderado_id) {
        $where[] = "r.liderado_id = ?";
        $params[] = $liderado_id;
    }
    
    if ($status) {
        $where[] = "r.status = ?";
        $params[] = $status;
    }
    
    if ($data_inicio) {
        $where[] = "r.data_reuniao >= ?";
        $params[] = $data_inicio;
    }
    
    if ($data_fim) {
        $where[] = "r.data_reuniao <= ?";
        $params[] = $data_fim;
    }
    
    // Se for GESTOR, só vê suas próprias reuniões como líder
    if ($usuario['role'] === 'GESTOR' && !$lider_id) {
        $stmt_colab = $pdo->prepare("SELECT colaborador_id FROM usuarios WHERE id = ?");
        $stmt_colab->execute([$usuario['id']]);
        $user_colab = $stmt_colab->fetch();
        if ($user_colab && $user_colab['colaborador_id']) {
            $where[] = "r.lider_id = ?";
            $params[] = $user_colab['colaborador_id'];
        }
    }
    
    $sql = "
        SELECT r.*,
               cl.nome_completo as lider_nome,
               cl.foto as lider_foto,
               cd.nome_completo as liderado_nome,
               cd.foto as liderado_foto,
               u.nome as criado_por_nome
        FROM reunioes_1on1 r
        INNER JOIN colaboradores cl ON r.lider_id = cl.id
        INNER JOIN colaboradores cd ON r.liderado_id = cd.id
        LEFT JOIN usuarios u ON r.created_by = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.data_reuniao DESC, r.hora_inicio DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reunioes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $reunioes
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

