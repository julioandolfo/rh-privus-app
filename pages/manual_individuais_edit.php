<?php
/**
 * Editar Manual Individual
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('manual_individuais_edit.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$id = $_GET['id'] ?? 0;

// Busca manual
$stmt = $pdo->prepare("SELECT * FROM manuais_individuais WHERE id = ?");
$stmt->execute([$id]);
$manual = $stmt->fetch();

if (!$manual) {
    redirect('manuais_individuais.php', 'Manual não encontrado!', 'error');
}

// Busca colaboradores vinculados
$stmt = $pdo->prepare("SELECT colaborador_id FROM manuais_individuais_colaboradores WHERE manual_id = ?");
$stmt->execute([$id]);
$colaboradores_vinculados = $stmt->fetchAll(PDO::FETCH_COLUMN);

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

$page_title = 'Editar Manual Individual';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Editar Manual Individual</h1>
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
                <li class="breadcrumb-item text-gray-900">Editar</li>
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
                    <input type="hidden" name="manual_id" value="<?= $manual['id'] ?>">
                    
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="form-label required">Título</label>
                            <input type="text" name="titulo" class="form-control form-control-solid" value="<?= htmlspecialchars($manual['titulo']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control form-control-solid" rows="3"><?= htmlspecialchars($manual['descricao'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <label class="form-label required">Conteúdo</label>
                            <textarea name="conteudo" id="conteudo" class="form-control form-control-solid" rows="15" required><?= htmlspecialchars($manual['conteudo']) ?></textarea>
                            <div class="form-text">Use o editor abaixo para formatar o conteúdo. Inclua informações como acessos, senhas, funções específicas, etc.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-solid">
                                <option value="ativo" <?= $manual['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inativo" <?= $manual['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
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
                                    <input class="form-check-input colaborador-checkbox" type="checkbox" name="colaboradores_ids[]" value="<?= $colab['id'] ?>" id="colab_<?= $colab['id'] ?>" <?= in_array($colab['id'], $colaboradores_vinculados) ? 'checked' : '' ?>>
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
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
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

// Atualiza checkbox "selecionar todos" baseado nos checkboxes individuais
document.querySelectorAll('.colaborador-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const allChecked = Array.from(document.querySelectorAll('.colaborador-checkbox')).every(c => c.checked);
        document.getElementById('selecionar_todos').checked = allChecked;
    });
});

// Verifica estado inicial
const allChecked = Array.from(document.querySelectorAll('.colaborador-checkbox')).every(c => c.checked);
document.getElementById('selecionar_todos').checked = allChecked;

document.getElementById('form_manual').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Salva conteúdo do TinyMCE antes de submeter
    if (typeof tinymce !== 'undefined' && tinymce.get('conteudo')) {
        tinymce.get('conteudo').save();
    }
    
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

<!--begin::TinyMCE-->
<script src="../assets/plugins/custom/tinymce/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa TinyMCE
    function initTinyMCE() {
        // Verifica se TinyMCE está disponível
        if (typeof tinymce === 'undefined') {
            console.warn('TinyMCE não está carregado. Tentando novamente...');
            setTimeout(initTinyMCE, 200);
            return;
        }
        
        // Remove editor existente se houver
        const editorId = 'conteudo';
        if (tinymce.get(editorId)) {
            tinymce.get(editorId).remove();
        }
        
        // Obtém conteúdo do textarea antes de inicializar
        const textarea = document.getElementById(editorId);
        const content = textarea ? textarea.value : '';
        
        // Configura base_url e suffix para usar os arquivos diretamente
        const baseUrl = '../assets/plugins/custom/tinymce';
        
        // Inicializa TinyMCE
        tinymce.init({
            selector: '#' + editorId,
            height: 600,
            menubar: true,
            base_url: baseUrl,
            suffix: '.min',
            license_key: 'gpl',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | ' +
                'bold italic forecolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | link image | help | code',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
            language: 'pt_BR',
            promotion: false,
            branding: false,
            skin: 'oxide',
            content_css: baseUrl + '/skins/ui/oxide/content.min.css',
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save();
                });
            },
            init_instance_callback: function(editor) {
                // Garante que o conteúdo seja carregado após inicialização
                if (content) {
                    editor.setContent(content);
                }
            }
        });
    }
    
    // Inicializa TinyMCE após carregar
    setTimeout(initTinyMCE, 500);
});
</script>
<!--end::TinyMCE-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
