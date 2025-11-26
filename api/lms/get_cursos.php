<?php
/**
 * API: Buscar Cursos Disponíveis
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lms_functions.php';

require_login();

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    if (!$colaborador_id) {
        throw new Exception('Colaborador não encontrado');
    }
    
    $filtros = [
        'categoria_id' => $_GET['categoria_id'] ?? null,
        'busca' => $_GET['busca'] ?? null,
        'nivel' => $_GET['nivel'] ?? null
    ];
    
    $cursos = buscar_cursos_disponiveis($colaborador_id, $filtros);
    
    // Adiciona progresso para cada curso
    foreach ($cursos as &$curso) {
        $progresso = buscar_progresso_curso($colaborador_id, $curso['id']);
        $curso['progresso'] = $progresso;
        $curso['percentual_conclusao'] = calcular_percentual_curso($colaborador_id, $curso['id']);
    }
    
    echo json_encode([
        'success' => true,
        'cursos' => $cursos
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

