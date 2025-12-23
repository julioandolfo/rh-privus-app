<?php
/**
 * API para buscar dados de uma entrevista
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $entrevista_id = (int)($_GET['id'] ?? 0);
    
    if (empty($entrevista_id)) {
        throw new Exception('ID da entrevista é obrigatório');
    }
    
    $stmt = $pdo->prepare("
        SELECT e.*,
               COALESCE(c.nome_completo, e.candidato_nome_manual) as candidato_nome,
               COALESCE(c.email, e.candidato_email_manual) as candidato_email,
               COALESCE(c.telefone, e.candidato_telefone_manual) as candidato_telefone,
               COALESCE(v.titulo, vm.titulo) as vaga_titulo,
               COALESCE(v.id, vm.id) as vaga_id,
               COALESCE(v.empresa_id, vm.empresa_id) as empresa_id,
               u.nome as entrevistador_nome,
               CASE WHEN e.candidatura_id IS NULL THEN 1 ELSE 0 END as is_manual
        FROM entrevistas e
        LEFT JOIN candidaturas cand ON e.candidatura_id = cand.id
        LEFT JOIN candidatos c ON cand.candidato_id = c.id
        LEFT JOIN vagas v ON cand.vaga_id = v.id
        LEFT JOIN vagas vm ON e.vaga_id_manual = vm.id
        LEFT JOIN usuarios u ON e.entrevistador_id = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$entrevista_id]);
    $entrevista = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entrevista) {
        throw new Exception('Entrevista não encontrada');
    }
    
    // Verifica permissão
    if ($usuario['role'] === 'RH') {
        if ($entrevista['empresa_id'] && isset($usuario['empresas_ids']) && !in_array($entrevista['empresa_id'], $usuario['empresas_ids'])) {
            throw new Exception('Você não tem permissão para ver esta entrevista');
        }
    } elseif ($usuario['role'] === 'GESTOR') {
        if ($entrevista['entrevistador_id'] != $usuario['id']) {
            throw new Exception('Você só pode ver suas próprias entrevistas');
        }
    }
    
    // Formata data para input datetime-local
    $entrevista['data_agendada_formatted'] = date('Y-m-d\TH:i', strtotime($entrevista['data_agendada']));
    
    echo json_encode([
        'success' => true,
        'entrevista' => $entrevista
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

