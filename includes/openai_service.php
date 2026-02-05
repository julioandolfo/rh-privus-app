<?php
/**
 * Serviço de Integração com OpenAI
 * Funções para comunicação com a API da OpenAI
 */

require_once __DIR__ . '/functions.php';

/**
 * Busca configurações da OpenAI
 */
function get_openai_config() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM openai_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    return $stmt->fetch();
}

/**
 * Testa conexão com OpenAI
 */
function testar_conexao_openai() {
    $config = get_openai_config();
    
    if (!$config || empty($config['api_key'])) {
        return [
            'success' => false,
            'message' => 'Configuração não encontrada ou API Key não configurada'
        ];
    }
    
    try {
        $response = chamar_openai_api([
            'model' => $config['modelo'],
            'messages' => [
                ['role' => 'user', 'content' => 'Responda apenas: OK']
            ],
            'max_tokens' => 10
        ], $config['api_key']);
        
        if ($response['success']) {
            return [
                'success' => true,
                'modelo' => $config['modelo'],
                'message' => 'Conexão estabelecida com sucesso!'
            ];
        } else {
            return [
                'success' => false,
                'message' => $response['error'] ?? 'Erro desconhecido'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Exceção: ' . $e->getMessage()
        ];
    }
}

/**
 * Chama a API da OpenAI
 */
function chamar_openai_api($data, $api_key = null) {
    if (!$api_key) {
        $config = get_openai_config();
        if (!$config) {
            return ['success' => false, 'error' => 'Configuração não encontrada'];
        }
        $api_key = $config['api_key'];
    }
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ],
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'Erro CURL: ' . $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode !== 200) {
        $errorMsg = $responseData['error']['message'] ?? 'Erro HTTP ' . $httpCode;
        return ['success' => false, 'error' => $errorMsg];
    }
    
    return [
        'success' => true,
        'data' => $responseData
    ];
}

/**
 * Gera uma vaga completa usando IA
 */
function gerar_vaga_com_ia($descricao_entrada, $empresa_id, $template_codigo = 'vaga_generica') {
    $inicio = microtime(true);
    $pdo = getDB();
    
    // Busca configurações
    $config = get_openai_config();
    if (!$config) {
        return [
            'success' => false,
            'message' => 'Integração com OpenAI não está configurada'
        ];
    }
    
    // Busca template
    $stmt = $pdo->prepare("SELECT * FROM openai_prompt_templates WHERE codigo = ? AND ativo = 1");
    $stmt->execute([$template_codigo]);
    $template = $stmt->fetch();
    
    if (!$template) {
        return [
            'success' => false,
            'message' => 'Template não encontrado'
        ];
    }
    
    // Verifica rate limiting
    $usuario_id = $_SESSION['usuario']['id'] ?? 0;
    if (!verificar_rate_limit($usuario_id)) {
        return [
            'success' => false,
            'message' => 'Limite diário de gerações atingido. Tente novamente amanhã.'
        ];
    }
    
    // Monta prompts
    $prompt_sistema = $template['prompt_sistema'];
    $prompt_usuario = str_replace('{descricao}', $descricao_entrada, $template['prompt_usuario']);
    
    // Adiciona estrutura JSON ao prompt
    $estrutura_json = '
{
  "titulo": "string",
  "descricao": "string (HTML formatado, 200-500 palavras, use tags <p>, <ul>, <li>)",
  "salario_min": "number ou null",
  "salario_max": "number ou null", 
  "tipo_contrato": "CLT|PJ|Estágio|Temporário|Freelance",
  "beneficios": ["array de strings"],
  "requisitos_obrigatorios": "string (lista em HTML com <ul><li>)",
  "requisitos_desejaveis": "string (lista em HTML com <ul><li>)",
  "competencias_tecnicas": "string (lista em HTML com <ul><li>)",
  "competencias_comportamentais": "string (lista em HTML com <ul><li>)",
  "modalidade": "Presencial|Remoto|Híbrido",
  "localizacao": "string ou null",
  "horario_trabalho": "string (ex: 08:00 às 18:00)",
  "dias_trabalho": "string (ex: Segunda a Sexta)"
}';
    
    $prompt_usuario .= "\n\nRetorne APENAS um JSON válido com esta estrutura exata:\n" . $estrutura_json;
    
    // Chama API da OpenAI
    $response = chamar_openai_api([
        'model' => $config['modelo'],
        'messages' => [
            ['role' => 'system', 'content' => $prompt_sistema],
            ['role' => 'user', 'content' => $prompt_usuario]
        ],
        'temperature' => floatval($config['temperatura']),
        'max_tokens' => intval($config['max_tokens'])
    ], $config['api_key']);
    
    $tempo_geracao = round((microtime(true) - $inicio) * 1000); // em ms
    
    if (!$response['success']) {
        return [
            'success' => false,
            'message' => 'Erro ao chamar OpenAI: ' . $response['error']
        ];
    }
    
    // Extrai resposta
    $responseData = $response['data'];
    $content = $responseData['choices'][0]['message']['content'] ?? '';
    $tokens_usados = $responseData['usage']['total_tokens'] ?? 0;
    
    // Limpa markdown code blocks se houver
    $content = preg_replace('/```json\s*/', '', $content);
    $content = preg_replace('/```\s*$/', '', $content);
    $content = trim($content);
    
    // Decodifica JSON
    $vaga_data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'message' => 'Erro ao decodificar resposta da IA: ' . json_last_error_msg(),
            'raw_response' => $content
        ];
    }
    
    // Calcula custo estimado (aproximado)
    $custo_por_modelo = [
        'gpt-4o' => 0.000015, // $15/1M tokens (média input/output)
        'gpt-4o-mini' => 0.000002, // $2/1M tokens
        'gpt-3.5-turbo' => 0.0000005 // $0.5/1M tokens
    ];
    $custo_estimado = ($custo_por_modelo[$config['modelo']] ?? 0) * $tokens_usados;
    
    // Calcula score de qualidade
    $qualidade_score = calcular_qualidade_vaga($vaga_data);
    
    // Adiciona confiança nos campos
    $vaga_data_com_confianca = adicionar_confianca_campos($vaga_data);
    
    // Registra histórico
    registrar_geracao_ia([
        'usuario_id' => $usuario_id,
        'empresa_id' => $empresa_id,
        'descricao_entrada' => $descricao_entrada,
        'resposta_ia' => json_encode($vaga_data),
        'modelo_usado' => $config['modelo'],
        'tokens_usados' => $tokens_usados,
        'custo_estimado' => $custo_estimado,
        'tempo_geracao_ms' => $tempo_geracao,
        'qualidade_score' => $qualidade_score,
        'template_usado' => $template_codigo
    ]);
    
    // Incrementa contador do template
    $pdo->prepare("UPDATE openai_prompt_templates SET vezes_usado = vezes_usado + 1 WHERE id = ?")
        ->execute([$template['id']]);
    
    // Registra rate limit
    registrar_uso_rate_limit($usuario_id);
    
    return [
        'success' => true,
        'data' => $vaga_data_com_confianca,
        'meta' => [
            'tokens_usados' => $tokens_usados,
            'custo_estimado' => $custo_estimado,
            'tempo_geracao_ms' => $tempo_geracao,
            'modelo_usado' => $config['modelo'],
            'qualidade_score' => $qualidade_score
        ]
    ];
}

