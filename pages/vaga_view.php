<?php
/**
 * Visualização Detalhada da Vaga
 */

$page_title = 'Detalhes da Vaga';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('vaga_view.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$vaga_id = (int)($_GET['id'] ?? 0);

if (!$vaga_id) {
    redirect('vagas.php', 'Vaga não encontrada', 'error');
}

// Busca vaga
$stmt = $pdo->prepare("
    SELECT v.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo,
           u.nome as criado_por_nome,
           COUNT(DISTINCT c.id) as total_candidaturas,
           COUNT(DISTINCT CASE WHEN c.status = 'aprovada' THEN c.id END) as candidaturas_aprovadas
    FROM vagas v
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN setores s ON v.setor_id = s.id
    LEFT JOIN cargos car ON v.cargo_id = car.id
    LEFT JOIN usuarios u ON v.criado_por = u.id
    LEFT JOIN candidaturas c ON v.id = c.vaga_id
    WHERE v.id = ?
    GROUP BY v.id
");
$stmt->execute([$vaga_id]);
$vaga = $stmt->fetch();

if (!$vaga || !can_access_empresa($vaga['empresa_id'])) {
    redirect('vagas.php', 'Sem permissão', 'error');
}

// Processa benefícios
$beneficios = [];
if ($vaga['beneficios']) {
    $beneficios = json_decode($vaga['beneficios'], true) ?: [];
}

// Busca etapas da vaga
$stmt = $pdo->prepare("
    SELECT e.*, ve.ordem as ordem_vaga
    FROM processo_seletivo_etapas e
    INNER JOIN vagas_etapas ve ON e.id = ve.etapa_id
    WHERE ve.vaga_id = ?
    ORDER BY ve.ordem ASC
");
$stmt->execute([$vaga_id]);
$etapas = $stmt->fetchAll();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card mb-5">
                    <div class="card-header">
                        <h2><?= htmlspecialchars($vaga['titulo']) ?></h2>
                        <div class="card-toolbar">
                            <?php if (has_role(['ADMIN', 'RH'])): ?>
                            <a href="vaga_edit.php?id=<?= $vaga_id ?>" class="btn btn-light-warning me-2">Editar</a>
                            <?php endif; ?>
                            <a href="vagas.php" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Empresa:</strong> <?= htmlspecialchars($vaga['empresa_nome']) ?></p>
                                <?php if ($vaga['nome_setor']): ?>
                                <p><strong>Setor:</strong> <?= htmlspecialchars($vaga['nome_setor']) ?></p>
                                <?php endif; ?>
                                <?php if ($vaga['nome_cargo']): ?>
                                <p><strong>Cargo:</strong> <?= htmlspecialchars($vaga['nome_cargo']) ?></p>
                                <?php endif; ?>
                                <p><strong>Modalidade:</strong> <?= htmlspecialchars($vaga['modalidade']) ?></p>
                                <p><strong>Tipo:</strong> <?= htmlspecialchars($vaga['tipo_contrato']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Status:</strong> 
                                    <span class="badge badge-light-primary"><?= ucfirst($vaga['status']) ?></span>
                                </p>
                                <p><strong>Quantidade:</strong> <?= $vaga['quantidade_preenchida'] ?>/<?= $vaga['quantidade_vagas'] ?></p>
                                <p><strong>Candidaturas:</strong> <?= $vaga['total_candidaturas'] ?> (<?= $vaga['candidaturas_aprovadas'] ?> aprovadas)</p>
                                <p><strong>Criado por:</strong> <?= htmlspecialchars($vaga['criado_por_nome']) ?></p>
                                <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($vaga['created_at'])) ?></p>
                            </div>
                        </div>
                        
                        <?php if ($vaga['salario_min'] || $vaga['salario_max']): ?>
                        <div class="mt-3">
                            <strong>Salário:</strong> 
                            R$ <?= number_format($vaga['salario_min'] ?? 0, 2, ',', '.') ?>
                            <?php if ($vaga['salario_max']): ?>
                            - R$ <?= number_format($vaga['salario_max'], 2, ',', '.') ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($beneficios)): ?>
                        <div class="mt-3">
                            <strong>Benefícios:</strong>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($beneficios as $beneficio): ?>
                                <span class="badge badge-light-primary"><?= htmlspecialchars($beneficio) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Descrição -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3>Descrição</h3>
                    </div>
                    <div class="card-body">
                        <?= nl2br(htmlspecialchars($vaga['descricao'])) ?>
                    </div>
                </div>
                
                <!-- Requisitos -->
                <?php if ($vaga['requisitos_obrigatorios'] || $vaga['requisitos_desejaveis']): ?>
                <div class="card mb-5">
                    <div class="card-header">
                        <h3>Requisitos</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($vaga['requisitos_obrigatorios']): ?>
                        <h4>Obrigatórios</h4>
                        <div><?= nl2br(htmlspecialchars($vaga['requisitos_obrigatorios'])) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($vaga['requisitos_desejaveis']): ?>
                        <h4 class="mt-4">Desejáveis</h4>
                        <div><?= nl2br(htmlspecialchars($vaga['requisitos_desejaveis'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Etapas do Processo -->
                <?php if (!empty($etapas)): ?>
                <div class="card mb-5">
                    <div class="card-header">
                        <h3>Etapas do Processo Seletivo</h3>
                    </div>
                    <div class="card-body">
                        <ol>
                            <?php foreach ($etapas as $etapa): ?>
                            <li><?= htmlspecialchars($etapa['nome']) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Ações -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex gap-2">
                            <a href="kanban_selecao.php?vaga_id=<?= $vaga_id ?>" class="btn btn-primary">
                                Ver Kanban
                            </a>
                            <?php if ($vaga['usar_landing_page_customizada']): ?>
                            <a href="vaga_landing_page.php?id=<?= $vaga_id ?>" class="btn btn-success">
                                Editar Landing Page
                            </a>
                            <?php endif; ?>
                            <a href="../vaga_publica.php?id=<?= $vaga_id ?>" target="_blank" class="btn btn-info">
                                Ver Portal Público
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

