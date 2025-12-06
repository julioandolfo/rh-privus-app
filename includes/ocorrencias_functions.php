<?php
/**
 * Funções auxiliares para o sistema de ocorrências avançado
 */

require_once __DIR__ . '/functions.php';

/**
 * Faz upload de anexo de ocorrência
 */
function upload_anexo_ocorrencia($file, $ocorrencia_id) {
    $upload_dir = __DIR__ . '/../uploads/ocorrencias/';
    
    // Cria diretório se não existir
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Cria subdiretório por ocorrência
    $ocorrencia_dir = $upload_dir . 'ocorrencia_' . $ocorrencia_id . '/';
    if (!file_exists($ocorrencia_dir)) {
        mkdir($ocorrencia_dir, 0755, true);
    }
    
    // Validações
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado'];
    }
    
    // Tipos permitidos
    $allowed_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($mime_type, $allowed_types) || !in_array($extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido'];
    }
    
    // Valida tamanho (máximo 10MB)
    $max_size = 10 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo 10MB'];
    }
    
    // Gera nome único
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $filepath = $ocorrencia_dir . $filename;
    
    // Move arquivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relative_path = 'uploads/ocorrencias/ocorrencia_' . $ocorrencia_id . '/' . $filename;
        return [
            'success' => true,
            'path' => $relative_path,
            'filename' => $file['name'],
            'mime_type' => $mime_type,
            'size' => $file['size']
        ];
    } else {
        return ['success' => false, 'error' => 'Erro ao fazer upload do arquivo'];
    }
}

/**
 * Registra histórico de alteração em ocorrência
 */
