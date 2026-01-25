<?php
/**
 * API de gerenciamento de resgates
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
        case 'meus':
            // Resgates do colaborador logado
            $colaborador_id = $usuario['colaborador_id'] ?? null;
            if (!$colaborador_id) {
                throw new Exception('Usuário não vinculado a um colaborador');
            }
            
            $resgates = loja_get_resgates_colaborador($colaborador_id);
            echo json_encode(['success' => true, 'resgates' => $resgates]);
            break;
            
        case 'listar':
            // Lista todos (admin)
            if (!in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
                throw new Exception('Sem permissão');
            }
            
            $filtros = [
                'status' => $_GET['status'] ?? null,
                'colaborador_id' => $_GET['colaborador_id'] ?? null,
                'produto_id' => $_GET['produto_id'] ?? null,
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null
            ];
            
            $resgates = loja_get_resgates_admin($filtros);
            $estatisticas = loja_get_estatisticas();
            
            echo json_encode([
                'success' => true,
                'resgates' => $resgates,
                'estatisticas' => $estatisticas
            ]);
            break;
            
        case 'atualizar_status':
            if (!in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
                throw new Exception('Sem permissão');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método inválido');
            }
            
            $resgate_id = intval($_POST['resgate_id'] ?? 0);
            $novo_status = $_POST['status'] ?? '';
            $dados = [
                'motivo' => $_POST['motivo'] ?? null,
                'observacao' => $_POST['observacao'] ?? null,
                'codigo_rastreio' => $_POST['codigo_rastreio'] ?? null
            ];
            
            if ($resgate_id <= 0) {
                throw new Exception('Resgate inválido');
            }
            
            $resultado = loja_atualizar_status_resgate($resgate_id, $novo_status, $usuario['id'], $dados);
            echo json_encode($resultado);
            break;
            
        case 'aprovar':
            if (!in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
                throw new Exception('Sem permissão');
            }
            
            $resgate_id = intval($_POST['resgate_id'] ?? 0);
            $resultado = loja_atualizar_status_resgate($resgate_id, 'aprovado', $usuario['id']);
            echo json_encode($resultado);
            break;
            
        case 'rejeitar':
            if (!in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
                throw new Exception('Sem permissão');
            }
            
            $resgate_id = intval($_POST['resgate_id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            $resultado = loja_atualizar_status_resgate($resgate_id, 'rejeitado', $usuario['id'], ['motivo' => $motivo]);
            echo json_encode($resultado);
            break;
            
        case 'detalhe':
            $resgate_id = intval($_GET['id'] ?? 0);
            
            // Verifica permissão
            $colaborador_id = $usuario['colaborador_id'] ?? null;
            $is_admin = in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR']);
            
            $stmt = $pdo->prepare("
                SELECT r.*, 
                       p.nome as produto_nome, p.imagem as produto_imagem, p.descricao as produto_descricao,
                       col.nome_completo as colaborador_nome, col.foto as colaborador_foto, col.email as colaborador_email,
                       e.nome_fantasia as empresa_nome,
                       ua.nome as aprovador_nome,
                       up.nome as preparador_nome,
                       ue.nome as enviador_nome,
                       uent.nome as entregador_nome
                FROM loja_resgates r
                INNER JOIN loja_produtos p ON r.produto_id = p.id
                INNER JOIN colaboradores col ON r.colaborador_id = col.id
                LEFT JOIN empresas e ON col.empresa_id = e.id
                LEFT JOIN usuarios ua ON r.aprovado_por = ua.id
                LEFT JOIN usuarios up ON r.preparado_por = up.id
                LEFT JOIN usuarios ue ON r.enviado_por = ue.id
                LEFT JOIN usuarios uent ON r.entregue_por = uent.id
                WHERE r.id = ?
            ");
            $stmt->execute([$resgate_id]);
            $resgate = $stmt->fetch();
            
            if (!$resgate) {
                throw new Exception('Resgate não encontrado');
            }
            
            // Verifica se pode ver
            if (!$is_admin && $resgate['colaborador_id'] != $colaborador_id) {
                throw new Exception('Sem permissão');
            }
            
            echo json_encode(['success' => true, 'resgate' => $resgate]);
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
