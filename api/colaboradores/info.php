<?php
/**
 * API: Buscar informações do colaborador (salário, jornada, etc)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$colaborador_id = $_GET['colaborador_id'] ?? null;

if (empty($colaborador_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Colaborador não informado'
    ]);
    exit;
}

$colaborador_id = (int)$colaborador_id;

// Verifica permissão
if (!can_access_colaborador($colaborador_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Sem permissão para acessar este colaborador'
    ]);
    exit;
}

try {
    $pdo = getDB();
    
    // Busca informações do colaborador
    $stmt = $pdo->prepare("
        SELECT 
            id,
            salario,
            jornada_diaria_horas,
            tipo_contrato
        FROM colaboradores 
        WHERE id = ?
    ");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$colaborador) {
        throw new Exception('Colaborador não encontrado');
    }
    
    // Converte salário para float, tratando valores NULL, vazios ou zero
    $salario = null;
    if (isset($colaborador['salario']) && $colaborador['salario'] !== null && $colaborador['salario'] !== '') {
        // Tenta converter para float
        $salario_valor = $colaborador['salario'];
        
        // Se for string, remove espaços e trata vírgula como separador decimal
        if (is_string($salario_valor)) {
            $salario_valor = trim($salario_valor);
            // Substitui vírgula por ponto se houver
            $salario_valor = str_replace(',', '.', str_replace('.', '', $salario_valor));
        }
        
        $salario_float = (float)$salario_valor;
        
        // Só atribui se for maior que zero
        if ($salario_float > 0) {
            $salario = $salario_float;
        }
    }
    
    // Converte jornada diária para float
    $jornada_diaria = 8.0; // Padrão
    if (isset($colaborador['jornada_diaria_horas']) && $colaborador['jornada_diaria_horas'] !== null && $colaborador['jornada_diaria_horas'] !== '' && $colaborador['jornada_diaria_horas'] > 0) {
        $jornada_diaria = (float)$colaborador['jornada_diaria_horas'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'salario' => $salario,
            'jornada_diaria_horas' => $jornada_diaria,
            'tipo_contrato' => $colaborador['tipo_contrato'] ?? null
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

