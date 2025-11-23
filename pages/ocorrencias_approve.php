<?php
/**
 * Aprovação/Rejeição de Ocorrências
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

require_page_permission('ocorrencias_list.php');

if (!has_role(['ADMIN', 'RH'])) {
    redirect('ocorrencias_list.php', 'Você não tem permissão para aprovar ocorrências.', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$ocorrencia_id = $_GET['id'] ?? 0;
$acao = $_GET['acao'] ?? '';

if (!$ocorrencia_id) {
    redirect('ocorrencias_list.php', 'Ocorrência não encontrada!', 'error');
}

// Busca ocorrência
$stmt = $pdo->prepare("
    SELECT o.*, c.nome_completo as colaborador_nome, t.nome as tipo_nome
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
    WHERE o.id = ?
");
$stmt->execute([$ocorrencia_id]);
$ocorrencia = $stmt->fetch();

if (!$ocorrencia) {
    redirect('ocorrencias_list.php', 'Ocorrência não encontrada!', 'error');
}

// Processa aprovação/rejeição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao_post = $_POST['acao'] ?? '';
    $observacao = sanitize($_POST['observacao'] ?? '');
    
    if ($acao_post === 'aprovar') {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE ocorrencias SET
                status_aprovacao = 'aprovada',
                aprovado_por = ?,
                data_aprovacao = NOW(),
                observacao_aprovacao = ?
                WHERE id = ?
            ");
            $stmt->execute([$usuario['id'], $observacao, $ocorrencia_id]);
            
            registrar_historico_ocorrencia($ocorrencia_id, 'aprovada', $usuario['id'], 'status_aprovacao', 'pendente', 'aprovada', $observacao);
            
            // Verifica e aplica advertências progressivas
            $tipo_ocorrencia = null;
            if ($ocorrencia['tipo_ocorrencia_id']) {
                $stmt_tipo = $pdo->prepare("SELECT * FROM tipos_ocorrencias WHERE id = ?");
                $stmt_tipo->execute([$ocorrencia['tipo_ocorrencia_id']]);
                $tipo_ocorrencia = $stmt_tipo->fetch();
            }
            
            if ($tipo_ocorrencia && $tipo_ocorrencia['conta_advertencia']) {
                verificar_advertencias_progressivas($ocorrencia['colaborador_id'], $ocorrencia['tipo_ocorrencia_id']);
            }
            
            // Envia notificações
            enviar_notificacoes_ocorrencia($ocorrencia_id);
            
            $pdo->commit();
            
            redirect('ocorrencia_view.php?id=' . $ocorrencia_id, 'Ocorrência aprovada com sucesso!');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            redirect('ocorrencias_list.php', 'Erro ao aprovar ocorrência: ' . $e->getMessage(), 'error');
        }
    } elseif ($acao_post === 'rejeitar') {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE ocorrencias SET
                status_aprovacao = 'rejeitada',
                aprovado_por = ?,
                data_aprovacao = NOW(),
                observacao_aprovacao = ?
                WHERE id = ?
            ");
            $stmt->execute([$usuario['id'], $observacao, $ocorrencia_id]);
            
            registrar_historico_ocorrencia($ocorrencia_id, 'rejeitada', $usuario['id'], 'status_aprovacao', 'pendente', 'rejeitada', $observacao);
            
            // Envia notificações
            enviar_notificacoes_ocorrencia($ocorrencia_id);
            
            $pdo->commit();
            
            redirect('ocorrencia_view.php?id=' . $ocorrencia_id, 'Ocorrência rejeitada.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            redirect('ocorrencias_list.php', 'Erro ao rejeitar ocorrência: ' . $e->getMessage(), 'error');
        }
    }
}

$page_title = 'Aprovar Ocorrência';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">
                <?= $acao === 'aprovar' ? 'Aprovar' : 'Rejeitar' ?> Ocorrência
            </h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item">
                    <a href="ocorrencias_list.php" class="text-muted text-hover-primary">Ocorrências</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Aprovar</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="ocorrencia_view.php?id=<?= $ocorrencia_id ?>" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-arrow-left fs-2"></i>
                Voltar
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informações da Ocorrência</h3>
            </div>
            <div class="card-body">
                <div class="row mb-7">
                    <div class="col-md-6">
                        <label class="fw-semibold fs-6 text-gray-500">Colaborador</label>
                        <div class="fw-bold fs-6"><?= htmlspecialchars($ocorrencia['colaborador_nome']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-semibold fs-6 text-gray-500">Tipo</label>
                        <div class="fw-bold fs-6"><?= htmlspecialchars($ocorrencia['tipo_nome'] ?? $ocorrencia['tipo']) ?></div>
                    </div>
                </div>
                <div class="row mb-7">
                    <div class="col-md-6">
                        <label class="fw-semibold fs-6 text-gray-500">Data</label>
                        <div class="fw-bold fs-6"><?= formatar_data($ocorrencia['data_ocorrencia']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-semibold fs-6 text-gray-500">Severidade</label>
                        <div class="fw-bold fs-6">
                            <?php
                            $severidade = $ocorrencia['severidade'] ?? 'moderada';
                            $severidade_labels = ['leve' => 'Leve', 'moderada' => 'Moderada', 'grave' => 'Grave', 'critica' => 'Crítica'];
                            $severidade_colors = [
                                'leve' => 'badge-light-success',
                                'moderada' => 'badge-light-info',
                                'grave' => 'badge-light-warning',
                                'critica' => 'badge-light-danger'
                            ];
                            ?>
                            <span class="badge <?= $severidade_colors[$severidade] ?? 'badge-light-info' ?>">
                                <?= $severidade_labels[$severidade] ?? 'Moderada' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mb-7">
                    <label class="fw-semibold fs-6 text-gray-500">Descrição</label>
                    <div class="fw-normal fs-6 text-gray-800">
                        <?= nl2br(htmlspecialchars($ocorrencia['descricao'] ?? '')) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-5">
            <div class="card-header">
                <h3 class="card-title">
                    <?= $acao === 'aprovar' ? 'Aprovar' : 'Rejeitar' ?> Ocorrência
                </h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="<?= htmlspecialchars($acao) ?>">
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Observações</label>
                        <textarea name="observacao" class="form-control form-control-solid" rows="5" placeholder="Adicione observações sobre a <?= $acao === 'aprovar' ? 'aprovação' : 'rejeição' ?>..."></textarea>
                    </div>
                    
                    <div class="text-center">
                        <a href="ocorrencia_view.php?id=<?= $ocorrencia_id ?>" class="btn btn-light me-3">Cancelar</a>
                        <button type="submit" class="btn btn-<?= $acao === 'aprovar' ? 'success' : 'danger' ?>">
                            <i class="ki-duotone ki-check fs-2"></i>
                            <?= $acao === 'aprovar' ? 'Aprovar' : 'Rejeitar' ?> Ocorrência
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

