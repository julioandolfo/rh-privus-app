<?php
/**
 * API: Criar Entrevista
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

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $candidatura_id = (int)($_POST['candidatura_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo = $_POST['tipo'] ?? 'presencial';
    $data_agendada = $_POST['data_agendada'] ?? '';
    
    if (empty($candidatura_id) || empty($titulo) || empty($data_agendada)) {
        throw new Exception('Candidatura, título e data são obrigatórios');
    }
    
    // Verifica permissão
    $stmt = $pdo->prepare("
        SELECT c.*, v.empresa_id
        FROM candidaturas c
        INNER JOIN vagas v ON c.vaga_id = v.id
        WHERE c.id = ?
    ");
    $stmt->execute([$candidatura_id]);
    $candidatura = $stmt->fetch();
    
    if (!$candidatura) {
        throw new Exception('Candidatura não encontrada');
    }
    
    if ($usuario['role'] === 'RH' && !can_access_empresa($candidatura['empresa_id'])) {
        throw new Exception('Sem permissão');
    }
    
    // Converte data
    $data_agendada_formatada = date('Y-m-d H:i:s', strtotime($data_agendada));
    
    // Cria entrevista
    $stmt = $pdo->prepare("
        INSERT INTO entrevistas 
        (candidatura_id, tipo, titulo, descricao, entrevistador_id, data_agendada, duracao_minutos, link_videoconferencia, localizacao, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'agendada')
    ");
    $stmt->execute([
        $candidatura_id,
        $tipo,
        $titulo,
        $_POST['descricao'] ?? null,
        $usuario['id'],
        $data_agendada_formatada,
        !empty($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : 60,
        strpos($_POST['localizacao'] ?? '', 'http') === 0 ? $_POST['localizacao'] : null,
        strpos($_POST['localizacao'] ?? '', 'http') !== 0 ? $_POST['localizacao'] : null
    ]);
    
    $entrevista_id = $pdo->lastInsertId();
    
    // Envia notificação ao candidato
    require_once __DIR__ . '/../../../includes/recrutamento_functions.php';
    $candidatura_completa = buscar_candidaturas_kanban(['vaga_id' => $candidatura['vaga_id']]);
    $candidatura_completa = array_filter($candidatura_completa, function($c) use ($candidatura_id) {
        return $c['id'] == $candidatura_id;
    });
    if (!empty($candidatura_completa)) {
        $cand = reset($candidatura_completa);
        enviar_email_candidato($cand, [
            'template' => 'entrevista_agendada',
            'assunto' => 'Entrevista Agendada - ' . $titulo
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Entrevista agendada com sucesso',
        'entrevista_id' => $entrevista_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

