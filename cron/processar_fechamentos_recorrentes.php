<?php
/**
 * Script para processar fechamentos extras recorrentes
 * Deve ser executado via cron diariamente (recomendado: 00:00 ou 01:00)
 * 
 * ESTRUTURA DO PROJETO:
 * rh-privus/
 *   ├── cron/
 *   │   └── processar_fechamentos_recorrentes.php  <- Este arquivo
 *   ├── includes/
 *   └── ...
 * 
 * EXEMPLOS DE CONFIGURAÇÃO:
 * 
 * Linux/Mac (crontab -e):
 * 0 1 * * * /usr/bin/php /caminho/completo/para/rh-privus/cron/processar_fechamentos_recorrentes.php
 * 
 * Exemplo real Linux:
 * 0 1 * * * /usr/bin/php /var/www/html/rh-privus/cron/processar_fechamentos_recorrentes.php
 * 
 * Windows Task Scheduler:
 * Programa: C:\laragon\bin\php\php-8.x.x\php.exe
 * Argumentos: C:\laragon\www\rh-privus\cron\processar_fechamentos_recorrentes.php
 * Diretório inicial: C:\laragon\www\rh-privus
 * 
 * Ou via linha de comando Windows:
 * php.exe C:\laragon\www\rh-privus\cron\processar_fechamentos_recorrentes.php
 * 
 * IMPORTANTE: O caminho deve ser ABSOLUTO (completo), não relativo!
 */

require_once __DIR__ . '/../includes/functions.php';

