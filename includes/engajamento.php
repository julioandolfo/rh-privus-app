<?php
/**
 * Funções Auxiliares para Sistema de Engajamento
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

/**
 * Verifica se um módulo de engajamento está ativo
 */
function engajamento_modulo_ativo($modulo) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT ativo FROM engajamento_config WHERE modulo = ?");
    $stmt->execute([$modulo]);
    $config = $stmt->fetch();
    return $config && $config['ativo'] == 1;
}

/**
 * Verifica se deve enviar email para um módulo
 */
function engajamento_enviar_email($modulo, $config_especifica = null) {
    if ($config_especifica !== null) {
        return $config_especifica == 1;
    }
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT enviar_email FROM engajamento_config WHERE modulo = ?");
    $stmt->execute([$modulo]);
    $config = $stmt->fetch();
    return $config && $config['enviar_email'] == 1;
}

/**
 * Verifica se deve enviar push para um módulo
 */
function engajamento_enviar_push($modulo, $config_especifica = null) {
    if ($config_especifica !== null) {
        return $config_especifica == 1;
    }
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT enviar_push FROM engajamento_config WHERE modulo = ?");
    $stmt->execute([$modulo]);
    $config = $stmt->fetch();
    return $config && $config['enviar_push'] == 1;
}

/**
 * Registra acesso no histórico
 */
function registrar_acesso($usuario_id = null, $colaborador_id = null) {
    try {
        $pdo = getDB();
        
        $data_acesso = date('Y-m-d');
        $hora_acesso = date('H:i:s');
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO acessos_historico (usuario_id, colaborador_id, data_acesso, hora_acesso, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $colaborador_id, $data_acesso, $hora_acesso, $ip_address, $user_agent]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao registrar acesso: " . $e->getMessage());
        return false;
    }
}

/**
 * Gera token único para link de resposta rápida
 */
function gerar_token_pesquisa() {
    return bin2hex(random_bytes(32));
}

/**
 * Busca colaboradores para público alvo de pesquisa
 */
