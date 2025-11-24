<?php
/**
 * API para upload de imagens do TinyMCE
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verifica login
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verifica se há arquivo
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado']);
    exit;
}

$upload_dir = __DIR__ . '/../../uploads/comunicados/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $_FILES['file']['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de arquivo não permitido']);
    exit;
}

$max_size = 5 * 1024 * 1024; // 5MB
if ($_FILES['file']['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'Arquivo muito grande']);
    exit;
}

$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
$filename = 'comunicado_' . time() . '_' . uniqid() . '.' . $ext;
$filepath = $upload_dir . $filename;

if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
    $base_url = get_base_url();
    $location = $base_url . '/uploads/comunicados/' . $filename;
    
    header('Content-Type: application/json');
    echo json_encode(['location' => $location]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao fazer upload']);
}