// Define timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $pdo = getDB();
    
    // Busca templates recorrentes ativos
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            tb.nome as tipo_bonus_nome,
            e.nome_fantasia as empresa_nome
        FROM fechamentos_pagamento_extras_config t
        LEFT JOIN tipos_bonus tb ON t.tipo_bonus_id = tb.id
        LEFT JOIN empresas e ON t.empresa_id = e.id
        WHERE t.ativo = 1
        AND t.recorrente = 1
        AND t.dia_mes IS NOT NULL
        AND t.dia_mes > 0
        AND t.dia_mes <= 31
    ");
    $stmt->execute();
    $templates = $stmt->fetchAll();
    
    if (empty($templates)) {
        echo "Nenhum template recorrente encontrado.\n";
        exit(0);
    }
    
    $hoje = (int)date('d');
    $mes_atual = date('Y-m');
    $processados = 0;
    $erros = 0;
    
    foreach ($templates as $template) {
        // Verifica se é o dia correto
        if ($template['dia_mes'] != $hoje) {
            continue;
        }
        
        // Verifica se já foi criado um fechamento para este template neste mês
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM fechamentos_pagamento fp
            WHERE fp.tipo_fechamento = 'extra'
            AND fp.subtipo_fechamento = ?
            AND fp.mes_referencia = ?
            AND fp.empresa_id = ?
            AND fp.referencia_externa LIKE ?
        ");
        $referencia_like = '%' . $template['nome'] . '%';
        $stmt_check->execute([
            $template['subtipo'],
            $mes_atual,
            $template['empresa_id'] ?: null,
            $referencia_like
        ]);
        $existe = $stmt_check->fetch();
        
        if ($existe && $existe['total'] > 0) {
            echo "Template '{$template['nome']}' já foi processado este mês. Pulando...\n";
            continue;
        }
        
        try {
            // Determina empresa
            $empresa_id = $template['empresa_id'];
            if (!$empresa_id) {
                // Se não tem empresa específica, busca todas (ou apenas uma se RH)
                // Por segurança, vamos pular templates sem empresa definida
                echo "Template '{$template['nome']}' não tem empresa definida. Pulando...\n";
                continue;
            }
            
            // Busca colaboradores ativos da empresa
            $stmt_colabs = $pdo->prepare("
                SELECT id, nome_completo
                FROM colaboradores
                WHERE empresa_id = ?
                AND ativo = 1
            ");
            $stmt_colabs->execute([$empresa_id]);
            $colaboradores = $stmt_colabs->fetchAll();
            
            if (empty($colaboradores)) {
                echo "Nenhum colaborador ativo encontrado para empresa do template '{$template['nome']}'. Pulando...\n";
                continue;
            }
            
            // Cria fechamento extra
            $data_pagamento = date('Y-m-d');
            $referencia_externa = $template['nome'] . ' - ' . date('m/Y');
            
            $stmt_fechamento = $pdo->prepare("
                INSERT INTO fechamentos_pagamento 
                (empresa_id, tipo_fechamento, subtipo_fechamento, mes_referencia, data_pagamento, 
                 referencia_externa, descricao, status, usuario_id, permite_edicao)
                VALUES (?, 'extra', ?, ?, ?, ?, ?, 'aberto', NULL, 1)
            ");
            $stmt_fechamento->execute([
                $empresa_id,
                $template['subtipo'],
                $mes_atual,
                $data_pagamento,
                $referencia_externa,
                $template['observacoes'] ?? 'Fechamento criado automaticamente via template recorrente'
            ]);
            $fechamento_id = $pdo->lastInsertId();
            
            $total_pagamento = 0;
            
            // Processa colaboradores conforme subtipo
            if ($template['subtipo'] === 'bonus_especifico') {
                // Bônus específico - precisa de tipo de bônus
                if (!$template['tipo_bonus_id']) {
                    throw new Exception("Template '{$template['nome']}' precisa ter tipo de bônus definido");
                }
                
                // Busca período do mês
                $ano_mes = explode('-', $mes_atual);
                $data_inicio = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
                $data_fim = date('Y-m-t', strtotime($data_inicio));
                
                foreach ($colaboradores as $colab) {
                    // Determina valor do bônus
                    $valor_bonus = 0;
                    $stmt_tb = $pdo->prepare("SELECT tipo_valor, valor_fixo FROM tipos_bonus WHERE id = ?");
                    $stmt_tb->execute([$template['tipo_bonus_id']]);
                    $tipo_bonus_data = $stmt_tb->fetch();
                    
                    if ($tipo_bonus_data['tipo_valor'] === 'fixo') {
                        $valor_bonus = (float)($tipo_bonus_data['valor_fixo'] ?? 0);
                    } else {
                        // Busca valor do colaborador
                        $stmt_cb = $pdo->prepare("
                            SELECT valor FROM colaboradores_bonus 
                            WHERE colaborador_id = ? AND tipo_bonus_id = ?
                            AND (data_inicio IS NULL OR data_inicio <= ?)
                            AND (data_fim IS NULL OR data_fim >= ?)
                            LIMIT 1
                        ");
                        $stmt_cb->execute([$colab['id'], $template['tipo_bonus_id'], $data_fim, $data_inicio]);
                        $colab_bonus = $stmt_cb->fetch();
                        $valor_bonus = $colab_bonus ? (float)$colab_bonus['valor'] : 0;
                    }
                    
                    if ($valor_bonus <= 0) {
                        continue; // Pula colaboradores sem bônus
                    }
                    
                    // Calcula desconto por ocorrências (se necessário)
                    require_once __DIR__ . '/../pages/fechamento_pagamentos.php';
                    $desconto_ocorrencias = calcular_desconto_bonus_ocorrencias(
                        $pdo,
                        $template['tipo_bonus_id'],
                        $colab['id'],
                        $valor_bonus,
                        $data_inicio,
                        $data_fim
                    );
                    
                    if ($desconto_ocorrencias > $valor_bonus) {
                        $desconto_ocorrencias = $valor_bonus;
                    }
                    
                    $valor_final = $valor_bonus - $desconto_ocorrencias;
                    
                    // Insere item
                    $stmt_item = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_itens
                        (fechamento_id, colaborador_id, inclui_salario, inclui_horas_extras, 
                         inclui_bonus_automaticos, valor_total)
                        VALUES (?, ?, 0, 0, 0, ?)
                    ");
                    $stmt_item->execute([$fechamento_id, $colab['id'], $valor_final]);
                    
                    // Insere bônus
                    $stmt_bonus = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_bonus
                        (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, 
                         valor_original, desconto_ocorrencias)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_bonus->execute([
                        $fechamento_id,
                        $colab['id'],
                        $template['tipo_bonus_id'],
                        $valor_final,
                        $valor_bonus,
                        $desconto_ocorrencias
                    ]);
                    
                    $total_pagamento += $valor_final;
                }
                
            } elseif ($template['subtipo'] === 'grupal') {
                // Bônus grupal - mesmo valor para todos
                $valor_padrao = $template['valor_padrao'] ?? 0;
                
                if ($valor_padrao <= 0 && $template['tipo_bonus_id']) {
                    // Tenta buscar valor do tipo de bônus
                    $stmt_tb = $pdo->prepare("SELECT tipo_valor, valor_fixo FROM tipos_bonus WHERE id = ?");
                    $stmt_tb->execute([$template['tipo_bonus_id']]);
                    $tipo_bonus_data = $stmt_tb->fetch();
                    if ($tipo_bonus_data && $tipo_bonus_data['tipo_valor'] === 'fixo') {
                        $valor_padrao = (float)($tipo_bonus_data['valor_fixo'] ?? 0);
                    }
                }
                
                if ($valor_padrao <= 0) {
                    throw new Exception("Template '{$template['nome']}' precisa ter valor padrão definido");
                }
                
                foreach ($colaboradores as $colab) {
                    // Insere item
                    $stmt_item = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_itens
                        (fechamento_id, colaborador_id, inclui_salario, inclui_horas_extras, 
                         inclui_bonus_automaticos, valor_manual, valor_total)
                        VALUES (?, ?, 0, 0, 0, ?, ?)
                    ");
                    $stmt_item->execute([$fechamento_id, $colab['id'], $valor_padrao, $valor_padrao]);
                    
                    // Se tem tipo de bônus, registra também
                    if ($template['tipo_bonus_id']) {
                        $stmt_bonus = $pdo->prepare("
                            INSERT INTO fechamentos_pagamento_bonus
                            (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, valor_original)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt_bonus->execute([
                            $fechamento_id,
                            $colab['id'],
                            $template['tipo_bonus_id'],
                            $valor_padrao,
                            $valor_padrao
                        ]);
                    }
                    
                    $total_pagamento += $valor_padrao;
                }
            }
            // Nota: 'individual' e 'adiantamento' não são adequados para recorrência automática
            // pois requerem seleção manual de colaborador e valores específicos
            
            // Atualiza total do fechamento
            $stmt_update = $pdo->prepare("
                UPDATE fechamentos_pagamento 
                SET total_colaboradores = ?, total_pagamento = ?
                WHERE id = ?
            ");
            $stmt_update->execute([count($colaboradores), $total_pagamento, $fechamento_id]);
            
            $processados++;
            echo "Template '{$template['nome']}' processado com sucesso. Fechamento #{$fechamento_id} criado.\n";
            
        } catch (Exception $e) {
            $erros++;
            echo "Erro ao processar template '{$template['nome']}': " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nProcessamento concluído: {$processados} processados, {$erros} erros\n";
    
    // Registra última execução
    try {
        $data_execucao = date('Y-m-d H:i:s');
        $stmt_log = $pdo->prepare("
            INSERT INTO cron_execucoes (nome_cron, data_execucao, processados, erros, status)
            VALUES ('processar_fechamentos_recorrentes', ?, ?, ?, 'sucesso')
            ON DUPLICATE KEY UPDATE 
                data_execucao = VALUES(data_execucao),
                processados = VALUES(processados),
                erros = VALUES(erros),
                status = VALUES(status)
        ");
        $stmt_log->execute([$data_execucao, $processados, $erros]);
    } catch (PDOException $e) {
        // Se a tabela não existir, cria ela
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS cron_execucoes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome_cron VARCHAR(100) NOT NULL UNIQUE,
                    data_execucao DATETIME NOT NULL,
                    processados INT DEFAULT 0,
                    erros INT DEFAULT 0,
                    status VARCHAR(20) DEFAULT 'sucesso',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_nome_cron (nome_cron),
                    INDEX idx_data_execucao (data_execucao)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            // Tenta inserir novamente
            $data_execucao = date('Y-m-d H:i:s');
            $stmt_log = $pdo->prepare("
                INSERT INTO cron_execucoes (nome_cron, data_execucao, processados, erros, status)
                VALUES ('processar_fechamentos_recorrentes', ?, ?, ?, 'sucesso')
            ");
            $stmt_log->execute([$data_execucao, $processados, $erros]);
        } catch (Exception $e2) {
            // Ignora erro de criação de tabela
            error_log("Erro ao registrar execução do cron: " . $e2->getMessage());
        }
    }
    
} catch (Exception $e) {
    echo "Erro fatal: " . $e->getMessage() . "\n";
    
    // Registra execução com erro
    try {
        $pdo = getDB();
        $data_execucao = date('Y-m-d H:i:s');
        $stmt_log = $pdo->prepare("
            INSERT INTO cron_execucoes (nome_cron, data_execucao, processados, erros, status)
            VALUES ('processar_fechamentos_recorrentes', ?, 0, 1, 'erro')
            ON DUPLICATE KEY UPDATE 
                data_execucao = VALUES(data_execucao),
                processados = 0,
                erros = 1,
                status = 'erro'
        ");
        $stmt_log->execute([$data_execucao]);
    } catch (Exception $e2) {
        // Ignora erro
    }
    
    exit(1);
}