function registrar_historico_ocorrencia($ocorrencia_id, $acao, $usuario_id, $campo_alterado = null, $valor_anterior = null, $valor_novo = null, $observacoes = null) {
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ocorrencias_historico 
            (ocorrencia_id, usuario_id, acao, campo_alterado, valor_anterior, valor_novo, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ocorrencia_id,
            $usuario_id,
            $acao,
            $campo_alterado,
            $valor_anterior,
            $valor_novo,
            $observacoes
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao registrar histórico: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica e aplica regras de advertências progressivas
 */
function verificar_advertencias_progressivas($colaborador_id, $tipo_ocorrencia_id = null) {
    $pdo = getDB();
    
    // Busca regras ativas
    $sql = "SELECT * FROM ocorrencias_regras_advertencias WHERE ativo = 1";
    $params = [];
    
    if ($tipo_ocorrencia_id) {
        $sql .= " AND (tipo_ocorrencia_id = ? OR tipo_ocorrencia_id IS NULL)";
        $params[] = $tipo_ocorrencia_id;
    } else {
        $sql .= " AND tipo_ocorrencia_id IS NULL";
    }
    
    $sql .= " ORDER BY quantidade_ocorrencias ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $regras = $stmt->fetchAll();
    
    $advertencias_aplicadas = [];
    
    foreach ($regras as $regra) {
        // Conta ocorrências do colaborador
        $sql_contagem = "
            SELECT COUNT(*) as total
            FROM ocorrencias o
            WHERE o.colaborador_id = ?
            AND o.status_aprovacao = 'aprovada'
        ";
        $params_contagem = [$colaborador_id];
        
        if ($regra['tipo_ocorrencia_id']) {
            $sql_contagem .= " AND o.tipo_ocorrencia_id = ?";
            $params_contagem[] = $regra['tipo_ocorrencia_id'];
        }
        
        if ($regra['periodo_dias']) {
            $sql_contagem .= " AND o.data_ocorrencia >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $params_contagem[] = $regra['periodo_dias'];
        }
        
        $stmt_contagem = $pdo->prepare($sql_contagem);
        $stmt_contagem->execute($params_contagem);
        $resultado = $stmt_contagem->fetch();
        $total_ocorrencias = $resultado['total'];
        
        // Verifica se atingiu a quantidade
        if ($total_ocorrencias >= $regra['quantidade_ocorrencias']) {
            // Verifica se já existe advertência deste nível
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) as total
                FROM ocorrencias_advertencias
                WHERE colaborador_id = ?
                AND tipo_advertencia = ?
                AND nivel = ?
                AND (data_validade IS NULL OR data_validade >= CURDATE())
            ");
            $stmt_check->execute([
                $colaborador_id,
                $regra['acao'],
                $regra['nivel_advertencia']
            ]);
            $check = $stmt_check->fetch();
            
            if ($check['total'] == 0) {
                // Busca última ocorrência para vincular
                $stmt_ultima = $pdo->prepare("
                    SELECT id FROM ocorrencias
                    WHERE colaborador_id = ?
                    ORDER BY data_ocorrencia DESC, created_at DESC
                    LIMIT 1
                ");
                $stmt_ultima->execute([$colaborador_id]);
                $ultima_ocorrencia = $stmt_ultima->fetch();
                
                $ocorrencia_id = $ultima_ocorrencia['id'] ?? null;
                
                // Calcula data de validade
                $data_validade = null;
                if ($regra['dias_validade']) {
                    $data_validade = date('Y-m-d', strtotime('+' . $regra['dias_validade'] . ' days'));
                }
                
                // Cria advertência
                $stmt_insert = $pdo->prepare("
                    INSERT INTO ocorrencias_advertencias
                    (colaborador_id, ocorrencia_id, tipo_advertencia, nivel, data_advertencia, data_validade, created_by)
                    VALUES (?, ?, ?, ?, CURDATE(), ?, ?)
                ");
                $stmt_insert->execute([
                    $colaborador_id,
                    $ocorrencia_id,
                    $regra['acao'],
                    $regra['nivel_advertencia'],
                    $data_validade,
                    $_SESSION['usuario']['id'] ?? 1
                ]);
                
                $advertencias_aplicadas[] = [
                    'tipo' => $regra['acao'],
                    'nivel' => $regra['nivel_advertencia'],
                    'quantidade_ocorrencias' => $total_ocorrencias
                ];
            }
        }
    }
    
    return $advertencias_aplicadas;
}

/**
 * Calcula desconto automático baseado na ocorrência
 */
function calcular_desconto_ocorrencia($ocorrencia_id) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT o.*, t.calcula_desconto, t.valor_desconto, t.codigo as tipo_codigo, c.salario
        FROM ocorrencias o
        INNER JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
        INNER JOIN colaboradores c ON o.colaborador_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$ocorrencia_id]);
    $ocorrencia = $stmt->fetch();
    
    if (!$ocorrencia || !$ocorrencia['calcula_desconto']) {
        return 0;
    }
    
    // Se for apenas informativa, retorna 0 (sem desconto)
    if (!empty($ocorrencia['apenas_informativa']) && $ocorrencia['apenas_informativa'] == 1) {
        return 0;
    }
    
    // Se tem valor fixo, usa ele
    if ($ocorrencia['valor_desconto']) {
        return (float)$ocorrencia['valor_desconto'];
    }
    
    if (!$ocorrencia['salario']) {
        return 0;
    }
    
    $jornada_diaria = 8; // Padrão 8h (coluna não existe na tabela colaboradores)
    $horas_mes = 220; // Padrão CLT
    $valor_hora = $ocorrencia['salario'] / $horas_mes;
    $tipo_codigo = $ocorrencia['tipo_codigo'] ?? '';
    
    // Se considera dia inteiro, calcula como falta completa
    if (!empty($ocorrencia['considera_dia_inteiro']) && $ocorrencia['considera_dia_inteiro'] == 1) {
        return $valor_hora * $jornada_diaria;
    }
    
    // Se for falta ou ausência injustificada e não tem tempo de atraso, calcula como dia inteiro
    if (in_array($tipo_codigo, ['falta', 'ausencia_injustificada']) && empty($ocorrencia['tempo_atraso_minutos'])) {
        return $valor_hora * $jornada_diaria;
    }
    
    // Se tem tempo de atraso, calcula proporcional ao salário
    if ($ocorrencia['tempo_atraso_minutos']) {
        // Calcula valor por minuto trabalhado
        $valor_minuto = $valor_hora / 60;
        return $valor_minuto * $ocorrencia['tempo_atraso_minutos'];
    }
    
    // Se for falta sem tempo de atraso, calcula como dia inteiro
    if (in_array($tipo_codigo, ['falta', 'ausencia_injustificada'])) {
        return $valor_hora * $jornada_diaria;
    }
    
    return 0;
}

/**
 * Busca campos dinâmicos de um tipo de ocorrência
 */
function get_campos_dinamicos_tipo($tipo_ocorrencia_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM tipos_ocorrencias_campos
        WHERE tipo_ocorrencia_id = ?
        ORDER BY ordem ASC
    ");
    $stmt->execute([$tipo_ocorrencia_id]);
    return $stmt->fetchAll();
}

/**
 * Valida campos dinâmicos conforme regras
 */
