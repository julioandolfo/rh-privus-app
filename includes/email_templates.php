<?php
/**
 * Sistema de Templates de Email
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/email.php';

/**
 * Substitui variáveis no template
 */
function substituir_variaveis($texto, $variaveis) {
    foreach ($variaveis as $chave => $valor) {
        $texto = str_replace('{' . $chave . '}', $valor ?? '', $texto);
    }
    return $texto;
}

/**
 * Busca template por código
 */
function buscar_template_email($codigo) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE codigo = ? AND ativo = 1");
    $stmt->execute([$codigo]);
    return $stmt->fetch();
}

/**
 * Envia email usando template
 */
function enviar_email_template($codigo_template, $email_destinatario, $variaveis = [], $opcoes = []) {
    $template = buscar_template_email($codigo_template);
    
    if (!$template) {
        return [
            'success' => false,
            'message' => 'Template de email não encontrado ou inativo.'
        ];
    }
    
    // Usa assunto customizado se fornecido nas opções, senão usa do template
    if (!empty($opcoes['assunto_customizado'])) {
        $assunto = $opcoes['assunto_customizado'];
    } else {
        $assunto = substituir_variaveis($template['assunto'], $variaveis);
    }
    
    // Substitui variáveis no corpo
    $corpo_html = substituir_variaveis($template['corpo_html'], $variaveis);
    $corpo_texto = $template['corpo_texto'] ? substituir_variaveis($template['corpo_texto'], $variaveis) : null;
    
    // Prepara opções
    $opcoes_email = array_merge([
        'nome_destinatario' => $variaveis['nome_completo'] ?? '',
        'texto_alternativo' => $corpo_texto
    ], $opcoes);
    
    // Remove assunto_customizado das opções antes de passar para enviar_email
    unset($opcoes_email['assunto_customizado']);
    
    return enviar_email($email_destinatario, $assunto, $corpo_html, $opcoes_email);
}

/**
 * Envia email de boas-vindas para novo colaborador
 * @param int $colaborador_id ID do colaborador
 * @param string|null $senha_plana Senha em texto claro para incluir no email (opcional)
 */
