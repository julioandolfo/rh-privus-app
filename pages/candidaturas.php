<?php
/**
 * Lista de Candidaturas
 */

$page_title = 'Candidaturas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/recrutamento_functions.php';

require_page_permission('candidaturas.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$where = ["1=1"];
$params = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "v.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$filtro_status = $_GET['status'] ?? '';
if ($filtro_status) {
    $where[] = "c.status = ?";
    $params[] = $filtro_status;
}

$filtro_vaga = $_GET['vaga_id'] ?? '';
if ($filtro_vaga) {
    $where[] = "c.vaga_id = ?";
    $params[] = (int)$filtro_vaga;
}

$sql = "
    SELECT c.*,
           cand.nome_completo,
           cand.email,
           v.titulo as vaga_titulo,
           e.nome_fantasia as empresa_nome,
           u.nome as recrutador_nome
    FROM candidaturas c
    INNER JOIN candidatos cand ON c.candidato_id = cand.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN usuarios u ON c.recrutador_responsavel = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.created_at DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidaturas = $stmt->fetchAll();

// Busca vagas para filtro
$stmt = $pdo->query("SELECT id, titulo FROM vagas ORDER BY titulo");
$vagas = $stmt->fetchAll();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Candidaturas</h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="kanban_selecao.php" class="btn btn-primary">
                                <i class="ki-duotone ki-chart-simple fs-2"></i>
                                Ver Kanban
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <!-- Filtros -->
                        <div class="mb-5">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <select name="status" class="form-select">
                                        <option value="">Todos os status</option>
                                        <option value="nova" <?= $filtro_status === 'nova' ? 'selected' : '' ?>>Nova</option>
                                        <option value="triagem" <?= $filtro_status === 'triagem' ? 'selected' : '' ?>>Triagem</option>
                                        <option value="entrevista" <?= $filtro_status === 'entrevista' ? 'selected' : '' ?>>Entrevista</option>
                                        <option value="avaliacao" <?= $filtro_status === 'avaliacao' ? 'selected' : '' ?>>Avaliação</option>
                                        <option value="aprovada" <?= $filtro_status === 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
                                        <option value="reprovada" <?= $filtro_status === 'reprovada' ? 'selected' : '' ?>>Reprovada</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select name="vaga_id" class="form-select">
                                        <option value="">Todas as vagas</option>
                                        <?php foreach ($vagas as $vaga): ?>
                                        <option value="<?= $vaga['id'] ?>" <?= $filtro_vaga == $vaga['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vaga['titulo']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-light-primary">Filtrar</button>
                                    <a href="candidaturas.php" class="btn btn-light">Limpar</a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Candidato</th>
                                        <th>Vaga</th>
                                        <th>Status</th>
                                        <th>Nota</th>
                                        <th>Recrutador</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidaturas as $candidatura): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($candidatura['nome_completo']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($candidatura['email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($candidatura['vaga_titulo']) ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'nova' => 'primary',
                                                'triagem' => 'warning',
                                                'entrevista' => 'info',
                                                'avaliacao' => 'secondary',
                                                'aprovada' => 'success',
                                                'reprovada' => 'danger'
                                            ];
                                            $color = $status_colors[$candidatura['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-light-<?= $color ?>"><?= ucfirst($candidatura['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($candidatura['nota_geral']): ?>
                                            <strong><?= $candidatura['nota_geral'] ?>/10</strong>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($candidatura['recrutador_nome'] ?? '-') ?></td>
                                        <td><?= date('d/m/Y', strtotime($candidatura['data_candidatura'])) ?></td>
                                        <td>
                                            <a href="candidatura_view.php?id=<?= $candidatura['id'] ?>" class="btn btn-sm btn-light-primary">
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

