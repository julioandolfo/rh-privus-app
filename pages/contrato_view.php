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
    
    if ($acao === 'cancelar' && $contrato['status'] !== 'assinado') {
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
        if ($signer_id > 0 && $contrato['autentique_document_id']) {
            try {
                $service = new AutentiqueService();
                $signatario = null;
                foreach ($signatarios as $s) {
                    if ($s['id'] == $signer_id) {
                        $signatario = $s;
                        break;
                    }
                }
                
                if ($signatario && $signatario['autentique_signer_id']) {
                    $service->reenviarAssinatura($contrato['autentique_document_id'], $signatario['autentique_signer_id']);
                    redirect('contrato_view.php?id=' . $contrato_id, 'Link reenviado com sucesso!', 'success');
                }
            } catch (Exception $e) {
                redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao reenviar: ' . $e->getMessage(), 'error');
            }
        }
    } elseif ($acao === 'sincronizar') {
        // Sincroniza status consultando a API do Autentique
        if ($contrato['autentique_document_id']) {
            try {
                $service = new AutentiqueService();
                log_contrato("=== SINCRONIZAÇÃO MANUAL - Contrato ID: $contrato_id ===");
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
                        
                        if (!$signer_signed) {
                            $todos_assinados = false;
                        } else {
                            $algum_assinado = true;
                        }
                        
                        // Tenta match por autentique_signer_id
                        $updated = false;
                        if ($signer_id) {
                            $stmt = $pdo->prepare("
                                UPDATE contratos_signatarios 
                                SET assinado = ?, data_assinatura = ?, autentique_signer_id = COALESCE(autentique_signer_id, ?), link_publico = COALESCE(link_publico, ?)
                                WHERE autentique_signer_id = ? AND contrato_id = ?
                            ");
                            $stmt->execute([
                                $signer_signed ? 1 : 0,
                                $signer_signed_at,
                                $signer_id,
                                $signer_link,
                                $signer_id,
                                $contrato_id
                            ]);
                            $updated = $stmt->rowCount() > 0;
                        }
                        
                        // Se não encontrou por ID, tenta por email
                        if (!$updated && $signer_email) {
                            $stmt = $pdo->prepare("
                                UPDATE contratos_signatarios 
                                SET assinado = ?, data_assinatura = ?, autentique_signer_id = COALESCE(autentique_signer_id, ?), link_publico = COALESCE(link_publico, ?)
                                WHERE email = ? AND contrato_id = ?
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
                        }
                        
                        $atualizacoes[] = [
                            'email' => $signer_email,
                            'signed' => $signer_signed,
                            'matched' => $updated
                        ];
                        
                        log_contrato("Match local: " . ($updated ? 'SIM' : 'NÃO'));
                    }
                    
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
                    
                    // Monta mensagem de feedback
                    $matched = count(array_filter($atualizacoes, fn($a) => $a['matched']));
                    $signed = count(array_filter($atualizacoes, fn($a) => $a['signed']));
                    $msg = "Sincronizado! $signed/" . count($signers_api) . " assinatura(s). $matched signatário(s) atualizados.";
                    
                    if ($novo_status !== $contrato['status']) {
                        $msg .= " Status: " . ucfirst($novo_status) . ".";
                    }
                    
                    log_contrato("=== FIM SINCRONIZAÇÃO ===");
                    redirect('contrato_view.php?id=' . $contrato_id, $msg, 'success');
                } else {
                    redirect('contrato_view.php?id=' . $contrato_id, 'Não foi possível obter dados do Autentique.', 'warning');
                }
            } catch (Exception $e) {
                log_contrato("ERRO sincronização: " . $e->getMessage());
                redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao sincronizar: ' . $e->getMessage(), 'error');
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
                        <div class="d-flex align-items-center justify-content-between mb-5 pb-5 border-bottom">
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
                                </div>
                            </div>
                            <div class="ms-3">
                                <?php if (!$signatario['assinado'] && $contrato['autentique_document_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="acao" value="reenviar_link">
                                    <input type="hidden" name="signer_id" value="<?= $signatario['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-light-primary" title="Reenviar link">
                                        <i class="ki-duotone ki-send fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($signatario['link_publico'] && in_array($signatario['tipo'], ['testemunha', 'representante'])): ?>
                                <button type="button" class="btn btn-sm btn-light-info btn-copiar-link" 
                                        data-link="<?= htmlspecialchars($signatario['link_publico']) ?>"
                                        title="Copiar link público">
                                    <i class="ki-duotone ki-copy fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

