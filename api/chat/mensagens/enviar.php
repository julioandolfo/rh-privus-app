<?php
/**
 * API: Enviar Mensagem
 */

// Suprime erros para não quebrar JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Inicia buffer de saída para capturar qualquer output indesejado
ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/chat_functions.php';
require_once __DIR__ . '/../../../includes/upload_documento.php';

$response = ['success' => false, 'message' => ''];

try {
    // Verifica login
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $usuario = $_SESSION['usuario'];
    
    // Valida dados
    $conversa_id = (int)($_POST['conversa_id'] ?? 0);
    $mensagem = trim($_POST['mensagem'] ?? '');
    $tipo = $_POST['tipo'] ?? 'texto';
    
    if (empty($conversa_id)) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    $usuario_id = null;
    $colaborador_id = null;
    
    if (is_colaborador() && !empty($usuario['colaborador_id'])) {
        $colaborador_id = $usuario['colaborador_id'];
    } else {
        $usuario_id = $usuario['id'];
    }
    
    $anexo = null;
    $voz = null;
    
    // Processa anexo
    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = upload_anexo_chat($_FILES['anexo'], $conversa_id);
        if (!$upload_result['success']) {
            throw new Exception($upload_result['error'] ?? 'Erro ao fazer upload do anexo');
        }
        $anexo = $upload_result;
        $tipo = 'anexo';
    }
    
    // Processa mensagem de voz
    if (isset($_FILES['voz']) && $_FILES['voz']['error'] === UPLOAD_ERR_OK) {
        $upload_result = upload_voz_chat($_FILES['voz'], $conversa_id);
        if (!$upload_result['success']) {
            throw new Exception($upload_result['error'] ?? 'Erro ao fazer upload da mensagem de voz');
        }
        $voz = $upload_result;
        $tipo = 'voz';
        
        // Se transcrição automática estiver ativa, transcreve
        if (buscar_config_chat('voz_transcricao_ativa')) {
            require_once __DIR__ . '/../../../includes/chatgpt_service.php';
            $api_key = buscar_config_chat('chatgpt_api_key');
            if (!empty($api_key)) {
                $transcricao = transcrever_audio_whisper($upload_result['caminho_completo'], $api_key);
                if ($transcricao['success']) {
                    $voz['transcricao'] = $transcricao['transcricao'];
                    $mensagem = $transcricao['transcricao']; // Usa transcrição como mensagem também
                }
            }
        }
    }
    
    // Se não tem mensagem nem anexo nem voz
    if (empty($mensagem) && !$anexo && !$voz) {
        throw new Exception('Mensagem, anexo ou voz é obrigatório');
    }
    
    // Envia mensagem
    $resultado = enviar_mensagem_chat(
        $conversa_id,
        $mensagem,
        $usuario_id,
        $colaborador_id,
        $tipo,
        $anexo,
        $voz
    );
    
    if (!$resultado['success']) {
        throw new Exception($resultado['error'] ?? 'Erro ao enviar mensagem');
    }
    
    // Envia notificações
    require_once __DIR__ . '/../../../includes/chat_notifications.php';
    enviar_notificacao_nova_mensagem($conversa_id, $resultado['mensagem_id']);
    
    $response = [
        'success' => true,
        'message' => 'Mensagem enviada com sucesso',
        'mensagem_id' => $resultado['mensagem_id']
    ];
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
} catch (Error $e) {
    $response['success'] = false;
    $response['message'] = 'Erro interno: ' . $e->getMessage();
}

// Limpa qualquer saída anterior e garante que apenas JSON seja enviado
ob_end_clean();
echo json_encode($response);
exit;

/**
 * Faz upload de anexo do chat
 */
function upload_anexo_chat($file, $conversa_id) {
    $upload_dir = __DIR__ . '/../../../uploads/chat/anexos/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $conversa_dir = $upload_dir . 'conversa_' . $conversa_id . '/';
    if (!file_exists($conversa_dir)) {
        mkdir($conversa_dir, 0755, true);
    }
    
    // Validações
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado'];
    }
    
    $max_size_mb = buscar_config_chat('max_tamanho_anexo_mb') ?: 10;
    $max_size = $max_size_mb * 1024 * 1024;
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => "Arquivo muito grande. Máximo {$max_size_mb}MB"];
    }
    
    // Tipos permitidos
    $allowed_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido'];
    }
    
    // Gera nome único
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $filepath = $conversa_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relative_path = 'uploads/chat/anexos/conversa_' . $conversa_id . '/' . $filename;
        return [
            'success' => true,
            'caminho' => $relative_path,
            'caminho_completo' => $filepath,
            'nome_original' => $file['name'],
            'tipo_mime' => $mime_type,
            'tamanho' => $file['size']
        ];
    } else {
        return ['success' => false, 'error' => 'Erro ao fazer upload do arquivo'];
    }
}

