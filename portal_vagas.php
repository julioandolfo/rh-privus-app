<?php
/**
 * Portal Público de Vagas - Layout Moderno e Editável
 */

require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

// Busca configuração do portal
$stmt = $pdo->query("SELECT * FROM portal_vagas_config WHERE ativo = 1 LIMIT 1");
$config = $stmt->fetch();

// Se não tem configuração ou portal inativo, usa padrão
if (!$config) {
    $config = [
        'titulo_pagina' => 'Trabalhe Conosco',
        'descricao_pagina' => 'Encontre a oportunidade perfeita para sua carreira',
        'cor_primaria' => '#009ef7',
        'cor_secundaria' => '#50cd89',
        'logo_url' => null,
        'imagem_hero_url' => null,
        'texto_hero' => 'Venha fazer parte do nosso time!',
        'texto_cta' => 'Ver Vagas',
        'mostrar_filtros' => 1
    ];
}

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

$ordem = $config['ordem_exibicao'] ?? 'data_criacao';
$ordem_sql = match($ordem) {
    'titulo' => 'v.titulo ASC',
    'empresa' => 'e.nome_fantasia ASC',
    default => 'v.created_at DESC'
};

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
    ORDER BY $ordem_sql
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vagas = $stmt->fetchAll();

// Busca cargos para filtro
$stmt = $pdo->query("SELECT DISTINCT c.id, c.nome_cargo FROM cargos c INNER JOIN vagas v ON c.id = v.cargo_id WHERE v.status = 'aberta' AND v.publicar_portal = 1 ORDER BY c.nome_cargo");
$cargos = $stmt->fetchAll();

