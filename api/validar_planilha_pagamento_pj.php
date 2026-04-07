<?php
/**
 * API: Recebe planilha CSV via upload temporário, valida e retorna o resultado.
 * O arquivo NÃO é salvo aqui (só validado) — o salvamento ocorre no submit final.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pagamento_pj_functions.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$usuario = $_SESSION['usuario'];

try {
    if (!isset($_FILES['planilha']) || !is_uploaded_file($_FILES['planilha']['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']);
        exit;
    }

    $mes_referencia = trim($_POST['mes_referencia'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $mes_referencia)) {
        echo json_encode(['success' => false, 'message' => 'Mês de referência inválido (use YYYY-MM)']);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['planilha']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        echo json_encode(['success' => false, 'message' => 'Envie um arquivo CSV (.csv)']);
        exit;
    }

    if ($_FILES['planilha']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Arquivo muito grande (máximo 10MB)']);
        exit;
    }

    // Salva temporariamente para parse
    $tmp_dir = __DIR__ . '/../uploads/tmp_planilhas/';
    if (!file_exists($tmp_dir)) {
        @mkdir($tmp_dir, 0755, true);
    }
    $tmp_path = $tmp_dir . 'tmp_' . time() . '_' . uniqid() . '.csv';
    if (!move_uploaded_file($_FILES['planilha']['tmp_name'], $tmp_path)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar arquivo']);
        exit;
    }

    $resultado = validar_planilha_pagamento_pj($tmp_path, $mes_referencia);

    // Limpa o arquivo temporário (não precisamos guardar — o usuário re-envia ao confirmar)
    @unlink($tmp_path);

    echo json_encode([
        'success' => true,
        'data' => $resultado
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
