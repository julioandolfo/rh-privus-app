<?php
/**
 * API para salvar/atualizar bônus de colaborador
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

check_permission(['ADMIN', 'RH']);

$action = $_POST['action'] ?? '';
$colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
$tipo_bonus_id = (int)($_POST['tipo_bonus_id'] ?? 0);
$valor = str_replace(['.', ','], ['', '.'], $_POST['valor'] ?? '0');
$data_inicio = $_POST['data_inicio'] ?? null;
$data_fim = $_POST['data_fim'] ?? null;
$observacoes = sanitize($_POST['observacoes'] ?? '');

if (empty($colaborador_id) || empty($tipo_bonus_id) || empty($valor)) {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
    exit;
}

try {
    $pdo = getDB();
    
    if ($action === 'add') {
        $stmt = $pdo->prepare("
            INSERT INTO colaboradores_bonus 
            (colaborador_id, tipo_bonus_id, valor, data_inicio, data_fim, observacoes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id, 
            $tipo_bonus_id, 
            $valor, 
            $data_inicio ?: null, 
            $data_fim ?: null, 
            $observacoes
        ]);
        echo json_encode(['success' => true, 'message' => 'Bônus adicionado com sucesso!']);
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) {
            echo json_encode(['success' => false, 'error' => 'ID não informado']);
            exit;
        }
        $stmt = $pdo->prepare("
            UPDATE colaboradores_bonus 
            SET tipo_bonus_id = ?, valor = ?, data_inicio = ?, data_fim = ?, observacoes = ?
            WHERE id = ? AND colaborador_id = ?
        ");
        $stmt->execute([
            $tipo_bonus_id, 
            $valor, 
            $data_inicio ?: null, 
            $data_fim ?: null, 
            $observacoes,
            $id,
            $colaborador_id
        ]);
        echo json_encode(['success' => true, 'message' => 'Bônus atualizado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar: ' . $e->getMessage()]);
}

