<?php
/**
 * Detalhes do Processo de Onboarding
 */

$page_title = 'Detalhes do Onboarding';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('onboarding.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$onboarding_id = (int)($_GET['id'] ?? 0);

if (!$onboarding_id) {
    redirect('onboarding.php', 'Onboarding não encontrado', 'error');
}

// Busca onboarding
$stmt = $pdo->prepare("
    SELECT o.*,
           c.nome_completo as candidato_nome,
           c.email as candidato_email,
           col.nome_completo as colaborador_nome,
           v.titulo as vaga_titulo,
           u.nome as responsavel_nome,
           m.nome_completo as mentor_nome
    FROM onboarding o
    INNER JOIN candidaturas cand ON o.candidatura_id = cand.id
    INNER JOIN candidatos c ON cand.candidato_id = c.id
    INNER JOIN vagas v ON cand.vaga_id = v.id
    LEFT JOIN colaboradores col ON o.colaborador_id = col.id
    LEFT JOIN usuarios u ON o.responsavel_id = u.id
    LEFT JOIN colaboradores m ON o.mentor_id = m.id
    WHERE o.id = ?
");
$stmt->execute([$onboarding_id]);
$onboarding = $stmt->fetch();

if (!$onboarding) {
    redirect('onboarding.php', 'Onboarding não encontrado', 'error');
}

// Busca tarefas
$stmt = $pdo->prepare("
    SELECT t.*, u.nome as responsavel_nome
    FROM onboarding_tarefas t
    LEFT JOIN usuarios u ON t.responsavel_id = u.id
    WHERE t.onboarding_id = ?
    ORDER BY t.etapa, t.ordem ASC
");
$stmt->execute([$onboarding_id]);
$tarefas = $stmt->fetchAll();

// Agrupa tarefas por etapa
$tarefas_por_etapa = [];
foreach ($tarefas as $tarefa) {
    $etapa = $tarefa['etapa'];
    if (!isset($tarefas_por_etapa[$etapa])) {
        $tarefas_por_etapa[$etapa] = [];
    }
    $tarefas_por_etapa[$etapa][] = $tarefa;
}
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card mb-5">
                    <div class="card-header">
                        <h2>Onboarding - <?= htmlspecialchars($onboarding['colaborador_nome'] ?: $onboarding['candidato_nome']) ?></h2>
                        <div class="card-toolbar">
                            <a href="onboarding.php" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Vaga:</strong> <?= htmlspecialchars($onboarding['vaga_titulo']) ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge badge-light-primary"><?= ucfirst($onboarding['status']) ?></span>
                                </p>
                                <p><strong>Responsável:</strong> <?= htmlspecialchars($onboarding['responsavel_nome']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Data Início:</strong> <?= date('d/m/Y', strtotime($onboarding['data_inicio'])) ?></p>
                                <?php if ($onboarding['data_previsao_conclusao']): ?>
                                <p><strong>Previsão:</strong> <?= date('d/m/Y', strtotime($onboarding['data_previsao_conclusao'])) ?></p>
                                <?php endif; ?>
                                <?php if ($onboarding['mentor_nome']): ?>
                                <p><strong>Mentor:</strong> <?= htmlspecialchars($onboarding['mentor_nome']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tarefas por Etapa -->
                <?php foreach ($tarefas_por_etapa as $etapa => $tarefas_etapa): ?>
                <div class="card mb-5">
                    <div class="card-header">
                        <h3><?= ucfirst($etapa) ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tarefa</th>
                                        <th>Tipo</th>
                                        <th>Responsável</th>
                                        <th>Status</th>
                                        <th>Vencimento</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tarefas_etapa as $tarefa): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($tarefa['titulo']) ?></strong>
                                            <?php if ($tarefa['descricao']): ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($tarefa['descricao']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= ucfirst($tarefa['tipo']) ?></td>
                                        <td><?= htmlspecialchars($tarefa['responsavel_nome'] ?? '-') ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pendente' => 'warning',
                                                'em_andamento' => 'info',
                                                'concluida' => 'success',
                                                'cancelada' => 'danger'
                                            ];
                                            $color = $status_colors[$tarefa['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-light-<?= $color ?>"><?= ucfirst($tarefa['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($tarefa['data_vencimento']): ?>
                                            <?= date('d/m/Y', strtotime($tarefa['data_vencimento'])) ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($tarefa['status'] !== 'concluida'): ?>
                                            <button class="btn btn-sm btn-light-success btn-concluir-tarefa" 
                                                    data-tarefa-id="<?= $tarefa['id'] ?>">
                                                Concluir
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-concluir-tarefa').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Deseja marcar esta tarefa como concluída?')) return;
        
        const tarefaId = this.dataset.tarefaId;
        
        try {
            const response = await fetch(`../api/recrutamento/onboarding/concluir_tarefa.php?id=${tarefaId}`, {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Tarefa concluída!');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            alert('Erro ao concluir tarefa');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

