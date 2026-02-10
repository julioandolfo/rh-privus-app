<?php
/**
 * Adicionar Novo Comunicado
 */

$page_title = 'Adicionar Comunicado';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('comunicado_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $conteudo = $_POST['conteudo'] ?? '';
    $status = $_POST['status'] ?? 'rascunho';
    $data_publicacao = !empty($_POST['data_publicacao']) ? $_POST['data_publicacao'] : null;
    $data_expiracao = !empty($_POST['data_expiracao']) ? $_POST['data_expiracao'] : null;
    
    if (empty($titulo) || empty($conteudo)) {
        redirect('comunicado_add.php', 'Preencha todos os campos obrigatórios!', 'error');
    }
    
    // Processa upload de imagem
    $imagem = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/comunicados/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['imagem']['tmp_name']);
        finfo_close($finfo);
        
        if (in_array($mime_type, $allowed_types)) {
            $max_size = 5 * 1024 * 1024; // 5MB
            if ($_FILES['imagem']['size'] <= $max_size) {
                $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                $filename = 'comunicado_' . time() . '_' . uniqid() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $filepath)) {
                    $imagem = 'uploads/comunicados/' . $filename;
                }
            }
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO comunicados (titulo, conteudo, imagem, criado_por_usuario_id, status, data_publicacao, data_expiracao)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $titulo,
            $conteudo,
            $imagem,
            $usuario['id'],
            $status,
            $data_publicacao,
            $data_expiracao
        ]);
        
        $comunicado_id = $pdo->lastInsertId();
        
        // Se o comunicado foi publicado, envia emails e push para todos os colaboradores
        if ($status === 'publicado') {
            require_once __DIR__ . '/../includes/email_templates.php';
            require_once __DIR__ . '/../includes/push_notifications.php';
            
            // Envia emails em background (não bloqueia a resposta)
            $resultado_email = enviar_email_novo_comunicado($comunicado_id);
            
            // Envia push notifications para todos colaboradores ativos
            $stmt_colabs = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo'");
            $colaboradores = $stmt_colabs->fetchAll();
            
            $push_enviados = 0;
            $titulo_preview = strlen($titulo) > 50 ? substr($titulo, 0, 50) . '...' : $titulo;
            
            foreach ($colaboradores as $colab) {
                try {
                    $resultado_push = enviar_push_colaborador(
                        $colab['id'],
                        'Novo Comunicado 📢',
                        $titulo_preview,
                        'pages/comunicado_view.php?id=' . $comunicado_id,
                        'comunicado',
                        $comunicado_id,
                        'comunicado'
                    );
                    if ($resultado_push['success']) {
                        $push_enviados++;
                    }
                } catch (Exception $e) {
                    error_log("Erro ao enviar push para colaborador {$colab['id']}: " . $e->getMessage());
                }
            }
            
            if ($resultado_email['success']) {
                $mensagem = 'Comunicado criado e notificações enviadas! ';
                $mensagem .= "({$resultado_email['enviados']} emails";
                if ($resultado_email['erros'] > 0) {
                    $mensagem .= ", {$resultado_email['erros']} erros";
                }
                $mensagem .= ", {$push_enviados} push)";
                redirect('comunicados.php', $mensagem, 'success');
            } else {
                redirect('comunicados.php', 'Comunicado criado mas houve erro ao enviar emails: ' . $resultado_email['message'], 'warning');
            }
        } else {
            redirect('comunicados.php', 'Comunicado criado com sucesso!', 'success');
        }
    } catch (PDOException $e) {
        redirect('comunicado_add.php', 'Erro ao criar comunicado: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Adicionar Comunicado</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">Comunicados</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Adicionar</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <form method="POST" enctype="multipart/form-data" id="form_comunicado">
            <!--begin::Card-->
            <div class="card mb-5">
                <div class="card-header border-0 pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Informações do Comunicado</span>
                    </h3>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-10">
                        <label class="form-label required">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-solid" required />
                    </div>
                    
                    <div class="mb-10">
                        <label class="form-label required">Conteúdo</label>
                        <textarea id="conteudo" name="conteudo" class="form-control" rows="15" required></textarea>
                    </div>
                    
                    <div class="mb-10">
                        <label class="form-label">Imagem (opcional)</label>
                        <input type="file" name="imagem" class="form-control form-control-solid" accept="image/*" />
                        <div class="form-text">Formatos aceitos: JPG, PNG, GIF, WEBP. Tamanho máximo: 5MB</div>
                    </div>
                    
                    <div class="row mb-10">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-solid">
                                <option value="rascunho">Rascunho</option>
                                <option value="publicado">Publicado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data de Publicação</label>
                            <input type="datetime-local" name="data_publicacao" id="data_publicacao" class="form-control form-control-solid" />
                        </div>
                    </div>
                    
                    <div class="mb-10">
                        <label class="form-label">Data de Expiração (opcional)</label>
                        <input type="datetime-local" name="data_expiracao" class="form-control form-control-solid" />
                    </div>
                </div>
            </div>
            <!--end::Card-->
            
            <!--begin::Actions-->
            <div class="card">
                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <a href="comunicados.php" class="btn btn-light btn-active-light-primary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Comunicado</span>
                        <span class="indicator-progress">Salvando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </div>
            <!--end::Actions-->
        </form>
        
    </div>
</div>
<!--end::Post-->

<!--begin::Script para preencher data/hora-->
<script>
(function() {
    function preencherDataPublicacao() {
        const dataPublicacaoField = document.getElementById('data_publicacao');
        if (dataPublicacaoField && !dataPublicacaoField.value) {
            const now = new Date();
            // Formata para datetime-local (YYYY-MM-DDTHH:mm)
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const datetimeLocal = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
            dataPublicacaoField.value = datetimeLocal;
            return true;
        }
        return false;
    }
    
    // Tenta preencher quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            preencherDataPublicacao();
            setTimeout(preencherDataPublicacao, 100);
            setTimeout(preencherDataPublicacao, 500);
        });
    } else {
        // DOM já está pronto
        preencherDataPublicacao();
        setTimeout(preencherDataPublicacao, 100);
        setTimeout(preencherDataPublicacao, 500);
    }
})();
</script>
<!--end::Script para preencher data/hora-->

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
        
        // Configura base_url e suffix para usar os arquivos diretamente
        const baseUrl = '../assets/plugins/custom/tinymce';
        
        // Inicializa TinyMCE
        tinymce.init({
            selector: '#' + editorId,
            height: 500,
            menubar: true,
            base_url: baseUrl,
            suffix: '.min',
            license_key: 'gpl', // Usa licença GPL (open source) - remove restrições de licença
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | ' +
                'bold italic forecolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help | image | link | code',
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
            language: 'pt_BR',
            promotion: false,
            branding: false,
            skin: 'oxide',
            content_css: baseUrl + '/skins/ui/oxide/content.min.css',
            images_upload_url: 'api/comunicados/upload_imagem.php',
            automatic_uploads: true,
            file_picker_types: 'image',
            images_upload_handler: function (blobInfo, progress) {
                return new Promise(function (resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'api/comunicados/upload_imagem.php');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    
                    xhr.upload.onprogress = function (e) {
                        progress(e.loaded / e.total * 100);
                    };
                    
                    xhr.onload = function () {
                        if (xhr.status === 403) {
                            reject({ message: 'HTTP Error: ' + xhr.status, remove: true });
                            return;
                        }
                        
                        if (xhr.status < 200 || xhr.status >= 300) {
                            reject('HTTP Error: ' + xhr.status);
                            return;
                        }
                        
                        var json = JSON.parse(xhr.responseText);
                        
                        if (!json || typeof json.location != 'string') {
                            reject('Invalid JSON: ' + xhr.responseText);
                            return;
                        }
                        
                        resolve(json.location);
                    };
                    
                    xhr.onerror = function () {
                        reject('Image upload failed due to a XHR Transport error. Code: ' + xhr.status);
                    };
                    
                    var formData = new FormData();
                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    
                    xhr.send(formData);
                });
            },
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save();
                });
            }
        });
    }
    
    // Inicializa TinyMCE após carregar
    setTimeout(initTinyMCE, 500);
    
    // Submit com loading
    document.getElementById('form_comunicado')?.addEventListener('submit', function(e) {
        // Salva conteúdo do TinyMCE antes de enviar
        if (typeof tinymce !== 'undefined' && tinymce.get('conteudo')) {
            tinymce.get('conteudo').save();
        }
        
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
        }
    });
});
</script>
<!--end::TinyMCE-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

