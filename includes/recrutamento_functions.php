<?php
/**
 * Funções Auxiliares - Sistema de Recrutamento e Seleção
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

/**
 * Gera token único para acompanhamento de candidatura
 */
function gerar_token_acompanhamento() {
    return bin2hex(random_bytes(32));
}

/**
 * Busca candidatura por token de acompanhamento
 */
function buscar_candidatura_por_token($token) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT c.*, 
               v.titulo as vaga_titulo,
               v.empresa_id,
               cand.nome_completo as candidato_nome,
               cand.email as candidato_email
        FROM candidaturas c
        INNER JOIN vagas v ON c.vaga_id = v.id
        INNER JOIN candidatos cand ON c.candidato_id = cand.id
        WHERE c.token_acompanhamento = ?
    ");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Busca etapas de uma vaga (jornada configurável)
 */
function buscar_etapas_vaga($vaga_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT e.*, ve.ordem as ordem_vaga, ve.obrigatoria as obrigatoria_vaga
        FROM processo_seletivo_etapas e
        INNER JOIN vagas_etapas ve ON e.id = ve.etapa_id
        WHERE ve.vaga_id = ?
        ORDER BY ve.ordem ASC
    ");
    $stmt->execute([$vaga_id]);
    return $stmt->fetchAll();
}

/**
 * Busca etapas padrão (quando vaga não tem etapas específicas)
 */
