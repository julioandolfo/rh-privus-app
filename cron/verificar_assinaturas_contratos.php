<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/autentique_service.php';

// Função de log
function log_cron($message) {
    $logFile = __DIR__ . '/../logs/cron_assinaturas.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

log_cron("=== INICIANDO VERIFICAÇÃO DE ASSINATURAS ===");

try {
    $pdo = getDB();
    
    // Verifica se Autentique está configurado
    $stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch();
    
    if (!$config) {
        log_cron("ERRO: Autentique não configurado");
        exit(1);
    }
    
    // Busca contratos que precisam ser verificados
    // Status: enviado (aguardando primeira assinatura) ou aguardando (já tem algumas assinaturas)
    $stmt = $pdo->prepare("
        SELECT c.*, 
               col.nome_completo as colaborador_nome,
               col.email_pessoal as colaborador_email
        FROM contratos c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        WHERE c.status IN ('enviado', 'aguardando')
          AND c.autentique_document_id IS NOT NULL
        ORDER BY c.created_at ASC
    ");
    $stmt->execute();
    $contratos = $stmt->fetchAll();
    
    $total_contratos = count($contratos);
    log_cron("Contratos para verificar: $total_contratos");
    
    if ($total_contratos === 0) {
        log_cron("Nenhum contrato pendente de verificação");
        log_cron("=== FIM ===");
        exit(0);
    }
    
    // Inicializa serviço Autentique
    $service = new AutentiqueService();
    
    $atualizados = 0;
    $erros = 0;
    $sem_mudanca = 0;
    
    foreach ($contratos as $contrato) {
        $contrato_id = $contrato['id'];
        $document_id = $contrato['autentique_document_id'];
        $status_anterior = $contrato['status'];
        
        log_cron("Verificando contrato #$contrato_id (document: $document_id) - Status: $status_anterior");
        
        try {
            // Consulta status no Autentique
            $status_autentique = $service->consultarStatus($document_id);
            
            if (!$status_autentique) {
                log_cron("  -> Documento não encontrado no Autentique");
                $erros++;
                continue;
            }
            
            $signers_api = $status_autentique['signers'] ?? [];
            
            if (empty($signers_api)) {
                log_cron("  -> Sem signatários na resposta da API");
                $sem_mudanca++;
                continue;
            }
            
            // Busca signatários locais
            $stmt_local = $pdo->prepare("
                SELECT * FROM contratos_signatarios 
                WHERE contrato_id = ? 
                ORDER BY ordem_assinatura
            ");
            $stmt_local->execute([$contrato_id]);
            $signatarios_locais = $stmt_local->fetchAll();
            
            // Atualiza cada signatário baseado na resposta da API
            $atualizacoes = 0;
            foreach ($signers_api as $signer) {
                $signer_email = $signer['email'] ?? null;
                $signer_signed = $signer['signed'] ?? false;
                $signer_signed_at = $signer['signedAt'] ?? null;
                $signer_id = $signer['id'] ?? null;
                $signer_link = $signer['link'] ?? null;
                
                if (!$signer_email) {
                    continue;
                }
                
                // Verifica se este email existe localmente
                $stmt_check = $pdo->prepare("
                    SELECT id, email, assinado 
                    FROM contratos_signatarios 
                    WHERE contrato_id = ? AND LOWER(email) = LOWER(?)
                ");
                $stmt_check->execute([$contrato_id, $signer_email]);
                $local_match = $stmt_check->fetch();
                
                if (!$local_match) {
                    log_cron("  -> Email $signer_email não existe localmente (ignorado)");
                    continue;
                }
                
                // Atualiza status de assinatura
                $stmt_update = $pdo->prepare("
                    UPDATE contratos_signatarios 
                    SET assinado = ?, 
                        data_assinatura = COALESCE(?, data_assinatura),
                        autentique_signer_id = COALESCE(?, autentique_signer_id),
                        link_publico = COALESCE(?, link_publico)
                    WHERE LOWER(email) = LOWER(?) AND contrato_id = ?
                ");
                $stmt_update->execute([
                    $signer_signed ? 1 : 0,
                    $signer_signed_at,
                    $signer_id,
                    $signer_link,
                    $signer_email,
                    $contrato_id
                ]);
                
                if ($stmt_update->rowCount() > 0) {
                    $atualizacoes++;
                    log_cron("  -> Atualizado: $signer_email - Assinado: " . ($signer_signed ? 'SIM' : 'NÃO'));
                }
            }
            
            // Verifica status atual dos signatários locais após atualizações
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) as total, SUM(assinado) as assinados 
                FROM contratos_signatarios 
                WHERE contrato_id = ?
            ");
            $stmt_check->execute([$contrato_id]);
            $check = $stmt_check->fetch();
            
            $total_signatarios = (int)($check['total'] ?? 0);
            $assinados = (int)($check['assinados'] ?? 0);
            
            // Determina novo status
            $novo_status = null;
            if ($total_signatarios > 0 && $assinados === $total_signatarios) {
                $novo_status = 'assinado';
            } elseif ($assinados > 0) {
                $novo_status = 'aguardando';
            } elseif ($assinados === 0 && $status_anterior !== 'enviado') {
                $novo_status = 'enviado';
            }
            
            // Atualiza status do contrato se mudou
            if ($novo_status && $novo_status !== $status_anterior) {
                $stmt_update_status = $pdo->prepare("
                    UPDATE contratos 
                    SET status = ? 
                    WHERE id = ?
                ");
                $stmt_update_status->execute([$novo_status, $contrato_id]);
                
                log_cron("  -> STATUS ATUALIZADO: $status_anterior -> $novo_status ($assinados/$total_signatarios assinaturas)");
                $atualizados++;
            } else {
                log_cron("  -> Sem mudança de status ($assinados/$total_signatarios assinaturas)");
                $sem_mudanca++;
            }
            
            // Registra evento de verificação
            try {
                $stmt_evento = $pdo->prepare("
                    INSERT INTO contratos_eventos (contrato_id, tipo_evento, dados_json)
                    VALUES (?, 'cron.verificacao', ?)
                ");
                $stmt_evento->execute([
                    $contrato_id,
                    json_encode([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'signers_api' => count($signers_api),
                        'assinados_local' => $assinados,
                        'total_local' => $total_signatarios,
                        'status_anterior' => $status_anterior,
                        'status_novo' => $novo_status
                    ], JSON_UNESCAPED_UNICODE)
                ]);
            } catch (Exception $e) {
                // Não falha o processo se não conseguir salvar evento
                log_cron("  -> Aviso: Não foi possível salvar evento: " . $e->getMessage());
            }
            
            // Pequena pausa para não sobrecarregar a API
            usleep(500000); // 500ms
            
        } catch (Exception $e) {
            log_cron("  -> ERRO ao verificar contrato #$contrato_id: " . $e->getMessage());
            $erros++;
        }
    }
    
    log_cron("=== RESUMO ===");
    log_cron("Total verificado: $total_contratos");
    log_cron("Atualizados: $atualizados");
    log_cron("Sem mudança: $sem_mudanca");
    log_cron("Erros: $erros");
    log_cron("=== FIM ===");
    
} catch (Exception $e) {
    log_cron("ERRO FATAL: " . $e->getMessage());
    log_cron("Stack: " . $e->getTraceAsString());
    exit(1);
}
