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
    } elseif ($acao === 'atualizar_status') {
        // Atualiza status consultando Autentique
        if ($contrato['autentique_document_id']) {
            try {
                $service = new AutentiqueService();
                $status_autentique = $service->consultarStatus($contrato['autentique_document_id']);
                
                if ($status_autentique) {
                    // Atualiza status do contrato
                    $novo_status = 'aguardando';
                    $todos_assinados = true;
                    
                    foreach ($status_autentique['signers'] ?? [] as $signer) {
                        if (!$signer['signed']) {
                            $todos_assinados = false;
                            break;
                        }
                    }
                    
                    if ($todos_assinados) {
                        $novo_status = 'assinado';
                    }
                    
                    $stmt = $pdo->prepare("UPDATE contratos SET status = ? WHERE id = ?");
                    $stmt->execute([$novo_status, $contrato_id]);
                    
                    // Atualiza signatários
                    foreach ($status_autentique['signers'] ?? [] as $signer) {
                        $stmt = $pdo->prepare("
                            UPDATE contratos_signatarios 
                            SET assinado = ?, data_assinatura = ?
                            WHERE autentique_signer_id = ? AND contrato_id = ?
                        ");
                        $stmt->execute([
                            $signer['signed'] ? 1 : 0,
                            $signer['signedAt'] ?? null,
                            $signer['id'],
                            $contrato_id
                        ]);
                    }
                    
                    redirect('contrato_view.php?id=' . $contrato_id, 'Status atualizado com sucesso!', 'success');
                }
            } catch (Exception $e) {
                redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao atualizar: ' . $e->getMessage(), 'error');
            }
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
            <?php if ($contrato['status'] !== 'assinado' && $contrato['status'] !== 'cancelado'): ?>
            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja cancelar este contrato?');">
                <input type="hidden" name="acao" value="cancelar">
                <button type="submit" class="btn btn-light-danger">Cancelar Contrato</button>
            </form>
            <?php endif; ?>
            <?php if ($contrato['autentique_document_id']): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="acao" value="atualizar_status">
                <button type="submit" class="btn btn-light-primary">Atualizar Status</button>
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
                                    <span class="badge badge-light-<?= $signatario['tipo'] === 'colaborador' ? 'primary' : ($signatario['tipo'] === 'testemunha' ? 'info' : 'success') ?>">
                                        <?= ucfirst($signatario['tipo']) ?>
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
                                <?php if ($signatario['link_publico'] && $signatario['tipo'] === 'testemunha'): ?>
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
            alert('Link copiado para a área de transferência!');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

