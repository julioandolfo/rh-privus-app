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
           COALESCE(c.nome_completo, e.candidato_nome_manual) as candidato_nome,
           COALESCE(c.email, e.candidato_email_manual) as candidato_email,
           COALESCE(c.telefone, e.candidato_telefone_manual) as candidato_telefone,
           col.nome_completo as colaborador_nome,
           COALESCE(v.titulo, ve.titulo) as vaga_titulo,
           u.nome as responsavel_nome,
           m.nome_completo as mentor_nome,
           CASE WHEN o.entrevista_id IS NOT NULL AND o.candidatura_id IS NULL THEN 1 ELSE 0 END as is_entrevista_manual
    FROM onboarding o
    LEFT JOIN candidaturas cand ON o.candidatura_id = cand.id
    LEFT JOIN candidatos c ON cand.candidato_id = c.id
    LEFT JOIN vagas v ON cand.vaga_id = v.id
    LEFT JOIN entrevistas e ON o.entrevista_id = e.id
    LEFT JOIN vagas ve ON e.vaga_id_manual = ve.id
    LEFT JOIN colaboradores col ON o.colaborador_id = col.id
    LEFT JOIN usuarios u ON o.responsavel_id = u.id
    LEFT JOIN colaboradores m ON o.mentor_id = m.id
    WHERE o.id = ?
");
$stmt->execute([$onboarding_id]);
$onboarding = $stmt->fetch();

// DEBUG TEMPORÁRIO - REMOVER DEPOIS
if (!$onboarding) {
    echo "<pre>DEBUG: Onboarding ID = $onboarding_id\n";
    
    // Testa query simples
    $stmt2 = $pdo->prepare("SELECT * FROM onboarding WHERE id = ?");
    $stmt2->execute([$onboarding_id]);
    $test = $stmt2->fetch();
    echo "Query simples: ";
    print_r($test);
    
    // Testa a entrevista
    if ($test && $test['entrevista_id']) {
        $stmt3 = $pdo->prepare("SELECT * FROM entrevistas WHERE id = ?");
        $stmt3->execute([$test['entrevista_id']]);
        $ent = $stmt3->fetch();
        echo "\nEntrevista: ";
        print_r($ent);
    }
    echo "</pre>";
    exit;
    // redirect('onboarding.php', 'Onboarding não encontrado', 'error');
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
                        <h2>
                            Onboarding - <?= htmlspecialchars($onboarding['colaborador_nome'] ?: $onboarding['candidato_nome']) ?>
                            <?php if (!empty($onboarding['is_entrevista_manual'])): ?>
                            <span class="badge badge-light-warning ms-2">Entrevista Manual</span>
                            <?php endif; ?>
                        </h2>
                        <div class="card-toolbar">
                            <a href="kanban_onboarding.php" class="btn btn-light-primary me-2">Kanban</a>
                            <a href="onboarding.php" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Candidato:</strong> <?= htmlspecialchars($onboarding['candidato_nome']) ?></p>
                                <?php if (!empty($onboarding['candidato_email'])): ?>
                                <p><strong>Email:</strong> <?= htmlspecialchars($onboarding['candidato_email']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($onboarding['candidato_telefone'])): ?>
                                <p><strong>Telefone:</strong> <?= htmlspecialchars($onboarding['candidato_telefone']) ?></p>
                                <?php endif; ?>
                                <p><strong>Vaga:</strong> <?= htmlspecialchars($onboarding['vaga_titulo'] ?? 'Não informada') ?></p>
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

