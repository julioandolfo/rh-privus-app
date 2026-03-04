<?php
/**
 * Visualizar Contrato - Detalhes e Status
 */

$page_title = 'Visualizar Contrato';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/autentique_service.php';

require_page_permission('contrato_view.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$contrato_id = intval($_GET['id'] ?? 0);

if ($contrato_id <= 0) {
    redirect('contratos.php', 'Contrato não encontrado.', 'error');
}

// Busca contrato
$stmt = $pdo->prepare("
    SELECT c.*, 
           col.nome_completo as colaborador_nome,
           col.cpf as colaborador_cpf,
           col.email_pessoal as colaborador_email,
           u.nome as criado_por_nome,
           t.nome as template_nome
    FROM contratos c
    INNER JOIN colaboradores col ON c.colaborador_id = col.id
    LEFT JOIN usuarios u ON c.criado_por_usuario_id = u.id
    LEFT JOIN contratos_templates t ON c.template_id = t.id
    WHERE c.id = ?
");

try {
    $stmt->execute([$contrato_id]);
    $contrato = $stmt->fetch();
} catch (Exception $e) {
    error_log("Erro ao buscar contrato: " . $e->getMessage());
    redirect('contratos.php', 'Erro ao carregar contrato: ' . $e->getMessage(), 'error');
}

if (!$contrato) {
    redirect('contratos.php', 'Contrato não encontrado.', 'error');
}

// Busca signatários
$stmt = $pdo->prepare("
    SELECT * FROM contratos_signatarios 
    WHERE contrato_id = ? 
    ORDER BY ordem_assinatura ASC
");
$stmt->execute([$contrato_id]);
$signatarios = $stmt->fetchAll();

// Busca eventos/histórico
$stmt = $pdo->prepare("
    SELECT * FROM contratos_eventos 
    WHERE contrato_id = ? 
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$contrato_id]);
$eventos = $stmt->fetchAll();

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    log_contrato("contrato_view.php - POST recebido: acao=$acao, contrato_id=$contrato_id");
    
    if ($acao === 'duplicar') {
        try {
            $pdo->beginTransaction();

            // Insere novo contrato como rascunho, zerando campos do Autentique
            $stmt = $pdo->prepare("
                INSERT INTO contratos (
                    colaborador_id, template_id, titulo, descricao_funcao,
                    conteudo_final_html, pdf_path, status,
                    autentique_document_id, autentique_token,
                    criado_por_usuario_id, data_criacao, data_vencimento, observacoes
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, NULL, 'rascunho',
                    NULL, NULL,
                    ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $contrato['colaborador_id'],
                $contrato['template_id'],
                'Cópia - ' . $contrato['titulo'],
                $contrato['descricao_funcao'],
                $contrato['conteudo_final_html'],
                $usuario['id'],
                $contrato['data_criacao'],
                $contrato['data_vencimento'],
                $contrato['observacoes'],
            ]);
            $novo_id = $pdo->lastInsertId();

            // Copia signatários zerando dados de assinatura e Autentique
            $stmt_insert = $pdo->prepare("
                INSERT INTO contratos_signatarios
                    (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($signatarios as $s) {
                $stmt_insert->execute([
                    $novo_id,
                    $s['tipo'],
                    $s['nome'],
                    $s['email'],
                    $s['cpf'],
                    $s['ordem_assinatura'],
                ]);
            }

            $pdo->commit();

            redirect(
                'contrato_enviar.php?id=' . $novo_id,
                'Contrato duplicado! Revise os dados e envie para assinatura.',
                'success'
            );
        } catch (Exception $e) {
            $pdo->rollBack();
            redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao duplicar: ' . $e->getMessage(), 'error');
        }

    } elseif ($acao === 'cancelar' && $contrato['status'] !== 'assinado') {
        try {
            // Cancela no Autentique se tiver document_id
            if ($contrato['autentique_document_id']) {
                $service = new AutentiqueService();
                $service->cancelarDocumento($contrato['autentique_document_id']);
            }
            
            $stmt = $pdo->prepare("UPDATE contratos SET status = 'cancelado' WHERE id = ?");
            $stmt->execute([$contrato_id]);
            
            redirect('contrato_view.php?id=' . $contrato_id, 'Contrato cancelado com sucesso!', 'success');
        } catch (Exception $e) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao cancelar: ' . $e->getMessage(), 'error');
        }
    } elseif ($acao === 'reenviar_link') {
        $signer_id = intval($_POST['signer_id'] ?? 0);
        if ($signer_id <= 0) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Signatário inválido.', 'error');
        } elseif (!$contrato['autentique_document_id']) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Este contrato não está vinculado ao Autentique.', 'error');
        } else {
            try {
                $service = new AutentiqueService();
                $signatario = null;
                foreach ($signatarios as $s) {
                    if ($s['id'] == $signer_id) {
                        $signatario = $s;
                        break;
                    }
                }
                
                if (!$signatario) {
                    redirect('contrato_view.php?id=' . $contrato_id, 'Signatário não encontrado.', 'error');
                } elseif (!$signatario['autentique_signer_id']) {
                    redirect('contrato_view.php?id=' . $contrato_id, 'Este signatário não possui ID no Autentique. Sincronize o contrato primeiro.', 'warning');
                } else {
                    $service->reenviarAssinatura($contrato['autentique_document_id'], $signatario['autentique_signer_id']);
                    redirect('contrato_view.php?id=' . $contrato_id, 'Link reenviado com sucesso para ' . htmlspecialchars($signatario['email']) . '!', 'success');
                }
            } catch (Exception $e) {
                redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao reenviar: ' . $e->getMessage(), 'error');
            }
        }
    } elseif ($acao === 'adicionar_testemunha') {
        // Adiciona nova testemunha ao contrato já enviado ao Autentique
        $nome  = trim($_POST['novo_nome'] ?? '');
        $email = trim($_POST['novo_email'] ?? '');

        if (!$nome || !$email) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Nome e e-mail são obrigatórios.', 'error');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('contrato_view.php?id=' . $contrato_id, 'E-mail inválido.', 'error');
        } elseif (!$contrato['autentique_document_id']) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Este contrato não está vinculado ao Autentique.', 'error');
        } else {
            try {
                $service = new AutentiqueService();
                $resultado = $service->adicionarSignatario($contrato['autentique_document_id'], ['email' => $email]);

                if (!$resultado || empty($resultado['public_id'])) {
                    throw new Exception('O Autentique não retornou confirmação do signatário.');
                }

                $ordem_max = 0;
                foreach ($signatarios as $s) {
                    if ($s['ordem_assinatura'] > $ordem_max) $ordem_max = $s['ordem_assinatura'];
                }

                $stmt = $pdo->prepare("
                    INSERT INTO contratos_signatarios
                        (contrato_id, tipo, nome, email, autentique_signer_id, link_publico, assinado, ordem_assinatura)
                    VALUES (?, 'testemunha', ?, ?, ?, ?, 0, ?)
                ");
                $stmt->execute([
                    $contrato_id,
                    $nome,
                    $email,
                    $resultado['public_id'],
                    $resultado['link']['short_link'] ?? null,
                    $ordem_max + 1
                ]);

                // Garante status correto
                if ($contrato['status'] === 'rascunho') {
                    $pdo->prepare("UPDATE contratos SET status = 'enviado' WHERE id = ?")->execute([$contrato_id]);
                }

                redirect('contrato_view.php?id=' . $contrato_id, 'Testemunha ' . htmlspecialchars($nome) . ' adicionada com sucesso!', 'success');
            } catch (Exception $e) {
                redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao adicionar testemunha: ' . $e->getMessage(), 'error');
            }
        }

    } elseif ($acao === 'remover_testemunha') {
        // Remove testemunha não-assinada do contrato
        $signer_id = intval($_POST['signer_id'] ?? 0);

        if ($signer_id <= 0) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Signatário inválido.', 'error');
        } elseif (!$contrato['autentique_document_id']) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Este contrato não está vinculado ao Autentique.', 'error');
        } else {
            $signatario = null;
            foreach ($signatarios as $s) {
                if ($s['id'] == $signer_id) { $signatario = $s; break; }
            }

            if (!$signatario) {
                redirect('contrato_view.php?id=' . $contrato_id, 'Signatário não encontrado.', 'error');
            } elseif ($signatario['assinado']) {
                redirect('contrato_view.php?id=' . $contrato_id, 'Não é possível remover um signatário que já assinou.', 'error');
            } elseif ($signatario['tipo'] !== 'testemunha') {
                redirect('contrato_view.php?id=' . $contrato_id, 'Apenas testemunhas podem ser removidas.', 'error');
            } else {
                try {
                    $service = new AutentiqueService();

                    if ($signatario['autentique_signer_id']) {
                        $service->removerSignatario($contrato['autentique_document_id'], $signatario['autentique_signer_id']);
                    }

                    $pdo->prepare("DELETE FROM contratos_signatarios WHERE id = ?")->execute([$signer_id]);

                    redirect('contrato_view.php?id=' . $contrato_id, 'Testemunha ' . htmlspecialchars($signatario['nome']) . ' removida com sucesso.', 'success');
                } catch (Exception $e) {
                    redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao remover testemunha: ' . $e->getMessage(), 'error');
                }
            }
        }

    } elseif ($acao === 'substituir_testemunha') {
        // Substitui testemunha não-assinada por nova pessoa
        $signer_id  = intval($_POST['signer_id'] ?? 0);
        $novo_nome  = trim($_POST['novo_nome'] ?? '');
        $novo_email = trim($_POST['novo_email'] ?? '');

        if ($signer_id <= 0 || !$novo_nome || !$novo_email) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Preencha todos os campos.', 'error');
        } elseif (!filter_var($novo_email, FILTER_VALIDATE_EMAIL)) {
            redirect('contrato_view.php?id=' . $contrato_id, 'E-mail inválido.', 'error');
        } elseif (!$contrato['autentique_document_id']) {
            redirect('contrato_view.php?id=' . $contrato_id, 'Este contrato não está vinculado ao Autentique.', 'error');
        } else {
            $signatario = null;
            foreach ($signatarios as $s) {
                if ($s['id'] == $signer_id) { $signatario = $s; break; }
            }

            if (!$signatario) {
                redirect('contrato_view.php?id=' . $contrato_id, 'Signatário não encontrado.', 'error');
            } elseif ($signatario['assinado']) {
                redirect('contrato_view.php?id=' . $contrato_id, 'Não é possível substituir um signatário que já assinou.', 'error');
            } elseif ($signatario['tipo'] !== 'testemunha') {
                redirect('contrato_view.php?id=' . $contrato_id, 'Apenas testemunhas podem ser substituídas.', 'error');
            } else {
                try {
                    $service = new AutentiqueService();

                    // 1. Remove o atual do Autentique (se tiver ID)
                    if ($signatario['autentique_signer_id']) {
                        $service->removerSignatario($contrato['autentique_document_id'], $signatario['autentique_signer_id']);
                    }

                    // 2. Adiciona o novo no Autentique
                    $resultado = $service->adicionarSignatario($contrato['autentique_document_id'], ['email' => $novo_email]);

                    if (!$resultado || empty($resultado['public_id'])) {
                        throw new Exception('O Autentique não retornou confirmação do novo signatário.');
                    }

                    // 3. Marca o antigo como substituído e insere o novo
                    $pdo->prepare("
                        UPDATE contratos_signatarios
                        SET substituido_em = NOW(), substituido_por = NULL
                        WHERE id = ?
                    ")->execute([$signer_id]);

                    $stmt = $pdo->prepare("
                        INSERT INTO contratos_signatarios
                            (contrato_id, tipo, nome, email, autentique_signer_id, link_publico, assinado, ordem_assinatura, substituido_por)
                        VALUES (?, 'testemunha', ?, ?, ?, ?, 0, ?, ?)
                    ");
                    $stmt->execute([
                        $contrato_id,
                        $novo_nome,
                        $novo_email,
                        $resultado['public_id'],
                        $resultado['link']['short_link'] ?? null,
                        $signatario['ordem_assinatura'],
                        $signer_id
                    ]);
                    $novo_id = $pdo->lastInsertId();

                    // Atualiza o antigo apontando para o novo
                    $pdo->prepare("UPDATE contratos_signatarios SET substituido_por = ? WHERE id = ?")->execute([$novo_id, $signer_id]);

                    // Remove o antigo da lista ativa (opcional: pode manter para histórico)
                    $pdo->prepare("DELETE FROM contratos_signatarios WHERE id = ?")->execute([$signer_id]);

                    redirect('contrato_view.php?id=' . $contrato_id, 'Testemunha substituída! ' . htmlspecialchars($signatario['nome']) . ' → ' . htmlspecialchars($novo_nome) . '.', 'success');
                } catch (Exception $e) {
                    redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao substituir testemunha: ' . $e->getMessage(), 'error');
                }
            }
        }

    } elseif ($acao === 'sincronizar') {
        // Sincroniza status consultando a API do Autentique
        log_contrato("=== SINCRONIZAÇÃO MANUAL INICIADA ===");
        log_contrato("Contrato ID: $contrato_id");
        log_contrato("autentique_document_id: " . ($contrato['autentique_document_id'] ?? 'VAZIO'));
        
        if ($contrato['autentique_document_id']) {
            try {
                log_contrato("Criando AutentiqueService...");
                $service = new AutentiqueService();
                log_contrato("AutentiqueService criado com sucesso");
                log_contrato("Document ID Autentique: " . $contrato['autentique_document_id']);
                
                $status_autentique = $service->consultarStatus($contrato['autentique_document_id']);
                log_contrato("Resposta Autentique: " . json_encode($status_autentique, JSON_UNESCAPED_UNICODE));
                
                if ($status_autentique) {
                    $signers_api = $status_autentique['signers'] ?? [];
                    $todos_assinados = true;
                    $algum_assinado = false;
                    $atualizacoes = [];
                    
                    // Busca signatários locais
                    $stmt_local = $pdo->prepare("SELECT * FROM contratos_signatarios WHERE contrato_id = ? ORDER BY ordem_assinatura");
                    $stmt_local->execute([$contrato_id]);
                    $signatarios_locais = $stmt_local->fetchAll();
                    
                    log_contrato("Signatários da API: " . count($signers_api));
                    log_contrato("Signatários locais: " . count($signatarios_locais));
                    
                    foreach ($signers_api as $signer) {
                        $signer_id = $signer['id'] ?? null;
                        $signer_email = $signer['email'] ?? null;
                        $signer_signed = $signer['signed'] ?? false;
                        $signer_signed_at = $signer['signedAt'] ?? null;
                        $signer_link = $signer['link'] ?? null;
                        
                        log_contrato("API Signer: ID=$signer_id Email=$signer_email Signed=" . ($signer_signed ? 'SIM' : 'NAO'));
                        
                        // Verifica se este email existe localmente (ignora signatários extras como o dono da conta)
                        $stmt_check = $pdo->prepare("SELECT id, email, assinado FROM contratos_signatarios WHERE contrato_id = ? AND LOWER(email) = LOWER(?)");
                        $stmt_check->execute([$contrato_id, $signer_email]);
                        $local_match = $stmt_check->fetch();
                        
                        if (!$local_match) {
                            log_contrato("  -> Email $signer_email NÃO existe localmente (provavelmente dono da conta) - IGNORANDO");
                            continue; // Pula este signer - não é um signatário nosso
                        }
                        
                        if (!$signer_signed) {
                            $todos_assinados = false;
                        } else {
                            $algum_assinado = true;
                        }
                        
                        // Match por EMAIL (mais confiável que signer_id devido a bug de offset)
                        $stmt = $pdo->prepare("
                            UPDATE contratos_signatarios 
                            SET assinado = ?, 
                                data_assinatura = ?, 
                                autentique_signer_id = ?,
                                link_publico = COALESCE(?, link_publico)
                            WHERE LOWER(email) = LOWER(?) AND contrato_id = ?
                        ");
                        $stmt->execute([
                            $signer_signed ? 1 : 0,
                            $signer_signed_at,
                            $signer_id,
                            $signer_link,
                            $signer_email,
                            $contrato_id
                        ]);
                        $updated = $stmt->rowCount() > 0;
                        
                        // rowCount pode retornar 0 se dados não mudaram - verifica se o registro existe
                        if (!$updated && $local_match) {
                            $updated = true; // O registro existe, só não mudou
                            log_contrato("  -> Registro existe mas dados não mudaram");
                        }
                        
                        $atualizacoes[] = [
                            'email' => $signer_email,
                            'signed' => $signer_signed,
                            'matched' => $updated
                        ];
                        
                        log_contrato("  -> Match: " . ($updated ? 'SIM' : 'NÃO') . " | Assinado localmente: " . ($local_match['assinado'] ? 'SIM' : 'NAO') . " -> " . ($signer_signed ? 'SIM' : 'NAO'));
                    }
                    
                    // Recheck: verifica todos_assinados apenas com signatários locais
                    $stmt_recheck = $pdo->prepare("SELECT COUNT(*) as total, SUM(assinado) as assinados FROM contratos_signatarios WHERE contrato_id = ?");
                    $stmt_recheck->execute([$contrato_id]);
                    $check = $stmt_recheck->fetch();
                    log_contrato("Check final local: {$check['assinados']}/{$check['total']} assinados");
                    
                    $todos_assinados = ($check['total'] > 0 && $check['assinados'] == $check['total']);
                    $algum_assinado = ($check['assinados'] > 0);
                    
                    // Atualiza status do contrato
                    if ($todos_assinados && count($signers_api) > 0) {
                        $novo_status = 'assinado';
                    } elseif ($algum_assinado) {
                        $novo_status = 'aguardando';
                    } else {
                        $novo_status = $contrato['status']; // Mantém
                    }
                    
                    if ($novo_status !== $contrato['status']) {
                        $stmt = $pdo->prepare("UPDATE contratos SET status = ? WHERE id = ?");
                        $stmt->execute([$novo_status, $contrato_id]);
                        log_contrato("Status atualizado: {$contrato['status']} -> $novo_status");
                    }
                    
                    // Monta mensagem de feedback usando dados locais (mais preciso)
                    $assinados_local = (int)($check['assinados'] ?? 0);
                    $total_local = (int)($check['total'] ?? 0);
                    $msg = "Sincronizado! $assinados_local/$total_local signatário(s) assinaram.";
                    
                    if ($novo_status !== $contrato['status']) {
                        $msg .= " Status: " . ucfirst($novo_status) . ".";
                    }
                    
                    log_contrato("=== FIM SINCRONIZAÇÃO ===");
                    redirect('contrato_view.php?id=' . $contrato_id, $msg, 'success');
                } else {
                    redirect('contrato_view.php?id=' . $contrato_id, 'Não foi possível obter dados do Autentique.', 'warning');
                }
            } catch (Exception $e) {
                log_contrato("ERRO sincronização (Exception): " . $e->getMessage());
                log_contrato("Arquivo: " . $e->getFile() . ":" . $e->getLine());
                redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao sincronizar: ' . $e->getMessage(), 'error');
            } catch (Error $e) {
                log_contrato("ERRO FATAL sincronização (Error): " . $e->getMessage());
                log_contrato("Arquivo: " . $e->getFile() . ":" . $e->getLine());
                redirect('contrato_view.php?id=' . $contrato_id, 'Erro fatal ao sincronizar: ' . $e->getMessage(), 'error');
            }
        } else {
            redirect('contrato_view.php?id=' . $contrato_id, 'Este contrato não possui ID do Autentique.', 'warning');
        }
    }
}

// Recarrega dados após ações
$stmt = $pdo->prepare("
    SELECT c.*, 
           col.nome_completo as colaborador_nome,
           col.cpf as colaborador_cpf,
           col.email_pessoal as colaborador_email,
           u.nome as criado_por_nome,
           t.nome as template_nome
    FROM contratos c
    INNER JOIN colaboradores col ON c.colaborador_id = col.id
    LEFT JOIN usuarios u ON c.criado_por_usuario_id = u.id
    LEFT JOIN contratos_templates t ON c.template_id = t.id
    WHERE c.id = ?
");
$stmt->execute([$contrato_id]);
$contrato = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT * FROM contratos_signatarios 
    WHERE contrato_id = ? 
    ORDER BY ordem_assinatura ASC
");
$stmt->execute([$contrato_id]);
$signatarios = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0"><?= htmlspecialchars($contrato['titulo']) ?></h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">Contratos</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Visualizar</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <?php if ($contrato['autentique_document_id']): ?>
            <form method="POST" style="display: inline;" id="form_sincronizar">
                <input type="hidden" name="acao" value="sincronizar">
                <button type="submit" class="btn btn-primary" id="btn_sincronizar">
                    <i class="ki-duotone ki-arrows-circle fs-4 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="indicator-label">Sincronizar com Autentique</span>
                    <span class="indicator-progress">Sincronizando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </form>
            <?php endif; ?>
            <?php if ($contrato['status'] === 'rascunho'): ?>
            <a href="contrato_enviar.php?id=<?= $contrato_id ?>" class="btn btn-light-success">
                <i class="ki-duotone ki-send fs-4 me-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Enviar para Assinatura
            </a>
            <?php endif; ?>
            <form method="POST" style="display: inline;" id="form_duplicar">
                <input type="hidden" name="acao" value="duplicar">
                <button type="button" class="btn btn-light-success" id="btn_duplicar">
                    <i class="ki-duotone ki-copy fs-4 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Duplicar
                </button>
            </form>
            <?php if ($contrato['status'] !== 'assinado' && $contrato['status'] !== 'cancelado'): ?>
            <form method="POST" style="display: inline;" id="form_cancelar">
                <input type="hidden" name="acao" value="cancelar">
                <button type="button" class="btn btn-light-danger" id="btn_cancelar">
                    <i class="ki-duotone ki-cross-circle fs-4 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Cancelar Contrato
                </button>
            </form>
            <?php endif; ?>
            <a href="contratos.php" class="btn btn-light">Voltar</a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="row">
            <!--begin::Col - Informações-->
            <div class="col-lg-8">
                <!--begin::Card - Contrato-->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Conteúdo do Contrato</span>
                            <span class="text-muted fw-semibold fs-7">
                                Colaborador: <?= htmlspecialchars($contrato['colaborador_nome']) ?>
                            </span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php if ($contrato['descricao_funcao']): ?>
                        <div class="mb-10">
                            <h4 class="text-gray-800 fw-bold mb-3">Descrição da Função</h4>
                            <div class="text-gray-700 fs-6">
                                <?= nl2br(htmlspecialchars($contrato['descricao_funcao'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="fs-6 text-gray-700">
                            <?= $contrato['conteudo_final_html'] ?>
                        </div>
                        
                        <?php if ($contrato['pdf_path']): ?>
                        <div class="mt-10">
                            <a href="../<?= htmlspecialchars($contrato['pdf_path']) ?>" target="_blank" class="btn btn-light-primary">
                                <i class="ki-duotone ki-file-down fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Baixar PDF
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Status e Signatários-->
            <div class="col-lg-4">
                <!--begin::Card - Status-->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Status</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php
                        $status_class = [
                            'rascunho' => 'warning',
                            'enviado' => 'info',
                            'aguardando' => 'warning',
                            'assinado' => 'success',
                            'cancelado' => 'danger',
                            'expirado' => 'secondary'
                        ];
                        $class = $status_class[$contrato['status']] ?? 'secondary';
                        ?>
                        <div class="mb-5">
                            <span class="badge badge-light-<?= $class ?> fs-4 px-4 py-3">
                                <?= ucfirst($contrato['status']) ?>
                            </span>
                        </div>
                        
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <span class="text-muted fs-7">Criado em:</span>
                                <div class="fw-bold"><?= date('d/m/Y H:i', strtotime($contrato['created_at'])) ?></div>
                            </div>
                            <?php if ($contrato['data_criacao']): ?>
                            <div>
                                <span class="text-muted fs-7">Data do Contrato:</span>
                                <div class="fw-bold"><?= formatar_data($contrato['data_criacao']) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($contrato['data_vencimento']): ?>
                            <div>
                                <span class="text-muted fs-7">Vencimento:</span>
                                <div class="fw-bold"><?= formatar_data($contrato['data_vencimento']) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($contrato['autentique_document_id']): ?>
                            <div>
                                <span class="text-muted fs-7">ID Autentique:</span>
                                <div class="fw-bold"><code><?= htmlspecialchars($contrato['autentique_document_id']) ?></code></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
                
                <!--begin::Card - Signatários-->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Signatários</span>
                            <span class="text-muted fw-semibold fs-7"><?= count($signatarios) ?> pessoa(s)</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php if (empty($signatarios)): ?>
                        <p class="text-muted">Nenhum signatário cadastrado</p>
                        <?php else: ?>
                        <?php foreach ($signatarios as $signatario): ?>
                        <div class="d-flex align-items-start justify-content-between mb-5 pb-5 border-bottom">
                            <div class="flex-grow-1">
                                <div class="fw-bold text-gray-900"><?= htmlspecialchars($signatario['nome']) ?></div>
                                <div class="text-muted fs-7"><?= htmlspecialchars($signatario['email']) ?></div>
                                <?php if ($signatario['cpf']): ?>
                                <div class="text-muted fs-7">CPF: <?= htmlspecialchars($signatario['cpf']) ?></div>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <?php
                                    $tipo_classes = [
                                        'colaborador' => 'primary',
                                        'representante' => 'success',
                                        'testemunha' => 'info',
                                        'rh' => 'warning'
                                    ];
                                    $tipo_labels = [
                                        'colaborador' => 'Colaborador',
                                        'representante' => 'Representante',
                                        'testemunha' => 'Testemunha',
                                        'rh' => 'RH'
                                    ];
                                    $tipo_class = $tipo_classes[$signatario['tipo']] ?? 'secondary';
                                    $tipo_label = $tipo_labels[$signatario['tipo']] ?? ucfirst($signatario['tipo']);
                                    ?>
                                    <span class="badge badge-light-<?= $tipo_class ?>">
                                        <?= $tipo_label ?>
                                    </span>
                                    <?php if ($signatario['assinado']): ?>
                                    <span class="badge badge-light-success ms-2">
                                        <i class="ki-duotone ki-check fs-6">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Assinado
                                    </span>
                                    <?php if ($signatario['data_assinatura']): ?>
                                    <div class="text-muted fs-7 mt-1">
                                        <?= date('d/m/Y H:i', strtotime($signatario['data_assinatura'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="badge badge-light-warning ms-2">Pendente</span>
                                    <?php endif; ?>

                                    <?php if (!empty($signatario['falha_envio'])): ?>
                                    <span class="badge badge-light-danger ms-2" 
                                          title="<?= htmlspecialchars($signatario['motivo_falha'] ?? 'Falha ao entregar e-mail') ?>">
                                        <i class="ki-duotone ki-information fs-6">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Falha no envio
                                    </span>
                                    <?php if ($signatario['motivo_falha']): ?>
                                    <div class="alert alert-danger py-2 px-3 mt-2 fs-8">
                                        <i class="ki-duotone ki-warning fs-6 me-1 text-danger">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?= htmlspecialchars($signatario['motivo_falha']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ms-3 d-flex flex-column gap-1">
                                <?php if (!$signatario['assinado'] && $contrato['autentique_document_id']): ?>
                                <form method="POST" style="display: inline;" class="form-reenviar-link">
                                    <input type="hidden" name="acao" value="reenviar_link">
                                    <input type="hidden" name="signer_id" value="<?= $signatario['id'] ?>">
                                    <button type="button" class="btn btn-sm btn-light-primary btn-reenviar-link w-100" title="Reenviar link de assinatura por e-mail">
                                        <i class="ki-duotone ki-send fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Reenviar
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($signatario['link_publico'] && in_array($signatario['tipo'], ['testemunha', 'representante'])): ?>
                                <button type="button" class="btn btn-sm btn-light-info btn-copiar-link w-100"
                                        data-link="<?= htmlspecialchars($signatario['link_publico']) ?>"
                                        title="Copiar link de assinatura">
                                    <i class="ki-duotone ki-copy fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Copiar link
                                </button>
                                <?php endif; ?>

                                <?php if (!$signatario['assinado'] && $signatario['tipo'] === 'testemunha' && $contrato['autentique_document_id'] && $contrato['status'] !== 'cancelado'): ?>
                                <button type="button" class="btn btn-sm btn-light-warning btn-substituir-testemunha w-100"
                                        data-signer-id="<?= $signatario['id'] ?>"
                                        data-nome="<?= htmlspecialchars($signatario['nome']) ?>"
                                        data-email="<?= htmlspecialchars($signatario['email']) ?>"
                                        title="Substituir por outra pessoa">
                                    <i class="ki-duotone ki-arrows-loop fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Substituir
                                </button>
                                <form method="POST" class="form-remover-testemunha">
                                    <input type="hidden" name="acao" value="remover_testemunha">
                                    <input type="hidden" name="signer_id" value="<?= $signatario['id'] ?>">
                                    <button type="button" class="btn btn-sm btn-light-danger btn-remover-testemunha w-100"
                                            data-nome="<?= htmlspecialchars($signatario['nome']) ?>">
                                        <i class="ki-duotone ki-trash fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                        Remover
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($contrato['autentique_document_id'] && $contrato['status'] !== 'cancelado' && $contrato['status'] !== 'assinado'): ?>
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-light-info w-100" id="btn-adicionar-testemunha">
                                <i class="ki-duotone ki-plus fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Testemunha
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->

                <!-- Modal: Substituir Testemunha -->
                <div class="modal fade" id="modal_substituir_testemunha" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" id="form_substituir_testemunha">
                                <input type="hidden" name="acao" value="substituir_testemunha">
                                <input type="hidden" name="signer_id" id="substituir_signer_id">
                                <div class="modal-header">
                                    <h5 class="modal-title">Substituir Testemunha</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-warning mb-4">
                                        <i class="ki-duotone ki-information fs-4 me-2">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                        Substituindo: <strong id="substituir_nome_atual"></strong><br>
                                        <small class="text-muted">O link atual será invalidado e um novo e-mail será enviado.</small>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label required">Nome da nova testemunha</label>
                                        <input type="text" name="novo_nome" class="form-control" placeholder="Nome completo" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label required">E-mail da nova testemunha</label>
                                        <input type="email" name="novo_email" class="form-control" placeholder="email@exemplo.com" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="ki-duotone ki-arrows-loop fs-4 me-1">
                                            <span class="path1"></span><span class="path2"></span>
                                        </i>
                                        Confirmar Substituição
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal: Adicionar Testemunha -->
                <div class="modal fade" id="modal_adicionar_testemunha" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" id="form_adicionar_testemunha">
                                <input type="hidden" name="acao" value="adicionar_testemunha">
                                <div class="modal-header">
                                    <h5 class="modal-title">Adicionar Testemunha</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-4">
                                        <label class="form-label required">Nome</label>
                                        <input type="text" name="novo_nome" class="form-control" placeholder="Nome completo" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label required">E-mail</label>
                                        <input type="email" name="novo_email" class="form-control" placeholder="email@exemplo.com" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-info">
                                        <i class="ki-duotone ki-plus fs-4 me-1">
                                            <span class="path1"></span><span class="path2"></span>
                                        </i>
                                        Adicionar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        
    </div>
</div>
<!--end::Post-->

<script>
// Copiar link público
document.querySelectorAll('.btn-copiar-link').forEach(btn => {
    btn.addEventListener('click', function() {
        const link = this.getAttribute('data-link');
        navigator.clipboard.writeText(link).then(() => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Link copiado!',
                    showConfirmButton: false,
                    timer: 1500
                });
            } else {
                alert('Link copiado para a área de transferência!');
            }
        });
    });
});

// Botão sincronizar - loading
document.getElementById('form_sincronizar')?.addEventListener('submit', function() {
    const btn = document.getElementById('btn_sincronizar');
    if (btn) {
        btn.setAttribute('data-kt-indicator', 'on');
        btn.disabled = true;
    }
});

// Botão reenviar link - confirmação com SweetAlert
document.querySelectorAll('.btn-reenviar-link').forEach(btn => {
    btn.addEventListener('click', function() {
        const form = this.closest('form');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Reenviar link de assinatura?',
                text: 'Um novo e-mail com o link de assinatura será enviado para este signatário.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#009ef7',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, reenviar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    form.submit();
                }
            });
        } else {
            if (confirm('Reenviar link de assinatura para este signatário?')) {
                btn.disabled = true;
                form.submit();
            }
        }
    });
});

// Botão duplicar - confirmação com SweetAlert
document.getElementById('btn_duplicar')?.addEventListener('click', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Duplicar Contrato?',
            html: 'Será criada uma cópia como <strong>rascunho</strong> com os mesmos signatários.<br><small class="text-muted">Você poderá revisar antes de enviar para assinatura.</small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#50cd89',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, duplicar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const btn = document.getElementById('btn_duplicar');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Duplicando...';
                document.getElementById('form_duplicar').submit();
            }
        });
    } else {
        if (confirm('Duplicar este contrato?')) {
            document.getElementById('form_duplicar').submit();
        }
    }
});

