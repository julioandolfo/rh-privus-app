<?php
/**
 * API para registrar demissão de colaborador
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    // Verifica permissão
    if ($usuario['role'] === 'GESTOR') {
        throw new Exception('Você não tem permissão para demitir colaboradores');
    }
    
    $colaborador_id = $_POST['colaborador_id'] ?? null;
    $data_demissao = $_POST['data_demissao'] ?? null;
    $tipo_demissao = $_POST['tipo_demissao'] ?? null;
    $motivo = $_POST['motivo'] ?? null;
    $observacoes = $_POST['observacoes'] ?? null;
    
    if (empty($colaborador_id) || empty($data_demissao) || empty($tipo_demissao)) {
        throw new Exception('Dados obrigatórios não informados');
    }
    
    // Verifica se colaborador existe
    $stmt = $pdo->prepare("SELECT id, status FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        throw new Exception('Colaborador não encontrado');
    }
    
    if ($colaborador['status'] === 'desligado') {
        throw new Exception('Colaborador já está desligado');
    }
    
    // Verifica permissão de acesso ao colaborador
    if (!can_access_colaborador($colaborador_id)) {
        throw new Exception('Você não tem permissão para acessar este colaborador');
    }
    
    // Valida tipo de demissão
    $tipos_validos = ['sem_justa_causa', 'justa_causa', 'pedido_demissao', 'aposentadoria', 'falecimento', 'outro'];
    if (!in_array($tipo_demissao, $tipos_validos)) {
        throw new Exception('Tipo de demissão inválido');
    }
    
    // Inicia transação
    $pdo->beginTransaction();
    
    try {
        // Insere registro de demissão
        $stmt = $pdo->prepare("
            INSERT INTO demissoes (colaborador_id, data_demissao, tipo_demissao, motivo, observacoes, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id,
            $data_demissao,
            $tipo_demissao,
            $motivo,
            $observacoes,
            $usuario['id']
        ]);
        
        // Atualiza status do colaborador
        $stmt = $pdo->prepare("UPDATE colaboradores SET status = 'desligado' WHERE id = ?");
        $stmt->execute([$colaborador_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Demissão registrada com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

