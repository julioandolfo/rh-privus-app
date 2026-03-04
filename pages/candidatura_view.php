<?php
/**
 * Visualização Detalhada da Candidatura
 */

$page_title = 'Detalhes da Candidatura';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/recrutamento_functions.php';

require_page_permission('candidatura_view.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$candidatura_id = (int)($_GET['id'] ?? 0);

if (!$candidatura_id) {
    redirect('candidaturas.php', 'Candidatura não encontrada', 'error');
}

// Busca candidatura
$stmt = $pdo->prepare("
    SELECT c.*,
           cand.*,
           v.titulo as vaga_titulo,
           v.empresa_id,
           e.nome_fantasia as empresa_nome,
           u.nome as recrutador_nome
    FROM candidaturas c
    INNER JOIN candidatos cand ON c.candidato_id = cand.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN usuarios u ON c.recrutador_responsavel = u.id
    WHERE c.id = ?
");
$stmt->execute([$candidatura_id]);
$candidatura = $stmt->fetch();

if (!$candidatura || !can_access_empresa($candidatura['empresa_id'])) {
    redirect('candidaturas.php', 'Sem permissão', 'error');
}

// Busca etapas
$stmt = $pdo->prepare("
    SELECT ce.*, e.nome as etapa_nome, e.codigo as etapa_codigo,
           u.nome as avaliador_nome
    FROM candidaturas_etapas ce
    INNER JOIN processo_seletivo_etapas e ON ce.etapa_id = e.id
    LEFT JOIN usuarios u ON ce.avaliador_id = u.id
    WHERE ce.candidatura_id = ?
    ORDER BY ce.created_at ASC
");
$stmt->execute([$candidatura_id]);
$etapas = $stmt->fetchAll();

// Busca anexos
$stmt = $pdo->prepare("SELECT * FROM candidaturas_anexos WHERE candidatura_id = ?");
$stmt->execute([$candidatura_id]);
$anexos = $stmt->fetchAll();

// Busca comentários
$stmt = $pdo->prepare("
    SELECT cc.*, u.nome as usuario_nome
    FROM candidaturas_comentarios cc
    LEFT JOIN usuarios u ON cc.usuario_id = u.id
    WHERE cc.candidatura_id = ?
    ORDER BY cc.created_at DESC
");
$stmt->execute([$candidatura_id]);
$comentarios = $stmt->fetchAll();

// Mapa de status para cores e textos
$status_map = [
    'nova'       => ['color' => 'primary',   'label' => 'Nova'],
    'triagem'    => ['color' => 'warning',   'label' => 'Triagem'],
    'entrevista' => ['color' => 'info',      'label' => 'Entrevista'],
    'avaliacao'  => ['color' => 'secondary', 'label' => 'Avaliação'],
    'aprovada'   => ['color' => 'success',   'label' => 'Aprovada'],
    'reprovada'  => ['color' => 'danger',    'label' => 'Reprovada'],
];
$status_info = $status_map[$candidatura['status']] ?? ['color' => 'secondary', 'label' => ucfirst($candidatura['status'])];

// Mapa de status de etapa
$etapa_status_map = [
    'pendente'   => ['color' => 'secondary', 'label' => 'Pendente'],
    'em_andamento' => ['color' => 'warning', 'label' => 'Em Andamento'],
    'aprovado'   => ['color' => 'success',   'label' => 'Aprovado'],
    'reprovado'  => ['color' => 'danger',    'label' => 'Reprovado'],
    'cancelado'  => ['color' => 'dark',      'label' => 'Cancelado'],
];

$coluna_kanban_atual = $candidatura['coluna_kanban'] ?? 'novos_candidatos';
$pode_recusar = has_role(['ADMIN', 'RH', 'GESTOR']) && $coluna_kanban_atual !== 'reprovados';
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">

                <!-- Breadcrumb / Cabeçalho da página -->
                <div class="d-flex align-items-center justify-content-between mb-6">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb text-muted fs-6 fw-semibold mb-1">
                                <li class="breadcrumb-item"><a href="candidaturas.php" class="text-muted text-hover-primary">Candidaturas</a></li>
                                <li class="breadcrumb-item active">Detalhe</li>
                            </ol>
                        </nav>
                        <h1 class="text-gray-800 fw-bold mb-0 fs-2">
                            <?= htmlspecialchars($candidatura['nome_completo']) ?>
                        </h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($pode_recusar): ?>
                        <button type="button" class="btn btn-danger" id="btnRecusar">
                            <i class="ki-duotone ki-cross-circle fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Recusar Candidato
                        </button>
                        <?php endif; ?>
                        <a href="kanban_selecao.php" class="btn btn-light-primary">
                            <i class="ki-duotone ki-chart-simple fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            Ver Kanban
                        </a>
                        <a href="candidaturas.php" class="btn btn-light">
                            <i class="ki-duotone ki-arrow-left fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Voltar
                        </a>
                    </div>
                </div>

                <!-- Alerta de recusado -->
                <?php if ($coluna_kanban_atual === 'reprovados'): ?>
                <div class="alert alert-danger d-flex align-items-center mb-6">
                    <i class="ki-duotone ki-cross-circle fs-2x text-danger me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <h5 class="mb-1">Candidato Recusado</h5>
                        <p class="mb-0">Este candidato foi movido para a etapa <strong>Reprovados</strong> no Kanban.</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row g-5">
                    <!-- Coluna principal -->
                    <div class="col-lg-8">

                        <!-- Card de Informações do Candidato -->
                        <div class="card mb-5">
                            <div class="card-header border-0 pt-5 pb-0">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-60px symbol-circle me-4">
                                        <div class="symbol-label bg-light-primary text-primary fw-bold fs-1">
                                            <?= strtoupper(substr($candidatura['nome_completo'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="text-gray-800 fw-bold mb-1"><?= htmlspecialchars($candidatura['nome_completo']) ?></h3>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <span class="text-muted fs-6">
                                                <i class="ki-duotone ki-sms fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                <?= htmlspecialchars($candidatura['email']) ?>
                                            </span>
                                            <?php if (!empty($candidatura['telefone'])): ?>
                                            <span class="text-muted fs-6">
                                                <i class="ki-duotone ki-phone fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                <?= htmlspecialchars($candidatura['telefone']) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body pt-4">
                                <div class="separator separator-dashed mb-5"></div>
                                <div class="row g-4">
                                    <div class="col-sm-6">
                                        <div class="d-flex flex-column">
                                            <span class="text-muted fs-7 fw-semibold mb-1">Vaga</span>
                                            <span class="text-gray-800 fw-bold fs-6">
                                                <a href="vagas_view.php?id=<?= $candidatura['vaga_id'] ?>" class="text-gray-800 text-hover-primary">
                                                    <?= htmlspecialchars($candidatura['vaga_titulo']) ?>
                                                </a>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex flex-column">
                                            <span class="text-muted fs-7 fw-semibold mb-1">Empresa</span>
                                            <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($candidatura['empresa_nome'] ?? '-') ?></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex flex-column">
                                            <span class="text-muted fs-7 fw-semibold mb-1">Status</span>
                                            <span>
                                                <span class="badge badge-light-<?= $status_info['color'] ?> fs-7 fw-bold">
                                                    <?= $status_info['label'] ?>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex flex-column">
                                            <span class="text-muted fs-7 fw-semibold mb-1">Nota Geral</span>
                                            <span class="text-gray-800 fw-bold fs-6">
                                                <?php if ($candidatura['nota_geral']): ?>
                                                <span class="badge badge-light-info fs-6"><?= $candidatura['nota_geral'] ?>/10</span>
                                                <?php else: ?>
                                                <span class="text-muted">Não avaliado</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex flex-column">
                                            <span class="text-muted fs-7 fw-semibold mb-1">Recrutador Responsável</span>
                                            <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($candidatura['recrutador_nome'] ?? '-') ?></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex flex-column">
                                            <span class="text-muted fs-7 fw-semibold mb-1">Data da Candidatura</span>
                                            <span class="text-gray-800 fw-bold fs-6">
                                                <?= date('d/m/Y \à\s H:i', strtotime($candidatura['data_candidatura'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($anexos)): ?>
                                <div class="separator separator-dashed my-5"></div>
                                <div>
                                    <h5 class="text-gray-700 fw-bold mb-3">
                                        <i class="ki-duotone ki-paper-clip fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                                        Anexos (<?= count($anexos) ?>)
                                    </h5>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($anexos as $anexo): ?>
                                        <?php
                                        $caminho_arquivo = $anexo['caminho_arquivo'];
                                        if (!preg_match('/^https?:\/\//', $caminho_arquivo)) {
                                            $base = rtrim(get_base_url(), '/');
                                            $caminho = '/' . ltrim($caminho_arquivo, '/');
                                            $caminho_arquivo = $base . $caminho;
                                        }
                                        ?>
                                        <a href="<?= htmlspecialchars($caminho_arquivo) ?>" target="_blank" class="btn btn-light-primary btn-sm">
                                            <i class="ki-duotone ki-file fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                            <?= htmlspecialchars($anexo['nome_arquivo']) ?>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card de Etapas -->
                        <?php if (!empty($etapas)): ?>
                        <div class="card mb-5">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold fs-4 text-gray-800">
                                        <i class="ki-duotone ki-abstract-26 fs-4 text-primary me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Progresso das Etapas
                                    </span>
                                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($etapas) ?> etapa(s) registrada(s)</span>
                                </h3>
                            </div>
                            <div class="card-body pt-3">
                                <?php foreach ($etapas as $etapa): ?>
                                <?php $etapa_si = $etapa_status_map[$etapa['status']] ?? ['color' => 'secondary', 'label' => ucfirst($etapa['status'])]; ?>
                                <div class="d-flex align-items-start mb-5">
                                    <div class="d-flex align-items-center justify-content-center w-40px h-40px rounded-circle bg-light-<?= $etapa_si['color'] ?> me-4 flex-shrink-0">
                                        <i class="ki-duotone ki-check fs-3 text-<?= $etapa_si['color'] ?>"><span class="path1"></span><span class="path2"></span></i>
                                    </div>
                                    <div class="flex-grow-1 border rounded p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h5 class="fw-bold mb-0 text-gray-800"><?= htmlspecialchars($etapa['etapa_nome']) ?></h5>
                                            <span class="badge badge-light-<?= $etapa_si['color'] ?>"><?= $etapa_si['label'] ?></span>
                                        </div>
                                        <div class="d-flex gap-4 flex-wrap fs-7 text-muted">
                                            <?php if ($etapa['nota']): ?>
                                            <span><strong class="text-gray-700">Nota:</strong> <?= $etapa['nota'] ?>/10</span>
                                            <?php endif; ?>
                                            <?php if ($etapa['avaliador_nome']): ?>
                                            <span><strong class="text-gray-700">Avaliador:</strong> <?= htmlspecialchars($etapa['avaliador_nome']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($etapa['feedback']): ?>
                                        <p class="mb-0 mt-2 text-gray-600 fs-7"><?= nl2br(htmlspecialchars($etapa['feedback'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Card de Comentários -->
                        <div class="card">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold fs-4 text-gray-800">
                                        <i class="ki-duotone ki-message-text-2 fs-4 text-primary me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Comentários
                                    </span>
                                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($comentarios) ?> comentário(s)</span>
                                </h3>
                            </div>
                            <div class="card-body pt-3">
                                <?php if (empty($comentarios)): ?>
                                <div class="text-center py-8 text-muted">
                                    <i class="ki-duotone ki-message-text-2 fs-3x text-gray-300 mb-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    <p class="mb-0">Nenhum comentário registrado.</p>
                                </div>
                                <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($comentarios as $comentario): ?>
                                    <div class="bg-light rounded p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-30px symbol-circle me-2">
                                                    <div class="symbol-label bg-primary text-white fw-bold fs-7">
                                                        <?= strtoupper(substr($comentario['usuario_nome'] ?? 'S', 0, 1)) ?>
                                                    </div>
                                                </div>
                                                <strong class="text-gray-800 fs-7"><?= htmlspecialchars($comentario['usuario_nome'] ?? 'Sistema') ?></strong>
                                            </div>
                                            <span class="text-muted fs-8"><?= date('d/m/Y H:i', strtotime($comentario['created_at'])) ?></span>
                                        </div>
                                        <p class="mb-0 text-gray-700 fs-7"><?= nl2br(htmlspecialchars($comentario['comentario'])) ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <!-- Coluna lateral -->
                    <div class="col-lg-4">

                        <!-- Card de etapa no Kanban -->
                        <div class="card mb-5">
                            <div class="card-header border-0 pt-5">
                                <h4 class="card-title fw-bold text-gray-800">
                                    <i class="ki-duotone ki-chart-simple fs-4 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                    Etapa no Kanban
                                </h4>
                            </div>
                            <div class="card-body pt-3">
                                <?php
                                // Busca a coluna atual no kanban
                                $stmt_col = $pdo->prepare("SELECT nome, cor, icone FROM kanban_colunas WHERE codigo = ? LIMIT 1");
                                $stmt_col->execute([$coluna_kanban_atual]);
                                $kanban_col = $stmt_col->fetch();
                                if ($kanban_col):
                                ?>
                                <div class="d-flex align-items-center p-3 rounded" style="background-color: <?= htmlspecialchars($kanban_col['cor']) ?>15; border-left: 4px solid <?= htmlspecialchars($kanban_col['cor']) ?>;">
                                    <?php if ($kanban_col['icone']): ?>
                                    <i class="ki-duotone ki-<?= htmlspecialchars($kanban_col['icone']) ?> fs-2 me-3" style="color: <?= htmlspecialchars($kanban_col['cor']) ?>">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php endif; ?>
                                    <div>
                                        <span class="fw-bold fs-6 text-gray-800"><?= htmlspecialchars($kanban_col['nome']) ?></span>
                                        <span class="d-block text-muted fs-7">Etapa atual</span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span class="text-muted fs-7">Não posicionado no Kanban</span>
                                <?php endif; ?>

                                <?php if ($pode_recusar): ?>
                                <div class="mt-4">
                                    <button type="button" class="btn btn-danger w-100" id="btnRecusar2">
                                        <i class="ki-duotone ki-cross-circle fs-4 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Recusar Candidato
                                    </button>
                                    <p class="text-muted fs-8 mt-2 mb-0 text-center">Move para "Reprovados" no Kanban</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Resumo Rápido -->
                        <div class="card">
                            <div class="card-header border-0 pt-5">
                                <h4 class="card-title fw-bold text-gray-800">
                                    <i class="ki-duotone ki-information fs-4 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Resumo
                                </h4>
                            </div>
                            <div class="card-body pt-3">
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <span class="text-muted fs-7">Etapas concluídas</span>
                                    <span class="fw-bold text-gray-800">
                                        <?= count(array_filter($etapas, fn($e) => in_array($e['status'], ['aprovado', 'reprovado']))) ?> / <?= count($etapas) ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <span class="text-muted fs-7">Comentários</span>
                                    <span class="fw-bold text-gray-800"><?= count($comentarios) ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <span class="text-muted fs-7">Anexos</span>
                                    <span class="fw-bold text-gray-800"><?= count($anexos) ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted fs-7">Candidatura enviada</span>
                                    <span class="fw-bold text-gray-800"><?= date('d/m/Y', strtotime($candidatura['data_candidatura'])) ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal de Recusa -->
<?php if ($pode_recusar): ?>
<div class="modal fade" id="modalRecusar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h4 class="modal-title fw-bold text-danger">
                    <i class="ki-duotone ki-cross-circle fs-3 text-danger me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Recusar Candidato
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex align-items-center mb-4">
                    <i class="ki-duotone ki-information-5 fs-2 text-warning me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        Esta ação irá mover <strong><?= htmlspecialchars($candidatura['nome_completo']) ?></strong> para a coluna <strong>Reprovados</strong> no Kanban.
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Motivo da recusa <span class="text-muted fs-8">(opcional)</span></label>
                    <textarea class="form-control" id="motivoRecusa" rows="3" placeholder="Descreva o motivo da recusa..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarRecusa">
                    <span class="indicator-label">Confirmar Recusa</span>
                    <span class="indicator-progress" style="display:none">
                        <span class="spinner-border spinner-border-sm align-middle me-2"></span>Aguarde...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const candidaturaId = <?= $candidatura_id ?>;
    const modalEl = document.getElementById('modalRecusar');
    const modal = new bootstrap.Modal(modalEl);

    // Abre modal pelos dois botões
    ['btnRecusar', 'btnRecusar2'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', () => modal.show());
    });

    // Confirmar recusa
    document.getElementById('btnConfirmarRecusa').addEventListener('click', async function() {
        const btn = this;
        const motivo = document.getElementById('motivoRecusa').value.trim();

        btn.querySelector('.indicator-label').style.display = 'none';
        btn.querySelector('.indicator-progress').style.display = 'inline-block';
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('candidatura_id', candidaturaId);
            formData.append('coluna_codigo', 'reprovados');

            const response = await fetch('../api/recrutamento/kanban/mover.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                // Se tiver motivo, salva como comentário
                if (motivo) {
                    const formComentario = new FormData();
                    formComentario.append('candidatura_id', candidaturaId);
                    formComentario.append('comentario', 'Motivo da recusa: ' + motivo);
                    await fetch('../api/recrutamento/candidaturas/comentar.php', {
                        method: 'POST',
                        body: formComentario
                    }).catch(() => {});
                }
                modal.hide();
                location.reload();
            } else {
                alert('Erro ao recusar: ' + data.message);
                btn.querySelector('.indicator-label').style.display = 'inline-block';
                btn.querySelector('.indicator-progress').style.display = 'none';
                btn.disabled = false;
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao processar a recusa.');
            btn.querySelector('.indicator-label').style.display = 'inline-block';
            btn.querySelector('.indicator-progress').style.display = 'none';
            btn.disabled = false;
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
