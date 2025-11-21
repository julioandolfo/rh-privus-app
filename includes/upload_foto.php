<?php
/**
 * Função para fazer upload de foto de perfil
 */

function upload_foto_perfil($file, $tipo = 'colaborador', $id = null) {
    // Diretório de upload
    $upload_dir = __DIR__ . '/../uploads/fotos/';
    
    // Cria diretório se não existir
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validações
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado'];
    }
    
    // Valida tipo de arquivo
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP'];
    }
    
    // Valida tamanho (máximo 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo 5MB'];
    }
    
    // Gera nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        // Tenta determinar extensão pelo mime type
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $extension = $extensions[$mime_type] ?? 'jpg';
    }
    
    $filename = $tipo . '_' . ($id ?? uniqid()) . '_' . time() . '.' . strtolower($extension);
    $filepath = $upload_dir . $filename;
    
    // Move arquivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Redimensiona imagem se necessário (máximo 500x500px)
        resize_image($filepath, 500, 500);
        
        // Retorna caminho relativo
        $relative_path = 'uploads/fotos/' . $filename;
        return ['success' => true, 'path' => $relative_path];
    } else {
        return ['success' => false, 'error' => 'Erro ao fazer upload do arquivo'];
    }
}

function resize_image($filepath, $max_width = 500, $max_height = 500) {
    if (!file_exists($filepath)) {
        return false;
    }
    
    $image_info = getimagesize($filepath);
    if (!$image_info) {
        return false;
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    $mime_type = $image_info['mime'];
    
    // Se já está dentro do tamanho máximo, não precisa redimensionar
    if ($width <= $max_width && $height <= $max_height) {
        return true;
    }
    
    // Calcula novas dimensões mantendo proporção
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);
    
    // Cria imagem a partir do arquivo
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $source = imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($filepath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($filepath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($filepath);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Cria nova imagem redimensionada
    $destination = imagecreatetruecolor($new_width, $new_height);
    
    // Mantém transparência para PNG e GIF
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Redimensiona
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Salva imagem
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            imagejpeg($destination, $filepath, 85);
            break;
        case 'image/png':
            imagepng($destination, $filepath, 8);
            break;
        case 'image/gif':
            imagegif($destination, $filepath);
            break;
        case 'image/webp':
            imagewebp($destination, $filepath, 85);
            break;
    }
    
    // Libera memória
    imagedestroy($source);
    imagedestroy($destination);
    
    return true;
}

function delete_foto_perfil($foto_path) {
    if (!empty($foto_path)) {
        $filepath = __DIR__ . '/../' . $foto_path;
        if (file_exists($filepath) && is_file($filepath)) {
            @unlink($filepath);
        }
    }
}

function get_foto_perfil($foto_path, $nome = null, $tamanho = 'medium') {
    if (!empty($foto_path) && file_exists(__DIR__ . '/../' . $foto_path)) {
        return '../' . $foto_path;
    }
    
    // Retorna avatar padrão com inicial do nome
    $inicial = !empty($nome) ? strtoupper(substr($nome, 0, 1)) : '?';
    return 'data:image/svg+xml;base64,' . base64_encode('
        <svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">
            <rect width="100" height="100" fill="#009ef7"/>
            <text x="50" y="50" font-family="Arial" font-size="40" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inicial) . '</text>
        </svg>
    ');
}

