<?php
/**
 * API: Salvar Automação
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
    
    $automacao_id = !empty($_POST['automacao_id']) ? (int)$_POST['automacao_id'] : null;
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    
    if (empty($nome) || empty($tipo)) {
        throw new Exception('Nome e tipo são obrigatórios');
    }
    
    // Processa JSONs
    $condicoes = null;
    if (!empty($_POST['condicoes'])) {
        $condicoes = json_encode(json_decode($_POST['condicoes'], true));
    }
    
    $configuracao = null;
    if (!empty($_POST['configuracao'])) {
        $configuracao = json_encode(json_decode($_POST['configuracao'], true));
    }
    
    $coluna_id = !empty($_POST['coluna_id']) ? (int)$_POST['coluna_id'] : null;
    $etapa_id = !empty($_POST['etapa_id']) ? (int)$_POST['etapa_id'] : null;
    
    if ($automacao_id) {
        // Atualiza
        $stmt = $pdo->prepare("
            UPDATE kanban_automatizacoes SET
            nome = ?, tipo = ?, coluna_id = ?, etapa_id = ?,
            condicoes = ?, configuracao = ?, ativo = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nome,
            $tipo,
            $coluna_id,
            $etapa_id,
            $condicoes,
            $configuracao,
            isset($_POST['ativo']) ? 1 : 0,
            $automacao_id
        ]);
    } else {
        // Cria nova
        $stmt = $pdo->prepare("
            INSERT INTO kanban_automatizacoes 
            (nome, tipo, coluna_id, etapa_id, condicoes, configuracao, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nome,
            $tipo,
            $coluna_id,
            $etapa_id,
            $condicoes,
            $configuracao,
            isset($_POST['ativo']) ? 1 : 0
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Automação salva com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

