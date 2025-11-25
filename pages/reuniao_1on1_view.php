<?php
/**
 * Visualizar Detalhes da Reunião 1:1
 */

$page_title = 'Detalhes da Reunião 1:1';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$reuniao_id = (int)($_GET['id'] ?? 0);

if ($reuniao_id <= 0) {
    redirect('reunioes_1on1.php', 'Reunião não encontrada', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca reunião
$stmt = $pdo->prepare("
    SELECT r.*,
           cl.nome_completo as lider_nome,
           cl.foto as lider_foto,
           cd.nome_completo as liderado_nome,
           cd.foto as liderado_foto,
           u.nome as criado_por_nome
    FROM reunioes_1on1 r
    INNER JOIN colaboradores cl ON r.lider_id = cl.id
    INNER JOIN colaboradores cd ON r.liderado_id = cd.id
    LEFT JOIN usuarios u ON r.created_by = u.id
    WHERE r.id = ?
");
$stmt->execute([$reuniao_id]);
$reuniao = $stmt->fetch();

if (!$reuniao) {
    redirect('reunioes_1on1.php', 'Reunião não encontrada', 'error');
}

// Verifica permissão
$pode_editar = false;
if ($usuario['colaborador_id'] == $reuniao['lider_id'] || 
    $usuario['colaborador_id'] == $reuniao['liderado_id'] ||
    $usuario['id'] == $reuniao['created_by'] ||
    $usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
    $pode_editar = true;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <a href="reunioes_1on1.php" class="btn btn-sm btn-light me-2">
                                <i class="ki-duotone ki-arrow-left fs-2"></i>
                                Voltar
                            </a>
                            <h2>Reunião 1:1</h2>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <h4>Líder</h4>
                                <div class="d-flex align-items-center">
                                    <?php if ($reuniao['lider_foto']): ?>
                                    <img src="../<?= htmlspecialchars($reuniao['lider_foto']) ?>" class="rounded-circle me-3" width="50" height="50" alt="">
                                    <?php else: ?>
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-primary text-white">
                                            <?= strtoupper(substr($reuniao['lider_nome'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($reuniao['lider_nome']) ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h4>Liderado</h4>
                                <div class="d-flex align-items-center">
                                    <?php if ($reuniao['liderado_foto']): ?>
                                    <img src="../<?= htmlspecialchars($reuniao['liderado_foto']) ?>" class="rounded-circle me-3" width="50" height="50" alt="">
                                    <?php else: ?>
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-success text-white">
                                            <?= strtoupper(substr($reuniao['liderado_nome'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($reuniao['liderado_nome']) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Data da Reunião</label>
                                <p><?= date('d/m/Y', strtotime($reuniao['data_reuniao'])) ?></p>
                            </div>
                            
                            <?php if ($reuniao['hora_inicio']): ?>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Horário</label>
                                <p>
                                    <?= date('H:i', strtotime($reuniao['hora_inicio'])) ?>
                                    <?php if ($reuniao['hora_fim']): ?>
                                    às <?= date('H:i', strtotime($reuniao['hora_fim'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Status</label>
                                <p>
                                    <?php
                                    $badge_class = [
                                        'agendada' => 'badge-warning',
                                        'realizada' => 'badge-success',
                                        'cancelada' => 'badge-danger',
                                        'reagendada' => 'badge-info'
                                    ];
                                    $status_class = $badge_class[$reuniao['status']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= ucfirst($reuniao['status']) ?></span>
                                </p>
                            </div>
                            
                            <?php if ($reuniao['avaliacao_lider'] || $reuniao['avaliacao_liderado']): ?>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Avaliações</label>
                                <p>
                                    <?php if ($reuniao['avaliacao_lider']): ?>
                                    Líder: <?= $reuniao['avaliacao_lider'] ?>/5 ⭐
                                    <?php endif; ?>
                                    <?php if ($reuniao['avaliacao_liderado']): ?>
                                    <br>Liderado: <?= $reuniao['avaliacao_liderado'] ?>/5 ⭐
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($reuniao['assuntos_tratados']): ?>
                        <div class="mb-5">
                            <label class="form-label fw-bold">Assuntos Tratados</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($reuniao['assuntos_tratados'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($reuniao['proximos_passos']): ?>
                        <div class="mb-5">
                            <label class="form-label fw-bold">Próximos Passos</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($reuniao['proximos_passos'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($reuniao['observacoes']): ?>
                        <div class="mb-5">
                            <label class="form-label fw-bold">Observações</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($reuniao['observacoes'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pode_editar && $reuniao['status'] !== 'realizada'): ?>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-editar-reuniao">
                                Editar Reunião
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Reunião -->
<?php if ($pode_editar): ?>
<div class="modal fade" id="modal-editar-reuniao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Reunião 1:1</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-editar-reuniao">
                    <input type="hidden" name="reuniao_id" value="<?= $reuniao_id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="agendada" <?= $reuniao['status'] === 'agendada' ? 'selected' : '' ?>>Agendada</option>
                            <option value="realizada" <?= $reuniao['status'] === 'realizada' ? 'selected' : '' ?>>Realizada</option>
                            <option value="cancelada" <?= $reuniao['status'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                            <option value="reagendada" <?= $reuniao['status'] === 'reagendada' ? 'selected' : '' ?>>Reagendada</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assuntos Tratados</label>
                        <textarea name="assuntos_tratados" class="form-control" rows="4"><?= htmlspecialchars($reuniao['assuntos_tratados']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Próximos Passos</label>
                        <textarea name="proximos_passos" class="form-control" rows="4"><?= htmlspecialchars($reuniao['proximos_passos']) ?></textarea>
                    </div>
                    
                    <?php if ($usuario['colaborador_id'] == $reuniao['lider_id']): ?>
                    <div class="mb-3">
                        <label class="form-label">Avaliação do Líder (1-5)</label>
                        <select name="avaliacao_lider" class="form-select">
                            <option value="">Não avaliado</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= $reuniao['avaliacao_lider'] == $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($usuario['colaborador_id'] == $reuniao['liderado_id']): ?>
                    <div class="mb-3">
                        <label class="form-label">Avaliação do Liderado (1-5)</label>
                        <select name="avaliacao_liderado" class="form-select">
                            <option value="">Não avaliado</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= $reuniao['avaliacao_liderado'] == $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($reuniao['observacoes']) ?></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-salvar-edicao">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-salvar-edicao').addEventListener('click', function() {
    const form = document.getElementById('form-editar-reuniao');
    const formData = new FormData(form);
    
    fetch('<?= get_base_url() ?>/api/reunioes_1on1/atualizar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Reunião atualizada com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao atualizar reunião');
        console.error(error);
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