function validar_campos_dinamicos($campos_dinamicos, $valores) {
    $erros = [];
    
    foreach ($campos_dinamicos as $campo) {
        $codigo = $campo['codigo'];
        $valor = $valores[$codigo] ?? null;
        
        // Valida obrigatório
        if ($campo['obrigatorio'] && empty($valor)) {
            $erros[] = "Campo '{$campo['label']}' é obrigatório";
            continue;
        }
        
        // Validações customizadas
        if ($campo['validacao']) {
            $validacoes = json_decode($campo['validacao'], true);
            
            if ($validacoes) {
                // Validação de tipo
                if (isset($validacoes['tipo'])) {
                    switch ($validacoes['tipo']) {
                        case 'number':
                            if (!is_numeric($valor)) {
                                $erros[] = "Campo '{$campo['label']}' deve ser numérico";
                            }
                            break;
                        case 'email':
                            if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                                $erros[] = "Campo '{$campo['label']}' deve ser um email válido";
                            }
                            break;
                    }
                }
                
                // Validação de mínimo/máximo
                if (isset($validacoes['min']) && $valor < $validacoes['min']) {
                    $erros[] = "Campo '{$campo['label']}' deve ser no mínimo {$validacoes['min']}";
                }
                if (isset($validacoes['max']) && $valor > $validacoes['max']) {
                    $erros[] = "Campo '{$campo['label']}' deve ser no máximo {$validacoes['max']}";
                }
            }
        }
    }
    
    return $erros;
}

/**
 * Envia notificações de ocorrência
 */
