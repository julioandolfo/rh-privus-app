<?php
/**
 * Página de Acompanhamento do Candidato (Sem Login - Token)
 * Layout moderno seguindo o padrão das páginas públicas de vagas
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/recrutamento_functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
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
        'logo_url' => null,
        'titulo_pagina' => 'RH Privus'
    ];
}

// Busca candidatura por token
$candidatura = buscar_candidatura_por_token($token);

if (!$candidatura) {
    die('Token inválido ou candidatura não encontrada.');
}

// Busca histórico de etapas
$stmt = $pdo->prepare("
    SELECT ce.*, e.nome as etapa_nome, e.codigo as etapa_codigo, e.cor as etapa_cor,
           u.nome as avaliador_nome
    FROM candidaturas_etapas ce
    INNER JOIN processo_seletivo_etapas e ON ce.etapa_id = e.id
    LEFT JOIN usuarios u ON ce.avaliador_id = u.id
    WHERE ce.candidatura_id = ?
    ORDER BY ce.created_at ASC
");
$stmt->execute([$candidatura['id']]);
$etapas = $stmt->fetchAll();

// Busca entrevistas agendadas
$stmt = $pdo->prepare("
    SELECT * FROM entrevistas
    WHERE candidatura_id = ? AND status IN ('agendada', 'reagendada')
    ORDER BY data_agendada ASC
");
$stmt->execute([$candidatura['id']]);
$entrevistas = $stmt->fetchAll();

// Busca comentários/mensagens
$stmt = $pdo->prepare("
    SELECT cc.*, u.nome as usuario_nome
    FROM candidaturas_comentarios cc
    LEFT JOIN usuarios u ON cc.usuario_id = u.id
    WHERE cc.candidatura_id = ?
    ORDER BY cc.created_at DESC
    LIMIT 10
");
$stmt->execute([$candidatura['id']]);
$comentarios = $stmt->fetchAll();

// Calcula progresso
$total_etapas = count($etapas);
$etapas_concluidas = 0;
foreach ($etapas as $etapa) {
    if ($etapa['status'] === 'concluida') {
        $etapas_concluidas++;
    }
}
$percentual = $total_etapas > 0 ? ($etapas_concluidas / $total_etapas) * 100 : 0;

// Status colors
$status_colors = [
    'nova' => '#009ef7',
    'triagem' => '#ffc700',
    'entrevista' => '#7239ea',
    'avaliacao' => '#6c757d',
    'aprovada' => '#50cd89',
    'reprovada' => '#f1416c',
    'desistente' => '#a1a5b7'
];

$status_labels = [
    'nova' => 'Nova',
    'triagem' => 'Triagem',
    'entrevista' => 'Entrevista',
    'avaliacao' => 'Avaliação',
    'aprovada' => 'Aprovada',
    'reprovada' => 'Reprovada',
    'desistente' => 'Desistente'
];

$cor_primaria = $portal_config['cor_primaria'] ?? '#009ef7';
$cor_secundaria = $portal_config['cor_secundaria'] ?? '#50cd89';
$status_color = $status_colors[$candidatura['status']] ?? '#009ef7';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Candidatura - <?= htmlspecialchars($candidatura['vaga_titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --cor-primaria: <?= htmlspecialchars($cor_primaria) ?>;
            --cor-secundaria: <?= htmlspecialchars($cor_secundaria) ?>;
            --status-color: <?= htmlspecialchars($status_color) ?>;
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .navbar-brand img {
            height: 40px;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            color: white;
            padding: 80px 0 60px;
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
            background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.1) 0%, transparent 50%);
            z-index: 0;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .hero-section .lead {
            font-size: 1.25rem;
            opacity: 0.95;
            margin-bottom: 1.5rem;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            font-weight: 600;
            font-size: 0.95rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Cards */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
            border: none;
            transition: all 0.3s;
        }
        
        .info-card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .info-card h3 {
            color: var(--cor-primaria);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--cor-secundaria);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .info-card h3 i {
            font-size: 1.5rem;
        }
        
        /* Progress Bar */
        .progress-container {
            background: #f1f5f9;
            border-radius: 50px;
            height: 30px;
            overflow: hidden;
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .progress-bar-custom {
            background: linear-gradient(90deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            height: 100%;
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            transition: width 0.5s ease;
            box-shadow: 0 2px 8px rgba(0, 158, 247, 0.3);
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            border-radius: 3px;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
            padding-left: 2rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -32px;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            border: 4px solid #e2e8f0;
            z-index: 2;
            transition: all 0.3s;
        }
        
        .timeline-item.concluida::before {
            background: var(--cor-secundaria);
            border-color: var(--cor-secundaria);
            box-shadow: 0 0 0 4px rgba(80, 205, 137, 0.2);
        }
        
        .timeline-item.em_andamento::before {
            background: var(--cor-primaria);
            border-color: var(--cor-primaria);
            box-shadow: 0 0 0 4px rgba(0, 158, 247, 0.2);
            animation: pulse 2s infinite;
        }
        
        .timeline-item.pendente::before {
            background: #e2e8f0;
            border-color: #cbd5e0;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .timeline-content {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.25rem;
            border-left: 4px solid var(--cor-primaria);
        }
        
        .timeline-item.concluida .timeline-content {
            border-left-color: var(--cor-secundaria);
            background: #f0fdf4;
        }
        
        .timeline-item.em_andamento .timeline-content {
            border-left-color: var(--cor-primaria);
            background: #eff6ff;
        }
        
        .timeline-content h5 {
            color: var(--cor-primaria);
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .timeline-item.concluida .timeline-content h5 {
            color: var(--cor-secundaria);
        }
        
        .timeline-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.75rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .timeline-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .timeline-meta i {
            color: var(--cor-primaria);
        }
        
        /* Entrevistas */
        .entrevista-card {
            background: linear-gradient(135deg, rgba(0, 158, 247, 0.05) 0%, rgba(80, 205, 137, 0.05) 100%);
            border: 2px solid rgba(0, 158, 247, 0.15);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .entrevista-card:hover {
            border-color: var(--cor-primaria);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .entrevista-card h5 {
            color: var(--cor-primaria);
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        
        .entrevista-info {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 0.75rem;
        }
        
        .entrevista-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
        }
        
        .entrevista-info-item i {
            color: var(--cor-primaria);
        }
        
        .entrevista-info-item a {
            color: var(--cor-primaria);
            text-decoration: none;
            font-weight: 600;
        }
        
        .entrevista-info-item a:hover {
            text-decoration: underline;
        }
        
        /* Mensagens */
        .mensagem-card {
            background: white;
            border-left: 4px solid var(--cor-primaria);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .mensagem-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .mensagem-autor {
            font-weight: 700;
            color: var(--cor-primaria);
        }
        
        .mensagem-data {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        .mensagem-texto {
            color: #64748b;
            line-height: 1.7;
        }
        
        /* Sidebar */
        .sidebar-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
            border: none;
        }
        
        .sidebar-card h5 {
            color: var(--cor-primaria);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--cor-secundaria);
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
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
            margin-top: 0.25rem;
            flex-shrink: 0;
        }
        
        .info-item-content {
            flex: 1;
        }
        
        .info-item strong {
            color: #64748b;
            display: block;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .info-item span {
            color: #2d3748;
            font-weight: 600;
        }
        
        .nota-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .timeline {
                padding-left: 30px;
            }
            
            .timeline-item {
                padding-left: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="portal_vagas.php">
                <?php if (!empty($portal_config['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($portal_config['logo_url']) ?>" alt="Logo">
                <?php else: ?>
                    <?= htmlspecialchars($portal_config['titulo_pagina'] ?? 'RH Privus') ?>
                <?php endif; ?>
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1>Acompanhe sua Candidatura</h1>
                <p class="lead">Olá, <strong><?= htmlspecialchars($candidatura['candidato_nome']) ?></strong>!</p>
                <div class="status-badge">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Status: <?= htmlspecialchars($status_labels[$candidatura['status']] ?? ucfirst($candidatura['status'])) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Conteúdo Principal -->
            <div class="col-lg-8">
                <!-- Status e Informações -->
                <div class="info-card">
                    <h3>
                        <i class="bi bi-briefcase-fill"></i>
                        <?= htmlspecialchars($candidatura['vaga_titulo']) ?>
                    </h3>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <i class="bi bi-calendar-event"></i>
                                <div class="info-item-content">
                                    <strong>Data da Candidatura</strong>
                                    <span><?= date('d/m/Y H:i', strtotime($candidatura['data_candidatura'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <i class="bi bi-clock-history"></i>
                                <div class="info-item-content">
                                    <strong>Última Atualização</strong>
                                    <span><?= date('d/m/Y H:i', strtotime($candidatura['updated_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progresso -->
                <div class="info-card">
                    <h3>
                        <i class="bi bi-graph-up-arrow"></i>
                        Progresso do Processo Seletivo
                    </h3>
                    <div class="progress-container">
                        <div class="progress-bar-custom" style="width: <?= $percentual ?>%">
                            <?= round($percentual) ?>% Concluído
                        </div>
                    </div>
                    
                    <!-- Timeline -->
                    <?php if (!empty($etapas)): ?>
                    <div class="timeline">
                        <?php foreach ($etapas as $etapa): ?>
                        <div class="timeline-item <?= $etapa['status'] ?>">
                            <div class="timeline-content">
                                <h5><?= htmlspecialchars($etapa['etapa_nome']) ?></h5>
                                <div class="timeline-meta">
                                    <span>
                                        <i class="bi bi-info-circle"></i>
                                        Status: <strong><?= ucfirst(str_replace('_', ' ', $etapa['status'])) ?></strong>
                                    </span>
                                    <?php if ($etapa['data_inicio']): ?>
                                    <span>
                                        <i class="bi bi-play-circle"></i>
                                        Iniciado: <?= date('d/m/Y H:i', strtotime($etapa['data_inicio'])) ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($etapa['data_conclusao']): ?>
                                    <span>
                                        <i class="bi bi-check-circle"></i>
                                        Concluído: <?= date('d/m/Y H:i', strtotime($etapa['data_conclusao'])) ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($etapa['avaliador_nome']): ?>
                                    <span>
                                        <i class="bi bi-person"></i>
                                        Avaliador: <?= htmlspecialchars($etapa['avaliador_nome']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($etapa['nota']): ?>
                                <div class="mt-2">
                                    <span class="nota-badge">
                                        <i class="bi bi-star-fill me-1"></i>
                                        Nota: <?= $etapa['nota'] ?>/10
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if ($etapa['feedback']): ?>
                                <div class="alert alert-info mt-3 mb-0">
                                    <strong><i class="bi bi-chat-left-text me-2"></i>Feedback:</strong><br>
                                    <?= nl2br(htmlspecialchars($etapa['feedback'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Ainda não há etapas registradas para esta candidatura.
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Próximas Entrevistas -->
                <?php if (!empty($entrevistas)): ?>
                <div class="info-card">
                    <h3>
                        <i class="bi bi-calendar-check-fill"></i>
                        Próximas Entrevistas
                    </h3>
                    <?php foreach ($entrevistas as $entrevista): ?>
                    <div class="entrevista-card">
                        <h5><?= htmlspecialchars($entrevista['titulo']) ?></h5>
                        <div class="entrevista-info">
                            <div class="entrevista-info-item">
                                <i class="bi bi-calendar-event"></i>
                                <span><?= date('d/m/Y', strtotime($entrevista['data_agendada'])) ?></span>
                            </div>
                            <div class="entrevista-info-item">
                                <i class="bi bi-clock"></i>
                                <span><?= date('H:i', strtotime($entrevista['data_agendada'])) ?></span>
                            </div>
                            <?php if ($entrevista['link_videoconferencia']): ?>
                            <div class="entrevista-info-item">
                                <i class="bi bi-camera-video"></i>
                                <a href="<?= htmlspecialchars($entrevista['link_videoconferencia']) ?>" target="_blank">
                                    Acessar Entrevista
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($entrevista['localizacao']): ?>
                            <div class="entrevista-info-item">
                                <i class="bi bi-geo-alt"></i>
                                <span><?= htmlspecialchars($entrevista['localizacao']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($entrevista['observacoes']): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <strong>Observações:</strong><br>
                            <?= nl2br(htmlspecialchars($entrevista['observacoes'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Mensagens -->
                <?php if (!empty($comentarios)): ?>
                <div class="info-card">
                    <h3>
                        <i class="bi bi-chat-dots-fill"></i>
                        Mensagens e Atualizações
                    </h3>
                    <?php foreach ($comentarios as $comentario): ?>
                    <div class="mensagem-card">
                        <div class="mensagem-header">
                            <span class="mensagem-autor">
                                <i class="bi bi-person-circle me-2"></i>
                                <?= htmlspecialchars($comentario['usuario_nome'] ?? 'Sistema') ?>
                            </span>
                            <span class="mensagem-data">
                                <?= date('d/m/Y H:i', strtotime($comentario['created_at'])) ?>
                            </span>
                        </div>
                        <div class="mensagem-texto">
                            <?= nl2br(htmlspecialchars($comentario['comentario'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar-card">
                    <h5>
                        <i class="bi bi-info-circle-fill"></i>
                        Informações da Vaga
                    </h5>
                    <div class="info-item">
                        <i class="bi bi-briefcase"></i>
                        <div class="info-item-content">
                            <strong>Vaga</strong>
                            <span><?= htmlspecialchars($candidatura['vaga_titulo']) ?></span>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-calendar-check"></i>
                        <div class="info-item-content">
                            <strong>Data da Candidatura</strong>
                            <span><?= date('d/m/Y H:i', strtotime($candidatura['data_candidatura'])) ?></span>
                        </div>
                    </div>
                    <?php if ($candidatura['nota_geral']): ?>
                    <div class="info-item">
                        <i class="bi bi-star-fill"></i>
                        <div class="info-item-content">
                            <strong>Nota Geral</strong>
                            <span class="nota-badge"><?= $candidatura['nota_geral'] ?>/10</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="sidebar-card">
                    <h5>
                        <i class="bi bi-question-circle-fill"></i>
                        Precisa de Ajuda?
                    </h5>
                    <p class="text-muted mb-3">
                        Se você tiver alguma dúvida sobre sua candidatura, entre em contato conosco através do email:
                    </p>
                    <p class="text-center">
                        <a href="mailto:<?= htmlspecialchars($candidatura['candidato_email']) ?>" class="btn btn-primary">
                            <i class="bi bi-envelope me-2"></i>
                            Entrar em Contato
                        </a>
                    </p>
                    <hr>
                    <p class="text-center mb-0">
                        <a href="portal_vagas.php" class="text-decoration-none">
                            <i class="bi bi-arrow-left me-2"></i>
                            Ver outras vagas
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
