<?php
/**
 * Funções para Cursos Obrigatórios e Sistema de Alertas
 */

require_once __DIR__ . '/lms_functions.php';
require_once __DIR__ . '/notificacoes.php';
require_once __DIR__ . '/push_notifications.php';
require_once __DIR__ . '/email.php';

/**
 * Atribui curso obrigatório a colaborador
 */
function atribuir_curso_obrigatorio($curso_id, $colaborador_id, $atribuido_por = null, $prazo_personalizado = null) {
    $pdo = getDB();
    
    // Verifica se já está atribuído
    $stmt = $pdo->prepare("
        SELECT id FROM cursos_obrigatorios_colaboradores 
        WHERE curso_id = ? AND colaborador_id = ?
    ");
    $stmt->execute([$curso_id, $colaborador_id]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Curso já está atribuído a este colaborador'];
    }
    
    // Busca dados do curso
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->execute([$curso_id]);
    $curso = $stmt->fetch();
    
    if (!$curso || !$curso['obrigatorio']) {
        return ['success' => false, 'message' => 'Curso não é obrigatório'];
    }
    
    // Calcula data limite
    $data_atribuicao = date('Y-m-d');
    $data_limite = calcular_data_limite($curso, $colaborador_id, $data_atribuicao, $prazo_personalizado);
    
    // Insere atribuição
    $stmt = $pdo->prepare("
        INSERT INTO cursos_obrigatorios_colaboradores 
        (curso_id, colaborador_id, atribuido_por_usuario_id, data_atribuicao, data_limite, status)
        VALUES (?, ?, ?, ?, ?, 'pendente')
    ");
    $stmt->execute([
        $curso_id,
        $colaborador_id,
        $atribuido_por,
        $data_atribuicao,
        $data_limite
    ]);
    
    $atribuicao_id = $pdo->lastInsertId();
    
    // Agenda notificação inicial
    if ($curso['alertar_sistema'] || $curso['alertar_email'] || $curso['alertar_push']) {
        agendar_alerta_inicial($atribuicao_id, $curso_id, $colaborador_id, $data_limite, $curso);
    }
    
    return [
        'success' => true,
        'atribuicao_id' => $atribuicao_id,
        'data_limite' => $data_limite
    ];
}

/**
 * Calcula data limite baseado no tipo de prazo
 */
function calcular_data_limite($curso, $colaborador_id, $data_atribuicao, $prazo_personalizado = null) {
    $prazo_dias = $prazo_personalizado ?? $curso['prazo_dias'];
    
    if (!$prazo_dias) {
        return null; // Sem prazo
    }
    
    switch ($curso['prazo_tipo']) {
        case 'data_fixa':
            return $curso['data_limite'];
            
        case 'dias_apos_admissao':
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT data_inicio FROM colaboradores WHERE id = ?");
            $stmt->execute([$colaborador_id]);
            $colaborador = $stmt->fetch();
            
            if ($colaborador && $colaborador['data_inicio']) {
                $data_base = new DateTime($colaborador['data_inicio']);
                $data_base->modify("+{$prazo_dias} days");
                return $data_base->format('Y-m-d');
            }
            // Fallback para data de atribuição
            $data_base = new DateTime($data_atribuicao);
            $data_base->modify("+{$prazo_dias} days");
            return $data_base->format('Y-m-d');
            
        case 'dias_apos_atribuicao':
        default:
            $data_base = new DateTime($data_atribuicao);
            $data_base->modify("+{$prazo_dias} days");
            return $data_base->format('Y-m-d');
    }
}

/**
 * Agenda alerta inicial
 */
function agendar_alerta_inicial($atribuicao_id, $curso_id, $colaborador_id, $data_limite, $curso) {
    $pdo = getDB();
    
    $agora = date('Y-m-d H:i:s');
    
    // Email
    if ($curso['alertar_email']) {
        $stmt = $pdo->prepare("
            INSERT INTO alertas_cursos_obrigatorios 
            (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada)
            VALUES (?, ?, ?, 'inicial', DATEDIFF(?, CURDATE()), 'email', ?)
        ");
        $stmt->execute([$atribuicao_id, $colaborador_id, $curso_id, $data_limite, $agora]);
    }
    
    // Push
    if ($curso['alertar_push']) {
        $stmt = $pdo->prepare("
            INSERT INTO alertas_cursos_obrigatorios 
            (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada)
            VALUES (?, ?, ?, 'inicial', DATEDIFF(?, CURDATE()), 'push', ?)
        ");
        $stmt->execute([$atribuicao_id, $colaborador_id, $curso_id, $data_limite, $agora]);
    }
    
    // Sistema
    if ($curso['alertar_sistema']) {
        $stmt = $pdo->prepare("
            INSERT INTO alertas_cursos_obrigatorios 
            (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada)
            VALUES (?, ?, ?, 'inicial', DATEDIFF(?, CURDATE()), 'sistema', ?)
        ");
        $stmt->execute([$atribuicao_id, $colaborador_id, $curso_id, $data_limite, $agora]);
    }
    
    // Envia notificação inicial imediatamente
    enviar_notificacao_inicial($curso_id, $colaborador_id, $data_limite);
}

/**
 * Envia notificação inicial do curso obrigatório
 */
function enviar_notificacao_inicial($curso_id, $colaborador_id, $data_limite) {
    $pdo = getDB();
    
    // Busca dados
    $stmt = $pdo->prepare("
        SELECT c.*, col.nome_completo, col.email_pessoal,
               u.id as usuario_id
        FROM cursos c
        INNER JOIN colaboradores col ON col.id = ?
        LEFT JOIN usuarios u ON u.colaborador_id = col.id
        WHERE c.id = ?
    ");
    $stmt->execute([$colaborador_id, $curso_id]);
    $dados = $stmt->fetch();
    
    if (!$dados) {
        return false;
    }
    
    $dias_restantes = (new DateTime($data_limite))->diff(new DateTime())->days;
    $base_url = get_base_url();
    $link_curso = $base_url . "/pages/lms/portal/curso_detalhes.php?id={$curso_id}";
    
    $titulo = "Novo Curso Obrigatório: {$dados['titulo']}";
    $mensagem = "Você tem um curso obrigatório que precisa ser concluído até " . date('d/m/Y', strtotime($data_limite));
    
    // Notificação no sistema
    if ($dados['alertar_sistema']) {
        criar_notificacao(
            $dados['usuario_id'] ?? null,
            $colaborador_id,
            'lms_obrigatorio',
            $titulo,
            $mensagem,
            "lms/portal/curso_detalhes.php?id={$curso_id}",
            $curso_id,
            'lms'
        );
    }
    
    // Email
    if ($dados['alertar_email'] && !empty($dados['email_pessoal'])) {
        $email_html = gerar_template_email_curso_obrigatorio($dados, $data_limite, $dias_restantes, $link_curso);
        enviar_email(
            $dados['email_pessoal'],
            $titulo,
            $email_html,
            ['nome_destinatario' => $dados['nome_completo']]
        );
    }
    
    // Push
    if ($dados['alertar_push']) {
        if ($dados['usuario_id']) {
            enviar_push_usuario($dados['usuario_id'], $titulo, $mensagem, $link_curso);
        } else {
            enviar_push_colaborador($colaborador_id, $titulo, $mensagem, $link_curso);
        }
    }
    
    return true;
}

/**
 * Agenda alertas progressivos baseado na configuração do curso
 */
function agendar_alertas_progressivos($atribuicao_id, $curso_id, $colaborador_id, $data_limite, $curso) {
    $pdo = getDB();
    
    if (empty($curso['dias_antes_alertar'])) {
        return;
    }
    
    $dias_alertar = json_decode($curso['dias_antes_alertar'], true);
    if (!is_array($dias_alertar)) {
        return;
    }
    
    $data_limite_obj = new DateTime($data_limite);
    
    foreach ($dias_alertar as $dias_antes) {
        $data_alerta = clone $data_limite_obj;
        $data_alerta->modify("-{$dias_antes} days");
        
        if ($data_alerta < new DateTime()) {
            continue; // Data já passou
        }
        
        // Email
        if ($curso['alertar_email']) {
            $stmt = $pdo->prepare("
                INSERT INTO alertas_cursos_obrigatorios 
                (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada)
                VALUES (?, ?, ?, 'lembrete', ?, 'email', ?)
            ");
            $stmt->execute([
                $atribuicao_id,
                $colaborador_id,
                $curso_id,
                $dias_antes,
                $data_alerta->format('Y-m-d H:i:s')
            ]);
        }
        
        // Push
        if ($curso['alertar_push']) {
            $stmt = $pdo->prepare("
                INSERT INTO alertas_cursos_obrigatorios 
                (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada)
                VALUES (?, ?, ?, 'lembrete', ?, 'push', ?)
            ");
            $stmt->execute([
                $atribuicao_id,
                $colaborador_id,
                $curso_id,
                $dias_antes,
                $data_alerta->format('Y-m-d H:i:s')
            ]);
        }
        
        // Sistema
        if ($curso['alertar_sistema']) {
            $stmt = $pdo->prepare("
                INSERT INTO alertas_cursos_obrigatorios 
                (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada)
                VALUES (?, ?, ?, 'lembrete', ?, 'sistema', ?)
            ");
            $stmt->execute([
                $atribuicao_id,
                $colaborador_id,
                $curso_id,
                $dias_antes,
                $data_alerta->format('Y-m-d H:i:s')
            ]);
        }
    }
    
    // Alerta de vencimento próximo (1 dia antes)
    $data_vencimento_proximo = clone $data_limite_obj;
    $data_vencimento_proximo->modify('-1 day');
    
    if ($data_vencimento_proximo >= new DateTime()) {
        if ($curso['alertar_email']) {
            $stmt = $pdo->prepare("
                INSERT INTO alertas_cursos_obrigatorios 
                (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada)
                VALUES (?, ?, ?, 'vencimento_proximo', 1, 'email', ?)
            ");
            $stmt->execute([$atribuicao_id, $colaborador_id, $curso_id, $data_vencimento_proximo->format('Y-m-d H:i:s')]);
        }
        
        if ($curso['alertar_push']) {
            $stmt = $pdo->prepare("
                INSERT INTO alertas_cursos_obrigatorios 
                (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada)
                VALUES (?, ?, ?, 'vencimento_proximo', 1, 'push', ?)
            ");
            $stmt->execute([$atribuicao_id, $colaborador_id, $curso_id, $data_vencimento_proximo->format('Y-m-d H:i:s')]);
        }
    }
}

/**
 * Processa alertas agendados
 */
function processar_alertas_agendados() {
    $pdo = getDB();
    
    $agora = date('Y-m-d H:i:s');
    
    // Busca alertas pendentes
    $stmt = $pdo->prepare("
        SELECT a.*, c.titulo as curso_titulo, col.nome_completo, col.email_pessoal,
               coc.data_limite, coc.status as status_atribuicao,
               u.id as usuario_id
        FROM alertas_cursos_obrigatorios a
        INNER JOIN cursos c ON c.id = a.curso_id
        INNER JOIN colaboradores col ON col.id = a.colaborador_id
        LEFT JOIN usuarios u ON u.colaborador_id = col.id
        INNER JOIN cursos_obrigatorios_colaboradores coc ON coc.id = a.curso_obrigatorio_id
        WHERE a.enviado = FALSE
        AND a.data_agendada <= ?
        AND coc.status IN ('pendente', 'em_andamento')
    ");
    $stmt->execute([$agora]);
    $alertas = $stmt->fetchAll();
    
    $processados = 0;
    $erros = 0;
    
    foreach ($alertas as $alerta) {
        try {
            $dias_restantes = (new DateTime($alerta['data_limite']))->diff(new DateTime())->days;
            
            // Atualiza dias restantes
            $stmt = $pdo->prepare("
                UPDATE alertas_cursos_obrigatorios 
                SET dias_restantes = ?
                WHERE id = ?
            ");
            $stmt->execute([$dias_restantes, $alerta['id']]);
            
            // Envia alerta
            $enviado = enviar_alerta_curso_obrigatorio($alerta, $dias_restantes);
            
            if ($enviado) {
                // Marca como enviado
                $stmt = $pdo->prepare("
                    UPDATE alertas_cursos_obrigatorios 
                    SET enviado = TRUE,
                        data_envio = NOW(),
                        tentativas = tentativas + 1
                    WHERE id = ?
                ");
                $stmt->execute([$alerta['id']]);
                
                // Atualiza último alerta enviado
                $stmt = $pdo->prepare("
                    UPDATE cursos_obrigatorios_colaboradores 
                    SET ultimo_alerta_enviado = CURDATE(),
                        tentativas_alertas = tentativas_alertas + 1
                    WHERE id = ?
                ");
                $stmt->execute([$alerta['curso_obrigatorio_id']]);
                
                $processados++;
            } else {
                $erros++;
            }
            
        } catch (Exception $e) {
            // Registra erro
            $stmt = $pdo->prepare("
                UPDATE alertas_cursos_obrigatorios 
                SET tentativas = tentativas + 1,
                    erro = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $alerta['id']]);
            $erros++;
        }
    }
    
    // Processa alertas vencidos (se configurado)
    processar_alertas_vencidos();
    
    return [
        'processados' => $processados,
        'erros' => $erros
    ];
}

/**
 * Envia alerta de curso obrigatório
 */
function enviar_alerta_curso_obrigatorio($alerta, $dias_restantes) {
    $base_url = get_base_url();
    $link_curso = $base_url . "/pages/lms/portal/curso_detalhes.php?id={$alerta['curso_id']}";
    
    $tipo_alerta = $alerta['tipo_alerta'];
    $dias_restantes_formatado = $dias_restantes > 0 ? $dias_restantes : abs($dias_restantes);
    
    switch ($tipo_alerta) {
        case 'inicial':
            $titulo = "Novo Curso Obrigatório: {$alerta['curso_titulo']}";
            $mensagem = "Você tem um curso obrigatório que precisa ser concluído até " . date('d/m/Y', strtotime($alerta['data_limite']));
            break;
            
        case 'lembrete':
            $titulo = "Lembrete: Curso Obrigatório Pendente";
            $mensagem = "O curso '{$alerta['curso_titulo']}' vence em {$dias_restantes_formatado} dia(s)";
            break;
            
        case 'vencimento_proximo':
            $titulo = "⚠️ ATENÇÃO: Curso Obrigatório Vence Amanhã!";
            $mensagem = "O curso '{$alerta['curso_titulo']}' vence amanhã! Complete-o agora.";
            break;
            
        case 'vencido':
            $titulo = "🚨 URGENTE: Curso Obrigatório Vencido";
            $mensagem = "O curso '{$alerta['curso_titulo']}' está vencido há {$dias_restantes_formatado} dia(s)";
            break;
            
        default:
            return false;
    }
    
    $canal = $alerta['canal'];
    
    switch ($canal) {
        case 'email':
            if (empty($alerta['email_pessoal'])) {
                return false;
            }
            $email_html = gerar_template_email_alerta($alerta, $dias_restantes, $link_curso, $tipo_alerta);
            return enviar_email(
                $alerta['email_pessoal'],
                $titulo,
                $email_html,
                ['nome_destinatario' => $alerta['nome_completo']]
            )['success'];
            
        case 'push':
            if ($alerta['usuario_id']) {
                return enviar_push_usuario($alerta['usuario_id'], $titulo, $mensagem, $link_curso)['success'];
            } else {
                return enviar_push_colaborador($alerta['colaborador_id'], $titulo, $mensagem, $link_curso)['success'];
            }
            
        case 'sistema':
            criar_notificacao(
                $alerta['usuario_id'] ?? null,
                $alerta['colaborador_id'],
                'lms_obrigatorio',
                $titulo,
                $mensagem,
                "lms/portal/curso_detalhes.php?id={$alerta['curso_id']}",
                $alerta['curso_id'],
                'lms'
            );
            return true;
            
        default:
            return false;
    }
}

/**
 * Processa alertas de cursos vencidos
 */
function processar_alertas_vencidos() {
    $pdo = getDB();
    
    // Busca cursos vencidos que ainda não foram concluídos
    $stmt = $pdo->prepare("
        SELECT coc.*, c.titulo, c.alertar_apos_vencimento, c.frequencia_alertas_vencido,
               c.alertar_email, c.alertar_push, c.alertar_sistema,
               col.nome_completo, col.email_pessoal, u.id as usuario_id
        FROM cursos_obrigatorios_colaboradores coc
        INNER JOIN cursos c ON c.id = coc.curso_id
        INNER JOIN colaboradores col ON col.id = coc.colaborador_id
        LEFT JOIN usuarios u ON u.colaborador_id = col.id
        WHERE coc.status IN ('pendente', 'em_andamento')
        AND coc.data_limite < CURDATE()
        AND c.alertar_apos_vencimento = TRUE
    ");
    $stmt->execute();
    $cursos_vencidos = $stmt->fetchAll();
    
    foreach ($cursos_vencidos as $curso) {
        // Verifica última vez que alertou
        $ultimo_alerta = $curso['ultimo_alerta_enviado'];
        $deve_alertar = false;
        
        if (!$ultimo_alerta) {
            $deve_alertar = true; // Nunca alertou
        } else {
            $dias_desde_ultimo = (new DateTime())->diff(new DateTime($ultimo_alerta))->days;
            
            switch ($curso['frequencia_alertas_vencido']) {
                case 'diario':
                    $deve_alertar = $dias_desde_ultimo >= 1;
                    break;
                case 'semanal':
                    $deve_alertar = $dias_desde_ultimo >= 7;
                    break;
                case 'mensal':
                    $deve_alertar = $dias_desde_ultimo >= 30;
                    break;
            }
        }
        
        if ($deve_alertar) {
            // Agenda alerta de vencido
            $dias_vencido = (new DateTime())->diff(new DateTime($curso['data_limite']))->days;
            
            if ($curso['alertar_email']) {
                $stmt = $pdo->prepare("
                    INSERT INTO alertas_cursos_obrigatorios 
                    (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada, enviado)
                    VALUES (?, ?, ?, 'vencido', ?, 'email', NOW(), TRUE)
                ");
                $stmt->execute([
                    $curso['id'],
                    $curso['colaborador_id'],
                    $curso['curso_id'],
                    -$dias_vencido
                ]);
                enviar_alerta_curso_obrigatorio([
                    'curso_titulo' => $curso['titulo'],
                    'curso_id' => $curso['curso_id'],
                    'colaborador_id' => $curso['colaborador_id'],
                    'email_pessoal' => $curso['email_pessoal'],
                    'nome_completo' => $curso['nome_completo'],
                    'usuario_id' => $curso['usuario_id'],
                    'canal' => 'email',
                    'tipo_alerta' => 'vencido'
                ], -$dias_vencido);
            }
            
            if ($curso['alertar_push']) {
                $stmt = $pdo->prepare("
                    INSERT INTO alertas_cursos_obrigatorios 
                    (curso_obrigatorio_id, colaborador_id, curso_id, tipo_alerta, dias_restantes, canal, data_agendada, enviado)
                    VALUES (?, ?, ?, 'vencido', ?, 'push', NOW(), TRUE)
                ");
                $stmt->execute([
                    $curso['id'],
                    $curso['colaborador_id'],
                    $curso['curso_id'],
                    -$dias_vencido
                ]);
                enviar_alerta_curso_obrigatorio([
                    'curso_titulo' => $curso['titulo'],
                    'curso_id' => $curso['curso_id'],
                    'colaborador_id' => $curso['colaborador_id'],
                    'usuario_id' => $curso['usuario_id'],
                    'canal' => 'push',
                    'tipo_alerta' => 'vencido'
                ], -$dias_vencido);
            }
            
            // Atualiza status para vencido
            $stmt = $pdo->prepare("
                UPDATE cursos_obrigatorios_colaboradores 
                SET status = 'vencido',
                    ultimo_alerta_enviado = CURDATE(),
                    tentativas_alertas = tentativas_alertas + 1
                WHERE id = ?
            ");
            $stmt->execute([$curso['id']]);
        }
    }
}

/**
 * Gera template de email para curso obrigatório
 */
function gerar_template_email_curso_obrigatorio($dados, $data_limite, $dias_restantes, $link_curso) {
    $data_formatada = date('d/m/Y', strtotime($data_limite));
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #009ef7; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; background: #009ef7; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .info-box { background: white; padding: 15px; border-left: 4px solid #009ef7; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📚 Novo Curso Obrigatório</h2>
            </div>
            <div class='content'>
                <p>Olá, <strong>{$dados['nome_completo']}</strong>!</p>
                
                <p>Você possui um curso obrigatório que precisa ser concluído:</p>
                
                <div class='info-box'>
                    <h3>{$dados['titulo']}</h3>
                    <p><strong>Prazo:</strong> {$data_formatada}</p>
                    <p><strong>Dias restantes:</strong> {$dias_restantes} dia(s)</p>
                </div>
                
                <p>Este é um curso obrigatório e deve ser concluído até a data limite.</p>
                
                <a href='{$link_curso}' class='button'>Acessar Curso</a>
                
                <p style='margin-top: 30px; font-size: 12px; color: #666;'>
                    Se você não conseguir acessar o link, copie e cole o seguinte endereço no seu navegador:<br>
                    {$link_curso}
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Gera template de email para alertas
 */
function gerar_template_email_alerta($alerta, $dias_restantes, $link_curso, $tipo_alerta) {
    $data_limite_formatada = date('d/m/Y', strtotime($alerta['data_limite']));
    $dias_formatado = abs($dias_restantes);
    
    $titulo_alerta = '';
    $mensagem_alerta = '';
    $cor_header = '#009ef7';
    
    switch ($tipo_alerta) {
        case 'lembrete':
            $titulo_alerta = 'Lembrete: Curso Obrigatório Pendente';
            $mensagem_alerta = "O curso <strong>{$alerta['curso_titulo']}</strong> vence em <strong>{$dias_formatado} dia(s)</strong>.";
            $cor_header = '#ffc700';
            break;
        case 'vencimento_proximo':
            $titulo_alerta = '⚠️ ATENÇÃO: Curso Vence Amanhã!';
            $mensagem_alerta = "O curso <strong>{$alerta['curso_titulo']}</strong> vence <strong>amanhã</strong>! Complete-o agora.";
            $cor_header = '#f1416c';
            break;
        case 'vencido':
            $titulo_alerta = '🚨 URGENTE: Curso Obrigatório Vencido';
            $mensagem_alerta = "O curso <strong>{$alerta['curso_titulo']}</strong> está <strong>vencido há {$dias_formatado} dia(s)</strong>.";
            $cor_header = '#f1416c';
            break;
    }
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: {$cor_header}; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; background: {$cor_header}; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .info-box { background: white; padding: 15px; border-left: 4px solid {$cor_header}; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$titulo_alerta}</h2>
            </div>
            <div class='content'>
                <p>Olá, <strong>{$alerta['nome_completo']}</strong>!</p>
                
                <p>{$mensagem_alerta}</p>
                
                <div class='info-box'>
                    <p><strong>Prazo:</strong> {$data_limite_formatada}</p>
                    <p><strong>Dias restantes:</strong> {$dias_restantes} dia(s)</p>
                </div>
                
                <a href='{$link_curso}' class='button'>Acessar Curso Agora</a>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Verifica e aplica regras automáticas de atribuição
 */
function aplicar_regras_automaticas($colaborador_id, $evento_tipo, $evento_valor = null) {
    $pdo = getDB();
    
    // Busca regras ativas para o tipo de evento
    $stmt = $pdo->prepare("
        SELECT cor.*, c.*
        FROM cursos_obrigatorios_regras cor
        INNER JOIN cursos c ON c.id = cor.curso_id
        WHERE cor.tipo_regra = ?
        AND cor.ativo = TRUE
        AND c.obrigatorio = TRUE
        AND c.status = 'publicado'
    ");
    $stmt->execute([$evento_tipo]);
    $regras = $stmt->fetchAll();
    
    foreach ($regras as $regra) {
        // Verifica se regra se aplica
        $aplica = false;
        
        if ($regra['valor_regra'] === null) {
            $aplica = true; // Regra geral
        } else {
            // Busca dados do colaborador
            $stmt_colab = $pdo->prepare("SELECT setor_id, cargo_id FROM colaboradores WHERE id = ?");
            $stmt_colab->execute([$colaborador_id]);
            $colab = $stmt_colab->fetch();
            
            switch ($regra['tipo_regra']) {
                case 'cargo':
                    $aplica = ($colab['cargo_id'] == $regra['valor_regra']);
                    break;
                case 'setor':
                    $aplica = ($colab['setor_id'] == $regra['valor_regra']);
                    break;
                case 'mudanca_cargo':
                    $aplica = ($evento_valor == $regra['valor_regra']);
                    break;
                case 'mudanca_setor':
                    $aplica = ($evento_valor == $regra['valor_regra']);
                    break;
            }
        }
        
        if ($aplica) {
            // Atribui curso
            atribuir_curso_obrigatorio(
                $regra['curso_id'],
                $colaborador_id,
                null, // Sistema
                $regra['prazo_dias']
            );
        }
    }
}

