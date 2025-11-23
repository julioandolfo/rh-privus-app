<?php
/**
 * Página Pública da Vaga - Landing Page Editável
 */

require_once __DIR__ . '/includes/functions.php';

$vaga_id = (int)($_GET['id'] ?? 0);

if (!$vaga_id) {
    header('Location: portal_vagas.php');
    exit;
}

$pdo = getDB();

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vaga['titulo']) ?> - RH Privus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cor-primaria: <?= $landing_page['cor_primaria'] ?? '#009ef7' ?>;
            --cor-secundaria: <?= $landing_page['cor_secundaria'] ?? '#f1416c' ?>;
        }
        .hero-section {
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            color: white;
            padding: 80px 0;
        }
        .beneficio-item {
            padding: 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php if ($usar_landing_customizada && $landing_page): ?>
        <!-- Landing Page Customizada -->
        <?php if ($landing_page['logo_empresa']): ?>
        <div class="container mt-3">
            <img src="<?= htmlspecialchars($landing_page['logo_empresa']) ?>" alt="Logo" height="60">
        </div>
        <?php endif; ?>
        
        <?php foreach ($componentes as $componente): ?>
            <?php if ($componente['tipo_componente'] === 'hero'): ?>
            <div class="hero-section">
                <div class="container text-center">
                    <?php if ($componente['imagem']): ?>
                    <img src="<?= htmlspecialchars($componente['imagem']) ?>" alt="Hero" class="img-fluid mb-4" style="max-height: 400px;">
                    <?php endif; ?>
                    <h1><?= htmlspecialchars($componente['titulo'] ?: $vaga['titulo']) ?></h1>
                    <?php if ($componente['conteudo']): ?>
                    <p class="lead"><?= nl2br(htmlspecialchars($componente['conteudo'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'sobre_vaga'): ?>
            <div class="container my-5">
                <h2><?= htmlspecialchars($componente['titulo'] ?: 'Sobre a Vaga') ?></h2>
                <div><?= nl2br(htmlspecialchars($componente['conteudo'] ?: $vaga['descricao'])) ?></div>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'requisitos'): ?>
            <div class="container my-5">
                <h2><?= htmlspecialchars($componente['titulo'] ?: 'Requisitos') ?></h2>
                <?php if ($vaga['requisitos_obrigatorios']): ?>
                <h4>Obrigatórios:</h4>
                <div><?= nl2br(htmlspecialchars($vaga['requisitos_obrigatorios'])) ?></div>
                <?php endif; ?>
                <?php if ($vaga['requisitos_desejaveis']): ?>
                <h4>Desejáveis:</h4>
                <div><?= nl2br(htmlspecialchars($vaga['requisitos_desejaveis'])) ?></div>
                <?php endif; ?>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'beneficios'): ?>
            <div class="container my-5">
                <h2><?= htmlspecialchars($componente['titulo'] ?: 'Benefícios') ?></h2>
                <?php if (!empty($beneficios)): ?>
                <div class="row">
                    <?php foreach ($beneficios as $beneficio): ?>
                    <div class="col-md-4">
                        <div class="beneficio-item">
                            <strong><?= htmlspecialchars($beneficio) ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'formulario'): ?>
            <div class="container my-5">
                <h2><?= htmlspecialchars($componente['titulo'] ?: 'Candidatar-se') ?></h2>
                <?php 
                $vaga_id_form = $vaga_id;
                include __DIR__ . '/formulario_candidatura.php'; 
                ?>
            </div>
            <?php elseif ($componente['tipo_componente'] === 'custom'): ?>
            <div class="container my-5">
                <?php if ($componente['titulo']): ?>
                <h2><?= htmlspecialchars($componente['titulo']) ?></h2>
                <?php endif; ?>
                <div><?= $componente['conteudo'] ?></div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Se não tiver componente de formulário, adiciona no final -->
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
            <h2>Candidatar-se</h2>
            <?php 
            $vaga_id_form = $vaga_id;
            include __DIR__ . '/formulario_candidatura.php'; 
            ?>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Template Padrão -->
        <div class="hero-section">
            <div class="container text-center">
                <h1><?= htmlspecialchars($vaga['titulo']) ?></h1>
                <p class="lead"><?= htmlspecialchars($vaga['empresa_nome']) ?></p>
            </div>
        </div>
        
        <div class="container my-5">
            <div class="row">
                <div class="col-md-8">
                    <h2>Sobre a Vaga</h2>
                    <div><?= nl2br(htmlspecialchars($vaga['descricao'])) ?></div>
                    
                    <?php if ($vaga['requisitos_obrigatorios']): ?>
                    <h3 class="mt-4">Requisitos Obrigatórios</h3>
                    <div><?= nl2br(htmlspecialchars($vaga['requisitos_obrigatorios'])) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($vaga['requisitos_desejaveis']): ?>
                    <h3 class="mt-4">Requisitos Desejáveis</h3>
                    <div><?= nl2br(htmlspecialchars($vaga['requisitos_desejaveis'])) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h4>Informações</h4>
                            <p><strong>Empresa:</strong> <?= htmlspecialchars($vaga['empresa_nome']) ?></p>
                            <?php if ($vaga['nome_setor']): ?>
                            <p><strong>Setor:</strong> <?= htmlspecialchars($vaga['nome_setor']) ?></p>
                            <?php endif; ?>
                            <p><strong>Modalidade:</strong> <?= htmlspecialchars($vaga['modalidade']) ?></p>
                            <p><strong>Tipo:</strong> <?= htmlspecialchars($vaga['tipo_contrato']) ?></p>
                            
                            <?php if ($vaga['salario_min'] || $vaga['salario_max']): ?>
                            <p><strong>Salário:</strong> 
                            R$ <?= number_format($vaga['salario_min'] ?? 0, 2, ',', '.') ?>
                            <?php if ($vaga['salario_max']): ?>
                            - R$ <?= number_format($vaga['salario_max'], 2, ',', '.') ?>
                            <?php endif; ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($beneficios)): ?>
                            <h5 class="mt-3">Benefícios</h5>
                            <ul>
                                <?php foreach ($beneficios as $beneficio): ?>
                                <li><?= htmlspecialchars($beneficio) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-5">
                <h2>Candidatar-se</h2>
                <?php 
                $vaga_id_form = $vaga_id;
                include __DIR__ . '/formulario_candidatura.php'; 
                ?>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Formulário de candidatura (incluído)
if (!function_exists('render_formulario_candidatura')) {
    function render_formulario_candidatura($vaga_id) {
        ?>
        <form id="formCandidatura" class="row g-3">
            <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
            <div class="col-md-6">
                <label class="form-label">Nome Completo *</label>
                <input type="text" name="nome_completo" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefone</label>
                <input type="tel" name="telefone" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">LinkedIn</label>
                <input type="url" name="linkedin" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Currículo (PDF, DOC, DOCX) *</label>
                <input type="file" name="curriculo" class="form-control" accept=".pdf,.doc,.docx" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Enviar Candidatura</button>
            </div>
        </form>
        
        <script>
        document.getElementById('formCandidatura').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const response = await fetch('<?= get_base_url() ?>/api/recrutamento/candidaturas/criar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Candidatura enviada com sucesso!');
                    if (data.link_acompanhamento) {
                        window.location.href = data.link_acompanhamento;
                    }
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                alert('Erro ao enviar candidatura');
            }
        });
        </script>
        <?php
    }
}
?>

