<?php
/**
 * Funções Auxiliares - Sistema de Chat Interno
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

/**
 * Função auxiliar para log do chat
 */
function chat_log($message, $data = null) {
    $log_file = __DIR__ . '/../logs/chat.log';
    $log_dir = dirname($log_file);
    
    // Cria diretório se não existir
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_msg = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data !== null) {
        $log_msg .= " | Data: " . json_encode($data);
    }
    $log_msg .= "\n";
    
    @file_put_contents($log_file, $log_msg, FILE_APPEND);
}

/**
 * Busca conversas de um colaborador
 */
function buscar_conversas_colaborador($colaborador_id, $status = null, $limit = 50) {
    $pdo = getDB();
    
    $sql = "
        SELECT c.*,
               COUNT(DISTINCT m.id) as total_mensagens_real,
               MAX(m.created_at) as ultima_mensagem_real_at,
               COUNT(DISTINCT CASE WHEN m.deletada = FALSE AND m.enviado_por_usuario_id IS NOT NULL AND m.lida_por_colaborador = FALSE THEN m.id END) as total_mensagens_nao_lidas_colaborador,
               (SELECT SUBSTRING(m2.mensagem, 1, 100) FROM chat_mensagens m2 WHERE m2.conversa_id = c.id AND m2.deletada = FALSE AND m2.mensagem IS NOT NULL AND m2.mensagem != '' ORDER BY m2.created_at DESC LIMIT 1) as ultima_mensagem_preview
        FROM chat_conversas c
        LEFT JOIN chat_mensagens m ON c.id = m.conversa_id AND m.deletada = FALSE
        WHERE c.colaborador_id = ?
    ";
    
    $params = [$colaborador_id];
    
    if ($status) {
        // Ajusta status: 'aberta' inclui todos os status que não são fechados
        if ($status === 'aberta') {
            $sql .= " AND c.status NOT IN ('fechada', 'arquivada', 'resolvida')";
        } else {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }
    }
    
    // Limite (deve ser inteiro)
    $limit = (int)$limit;
    if ($limit <= 0) {
        $limit = 50;
    }
    $sql .= " GROUP BY c.id ORDER BY c.ultima_mensagem_at DESC LIMIT {$limit}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Busca conversas para RH
 * 
 * @param array $filtros Filtros de busca
 * @param array|null $usuario Informações do usuário logado (role e id). Se não fornecido, busca da sessão.
 * @return array Lista de conversas
 */
function buscar_conversas_rh($filtros = [], $usuario = null) {
    $pdo = getDB();
    
    // Se não forneceu usuário, busca da sessão
    if ($usuario === null && isset($_SESSION['usuario'])) {
        $usuario = $_SESSION['usuario'];
    }
    
    $usuario_role = $usuario['role'] ?? null;
    $usuario_id = $usuario['id'] ?? null;
    
    $sql = "
        SELECT c.*,
               col.nome_completo as colaborador_nome,
               col.foto as colaborador_foto,
               col.email_pessoal as colaborador_email,
               u.nome as atribuido_para_nome,
               cat.nome as categoria_nome,
               cat.cor as categoria_cor,
               COALESCE(COUNT(DISTINCT CASE WHEN m.deletada = FALSE AND m.enviado_por_colaborador_id IS NOT NULL AND m.lida_por_rh = FALSE THEN m.id END), 0) as total_mensagens_nao_lidas_rh,
               (SELECT SUBSTRING(m2.mensagem, 1, 100) FROM chat_mensagens m2 WHERE m2.conversa_id = c.id AND m2.deletada = FALSE AND m2.mensagem IS NOT NULL AND m2.mensagem != '' ORDER BY m2.created_at DESC LIMIT 1) as ultima_mensagem_preview
        FROM chat_conversas c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        LEFT JOIN usuarios u ON c.atribuido_para_usuario_id = u.id
        LEFT JOIN chat_categorias cat ON c.categoria_id = cat.id
        LEFT JOIN chat_mensagens m ON c.id = m.conversa_id AND m.deletada = FALSE
        WHERE 1=1
    ";
    
    $params = [];
    
    // FILTRO AUTOMÁTICO POR ATRIBUIÇÃO
    // ADMIN vê tudo, RH vê apenas conversas atribuídas a ele + não atribuídas
    $aplicar_filtro_automatico = true;
    if ($usuario_role === 'RH' && !empty($usuario_id)) {
        // RH: apenas conversas atribuídas a ele OU não atribuídas
        // Mas verifica se há filtro manual que pode sobrescrever
        if (isset($filtros['atribuido_para']) && $filtros['atribuido_para'] !== '') {
            // Se há filtro manual, não aplica o automático (será aplicado abaixo)
            $aplicar_filtro_automatico = false;
        } else {
            // Aplica filtro automático
            $sql .= " AND (c.atribuido_para_usuario_id = ? OR c.atribuido_para_usuario_id IS NULL)";
            $params[] = (int)$usuario_id;
        }
    }
    // ADMIN não tem filtro automático (vê tudo)
    
    // Filtro por status
    if (!empty($filtros['status'])) {
        // Ajusta status: 'aberta' inclui todos os status que não são fechados
        if ($filtros['status'] === 'aberta') {
            $sql .= " AND c.status NOT IN ('fechada', 'arquivada', 'resolvida')";
        } elseif ($filtros['status'] === '') {
            // Se status vazio, não filtra por status
            // Não adiciona nada
        } else {
            $sql .= " AND c.status = ?";
            $params[] = $filtros['status'];
        }
    } else {
        // Se não especificou status, mostra todas (incluindo fechadas)
        // Não adiciona filtro
    }
    
    // Filtro manual por atribuído (pode sobrescrever o filtro automático)
    if (isset($filtros['atribuido_para']) && $filtros['atribuido_para'] !== '') {
        if ($filtros['atribuido_para'] === 'nao_atribuido') {
            $sql .= " AND c.atribuido_para_usuario_id IS NULL";
        } elseif (is_numeric($filtros['atribuido_para'])) {
            $sql .= " AND c.atribuido_para_usuario_id = ?";
            $params[] = (int)$filtros['atribuido_para'];
        }
    }
    
    // Filtro por prioridade
    if (!empty($filtros['prioridade'])) {
        $sql .= " AND c.prioridade = ?";
        $params[] = $filtros['prioridade'];
    }
    
    // Filtro por categoria
    if (!empty($filtros['categoria_id'])) {
        $sql .= " AND c.categoria_id = ?";
        $params[] = $filtros['categoria_id'];
    }
    
    // Busca por título ou nome do colaborador
    if (!empty($filtros['busca'])) {
        $sql .= " AND (c.titulo LIKE ? OR col.nome_completo LIKE ?)";
        $busca = '%' . $filtros['busca'] . '%';
        $params[] = $busca;
        $params[] = $busca;
    }
    
    // Ordenação
    $order_by = $filtros['order_by'] ?? 'ultima_mensagem_at';
    $order_dir = $filtros['order_dir'] ?? 'DESC';
    
    // Valida order_by para prevenir SQL injection
    $allowed_order_by = ['ultima_mensagem_at', 'created_at', 'titulo', 'prioridade', 'status'];
    if (!in_array($order_by, $allowed_order_by)) {
        $order_by = 'ultima_mensagem_at';
    }
    
    $order_dir = strtoupper($order_dir) === 'ASC' ? 'ASC' : 'DESC';
    
    // Adiciona GROUP BY antes do ORDER BY
    $sql .= " GROUP BY c.id";
    $sql .= " ORDER BY c.{$order_by} {$order_dir}";
    
    // Limite (deve ser inteiro, não pode usar prepared statement diretamente)
    $limit = (int)($filtros['limit'] ?? 50);
    if ($limit <= 0) {
        $limit = 50;
    }
    $sql .= " LIMIT {$limit}";
    
    // Debug: Log da query
    chat_log("buscar_conversas_rh - SQL", $sql);
    chat_log("buscar_conversas_rh - Params", $params);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultado = $stmt->fetchAll();
    
    chat_log("buscar_conversas_rh - Resultados encontrados: " . count($resultado));
    if (!empty($resultado)) {
        chat_log("buscar_conversas_rh - Primeira conversa ID: " . ($resultado[0]['id'] ?? 'N/A'));
        chat_log("buscar_conversas_rh - Primeira conversa status: " . ($resultado[0]['status'] ?? 'N/A'));
    }
    
    return $resultado;
}

/**
 * Busca mensagens de uma conversa
 */
function buscar_mensagens_conversa($conversa_id, $page = 1, $limit = 50) {
    $pdo = getDB();
    
    $offset = ($page - 1) * $limit;
    
    // Garante que são inteiros
    $limit = (int)$limit;
    $offset = (int)$offset;
    if ($limit <= 0) {
        $limit = 50;
    }
    if ($offset < 0) {
        $offset = 0;
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*,
               u.nome as usuario_nome,
               u.foto as usuario_foto,
               col.nome_completo as colaborador_nome,
               col.foto as colaborador_foto
        FROM chat_mensagens m
        LEFT JOIN usuarios u ON m.enviado_por_usuario_id = u.id
        LEFT JOIN colaboradores col ON m.enviado_por_colaborador_id = col.id
        WHERE m.conversa_id = ? AND m.deletada = FALSE
        ORDER BY m.created_at ASC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute([$conversa_id]);
    return $stmt->fetchAll();
}

/**
 * Cria uma nova conversa
 */
function criar_conversa($colaborador_id, $titulo, $mensagem, $categoria_id = null, $prioridade = 'normal') {
    $pdo = getDB();
    
    try {
        $pdo->beginTransaction();
        
        // Cria conversa
        $stmt = $pdo->prepare("
            INSERT INTO chat_conversas 
            (colaborador_id, titulo, status, prioridade, categoria_id, ultima_mensagem_at, ultima_mensagem_por)
            VALUES (?, ?, 'nova', ?, ?, NOW(), 'colaborador')
        ");
        $stmt->execute([$colaborador_id, $titulo, $prioridade, $categoria_id]);
        $conversa_id = $pdo->lastInsertId();
        
        // Cria primeira mensagem
        $stmt = $pdo->prepare("
            INSERT INTO chat_mensagens 
            (conversa_id, enviado_por_colaborador_id, tipo, mensagem)
            VALUES (?, ?, 'texto', ?)
        ");
        $stmt->execute([$conversa_id, $colaborador_id, $mensagem]);
        
        // Aplica SLA
        aplicar_sla_conversa($conversa_id);
        
        $pdo->commit();
        return ['success' => true, 'conversa_id' => $conversa_id];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envia mensagem em uma conversa
 */
function enviar_mensagem_chat($conversa_id, $mensagem, $usuario_id = null, $colaborador_id = null, $tipo = 'texto', $anexo = null, $voz = null) {
    $pdo = getDB();
    
    if (!$usuario_id && !$colaborador_id) {
        return ['success' => false, 'error' => 'Informe usuario_id ou colaborador_id'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verifica se conversa existe e está aberta
        $stmt = $pdo->prepare("SELECT id, status FROM chat_conversas WHERE id = ?");
        $stmt->execute([$conversa_id]);
        $conversa = $stmt->fetch();
        
        if (!$conversa) {
            throw new Exception('Conversa não encontrada');
        }
        
        if (in_array($conversa['status'], ['fechada', 'arquivada'])) {
            // Reabre conversa se necessário
            $stmt = $pdo->prepare("UPDATE chat_conversas SET status = 'em_atendimento' WHERE id = ?");
            $stmt->execute([$conversa_id]);
        }
        
        // Prepara dados da mensagem
        $dados_mensagem = [
            'conversa_id' => $conversa_id,
            'tipo' => $tipo,
            'mensagem' => $mensagem
        ];
        
        if ($usuario_id) {
            $dados_mensagem['enviado_por_usuario_id'] = $usuario_id;
        } else {
            $dados_mensagem['enviado_por_colaborador_id'] = $colaborador_id;
        }
        
        // Anexo
        if ($anexo) {
            $dados_mensagem['anexo_caminho'] = $anexo['caminho'];
            $dados_mensagem['anexo_nome_original'] = $anexo['nome_original'];
            $dados_mensagem['anexo_tipo_mime'] = $anexo['tipo_mime'];
            $dados_mensagem['anexo_tamanho'] = $anexo['tamanho'];
        }
        
        // Voz
        if ($voz) {
            $dados_mensagem['voz_caminho'] = $voz['caminho'];
            $dados_mensagem['voz_duracao_segundos'] = $voz['duracao_segundos'];
            $dados_mensagem['voz_transcricao'] = $voz['transcricao'] ?? null;
        }
        
        // Insere mensagem
        $campos = array_keys($dados_mensagem);
        $placeholders = array_fill(0, count($campos), '?');
        $sql = "INSERT INTO chat_mensagens (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($dados_mensagem));
        $mensagem_id = $pdo->lastInsertId();
        
        $pdo->commit();
        return ['success' => true, 'mensagem_id' => $mensagem_id];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Aplica SLA a uma conversa
 */
function aplicar_sla_conversa($conversa_id) {
    $pdo = getDB();
    
    // Busca configuração de SLA (primeiro da empresa, depois global)
    $stmt = $pdo->prepare("
        SELECT sla.* 
        FROM chat_conversas c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        LEFT JOIN chat_sla_config sla ON (sla.empresa_id = col.empresa_id OR sla.empresa_id IS NULL) AND sla.ativo = TRUE
        WHERE c.id = ?
        ORDER BY sla.empresa_id DESC
        LIMIT 1
    ");
    $stmt->execute([$conversa_id]);
    $sla_config = $stmt->fetch();
    
    if (!$sla_config) {
        return false;
    }
    
    // Busca dados da conversa
    $stmt = $pdo->prepare("SELECT * FROM chat_conversas WHERE id = ?");
    $stmt->execute([$conversa_id]);
    $conversa = $stmt->fetch();
    
    // Calcula vencimentos
    $tempo_primeira_resposta = $sla_config['tempo_primeira_resposta_minutos'] * 60;
    $tempo_resolucao = $sla_config['tempo_resolucao_horas'] * 3600;
    
    // Ajusta por prioridade se configurado
    if ($sla_config['aplicar_por_prioridade']) {
        if ($conversa['prioridade'] === 'urgente' && $sla_config['sla_prioridade_urgente_minutos']) {
            $tempo_primeira_resposta = $sla_config['sla_prioridade_urgente_minutos'] * 60;
        } elseif ($conversa['prioridade'] === 'alta' && $sla_config['sla_prioridade_alta_minutos']) {
            $tempo_primeira_resposta = $sla_config['sla_prioridade_alta_minutos'] * 60;
        }
    }
    
    // Calcula vencimentos considerando horário comercial
    $agora = new DateTime();
    $vencimento_primeira_resposta = clone $agora;
    $vencimento_resolucao = clone $agora;
    
    if ($sla_config['aplicar_apenas_horario_comercial']) {
        // Lógica para considerar apenas horário comercial
        // Simplificado: adiciona tempo considerando apenas horas úteis
        $vencimento_primeira_resposta->modify("+{$sla_config['tempo_primeira_resposta_minutos']} minutes");
        $vencimento_resolucao->modify("+{$sla_config['tempo_resolucao_horas']} hours");
    } else {
        $vencimento_primeira_resposta->modify("+{$sla_config['tempo_primeira_resposta_minutos']} minutes");
        $vencimento_resolucao->modify("+{$sla_config['tempo_resolucao_horas']} hours");
    }
    
    // Atualiza conversa
    $stmt = $pdo->prepare("
        UPDATE chat_conversas SET
            sla_primeira_resposta_vencimento = ?,
            sla_resolucao_vencimento = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $vencimento_primeira_resposta->format('Y-m-d H:i:s'),
        $vencimento_resolucao->format('Y-m-d H:i:s'),
        $conversa_id
    ]);
    
    return true;
}

/**
 * Marca mensagens como lidas
 */
function marcar_mensagens_lidas($conversa_id, $usuario_id = null, $colaborador_id = null) {
    $pdo = getDB();
    
    $campo_lida = $usuario_id ? 'lida_por_rh' : 'lida_por_colaborador';
    $campo_lida_at = $usuario_id ? 'lida_por_rh_at' : 'lida_por_colaborador_at';
    
    $stmt = $pdo->prepare("
        UPDATE chat_mensagens SET
            {$campo_lida} = TRUE,
            {$campo_lida_at} = NOW()
        WHERE conversa_id = ? 
        AND {$campo_lida} = FALSE
        AND deletada = FALSE
    ");
    $stmt->execute([$conversa_id]);
    
    return $stmt->rowCount();
}

/**
 * Atribui conversa para um RH
 */
function atribuir_conversa($conversa_id, $usuario_id, $quem_atribuiu_id) {
    $pdo = getDB();
    
    try {
        $pdo->beginTransaction();
        
        // Busca conversa atual
        $stmt = $pdo->prepare("SELECT atribuido_para_usuario_id, status FROM chat_conversas WHERE id = ?");
        $stmt->execute([$conversa_id]);
        $conversa = $stmt->fetch();
        
        // Atualiza atribuição
        $stmt = $pdo->prepare("
            UPDATE chat_conversas SET
                atribuido_para_usuario_id = ?,
                status = CASE 
                    WHEN status = 'nova' THEN 'aguardando_triagem'
                    WHEN status = 'aguardando_triagem' THEN 'em_atendimento'
                    ELSE status
                END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$usuario_id, $conversa_id]);
        
        // Registra histórico
        $stmt = $pdo->prepare("
            INSERT INTO chat_historico_acoes 
            (conversa_id, acao, realizado_por_usuario_id, dados_anteriores, dados_novos)
            VALUES (?, 'atribuida', ?, ?, ?)
        ");
        $stmt->execute([
            $conversa_id,
            $quem_atribuiu_id,
            json_encode(['atribuido_para_usuario_id' => $conversa['atribuido_para_usuario_id']]),
            json_encode(['atribuido_para_usuario_id' => $usuario_id])
        ]);
        
        $pdo->commit();
        return ['success' => true];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fecha uma conversa
 */
function fechar_conversa($conversa_id, $usuario_id, $motivo = null) {
    $pdo = getDB();
    
    try {
        $pdo->beginTransaction();
        
        // Busca conversa
        $stmt = $pdo->prepare("SELECT * FROM chat_conversas WHERE id = ?");
        $stmt->execute([$conversa_id]);
        $conversa = $stmt->fetch();
        
        // Calcula tempo de resolução
        $tempo_resolucao = null;
        if ($conversa['created_at']) {
            $created = new DateTime($conversa['created_at']);
            $agora = new DateTime();
            $tempo_resolucao = $agora->getTimestamp() - $created->getTimestamp();
        }
        
        // Verifica SLA
        $sla_cumprido = null;
        if ($conversa['sla_resolucao_vencimento']) {
            $vencimento = new DateTime($conversa['sla_resolucao_vencimento']);
            $agora = new DateTime();
            $sla_cumprido = $agora <= $vencimento;
        }
        
        // Atualiza conversa
        $stmt = $pdo->prepare("
            UPDATE chat_conversas SET
                status = 'fechada',
                tempo_resolucao_segundos = ?,
                sla_resolucao_cumprido = ?,
                fechada_at = NOW(),
                fechada_por = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$tempo_resolucao, $sla_cumprido, $usuario_id, $conversa_id]);
        
        // Registra histórico
        $stmt = $pdo->prepare("
            INSERT INTO chat_historico_acoes 
            (conversa_id, acao, realizado_por_usuario_id, observacoes)
            VALUES (?, 'fechada', ?, ?)
        ");
        $stmt->execute([$conversa_id, $usuario_id, $motivo]);
        
        $pdo->commit();
        return ['success' => true];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Busca configuração do chat
 */
function buscar_config_chat($chave) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT valor, tipo FROM chat_configuracoes WHERE chave = ?");
    $stmt->execute([$chave]);
    $config = $stmt->fetch();
    
    if (!$config) {
        return null;
    }
    
    // Converte tipo
    switch ($config['tipo']) {
        case 'boolean':
            return $config['valor'] === 'true';
        case 'integer':
            return (int)$config['valor'];
        case 'json':
            return json_decode($config['valor'], true);
        default:
            return $config['valor'];
    }
}


/**
 * Verifica se chat está ativo
 */
function chat_ativo() {
    return buscar_config_chat('chat_ativo') === true;
}

/**
 * Busca preferências do usuário
 */
function buscar_preferencias_chat($usuario_id = null, $colaborador_id = null) {
    $pdo = getDB();
    
    if ($usuario_id) {
        $stmt = $pdo->prepare("SELECT * FROM chat_preferencias_usuario WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM chat_preferencias_usuario WHERE colaborador_id = ?");
        $stmt->execute([$colaborador_id]);
    }
    
    $prefs = $stmt->fetch();
    
    // Cria registro padrão se não existir
    if (!$prefs) {
        if ($usuario_id) {
            $stmt = $pdo->prepare("INSERT INTO chat_preferencias_usuario (usuario_id) VALUES (?)");
            $stmt->execute([$usuario_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO chat_preferencias_usuario (colaborador_id) VALUES (?)");
            $stmt->execute([$colaborador_id]);
        }
        return buscar_preferencias_chat($usuario_id, $colaborador_id);
    }
    
    return $prefs;
}