function buscar_etapas_padrao() {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT * FROM processo_seletivo_etapas
        WHERE vaga_id IS NULL AND ativo = 1
        ORDER BY ordem ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Cria primeira etapa automaticamente para candidatura
 */
function criar_etapas_iniciais_candidatura($candidatura_id, $vaga_id) {
    $pdo = getDB();
    $etapas = buscar_etapas_vaga($vaga_id);
    
    if (empty($etapas)) {
        $etapas = buscar_etapas_padrao();
    }
    
    if (empty($etapas)) {
        return false;
    }
    
    // Cria primeira etapa como pendente
    $primeira_etapa = $etapas[0];
    
    $stmt = $pdo->prepare("
        INSERT INTO candidaturas_etapas (candidatura_id, etapa_id, status)
        VALUES (?, ?, 'pendente')
    ");
    $stmt->execute([$candidatura_id, $primeira_etapa['id']]);
    
    // Atualiza etapa atual da candidatura
    $stmt = $pdo->prepare("
        UPDATE candidaturas 
        SET etapa_atual_id = ?, coluna_kanban = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $primeira_etapa['id'],
        $primeira_etapa['codigo'],
        $candidatura_id
    ]);
    
    return true;
}

/**
 * Executa automações ao mover candidatura para coluna
 */
function executar_automatizacoes_kanban($candidatura_id, $coluna_codigo) {
    $pdo = getDB();
    
    // Busca coluna
    $stmt = $pdo->prepare("SELECT id FROM kanban_colunas WHERE codigo = ?");
    $stmt->execute([$coluna_codigo]);
    $coluna = $stmt->fetch();
    
    if (!$coluna) {
        return false;
    }
    
    // Busca automações da coluna
    $stmt = $pdo->prepare("
        SELECT * FROM kanban_automatizacoes
        WHERE coluna_id = ? AND ativo = 1
    ");
    $stmt->execute([$coluna['id']]);
    $automatizacoes = $stmt->fetchAll();
    
    // Busca dados da candidatura
    $stmt = $pdo->prepare("
        SELECT c.*, 
               cand.nome_completo, cand.email, cand.telefone,
               v.titulo as vaga_titulo, v.empresa_id,
               e.nome_fantasia as empresa_nome
        FROM candidaturas c
        INNER JOIN candidatos cand ON c.candidato_id = cand.id
        INNER JOIN vagas v ON c.vaga_id = v.id
        LEFT JOIN empresas e ON v.empresa_id = e.id
        WHERE c.id = ?
    ");
    $stmt->execute([$candidatura_id]);
    $candidatura = $stmt->fetch();
    
    foreach ($automatizacoes as $automacao) {
        executar_automacao($automacao, $candidatura);
    }
    
    return true;
}

/**
 * Executa uma automação específica
 */
function executar_automacao($automacao, $candidatura) {
    $config = json_decode($automacao['configuracao'], true);
    $condicoes = json_decode($automacao['condicoes'], true);
    
    // Verifica condições
    if (!verificar_condicoes_automacao($condicoes, $candidatura)) {
        return false;
    }
    
    switch ($automacao['tipo']) {
        case 'email_candidato':
            enviar_email_candidato($candidatura, $config);
            break;
        case 'email_recrutador':
            enviar_email_recrutador($candidatura, $config);
            break;
        case 'notificacao_sistema':
            criar_notificacao_sistema($candidatura, $config);
            break;
        case 'criar_tarefa':
            criar_tarefa_onboarding($candidatura, $config);
            break;
        case 'enviar_aprovacao':
            enviar_email_aprovacao($candidatura, $config);
            break;
        case 'enviar_rejeicao':
            enviar_email_rejeicao($candidatura, $config);
            break;
    }
    
    return true;
}

/**
 * Verifica condições de automação
 */
function verificar_condicoes_automacao($condicoes, $candidatura) {
    if (empty($condicoes)) {
        return true;
    }
    
    // Condição: ao entrar na coluna
    if (isset($condicoes['ao_entrar_coluna']) && $condicoes['ao_entrar_coluna']) {
        return true;
    }
    
    // Condição: dias sem atualização
    if (isset($condicoes['dias_sem_atualizacao'])) {
        $dias = (int)$condicoes['dias_sem_atualizacao'];
        $data_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
        if ($candidatura['updated_at'] < $data_limite) {
            return true;
        }
    }
    
    // Condição: nota mínima
    if (isset($condicoes['nota_minima'])) {
        if ($candidatura['nota_geral'] >= $condicoes['nota_minima']) {
            return true;
        }
    }
    
    return false;
}

/**
 * Envia email ao candidato
 */
function enviar_email_candidato($candidatura, $config) {
    require_once __DIR__ . '/email_templates.php';
    
    $template_codigo = $config['template'] ?? 'confirmacao_candidatura';
    
    // Prepara variáveis para o template
    $variaveis = preparar_variaveis_email_candidatura($candidatura);
    
    // Usa assunto customizado se fornecido, senão usa do template
    $opcoes = [];
    if (!empty($config['assunto'])) {
        // Se tiver assunto customizado, substitui variáveis manualmente
        $assunto = str_replace(['{nome_completo}', '{vaga_titulo}', '{empresa_nome}'], 
            [$variaveis['nome_completo'], $variaveis['vaga_titulo'], $variaveis['empresa_nome']], 
            $config['assunto']);
        $opcoes['assunto_customizado'] = $assunto;
    }
    
    // Envia usando o sistema de templates
    $resultado = enviar_email_template($template_codigo, $candidatura['email'], $variaveis, $opcoes);
    
    // Se o template não existir, usa fallback
    if (!$resultado['success']) {
        require_once __DIR__ . '/email.php';
        $assunto = $config['assunto'] ?? 'Atualização da sua candidatura';
        $corpo = obter_template_email_recrutamento($template_codigo, $candidatura);
        enviar_email($candidatura['email'], $assunto, $corpo);
    }
    
    return $resultado;
}

/**
 * Envia email ao recrutador
 */
function enviar_email_recrutador($candidatura, $config) {
    if (empty($candidatura['recrutador_responsavel'])) {
        return false;
    }
    
    require_once __DIR__ . '/email_templates.php';
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt->execute([$candidatura['recrutador_responsavel']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        return false;
    }
    
    // Prepara variáveis para o template
    $variaveis = preparar_variaveis_email_candidatura($candidatura);
    $variaveis['link_candidatura'] = get_base_url() . "/pages/candidatura_view.php?id={$candidatura['id']}";
    
    // Usa template padrão ou customizado
    $template_codigo = $config['template'] ?? 'nova_candidatura_recrutador';
    
    // Usa assunto customizado se fornecido
    $opcoes = [];
    if (!empty($config['assunto'])) {
        $assunto = str_replace(['{nome_completo}', '{vaga_titulo}', '{empresa_nome}'], 
            [$variaveis['nome_completo'], $variaveis['vaga_titulo'], $variaveis['empresa_nome']], 
            $config['assunto']);
        $opcoes['assunto_customizado'] = $assunto;
    }
    
    // Envia usando o sistema de templates
    $resultado = enviar_email_template($template_codigo, $usuario['email'], $variaveis, $opcoes);
    
    // Se o template não existir, usa fallback
    if (!$resultado['success']) {
        require_once __DIR__ . '/email.php';
        $assunto = $config['assunto'] ?? 'Nova candidatura recebida';
        $corpo = "Nova candidatura recebida para a vaga: {$candidatura['vaga_titulo']}\n\n";
        $corpo .= "Candidato: {$candidatura['nome_completo']}\n";
        $corpo .= "Email: {$candidatura['email']}\n";
        $corpo .= "\nVer candidatura: " . get_base_url() . "/pages/candidatura_view.php?id={$candidatura['id']}";
        enviar_email($usuario['email'], $assunto, $corpo);
        return ['success' => true];
    }
    
    return $resultado;
}

/**
 * Cria notificação no sistema
 */
function criar_notificacao_sistema($candidatura, $config) {
    $pdo = getDB();
    
    $destinatarios = $config['destinatarios'] ?? ['recrutador_responsavel'];
    
    foreach ($destinatarios as $destinatario) {
        $usuario_id = null;
        
        if ($destinatario === 'recrutador_responsavel' && $candidatura['recrutador_responsavel']) {
            $usuario_id = $candidatura['recrutador_responsavel'];
        }
        
        if ($usuario_id) {
            $stmt = $pdo->prepare("
                INSERT INTO notificacoes_sistema 
                (usuario_id, titulo, mensagem, tipo, link, lida)
                VALUES (?, ?, ?, 'recrutamento', ?, 0)
            ");
            $stmt->execute([
                $usuario_id,
                'Nova candidatura',
                "Nova candidatura para: {$candidatura['vaga_titulo']}",
                "/rh-privus/pages/candidatura_view.php?id={$candidatura['id']}"
            ]);
        }
    }
}

/**
 * Cria processo de onboarding
 */
function criar_tarefa_onboarding($candidatura, $config) {
    $pdo = getDB();
    
    // Verifica se já existe onboarding
    $stmt = $pdo->prepare("SELECT id FROM onboarding WHERE candidatura_id = ?");
    $stmt->execute([$candidatura['id']]);
    if ($stmt->fetch()) {
        return false; // Já existe
    }
    
    // Cria onboarding
    $stmt = $pdo->prepare("
        INSERT INTO onboarding 
        (candidatura_id, status, coluna_kanban, data_inicio, responsavel_id)
        VALUES (?, 'contratado', 'contratado', CURDATE(), ?)
    ");
    $stmt->execute([
        $candidatura['id'],
        $candidatura['recrutador_responsavel'] ?? 1
    ]);
    
    return true;
}

/**
 * Envia email de aprovação
 */
function enviar_email_aprovacao($candidatura, $config) {
    require_once __DIR__ . '/email_templates.php';
    
    $template_codigo = $config['template'] ?? 'aprovacao';
    
    // Prepara variáveis para o template
    $variaveis = preparar_variaveis_email_candidatura($candidatura);
    $variaveis['data_aprovacao'] = !empty($candidatura['data_aprovacao']) 
        ? date('d/m/Y', strtotime($candidatura['data_aprovacao'])) 
        : date('d/m/Y');
    
    // Usa assunto customizado se fornecido
    $opcoes = [];
    if (!empty($config['assunto'])) {
        $assunto = str_replace(['{nome_completo}', '{vaga_titulo}', '{empresa_nome}'], 
            [$variaveis['nome_completo'], $variaveis['vaga_titulo'], $variaveis['empresa_nome']], 
            $config['assunto']);
        $opcoes['assunto_customizado'] = $assunto;
    }
    
    // Envia usando o sistema de templates
    $resultado = enviar_email_template($template_codigo, $candidatura['email'], $variaveis, $opcoes);
    
    // Se o template não existir, usa fallback
    if (!$resultado['success']) {
        require_once __DIR__ . '/email.php';
        $assunto = $config['assunto'] ?? 'Parabéns! Você foi aprovado!';
        $corpo = obter_template_email_recrutamento($template_codigo, $candidatura);
        enviar_email($candidatura['email'], $assunto, $corpo);
        return ['success' => true];
    }
    
    return $resultado;
}

/**
 * Envia email de rejeição
 */
function enviar_email_rejeicao($candidatura, $config) {
    require_once __DIR__ . '/email_templates.php';
    
    $template_codigo = $config['template'] ?? 'rejeicao';
    
    // Prepara variáveis para o template
    $variaveis = preparar_variaveis_email_candidatura($candidatura);
    $variaveis['data_rejeicao'] = !empty($candidatura['data_reprovacao']) 
        ? date('d/m/Y', strtotime($candidatura['data_reprovacao'])) 
        : date('d/m/Y');
    
    // Adiciona motivo da rejeição se existir
    $variaveis['motivo_rejeicao'] = '';
    if (!empty($candidatura['motivo_reprovacao'])) {
        $variaveis['motivo_rejeicao'] = '<p><strong>Motivo:</strong> ' . nl2br(htmlspecialchars($candidatura['motivo_reprovacao'])) . '</p>';
    }
    
    // Usa assunto customizado se fornecido
    $opcoes = [];
    if (!empty($config['assunto'])) {
        $assunto = str_replace(['{nome_completo}', '{vaga_titulo}', '{empresa_nome}'], 
            [$variaveis['nome_completo'], $variaveis['vaga_titulo'], $variaveis['empresa_nome']], 
            $config['assunto']);
        $opcoes['assunto_customizado'] = $assunto;
    }
    
    // Envia usando o sistema de templates
    $resultado = enviar_email_template($template_codigo, $candidatura['email'], $variaveis, $opcoes);
    
    // Se o template não existir, usa fallback
    if (!$resultado['success']) {
        require_once __DIR__ . '/email.php';
        $assunto = $config['assunto'] ?? 'Atualização sobre sua candidatura';
        $corpo = obter_template_email_recrutamento($template_codigo, $candidatura);
        enviar_email($candidatura['email'], $assunto, $corpo);
        return ['success' => true];
    }
    
    return $resultado;
}

/**
 * Prepara variáveis para templates de email de candidatura
 */
function preparar_variaveis_email_candidatura($candidatura) {
    $pdo = getDB();
    
    // Busca dados adicionais se necessário
    $empresa_nome = '';
    if (!empty($candidatura['empresa_id'])) {
        $stmt = $pdo->prepare("SELECT nome_fantasia FROM empresas WHERE id = ?");
        $stmt->execute([$candidatura['empresa_id']]);
        $empresa = $stmt->fetch();
        $empresa_nome = $empresa['nome_fantasia'] ?? '';
    }
    
    // Formata data de candidatura
    $data_candidatura = '';
    if (!empty($candidatura['data_candidatura'])) {
        $data_candidatura = date('d/m/Y', strtotime($candidatura['data_candidatura']));
    } elseif (!empty($candidatura['created_at'])) {
        $data_candidatura = date('d/m/Y', strtotime($candidatura['created_at']));
    }
    
    return [
        'nome_completo' => $candidatura['nome_completo'] ?? '',
        'nome' => $candidatura['nome_completo'] ?? '',
        'email' => $candidatura['email'] ?? '',
        'telefone' => $candidatura['telefone'] ?? '',
        'vaga_titulo' => $candidatura['vaga_titulo'] ?? '',
        'vaga' => $candidatura['vaga_titulo'] ?? '',
        'empresa_nome' => $empresa_nome ?: ($candidatura['empresa_nome'] ?? ''),
        'data_candidatura' => $data_candidatura,
        'link_acompanhamento' => get_base_url() . "/acompanhar.php?token=" . ($candidatura['token_acompanhamento'] ?? ''),
        'status' => $candidatura['status'] ?? '',
        'nota_geral' => $candidatura['nota_geral'] ?? ''
    ];
}

/**
 * Obtém template de email de recrutamento (fallback)
 * Mantida para compatibilidade com código antigo
 */
function obter_template_email_recrutamento($template, $candidatura) {
    require_once __DIR__ . '/email_templates.php';
    
    $variaveis = preparar_variaveis_email_candidatura($candidatura);
    
    // Tenta buscar template
    $template_data = buscar_template_email($template);
    
    if ($template_data) {
        require_once __DIR__ . '/email_templates.php';
        $corpo = substituir_variaveis($template_data['corpo_html'] ?: $template_data['corpo_texto'], $variaveis);
        return $corpo;
    }
    
    // Template padrão fallback
    $corpo = "Olá {$variaveis['nome_completo']},\n\n";
    $corpo .= "Sua candidatura para a vaga '{$variaveis['vaga_titulo']}' foi atualizada.\n\n";
    $corpo .= "Acompanhe seu processo: {$variaveis['link_acompanhamento']}\n";
    
    return $corpo;
}

/**
 * Registra histórico de candidatura
 */
function registrar_historico_candidatura($candidatura_id, $acao, $usuario_id = null, $campo_alterado = null, $valor_anterior = null, $valor_novo = null, $observacoes = null) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        INSERT INTO candidaturas_historico 
        (candidatura_id, usuario_id, acao, campo_alterado, valor_anterior, valor_novo, observacoes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $candidatura_id,
        $usuario_id,
        $acao,
        $campo_alterado,
        $valor_anterior,
        $valor_novo,
        $observacoes
    ]);
}

/**
 * Calcula nota geral do candidato
 */
function calcular_nota_geral_candidatura($candidatura_id) {
    $pdo = getDB();
    
    // Busca todas as notas das etapas
    $stmt = $pdo->prepare("
        SELECT AVG(nota) as nota_media
        FROM candidaturas_etapas
        WHERE candidatura_id = ? AND nota IS NOT NULL
    ");
    $stmt->execute([$candidatura_id]);
    $resultado = $stmt->fetch();
    
    $nota_geral = $resultado['nota_media'] ? round($resultado['nota_media'], 1) : null;
    
    // Atualiza nota geral
    $stmt = $pdo->prepare("
        UPDATE candidaturas 
        SET nota_geral = ?
        WHERE id = ?
    ");
    $stmt->execute([$nota_geral, $candidatura_id]);
    
    return $nota_geral;
}

/**
 * Busca entrevistas sem candidatura para Kanban
 */
function buscar_entrevistas_kanban($filtros = []) {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'] ?? null;
    
    $where = ["e.candidatura_id IS NULL"];
    $params = [];
    
    // Filtro por vaga
    if (!empty($filtros['vaga_id'])) {
        $where[] = "e.vaga_id_manual = ?";
        $params[] = $filtros['vaga_id'];
    }
    
    // Filtro por coluna
    if (!empty($filtros['coluna'])) {
        $where[] = "e.coluna_kanban = ?";
        $params[] = $filtros['coluna'];
    }
    
    // Permissões por empresa
    if ($usuario && $usuario['role'] === 'RH') {
        if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
            $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
            // Se não tem vaga manual, não filtra por empresa (mostra todas)
            // Se tem vaga manual, filtra pela empresa da vaga
            $where[] = "(e.vaga_id_manual IS NULL OR v.empresa_id IN ($placeholders))";
            $params = array_merge($params, $usuario['empresas_ids']);
        }
    }
    
    $sql = "
        SELECT 
            CONCAT('entrevista_', e.id) as id,
            e.vaga_id_manual as vaga_id,
            e.candidato_nome_manual as nome_completo,
            e.candidato_email_manual as email,
            v.titulo as vaga_titulo,
            v.empresa_id,
            emp.nome_fantasia as empresa_nome,
            u.nome as recrutador_nome,
            COALESCE(e.coluna_kanban, 'entrevistas') as coluna_kanban,
            e.status,
            e.data_agendada as data_candidatura,
            e.created_at,
            e.titulo as entrevista_titulo,
            1 as is_entrevista_manual
        FROM entrevistas e
        LEFT JOIN vagas v ON e.vaga_id_manual = v.id
        LEFT JOIN empresas emp ON v.empresa_id = emp.id
        LEFT JOIN usuarios u ON e.entrevistador_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Busca candidaturas para Kanban (inclui entrevistas sem candidatura)
 */
function buscar_candidaturas_kanban($filtros = []) {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'] ?? null;
    
    $where = ["1=1"];
    $params = [];
    
    // Filtro por vaga
    if (!empty($filtros['vaga_id'])) {
        $where[] = "c.vaga_id = ?";
        $params[] = $filtros['vaga_id'];
    }
    
    // Filtro por coluna
    if (!empty($filtros['coluna'])) {
        $where[] = "c.coluna_kanban = ?";
        $params[] = $filtros['coluna'];
    }
    
    // Filtro por recrutador
    if (!empty($filtros['recrutador_id'])) {
        $where[] = "c.recrutador_responsavel = ?";
        $params[] = $filtros['recrutador_id'];
    }
    
    // Filtro por status (array)
    if (!empty($filtros['status']) && is_array($filtros['status'])) {
        $placeholders = implode(',', array_fill(0, count($filtros['status']), '?'));
        $where[] = "c.status IN ($placeholders)";
        $params = array_merge($params, $filtros['status']);
    }
    
    // Permissões por empresa
    if ($usuario && $usuario['role'] === 'RH') {
        if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
            $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
            $where[] = "v.empresa_id IN ($placeholders)";
            $params = array_merge($params, $usuario['empresas_ids']);
        }
    }
    
    $sql = "
        SELECT c.*,
               cand.nome_completo,
               cand.email,
               v.titulo as vaga_titulo,
               v.empresa_id,
               e.nome_fantasia as empresa_nome,
               u.nome as recrutador_nome,
               0 as is_entrevista_manual
        FROM candidaturas c
        INNER JOIN candidatos cand ON c.candidato_id = cand.id
        INNER JOIN vagas v ON c.vaga_id = v.id
        LEFT JOIN empresas e ON v.empresa_id = e.id
        LEFT JOIN usuarios u ON c.recrutador_responsavel = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidaturas = $stmt->fetchAll();
    
    // Busca também entrevistas sem candidatura
    $entrevistas = buscar_entrevistas_kanban($filtros);
    
    // Combina resultados
    return array_merge($candidaturas, $entrevistas);
}

/**
 * Busca colunas do Kanban
 */
function buscar_colunas_kanban() {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT * FROM kanban_colunas
        WHERE ativo = 1
        ORDER BY ordem ASC
    ");
    return $stmt->fetchAll();
}

