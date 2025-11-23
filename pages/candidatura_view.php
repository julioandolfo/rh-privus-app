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
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card mb-5">
                    <div class="card-header">
                        <h2><?= htmlspecialchars($candidatura['nome_completo']) ?></h2>
                        <div class="card-toolbar">
                            <a href="candidaturas.php" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?= htmlspecialchars($candidatura['email']) ?></p>
                                <p><strong>Telefone:</strong> <?= htmlspecialchars($candidatura['telefone'] ?? '-') ?></p>
                                <p><strong>Vaga:</strong> <?= htmlspecialchars($candidatura['vaga_titulo']) ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge badge-light-primary"><?= ucfirst($candidatura['status']) ?></span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Nota Geral:</strong> 
                                    <?= $candidatura['nota_geral'] ? $candidatura['nota_geral'] . '/10' : '-' ?>
                                </p>
                                <p><strong>Data da Candidatura:</strong> 
                                    <?= date('d/m/Y H:i', strtotime($candidatura['data_candidatura'])) ?>
                                </p>
                                <p><strong>Recrutador Responsável:</strong> 
                                    <?= htmlspecialchars($candidatura['recrutador_nome'] ?? '-') ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($anexos)): ?>
                        <div class="mt-4">
                            <h4>Anexos</h4>
                            <?php foreach ($anexos as $anexo): ?>
                            <a href="<?= htmlspecialchars($anexo['caminho_arquivo']) ?>" target="_blank" class="btn btn-light-primary me-2">
                                <i class="ki-duotone ki-file fs-2"></i>
                                <?= htmlspecialchars($anexo['nome_arquivo']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Etapas -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3>Progresso das Etapas</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($etapas as $etapa): ?>
                        <div class="border rounded p-3 mb-3">
                            <h5><?= htmlspecialchars($etapa['etapa_nome']) ?></h5>
                            <p><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $etapa['status'])) ?></p>
                            <?php if ($etapa['nota']): ?>
                            <p><strong>Nota:</strong> <?= $etapa['nota'] ?>/10</p>
                            <?php endif; ?>
                            <?php if ($etapa['feedback']): ?>
                            <p><strong>Feedback:</strong> <?= nl2br(htmlspecialchars($etapa['feedback'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Comentários -->
                <div class="card">
                    <div class="card-header">
                        <h3>Comentários</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($comentarios as $comentario): ?>
                        <div class="border rounded p-3 mb-3">
                            <strong><?= htmlspecialchars($comentario['usuario_nome'] ?? 'Sistema') ?></strong>
                            <span class="text-muted"> - <?= date('d/m/Y H:i', strtotime($comentario['created_at'])) ?></span>
                            <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($comentario['comentario'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