function enviar_notificacoes_ocorrencia($ocorrencia_id) {
    $pdo = getDB();
    
    // Busca dados da ocorrência
    $stmt = $pdo->prepare("
        SELECT o.*, 
               t.notificar_colaborador, t.notificar_colaborador_sistema, t.notificar_colaborador_email, t.notificar_colaborador_push,
               t.notificar_gestor, t.notificar_gestor_sistema, t.notificar_gestor_email, t.notificar_gestor_push,
               t.notificar_rh, t.notificar_rh_sistema, t.notificar_rh_email, t.notificar_rh_push,
               c.nome_completo, c.email_pessoal, c.setor_id
        FROM ocorrencias o
        INNER JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
        INNER JOIN colaboradores c ON o.colaborador_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$ocorrencia_id]);
    $ocorrencia = $stmt->fetch();
    
    if (!$ocorrencia) {
        return false;
    }
    
    // Busca gestor do setor se necessário
    $gestor_id = null;
    if ($ocorrencia['notificar_gestor'] && !empty($ocorrencia['setor_id'])) {
        $stmt_gestor = $pdo->prepare("SELECT id FROM usuarios WHERE setor_id = ? AND role = 'GESTOR' AND status = 'ativo' LIMIT 1");
        $stmt_gestor->execute([$ocorrencia['setor_id']]);
        $gestor = $stmt_gestor->fetch();
        if ($gestor) {
            $gestor_id = $gestor['id'];
        }
    }
    $ocorrencia['gestor_id'] = $gestor_id;
    
    require_once __DIR__ . '/notificacoes.php';
    require_once __DIR__ . '/push_notifications.php';
    
    // Busca tipo de ocorrência para mensagem mais completa
    $tipo_nome = $ocorrencia['tipo'] ?? 'Ocorrência';
    if ($ocorrencia['tipo_ocorrencia_id']) {
        $stmt_tipo = $pdo->prepare("SELECT nome FROM tipos_ocorrencias WHERE id = ?");
        $stmt_tipo->execute([$ocorrencia['tipo_ocorrencia_id']]);
        $tipo_data = $stmt_tipo->fetch();
        if ($tipo_data) {
            $tipo_nome = $tipo_data['nome'];
        }
    }
    
    // Prepara URL completa para push notifications
    $base_url = get_base_url();
    $link_ocorrencia = $base_url . '/pages/ocorrencia_view.php?id=' . $ocorrencia_id;
    $link_ocorrencia_relativo = '../pages/ocorrencia_view.php?id=' . $ocorrencia_id;
    
    // Prepara mensagens
    $titulo_colaborador = 'Nova Ocorrência Registrada';
    $mensagem_colaborador = "Uma ocorrência do tipo '{$tipo_nome}' foi registrada em seu nome.";
    
    $titulo_gestor = 'Nova Ocorrência no Setor';
    $mensagem_gestor = "Uma ocorrência do tipo '{$tipo_nome}' foi registrada para o colaborador {$ocorrencia['nome_completo']}.";
    
    $titulo_rh = 'Nova Ocorrência Registrada';
    $mensagem_rh = "Uma ocorrência do tipo '{$tipo_nome}' foi registrada para o colaborador {$ocorrencia['nome_completo']}.";
    
    // Notifica colaborador (respeitando canais configurados)
    if ($ocorrencia['notificar_colaborador']) {
        // Busca usuário do colaborador se existir
        $stmt_user = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ?");
        $stmt_user->execute([$ocorrencia['colaborador_id']]);
        $usuario_colab = $stmt_user->fetch();
        
        // Notificação no sistema
        if ($ocorrencia['notificar_colaborador_sistema']) {
            criar_notificacao(
                $usuario_colab['id'] ?? null,
                $ocorrencia['colaborador_id'],
                'ocorrencia',
                $titulo_colaborador,
                $mensagem_colaborador,
                $link_ocorrencia_relativo,
                $ocorrencia_id,
                'ocorrencia'
            );
        }
        
        // Push notification para colaborador
        if ($ocorrencia['notificar_colaborador_push']) {
            try {
                if ($usuario_colab && $usuario_colab['id']) {
                    // Se tem usuário vinculado, envia por usuario_id
                    enviar_push_usuario($usuario_colab['id'], $titulo_colaborador, $mensagem_colaborador, $link_ocorrencia);
                } else {
                    // Se não tem usuário, envia por colaborador_id
                    enviar_push_colaborador($ocorrencia['colaborador_id'], $titulo_colaborador, $mensagem_colaborador, $link_ocorrencia);
                }
            } catch (Exception $e) {
                error_log("Erro ao enviar push para colaborador: " . $e->getMessage());
            }
        }
        
        // Email (verifica se está habilitado)
        if ($ocorrencia['notificar_colaborador_email']) {
            // Email já é enviado pela função enviar_email_ocorrencia quando chamada
            // A função enviar_email_ocorrencia precisa verificar essa configuração também
        }
    }
    
    // Notifica gestor (respeitando canais configurados)
    if ($ocorrencia['notificar_gestor'] && $ocorrencia['gestor_id']) {
        // Notificação no sistema
        if ($ocorrencia['notificar_gestor_sistema']) {
            criar_notificacao(
                $ocorrencia['gestor_id'],
                null,
                'ocorrencia',
                $titulo_gestor,
                $mensagem_gestor,
                $link_ocorrencia_relativo,
                $ocorrencia_id,
                'ocorrencia'
            );
        }
        
        // Push notification para gestor
        if ($ocorrencia['notificar_gestor_push']) {
            try {
                enviar_push_usuario($ocorrencia['gestor_id'], $titulo_gestor, $mensagem_gestor, $link_ocorrencia);
            } catch (Exception $e) {
                error_log("Erro ao enviar push para gestor: " . $e->getMessage());
            }
        }
        
        // Email (verifica se está habilitado)
        if ($ocorrencia['notificar_gestor_email']) {
            // Email já é enviado pela função enviar_email_ocorrencia quando chamada
        }
    }
    
    // Notifica RH (todos os usuários RH da empresa) - respeitando canais configurados
    if ($ocorrencia['notificar_rh']) {
        $stmt_rh = $pdo->prepare("
            SELECT id FROM usuarios
            WHERE role = 'RH' AND empresa_id = (
                SELECT empresa_id FROM colaboradores WHERE id = ?
            )
        ");
        $stmt_rh->execute([$ocorrencia['colaborador_id']]);
        $usuarios_rh = $stmt_rh->fetchAll();
        
        foreach ($usuarios_rh as $usuario_rh) {
            // Notificação no sistema
            if ($ocorrencia['notificar_rh_sistema']) {
                criar_notificacao(
                    $usuario_rh['id'],
                    null,
                    'ocorrencia',
                    $titulo_rh,
                    $mensagem_rh,
                    $link_ocorrencia_relativo,
                    $ocorrencia_id,
                    'ocorrencia'
                );
            }
            
            // Push notification para RH
            if ($ocorrencia['notificar_rh_push']) {
                try {
                    enviar_push_usuario($usuario_rh['id'], $titulo_rh, $mensagem_rh, $link_ocorrencia);
                } catch (Exception $e) {
                    error_log("Erro ao enviar push para RH (ID: {$usuario_rh['id']}): " . $e->getMessage());
                }
            }
            
            // Email (verifica se está habilitado)
            if ($ocorrencia['notificar_rh_email']) {
                // Email já é enviado pela função enviar_email_ocorrencia quando chamada
            }
        }
    }
    
    return true;
}

