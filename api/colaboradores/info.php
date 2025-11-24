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
        
        // Se já for numérico (int ou float), usa diretamente
        if (is_numeric($salario_valor) && !is_string($salario_valor)) {
            $salario_float = (float)$salario_valor;
            if ($salario_float > 0) {
                $salario = $salario_float;
            }
        } 
        // Se for string (pode ter vírgula como separador decimal), trata formato brasileiro
        elseif (is_string($salario_valor)) {
            $salario_valor = trim($salario_valor);
            
            // Remove espaços e caracteres não numéricos exceto vírgula e ponto
            $salario_valor = preg_replace('/[^\d,.-]/', '', $salario_valor);
            
            // Se tiver vírgula e ponto (ex: 5.000,00 ou 5,000.00)
            if (strpos($salario_valor, ',') !== false && strpos($salario_valor, '.') !== false) {
                // Determina qual é o separador de milhar e qual é decimal
                $pos_virgula = strpos($salario_valor, ',');
                $pos_ponto = strpos($salario_valor, '.');
                
                // Se vírgula vem depois do ponto, vírgula é decimal (ex: 5.000,00)
                if ($pos_virgula > $pos_ponto) {
                    // Formato: 5.000,00 -> remove pontos, substitui vírgula por ponto
                    $salario_valor = str_replace('.', '', $salario_valor);
                    $salario_valor = str_replace(',', '.', $salario_valor);
                } else {
                    // Formato: 5,000.00 -> remove vírgulas
                    $salario_valor = str_replace(',', '', $salario_valor);
                }
            } 
            // Se só tem vírgula (formato brasileiro: 5000,00)
            elseif (strpos($salario_valor, ',') !== false) {
                $salario_valor = str_replace(',', '.', $salario_valor);
            }
            // Se só tem ponto, já está no formato correto
            
            // Converte para float
            $salario_float = (float)$salario_valor;
            
            // Só atribui se for maior que zero
            if ($salario_float > 0) {
                $salario = $salario_float;
            }
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