/**
 * Calcula score de qualidade da vaga (0-100)
 */
function calcular_qualidade_vaga($vaga_data) {
    $score = 0;
    
    // Título (10 pontos)
    if (!empty($vaga_data['titulo']) && strlen($vaga_data['titulo']) >= 10) {
        $score += 10;
    }
    
    // Descrição (20 pontos)
    if (!empty($vaga_data['descricao'])) {
        $desc_length = strlen(strip_tags($vaga_data['descricao']));
        if ($desc_length >= 200) {
            $score += 20;
        } elseif ($desc_length >= 100) {
            $score += 10;
        }
    }
    
    // Salário (15 pontos)
    if (isset($vaga_data['salario_min']) && $vaga_data['salario_min'] > 0) {
        $score += 10;
    }
    if (isset($vaga_data['salario_max']) && $vaga_data['salario_max'] > 0) {
        $score += 5;
    }
    
    // Benefícios (10 pontos)
    if (!empty($vaga_data['beneficios']) && count($vaga_data['beneficios']) >= 3) {
        $score += 10;
    } elseif (!empty($vaga_data['beneficios'])) {
        $score += 5;
    }
    
    // Requisitos (15 pontos)
    if (!empty($vaga_data['requisitos_obrigatorios'])) {
        $score += 10;
    }
    if (!empty($vaga_data['requisitos_desejaveis'])) {
        $score += 5;
    }
    
    // Competências (15 pontos)
    if (!empty($vaga_data['competencias_tecnicas'])) {
        $score += 10;
    }
    if (!empty($vaga_data['competencias_comportamentais'])) {
        $score += 5;
    }
    
    // Informações adicionais (15 pontos)
    if (!empty($vaga_data['modalidade'])) {
        $score += 5;
    }
    if (!empty($vaga_data['horario_trabalho'])) {
        $score += 5;
    }
    if (!empty($vaga_data['dias_trabalho'])) {
        $score += 5;
    }
    
    return min($score, 100);
}

/**
 * Adiciona indicadores de confiança aos campos
 */
