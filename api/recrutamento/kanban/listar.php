<?php
/**
 * API: Listar Candidaturas para Kanban
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/recrutamento_functions.php';

require_login();

try {
    $filtros = [
        'vaga_id' => !empty($_GET['vaga_id']) ? (int)$_GET['vaga_id'] : null,
        'coluna' => !empty($_GET['coluna']) ? $_GET['coluna'] : null,
        'recrutador_id' => !empty($_GET['recrutador_id']) ? (int)$_GET['recrutador_id'] : null
    ];
    
    $candidaturas = buscar_candidaturas_kanban($filtros);
    $colunas = buscar_colunas_kanban();
    
    // Organiza por coluna
    $kanban_data = [];
    foreach ($colunas as $coluna) {
        $kanban_data[$coluna['codigo']] = [
            'coluna' => $coluna,
            'candidaturas' => []
        ];
    }
    
    foreach ($candidaturas as $candidatura) {
        $coluna_codigo = $candidatura['coluna_kanban'] ?? 'novos_candidatos';
        if (isset($kanban_data[$coluna_codigo])) {
            $kanban_data[$coluna_codigo]['candidaturas'][] = $candidatura;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $kanban_data,
        'colunas' => $colunas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

