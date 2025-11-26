<?php
/**
 * Funções Auxiliares do Sistema LMS
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

/**
 * Busca cursos disponíveis para um colaborador
 */
function buscar_cursos_disponiveis($colaborador_id, $filtros = []) {
    $pdo = getDB();
    
    $where = ["c.status = 'publicado'"];
    $params = [];
    
    // Filtro por categoria
    if (!empty($filtros['categoria_id'])) {
        $where[] = "c.categoria_id = ?";
        $params[] = $filtros['categoria_id'];
    }
    
    // Filtro por busca
    if (!empty($filtros['busca'])) {
        $where[] = "(c.titulo LIKE ? OR c.descricao LIKE ?)";
        $busca = "%{$filtros['busca']}%";
        $params[] = $busca;
        $params[] = $busca;
    }
    
    // Filtro por nível
    if (!empty($filtros['nivel'])) {
        $where[] = "c.nivel_dificuldade = ?";
        $params[] = $filtros['nivel'];
    }
    
    // Verifica período de disponibilidade
    $where[] = "(c.data_inicio IS NULL OR c.data_inicio <= CURDATE())";
    $where[] = "(c.data_fim IS NULL OR c.data_fim >= CURDATE())";
    
    $where_sql = implode(' AND ', $where);
    
    $sql = "
        SELECT c.*, 
               cat.nome as categoria_nome,
               cat.icone as categoria_icone,
               cat.cor as categoria_cor,
               COUNT(DISTINCT a.id) as total_aulas,
               (SELECT COUNT(*) FROM progresso_colaborador pc 
                WHERE pc.colaborador_id = ? AND pc.curso_id = c.id AND pc.status = 'concluido') as aulas_concluidas,
               (SELECT COUNT(*) FROM favoritos_cursos fc 
                WHERE fc.colaborador_id = ? AND fc.curso_id = c.id) as favoritado
        FROM cursos c
        LEFT JOIN categorias_cursos cat ON c.categoria_id = cat.id
        LEFT JOIN aulas a ON a.curso_id = c.id AND a.status = 'publicado'
        WHERE $where_sql
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ";
    
    $params = array_merge([$colaborador_id, $colaborador_id], $params);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();
        return $result ? $result : [];
    } catch (PDOException $e) {
        error_log("Erro ao buscar cursos disponíveis: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca progresso de um colaborador em um curso
 */
function buscar_progresso_curso($colaborador_id, $curso_id) {
    $pdo = getDB();
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_aulas,
                SUM(CASE WHEN pc.status = 'concluido' THEN 1 ELSE 0 END) as aulas_concluidas,
                SUM(CASE WHEN pc.status = 'em_andamento' THEN 1 ELSE 0 END) as aulas_em_andamento,
                AVG(pc.percentual_conclusao) as percentual_medio,
                SUM(pc.tempo_assistido) as tempo_total_assistido
            FROM aulas a
            LEFT JOIN progresso_colaborador pc ON pc.aula_id = a.id AND pc.colaborador_id = ?
            WHERE a.curso_id = ? AND a.status = 'publicado'
        ");
        $stmt->execute([$colaborador_id, $curso_id]);
        $result = $stmt->fetch();
        return $result ? $result : [
            'total_aulas' => 0,
            'aulas_concluidas' => 0,
            'aulas_em_andamento' => 0,
            'percentual_medio' => 0,
            'tempo_total_assistido' => 0
        ];
    } catch (PDOException $e) {
        error_log("Erro ao buscar progresso do curso: " . $e->getMessage());
        return [
            'total_aulas' => 0,
            'aulas_concluidas' => 0,
            'aulas_em_andamento' => 0,
            'percentual_medio' => 0,
            'tempo_total_assistido' => 0
        ];
    }
}

/**
 * Calcula percentual de conclusão de um curso
 */
function calcular_percentual_curso($colaborador_id, $curso_id) {
    try {
        $progresso = buscar_progresso_curso($colaborador_id, $curso_id);
        
        if (!isset($progresso['total_aulas']) || $progresso['total_aulas'] == 0) {
            return 0;
        }
        
        $aulas_concluidas = $progresso['aulas_concluidas'] ?? 0;
        return round(($aulas_concluidas / $progresso['total_aulas']) * 100, 2);
    } catch (Exception $e) {
        error_log("Erro ao calcular percentual do curso: " . $e->getMessage());
        return 0;
    }
}

/**
 * Verifica se colaborador pode acessar um curso
 */
function pode_acessar_curso($colaborador_id, $curso_id) {
    $pdo = getDB();
    
    // Busca dados do colaborador
    $stmt = $pdo->prepare("
        SELECT c.empresa_id, c.setor_id, c.cargo_id 
        FROM colaboradores c 
        WHERE c.id = ?
    ");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        return false;
    }
    
    // Busca curso
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->execute([$curso_id]);
    $curso = $stmt->fetch();
    
    if (!$curso || $curso['status'] != 'publicado') {
        return false;
    }
    
    // Verifica restrições
    if ($curso['empresa_id'] && $curso['empresa_id'] != $colaborador['empresa_id']) {
        return false;
    }
    
    if ($curso['setor_id'] && $curso['setor_id'] != $colaborador['setor_id']) {
        return false;
    }
    
    if ($curso['cargo_id'] && $curso['cargo_id'] != $colaborador['cargo_id']) {
        return false;
    }
    
    // Verifica período
    if ($curso['data_inicio'] && $curso['data_inicio'] > date('Y-m-d')) {
        return false;
    }
    
    if ($curso['data_fim'] && $curso['data_fim'] < date('Y-m-d')) {
        return false;
    }
    
    return true;
}

/**
 * Inicia progresso de uma aula
 */
function iniciar_progresso_aula($colaborador_id, $curso_id, $aula_id) {
    try {
        $pdo = getDB();
        
        // Verifica se já existe progresso
        $stmt = $pdo->prepare("
            SELECT id FROM progresso_colaborador 
            WHERE colaborador_id = ? AND curso_id = ? AND aula_id = ?
        ");
        $stmt->execute([$colaborador_id, $curso_id, $aula_id]);
        $progresso = $stmt->fetch();
        
        if ($progresso) {
            // Atualiza último acesso
            $stmt = $pdo->prepare("
                UPDATE progresso_colaborador 
                SET data_ultimo_acesso = NOW(),
                    status = CASE WHEN status = 'nao_iniciado' THEN 'em_andamento' ELSE status END
                WHERE id = ?
            ");
            $stmt->execute([$progresso['id']]);
            return $progresso['id'];
        }
        
        // Busca duração da aula
        $stmt = $pdo->prepare("SELECT duracao_segundos FROM aulas WHERE id = ?");
        $stmt->execute([$aula_id]);
        $aula = $stmt->fetch();
        
        // Cria novo progresso
        $stmt = $pdo->prepare("
            INSERT INTO progresso_colaborador 
            (colaborador_id, curso_id, aula_id, status, data_inicio, data_inicio_real, data_ultimo_acesso, tempo_total_conteudo, ip_address, user_agent)
            VALUES (?, ?, ?, 'em_andamento', NOW(), NOW(), NOW(), ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id,
            $curso_id,
            $aula_id,
            $aula['duracao_segundos'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erro ao iniciar progresso da aula: " . $e->getMessage());
        throw new Exception("Erro ao iniciar progresso da aula");
    }
}

/**
 * Cria sessão de aula
 */
function criar_sessao_aula($progresso_id, $colaborador_id, $aula_id, $curso_id) {
    try {
        $pdo = getDB();
        
        // Fecha sessões anteriores ativas
        $stmt = $pdo->prepare("
            UPDATE lms_sessoes_aula 
            SET sessao_ativa = FALSE, data_fim = NOW()
            WHERE progresso_id = ? AND sessao_ativa = TRUE
        ");
        $stmt->execute([$progresso_id]);
        
        // Cria nova sessão
        $stmt = $pdo->prepare("
            INSERT INTO lms_sessoes_aula 
            (progresso_id, colaborador_id, aula_id, curso_id, data_inicio, ip_address, user_agent, dispositivo, navegador)
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)
        ");
        
        $dispositivo = detectar_dispositivo();
        $navegador = detectar_navegador();
        
        $stmt->execute([
            $progresso_id,
            $colaborador_id,
            $aula_id,
            $curso_id,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $dispositivo,
            $navegador
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erro ao criar sessão da aula: " . $e->getMessage());
        throw new Exception("Erro ao criar sessão da aula");
    }
}

/**
 * Detecta tipo de dispositivo
 */
function detectar_dispositivo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/mobile|android|iphone|ipad/i', $user_agent)) {
        return 'mobile';
    } elseif (preg_match('/tablet|ipad/i', $user_agent)) {
        return 'tablet';
    }
    
    return 'desktop';
}

/**
 * Detecta navegador
 */
function detectar_navegador() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/chrome/i', $user_agent)) return 'Chrome';
    if (preg_match('/firefox/i', $user_agent)) return 'Firefox';
    if (preg_match('/safari/i', $user_agent)) return 'Safari';
    if (preg_match('/edge/i', $user_agent)) return 'Edge';
    if (preg_match('/opera/i', $user_agent)) return 'Opera';
    
    return 'Desconhecido';
}

/**
 * Registra evento do player
 */
function registrar_evento_player($sessao_id, $progresso_id, $colaborador_id, $aula_id, $tipo_evento, $posicao_video, $dados_adicionais = []) {
    $pdo = getDB();
    
    // Busca duração total
    $stmt = $pdo->prepare("SELECT duracao_segundos FROM aulas WHERE id = ?");
    $stmt->execute([$aula_id]);
    $aula = $stmt->fetch();
    
    // Calcula tempo decorrido da sessão
    $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, data_inicio, NOW()) as tempo_decorrido FROM lms_sessoes_aula WHERE id = ?");
    $stmt->execute([$sessao_id]);
    $sessao = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        INSERT INTO lms_eventos_player 
        (sessao_id, progresso_id, colaborador_id, aula_id, tipo_evento, posicao_video, duracao_total, timestamp_evento, tempo_decorrido, dados_adicionais, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessao_id,
        $progresso_id,
        $colaborador_id,
        $aula_id,
        $tipo_evento,
        $posicao_video,
        $aula['duracao_segundos'] ?? null,
        $sessao['tempo_decorrido'] ?? 0,
        json_encode($dados_adicionais),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Atualiza progresso da aula baseado em eventos
 */
function atualizar_progresso_aula($progresso_id, $sessao_id) {
    $pdo = getDB();
    
    // Busca eventos da sessão
    $stmt = $pdo->prepare("
        SELECT * FROM lms_eventos_player 
        WHERE sessao_id = ? 
        ORDER BY timestamp_evento ASC
    ");
    $stmt->execute([$sessao_id]);
    $eventos = $stmt->fetchAll();
    
    // Busca dados da aula
    $stmt = $pdo->prepare("
        SELECT pc.*, a.duracao_segundos 
        FROM progresso_colaborador pc
        INNER JOIN aulas a ON a.id = pc.aula_id
        WHERE pc.id = ?
    ");
    $stmt->execute([$progresso_id]);
    $progresso = $stmt->fetch();
    
    if (!$progresso) {
        return false;
    }
    
    $duracao_total = $progresso['duracao_segundos'] ?? 0;
    
    // Calcula tempo assistido baseado em eventos
    $tempo_assistido = calcular_tempo_assistido($eventos, $duracao_total);
    $percentual = $duracao_total > 0 ? ($tempo_assistido / $duracao_total) * 100 : 0;
    $ultima_posicao = obter_ultima_posicao($eventos);
    
    // Atualiza progresso
    $stmt = $pdo->prepare("
        UPDATE progresso_colaborador 
        SET tempo_total_assistido = ?,
            tempo_assistido = ?,
            percentual_conclusao = ?,
            ultima_posicao = ?,
            data_ultimo_acesso = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $tempo_assistido,
        $tempo_assistido,
        round($percentual, 2),
        $ultima_posicao,
        $progresso_id
    ]);
    
    // Atualiza sessão
    $stmt = $pdo->prepare("
        UPDATE lms_sessoes_aula 
        SET tempo_assistido_segundos = ?,
            tempo_total_segundos = TIMESTAMPDIFF(SECOND, data_inicio, NOW()),
            posicao_final = ?,
            percentual_assistido = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $tempo_assistido,
        $ultima_posicao,
        round($percentual, 2),
        $sessao_id
    ]);
    
    return true;
}

/**
 * Calcula tempo realmente assistido baseado em eventos
 */
function calcular_tempo_assistido($eventos, $duracao_total) {
    $tempo_assistido = 0;
    $ultimo_play = null;
    $ultima_posicao = 0;
    $em_reproducao = false;
    
    foreach ($eventos as $evento) {
        $posicao = $evento['posicao_video'];
        $tipo = $evento['tipo_evento'];
        
        switch ($tipo) {
            case 'play':
                $em_reproducao = true;
                $ultimo_play = strtotime($evento['timestamp_evento']);
                $ultima_posicao = $posicao;
                break;
                
            case 'pause':
                if ($em_reproducao && $ultimo_play) {
                    $tempo_sessao = strtotime($evento['timestamp_evento']) - $ultimo_play;
                    $tempo_assistido += $tempo_sessao;
                }
                $em_reproducao = false;
                $ultima_posicao = $posicao;
                break;
                
            case 'seek':
                // Seek para frente não conta como tempo assistido
                // Seek para trás pode contar se estava em reprodução
                if ($em_reproducao && $posicao < $ultima_posicao) {
                    // Seek para trás durante reprodução - conta tempo até a nova posição
                    $tempo_pulado = $ultima_posicao - $posicao;
                    // Não adiciona tempo, apenas atualiza posição
                }
                $ultima_posicao = $posicao;
                break;
                
            case 'ended':
                if ($em_reproducao && $ultimo_play) {
                    $tempo_sessao = strtotime($evento['timestamp_evento']) - $ultimo_play;
                    $tempo_assistido += $tempo_sessao;
                }
                $em_reproducao = false;
                $ultima_posicao = $duracao_total;
                break;
        }
    }
    
    // Se ainda está em reprodução, calcula até agora
    if ($em_reproducao && $ultimo_play) {
        $tempo_sessao = time() - $ultimo_play;
        $tempo_assistido += $tempo_sessao;
    }
    
    return min($tempo_assistido, $duracao_total);
}

/**
 * Obtém última posição dos eventos
 */
function obter_ultima_posicao($eventos) {
    if (empty($eventos)) {
        return 0;
    }
    
    $ultimo_evento = end($eventos);
    return $ultimo_evento['posicao_video'] ?? 0;
}

/**
 * Verifica se curso está completo
 */
function verificar_curso_completo($colaborador_id, $curso_id) {
    $pdo = getDB();
    
    // Busca total de aulas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM aulas 
        WHERE curso_id = ? AND status = 'publicado'
    ");
    $stmt->execute([$curso_id]);
    $total = $stmt->fetch()['total'];
    
    if ($total == 0) {
        return false;
    }
    
    // Busca aulas concluídas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as concluidas 
        FROM progresso_colaborador pc
        INNER JOIN aulas a ON a.id = pc.aula_id
        WHERE pc.colaborador_id = ? 
        AND pc.curso_id = ? 
        AND a.status = 'publicado'
        AND pc.status = 'concluido'
    ");
    $stmt->execute([$colaborador_id, $curso_id]);
    $concluidas = $stmt->fetch()['concluidas'];
    
    return $concluidas >= $total;
}

/**
 * Gera código único para certificado
 */
function gerar_codigo_certificado($colaborador_id, $curso_id) {
    return strtoupper('CERT-' . date('Y') . '-' . $colaborador_id . '-' . $curso_id . '-' . substr(md5(uniqid()), 0, 8));
}

/**
 * Gera hash de verificação para certificado
 */
function gerar_hash_certificado($colaborador_id, $curso_id, $data_emissao) {
    return hash('sha256', $colaborador_id . $curso_id . $data_emissao . 'CERT_SECRET_KEY');
}

