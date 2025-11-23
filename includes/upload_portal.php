<?php
/**
 * Função para fazer upload de imagens do portal público
 */

function upload_imagem_portal($file, $tipo = 'logo') {
    // Diretório de upload
    $upload_dir = __DIR__ . '/../uploads/portal/';
    
    // Cria diretório se não existir
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validações
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado'];
    }
    
    // Valida tipo de arquivo (apenas imagens)
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF, WEBP ou SVG'];
    }
    
    // Valida tamanho (máximo 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo 5MB'];
    }
    
    // Gera nome único para o arquivo
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($extension)) {
        // Tenta determinar extensão pelo mime type
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg'
        ];
        $extension = $extensions[$mime_type] ?? 'jpg';
    }
    
    $filename = $tipo . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move arquivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relative_path = 'uploads/portal/' . $filename;
        return [
            'success' => true,
            'path' => $relative_path,
            'url' => get_base_url() . $relative_path,
            'filename' => $file['name'],
            'mime_type' => $mime_type,
            'size' => $file['size']
        ];
    } else {
        return ['success' => false, 'error' => 'Erro ao fazer upload do arquivo'];
    }
}

