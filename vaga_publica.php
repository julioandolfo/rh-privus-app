<?php
/**
 * Página Pública da Vaga - Landing Page Editável com Layout Moderno
 */

require_once __DIR__ . '/includes/functions.php';

$vaga_id = (int)($_GET['id'] ?? 0);

if (!$vaga_id) {
    header('Location: portal_vagas.php');
    exit;
}

$pdo = getDB();

// Busca configuração do portal (para usar cores e logo)
$stmt = $pdo->query("SELECT * FROM portal_vagas_config WHERE ativo = 1 LIMIT 1");
$portal_config = $stmt->fetch();

if (!$portal_config) {
    $portal_config = [
        'cor_primaria' => '#009ef7',
        'cor_secundaria' => '#50cd89',
        'logo_url' => null
    ];
}

// Busca vaga
$stmt = $pdo->prepare("
    SELECT v.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo
    FROM vagas v
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN setores s ON v.setor_id = s.id
    LEFT JOIN cargos car ON v.cargo_id = car.id
    WHERE v.id = ? AND v.status = 'aberta' AND v.publicar_portal = 1
");
$stmt->execute([$vaga_id]);
$vaga = $stmt->fetch();

if (!$vaga) {
    header('Location: portal_vagas.php');
    exit;
}

// Busca landing page customizada (se existir e estiver ativa)
$usar_landing_customizada = false;
$landing_page = null;
$componentes = [];

if ($vaga['usar_landing_page_customizada']) {
    $stmt = $pdo->prepare("SELECT * FROM vagas_landing_pages WHERE vaga_id = ? AND ativo = 1");
    $stmt->execute([$vaga_id]);
    $landing_page = $stmt->fetch();
    
    if ($landing_page) {
        $usar_landing_customizada = true;
        
        // Busca componentes
        $stmt = $pdo->prepare("
            SELECT * FROM vagas_landing_page_componentes
            WHERE landing_page_id = ? AND visivel = 1
            ORDER BY ordem ASC
        ");
        $stmt->execute([$landing_page['id']]);
        $componentes = $stmt->fetchAll();
    }
}

// Processa benefícios
$beneficios = [];
if ($vaga['beneficios']) {
    $beneficios = json_decode($vaga['beneficios'], true) ?: [];
}

$base_url = get_base_url();
$cor_primaria = $landing_page['cor_primaria'] ?? $portal_config['cor_primaria'] ?? '#009ef7';
$cor_secundaria = $landing_page['cor_secundaria'] ?? $portal_config['cor_secundaria'] ?? '#50cd89';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vaga['titulo']) ?> - RH Privus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --cor-primaria: <?= htmlspecialchars($cor_primaria) ?>;
            --cor-secundaria: <?= htmlspecialchars($cor_secundaria) ?>;
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
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            color: white;
            padding: 120px 0 80px;
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
            background: url('<?= $landing_page['imagem_hero'] ?? '' ?>') center/cover;
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
            margin-bottom: 1rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .hero-section .lead {
            font-size: 1.5rem;
            opacity: 0.95;
            margin-bottom: 2rem;
        }
        
        /* Cards e Seções */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
            border: none;
        }
        
        .info-card h3 {
            color: var(--cor-primaria);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--cor-secundaria);
        }
        
        .info-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item i {
            color: var(--cor-primaria);
            font-size: 1.25rem;
            width: 30px;
            margin-right: 1rem;
        }
        
        .info-item strong {
            color: #64748b;
            min-width: 120px;
        }
        
        .info-item span {
            color: #2d3748;
            font-weight: 500;
        }
        
        /* Benefícios */
        .beneficios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .beneficio-card {
            background: linear-gradient(135deg, rgba(0, 158, 247, 0.08) 0%, rgba(80, 205, 137, 0.08) 100%);
            border: 1px solid rgba(0, 158, 247, 0.15);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .beneficio-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
            border-color: var(--cor-primaria);
        }
        
        .beneficio-card i {
            font-size: 1.25rem;
            color: var(--cor-secundaria);
            flex-shrink: 0;
        }
        
        .beneficio-card strong {
            color: #2d3748;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Conteúdo */
        .content-section {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
        }
        
        .content-section h2 {
            color: var(--cor-primaria);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--cor-secundaria);
        }
        
        .content-section p,
        .content-section div {
            color: #64748b;
            line-height: 1.8;
            font-size: 1.05rem;
        }
        
        /* Requisitos */
        .requisitos-list {
            list-style: none;
            padding: 0;
        }
        
        .requisitos-list li {
            padding: 0.75rem 0;
            padding-left: 2rem;
            position: relative;
            color: #64748b;
            line-height: 1.8;
        }
        
        .requisitos-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--cor-secundaria);
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        /* Botão CTA */
        .btn-candidatar {
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-candidatar:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 158, 247, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-candidatar i {
            margin-right: 0.5rem;
        }
        
        /* Badges */
        .vaga-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            margin: 0.25rem;
        }
        
        .vaga-badge i {
            font-size: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .hero-section .lead {
                font-size: 1.25rem;
            }
            
            .beneficios-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="portal_vagas.php">
                <?php if ($portal_config['logo_url']): ?>
                <img src="<?= htmlspecialchars($portal_config['logo_url']) ?>" alt="Logo">
                <?php else: ?>
                RH Privus
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="portal_vagas.php">
                            <i class="bi bi-arrow-left me-1"></i>
                            Voltar para Vagas
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($usar_landing_customizada && $landing_page): ?>
        <!-- Landing Page Customizada -->
        <?php if ($landing_page['logo_empresa']): ?>
        <div class="container mt-5 pt-4">
            <img src="<?= htmlspecialchars($landing_page['logo_empresa']) ?>" alt="Logo" height="60" class="mb-4">
        </div>
        <?php endif; ?>
        
        <?php foreach ($componentes as $componente): ?>
            <?php if ($componente['tipo_componente'] === 'hero'): ?>
            <div class="hero-section">
                <div class="container hero-content text-center">
                    <?php if ($componente['imagem']): ?>
                    <img src="<?= htmlspecialchars($componente['imagem']) ?>" alt="Hero" class="img-fluid mb-4" style="max-height: 400px; border-radius: 16px;">
                    <?php endif; ?>
                    <h1><?= htmlspecialchars($componente['titulo'] ?: $vaga['titulo']) ?></h1>
                    <?php if ($componente['conteudo']): ?>
                    <p class="lead"><?= nl2br(htmlspecialchars($componente['conteudo'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'sobre_vaga'): ?>
            <div class="container my-5">
                <div class="content-section">
                    <h2><?= htmlspecialchars($componente['titulo'] ?: 'Sobre a Vaga') ?></h2>
                    <div><?= nl2br(htmlspecialchars($componente['conteudo'] ?: $vaga['descricao'])) ?></div>
                </div>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'requisitos'): ?>
            <div class="container my-5">
                <div class="content-section">
                    <h2><?= htmlspecialchars($componente['titulo'] ?: 'Requisitos') ?></h2>
                    <?php if ($vaga['requisitos_obrigatorios']): ?>
                    <h4 class="mt-4 mb-3" style="color: var(--cor-primaria);">Obrigatórios:</h4>
                    <ul class="requisitos-list">
                        <?php 
                        $reqs_obrigatorios = explode("\n", $vaga['requisitos_obrigatorios']);
                        foreach ($reqs_obrigatorios as $req): 
                            if (trim($req)):
                        ?>
                        <li><?= htmlspecialchars(trim($req)) ?></li>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </ul>
                    <?php endif; ?>
                    <?php if ($vaga['requisitos_desejaveis']): ?>
                    <h4 class="mt-4 mb-3" style="color: var(--cor-primaria);">Desejáveis:</h4>
                    <ul class="requisitos-list">
                        <?php 
                        $reqs_desejaveis = explode("\n", $vaga['requisitos_desejaveis']);
                        foreach ($reqs_desejaveis as $req): 
                            if (trim($req)):
                        ?>
                        <li><?= htmlspecialchars(trim($req)) ?></li>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'beneficios'): ?>
            <div class="container my-5">
                <div class="content-section">
                    <h2><?= htmlspecialchars($componente['titulo'] ?: 'Benefícios') ?></h2>
                    <?php if (!empty($beneficios)): ?>
                    <div class="beneficios-grid">
                        <?php foreach ($beneficios as $beneficio): ?>
                        <div class="beneficio-card">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong><?= htmlspecialchars($beneficio) ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'formulario'): ?>
            <div class="container my-5">
                <div class="content-section text-center">
                    <h2><?= htmlspecialchars($componente['titulo'] ?: 'Candidatar-se') ?></h2>
                    <p class="mb-4">Preencha o formulário para se candidatar a esta vaga</p>
                    <a href="candidatar.php?id=<?= $vaga_id ?>" class="btn-candidatar" style="max-width: 400px; display: inline-block;">
                        <i class="bi bi-send"></i> Candidatar-se Agora
                    </a>
                </div>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'custom'): ?>
            <div class="container my-5">
                <div class="content-section">
                    <?php if ($componente['titulo']): ?>
                    <h2><?= htmlspecialchars($componente['titulo']) ?></h2>
                    <?php endif; ?>
                    <div><?= $componente['conteudo'] ?></div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Se não tiver componente de formulário, adiciona CTA no final -->
        <?php 
        $tem_formulario = false;
        foreach ($componentes as $comp) {
            if ($comp['tipo_componente'] === 'formulario') {
                $tem_formulario = true;
                break;
            }
        }
        if (!$tem_formulario):
        ?>
        <div class="container my-5">
            <div class="content-section text-center">
                <h2>Candidatar-se</h2>
                <p class="mb-4">Preencha o formulário para se candidatar a esta vaga</p>
                <a href="candidatar.php?id=<?= $vaga_id ?>" class="btn-candidatar" style="max-width: 400px; display: inline-block;">
                    <i class="bi bi-send"></i> Candidatar-se Agora
                </a>
            </div>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Template Padrão Moderno -->
        <div class="hero-section">
            <div class="container hero-content text-center">
                <h1><?= htmlspecialchars($vaga['titulo']) ?></h1>
                <p class="lead"><?= htmlspecialchars($vaga['empresa_nome']) ?></p>
                <div class="mt-4">
                    <span class="vaga-badge">
                        <i class="bi bi-geo-alt"></i>
                        <?= htmlspecialchars($vaga['modalidade']) ?>
                    </span>
                    <span class="vaga-badge">
                        <i class="bi bi-file-earmark-text"></i>
                        <?= htmlspecialchars($vaga['tipo_contrato']) ?>
                    </span>
                    <?php if ($vaga['nome_setor']): ?>
                    <span class="vaga-badge">
                        <i class="bi bi-building"></i>
                        <?= htmlspecialchars($vaga['nome_setor']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="container my-5">
            <div class="row">
                <!-- Conteúdo Principal -->
                <div class="col-lg-8">
                    <div class="content-section">
                        <h2>Sobre a Vaga</h2>
                        <div><?= nl2br(htmlspecialchars($vaga['descricao'])) ?></div>
                    </div>
                    
                    <?php if ($vaga['requisitos_obrigatorios']): ?>
                    <div class="content-section">
                        <h2>Requisitos Obrigatórios</h2>
                        <ul class="requisitos-list">
                            <?php 
                            $reqs_obrigatorios = explode("\n", $vaga['requisitos_obrigatorios']);
                            foreach ($reqs_obrigatorios as $req): 
                                if (trim($req)):
                            ?>
                            <li><?= htmlspecialchars(trim($req)) ?></li>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($vaga['requisitos_desejaveis']): ?>
                    <div class="content-section">
                        <h2>Requisitos Desejáveis</h2>
                        <ul class="requisitos-list">
                            <?php 
                            $reqs_desejaveis = explode("\n", $vaga['requisitos_desejaveis']);
                            foreach ($reqs_desejaveis as $req): 
                                if (trim($req)):
                            ?>
                            <li><?= htmlspecialchars(trim($req)) ?></li>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar de Informações -->
                <div class="col-lg-4">
                    <div class="info-card sticky-top" style="top: 100px;">
                        <h3>Informações</h3>
                        
                        <div class="info-item">
                            <i class="bi bi-building"></i>
                            <strong>Empresa:</strong>
                            <span><?= htmlspecialchars($vaga['empresa_nome']) ?></span>
                        </div>
                        
                        <?php if ($vaga['nome_setor']): ?>
                        <div class="info-item">
                            <i class="bi bi-diagram-3"></i>
                            <strong>Setor:</strong>
                            <span><?= htmlspecialchars($vaga['nome_setor']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vaga['nome_cargo']): ?>
                        <div class="info-item">
                            <i class="bi bi-briefcase"></i>
                            <strong>Cargo:</strong>
                            <span><?= htmlspecialchars($vaga['nome_cargo']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <i class="bi bi-geo-alt"></i>
                            <strong>Modalidade:</strong>
                            <span><?= htmlspecialchars($vaga['modalidade']) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <i class="bi bi-file-earmark-text"></i>
                            <strong>Tipo:</strong>
                            <span><?= htmlspecialchars($vaga['tipo_contrato']) ?></span>
                        </div>
                        
                        <?php if ($vaga['salario_min'] || $vaga['salario_max']): ?>
                        <div class="info-item">
                            <i class="bi bi-currency-dollar"></i>
                            <strong>Salário:</strong>
                            <span style="color: var(--cor-secundaria); font-weight: 700;">
                                R$ <?= number_format($vaga['salario_min'] ?? 0, 2, ',', '.') ?>
                                <?php if ($vaga['salario_max']): ?>
                                - R$ <?= number_format($vaga['salario_max'], 2, ',', '.') ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vaga['localizacao']): ?>
                        <div class="info-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <strong>Localização:</strong>
                            <span><?= htmlspecialchars($vaga['localizacao']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vaga['horario_trabalho']): ?>
                        <div class="info-item">
                            <i class="bi bi-clock"></i>
                            <strong>Horário:</strong>
                            <span><?= htmlspecialchars($vaga['horario_trabalho']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vaga['dias_trabalho']): ?>
                        <div class="info-item">
                            <i class="bi bi-calendar-week"></i>
                            <strong>Dias:</strong>
                            <span><?= htmlspecialchars($vaga['dias_trabalho']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($beneficios)): ?>
                        <div class="mt-4">
                            <h4 style="color: var(--cor-primaria); margin-bottom: 0.75rem; font-size: 1rem;">Benefícios</h4>
                            <div class="beneficios-grid">
                                <?php foreach ($beneficios as $beneficio): ?>
                                <div class="beneficio-card">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <strong><?= htmlspecialchars($beneficio) ?></strong>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- CTA de Candidatura -->
            <div class="content-section text-center">
                <h2>Candidatar-se</h2>
                <p class="mb-4">Preencha o formulário para se candidatar a esta vaga</p>
                <a href="candidatar.php?id=<?= $vaga_id ?>" class="btn-candidatar" style="max-width: 400px; display: inline-block;">
                    <i class="bi bi-send"></i> Candidatar-se Agora
                </a>
            </div>
        </div>
    <?php endif; ?>

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