// Botão cancelar - confirmação com SweetAlert
document.getElementById('btn_cancelar')?.addEventListener('click', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Cancelar Contrato',
            html: 'Tem certeza que deseja cancelar este contrato?<br><small class="text-muted">Esta ação tentará cancelar também no Autentique.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, cancelar',
            cancelButtonText: 'Não'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('form_cancelar').submit();
            }
        });
    } else {
        if (confirm('Tem certeza que deseja cancelar este contrato?')) {
            document.getElementById('form_cancelar').submit();
        }
    }
});

// Botão Substituir Testemunha - abre modal com dados preenchidos
document.querySelectorAll('.btn-substituir-testemunha').forEach(btn => {
    btn.addEventListener('click', function() {
        const signerId = this.getAttribute('data-signer-id');
        const nome     = this.getAttribute('data-nome');
        document.getElementById('substituir_signer_id').value = signerId;
        document.getElementById('substituir_nome_atual').textContent = nome;
        // Limpa campos do form
        const form = document.getElementById('form_substituir_testemunha');
        form.querySelector('[name="novo_nome"]').value  = '';
        form.querySelector('[name="novo_email"]').value = '';
        const modal = new bootstrap.Modal(document.getElementById('modal_substituir_testemunha'));
        modal.show();
    });
});

