<?php
/**
 * Configuração de Pontos - Sistema de Pontuação
 */

$page_title = 'Configurações de Pontos';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('configuracoes_pontos.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    
    if ($acao === 'atualizar') {
        $id = $_POST['id'] ?? null;
        $pontos = intval($_POST['pontos'] ?? 0);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        if ($id) {
            $stmt = $pdo->prepare("UPDATE pontos_config SET pontos = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$pontos, $ativo, $id]);
            
            redirect('configuracoes_pontos.php', 'Configuração atualizada com sucesso!', 'success');
        }
    }
}

// Busca configurações
$stmt = $pdo->query("SELECT * FROM pontos_config ORDER BY acao");
$configuracoes = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Configurações de Pontos</h1>
            <span class="text-muted mt-1 fw-semibold fs-7">Configure quantos pontos cada ação vale</span>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        <!--begin::Card-->
        <div class="card card-flush">
            <div class="card-header pt-7">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-800">Ações e Pontos</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Defina a pontuação para cada ação do sistema</span>
                </h3>
            </div>
            <div class="card-body pt-6">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Ação</th>
                                <th class="min-w-300px">Descrição</th>
                                <th class="min-w-150px">Pontos</th>
                                <th class="min-w-100px">Status</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($configuracoes as $config): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-light-primary"><?= htmlspecialchars($config['acao']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($config['descricao'] ?? '-') ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="acao" value="atualizar">
                                        <input type="hidden" name="id" value="<?= $config['id'] ?>">
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" name="pontos" value="<?= $config['pontos'] ?>" class="form-control form-control-solid w-100px" min="0" required>
                                            <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="acao" value="atualizar">
                                        <input type="hidden" name="id" value="<?= $config['id'] ?>">
                                        <input type="hidden" name="pontos" value="<?= $config['pontos'] ?>">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="ativo" value="1" <?= $config['ativo'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <label class="form-check-label">
                                                <?= $config['ativo'] ? 'Ativo' : 'Inativo' ?>
                                            </label>
                                        </div>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <?php if ($config['ativo']): ?>
                                        <span class="badge badge-light-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

