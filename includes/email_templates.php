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
        'texto_alternativo' => $corpo_texto,
        'template_codigo' => $template['codigo'],
        'template_nome' => $template['nome'],
        'origem' => 'template_' . $template['codigo']
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

/**
 * Envia email de novo comunicado para todos colaboradores
 */
function enviar_email_novo_comunicado($comunicado_id) {
    $pdo = getDB();
    
    // Busca dados do comunicado
    $stmt = $pdo->prepare("
        SELECT c.*, u.nome as criado_por_nome, u.empresa_id
        FROM comunicados c
        LEFT JOIN usuarios u ON c.criado_por_usuario_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$comunicado_id]);
    $comunicado = $stmt->fetch();
    
    if (!$comunicado) {
        return [
            'success' => false,
            'message' => 'Comunicado não encontrado.',
            'enviados' => 0,
            'erros' => 0
        ];
    }
    
    // Busca empresa
    $empresa_nome = 'Sistema RH';
    if ($comunicado['empresa_id']) {
        $stmt_empresa = $pdo->prepare("SELECT nome_fantasia FROM empresas WHERE id = ?");
        $stmt_empresa->execute([$comunicado['empresa_id']]);
        $empresa = $stmt_empresa->fetch();
        if ($empresa) {
            $empresa_nome = $empresa['nome_fantasia'];
        }
    }
    
    // Busca todos os colaboradores ativos com email
    $stmt_colab = $pdo->prepare("
        SELECT DISTINCT
            c.id as colaborador_id,
            c.nome_completo,
            c.email_pessoal,
            u.id as usuario_id,
            u.email as usuario_email
        FROM colaboradores c
        LEFT JOIN usuarios u ON c.id = u.colaborador_id
        WHERE c.status = 'ativo'
        AND (
            c.email_pessoal IS NOT NULL AND c.email_pessoal != ''
            OR u.email IS NOT NULL
        )
        ORDER BY c.nome_completo
    ");
    $stmt_colab->execute();
    $colaboradores = $stmt_colab->fetchAll();
    
    if (empty($colaboradores)) {
        return [
            'success' => false,
            'message' => 'Nenhum colaborador com email encontrado.',
            'enviados' => 0,
            'erros' => 0
        ];
    }
    
    // Prepara URL do sistema
    $sistema_url = get_base_url();
    
    // Prepara conteúdo (remove HTML para preview e cria versão texto)
    $conteudo_texto = strip_tags($comunicado['conteudo']);
    $conteudo_preview = mb_substr($conteudo_texto, 0, 300);
    if (mb_strlen($conteudo_texto) > 300) {
        $conteudo_preview .= '...';
    }
    
    // Prepara imagem HTML
    $imagem_html = '';
    if (!empty($comunicado['imagem'])) {
        $imagem_url = rtrim($sistema_url, '/') . '/' . ltrim($comunicado['imagem'], '/');
        $imagem_html = '<div style="margin: 15px 0; text-center;"><img src="' . htmlspecialchars($imagem_url) . '" alt="' . htmlspecialchars($comunicado['titulo']) . '" style="max-width: 100%; height: auto; border-radius: 4px;" /></div>';
    }
    
    // Formata data de publicação
    $data_publicacao = $comunicado['data_publicacao'] 
        ? formatar_data($comunicado['data_publicacao'], 'd/m/Y H:i')
        : formatar_data($comunicado['created_at'], 'd/m/Y H:i');
    
    $enviados = 0;
    $erros = 0;
    
    // Envia email para cada colaborador
    foreach ($colaboradores as $colab) {
        $email_destino = $colab['email_pessoal'] ?? $colab['usuario_email'];
        
        if (empty($email_destino)) {
            continue;
        }
        
        // Prepara variáveis para o template
        $variaveis = [
            'nome_completo' => $colab['nome_completo'],
            'titulo' => $comunicado['titulo'],
            'conteudo_preview' => nl2br(htmlspecialchars($conteudo_preview)),
            'conteudo_texto' => $conteudo_texto,
            'imagem_html' => $imagem_html,
            'criado_por_nome' => $comunicado['criado_por_nome'] ?? 'Administrador',
            'data_publicacao' => $data_publicacao,
            'sistema_url' => $sistema_url,
            'empresa_nome' => $empresa_nome
        ];
        
        // Envia email
        $resultado = enviar_email_template('novo_comunicado', $email_destino, $variaveis);
        
        if ($resultado['success']) {
            $enviados++;
        } else {
            $erros++;
        }
    }
    
    return [
        'success' => true,
        'message' => "Emails enviados: {$enviados}, Erros: {$erros}",
        'enviados' => $enviados,
        'erros' => $erros,
        'total_colaboradores' => count($colaboradores)
    ];
}