function enviar_email_novo_colaborador($colaborador_id, $senha_plana = null) {
    $pdo = getDB();
    
    // Busca dados do colaborador
    $stmt = $pdo->prepare("
        SELECT c.*, 
               e.nome_fantasia as empresa_nome,
               s.nome_setor,
               car.nome_cargo
        FROM colaboradores c
        LEFT JOIN empresas e ON c.empresa_id = e.id
        LEFT JOIN setores s ON c.setor_id = s.id
        LEFT JOIN cargos car ON c.cargo_id = car.id
        WHERE c.id = ?
    ");
    $stmt->execute([$colaborador_id]);
    $colab = $stmt->fetch();
    
    if (!$colab || empty($colab['email_pessoal'])) {
        return ['success' => false, 'message' => 'Colaborador não encontrado ou sem email cadastrado.'];
    }
    
    // Determina login (CPF ou email)
    $usuario_login = !empty($colab['cpf']) ? formatar_cpf($colab['cpf']) : $colab['email_pessoal'];
    
    // Prepara variáveis
    $variaveis = [
        'nome_completo' => $colab['nome_completo'],
        'empresa_nome' => $colab['empresa_nome'] ?? '',
        'cargo_nome' => $colab['nome_cargo'] ?? '',
        'setor_nome' => $colab['nome_setor'] ?? '',
        'data_inicio' => formatar_data($colab['data_inicio']),
        'tipo_contrato' => $colab['tipo_contrato'],
        'cpf' => formatar_cpf($colab['cpf'] ?? ''),
        'email_pessoal' => $colab['email_pessoal'],
        'telefone' => $colab['telefone'] ?? '',
        'usuario_login' => $usuario_login,
        'senha' => $senha_plana ?? '',
        'dados_acesso_html' => ''
    ];
    
    // Se houver senha, prepara HTML e texto com dados de acesso
    if (!empty($senha_plana)) {
        $variaveis['dados_acesso_html'] = '
            <div style="background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h3 style="margin-top: 0; color: #0d6efd;">Dados de Acesso ao Sistema</h3>
                <p style="margin-bottom: 10px;"><strong>Usuário:</strong> ' . htmlspecialchars($usuario_login) . '</p>
                <p style="margin-bottom: 10px;"><strong>Senha:</strong> <code style="background-color: #e9ecef; padding: 4px 8px; border-radius: 3px;">' . htmlspecialchars($senha_plana) . '</code></p>
                <p style="margin-bottom: 0; color: #6c757d; font-size: 14px;"><em>Guarde estas informações com segurança. Você pode alterar sua senha após o primeiro acesso.</em></p>
            </div>
        ';
        $variaveis['dados_acesso_texto'] = "\n\nDADOS DE ACESSO AO SISTEMA:\nUsuário: " . $usuario_login . "\nSenha: " . $senha_plana . "\n\nGuarde estas informações com segurança. Você pode alterar sua senha após o primeiro acesso.";
    } else {
        $variaveis['dados_acesso_texto'] = '';
    }
    
    return enviar_email_template('novo_colaborador', $colab['email_pessoal'], $variaveis);
}

/**
 * Envia email de nova promoção
 */
function enviar_email_nova_promocao($promocao_id) {
    $pdo = getDB();
    
    // Busca dados da promoção
    $stmt = $pdo->prepare("
        SELECT p.*, 
               c.nome_completo, c.email_pessoal,
               e.nome_fantasia as empresa_nome,
               u.nome as usuario_nome
        FROM promocoes p
        INNER JOIN colaboradores c ON p.colaborador_id = c.id
        LEFT JOIN empresas e ON c.empresa_id = e.id
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$promocao_id]);
    $promocao = $stmt->fetch();
    
    if (!$promocao || empty($promocao['email_pessoal'])) {
        return ['success' => false, 'message' => 'Promoção não encontrada ou colaborador sem email.'];
    }
    
    // Prepara variáveis
    $variaveis = [
        'nome_completo' => $promocao['nome_completo'],
        'data_promocao' => formatar_data($promocao['data_promocao']),
        'salario_anterior' => number_format($promocao['salario_anterior'], 2, ',', '.'),
        'salario_novo' => number_format($promocao['salario_novo'], 2, ',', '.'),
        'motivo' => $promocao['motivo'],
        'observacoes' => $promocao['observacoes'] ? '<p><strong>Observações:</strong> ' . nl2br(htmlspecialchars($promocao['observacoes'])) . '</p>' : '',
        'empresa_nome' => $promocao['empresa_nome'] ?? ''
    ];
    
    return enviar_email_template('nova_promocao', $promocao['email_pessoal'], $variaveis);
}

/**
 * Envia email de fechamento de pagamento
 */
