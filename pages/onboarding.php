<?php
/**
 * Lista de Processos de Onboarding
 */

$page_title = 'Onboarding';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('onboarding.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca processos de onboarding
$where = ["1=1"];
$params = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "v.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$sql = "
    SELECT o.*,
           c.nome_completo as candidato_nome,
           col.nome_completo as colaborador_nome,
           v.titulo as vaga_titulo,
           u.nome as responsavel_nome,
           COUNT(DISTINCT t.id) as total_tarefas,
           COUNT(DISTINCT CASE WHEN t.status = 'concluida' THEN t.id END) as tarefas_concluidas
    FROM onboarding o
    INNER JOIN candidaturas cand ON o.candidatura_id = cand.id
    INNER JOIN candidatos c ON cand.candidato_id = c.id
    INNER JOIN vagas v ON cand.vaga_id = v.id
    LEFT JOIN colaboradores col ON o.colaborador_id = col.id
    LEFT JOIN usuarios u ON o.responsavel_id = u.id
    LEFT JOIN onboarding_tarefas t ON o.id = t.onboarding_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY o.id
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$onboardings = $stmt->fetchAll();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Processos de Onboarding</h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="kanban_onboarding.php" class="btn btn-primary">
                                <i class="ki-duotone ki-chart-simple fs-2"></i>
                                Ver Kanban
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Colaborador</th>
                                        <th>Vaga</th>
                                        <th>Status</th>
                                        <th>Progresso</th>
                                        <th>Responsável</th>
                                        <th>Data Início</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($onboardings as $onboarding): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($onboarding['colaborador_nome'] ?: $onboarding['candidato_nome']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($onboarding['vaga_titulo']) ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'contratado' => 'primary',
                                                'documentacao' => 'warning',
                                                'treinamento' => 'info',
                                                'integracao' => 'success',
                                                'acompanhamento' => 'danger',
                                                'concluido' => 'success'
                                            ];
                                            $color = $status_colors[$onboarding['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-light-<?= $color ?>"><?= ucfirst($onboarding['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($onboarding['total_tarefas'] > 0): ?>
                                            <?php $percentual = ($onboarding['tarefas_concluidas'] / $onboarding['total_tarefas']) * 100; ?>
                                            <div class="progress" style="width: 150px;">
                                                <div class="progress-bar" style="width: <?= $percentual ?>%">
                                                    <?= round($percentual) ?>%
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">Sem tarefas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($onboarding['responsavel_nome']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($onboarding['data_inicio'])) ?></td>
                                        <td>
                                            <a href="onboarding_view.php?id=<?= $onboarding['id'] ?>" class="btn btn-sm btn-light-primary">
                                                Ver Detalhes
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