/**
 * Faz upload de mensagem de voz
 */
function upload_voz_chat($file, $conversa_id) {
    $upload_dir = __DIR__ . '/../../../uploads/chat/voz/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $conversa_dir = $upload_dir . 'conversa_' . $conversa_id . '/';
    if (!file_exists($conversa_dir)) {
        mkdir($conversa_dir, 0755, true);
    }
    
    // Validações
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo de áudio enviado'];
    }
    
    $max_size_mb = buscar_config_chat('max_tamanho_voz_mb') ?: 5;
    $max_size = $max_size_mb * 1024 * 1024;
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => "Áudio muito grande. Máximo {$max_size_mb}MB"];
    }
    
    // Formatos permitidos (incluindo webm para gravação do navegador)
    $formatos_config = buscar_config_chat('voz_formatos_permitidos');
    if (is_array($formatos_config)) {
        $formatos_permitidos = $formatos_config;
    } elseif (is_string($formatos_config)) {
        $formatos_permitidos = json_decode($formatos_config, true) ?: ['mp3', 'wav', 'ogg', 'm4a', 'webm'];
    } else {
        $formatos_permitidos = ['mp3', 'wav', 'ogg', 'm4a', 'webm'];
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Se não tem extensão, tenta detectar pelo MIME type
    if (empty($extension)) {
        $finfo_temp = finfo_open(FILEINFO_MIME_TYPE);
        $mime_temp = finfo_file($finfo_temp, $file['tmp_name']);
        finfo_close($finfo_temp);
        
        $mime_to_ext = [
            'audio/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/m4a' => 'm4a'
        ];
        $extension = $mime_to_ext[$mime_temp] ?? 'webm';
    }
    
    // Adiciona webm se não estiver na lista
    if (!in_array('webm', $formatos_permitidos)) {
        $formatos_permitidos[] = 'webm';
    }
    
    if (!in_array($extension, $formatos_permitidos)) {
        return ['success' => false, 'error' => 'Formato de áudio não permitido. Use: ' . implode(', ', $formatos_permitidos)];
    }
    
    // Tipos MIME permitidos (incluindo webm e variações)
    $allowed_mimes = [
        'audio/webm', 
        'video/webm', // Alguns navegadores retornam video/webm para áudio WebM
        'audio/webm;codecs=opus',
        'audio/mpeg', 
        'audio/mp3', 
        'audio/wav', 
        'audio/x-wav',
        'audio/ogg', 
        'audio/ogg;codecs=opus',
        'audio/m4a',
        'audio/x-m4a',
        'application/octet-stream' // Fallback para alguns casos
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Remove parâmetros do MIME type para comparação (ex: audio/webm;codecs=opus -> audio/webm)
    $mime_type_base = explode(';', $mime_type)[0];
    
    // Verifica se o MIME type está permitido (com ou sem parâmetros)
    $mime_permitido = false;
    foreach ($allowed_mimes as $allowed) {
        $allowed_base = explode(';', $allowed)[0];
        if ($mime_type === $allowed || $mime_type_base === $allowed_base) {
            $mime_permitido = true;
            break;
        }
    }
    
    // Se não está na lista mas a extensão é webm e o MIME contém "webm", permite
    if (!$mime_permitido && $extension === 'webm' && (strpos($mime_type, 'webm') !== false || strpos($mime_type, 'octet-stream') !== false)) {
        $mime_permitido = true;
    }
    
    if (!$mime_permitido) {
        return ['success' => false, 'error' => "Tipo de arquivo de áudio não permitido. Tipo detectado: {$mime_type}, Extensão: {$extension}"];
    }
    
    // Gera nome único
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $filepath = $conversa_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relative_path = 'uploads/chat/voz/conversa_' . $conversa_id . '/' . $filename;
        
        // Tenta obter duração do áudio (requer getid3 ou similar)
        $duracao = obter_duracao_audio($filepath);
        
        return [
            'success' => true,
            'caminho' => $relative_path,
            'caminho_completo' => $filepath,
            'duracao_segundos' => $duracao,
            'tipo_mime' => $mime_type,
            'tamanho' => $file['size']
        ];
    } else {
        return ['success' => false, 'error' => 'Erro ao fazer upload do áudio'];
    }
}

/**
 * Obtém duração do áudio (simplificado - pode melhorar com biblioteca)
 */
function obter_duracao_audio($filepath) {
    // Implementação básica - pode usar getid3 ou ffmpeg para melhor precisão
    // Por enquanto retorna null e será calculado no frontend
    return null;
}

