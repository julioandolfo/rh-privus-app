<?php
/**
 * Visualizar/Editar PDI
 */

$page_title = 'Plano de Desenvolvimento Individual';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$pdi_id = (int)($_GET['id'] ?? 0);

if ($pdi_id <= 0) {
    redirect('pdis.php', 'PDI não encontrado', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca PDI
$stmt = $pdo->prepare("
    SELECT p.*,
           c.nome_completo as colaborador_nome,
           c.foto as colaborador_foto,
           u.nome as criado_por_nome
    FROM pdis p
    INNER JOIN colaboradores c ON p.colaborador_id = c.id
    LEFT JOIN usuarios u ON p.criado_por = u.id
    WHERE p.id = ?
");
$stmt->execute([$pdi_id]);
$pdi = $stmt->fetch();

if (!$pdi) {
    redirect('pdis.php', 'PDI não encontrado', 'error');
}

// Verifica permissão
$pode_editar = false;
if ($usuario['colaborador_id'] == $pdi['colaborador_id'] ||
    $usuario['id'] == $pdi['criado_por'] ||
    $usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
    $pode_editar = true;
}

// Busca objetivos
$stmt = $pdo->prepare("
    SELECT * FROM pdi_objetivos 
    WHERE pdi_id = ? 
    ORDER BY ordem ASC, id ASC
");
$stmt->execute([$pdi_id]);
$objetivos = $stmt->fetchAll();

// Busca ações
$stmt = $pdo->prepare("
    SELECT pa.*, po.objetivo as objetivo_nome
    FROM pdi_acoes pa
    LEFT JOIN pdi_objetivos po ON pa.objetivo_id = po.id
    WHERE pa.pdi_id = ?
    ORDER BY pa.ordem ASC, pa.id ASC
");
$stmt->execute([$pdi_id]);
$acoes = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <a href="pdis.php" class="btn btn-sm btn-light me-2">
                                <i class="ki-duotone ki-arrow-left fs-2"></i>
                                Voltar
                            </a>
                            <h2>PDI - <?= htmlspecialchars($pdi['titulo']) ?></h2>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <h4>Colaborador</h4>
                                <div class="d-flex align-items-center">
                                    <?php if ($pdi['colaborador_foto']): ?>
                                    <img src="<?= htmlspecialchars($pdi['colaborador_foto']) ?>" class="rounded-circle me-3" width="50" height="50" alt="">
                                    <?php else: ?>
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-primary text-white">
                                            <?= strtoupper(substr($pdi['colaborador_nome'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($pdi['colaborador_nome']) ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Status</label>
                                <p>
                                    <?php
                                    $badge_class = [
                                        'rascunho' => 'badge-secondary',
                                        'ativo' => 'badge-success',
                                        'concluido' => 'badge-info',
                                        'cancelado' => 'badge-danger',
                                        'pausado' => 'badge-warning'
                                    ];
                                    $status_class = $badge_class[$pdi['status']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= ucfirst($pdi['status']) ?></span>
                                </p>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Progresso</label>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?= $pdi['progresso_percentual'] ?>%;" 
                                         aria-valuenow="<?= $pdi['progresso_percentual'] ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?= $pdi['progresso_percentual'] ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Data Início</label>
                                <p><?= date('d/m/Y', strtotime($pdi['data_inicio'])) ?></p>
                            </div>
                            
                            <?php if ($pdi['data_fim_prevista']): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Data Fim Prevista</label>
                                <p><?= date('d/m/Y', strtotime($pdi['data_fim_prevista'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($pdi['objetivo_geral']): ?>
                        <div class="mb-5">
                            <label class="form-label fw-bold">Objetivo Geral</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($pdi['objetivo_geral'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pdi['descricao']): ?>
                        <div class="mb-5">
                            <label class="form-label fw-bold">Descrição</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($pdi['descricao'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Objetivos -->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3>Objetivos</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($objetivos)): ?>
                        <p class="text-muted">Nenhum objetivo cadastrado.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Objetivo</th>
                                        <th>Prazo</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($objetivos as $obj): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($obj['objetivo']) ?></strong>
                                            <?php if ($obj['descricao']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($obj['descricao']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $obj['prazo'] ? date('d/m/Y', strtotime($obj['prazo'])) : '-' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_obj = [
                                                'pendente' => 'badge-warning',
                                                'em_andamento' => 'badge-info',
                                                'concluido' => 'badge-success',
                                                'cancelado' => 'badge-danger'
                                            ];
                                            $status_obj_class = $badge_obj[$obj['status']] ?? 'badge-secondary';
                                            ?>
                                            <span class="badge <?= $status_obj_class ?>"><?= ucfirst($obj['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($pode_editar && $obj['status'] !== 'concluido'): ?>
                                            <button class="btn btn-sm btn-success btn-concluir-item" 
                                                    data-tipo="objetivo" 
                                                    data-id="<?= $obj['id'] ?>">
                                                Concluir
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Ações -->
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3>Ações</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($acoes)): ?>
                        <p class="text-muted">Nenhuma ação cadastrada.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ação</th>
                                        <th>Objetivo</th>
                                        <th>Prazo</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($acoes as $acao): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($acao['acao']) ?></strong>
                                            <?php if ($acao['descricao']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($acao['descricao']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $acao['objetivo_nome'] ? htmlspecialchars($acao['objetivo_nome']) : '-' ?>
                                        </td>
                                        <td>
                                            <?= $acao['prazo'] ? date('d/m/Y', strtotime($acao['prazo'])) : '-' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_acao = [
                                                'pendente' => 'badge-warning',
                                                'em_andamento' => 'badge-info',
                                                'concluido' => 'badge-success',
                                                'cancelado' => 'badge-danger'
                                            ];
                                            $status_acao_class = $badge_acao[$acao['status']] ?? 'badge-secondary';
                                            ?>
                                            <span class="badge <?= $status_acao_class ?>"><?= ucfirst($acao['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($pode_editar && $acao['status'] !== 'concluido'): ?>
                                            <button class="btn btn-sm btn-success btn-concluir-item" 
                                                    data-tipo="acao" 
                                                    data-id="<?= $acao['id'] ?>">
                                                Concluir
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-concluir-item').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const tipo = this.dataset.tipo;
        const itemId = this.dataset.id;
        
        if (!confirm(`Deseja marcar este ${tipo} como concluído?`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('tipo', tipo);
        formData.append('item_id', itemId);
        formData.append('observacoes', '');
        
        fetch('<?= get_base_url() ?>/api/pdis/concluir_item.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`${tipo.charAt(0).toUpperCase() + tipo.slice(1)} marcado como concluído!`);
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro ao concluir item');
            console.error(error);
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

