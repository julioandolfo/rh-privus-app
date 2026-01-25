<?php
/**
 * Sistema de Segurança e Anti-Fraude do LMS
 */

require_once __DIR__ . '/lms_functions.php';

/**
 * Valida conclusão de aula com múltiplas camadas de segurança
 */
function validar_conclusao_aula($progresso_id, $colaborador_id, $aula_id, $curso_id) {
    $pdo = getDB();
    
    // Busca progresso
    $stmt = $pdo->prepare("
        SELECT pc.*, a.tipo_conteudo, a.duracao_segundos,
               cs.*
        FROM progresso_colaborador pc
        INNER JOIN aulas a ON a.id = pc.aula_id
        LEFT JOIN lms_configuracoes_seguranca cs ON (
            cs.aula_id = a.id OR 
            (cs.aula_id IS NULL AND cs.curso_id = a.curso_id AND cs.tipo_conteudo = a.tipo_conteudo) OR
            (cs.aula_id IS NULL AND cs.curso_id IS NULL AND cs.tipo_conteudo = a.tipo_conteudo)
        )
        WHERE pc.id = ?
        ORDER BY cs.aula_id DESC, cs.curso_id DESC
        LIMIT 1
    ");
    $stmt->execute([$progresso_id]);
    $progresso = $stmt->fetch();
    
    if (!$progresso) {
        return [
            'pode_concluir' => false,
            'motivo' => 'Progresso não encontrado',
            'score_risco' => 100
        ];
    }
    
    // Busca configuração de segurança (usa padrão se não houver específica)
    $config = obter_config_seguranca($aula_id, $curso_id, $progresso['tipo_conteudo']);
    
    // Busca eventos da sessão atual
    $stmt = $pdo->prepare("
        SELECT * FROM lms_sessoes_aula 
        WHERE progresso_id = ? AND sessao_ativa = TRUE
        ORDER BY data_inicio DESC LIMIT 1
    ");
    $stmt->execute([$progresso_id]);
    $sessao = $stmt->fetch();
    
    if (!$sessao) {
        return [
            'pode_concluir' => false,
            'motivo' => 'Sessão não encontrada',
            'score_risco' => 50
        ];
    }
    
    // Busca eventos do player
    $stmt = $pdo->prepare("
        SELECT * FROM lms_eventos_player 
        WHERE sessao_id = ? 
        ORDER BY timestamp_evento ASC
    ");
    $stmt->execute([$sessao['id']]);
    $eventos = $stmt->fetchAll();
    
    // Calcula métricas
    $metricas = calcular_metricas_seguranca($eventos, $progresso, $sessao, $config);
    
    // Calcula score de risco
    $score_risco = calcular_score_risco($metricas, $config);
    
    // Validações principais
    $validacoes = [];
    
    // 1. Validação de tempo mínimo
    if ($config['tempo_minimo_percentual'] > 0) {
        $percentual_minimo = $config['tempo_minimo_percentual'];
        if ($metricas['percentual_assistido'] < $percentual_minimo) {
            $validacoes[] = [
                'tipo' => 'tempo_minimo',
                'aprovado' => false,
                'mensagem' => "Percentual mínimo não atingido ({$metricas['percentual_assistido']}% < {$percentual_minimo}%)"
            ];
        }
    }
    
    // 2. Validação de tempo absoluto
    if ($config['tempo_minimo_segundos'] > 0) {
        if ($metricas['tempo_assistido'] < $config['tempo_minimo_segundos']) {
            $validacoes[] = [
                'tipo' => 'tempo_absoluto',
                'aprovado' => false,
                'mensagem' => "Tempo mínimo não atingido ({$metricas['tempo_assistido']}s < {$config['tempo_minimo_segundos']}s)"
            ];
        }
    }
    
    // 3. Validação de velocidade
    if ($config['validar_tempo_real'] && $metricas['velocidade_media'] > $config['tolerancia_velocidade']) {
        $validacoes[] = [
            'tipo' => 'velocidade',
            'aprovado' => false,
            'mensagem' => "Velocidade acima do permitido ({$metricas['velocidade_media']}x > {$config['tolerancia_velocidade']}x)"
        ];
    }
    
    // 4. Validação de interações
    if ($config['requer_interacao'] && $metricas['total_interacoes'] < $config['minimo_interacoes']) {
        $validacoes[] = [
            'tipo' => 'interacoes',
            'aprovado' => false,
            'mensagem' => "Interações insuficientes ({$metricas['total_interacoes']} < {$config['minimo_interacoes']})"
        ];
    }
    
    // 5. Validação de janela ativa
    if ($config['validar_janela_ativa'] && $metricas['percentual_janela_ativa'] < 70) {
        $validacoes[] = [
            'tipo' => 'janela_ativa',
            'aprovado' => false,
            'mensagem' => "Tempo com janela inativa muito alto ({$metricas['percentual_janela_ativa']}%)"
        ];
    }
    
    // 6. Validação de bloqueio de pular
    if ($config['bloquear_pular'] && $metricas['seeks_suspeitos'] > 0) {
        $validacoes[] = [
            'tipo' => 'pular_conteudo',
            'aprovado' => false,
            'mensagem' => "Detectadas tentativas de pular conteúdo ({$metricas['seeks_suspeitos']} seeks suspeitos)"
        ];
    }
    
    // Verifica se passou em todas as validações
    $aprovado = true;
    $motivos = [];
    
    foreach ($validacoes as $validacao) {
        if (!$validacao['aprovado']) {
            $aprovado = false;
            $motivos[] = $validacao['mensagem'];
        }
    }
    
    // Se score de risco muito alto, bloqueia mesmo que passe nas validações
    if ($score_risco >= 70 && $config['acao_fraude'] == 'bloquear') {
        $aprovado = false;
        $motivos[] = "Score de risco muito alto ({$score_risco}/100)";
    }
    
    // Registra tentativa de conclusão
    registrar_tentativa_conclusao($progresso_id, $colaborador_id, $aula_id, $curso_id, $aprovado, $score_risco, $metricas, $motivos);
    
    return [
        'pode_concluir' => $aprovado,
        'motivo' => !empty($motivos) ? implode('; ', $motivos) : 'Aprovado',
        'score_risco' => $score_risco,
        'metricas' => $metricas,
        'validacoes' => $validacoes,
        'requer_aprovacao' => $score_risco >= 50 && $score_risco < 70
    ];
}

/**
 * Obtém configuração de segurança (com fallback para padrão)
 */
function obter_config_seguranca($aula_id, $curso_id, $tipo_conteudo) {
    $pdo = getDB();
    
    // Tenta buscar configuração específica da aula
    $stmt = $pdo->prepare("
        SELECT * FROM lms_configuracoes_seguranca 
        WHERE aula_id = ? AND tipo_conteudo = ?
        LIMIT 1
    ");
    $stmt->execute([$aula_id, $tipo_conteudo]);
    $config = $stmt->fetch();
    
    if ($config) {
        return $config;
    }
    
    // Tenta buscar configuração do curso
    $stmt = $pdo->prepare("
        SELECT * FROM lms_configuracoes_seguranca 
        WHERE curso_id = ? AND aula_id IS NULL AND tipo_conteudo = ?
        LIMIT 1
    ");
    $stmt->execute([$curso_id, $tipo_conteudo]);
    $config = $stmt->fetch();
    
    if ($config) {
        return $config;
    }
    
    // Usa configuração global padrão
    $stmt = $pdo->prepare("
        SELECT * FROM lms_configuracoes_seguranca 
        WHERE curso_id IS NULL AND aula_id IS NULL AND tipo_conteudo = ?
        LIMIT 1
    ");
    $stmt->execute([$tipo_conteudo]);
    $config = $stmt->fetch();
    
    // Se não houver, retorna padrão hardcoded
    if (!$config) {
        return [
            'tempo_minimo_percentual' => 80.00,
            'tempo_minimo_segundos' => null,
            'validar_tempo_real' => true,
            'tolerancia_velocidade' => 2.00,
            'requer_interacao' => false,
            'minimo_interacoes' => 0,
            'validar_janela_ativa' => true,
            'validar_foco' => true,
            'bloquear_pular' => true,
            'requer_sequencial' => true,
            'permitir_revisao' => true,
            'requer_avaliacao' => false,
            'nota_minima' => null,
            'detectar_velocidade_anormal' => true,
            'detectar_multiplas_abas' => true,
            'detectar_automatizacao' => true,
            'alertar_suspeita' => true,
            'acao_fraude' => 'alertar'
        ];
    }
    
    return $config;
}

/**
 * Calcula métricas de segurança
 */
function calcular_metricas_seguranca($eventos, $progresso, $sessao, $config) {
    $duracao_total = $progresso['duracao_segundos'] ?? 0;
    $tempo_sessao = $sessao['tempo_total_segundos'] ?? 0;
    
    // Calcula tempo assistido real
    $tempo_assistido = calcular_tempo_assistido($eventos, $duracao_total);
    $percentual_assistido = $duracao_total > 0 ? ($tempo_assistido / $duracao_total) * 100 : 0;
    
    // Calcula velocidade média
    $tempo_real = $sessao['tempo_total_segundos'] ?? 1;
    $velocidade_media = $tempo_real > 0 ? ($tempo_assistido / $tempo_real) : 1;
    
    // Conta interações
    $total_interacoes = 0;
    $seeks_suspeitos = 0;
    $ultima_posicao = 0;
    $tempo_inativo = 0;
    $tempo_janela_ativa = 0;
    $eventos_play = 0;
    $eventos_pause = 0;
    
    foreach ($eventos as $evento) {
        $tipo = $evento['tipo_evento'];
        
        if ($tipo == 'interaction') {
            $total_interacoes++;
        }
        
        if ($tipo == 'seek') {
            $dados = json_decode($evento['dados_adicionais'] ?? '{}', true);
            $posicao_anterior = $ultima_posicao;
            $posicao_nova = $evento['posicao_video'];
            
            // Seek para frente mais de 10% é suspeito
            if ($posicao_nova > $posicao_anterior + ($duracao_total * 0.1)) {
                $seeks_suspeitos++;
            }
            
            $ultima_posicao = $posicao_nova;
        }
        
        if ($tipo == 'play') {
            $eventos_play++;
            $ultima_posicao = $evento['posicao_video'];
        }
        
        if ($tipo == 'pause') {
            $eventos_pause++;
        }
        
        if ($tipo == 'blur' || $tipo == 'visibilitychange') {
            $dados = json_decode($evento['dados_adicionais'] ?? '{}', true);
            if (isset($dados['hidden']) && $dados['hidden']) {
                $tempo_inativo += 10; // Estimativa
            }
        }
        
        if ($tipo == 'focus' || ($tipo == 'visibilitychange' && isset($dados['hidden']) && !$dados['hidden'])) {
            $tempo_janela_ativa += 10; // Estimativa
        }
    }
    
    $percentual_janela_ativa = $tempo_sessao > 0 ? (($tempo_sessao - $tempo_inativo) / $tempo_sessao) * 100 : 100;
    
    return [
        'tempo_assistido' => $tempo_assistido,
        'tempo_total' => $duracao_total,
        'percentual_assistido' => round($percentual_assistido, 2),
        'velocidade_media' => round($velocidade_media, 2),
        'total_interacoes' => $total_interacoes,
        'seeks_suspeitos' => $seeks_suspeitos,
        'percentual_janela_ativa' => round($percentual_janela_ativa, 2),
        'tempo_inativo' => $tempo_inativo,
        'eventos_play' => $eventos_play,
        'eventos_pause' => $eventos_pause,
        'tempo_sessao' => $tempo_sessao
    ];
}

/**
 * Calcula score de risco (0-100)
 */
function calcular_score_risco($metricas, $config) {
    $risco = 0;
    
    // Tempo muito rápido
    if ($metricas['tempo_assistido'] < ($metricas['tempo_total'] * 0.5)) {
        $risco += 30;
    }
    
    // Velocidade anormal
    if ($metricas['velocidade_media'] > 2.5) {
        $risco += 20;
    }
    
    // Falta de interação
    if ($config['requer_interacao'] && $metricas['total_interacoes'] < $config['minimo_interacoes']) {
        $risco += 25;
    }
    
    // Janela inativa
    if ($metricas['percentual_janela_ativa'] < 70) {
        $risco += 15;
    }
    
    // Seeks suspeitos
    if ($metricas['seeks_suspeitos'] > 3) {
        $risco += 10;
    }
    
    // Padrão muito regular (possível automação)
    if ($metricas['eventos_play'] > 0 && $metricas['eventos_pause'] > 0) {
        $razao = $metricas['eventos_play'] / $metricas['eventos_pause'];
        if ($razao > 0.9 && $razao < 1.1 && $metricas['eventos_play'] > 5) {
            $risco += 10; // Padrão muito regular
        }
    }
    
    return min($risco, 100);
}

/**
 * Registra tentativa de conclusão
 */
function registrar_tentativa_conclusao($progresso_id, $colaborador_id, $aula_id, $curso_id, $aprovado, $score_risco, $metricas, $motivos) {
    $pdo = getDB();
    
    // Incrementa tentativas
    $stmt = $pdo->prepare("
        UPDATE progresso_colaborador 
        SET tentativas_conclusao = tentativas_conclusao + 1
        WHERE id = ?
    ");
    $stmt->execute([$progresso_id]);
    
    // Registra na auditoria
    $acao = $aprovado ? 'conclusao_aprovada' : 'tentativa_conclusao';
    
    $stmt = $pdo->prepare("
        INSERT INTO lms_auditoria_conclusao 
        (progresso_id, colaborador_id, aula_id, curso_id, acao, motivo, dados_validacao, resultado_validacao, score_risco, data_acao, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt->execute([
        $progresso_id,
        $colaborador_id,
        $aula_id,
        $curso_id,
        $acao,
        implode('; ', $motivos),
        json_encode($metricas),
        json_encode(['aprovado' => $aprovado, 'score_risco' => $score_risco]),
        $score_risco,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // Se score muito alto, bloqueia
    if ($score_risco >= 70) {
        $stmt = $pdo->prepare("
            UPDATE progresso_colaborador 
            SET bloqueado_por_fraude = TRUE,
                motivo_bloqueio = ?
            WHERE id = ?
        ");
        $stmt->execute([
            "Score de risco muito alto ({$score_risco}/100): " . implode('; ', $motivos),
            $progresso_id
        ]);
        
        // Registra bloqueio
        $stmt = $pdo->prepare("
            INSERT INTO lms_auditoria_conclusao 
            (progresso_id, colaborador_id, aula_id, curso_id, acao, motivo, score_risco, data_acao)
            VALUES (?, ?, ?, ?, 'bloqueio_fraude', ?, ?, NOW())
        ");
        $stmt->execute([
            $progresso_id,
            $colaborador_id,
            $aula_id,
            $curso_id,
            "Bloqueado automaticamente por suspeita de fraude",
            $score_risco
        ]);
    }
    
    return true;
}

/**
 * Marca aula como concluída (após validação)
 */
function marcar_aula_concluida($progresso_id, $colaborador_id, $aula_id, $curso_id, $aprovacao_manual = false, $aprovado_por = null) {
    $pdo = getDB();
    
    $pdo->beginTransaction();
    
    try {
        // Atualiza progresso
        $stmt = $pdo->prepare("
            UPDATE progresso_colaborador 
            SET status = 'concluido',
                percentual_conclusao = 100.00,
                data_conclusao = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$progresso_id]);
        
        // Fecha sessão ativa
        $stmt = $pdo->prepare("
            UPDATE lms_sessoes_aula 
            SET sessao_ativa = FALSE,
                data_fim = NOW()
            WHERE progresso_id = ? AND sessao_ativa = TRUE
        ");
        $stmt->execute([$progresso_id]);
        
        // Registra conclusão na auditoria
        $acao = $aprovacao_manual ? 'aprovacao_manual' : 'conclusao_aprovada';
        
        $stmt = $pdo->prepare("
            INSERT INTO lms_auditoria_conclusao 
            (progresso_id, colaborador_id, aula_id, curso_id, acao, aprovado_por_usuario_id, data_acao)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $progresso_id,
            $colaborador_id,
            $aula_id,
            $curso_id,
            $acao,
            $aprovado_por
        ]);
        
        // Verifica se curso está completo
        if (verificar_curso_completo($colaborador_id, $curso_id)) {
            // Gera certificado se necessário
            gerar_certificado_curso($colaborador_id, $curso_id);
            
            // Verifica badges
            verificar_badges_curso($colaborador_id, $curso_id);
            
            // Adiciona pontos pela conclusão do curso
            require_once __DIR__ . '/pontuacao.php';
            
            // Busca pontos de recompensa configurados no curso
            $stmt_curso = $pdo->prepare("SELECT pontos_recompensa FROM cursos WHERE id = ?");
            $stmt_curso->execute([$curso_id]);
            $curso_dados = $stmt_curso->fetch();
            
            if ($curso_dados && $curso_dados['pontos_recompensa'] > 0) {
                adicionar_pontos_curso($colaborador_id, $curso_id, $curso_dados['pontos_recompensa']);
            }
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Gera certificado do curso
 */
function gerar_certificado_curso($colaborador_id, $curso_id) {
    $pdo = getDB();
    
    // Verifica se já existe certificado
    $stmt = $pdo->prepare("
        SELECT id FROM certificados 
        WHERE colaborador_id = ? AND curso_id = ?
    ");
    $stmt->execute([$colaborador_id, $curso_id]);
    if ($stmt->fetch()) {
        return false; // Já existe
    }
    
    // Busca dados
    $stmt = $pdo->prepare("
        SELECT c.*, col.nome_completo 
        FROM cursos c
        INNER JOIN colaboradores col ON col.id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$colaborador_id, $curso_id]);
    $dados = $stmt->fetch();
    
    if (!$dados) {
        return false;
    }
    
    $codigo = gerar_codigo_certificado($colaborador_id, $curso_id);
    $data_emissao = date('Y-m-d');
    $hash = gerar_hash_certificado($colaborador_id, $curso_id, $data_emissao);
    
    // Insere certificado
    $stmt = $pdo->prepare("
        INSERT INTO certificados 
        (colaborador_id, curso_id, codigo_unico, data_emissao, hash_verificacao, status)
        VALUES (?, ?, ?, ?, ?, 'ativo')
    ");
    $stmt->execute([
        $colaborador_id,
        $curso_id,
        $codigo,
        $data_emissao,
        $hash
    ]);
    
    return true;
}

/**
 * Verifica badges relacionados ao curso
 */
function verificar_badges_curso($colaborador_id, $curso_id) {
    $pdo = getDB();
    
    // Busca badges de "curso completo"
    $stmt = $pdo->prepare("
        SELECT * FROM badges_conquistas 
        WHERE tipo = 'curso_completo' AND ativo = TRUE
    ");
    $stmt->execute();
    $badges = $stmt->fetchAll();
    
    foreach ($badges as $badge) {
        // Verifica se já tem o badge
        $stmt = $pdo->prepare("
            SELECT id FROM colaborador_badges 
            WHERE colaborador_id = ? AND badge_id = ? AND curso_id = ?
        ");
        $stmt->execute([$colaborador_id, $badge['id'], $curso_id]);
        
        if (!$stmt->fetch()) {
            // Concede badge
            $stmt = $pdo->prepare("
                INSERT INTO colaborador_badges 
                (colaborador_id, badge_id, curso_id, data_conquista)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$colaborador_id, $badge['id'], $curso_id]);
        }
    }
}

