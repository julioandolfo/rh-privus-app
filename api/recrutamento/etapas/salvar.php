<?php
/**
 * API: Salvar Etapa do Processo
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
    
    $etapa_id = !empty($_POST['etapa_id']) ? (int)$_POST['etapa_id'] : null;
    $nome = trim($_POST['nome'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    
    if (empty($nome) || empty($codigo) || empty($tipo)) {
        throw new Exception('Nome, código e tipo são obrigatórios');
    }
    
    // Verifica se código já existe (exceto se for edição)
    $stmt = $pdo->prepare("SELECT id FROM processo_seletivo_etapas WHERE codigo = ? AND vaga_id IS NULL" . ($etapa_id ? " AND id != ?" : ""));
    if ($etapa_id) {
        $stmt->execute([$codigo, $etapa_id]);
    } else {
        $stmt->execute([$codigo]);
    }
    if ($stmt->fetch()) {
        throw new Exception('Código já existe');
    }
    
    if ($etapa_id) {
        // Atualiza
        $stmt = $pdo->prepare("
            UPDATE processo_seletivo_etapas SET
            nome = ?, codigo = ?, tipo = ?, ordem = ?,
            obrigatoria = ?, permite_pular = ?, tempo_medio_minutos = ?,
            descricao = ?, cor_kanban = ?
            WHERE id = ? AND vaga_id IS NULL
        ");
        $stmt->execute([
            $nome,
            $codigo,
            $tipo,
            (int)($_POST['ordem'] ?? 0),
            isset($_POST['obrigatoria']) ? 1 : 0,
            isset($_POST['permite_pular']) ? 1 : 0,
            !empty($_POST['tempo_medio_minutos']) ? (int)$_POST['tempo_medio_minutos'] : null,
            $_POST['descricao'] ?? null,
            $_POST['cor_kanban'] ?? '#6c757d',
            $etapa_id
        ]);
    } else {
        // Cria nova
        $stmt = $pdo->prepare("
            INSERT INTO processo_seletivo_etapas 
            (nome, codigo, tipo, ordem, obrigatoria, permite_pular, tempo_medio_minutos, descricao, cor_kanban, vaga_id, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 1)
        ");
        $stmt->execute([
            $nome,
            $codigo,
            $tipo,
            (int)($_POST['ordem'] ?? 0),
            isset($_POST['obrigatoria']) ? 1 : 0,
            isset($_POST['permite_pular']) ? 1 : 0,
            !empty($_POST['tempo_medio_minutos']) ? (int)$_POST['tempo_medio_minutos'] : null,
            $_POST['descricao'] ?? null,
            $_POST['cor_kanban'] ?? '#6c757d'
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Etapa salva com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

