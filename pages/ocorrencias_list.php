<?php
/**
 * Lista de Ocorrências
 */

$page_title = 'Ocorrências';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/header.php';

require_page_permission('ocorrencias_list.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_colaborador = $_GET['colaborador'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

// Monta query com filtros
$where = [];
$params = [];

if ($usuario['role'] === 'RH') {
    $where[] = "c.empresa_id = ?";
    $params[] = $usuario['empresa_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $where[] = "c.setor_id = ?";
    $params[] = $setor_id;
}

if ($filtro_colaborador) {
    $where[] = "o.colaborador_id = ?";
    $params[] = $filtro_colaborador;
}

if ($filtro_tipo) {
    $where[] = "o.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_data_inicio) {
    $where[] = "o.data_ocorrencia >= ?";
    $params[] = $filtro_data_inicio;
}

if ($filtro_data_fim) {
    $where[] = "o.data_ocorrencia <= ?";
    $params[] = $filtro_data_fim;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT o.*, 
           c.nome_completo as colaborador_nome,
           u.nome as usuario_nome
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    LEFT JOIN usuarios u ON o.usuario_id = u.id
    $where_sql
    ORDER BY o.data_ocorrencia DESC, o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ocorrencias = $stmt->fetchAll();

// Busca colaboradores para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_colab = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo' ORDER BY nome_completo");
} elseif ($usuario['role'] === 'RH') {
    $stmt_colab = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_completo");
    $stmt_colab->execute([$usuario['empresa_id']]);
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt_colab = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE setor_id = ? AND status = 'ativo' ORDER BY nome_completo");
    $stmt_colab->execute([$setor_id]);
} else {
    $colaboradores = [];
}
$colaboradores = isset($stmt_colab) ? $stmt_colab->fetchAll() : [];

$tipos_ocorrencias = [
    'atraso' => 'Atraso',
    'falta' => 'Falta',
    'ausência injustificada' => 'Ausência Injustificada',
    'falha operacional' => 'Falha Operacional',
    'desempenho baixo' => 'Desempenho Baixo',
    'comportamento inadequado' => 'Comportamento Inadequado',
    'advertência' => 'Advertência',
    'elogio' => 'Elogio'
];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-clipboard-check"></i> Ocorrências</h2>
        <a href="ocorrencias_add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nova Ocorrência
        </a>
    </div>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Colaborador</label>
                    <select name="colaborador" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= $colab['id'] ?>" <?= $filtro_colaborador == $colab['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($colab['nome_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_ocorrencias as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $filtro_tipo === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $filtro_data_inicio ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $filtro_data_fim ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                    <a href="ocorrencias_list.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Colaborador</th>
                            <th>Tipo</th>
                            <th>Descrição</th>
                            <th>Registrado por</th>
                            <th>Data Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ocorrencias as $ocorrencia): ?>
                        <tr>
                            <td><?= formatar_data($ocorrencia['data_ocorrencia']) ?></td>
                            <td>
                                <a href="colaborador_view.php?id=<?= $ocorrencia['colaborador_id'] ?>">
                                    <?= htmlspecialchars($ocorrencia['colaborador_nome']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-<?= in_array($ocorrencia['tipo'], ['elogio']) ? 'success' : ($ocorrencia['tipo'] === 'advertência' ? 'danger' : 'warning') ?>">
                                    <?= htmlspecialchars($tipos_ocorrencias[$ocorrencia['tipo']] ?? $ocorrencia['tipo']) ?>
                                </span>
                            </td>
                            <td><?= nl2br(htmlspecialchars(substr($ocorrencia['descricao'], 0, 100))) ?><?= strlen($ocorrencia['descricao']) > 100 ? '...' : '' ?></td>
                            <td><?= htmlspecialchars($ocorrencia['usuario_nome']) ?></td>
                            <td><?= formatar_data($ocorrencia['created_at'], 'd/m/Y H:i') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

