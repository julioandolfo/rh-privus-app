<?php
/**
 * Serviço de Integração com ChatGPT/OpenAI
 */

require_once __DIR__ . '/functions.php';

/**
 * Gera resumo de uma conversa usando ChatGPT
 */
function gerar_resumo_conversa_ia($conversa_id) {
    $pdo = getDB();
    
    // Verifica se ChatGPT está ativo
    $chatgpt_ativo = buscar_config_chat('chatgpt_ativo');
    if (!$chatgpt_ativo) {
        return ['success' => false, 'error' => 'Integração com ChatGPT não está ativa'];
    }
    
    $api_key = buscar_config_chat('chatgpt_api_key');
    if (empty($api_key)) {
        return ['success' => false, 'error' => 'API Key do ChatGPT não configurada'];
    }
    
    // Busca mensagens da conversa
    $stmt = $pdo->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.enviado_por_usuario_id IS NOT NULL THEN u.nome
                   WHEN m.enviado_por_colaborador_id IS NOT NULL THEN col.nome_completo
               END as autor_nome,
               CASE 
                   WHEN m.enviado_por_usuario_id IS NOT NULL THEN 'RH'
                   WHEN m.enviado_por_colaborador_id IS NOT NULL THEN 'Colaborador'
               END as autor_tipo
        FROM chat_mensagens m
        LEFT JOIN usuarios u ON m.enviado_por_usuario_id = u.id
        LEFT JOIN colaboradores col ON m.enviado_por_colaborador_id = col.id
        WHERE m.conversa_id = ? 
        AND m.deletada = FALSE
        AND m.tipo IN ('texto', 'voz')
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversa_id]);
    $mensagens = $stmt->fetchAll();
    
    if (empty($mensagens)) {
        return ['success' => false, 'error' => 'Nenhuma mensagem encontrada na conversa'];
    }
    
    // Busca dados da conversa
    $stmt = $pdo->prepare("
        SELECT c.*, col.nome_completo as colaborador_nome
        FROM chat_conversas c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conversa_id]);
    $conversa = $stmt->fetch();
    
    // Monta texto da conversa
    $texto_conversa = "Conversa entre Colaborador ({$conversa['colaborador_nome']}) e RH\n\n";
    $texto_conversa .= "Título: {$conversa['titulo']}\n";
    $texto_conversa .= "Categoria: " . ($conversa['categoria_id'] ? 'Categoria ' . $conversa['categoria_id'] : 'Não categorizada') . "\n";
    $texto_conversa .= "Prioridade: {$conversa['prioridade']}\n\n";
    $texto_conversa .= "Mensagens:\n\n";
    
    foreach ($mensagens as $msg) {
        $autor = $msg['autor_tipo'] . ' (' . $msg['autor_nome'] . ')';
        $data = date('d/m/Y H:i', strtotime($msg['created_at']));
        $texto = $msg['mensagem'] ?? '';
        
        // Se for voz e tiver transcrição, usa transcrição
        if ($msg['tipo'] === 'voz' && !empty($msg['voz_transcricao'])) {
            $texto = "[Áudio transcrito] " . $msg['voz_transcricao'];
        } elseif ($msg['tipo'] === 'voz') {
            $texto = "[Mensagem de voz - duração: {$msg['voz_duracao_segundos']}s]";
        }
        
        $texto_conversa .= "[{$data}] {$autor}: {$texto}\n";
    }
    
    // Monta prompt
    $prompt = "Resuma a seguinte conversa entre um colaborador e o RH, destacando:\n\n";
    $prompt .= "1. Assunto principal da conversa\n";
    $prompt .= "2. Problemas ou solicitações mencionadas pelo colaborador\n";
    $prompt .= "3. Soluções ou respostas propostas pelo RH\n";
    $prompt .= "4. Ações tomadas ou acordadas\n";
    $prompt .= "5. Status final da conversa\n";
    $prompt .= "6. Pontos importantes que merecem atenção\n\n";
    $prompt .= "Seja objetivo e direto. Use tópicos quando apropriado.\n\n";
    $prompt .= "Conversa:\n\n";
    $prompt .= $texto_conversa;
    
    // Configurações
    $modelo = buscar_config_chat('chatgpt_modelo') ?: 'gpt-4';
    $temperatura = (float)(buscar_config_chat('chatgpt_temperatura') ?: 0.7);
    $max_tokens = (int)(buscar_config_chat('chatgpt_max_tokens') ?: 500);
    
    // Chama API
    $resultado = chamar_api_openai($api_key, $modelo, $prompt, $temperatura, $max_tokens);
    
    if (!$resultado['success']) {
        return $resultado;
    }
    
    // Salva resumo
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO chat_resumos_ia 
            (conversa_id, resumo, prompt_usado, modelo_usado, tokens_usados, custo_estimado, gerado_por_usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $conversa_id,
            $resultado['resumo'],
            $prompt,
            $modelo,
            $resultado['tokens_usados'] ?? null,
            $resultado['custo_estimado'] ?? null,
            $_SESSION['usuario']['id'] ?? null
        ]);
        
        $resumo_id = $pdo->lastInsertId();
        
        // Atualiza conversa com resumo
        $stmt = $pdo->prepare("
            UPDATE chat_conversas SET
                resumo_ia = ?,
                resumo_ia_gerado_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$resultado['resumo'], $conversa_id]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'resumo' => $resultado['resumo'],
            'resumo_id' => $resumo_id,
            'tokens_usados' => $resultado['tokens_usados'] ?? null,
            'custo_estimado' => $resultado['custo_estimado'] ?? null
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Erro ao salvar resumo: ' . $e->getMessage()];
    }
}