$base_url = get_base_url();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['titulo_pagina']) ?> - RH Privus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --cor-primaria: <?= htmlspecialchars($config['cor_primaria']) ?>;
            --cor-secundaria: <?= htmlspecialchars($config['cor_secundaria']) ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            color: #2d3748;
            line-height: 1.6;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            color: white;
            padding: 100px 0 80px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('<?= $config['imagem_hero_url'] ? htmlspecialchars($config['imagem_hero_url']) : '' ?>') center/cover;
            opacity: 0.15;
            z-index: 0;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .hero-section p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        
        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--cor-primaria) !important;
        }
        
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        /* Cards de Vagas */
        .vaga-card {
            background: white;
            border-radius: 16px;
            padding: 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .vaga-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
        
        .vaga-card-header {
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            padding: 1.5rem;
            color: white;
        }
        
        .vaga-card-header h5 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.3;
        }
        
        .vaga-card-body {
            padding: 1.5rem;
            flex-grow: 1;
        }
        
        .vaga-empresa {
            color: var(--cor-primaria);
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        
        .vaga-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .vaga-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            background: #f0f4ff;
            color: var(--cor-primaria);
            border: 1px solid rgba(0, 158, 247, 0.2);
        }
        
        .vaga-badge.modalidade {
            background: #e8f5e9;
            color: var(--cor-secundaria);
            border-color: rgba(80, 205, 137, 0.2);
        }
        
        .vaga-salario {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--cor-secundaria);
            margin: 1rem 0;
        }
        
        .vaga-descricao {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .vaga-descricao * {
            all: unset;
            color: inherit;
            font-size: inherit;
            line-height: inherit;
        }
        
        .beneficios-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .beneficio-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.7rem;
            background: #f8f9fa;
            border-radius: 12px;
            font-size: 0.8rem;
            color: #64748b;
        }
        
        .beneficio-tag i {
            color: var(--cor-secundaria);
        }
        
        .vaga-card-footer {
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-vaga {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-vaga:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 158, 247, 0.4);
            color: white;
        }
        
        /* Filtros */
        .filtros-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin: -60px auto 3rem;
            position: relative;
            z-index: 10;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 1200px;
        }
        
        .filtros-section .form-control,
        .filtros-section .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .filtros-section .form-control:focus,
        .filtros-section .form-select:focus {
            border-color: var(--cor-primaria);
            box-shadow: 0 0 0 3px rgba(0, 158, 247, 0.1);
        }
        
        .btn-filtrar {
            background: var(--cor-primaria);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-filtrar:hover {
            background: var(--cor-secundaria);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 158, 247, 0.3);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .filtros-section {
                margin-top: -40px;
                padding: 1.5rem;
            }
            
            .vaga-card-header h5 {
                font-size: 1.25rem;
            }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .vaga-card {
            animation: fadeInUp 0.5s ease-out;
        }
        
        .vaga-card:nth-child(1) { animation-delay: 0.1s; }
        .vaga-card:nth-child(2) { animation-delay: 0.2s; }
        .vaga-card:nth-child(3) { animation-delay: 0.3s; }
        .vaga-card:nth-child(4) { animation-delay: 0.4s; }
        .vaga-card:nth-child(5) { animation-delay: 0.5s; }
        .vaga-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <?php if ($config['logo_url']): ?>
                <img src="<?= htmlspecialchars($config['logo_url']) ?>" alt="Logo">
                <?php else: ?>
                <?= htmlspecialchars($config['titulo_pagina']) ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#vagas">Vagas</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1><?= htmlspecialchars($config['texto_hero'] ?: $config['titulo_pagina']) ?></h1>
                <p><?= htmlspecialchars($config['descricao_pagina']) ?></p>
                <a href="#vagas" class="btn btn-light btn-lg px-5 py-3 rounded-pill fw-semibold">
                    <?= htmlspecialchars($config['texto_cta']) ?>
                    <i class="bi bi-arrow-down ms-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Filtros -->
    <?php if ($config['mostrar_filtros']): ?>
    <div class="container">
        <div class="filtros-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="busca" class="form-control" 
                               placeholder="Buscar vagas..." value="<?= htmlspecialchars($filtro_busca) ?>">
                    </div>
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
                    <button type="submit" class="btn btn-filtrar w-100">
                        <i class="bi bi-funnel me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Lista de Vagas -->
    <section id="vagas" class="container mb-5" style="margin-top: 2rem;">
        <div class="row">
            <?php if (empty($vagas)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="bi bi-briefcase"></i>
                    <h3>Nenhuma vaga encontrada</h3>
                    <p>Tente ajustar os filtros de busca</p>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($vagas as $vaga): ?>
            <div class="col-md-6 col-lg-4">
                <div class="vaga-card">
                    <div class="vaga-card-header">
                        <h5><?= htmlspecialchars($vaga['titulo']) ?></h5>
                    </div>
                    <div class="vaga-card-body">
                        <div class="vaga-empresa">
                            <i class="bi bi-building me-1"></i>
                            <?= htmlspecialchars($vaga['empresa_nome']) ?>
                            <?php if ($vaga['nome_setor']): ?>
                            <span class="text-muted"> • <?= htmlspecialchars($vaga['nome_setor']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="vaga-badges">
                            <span class="vaga-badge modalidade">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?= htmlspecialchars($vaga['modalidade']) ?>
                            </span>
                            <span class="vaga-badge">
                                <i class="bi bi-file-earmark-text me-1"></i>
                                <?= htmlspecialchars($vaga['tipo_contrato']) ?>
                            </span>
                        </div>
                        
                        <?php if ($vaga['salario_min'] || $vaga['salario_max']): ?>
                        <div class="vaga-salario">
                            <i class="bi bi-currency-dollar me-1"></i>
                            R$ <?= number_format($vaga['salario_min'] ?? 0, 2, ',', '.') ?>
                            <?php if ($vaga['salario_max']): ?>
                            - R$ <?= number_format($vaga['salario_max'], 2, ',', '.') ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="vaga-descricao">
                            <?= strip_tags(substr($vaga['descricao'], 0, 150)) ?>...
                        </div>
                        
                        <?php if ($vaga['beneficios']): ?>
                        <?php 
                        $beneficios = json_decode($vaga['beneficios'], true);
                        if (is_array($beneficios) && !empty($beneficios)):
                        ?>
                        <div class="beneficios-list">
                            <?php foreach (array_slice($beneficios, 0, 3) as $beneficio): ?>
                            <span class="beneficio-tag">
                                <i class="bi bi-check-circle"></i>
                                <?= htmlspecialchars($beneficio) ?>
                            </span>
                            <?php endforeach; ?>
                            <?php if (count($beneficios) > 3): ?>
                            <span class="beneficio-tag">+<?= count($beneficios) - 3 ?> mais</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="vaga-card-footer">
                        <a href="vaga_publica.php?id=<?= $vaga['id'] ?>" class="btn btn-vaga">
                            Ver Detalhes e Candidatar-se
                            <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> RH Privus. Todos os direitos reservados.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
