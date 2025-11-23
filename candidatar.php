<?php
/**
 * Página de Candidatura - Formulário Separado
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

// Processa benefícios
$beneficios = [];
if ($vaga['beneficios']) {
    $beneficios = json_decode($vaga['beneficios'], true) ?: [];
}

$base_url = get_base_url();
$cor_primaria = $portal_config['cor_primaria'] ?? '#009ef7';
$cor_secundaria = $portal_config['cor_secundaria'] ?? '#50cd89';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatar-se - <?= htmlspecialchars($vaga['titulo']) ?> - RH Privus</title>
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
            padding: 60px 0 40px;
            margin-top: 70px;
        }
        
        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .hero-section .lead {
            font-size: 1.25rem;
            opacity: 0.95;
        }
        
        /* Vaga Info Card */
        .vaga-info-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
        }
        
        .vaga-info-card h3 {
            color: var(--cor-primaria);
            font-weight: 700;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--cor-secundaria);
        }
        
        .info-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            color: #64748b;
        }
        
        .info-item i {
            color: var(--cor-primaria);
            font-size: 1.1rem;
            width: 25px;
            margin-right: 0.75rem;
        }
        
        /* Form Section */
        .form-section {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .form-section h2 {
            color: var(--cor-primaria);
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .form-section .form-control,
        .form-section .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-section .form-control:focus,
        .form-section .form-select:focus {
            border-color: var(--cor-primaria);
            box-shadow: 0 0 0 3px rgba(0, 158, 247, 0.1);
        }
        
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
            width: 100%;
        }
        
        .btn-candidatar:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 158, 247, 0.4);
            color: white;
        }
        
        .btn-candidatar:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .hero-section .lead {
                font-size: 1.1rem;
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
                        <a class="nav-link fw-semibold" href="vaga_publica.php?id=<?= $vaga_id ?>">
                            <i class="bi bi-arrow-left me-1"></i>
                            Voltar para Vaga
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container text-center">
            <h1>Candidatar-se</h1>
            <p class="lead"><?= htmlspecialchars($vaga['titulo']) ?></p>
        </div>
    </div>
    
    <div class="container my-5">
        <div class="row">
            <!-- Informações da Vaga -->
            <div class="col-lg-4 mb-4">
                <div class="vaga-info-card sticky-top" style="top: 100px;">
                    <h3>Informações da Vaga</h3>
                    
                    <div class="info-item">
                        <i class="bi bi-building"></i>
                        <span><strong>Empresa:</strong> <?= htmlspecialchars($vaga['empresa_nome']) ?></span>
                    </div>
                    
                    <?php if ($vaga['nome_setor']): ?>
                    <div class="info-item">
                        <i class="bi bi-diagram-3"></i>
                        <span><strong>Setor:</strong> <?= htmlspecialchars($vaga['nome_setor']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($vaga['nome_cargo']): ?>
                    <div class="info-item">
                        <i class="bi bi-briefcase"></i>
                        <span><strong>Cargo:</strong> <?= htmlspecialchars($vaga['nome_cargo']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <i class="bi bi-geo-alt"></i>
                        <span><strong>Modalidade:</strong> <?= htmlspecialchars($vaga['modalidade']) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <i class="bi bi-file-earmark-text"></i>
                        <span><strong>Tipo:</strong> <?= htmlspecialchars($vaga['tipo_contrato']) ?></span>
                    </div>
                    
                    <?php if ($vaga['salario_min'] || $vaga['salario_max']): ?>
                    <div class="info-item">
                        <i class="bi bi-currency-dollar"></i>
                        <span><strong>Salário:</strong> 
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
                        <span><strong>Localização:</strong> <?= htmlspecialchars($vaga['localizacao']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($vaga['horario_trabalho']): ?>
                    <div class="info-item">
                        <i class="bi bi-clock"></i>
                        <span><strong>Horário:</strong> <?= htmlspecialchars($vaga['horario_trabalho']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($vaga['dias_trabalho']): ?>
                    <div class="info-item">
                        <i class="bi bi-calendar-week"></i>
                        <span><strong>Dias:</strong> <?= htmlspecialchars($vaga['dias_trabalho']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($beneficios)): ?>
                    <div class="mt-3 pt-3" style="border-top: 1px solid #e2e8f0;">
                        <strong style="color: var(--cor-primaria);">Benefícios:</strong>
                        <ul class="mt-2 mb-0" style="padding-left: 1.5rem; color: #64748b;">
                            <?php foreach ($beneficios as $beneficio): ?>
                            <li><?= htmlspecialchars($beneficio) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Formulário de Candidatura -->
            <div class="col-lg-8">
                <div class="form-section">
                    <h2>Preencha seus dados</h2>
                    
                    <form id="formCandidatura" class="row g-3" enctype="multipart/form-data">
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
                            <label class="form-label">Telefone/WhatsApp *</label>
                            <input type="tel" name="telefone" class="form-control" placeholder="(00) 00000-0000" id="telefone" required>
                            <small class="form-text text-muted">Número para contato</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">LinkedIn</label>
                            <input type="url" name="linkedin" class="form-control" placeholder="https://linkedin.com/in/seu-perfil">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Instagram</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" name="instagram" class="form-control" placeholder="seuinstagram" id="instagram">
                            </div>
                            <small class="form-text text-muted">Sem o @</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Portfolio/Site</label>
                            <input type="url" name="portfolio" class="form-control" placeholder="https://seuportfolio.com">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Currículo (PDF, DOC, DOCX) *</label>
                            <input type="file" name="curriculo" class="form-control" accept=".pdf,.doc,.docx" required>
                            <small class="form-text text-muted">Tamanho máximo: 10MB</small>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn-candidatar" id="btnSubmit">
                                <i class="bi bi-send"></i> Enviar Candidatura
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> RH Privus. Todos os direitos reservados.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        // Máscara para telefone
        $(document).ready(function() {
            if (typeof $.fn.mask !== 'undefined') {
                var SPMaskBehavior = function (val) {
                    return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00000';
                },
                spOptions = {
                    onKeyPress: function(val, e, field, options) {
                        field.mask(SPMaskBehavior.apply({}, arguments), options);
                    }
                };
                $('#telefone').mask(SPMaskBehavior, spOptions);
            }
        });
        
        // Validação e formatação do Instagram
        document.getElementById('instagram')?.addEventListener('input', function() {
            // Remove @ se o usuário digitar
            this.value = this.value.replace('@', '');
        });
        
        // Submete formulário
        document.getElementById('formCandidatura').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitBtn = document.getElementById('btnSubmit');
            
            // Garante que Instagram não tenha @
            const instagram = formData.get('instagram');
            if (instagram) {
                formData.set('instagram', instagram.toString().replace('@', ''));
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
            
            try {
                const response = await fetch('<?= get_base_url() ?>/api/recrutamento/candidaturas/criar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Candidatura enviada com sucesso!');
                    if (data.link_acompanhamento) {
                        if (confirm('Deseja acompanhar o status da sua candidatura?')) {
                            window.location.href = data.link_acompanhamento;
                        } else {
                            window.location.href = 'portal_vagas.php';
                        }
                    } else {
                        window.location.href = 'portal_vagas.php';
                    }
                } else {
                    alert('❌ Erro: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send"></i> Enviar Candidatura';
                }
            } catch (error) {
                alert('❌ Erro ao enviar candidatura. Tente novamente.');
                console.error(error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send"></i> Enviar Candidatura';
            }
        });
    </script>
</body>
</html>