/**
 * Envia email de horas extras
 */
function enviar_email_horas_extras($hora_extra_id) {
    $pdo = getDB();
    
    // Busca dados da hora extra
    $stmt = $pdo->prepare("
        SELECT h.*, 
               c.nome_completo, c.email_pessoal, c.setor_id,
               e.nome_fantasia as empresa_nome,
               s.nome_setor,
               car.nome_cargo,
               u.nome as usuario_nome
        FROM horas_extras h
        INNER JOIN colaboradores c ON h.colaborador_id = c.id
        LEFT JOIN empresas e ON c.empresa_id = e.id
        LEFT JOIN setores s ON c.setor_id = s.id
        LEFT JOIN cargos car ON c.cargo_id = car.id
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.id = ?
    ");
    $stmt->execute([$hora_extra_id]);
    $hora_extra = $stmt->fetch();
    
    if (!$hora_extra) {
        return ['success' => false, 'message' => 'Hora extra não encontrada.'];
    }
    
    // Verifica se colaborador tem email
    if (empty($hora_extra['email_pessoal'])) {
        return ['success' => false, 'message' => 'Colaborador sem email cadastrado.'];
    }
    
    // Verifica se template está ativo
    $template = buscar_template_email('horas_extras');
    if (!$template || !$template['ativo']) {
        return ['success' => false, 'message' => 'Template de email não encontrado ou inativo.'];
    }
    
    // Prepara variáveis HTML condicionais
    $tipo_pagamento_html = '';
    $valor_hora_html = '';
    $percentual_adicional_html = '';
    $valor_total_html = '';
    $saldo_banco_html = '';
    $observacoes_html = '';
    
    $tipo_pagamento = $hora_extra['tipo_pagamento'] ?? 'dinheiro';
    
    if ($tipo_pagamento === 'banco_horas') {
        $tipo_pagamento_html = '<li><strong>Tipo de Pagamento:</strong> Banco de Horas</li>';
        
        // Busca saldo atual do banco de horas
        require_once __DIR__ . '/banco_horas_functions.php';
        $saldo = get_or_create_saldo_banco_horas($hora_extra['colaborador_id']);
        $saldo_total = (float)$saldo['saldo_horas'] + ($saldo['saldo_minutos'] / 60);
        $saldo_banco_html = '<li><strong>Saldo Atual no Banco:</strong> ' . number_format($saldo_total, 2, ',', '.') . ' horas</li>';
    } else {
        $tipo_pagamento_html = '<li><strong>Tipo de Pagamento:</strong> Pagamento em Dinheiro</li>';
        
        if (!empty($hora_extra['valor_hora']) && $hora_extra['valor_hora'] > 0) {
            $valor_hora_html = '<li><strong>Valor da Hora:</strong> R$ ' . number_format($hora_extra['valor_hora'], 2, ',', '.') . '</li>';
        }
        
        if (!empty($hora_extra['percentual_adicional']) && $hora_extra['percentual_adicional'] > 0) {
            $percentual_adicional_html = '<li><strong>Percentual Adicional:</strong> ' . number_format($hora_extra['percentual_adicional'], 2, ',', '.') . '%</li>';
        }
        
        if (!empty($hora_extra['valor_total']) && $hora_extra['valor_total'] > 0) {
            $valor_total_html = '<li><strong>Valor Total:</strong> R$ ' . number_format($hora_extra['valor_total'], 2, ',', '.') . '</li>';
        }
    }
    
    if (!empty($hora_extra['observacoes'])) {
        $observacoes_html = '<li><strong>Observações:</strong> ' . nl2br(htmlspecialchars($hora_extra['observacoes'])) . '</li>';
    }
    
    // Prepara variáveis texto alternativo
    $tipo_pagamento_texto = '';
    $valor_hora_texto = '';
    $percentual_adicional_texto = '';
    $valor_total_texto = '';
    $saldo_banco_texto = '';
    $observacoes_texto = '';
    
    if ($tipo_pagamento === 'banco_horas') {
        $tipo_pagamento_texto = '- Tipo de Pagamento: Banco de Horas';
        if (!empty($saldo_banco_html)) {
            $saldo_banco_texto = '- ' . strip_tags($saldo_banco_html);
        }
    } else {
        $tipo_pagamento_texto = '- Tipo de Pagamento: Pagamento em Dinheiro';
        if (!empty($valor_hora_html)) {
            $valor_hora_texto = '- ' . strip_tags($valor_hora_html);
        }
        if (!empty($percentual_adicional_html)) {
            $percentual_adicional_texto = '- ' . strip_tags($percentual_adicional_html);
        }
        if (!empty($valor_total_html)) {
            $valor_total_texto = '- ' . strip_tags($valor_total_html);
        }
    }
    
    if (!empty($hora_extra['observacoes'])) {
        $observacoes_texto = '- Observações: ' . strip_tags($hora_extra['observacoes']);
    }
    
    // Formata quantidade de horas
    $quantidade_horas_formatada = number_format($hora_extra['quantidade_horas'], 2, ',', '.') . ' horas';
    $horas_inteiras = floor($hora_extra['quantidade_horas']);
    $minutos = round(($hora_extra['quantidade_horas'] - $horas_inteiras) * 60);
    if ($horas_inteiras > 0 && $minutos > 0) {
        $quantidade_horas_formatada = $horas_inteiras . 'h ' . $minutos . 'min';
    } elseif ($horas_inteiras > 0) {
        $quantidade_horas_formatada = $horas_inteiras . 'h';
    } elseif ($minutos > 0) {
        $quantidade_horas_formatada = $minutos . 'min';
    }
    
    $variaveis = [
        'nome_completo' => $hora_extra['nome_completo'] ?? '',
        'data_trabalho' => formatar_data($hora_extra['data_trabalho'] ?? ''),
        'quantidade_horas' => $quantidade_horas_formatada,
        'tipo_pagamento_html' => $tipo_pagamento_html,
        'valor_hora_html' => $valor_hora_html,
        'percentual_adicional_html' => $percentual_adicional_html,
        'valor_total_html' => $valor_total_html,
        'saldo_banco_html' => $saldo_banco_html,
        'observacoes_html' => $observacoes_html,
        'tipo_pagamento_texto' => $tipo_pagamento_texto,
        'valor_hora_texto' => $valor_hora_texto,
        'percentual_adicional_texto' => $percentual_adicional_texto,
        'valor_total_texto' => $valor_total_texto,
        'saldo_banco_texto' => $saldo_banco_texto,
        'observacoes_texto' => $observacoes_texto,
        'usuario_registro' => $hora_extra['usuario_nome'] ?? '',
        'data_registro' => formatar_data($hora_extra['created_at'] ?? '', 'd/m/Y H:i'),
        'empresa_nome' => $hora_extra['empresa_nome'] ?? '',
        'setor_nome' => $hora_extra['nome_setor'] ?? '',
        'cargo_nome' => $hora_extra['nome_cargo'] ?? '',
        'ano_atual' => date('Y')
    ];
    
    return enviar_email_template('horas_extras', $hora_extra['email_pessoal'], $variaveis);
}

