<?php
/**
 * API para marcar comunicado como lido
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$comunicado_id = intval($input['comunicado_id'] ?? 0);

if ($comunicado_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do comunicado inválido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Verifica se o comunicado existe
    $stmt = $pdo->prepare("SELECT id FROM comunicados WHERE id = ? AND status = 'publicado'");
    $stmt->execute([$comunicado_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Comunicado não encontrado']);
        exit;
    }
    
    // Verifica se já existe registro de leitura
    $stmt = $pdo->prepare("
        SELECT id FROM comunicados_leitura 
        WHERE comunicado_id = ? 
        AND (usuario_id = ? OR colaborador_id = ?)
    ");
    $stmt->execute([$comunicado_id, $usuario_id, $colaborador_id]);
    $leitura = $stmt->fetch();
    
    $primeira_leitura = false;
    
    if ($leitura) {
        // Verifica se é a primeira vez que está marcando como lido
        $stmt_check = $pdo->prepare("SELECT lido FROM comunicados_leitura WHERE id = ?");
        $stmt_check->execute([$leitura['id']]);
        $leitura_atual = $stmt_check->fetch();
        $primeira_leitura = ($leitura_atual && $leitura_atual['lido'] == 0);
        
        // Atualiza registro existente
        $stmt = $pdo->prepare("
            UPDATE comunicados_leitura 
            SET lido = 1, 
                data_leitura = NOW(),
                data_visualizacao = NOW(),
                vezes_visualizado = vezes_visualizado + 1
            WHERE id = ?
        ");
        $stmt->execute([$leitura['id']]);
    } else {
        // Cria novo registro - é a primeira leitura
        $primeira_leitura = true;
        
        $stmt = $pdo->prepare("
            INSERT INTO comunicados_leitura (comunicado_id, usuario_id, colaborador_id, lido, data_leitura, data_visualizacao, vezes_visualizado)
            VALUES (?, ?, ?, 1, NOW(), NOW(), 1)
        ");
        $stmt->execute([$comunicado_id, $usuario_id, $colaborador_id]);
    }
    
    // Adiciona pontos se for a primeira leitura
    $pontos_ganhos = 0;
    $pontos_totais = 0;
    
    if ($primeira_leitura) {
        require_once __DIR__ . '/../../includes/pontuacao.php';
        $ganhou_pontos = adicionar_pontos('comunicado_lido', $usuario_id, $colaborador_id, $comunicado_id, 'comunicado');
        
        if ($ganhou_pontos) {
            // Busca quantidade de pontos da ação
            $stmt_pontos = $pdo->prepare("SELECT pontos FROM pontos_config WHERE acao = 'comunicado_lido' AND ativo = 1");
            $stmt_pontos->execute();
            $config_pontos = $stmt_pontos->fetch();
            $pontos_ganhos = $config_pontos ? $config_pontos['pontos'] : 0;
            
            // Busca novo total
            $novos_pontos = obter_pontos($usuario_id, $colaborador_id);
            $pontos_totais = $novos_pontos['pontos_totais'] ?? 0;
        }
    }
    
    $response = ['success' => true, 'message' => 'Comunicado marcado como lido'];
    
    if ($pontos_ganhos > 0) {
        $response['pontos_ganhos'] = $pontos_ganhos;
        $response['pontos_totais'] = $pontos_totais;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao marcar como lido: ' . $e->getMessage()]);
}