// Botão Remover Testemunha - confirmação antes de submeter
document.querySelectorAll('.btn-remover-testemunha').forEach(btn => {
    btn.addEventListener('click', function() {
        const nome = this.getAttribute('data-nome');
        const form = this.closest('form');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Remover Testemunha?',
                html: `<strong>${nome}</strong> será removida do contrato e não poderá mais assinar.<br><small class="text-muted">Esta ação não pode ser desfeita.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.disabled = true;
                    form.submit();
                }
            });
        } else {
            if (confirm('Remover testemunha ' + nome + '?')) {
                form.submit();
            }
        }
    });
});

// Botão Adicionar Testemunha - abre modal
document.getElementById('btn-adicionar-testemunha')?.addEventListener('click', function() {
    const form = document.getElementById('form_adicionar_testemunha');
    form.querySelector('[name="novo_nome"]').value  = '';
    form.querySelector('[name="novo_email"]').value = '';
    const modal = new bootstrap.Modal(document.getElementById('modal_adicionar_testemunha'));
    modal.show();
});

// Loading nos forms de modal ao submeter
['form_substituir_testemunha', 'form_adicionar_testemunha'].forEach(id => {
    document.getElementById(id)?.addEventListener('submit', function() {
        const btn = this.querySelector('[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processando...';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

