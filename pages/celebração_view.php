<?php
/**
 * Visualizar Celebração
 */

$page_title = 'Celebração';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$celebração_id = (int)($_GET['id'] ?? 0);

if ($celebração_id <= 0) {
    redirect('celebracoes.php', 'Celebração não encontrada', 'error');
}

$pdo = getDB();

// Busca celebração
$stmt = $pdo->prepare("
    SELECT c.*,
           cr.nome_completo as remetente_nome,
           cr.foto as remetente_foto,
           cd.nome_completo as destinatario_nome,
           cd.foto as destinatario_foto,
           u.nome as remetente_usuario_nome
    FROM celebracoes c
    LEFT JOIN colaboradores cr ON c.remetente_id = cr.id
    LEFT JOIN usuarios u ON c.remetente_usuario_id = u.id
    INNER JOIN colaboradores cd ON c.destinatario_id = cd.id
    WHERE c.id = ?
");
$stmt->execute([$celebração_id]);
$celebração = $stmt->fetch();

if (!$celebração) {
    redirect('celebracoes.php', 'Celebração não encontrada', 'error');
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
                            <a href="celebracoes.php" class="btn btn-sm btn-light me-2">
                                <i class="ki-duotone ki-arrow-left fs-2"></i>
                                Voltar
                            </a>
                            <h2>🎉 <?= htmlspecialchars($celebração['titulo']) ?></h2>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <h4>De</h4>
                                <div class="d-flex align-items-center">
                                    <?php
                                    $remetente_nome = $celebração['remetente_nome'] ?? $celebração['remetente_usuario_nome'] ?? 'Sistema';
                                    $remetente_foto = $celebração['remetente_foto'] ?? null;
                                    ?>
                                    <?php if ($remetente_foto): ?>
                                    <img src="<?= htmlspecialchars($remetente_foto) ?>" class="rounded-circle me-3" width="50" height="50" alt="">
                                    <?php else: ?>
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-primary text-white">
                                            <?= strtoupper(substr($remetente_nome, 0, 1)) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($remetente_nome) ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h4>Para</h4>
                                <div class="d-flex align-items-center">
                                    <?php if ($celebração['destinatario_foto']): ?>
                                    <img src="<?= htmlspecialchars($celebração['destinatario_foto']) ?>" class="rounded-circle me-3" width="50" height="50" alt="">
                                    <?php else: ?>
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-success text-white">
                                            <?= strtoupper(substr($celebração['destinatario_nome'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($celebração['destinatario_nome']) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Tipo</label>
                                <p>
                                    <?php
                                    $tipos = [
                                        'aniversario' => '🎂 Aniversário',
                                        'promocao' => '📈 Promoção',
                                        'conquista' => '🏆 Conquista',
                                        'reconhecimento' => '⭐ Reconhecimento',
                                        'outro' => '🎉 Outro'
                                    ];
                                    echo $tipos[$celebração['tipo']] ?? $celebração['tipo'];
                                    ?>
                                </p>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Data</label>
                                <p><?= date('d/m/Y', strtotime($celebração['data_celebração'])) ?></p>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Status</label>
                                <p>
                                    <?php
                                    $badge_class = [
                                        'ativo' => 'badge-success',
                                        'oculto' => 'badge-secondary',
                                        'removido' => 'badge-danger'
                                    ];
                                    $status_class = $badge_class[$celebração['status']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= ucfirst($celebração['status']) ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($celebração['descricao']): ?>
                        <div class="mb-5">
                            <label class="form-label fw-bold">Descrição</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($celebração['descricao'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($celebração['imagem']): ?>
                        <div class="mb-5">
                            <label class="form-label fw-bold">Imagem</label>
                            <div>
                                <img src="<?= htmlspecialchars($celebração['imagem']) ?>" class="img-fluid rounded" alt="Imagem da celebração" style="max-height: 400px;">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

