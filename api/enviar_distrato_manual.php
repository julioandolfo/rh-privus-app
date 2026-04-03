<?php
/**
 * API para gerar e enviar distrato manualmente para um colaborador desligado.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/distrato_contrato_auto.php';

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
        throw new Exception('Você não tem permissão para usar esta função.');
    }
    
    $colaborador_id = $_POST['colaborador_id'] ?? null;
    
    if (empty($colaborador_id)) {
        throw new Exception('Colaborador não informado.');
    }
    
    // Verifica se colaborador existe
    $stmt = $pdo->prepare("SELECT id, status FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        throw new Exception('Colaborador não encontrado.');
    }
    
    if ($colaborador['status'] !== 'desligado') {
        throw new Exception('O colaborador precisa estar desligado para gerar um distrato.');
    }
    
    // Verifica permissão de acesso ao colaborador
    if (!can_access_colaborador($colaborador_id)) {
        throw new Exception('Você não tem permissão para acessar este colaborador.');
    }

    // Busca a demissão deste colaborador
    $stmt = $pdo->prepare("
        SELECT id 
        FROM demissoes 
        WHERE colaborador_id = ? 
        ORDER BY data_demissao DESC, id DESC 
        LIMIT 1
    ");
    $stmt->execute([$colaborador_id]);
    $demissao = $stmt->fetch();

    if (!$demissao) {
        throw new Exception('Nenhum registro de demissão encontrado para este colaborador. Exclua o colaborador e registre a demissão novamente.');
    }

    $demissao_id = (int)$demissao['id'];

    // Chama função que também enviará e verificará duplicidade
    $res_distrato = criar_contrato_distrato_automatico($pdo, (int)$colaborador_id, $demissao_id, $usuario);

    if (empty($res_distrato['created'])) {
        throw new Exception($res_distrato['message'] ?? 'Falha desconhecida ao criar distrato.');
    }

    $msg_ok = 'Distrato processado com sucesso!';
    if (!empty($res_distrato['message'])) {
        $msg_ok = $res_distrato['message'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => $msg_ok,
        'contrato_distrato_id' => $res_distrato['contrato_id'],
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