/**
 * Busca tags disponíveis
 */
function get_tags_ocorrencias() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM ocorrencias_tags WHERE ativo = 1 ORDER BY nome");
    return $stmt->fetchAll();
}

/**
 * Busca templates de descrição para um tipo
 */
function get_templates_descricao($tipo_ocorrencia_id = null) {
    $pdo = getDB();
    
    if ($tipo_ocorrencia_id) {
        $stmt = $pdo->prepare("
            SELECT * FROM ocorrencias_templates_descricao
            WHERE ativo = 1 AND (tipo_ocorrencia_id = ? OR tipo_ocorrencia_id IS NULL)
            ORDER BY tipo_ocorrencia_id DESC, ordem ASC
        ");
        $stmt->execute([$tipo_ocorrencia_id]);
    } else {
        $stmt = $pdo->query("
            SELECT * FROM ocorrencias_templates_descricao
            WHERE ativo = 1 AND tipo_ocorrencia_id IS NULL
            ORDER BY ordem ASC
        ");
    }
    
    return $stmt->fetchAll();
}

/**
 * Processa e salva campos dinâmicos de um tipo de ocorrência
 */
function processar_campos_dinamicos($tipo_ocorrencia_id, $campos) {
    $pdo = getDB();
    
    // Remove campos existentes que não estão mais na lista
    $ids_manter = [];
    foreach ($campos as $campo) {
        if (isset($campo['id']) && $campo['id']) {
            $ids_manter[] = (int)$campo['id'];
        }
    }
    
    if (!empty($ids_manter)) {
        $placeholders = implode(',', array_fill(0, count($ids_manter), '?'));
        $stmt = $pdo->prepare("DELETE FROM tipos_ocorrencias_campos WHERE tipo_ocorrencia_id = ? AND id NOT IN ($placeholders)");
        $stmt->execute(array_merge([$tipo_ocorrencia_id], $ids_manter));
    } else {
        $stmt = $pdo->prepare("DELETE FROM tipos_ocorrencias_campos WHERE tipo_ocorrencia_id = ?");
        $stmt->execute([$tipo_ocorrencia_id]);
    }
    
    // Salva/atualiza campos
    foreach ($campos as $index => $campo) {
        $id = $campo['id'] ?? null;
        $nome = sanitize($campo['nome'] ?? '');
        $codigo = sanitize($campo['codigo'] ?? '');
        $tipo_campo = $campo['tipo_campo'] ?? 'text';
        $label = sanitize($campo['label'] ?? $nome); // Usa nome como label se label não fornecido
        $placeholder = sanitize($campo['placeholder'] ?? '');
        $obrigatorio = isset($campo['obrigatorio']) ? 1 : 0;
        $valor_padrao = sanitize($campo['valor_padrao'] ?? '');
        
        // Se label não foi fornecido explicitamente, usa o nome
        if (empty($label) || $label === $nome) {
            $label = $nome;
        }
        
        // Gera código automaticamente se não fornecido
        if (empty($codigo)) {
            $codigo_origem = !empty($nome) ? $nome : $label;
            $codigo = strtolower($codigo_origem);
            // Remove acentos
            $codigo = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $codigo);
            // Remove caracteres especiais
            $codigo = preg_replace('/[^a-z0-9\s]/', '', $codigo);
            // Substitui espaços por underscore
            $codigo = preg_replace('/\s+/', '_', $codigo);
            // Remove underscores duplicados
            $codigo = preg_replace('/_+/', '_', $codigo);
            // Remove underscores do início/fim
            $codigo = trim($codigo, '_');
        }
        
        // Processa opções (converte de texto para array)
        $opcoes = null;
        if (!empty($campo['opcoes_text'])) {
            $opcoes_array = array_filter(array_map('trim', explode("\n", $campo['opcoes_text'])));
            if (!empty($opcoes_array)) {
                $opcoes = json_encode($opcoes_array);
            }
        } elseif (!empty($campo['opcoes'])) {
            $opcoes = is_string($campo['opcoes']) ? $campo['opcoes'] : json_encode($campo['opcoes']);
        }
        
        $validacao = !empty($campo['validacao']) ? json_encode($campo['validacao']) : null;
        $condicao_exibir = !empty($campo['condicao_exibir']) ? json_encode($campo['condicao_exibir']) : null;
        $ordem = $index;
        
        if ($id) {
            // Atualiza
            $stmt = $pdo->prepare("
                UPDATE tipos_ocorrencias_campos SET
                nome = ?, codigo = ?, tipo_campo = ?, label = ?, placeholder = ?,
                obrigatorio = ?, valor_padrao = ?, opcoes = ?, validacao = ?,
                condicao_exibir = ?, ordem = ?
                WHERE id = ? AND tipo_ocorrencia_id = ?
            ");
            $stmt->execute([
                $nome, $codigo, $tipo_campo, $label, $placeholder,
                $obrigatorio, $valor_padrao, $opcoes, $validacao,
                $condicao_exibir, $ordem, $id, $tipo_ocorrencia_id
            ]);
        } else {
            // Insere
            $stmt = $pdo->prepare("
                INSERT INTO tipos_ocorrencias_campos
                (tipo_ocorrencia_id, nome, codigo, tipo_campo, label, placeholder,
                 obrigatorio, valor_padrao, opcoes, validacao, condicao_exibir, ordem)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tipo_ocorrencia_id, $nome, $codigo, $tipo_campo, $label, $placeholder,
                $obrigatorio, $valor_padrao, $opcoes, $validacao, $condicao_exibir, $ordem
            ]);
        }
    }
}

/**
 * ============================================
 * SISTEMA DE FLAGS AUTOMÁTICAS
 * ============================================
 */

/**
 * Cria uma flag automaticamente quando uma ocorrência é registrada
 * @param int $ocorrencia_id ID da ocorrência
 * @param int $usuario_id ID do usuário que criou a ocorrência
 * @return array Resultado da operação
 */
function criar_flag_automatica($ocorrencia_id, $usuario_id) {
    $pdo = getDB();
    
    try {
        // Busca dados da ocorrência
        $stmt = $pdo->prepare("
            SELECT o.*, t.gera_flag, t.tipo_flag, c.id as colaborador_id
            FROM ocorrencias o
            INNER JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
            INNER JOIN colaboradores c ON o.colaborador_id = c.id
            WHERE o.id = ?
        ");
        $stmt->execute([$ocorrencia_id]);
        $ocorrencia = $stmt->fetch();
        
        if (!$ocorrencia || !$ocorrencia['gera_flag'] || !$ocorrencia['tipo_flag']) {
            return ['success' => false, 'message' => 'Este tipo de ocorrência não gera flag'];
        }
        
        // Verifica se a ocorrência está aprovada (flags só são criadas para ocorrências aprovadas)
        if ($ocorrencia['status_aprovacao'] !== 'aprovada') {
            return ['success' => false, 'message' => 'Flag será criada após aprovação da ocorrência'];
        }
        
        // Verifica se já existe flag para esta ocorrência
        $stmt_check = $pdo->prepare("SELECT id FROM ocorrencias_flags WHERE ocorrencia_id = ?");
        $stmt_check->execute([$ocorrencia_id]);
        if ($stmt_check->fetch()) {
            return ['success' => false, 'message' => 'Flag já existe para esta ocorrência'];
        }
        
        // Calcula data de validade (30 dias corridos a partir da data da ocorrência)
        $data_flag = $ocorrencia['data_ocorrencia'];
        
        // Verifica se colaborador tem flags ativas - se tiver, renova todas para contar juntas
        verificar_renovacao_flags($ocorrencia['colaborador_id'], $usuario_id);
        
        // Busca a nova validade (pode ter sido renovada pela função acima)
        $stmt_validade = $pdo->prepare("
            SELECT MAX(data_validade) as max_validade
            FROM ocorrencias_flags
            WHERE colaborador_id = ? AND status = 'ativa' AND data_validade >= CURDATE()
        ");
        $stmt_validade->execute([$ocorrencia['colaborador_id']]);
        $result_validade = $stmt_validade->fetch();
        
        // Se tem flags ativas, usa a validade renovada, senão cria nova validade
        if ($result_validade['max_validade']) {
            $data_validade = $result_validade['max_validade'];
        } else {
            $data_validade = date('Y-m-d', strtotime($data_flag . ' +30 days'));
        }
        
        // Insere a flag
        $stmt = $pdo->prepare("
            INSERT INTO ocorrencias_flags 
            (colaborador_id, ocorrencia_id, tipo_flag, data_flag, data_validade, status, created_by)
            VALUES (?, ?, ?, ?, ?, 'ativa', ?)
        ");
        $stmt->execute([
            $ocorrencia['colaborador_id'],
            $ocorrencia_id,
            $ocorrencia['tipo_flag'],
            $data_flag,
            $data_validade,
            $usuario_id
        ]);
        
        $flag_id = $pdo->lastInsertId();
        
        // Registra histórico
        registrar_historico_flag($flag_id, 'criada', $usuario_id, 'Flag criada automaticamente pela ocorrência');
        
        // Verifica se atingiu 3 flags ativas (mas não desliga automaticamente)
        $flags_ativas = contar_flags_ativas($ocorrencia['colaborador_id']);
        
        return [
            'success' => true,
            'flag_id' => $flag_id,
            'flags_ativas' => $flags_ativas,
            'message' => $flags_ativas >= 3 
                ? 'Flag criada! ATENÇÃO: Colaborador possui 3 ou mais flags ativas.' 
                : 'Flag criada com sucesso'
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao criar flag automática: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Conta quantas flags ativas um colaborador possui
 * @param int $colaborador_id ID do colaborador
 * @return int Número de flags ativas
 */
function contar_flags_ativas($colaborador_id) {
    $pdo = getDB();
    
    // Verifica e expira flags vencidas apenas deste colaborador (otimizado)
    // Nota: Para melhor performance, recomenda-se executar cron diariamente
    verificar_expiracao_flags($colaborador_id);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM ocorrencias_flags
        WHERE colaborador_id = ? AND status = 'ativa' AND data_validade >= CURDATE()
    ");
    $stmt->execute([$colaborador_id]);
    $result = $stmt->fetch();
    
    return (int)$result['total'];
}

/**
 * Busca todas as flags ativas de um colaborador
 * @param int $colaborador_id ID do colaborador
 * @return array Lista de flags ativas
 */
function get_flags_ativas($colaborador_id) {
    $pdo = getDB();
    
    // Verifica e expira flags vencidas apenas deste colaborador (otimizado)
    // Nota: Para melhor performance, recomenda-se executar cron diariamente
    verificar_expiracao_flags($colaborador_id);
    
    $stmt = $pdo->prepare("
        SELECT f.*, 
               o.descricao as ocorrencia_descricao,
               o.data_ocorrencia,
               t.nome as tipo_ocorrencia_nome,
               u.nome as created_by_nome
        FROM ocorrencias_flags f
        INNER JOIN ocorrencias o ON f.ocorrencia_id = o.id
        LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
        LEFT JOIN usuarios u ON f.created_by = u.id
        WHERE f.colaborador_id = ? 
        AND f.status = 'ativa' 
        AND f.data_validade >= CURDATE()
        ORDER BY f.data_flag DESC, f.data_validade ASC
    ");
    $stmt->execute([$colaborador_id]);
    return $stmt->fetchAll();
}

/**
 * Busca todas as flags (ativas e expiradas) de um colaborador
 * @param int $colaborador_id ID do colaborador
 * @param string $status Filtro por status ('ativa', 'expirada' ou null para todas)
 * @return array Lista de flags
 */
function get_flags_colaborador($colaborador_id, $status = null) {
    $pdo = getDB();
    
    $sql = "
        SELECT f.*, 
               o.descricao as ocorrencia_descricao,
               o.data_ocorrencia,
               t.nome as tipo_ocorrencia_nome,
               u.nome as created_by_nome
        FROM ocorrencias_flags f
        INNER JOIN ocorrencias o ON f.ocorrencia_id = o.id
        LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
        LEFT JOIN usuarios u ON f.created_by = u.id
        WHERE f.colaborador_id = ?
    ";
    
    $params = [$colaborador_id];
    
    if ($status) {
        $sql .= " AND f.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY f.data_flag DESC, f.data_validade ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Verifica e expira flags vencidas para um colaborador (ou todos)
 * @param int|null $colaborador_id ID do colaborador (null para verificar todos)
 * @return int Número de flags expiradas
 */
function verificar_expiracao_flags($colaborador_id = null) {
    $pdo = getDB();
    
    $sql = "
        UPDATE ocorrencias_flags 
        SET status = 'expirada', updated_at = NOW()
        WHERE status = 'ativa' 
        AND data_validade < CURDATE()
    ";
    
    $params = [];
    if ($colaborador_id) {
        $sql .= " AND colaborador_id = ?";
        $params[] = $colaborador_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $flags_expiradas = $stmt->rowCount();
    
    // Registra histórico das flags expiradas
    if ($flags_expiradas > 0) {
        $sql_historico = "
            INSERT INTO ocorrencias_flags_historico (flag_id, acao, usuario_id, observacoes)
            SELECT id, 'expirada', 1, 'Flag expirada automaticamente'
            FROM ocorrencias_flags
            WHERE status = 'expirada' 
            AND data_validade < CURDATE()
        ";
        
        if ($colaborador_id) {
            $sql_historico .= " AND colaborador_id = ?";
        }
        
        $stmt_historico = $pdo->prepare($sql_historico);
        $stmt_historico->execute($params);
    }
    
    return $flags_expiradas;
}

/**
 * Registra histórico de uma flag
 * @param int $flag_id ID da flag
 * @param string $acao Ação realizada ('criada', 'expirada', 'renovada', 'cancelada')
 * @param int $usuario_id ID do usuário
 * @param string|null $observacoes Observações
 */
function registrar_historico_flag($flag_id, $acao, $usuario_id, $observacoes = null) {
    $pdo = getDB();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ocorrencias_flags_historico (flag_id, acao, usuario_id, observacoes)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$flag_id, $acao, $usuario_id, $observacoes]);
    } catch (Exception $e) {
        error_log("Erro ao registrar histórico de flag: " . $e->getMessage());
    }
}

/**
 * Renova validade de uma flag (adiciona 30 dias a partir de hoje)
 * Usado quando colaborador recebe nova flag enquanto outra está ativa
 * @param int $flag_id ID da flag
 * @param int $usuario_id ID do usuário que está renovando
 * @return array Resultado da operação
 */
function renovar_validade_flag($flag_id, $usuario_id) {
    $pdo = getDB();
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM ocorrencias_flags WHERE id = ?");
        $stmt->execute([$flag_id]);
        $flag = $stmt->fetch();
        
        if (!$flag) {
            return ['success' => false, 'message' => 'Flag não encontrada'];
        }
        
        // Calcula nova validade (30 dias a partir de hoje)
        $nova_validade = date('Y-m-d', strtotime('+30 days'));
        
        $stmt = $pdo->prepare("
            UPDATE ocorrencias_flags 
            SET data_validade = ?, status = 'ativa', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nova_validade, $flag_id]);
        
        registrar_historico_flag($flag_id, 'renovada', $usuario_id, "Validade renovada até {$nova_validade}");
        
        return ['success' => true, 'nova_validade' => $nova_validade];
        
    } catch (Exception $e) {
        error_log("Erro ao renovar validade de flag: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verifica se deve renovar validade de flags existentes ao criar nova flag
 * Se colaborador tem flags ativas, renova todas para contar juntas
 * @param int $colaborador_id ID do colaborador
 * @param int $usuario_id ID do usuário
 */
function verificar_renovacao_flags($colaborador_id, $usuario_id) {
    $pdo = getDB();
    
    // Busca flags ativas do colaborador
    $stmt = $pdo->prepare("
        SELECT id FROM ocorrencias_flags
        WHERE colaborador_id = ? 
        AND status = 'ativa' 
        AND data_validade >= CURDATE()
    ");
    $stmt->execute([$colaborador_id]);
    $flags_ativas = $stmt->fetchAll();
    
    // Se tem flags ativas, renova todas para contar juntas
    if (count($flags_ativas) > 0) {
        $nova_validade = date('Y-m-d', strtotime('+30 days'));
        
        foreach ($flags_ativas as $flag) {
            $stmt_update = $pdo->prepare("
                UPDATE ocorrencias_flags 
                SET data_validade = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([$nova_validade, $flag['id']]);
            
            registrar_historico_flag(
                $flag['id'], 
                'renovada', 
                $usuario_id, 
                "Validade renovada para contar junto com nova flag (válido até {$nova_validade})"
            );
        }
    }
}

/**
 * Obtém label do tipo de flag
 * @param string $tipo_flag Tipo da flag
 * @return string Label formatado
 */
function get_label_tipo_flag($tipo_flag) {
    $labels = [
        'falta_nao_justificada' => 'Falta Não Justificada',
        'falta_compromisso_pessoal' => 'Falta por Compromisso Pessoal',
        'ma_conduta' => 'Má Conduta'
    ];
    
    return $labels[$tipo_flag] ?? $tipo_flag;
}

/**
 * Obtém cor do badge para tipo de flag
 * @param string $tipo_flag Tipo da flag
 * @return string Classe CSS do badge
 */
function get_cor_badge_flag($tipo_flag) {
    $cores = [
        'falta_nao_justificada' => 'danger',
        'falta_compromisso_pessoal' => 'warning',
        'ma_conduta' => 'danger'
    ];
    
    return $cores[$tipo_flag] ?? 'secondary';
}

