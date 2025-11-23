<?php
/**
 * Página de Acompanhamento do Candidato (Sem Login - Token)
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/recrutamento_functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: portal_vagas.php');
    exit;
}

$pdo = getDB();

// Busca candidatura por token
$candidatura = buscar_candidatura_por_token($token);

if (!$candidatura) {
    die('Token inválido ou candidatura não encontrada.');
}

// Busca histórico de etapas
$stmt = $pdo->prepare("
    SELECT ce.*, e.nome as etapa_nome, e.codigo as etapa_codigo,
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Candidatura - RH Privus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
        }
        .timeline-item.concluida::before {
            background: #50cd89;
        }
        .timeline-item.em_andamento::before {
            background: #009ef7;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -20px;
            top: 17px;
            width: 2px;
            height: calc(100% - 17px);
            background: #e0e0e0;
        }
        .timeline-item:last-child::after {
            display: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="portal_vagas.php">RH Privus</a>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-8">
                <h1>Acompanhar Candidatura</h1>
                <p class="text-muted">Olá, <strong><?= htmlspecialchars($candidatura['candidato_nome']) ?></strong>!</p>
                
                <!-- Status Atual -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3><?= htmlspecialchars($candidatura['vaga_titulo']) ?></h3>
                        <p class="mb-2"><strong>Status:</strong> 
                            <span class="badge bg-primary"><?= ucfirst($candidatura['status']) ?></span>
                        </p>
                        <p class="mb-0"><strong>Última atualização:</strong> 
                            <?= date('d/m/Y H:i', strtotime($candidatura['updated_at'])) ?>
                        </p>
                    </div>
                </div>
                
                <!-- Progresso -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4>Progresso do Processo</h4>
                        <div class="progress mb-3" style="height: 25px;">
                            <?php
                            $total_etapas = count($etapas);
                            $etapas_concluidas = 0;
                            foreach ($etapas as $etapa) {
                                if ($etapa['status'] === 'concluida') {
                                    $etapas_concluidas++;
                                }
                            }
                            $percentual = $total_etapas > 0 ? ($etapas_concluidas / $total_etapas) * 100 : 0;
                            ?>
                            <div class="progress-bar" role="progressbar" style="width: <?= $percentual ?>%">
                                <?= round($percentual) ?>%
                            </div>
                        </div>
                        
                        <!-- Timeline -->
                        <div class="timeline">
                            <?php foreach ($etapas as $etapa): ?>
                            <div class="timeline-item <?= $etapa['status'] ?>">
                                <h5><?= htmlspecialchars($etapa['etapa_nome']) ?></h5>
                                <p class="text-muted mb-1">
                                    <strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $etapa['status'])) ?>
                                </p>
                                <?php if ($etapa['data_inicio']): ?>
                                <p class="text-muted mb-1">
                                    <small>Iniciado em: <?= date('d/m/Y H:i', strtotime($etapa['data_inicio'])) ?></small>
                                </p>
                                <?php endif; ?>
                                <?php if ($etapa['data_conclusao']): ?>
                                <p class="text-muted mb-1">
                                    <small>Concluído em: <?= date('d/m/Y H:i', strtotime($etapa['data_conclusao'])) ?></small>
                                </p>
                                <?php endif; ?>
                                <?php if ($etapa['nota']): ?>
                                <p class="mb-1"><strong>Nota:</strong> <?= $etapa['nota'] ?>/10</p>
                                <?php endif; ?>
                                <?php if ($etapa['feedback']): ?>
                                <div class="alert alert-info mt-2">
                                    <strong>Feedback:</strong><br>
                                    <?= nl2br(htmlspecialchars($etapa['feedback'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Próximas Entrevistas -->
                <?php if (!empty($entrevistas)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4>Próximas Entrevistas</h4>
                        <?php foreach ($entrevistas as $entrevista): ?>
                        <div class="border rounded p-3 mb-2">
                            <h5><?= htmlspecialchars($entrevista['titulo']) ?></h5>
                            <p class="mb-1">
                                <strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($entrevista['data_agendada'])) ?>
                            </p>
                            <?php if ($entrevista['link_videoconferencia']): ?>
                            <p class="mb-1">
                                <strong>Link:</strong> 
                                <a href="<?= htmlspecialchars($entrevista['link_videoconferencia']) ?>" target="_blank">
                                    Acessar entrevista
                                </a>
                            </p>
                            <?php endif; ?>
                            <?php if ($entrevista['localizacao']): ?>
                            <p class="mb-0">
                                <strong>Local:</strong> <?= htmlspecialchars($entrevista['localizacao']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Mensagens -->
                <?php if (!empty($comentarios)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4>Mensagens</h4>
                        <?php foreach ($comentarios as $comentario): ?>
                        <div class="border rounded p-3 mb-2">
                            <strong><?= htmlspecialchars($comentario['usuario_nome'] ?? 'Sistema') ?></strong>
                            <span class="text-muted">
                                - <?= date('d/m/Y H:i', strtotime($comentario['created_at'])) ?>
                            </span>
                            <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($comentario['comentario'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>Informações da Vaga</h5>
                        <p><strong>Vaga:</strong> <?= htmlspecialchars($candidatura['vaga_titulo']) ?></p>
                        <p><strong>Data da Candidatura:</strong> 
                            <?= date('d/m/Y H:i', strtotime($candidatura['data_candidatura'])) ?>
                        </p>
                        <?php if ($candidatura['nota_geral']): ?>
                        <p><strong>Nota Geral:</strong> <?= $candidatura['nota_geral'] ?>/10</p>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <h6>Precisa de ajuda?</h6>
                        <p class="small">Entre em contato através do email: <?= htmlspecialchars($candidatura['candidato_email']) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