/**
 * Chama API da OpenAI
 */
function chamar_api_openai($api_key, $modelo, $prompt, $temperatura = 0.7, $max_tokens = 500) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $data = [
        'model' => $modelo,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Você é um assistente especializado em resumir conversas de atendimento ao cliente. Seja objetivo e direto.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => $temperatura,
        'max_tokens' => $max_tokens
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'Erro na requisição: ' . $error];
    }
    
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_msg = $error_data['error']['message'] ?? 'Erro desconhecido';
        return ['success' => false, 'error' => "Erro da API: {$error_msg} (HTTP {$http_code})"];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['choices'][0]['message']['content'])) {
        return ['success' => false, 'error' => 'Resposta inválida da API'];
    }
    
    $resumo = trim($data['choices'][0]['message']['content']);
    $tokens_usados = $data['usage']['total_tokens'] ?? null;
    
    // Calcula custo estimado (valores aproximados)
    $custo_estimado = null;
    if ($tokens_usados) {
        // Preços aproximados por 1K tokens (pode variar)
        $preco_por_1k_tokens = 0.03; // Exemplo para gpt-4
        if (strpos($modelo, 'gpt-3.5') !== false) {
            $preco_por_1k_tokens = 0.002;
        }
        $custo_estimado = ($tokens_usados / 1000) * $preco_por_1k_tokens;
    }
    
    return [
        'success' => true,
        'resumo' => $resumo,
        'tokens_usados' => $tokens_usados,
        'custo_estimado' => $custo_estimado
    ];
}

/**
 * Transcreve áudio usando Whisper API (opcional)
 */
function transcrever_audio_whisper($caminho_audio, $api_key) {
    if (!file_exists($caminho_audio)) {
        return ['success' => false, 'error' => 'Arquivo de áudio não encontrado'];
    }
    
    $url = 'https://api.openai.com/v1/audio/transcriptions';
    
    $cfile = new CURLFile($caminho_audio, 'audio/mpeg', basename($caminho_audio));
    
    $data = [
        'file' => $cfile,
        'model' => 'whisper-1',
        'language' => 'pt'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'Erro na requisição: ' . $error];
    }
    
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_msg = $error_data['error']['message'] ?? 'Erro desconhecido';
        return ['success' => false, 'error' => "Erro da API: {$error_msg} (HTTP {$http_code})"];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['text'])) {
        return ['success' => false, 'error' => 'Resposta inválida da API'];
    }
    
    return [
        'success' => true,
        'transcricao' => trim($data['text'])
    ];
}

