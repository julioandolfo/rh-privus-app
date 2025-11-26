<?php
/**
 * API: Buscar Aulas de um Curso
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lms_functions.php';

require_login();

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $curso_id = (int)($_GET['curso_id'] ?? 0);
    
    if ($curso_id <= 0) {
        throw new Exception('ID do curso inválido');
    }
    
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    if (!$colaborador_id) {
        throw new Exception('Colaborador não encontrado');
    }
    
    // Verifica se pode acessar o curso
    if (!pode_acessar_curso($colaborador_id, $curso_id)) {
        throw new Exception('Você não tem permissão para acessar este curso');
    }
    
    // Busca aulas
    $stmt = $pdo->prepare("
        SELECT a.*,
               pc.id as progresso_id,
               pc.status as status_progresso,
               pc.percentual_conclusao,
               pc.tempo_assistido,
               pc.ultima_posicao,
               pc.data_conclusao,
               pc.bloqueado_por_fraude
        FROM aulas a
        LEFT JOIN progresso_colaborador pc ON pc.aula_id = a.id AND pc.colaborador_id = ?
        WHERE a.curso_id = ? AND a.status = 'publicado'
        ORDER BY a.ordem ASC, a.id ASC
    ");
    $stmt->execute([$colaborador_id, $curso_id]);
    $aulas = $stmt->fetchAll();
    
    // Busca campos personalizados para aulas de texto
    foreach ($aulas as &$aula) {
        if ($aula['tipo_conteudo'] == 'texto') {
            $stmt = $pdo->prepare("
                SELECT * FROM campos_personalizados_aula 
                WHERE aula_id = ? 
                ORDER BY ordem ASC
            ");
            $stmt->execute([$aula['id']]);
            $aula['campos_personalizados'] = $stmt->fetchAll();
        }
    }
    
    echo json_encode([
        'success' => true,
        'aulas' => $aulas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

