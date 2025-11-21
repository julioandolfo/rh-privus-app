<?php
/**
 * Função para fazer upload de documentos de pagamento
 * Aceita PDF, imagens e documentos do Office
 */

function upload_documento_pagamento($file, $fechamento_id, $item_id) {
    // Diretório de upload
    $upload_dir = __DIR__ . '/../uploads/documentos_pagamento/';
    
    // Cria diretório se não existir
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Cria subdiretório por fechamento para organização
    $fechamento_dir = $upload_dir . 'fechamento_' . $fechamento_id . '/';
    if (!file_exists($fechamento_dir)) {
        mkdir($fechamento_dir, 0755, true);
    }
    
    // Validações
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado'];
    }
    
    // Valida tipo de arquivo
    $allowed_types = [
        // Documentos
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        // Imagens
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($mime_type, $allowed_types) || !in_array($extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido. Use PDF, DOC, DOCX, XLS, XLSX ou imagens (JPG, PNG, GIF, WEBP)'];
    }
    
    // Valida tamanho (máximo 10MB)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo 10MB'];
    }
    
    // Gera nome único para o arquivo
    $filename = 'item_' . $item_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $fechamento_dir . $filename;
    
    // Move arquivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Retorna caminho relativo
        $relative_path = 'uploads/documentos_pagamento/fechamento_' . $fechamento_id . '/' . $filename;
        return ['success' => true, 'path' => $relative_path, 'filename' => $file['name']];
    } else {
        return ['success' => false, 'error' => 'Erro ao fazer upload do arquivo'];
    }
}

/**
 * Deleta documento de pagamento
 */
function delete_documento_pagamento($documento_path) {
    if (!empty($documento_path)) {
        $filepath = __DIR__ . '/../' . $documento_path;
        if (file_exists($filepath) && is_file($filepath)) {
            @unlink($filepath);
            
            // Tenta remover diretório pai se estiver vazio
            $dir = dirname($filepath);
            if (is_dir($dir) && count(scandir($dir)) == 2) { // Apenas . e ..
                @rmdir($dir);
            }
        }
    }
}

/**
 * Obtém URL para download do documento
 */
function get_documento_url($documento_path) {
    if (!empty($documento_path) && file_exists(__DIR__ . '/../' . $documento_path)) {
        return '../' . $documento_path;
    }
    return null;
}

/**
 * Verifica se arquivo é imagem para preview
 */
function is_image_file($filepath) {
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    return in_array($extension, $image_extensions);
}

/**
 * Formata tamanho do arquivo para exibição
 */
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

