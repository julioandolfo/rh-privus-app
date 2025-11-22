<?php
/**
 * Notificações - Visualizar todas as notificações do sistema
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/notificacoes.php';

require_page_permission('notificacoes.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$usuario_id = $usuario['id'] ?? null;
$colaborador_id = $usuario['colaborador_id'] ?? null;

// Filtros
$filtro_tipo = $_GET['tipo'] ?? 'todas'; // 'todas', 'nao_lidas', 'lidas'
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Busca notificações
$where = [];
$params = [];

if ($usuario_id) {
    $where[] = "usuario_id = ?";
    $params[] = $usuario_id;
} else if ($colaborador_id) {
    $where[] = "colaborador_id = ?";
    $params[] = $colaborador_id;
} else {
    $notificacoes = [];
    $total = 0;
}

if (!empty($where)) {
    $where_sql = implode(' AND ', $where);
    
    // Aplica filtro de lida/não lida
    if ($filtro_tipo === 'nao_lidas') {
        $where_sql .= " AND lida = 0";
    } else if ($filtro_tipo === 'lidas') {
        $where_sql .= " AND lida = 1";
    }
    
    // Conta total
    $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes_sistema WHERE $where_sql");
    $stmt_count->execute($params);
    $total = $stmt_count->fetch()['total'];
    
    // Busca notificações
    $params_query = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare("
        SELECT * FROM notificacoes_sistema
        WHERE $where_sql
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $notificacoes = [];
    $total = 0;
}

// Estatísticas
$total_nao_lidas = contar_notificacoes_nao_lidas($usuario_id, $colaborador_id);
$total_lidas = $total - $total_nao_lidas;

$page_title = 'Notificações';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Notificações</h1>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        <div class="row g-5 g-xl-8">
            <div class="col-xl-12">
                <!--begin::Card-->
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Minhas Notificações</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Total: <?= $total ?> | Não lidas: <?= $total_nao_lidas ?></span>
                        </h3>
                        <div class="card-toolbar">
                            <!--begin::Filtros-->
                            <div class="d-flex align-items-center gap-2">
                                <a href="?tipo=todas" class="btn btn-sm btn-light <?= $filtro_tipo === 'todas' ? 'btn-active' : '' ?>">
                                    Todas
                                </a>
                                <a href="?tipo=nao_lidas" class="btn btn-sm btn-light <?= $filtro_tipo === 'nao_lidas' ? 'btn-active' : '' ?>">
                                    Não Lidas
                                </a>
                                <a href="?tipo=lidas" class="btn btn-sm btn-light <?= $filtro_tipo === 'lidas' ? 'btn-active' : '' ?>">
                                    Lidas
                                </a>
                            </div>
                            <!--end::Filtros-->
                        </div>
                    </div>
                    <div class="card-body pt-6">
                        <?php if (empty($notificacoes)): ?>
                            <div class="text-center text-muted py-10">
                                <i class="ki-duotone ki-notification-status fs-3x text-gray-400 mb-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <p class="fs-5 fw-semibold text-gray-600">Nenhuma notificação encontrada</p>
                                <p class="text-muted">Você não possui notificações <?= $filtro_tipo !== 'todas' ? ($filtro_tipo === 'nao_lidas' ? 'não lidas' : 'lidas') : '' ?>.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-150px">Título</th>
                                            <th class="min-w-200px">Mensagem</th>
                                            <th class="min-w-100px">Tipo</th>
                                            <th class="min-w-100px">Data</th>
                                            <th class="min-w-100px text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notificacoes as $notif): ?>
                                            <tr class="<?= $notif['lida'] == 0 ? 'table-active' : '' ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($notif['lida'] == 0): ?>
                                                            <span class="bullet bullet-dot bg-success me-2"></span>
                                                        <?php endif; ?>
                                                        <span class="fw-bold text-gray-800"><?= htmlspecialchars($notif['titulo']) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-gray-700"><?= htmlspecialchars(substr($notif['mensagem'], 0, 100)) ?><?= strlen($notif['mensagem']) > 100 ? '...' : '' ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light-info"><?= htmlspecialchars($notif['tipo']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-muted fs-7"><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></span>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($notif['link']): ?>
                                                        <a href="<?= htmlspecialchars($notif['link']) ?>" class="btn btn-sm btn-light-primary" onclick="marcarNotificacaoLida(<?= $notif['id'] ?>)">
                                                            Ver
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($notif['lida'] == 0): ?>
                                                        <button class="btn btn-sm btn-light-success" onclick="marcarNotificacaoLida(<?= $notif['id'] ?>)">
                                                            Marcar como lida
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!--begin::Paginação-->
                            <?php if ($total > $limit): ?>
                                <div class="d-flex justify-content-between align-items-center mt-5">
                                    <div class="text-muted">
                                        Mostrando <?= $offset + 1 ?> a <?= min($offset + $limit, $total) ?> de <?= $total ?> notificações
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?tipo=<?= $filtro_tipo ?>&page=<?= $page - 1 ?>" class="btn btn-sm btn-light">Anterior</a>
                                        <?php endif; ?>
                                        <?php if ($offset + $limit < $total): ?>
                                            <a href="?tipo=<?= $filtro_tipo ?>&page=<?= $page + 1 ?>" class="btn btn-sm btn-light">Próxima</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!--end::Paginação-->
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->
            </div>
        </div>
    </div>
</div>
<!--end::Post-->

<script>
function marcarNotificacaoLida(notificacaoId) {
    const formData = new FormData();
    formData.append('notificacao_id', notificacaoId);
    
    fetch('../api/notificacoes/marcar_lida.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarrega a página para atualizar a lista
            window.location.reload();
        } else {
            Swal.fire({
                text: data.message || 'Erro ao marcar notificação como lida',
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            text: 'Erro ao marcar notificação como lida',
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