function buscar_colaboradores_publico_alvo($publico_alvo, $empresa_id = null, $setor_id = null, $cargo_id = null, $participantes_ids = null) {
    $pdo = getDB();
    $where = ["c.status = 'ativo'"];
    $params = [];
    
    if ($publico_alvo === 'empresa' && $empresa_id) {
        $where[] = "c.empresa_id = ?";
        $params[] = $empresa_id;
    } elseif ($publico_alvo === 'setor' && $setor_id) {
        $where[] = "c.setor_id = ?";
        $params[] = $setor_id;
    } elseif ($publico_alvo === 'cargo' && $cargo_id) {
        $where[] = "c.cargo_id = ?";
        $params[] = $cargo_id;
    } elseif ($publico_alvo === 'especifico' && $participantes_ids) {
        $ids = json_decode($participantes_ids, true);
        if (is_array($ids) && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "c.id IN ($placeholders)";
            $params = array_merge($params, $ids);
        } else {
            return []; // Sem IDs específicos, retorna vazio
        }
    }
    
    $sql = "SELECT c.* FROM colaboradores c WHERE " . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Envia notificação de pesquisa para colaboradores
 */
function enviar_notificacao_pesquisa($pesquisa_id, $tipo = 'satisfacao', $enviar_email = true, $enviar_push = true) {
    require_once __DIR__ . '/push_notifications.php';
    require_once __DIR__ . '/email.php';
    
    $pdo = getDB();
    
    if ($tipo === 'satisfacao') {
        $stmt = $pdo->prepare("
            SELECT ps.*, 
                   GROUP_CONCAT(DISTINCT pse.colaborador_id) as colaboradores_ids
            FROM pesquisas_satisfacao ps
            LEFT JOIN pesquisas_satisfacao_envios pse ON ps.id = pse.pesquisa_id
            WHERE ps.id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT pr.*,
                   GROUP_CONCAT(DISTINCT pre.colaborador_id) as colaboradores_ids
            FROM pesquisas_rapidas pr
            LEFT JOIN pesquisas_rapidas_envios pre ON pr.id = pre.pesquisa_id
            WHERE pr.id = ?
        ");
    }
    
    $stmt->execute([$pesquisa_id]);
    $pesquisa = $stmt->fetch();
    
    if (!$pesquisa) {
        return false;
    }
    
    // Busca colaboradores do público alvo
    $colaboradores = buscar_colaboradores_publico_alvo(
        $pesquisa['publico_alvo'],
        $pesquisa['empresa_id'],
        $pesquisa['setor_id'],
        $pesquisa['cargo_id'],
        $pesquisa['participantes_ids']
    );
    
    $base_url = get_base_url();
    $link_resposta = $base_url . '/pages/responder_pesquisa.php?token=' . $pesquisa['link_token'];
    
    foreach ($colaboradores as $colab) {
        // Gera token único para este colaborador
        $token_colaborador = gerar_token_pesquisa();
        
        // Link específico para este colaborador
        $link_resposta_colab = $base_url . '/pages/responder_pesquisa.php?token=' . $token_colaborador;
        
        // Envia email
        if ($enviar_email && engajamento_enviar_email($tipo === 'satisfacao' ? 'pesquisas_satisfacao' : 'pesquisas_rapidas', $pesquisa['enviar_email'])) {
            $assunto = "Nova Pesquisa: " . $pesquisa['titulo'];
            $mensagem = "
                <h2>Olá, {$colab['nome_completo']}!</h2>
                <p>Uma nova pesquisa foi criada e sua participação é importante.</p>
                <p><strong>{$pesquisa['titulo']}</strong></p>
                " . ($pesquisa['descricao'] ? "<p>{$pesquisa['descricao']}</p>" : "") . "
                <p><a href='{$link_resposta_colab}' style='background-color: #009ef7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>Responder Pesquisa</a></p>
            ";
            
            enviar_email($colab['email_pessoal'], $assunto, $mensagem);
        }
        
        // Envia push
        if ($enviar_push && engajamento_enviar_push($tipo === 'satisfacao' ? 'pesquisas_satisfacao' : 'pesquisas_rapidas', $pesquisa['enviar_push'])) {
            $titulo = "Nova Pesquisa";
            $mensagem_push = $pesquisa['titulo'];
            
            enviar_push_colaborador($colab['id'], $titulo, $mensagem_push, $link_resposta_colab);
        }
        
        // Registra envio com token único
        if ($tipo === 'satisfacao') {
            $stmt_envio = $pdo->prepare("
                INSERT INTO pesquisas_satisfacao_envios (pesquisa_id, colaborador_id, token_resposta, email_enviado, push_enviado)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    token_resposta = VALUES(token_resposta),
                    email_enviado = VALUES(email_enviado),
                    push_enviado = VALUES(push_enviado)
            ");
            $stmt_envio->execute([$pesquisa_id, $colab['id'], $token_colaborador, $enviar_email ? 1 : 0, $enviar_push ? 1 : 0]);
        } else {
            $stmt_envio = $pdo->prepare("
                INSERT INTO pesquisas_rapidas_envios (pesquisa_id, colaborador_id, token_resposta, email_enviado, push_enviado)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    token_resposta = VALUES(token_resposta),
                    email_enviado = VALUES(email_enviado),
                    push_enviado = VALUES(push_enviado)
            ");
            $stmt_envio->execute([$pesquisa_id, $colab['id'], $token_colaborador, $enviar_email ? 1 : 0, $enviar_push ? 1 : 0]);
        }
    }
    
    return true;
}

/**
 * Calcula progresso do PDI
 */
function calcular_progresso_pdi($pdi_id) {
    $pdo = getDB();
    
    // Conta objetivos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pdi_objetivos WHERE pdi_id = ?");
    $stmt->execute([$pdi_id]);
    $total_objetivos = $stmt->fetch()['total'];
    
    if ($total_objetivos == 0) {
        return 0;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as concluidos FROM pdi_objetivos WHERE pdi_id = ? AND status = 'concluido'");
    $stmt->execute([$pdi_id]);
    $objetivos_concluidos = $stmt->fetch()['concluidos'];
    
    // Conta ações
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pdi_acoes WHERE pdi_id = ?");
    $stmt->execute([$pdi_id]);
    $total_acoes = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as concluidas FROM pdi_acoes WHERE pdi_id = ? AND status = 'concluido'");
    $stmt->execute([$pdi_id]);
    $acoes_concluidas = $stmt->fetch()['concluidas'];
    
    $total_itens = $total_objetivos + $total_acoes;
    if ($total_itens == 0) {
        return 0;
    }
    
    $itens_concluidos = $objetivos_concluidos + $acoes_concluidas;
    $progresso = round(($itens_concluidos / $total_itens) * 100);
    
    // Atualiza progresso no PDI
    $stmt = $pdo->prepare("UPDATE pdis SET progresso_percentual = ? WHERE id = ?");
    $stmt->execute([$progresso, $pdi_id]);
    
    return $progresso;
}

