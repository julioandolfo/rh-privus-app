<?php
/**
 * API para gerar e enviar distrato manualmente para um colaborador desligado ou ativo.
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
    $data_distrato = $_POST['data_distrato'] ?? null;
    $motivo_distrato = $_POST['motivo_distrato'] ?? null;
    
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
    
    // Verifica permissão de acesso ao colaborador
    if (!can_access_colaborador($colaborador_id)) {
        throw new Exception('Você não tem permissão para acessar este colaborador.');
    }

    $demissao_id = null;
    $dados_avulsos = null;

    if ($colaborador['status'] === 'desligado') {
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

        if ($demissao) {
            $demissao_id = (int)$demissao['id'];
        } else {
            // Permite gerar como avulso mesmo estando desligado se não houver demissão registrada
            if (empty($data_distrato)) {
                $data_distrato = date('Y-m-d');
            }
        }
    } else {
        // Se ativo ou pausado e quer gerar distrato (ex: renovação)
        if (empty($data_distrato)) {
            throw new Exception('É obrigatório informar a data do distrato ao gerar de forma avulsa.');
        }
    }

    if (!$demissao_id) {
        // É um distrato avulso
        $dados_avulsos = [
            'data_demissao' => $data_distrato,
            'tipo_demissao' => 'outro',
            'motivo' => $motivo_distrato ?: 'Renovação/Alteração contratual'
        ];
    }

    // Chama função que também enviará e verificará duplicidade
    $res_distrato = criar_contrato_distrato_automatico($pdo, (int)$colaborador_id, $demissao_id, $usuario, $dados_avulsos);

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
