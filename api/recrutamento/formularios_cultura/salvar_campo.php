<?php
/**
 * API: Salvar Campo do Formulário de Cultura
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
    
    $campo_id = !empty($_POST['campo_id']) ? (int)$_POST['campo_id'] : null;
    $formulario_id = (int)($_POST['formulario_id'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    $tipo_campo = $_POST['tipo_campo'] ?? '';
    
    if (empty($formulario_id) || empty($label) || empty($tipo_campo)) {
        throw new Exception('Formulário, label e tipo são obrigatórios');
    }
    
    // Gera código único
    $codigo = strtolower(preg_replace('/[^a-z0-9]/', '_', $label));
    
    // Processa opções
    $opcoes = null;
    if (!empty($_POST['opcoes'])) {
        $opcoes_array = json_decode($_POST['opcoes'], true);
        if (is_array($opcoes_array)) {
            $opcoes = json_encode($opcoes_array);
        }
    }
    
    if ($campo_id) {
        // Atualiza
        $stmt = $pdo->prepare("
            UPDATE formularios_cultura_campos SET
            label = ?, tipo_campo = ?, obrigatorio = ?, ordem = ?,
            opcoes = ?, escala_min = ?, escala_max = ?, peso = ?
            WHERE id = ? AND formulario_id = ?
        ");
        $stmt->execute([
            $label,
            $tipo_campo,
            isset($_POST['obrigatorio']) ? (int)$_POST['obrigatorio'] : 0,
            (int)($_POST['ordem'] ?? 0),
            $opcoes,
            !empty($_POST['escala_min']) ? (int)$_POST['escala_min'] : null,
            !empty($_POST['escala_max']) ? (int)$_POST['escala_max'] : null,
            !empty($_POST['peso']) ? (float)$_POST['peso'] : 1.00,
            $campo_id,
            $formulario_id
        ]);
    } else {
        // Cria novo
        $stmt = $pdo->prepare("
            INSERT INTO formularios_cultura_campos 
            (formulario_id, nome, codigo, tipo_campo, label, obrigatorio, ordem, opcoes, escala_min, escala_max, peso)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $formulario_id,
            $label,
            $codigo,
            $tipo_campo,
            $label,
            isset($_POST['obrigatorio']) ? (int)$_POST['obrigatorio'] : 0,
            (int)($_POST['ordem'] ?? 0),
            $opcoes,
            !empty($_POST['escala_min']) ? (int)$_POST['escala_min'] : null,
            !empty($_POST['escala_max']) ? (int)$_POST['escala_max'] : null,
            !empty($_POST['peso']) ? (float)$_POST['peso'] : 1.00
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Campo salvo com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

