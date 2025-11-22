<?php
/**
 * Relatório de Ocorrências
 */

$page_title = 'Relatório de Ocorrências';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/header.php';

require_page_permission('relatorio_ocorrencias.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_empresa = $_GET['empresa'] ?? '';
$filtro_setor = $_GET['setor'] ?? '';
$filtro_colaborador = $_GET['colaborador'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$filtro_data_fim = $_GET['data_fim'] ?? date('Y-m-t');

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

if ($filtro_empresa && $usuario['role'] === 'ADMIN') {
    $where[] = "c.empresa_id = ?";
    $params[] = $filtro_empresa;
}

if ($filtro_setor) {
    $where[] = "c.setor_id = ?";
    $params[] = $filtro_setor;
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
           c.cpf,
           s.nome_setor,
           e.nome_fantasia as empresa_nome,
           u.nome as usuario_nome
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    LEFT JOIN setores s ON c.setor_id = s.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN usuarios u ON o.usuario_id = u.id
    $where_sql
    ORDER BY o.data_ocorrencia DESC, o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ocorrencias = $stmt->fetchAll();

// Busca empresas para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt_empresas->fetchAll();
} else {
    $empresas = [];
}

// Busca setores para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_setores = $pdo->query("SELECT id, nome_setor FROM setores WHERE status = 'ativo' ORDER BY nome_setor");
} elseif ($usuario['role'] === 'RH') {
    $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
    $stmt_setores->execute([$usuario['empresa_id']]);
} else {
    $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE id = ? AND status = 'ativo'");
    $stmt_setores->execute([$setor_id]);
}
$setores = $stmt_setores->fetchAll();

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

// Exportação CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_ocorrencias_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalho
    fputcsv($output, ['Data', 'Empresa', 'Setor', 'Colaborador', 'CPF', 'Tipo', 'Descrição', 'Registrado por', 'Data Registro'], ';');
    
    // Dados
    foreach ($ocorrencias as $ocorrencia) {
        fputcsv($output, [
            formatar_data($ocorrencia['data_ocorrencia']),
            $ocorrencia['empresa_nome'] ?? '',
            $ocorrencia['nome_setor'] ?? '',
            $ocorrencia['colaborador_nome'],
            formatar_cpf($ocorrencia['cpf']),
            $tipos_ocorrencias[$ocorrencia['tipo']] ?? $ocorrencia['tipo'],
            $ocorrencia['descricao'],
            $ocorrencia['usuario_nome'],
            formatar_data($ocorrencia['created_at'], 'd/m/Y H:i')
        ], ';');
    }
    
    fclose($output);
    exit;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-earmark-text"></i> Relatório de Ocorrências</h2>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
            <i class="bi bi-download"></i> Exportar CSV
        </a>
    </div>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <?php if ($usuario['role'] === 'ADMIN'): ?>
                <div class="col-md-3">
                    <label class="form-label">Empresa</label>
                    <select name="empresa" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($empresas as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $filtro_empresa == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['nome_fantasia']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-<?= $usuario['role'] === 'ADMIN' ? '2' : '3' ?>">
                    <label class="form-label">Setor</label>
                    <select name="setor" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($setores as $setor): ?>
                        <option value="<?= $setor['id'] ?>" <?= $filtro_setor == $setor['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($setor['nome_setor']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-<?= $usuario['role'] === 'ADMIN' ? '2' : '3' ?>">
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
                
                <div class="col-md-<?= $usuario['role'] === 'ADMIN' ? '2' : '3' ?>">
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
                
                <div class="col-md-<?= $usuario['role'] === 'ADMIN' ? '1' : '1' ?>">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $filtro_data_inicio ?>">
                </div>
                
                <div class="col-md-<?= $usuario['role'] === 'ADMIN' ? '1' : '1' ?>">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $filtro_data_fim ?>">
                </div>
                
                <div class="col-md-<?= $usuario['role'] === 'ADMIN' ? '1' : '1' ?> d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="relatorio_ocorrencias.php" class="btn btn-secondary">
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
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                            <th>Empresa</th>
                            <?php endif; ?>
                            <th>Setor</th>
                            <th>Colaborador</th>
                            <th>CPF</th>
                            <th>Tipo</th>
                            <th>Descrição</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ocorrencias as $ocorrencia): ?>
                        <tr>
                            <td><?= formatar_data($ocorrencia['data_ocorrencia']) ?></td>
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                            <td><?= htmlspecialchars($ocorrencia['empresa_nome'] ?? '') ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($ocorrencia['nome_setor'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ocorrencia['colaborador_nome']) ?></td>
                            <td><?= formatar_cpf($ocorrencia['cpf']) ?></td>
                            <td>
                                <span class="badge bg-<?= in_array($ocorrencia['tipo'], ['elogio']) ? 'success' : ($ocorrencia['tipo'] === 'advertência' ? 'danger' : 'warning') ?>">
                                    <?= htmlspecialchars($tipos_ocorrencias[$ocorrencia['tipo']] ?? $ocorrencia['tipo']) ?>
                                </span>
                            </td>
                            <td><?= nl2br(htmlspecialchars(substr($ocorrencia['descricao'], 0, 100))) ?><?= strlen($ocorrencia['descricao']) > 100 ? '...' : '' ?></td>
                            <td><?= htmlspecialchars($ocorrencia['usuario_nome']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

