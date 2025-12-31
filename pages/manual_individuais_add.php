<?php
/**
 * Adicionar Manual Individual
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('manual_individuais_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca colaboradores disponíveis (baseado no role)
$where_colab = [];
$params_colab = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where_colab[] = "c.empresa_id IN ($placeholders)";
        $params_colab = array_merge($params_colab, $usuario['empresas_ids']);
    } else {
        $where_colab[] = "c.empresa_id = ?";
        $params_colab[] = $usuario['empresa_id'] ?? 0;
    }
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt_setor->execute([$usuario['id']]);
    $user_data = $stmt_setor->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    $where_colab[] = "c.setor_id = ?";
    $params_colab[] = $setor_id;
}

$where_colab[] = "c.status = 'ativo'";
$where_sql_colab = !empty($where_colab) ? 'WHERE ' . implode(' AND ', $where_colab) : 'WHERE c.status = \'ativo\'';

$sql_colab = "
    SELECT c.id, c.nome_completo, c.cpf, e.nome_fantasia as empresa_nome, s.nome_setor
    FROM colaboradores c
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN setores s ON c.setor_id = s.id
    $where_sql_colab
    ORDER BY c.nome_completo
";

$stmt_colab = $pdo->prepare($sql_colab);
$stmt_colab->execute($params_colab);
$colaboradores = $stmt_colab->fetchAll();

$page_title = 'Novo Manual Individual';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Novo Manual Individual</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="manuais_individuais.php" class="text-muted text-hover-primary">Manuais Individuais</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Novo</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-body">
                <form id="form_manual" method="POST">
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="form-label required">Título</label>
                            <input type="text" name="titulo" class="form-control form-control-solid" required>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control form-control-solid" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="form-label required">Conteúdo</label>
                            <textarea name="conteudo" id="conteudo" class="form-control form-control-solid" rows="15" required></textarea>
                            <div class="form-text">Você pode usar HTML para formatar o conteúdo. Inclua informações como acessos, senhas, funções específicas, etc.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-solid">
                                <option value="ativo" selected>Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="form-label">Colaboradores com Acesso</label>
                            <div class="border rounded p-4" style="max-height: 400px; overflow-y: auto;">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="selecionar_todos">
                                    <label class="form-check-label fw-bold" for="selecionar_todos">
                                        Selecionar Todos
                                    </label>
                                </div>
                                <hr>
                                <?php foreach ($colaboradores as $colab): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input colaborador-checkbox" type="checkbox" name="colaboradores_ids[]" value="<?= $colab['id'] ?>" id="colab_<?= $colab['id'] ?>">
                                    <label class="form-check-label" for="colab_<?= $colab['id'] ?>">
                                        <strong><?= htmlspecialchars($colab['nome_completo']) ?></strong>
                                        <?php if ($colab['nome_setor']): ?>
                                        <span class="text-muted"> - <?= htmlspecialchars($colab['nome_setor']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($usuario['role'] === 'ADMIN' && $colab['empresa_nome']): ?>
                                        <span class="text-muted"> - <?= htmlspecialchars($colab['empresa_nome']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="manuais_individuais.php" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Salvar Manual</button>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<script>
document.getElementById('selecionar_todos').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.colaborador-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

document.getElementById('form_manual').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    Swal.fire({
        title: 'Salvando...',
        text: 'Por favor, aguarde.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('../api/manuais_individuais/salvar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                text: data.message,
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            }).then(() => {
                window.location.href = 'manuais_individuais.php';
            });
        } else {
            Swal.fire({
                text: data.message || 'Erro ao salvar manual',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        }
    })
    .catch(error => {
        Swal.fire({
            text: 'Erro ao salvar manual: ' + error.message,
            icon: 'error',
            buttonsStyling: false,
            confirmButtonText: 'Ok',
            customClass: {
                confirmButton: 'btn btn-primary'
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
