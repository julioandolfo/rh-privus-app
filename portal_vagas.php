<?php
/**
 * Portal Público de Vagas - Landing Pages Editáveis
 */

require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

// Busca vagas abertas e publicadas
$where = ["v.status = 'aberta'", "v.publicar_portal = 1"];
$params = [];

// Filtros
$filtro_cargo = $_GET['cargo'] ?? '';
$filtro_modalidade = $_GET['modalidade'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';

if ($filtro_cargo) {
    $where[] = "car.id = ?";
    $params[] = (int)$filtro_cargo;
}

if ($filtro_modalidade) {
    $where[] = "v.modalidade = ?";
    $params[] = $filtro_modalidade;
}

if ($filtro_busca) {
    $where[] = "(v.titulo LIKE ? OR v.descricao LIKE ?)";
    $busca_termo = "%{$filtro_busca}%";
    $params[] = $busca_termo;
    $params[] = $busca_termo;
}

$sql = "
    SELECT v.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo
    FROM vagas v
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN setores s ON v.setor_id = s.id
    LEFT JOIN cargos car ON v.cargo_id = car.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY v.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vagas = $stmt->fetchAll();

// Busca cargos para filtro
$stmt = $pdo->query("SELECT DISTINCT c.id, c.nome_cargo FROM cargos c INNER JOIN vagas v ON c.id = v.cargo_id WHERE v.status = 'aberta' AND v.publicar_portal = 1 ORDER BY c.nome_cargo");
$cargos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas Abertas - RH Privus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .vaga-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .vaga-card:hover {
            transform: translateY(-5px);
        }
        .beneficio-badge {
            display: inline-block;
            margin: 2px;
            padding: 4px 8px;
            background: #f0f0f0;
            border-radius: 4px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="#">RH Privus</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#vagas">Vagas</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h1 class="mb-4">Vagas Abertas</h1>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="busca" class="form-control" placeholder="Buscar vagas..." value="<?= htmlspecialchars($filtro_busca) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="cargo" class="form-select">
                            <option value="">Todos os cargos</option>
                            <?php foreach ($cargos as $cargo): ?>
                            <option value="<?= $cargo['id'] ?>" <?= $filtro_cargo == $cargo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cargo['nome_cargo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="modalidade" class="form-select">
                            <option value="">Todas as modalidades</option>
                            <option value="Presencial" <?= $filtro_modalidade == 'Presencial' ? 'selected' : '' ?>>Presencial</option>
                            <option value="Remoto" <?= $filtro_modalidade == 'Remoto' ? 'selected' : '' ?>>Remoto</option>
                            <option value="Híbrido" <?= $filtro_modalidade == 'Híbrido' ? 'selected' : '' ?>>Híbrido</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Vagas -->
        <div class="row" id="vagas">
            <?php if (empty($vagas)): ?>
            <div class="col-12">
                <div class="alert alert-info">Nenhuma vaga encontrada.</div>
            </div>
            <?php else: ?>
            <?php foreach ($vagas as $vaga): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card vaga-card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($vaga['titulo']) ?></h5>
                        <p class="text-muted mb-2">
                            <strong><?= htmlspecialchars($vaga['empresa_nome']) ?></strong>
                            <?php if ($vaga['nome_setor']): ?>
                            <br><small><?= htmlspecialchars($vaga['nome_setor']) ?></small>
                            <?php endif; ?>
                        </p>
                        
                        <div class="mb-2">
                            <span class="badge bg-primary"><?= htmlspecialchars($vaga['modalidade']) ?></span>
                            <span class="badge bg-secondary"><?= htmlspecialchars($vaga['tipo_contrato']) ?></span>
                        </div>
                        
                        <?php if ($vaga['salario_min'] || $vaga['salario_max']): ?>
                        <p class="mb-2">
                            <strong>Salário:</strong> 
                            R$ <?= number_format($vaga['salario_min'] ?? 0, 2, ',', '.') ?>
                            <?php if ($vaga['salario_max']): ?>
                            - R$ <?= number_format($vaga['salario_max'], 2, ',', '.') ?>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($vaga['beneficios']): ?>
                        <div class="mb-2">
                            <?php 
                            $beneficios = json_decode($vaga['beneficios'], true);
                            if (is_array($beneficios)):
                            ?>
                            <?php foreach ($beneficios as $beneficio): ?>
                            <span class="beneficio-badge"><?= htmlspecialchars($beneficio) ?></span>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <p class="card-text text-truncate"><?= htmlspecialchars(substr($vaga['descricao'], 0, 100)) ?>...</p>
                    </div>
                    <div class="card-footer">
                        <a href="vaga_publica.php?id=<?= $vaga['id'] ?>" class="btn btn-primary w-100">
                            Ver Detalhes e Candidatar-se
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

