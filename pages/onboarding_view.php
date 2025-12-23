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

// DEBUG - ANTES DA QUERY
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Primeiro testa query simples
$stmt_simple = $pdo->prepare("SELECT * FROM onboarding WHERE id = ?");
$stmt_simple->execute([$onboarding_id]);
$onboarding_simple = $stmt_simple->fetch(PDO::FETCH_ASSOC);

if (!$onboarding_simple) {
    echo "Onboarding não encontrado com query simples. ID: $onboarding_id";
    exit;
}

// Busca dados da entrevista se existir
$entrevista = null;
if (!empty($onboarding_simple['entrevista_id'])) {
    $stmt_ent = $pdo->prepare("SELECT * FROM entrevistas WHERE id = ?");
    $stmt_ent->execute([$onboarding_simple['entrevista_id']]);
    $entrevista = $stmt_ent->fetch(PDO::FETCH_ASSOC);
}

// Busca dados da candidatura se existir
$candidatura = null;
$candidato = null;
$vaga = null;
if (!empty($onboarding_simple['candidatura_id'])) {
    $stmt_cand = $pdo->prepare("
        SELECT cand.*, c.nome_completo, c.email, c.telefone, v.titulo as vaga_titulo
        FROM candidaturas cand
        INNER JOIN candidatos c ON cand.candidato_id = c.id
        INNER JOIN vagas v ON cand.vaga_id = v.id
        WHERE cand.id = ?
    ");
    $stmt_cand->execute([$onboarding_simple['candidatura_id']]);
    $candidatura = $stmt_cand->fetch(PDO::FETCH_ASSOC);
}

// Busca vaga da entrevista manual
$vaga_entrevista = null;
if ($entrevista && !empty($entrevista['vaga_id_manual'])) {
    $stmt_vaga = $pdo->prepare("SELECT titulo FROM vagas WHERE id = ?");
    $stmt_vaga->execute([$entrevista['vaga_id_manual']]);
    $vaga_entrevista = $stmt_vaga->fetch(PDO::FETCH_ASSOC);
}

// Busca responsável
$responsavel = null;
if (!empty($onboarding_simple['responsavel_id'])) {
    $stmt_resp = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt_resp->execute([$onboarding_simple['responsavel_id']]);
    $responsavel = $stmt_resp->fetch(PDO::FETCH_ASSOC);
}

// Busca colaborador
$colaborador = null;
if (!empty($onboarding_simple['colaborador_id'])) {
    $stmt_col = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
    $stmt_col->execute([$onboarding_simple['colaborador_id']]);
    $colaborador = $stmt_col->fetch(PDO::FETCH_ASSOC);
}

// Busca mentor
$mentor = null;
if (!empty($onboarding_simple['mentor_id'])) {
    $stmt_mentor = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
    $stmt_mentor->execute([$onboarding_simple['mentor_id']]);
    $mentor = $stmt_mentor->fetch(PDO::FETCH_ASSOC);
}

// Monta objeto onboarding
$onboarding = $onboarding_simple;
$onboarding['is_entrevista_manual'] = (!empty($onboarding_simple['entrevista_id']) && empty($onboarding_simple['candidatura_id'])) ? 1 : 0;

if ($candidatura) {
    $onboarding['candidato_nome'] = $candidatura['nome_completo'];
    $onboarding['candidato_email'] = $candidatura['email'];
    $onboarding['candidato_telefone'] = $candidatura['telefone'];
    $onboarding['vaga_titulo'] = $candidatura['vaga_titulo'];
} elseif ($entrevista) {
    $onboarding['candidato_nome'] = $entrevista['candidato_nome_manual'];
    $onboarding['candidato_email'] = $entrevista['candidato_email_manual'];
    $onboarding['candidato_telefone'] = $entrevista['candidato_telefone_manual'];
    $onboarding['vaga_titulo'] = $vaga_entrevista['titulo'] ?? null;
} else {
    $onboarding['candidato_nome'] = 'Desconhecido';
    $onboarding['candidato_email'] = null;
    $onboarding['candidato_telefone'] = null;
    $onboarding['vaga_titulo'] = null;
}

$onboarding['colaborador_nome'] = $colaborador['nome_completo'] ?? null;
$onboarding['responsavel_nome'] = $responsavel['nome'] ?? null;
$onboarding['mentor_nome'] = $mentor['nome_completo'] ?? null;

if (!$onboarding) {
    redirect('onboarding.php', 'Onboarding não encontrado', 'error');
}

// Busca tarefas
$stmt = $pdo->prepare("
    SELECT t.*, u.nome as responsavel_nome
    FROM onboarding_tarefas t
    LEFT JOIN usuarios u ON t.responsavel_id = u.id
    WHERE t.onboarding_id = ?
    ORDER BY t.etapa, t.id ASC
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