function adicionar_confianca_campos($vaga_data) {
    $campos_confianca = [];
    
    foreach ($vaga_data as $campo => $valor) {
        $confianca = 'medium'; // padrão
        
        // Campos que geralmente têm alta confiança
        if (in_array($campo, ['titulo', 'tipo_contrato', 'modalidade'])) {
            $confianca = 'high';
        }
        
        // Campos que podem ter baixa confiança
        if (in_array($campo, ['salario_min', 'salario_max', 'localizacao']) && empty($valor)) {
            $confianca = 'low';
        }
        
        // Verifica completude de campos de texto
        if (in_array($campo, ['descricao', 'requisitos_obrigatorios', 'competencias_tecnicas'])) {
            $length = is_string($valor) ? strlen(strip_tags($valor)) : 0;
            if ($length >= 100) {
                $confianca = 'high';
            } elseif ($length < 50) {
                $confianca = 'low';
            }
        }
        
        $campos_confianca[$campo . '_confianca'] = $confianca;
    }
    
    return array_merge($vaga_data, ['_confianca' => $campos_confianca]);
}

/**
 * Verifica rate limiting
 */
function verificar_rate_limit($usuario_id) {
    $pdo = getDB();
    $hoje = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT quantidade, limite_diario 
        FROM openai_rate_limit 
        WHERE usuario_id = ? AND data_uso = ?
    ");
    $stmt->execute([$usuario_id, $hoje]);
    $registro = $stmt->fetch();
    
    if (!$registro) {
        return true; // Primeira geração do dia
    }
    
    return $registro['quantidade'] < $registro['limite_diario'];
}

/**
 * Registra uso no rate limit
 */
function registrar_uso_rate_limit($usuario_id) {
    $pdo = getDB();
    $hoje = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        INSERT INTO openai_rate_limit (usuario_id, data_uso, quantidade, limite_diario)
        VALUES (?, ?, 1, 10)
        ON DUPLICATE KEY UPDATE quantidade = quantidade + 1, updated_at = NOW()
    ");
    $stmt->execute([$usuario_id, $hoje]);
}

/**
 * Registra geração no histórico
 */
function registrar_geracao_ia($dados) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        INSERT INTO vagas_geradas_ia 
        (usuario_id, empresa_id, descricao_entrada, resposta_ia, modelo_usado, 
         tokens_usados, custo_estimado, tempo_geracao_ms, qualidade_score, template_usado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $dados['usuario_id'],
        $dados['empresa_id'],
        $dados['descricao_entrada'],
        $dados['resposta_ia'],
        $dados['modelo_usado'],
        $dados['tokens_usados'],
        $dados['custo_estimado'],
        $dados['tempo_geracao_ms'],
        $dados['qualidade_score'],
        $dados['template_usado']
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Atualiza histórico quando vaga é salva
 */
function atualizar_historico_vaga_salva($historico_id, $vaga_id, $campos_editados = []) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        UPDATE vagas_geradas_ia 
        SET vaga_id = ?, foi_salva = 1, campos_editados = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $vaga_id,
        json_encode($campos_editados),
        $historico_id
    ]);
}

/**
 * Refina uma vaga existente com nova instrução
 */
function refinar_vaga_com_ia($vaga_data_atual, $instrucao_refinamento, $empresa_id) {
    $config = get_openai_config();
    
    if (!$config) {
        return [
            'success' => false,
            'message' => 'Integração com OpenAI não está configurada'
        ];
    }
    
    $prompt_sistema = "Você é um especialista em Recursos Humanos. Refine a vaga abaixo seguindo a instrução do usuário.";
    
    $prompt_usuario = "Vaga atual (JSON):\n" . json_encode($vaga_data_atual, JSON_PRETTY_PRINT);
    $prompt_usuario .= "\n\nInstrução de refinamento: " . $instrucao_refinamento;
    $prompt_usuario .= "\n\nRetorne a vaga refinada no mesmo formato JSON.";
    
    $response = chamar_openai_api([
        'model' => $config['modelo'],
        'messages' => [
            ['role' => 'system', 'content' => $prompt_sistema],
            ['role' => 'user', 'content' => $prompt_usuario]
        ],
        'temperature' => floatval($config['temperatura']),
        'max_tokens' => intval($config['max_tokens'])
    ], $config['api_key']);
    
    if (!$response['success']) {
        return [
            'success' => false,
            'message' => 'Erro ao refinar: ' . $response['error']
        ];
    }
    
    $content = $response['data']['choices'][0]['message']['content'] ?? '';
    $content = preg_replace('/```json\s*/', '', $content);
    $content = preg_replace('/```\s*$/', '', $content);
    $content = trim($content);
    
    $vaga_refinada = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'message' => 'Erro ao decodificar resposta: ' . json_last_error_msg()
        ];
    }
    
    return [
        'success' => true,
        'data' => $vaga_refinada
    ];
}