function enviar_email_fechamento_pagamento($fechamento_id, $colaborador_id) {
    $pdo = getDB();
    
    // Busca dados do fechamento e colaborador
    $stmt = $pdo->prepare("
        SELECT f.*, 
               c.nome_completo, c.email_pessoal,
               e.nome_fantasia as empresa_nome,
               i.salario_base, i.horas_extras, i.valor_horas_extras, 
               i.descontos, i.adicionais, i.valor_total, i.observacoes
        FROM fechamentos_pagamento f
        INNER JOIN fechamentos_pagamento_itens i ON f.id = i.fechamento_id
        INNER JOIN colaboradores c ON i.colaborador_id = c.id
        LEFT JOIN empresas e ON f.empresa_id = e.id
        WHERE f.id = ? AND c.id = ?
    ");
    $stmt->execute([$fechamento_id, $colaborador_id]);
    $dados = $stmt->fetch();
    
    if (!$dados || empty($dados['email_pessoal'])) {
        return ['success' => false, 'message' => 'Dados não encontrados ou colaborador sem email.'];
    }
    
    // Formata mês de referência
    $mes_ref = explode('-', $dados['mes_referencia']);
    $mes_formatado = date('m/Y', strtotime($mes_ref[0] . '-' . $mes_ref[1] . '-01'));
    
    // Prepara variáveis
    $variaveis = [
        'nome_completo' => $dados['nome_completo'],
        'mes_referencia' => $mes_formatado,
        'salario_base' => number_format($dados['salario_base'], 2, ',', '.'),
        'horas_extras' => number_format($dados['horas_extras'], 2, ',', '.'),
        'valor_horas_extras' => number_format($dados['valor_horas_extras'], 2, ',', '.'),
        'descontos' => number_format($dados['descontos'], 2, ',', '.'),
        'adicionais' => number_format($dados['adicionais'], 2, ',', '.'),
        'valor_total' => number_format($dados['valor_total'], 2, ',', '.'),
        'data_fechamento' => formatar_data($dados['data_fechamento']),
        'observacoes' => $dados['observacoes'] ? '<p><strong>Observações:</strong> ' . nl2br(htmlspecialchars($dados['observacoes'])) . '</p>' : '',
        'empresa_nome' => $dados['empresa_nome'] ?? ''
    ];
    
    return enviar_email_template('fechamento_pagamento', $dados['email_pessoal'], $variaveis);
}

/**
 * Envia email de ocorrência
 */
function enviar_email_ocorrencia($ocorrencia_id) {
    $pdo = getDB();
    
    // Busca dados da ocorrência
    $stmt = $pdo->prepare("
        SELECT o.*, 
               c.nome_completo, c.email_pessoal, c.setor_id,
               e.nome_fantasia as empresa_nome,
               s.nome_setor,
               car.nome_cargo,
               u.nome as usuario_nome,
               t.nome as tipo_ocorrencia_nome,
               t.notificar_colaborador_email, t.notificar_gestor_email, t.notificar_rh_email
        FROM ocorrencias o
        INNER JOIN colaboradores c ON o.colaborador_id = c.id
        LEFT JOIN empresas e ON c.empresa_id = e.id
        LEFT JOIN setores s ON c.setor_id = s.id
        LEFT JOIN cargos car ON c.cargo_id = car.id
        LEFT JOIN usuarios u ON o.usuario_id = u.id
        LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
        WHERE o.id = ?
    ");
    $stmt->execute([$ocorrencia_id]);
    $ocorrencia = $stmt->fetch();
    
    // Busca gestor do setor se necessário
    $gestor_id = null;
    if (!empty($ocorrencia['setor_id'])) {
        $stmt_gestor = $pdo->prepare("SELECT id FROM usuarios WHERE setor_id = ? AND role = 'GESTOR' AND status = 'ativo' LIMIT 1");
        $stmt_gestor->execute([$ocorrencia['setor_id']]);
        $gestor = $stmt_gestor->fetch();
        if ($gestor) {
            $gestor_id = $gestor['id'];
        }
    }
    $ocorrencia['gestor_id'] = $gestor_id;
    
    if (!$ocorrencia) {
        return ['success' => false, 'message' => 'Ocorrência não encontrada.'];
    }
    
    // Verifica se email está habilitado para colaborador
    if (empty($ocorrencia['email_pessoal']) || !$ocorrencia['notificar_colaborador_email']) {
        return ['success' => false, 'message' => 'Email não habilitado ou colaborador sem email.'];
    }
    
    // Prepara variáveis HTML condicionais
    $hora_ocorrencia_html = '';
    $tempo_atraso_html = '';
    $severidade_html = '';
    $status_aprovacao_html = '';
    $tags_html = '';
    $valor_desconto_html = '';
    
    if (!empty($ocorrencia['hora_ocorrencia'])) {
        $hora_ocorrencia_html = '<li><strong>Hora:</strong> ' . substr($ocorrencia['hora_ocorrencia'], 0, 5) . '</li>';
    }
    
    if (!empty($ocorrencia['tempo_atraso_minutos'])) {
        $horas = floor($ocorrencia['tempo_atraso_minutos'] / 60);
        $minutos = $ocorrencia['tempo_atraso_minutos'] % 60;
        $tempo_atraso_html = '<li><strong>Tempo de Atraso:</strong> ' . ($horas > 0 ? $horas . 'h ' : '') . $minutos . 'min</li>';
    }
    
    if (!empty($ocorrencia['severidade'])) {
        $severidade_labels = [
            'leve' => 'Leve',
            'moderada' => 'Moderada',
            'grave' => 'Grave',
            'critica' => 'Crítica'
        ];
        $severidade_html = '<li><strong>Severidade:</strong> ' . ($severidade_labels[$ocorrencia['severidade']] ?? ucfirst($ocorrencia['severidade'])) . '</li>';
    }
    
    if (!empty($ocorrencia['status_aprovacao'])) {
        $status_labels = [
            'pendente' => 'Pendente de Aprovação',
            'aprovada' => 'Aprovada',
            'rejeitada' => 'Rejeitada'
        ];
        $status_aprovacao_html = '<li><strong>Status:</strong> ' . ($status_labels[$ocorrencia['status_aprovacao']] ?? ucfirst($ocorrencia['status_aprovacao'])) . '</li>';
    }
    
    if (!empty($ocorrencia['tags'])) {
        require_once __DIR__ . '/ocorrencias_functions.php';
        $tags_array = json_decode($ocorrencia['tags'], true);
        if ($tags_array) {
            $tags_disponiveis = get_tags_ocorrencias();
            $tags_nomes = [];
            foreach ($tags_array as $tag_id) {
                foreach ($tags_disponiveis as $tag) {
                    if ($tag['id'] == $tag_id) {
                        $tags_nomes[] = $tag['nome'];
                        break;
                    }
                }
            }
            if (!empty($tags_nomes)) {
                $tags_html = '<li><strong>Tags:</strong> ' . implode(', ', $tags_nomes) . '</li>';
            }
        }
    }
    
    if (!empty($ocorrencia['valor_desconto']) && $ocorrencia['valor_desconto'] > 0) {
        $valor_desconto_html = '<li><strong>Desconto Calculado:</strong> R$ ' . number_format($ocorrencia['valor_desconto'], 2, ',', '.') . '</li>';
    }
    
    $variaveis = [
        'nome_completo' => $ocorrencia['nome_completo'] ?? '',
        'tipo_ocorrencia' => $ocorrencia['tipo_ocorrencia_nome'] ?? $ocorrencia['tipo'] ?? '',
        'data_ocorrencia' => formatar_data($ocorrencia['data_ocorrencia'] ?? ''),
        'hora_ocorrencia' => $hora_ocorrencia_html,
        'tempo_atraso' => $tempo_atraso_html,
        'severidade' => $severidade_html,
        'status_aprovacao' => $status_aprovacao_html,
        'tags' => $tags_html,
        'valor_desconto' => $valor_desconto_html,
        'descricao' => !empty($ocorrencia['descricao']) ? nl2br(htmlspecialchars($ocorrencia['descricao'])) : '',
        'usuario_registro' => $ocorrencia['usuario_nome'] ?? '',
        'data_registro' => formatar_data($ocorrencia['created_at'] ?? '', 'd/m/Y H:i'),
        'empresa_nome' => $ocorrencia['empresa_nome'] ?? '',
        'setor_nome' => $ocorrencia['nome_setor'] ?? '',
        'cargo_nome' => $ocorrencia['nome_cargo'] ?? ''
    ];
    
    return enviar_email_template('ocorrencia', $ocorrencia['email_pessoal'], $variaveis);
}

