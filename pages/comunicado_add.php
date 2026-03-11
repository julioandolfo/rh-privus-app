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

// Carrega colaboradores ativos para o seletor de destinatários
$stmt_todos_colabs = $pdo->query("
    SELECT c.id, c.nome_completo,
           s.nome_setor  AS departamento,
           ca.nome_cargo AS cargo
    FROM colaboradores c
    LEFT JOIN setores s  ON s.id  = c.setor_id
    LEFT JOIN cargos  ca ON ca.id = c.cargo_id
    WHERE c.status = 'ativo'
    ORDER BY c.nome_completo
");
$todos_colaboradores = $stmt_todos_colabs->fetchAll();

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo          = trim($_POST['titulo'] ?? '');
    $conteudo        = $_POST['conteudo'] ?? '';
    $status          = $_POST['status'] ?? 'rascunho';
    $data_publicacao = !empty($_POST['data_publicacao']) ? $_POST['data_publicacao'] : null;
    $data_expiracao  = !empty($_POST['data_expiracao'])  ? $_POST['data_expiracao']  : null;

    // Destinatários: 'todos' ou array de IDs específicos
    $destinatarios_raw = $_POST['destinatarios'] ?? [];
    $enviar_para_todos = empty($destinatarios_raw)
        || in_array('todos', $destinatarios_raw, true)
        || (count($destinatarios_raw) === 1 && $destinatarios_raw[0] === '');

    $ids_selecionados = [];
    if (!$enviar_para_todos) {
        $ids_selecionados = array_values(array_filter(array_map('intval', $destinatarios_raw), fn($v) => $v > 0));
        if (empty($ids_selecionados)) {
            redirect('comunicado_add.php', 'Selecione ao menos um destinatário!', 'error');
        }
    }

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
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['imagem']['tmp_name']);
        finfo_close($finfo);

        if (in_array($mime_type, $allowed_types)) {
            $max_size = 5 * 1024 * 1024;
            if ($_FILES['imagem']['size'] <= $max_size) {
                $ext      = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                $filename = 'comunicado_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_dir . $filename)) {
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
        $stmt->execute([$titulo, $conteudo, $imagem, $usuario['id'], $status, $data_publicacao, $data_expiracao]);
        $comunicado_id = $pdo->lastInsertId();

        if ($status === 'publicado') {
            require_once __DIR__ . '/../includes/email_templates.php';
            require_once __DIR__ . '/../includes/push_notifications.php';
            require_once __DIR__ . '/../includes/evolution_service.php';
            require_once __DIR__ . '/../includes/slack_service.php';

            $url_comunicado = get_base_url() . '/pages/comunicado_view.php?id=' . $comunicado_id;
            $titulo_preview = mb_strlen($titulo) > 50 ? mb_substr($titulo, 0, 50) . '...' : $titulo;

            // ─── Monta lista de colaboradores para notificar ──────────────────
            if ($enviar_para_todos) {
                $stmt_colabs = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo'");
            } else {
                $ph          = implode(',', array_fill(0, count($ids_selecionados), '?'));
                $stmt_colabs = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo' AND id IN ($ph)");
                $stmt_colabs->execute($ids_selecionados);
            }
            $colaboradores_notif = $enviar_para_todos ? $stmt_colabs->fetchAll() : $stmt_colabs->fetchAll();

            // ─── Email ────────────────────────────────────────────────────────
            $ids_email       = $enviar_para_todos ? null : $ids_selecionados;
            $resultado_email = enviar_email_novo_comunicado($comunicado_id, $ids_email);

            // ─── Push + WhatsApp (fila em massa) + Slack DM ─────────────────
            $push_enviados   = 0;
            $wa_enfileirados = 0;

            foreach ($colaboradores_notif as $colab) {
                // WhatsApp via fila — comunicado é envio em massa; cron ou botão
                // "Processar Fila" no painel Evolution dispara as mensagens com rate limiting.
                try {
                    $stmt_wa = $pdo->prepare("
                        SELECT whatsapp_numero, whatsapp_ativo, nome_completo
                        FROM colaboradores WHERE id = ? AND status = 'ativo'
                    ");
                    $stmt_wa->execute([$colab['id']]);
                    $colab_wa = $stmt_wa->fetch();
                    if ($colab_wa && $colab_wa['whatsapp_ativo'] && !empty($colab_wa['whatsapp_numero'])) {
                        $nome_wa  = $colab_wa['nome_completo'];
                        $texto_wa = "👋 Olá, *{$nome_wa}*!\n\n*📢 Novo Comunicado*\n\n{$titulo_preview}\n\n🔗 Acesse: {$url_comunicado}\n\n_RH Privus_";
                        $enfileirou = evolution_enfileirar_mensagem(
                            $colab['id'],
                            evolution_normalizar_numero($colab_wa['whatsapp_numero']),
                            '📢 Novo Comunicado',
                            $texto_wa,
                            $url_comunicado,
                            'notificacao'
                        );
                        if ($enfileirou) $wa_enfileirados++;
                    }
                } catch (Exception $wa_e) {
                    error_log("[WA] Comunicado enfileirar colaborador {$colab['id']}: " . $wa_e->getMessage());
                }

                // Push + Slack DM
                try {
                    $resultado_push = enviar_push_colaborador(
                        $colab['id'],
                        'Novo Comunicado 📢',
                        $titulo_preview,
                        $url_comunicado,
                        'comunicado',
                        $comunicado_id,
                        'comunicado'
                        // WA não duplica pois enviar_push_colaborador chama evolution_notificar_colaborador
                        // que envia direto — mas aqui já enfileiramos acima, então passamos false
                        , false
                    );
                    if (!empty($resultado_push['success'])) $push_enviados++;
                } catch (Exception $e) {
                    error_log("Erro push comunicado colaborador {$colab['id']}: " . $e->getMessage());
                }
            }

            // ─── Slack canal (broadcast — só quando for para todos) ───────────
            if ($enviar_para_todos) {
                try {
                    $preview_texto = mb_substr(strip_tags($conteudo), 0, 300);
                    slack_comunicado_no_canal(
                        '📢 Novo Comunicado: ' . $titulo_preview,
                        $preview_texto,
                        $url_comunicado
                    );
                } catch (Exception $sl_e) {
                    error_log('[Slack] Erro ao postar comunicado no canal: ' . $sl_e->getMessage());
                }
            }

            // ─── Mensagem de retorno ──────────────────────────────────────────
            $total_dest = count($colaboradores_notif);
            if ($resultado_email['success']) {
                $msg  = "Comunicado publicado! {$total_dest} destinatário(s): ";
                $msg .= "{$resultado_email['enviados']} e-mail(s)";
                $msg .= ", {$push_enviados} push";
                $msg .= ", {$wa_enfileirados} WA na fila (processar em Configurações → WhatsApp)";
                if ($resultado_email['erros'] > 0) {
                    $msg .= " ({$resultado_email['erros']} erros de e-mail)";
                }
                redirect('comunicados.php', $msg, 'success');
            } else {
                redirect('comunicados.php', 'Comunicado criado mas houve erro ao enviar e-mails: ' . $resultado_email['message'], 'warning');
            }
        } else {
            redirect('comunicados.php', 'Comunicado salvo como rascunho!', 'success');
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

            <!--begin::Card Destinatários-->
            <div class="card mb-5">
                <div class="card-header border-0 pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Destinatários</span>
                        <span class="text-muted fw-semibold fs-7">Defina quem receberá este comunicado (push, e-mail e WhatsApp)</span>
                    </h3>
                </div>
                <div class="card-body pt-4">

                    <!--begin::Tipo-->
                    <div class="d-flex gap-4 mb-6">
                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                            <input type="radio" name="tipo_destinatario" id="radio_todos" value="todos" class="form-check-input" checked>
                            <span class="fw-semibold fs-6">Todos os colaboradores ativos</span>
                        </label>
                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                            <input type="radio" name="tipo_destinatario" id="radio_especificos" value="especificos" class="form-check-input">
                            <span class="fw-semibold fs-6">Colaboradores específicos</span>
                        </label>
                    </div>
                    <!--end::Tipo-->

                    <!--begin::Seletor específico (oculto por padrão)-->
                    <div id="bloco_especificos" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <label class="form-label mb-0 fw-semibold">Selecione os colaboradores</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light-primary" id="btn_selecionar_todos">Marcar todos</button>
                                <button type="button" class="btn btn-sm btn-light-danger" id="btn_limpar_todos">Limpar</button>
                            </div>
                        </div>
                        <select id="select_destinatarios" name="destinatarios[]" multiple class="form-select" data-placeholder="Buscar colaborador...">
                            <?php foreach ($todos_colaboradores as $colab): ?>
                                <option value="<?= $colab['id'] ?>"
                                    data-dept="<?= htmlspecialchars($colab['departamento'] ?? '') ?>"
                                    data-cargo="<?= htmlspecialchars($colab['cargo'] ?? '') ?>">
                                    <?= htmlspecialchars($colab['nome_completo']) ?>
                                    <?php if (!empty($colab['departamento'])): ?>
                                        — <?= htmlspecialchars($colab['departamento']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text mt-2">
                            <span id="label_qtd_selecionados" class="badge badge-light-primary me-2">0 selecionado(s)</span>
                            de <?= count($todos_colaboradores) ?> colaboradores ativos
                        </div>
                    </div>
                    <!--end::Seletor específico-->

                    <!--begin::Info todos-->
                    <div id="bloco_todos_info" class="alert alert-light-primary d-flex align-items-center">
                        <i class="ki-duotone ki-people fs-2 text-primary me-3">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                            <span class="path4"></span><span class="path5"></span>
                        </i>
                        <div>
                            <strong><?= count($todos_colaboradores) ?> colaboradores</strong> receberão este comunicado via push, e-mail e WhatsApp.
                        </div>
                    </div>
                    <!--end::Info todos-->

                </div>
            </div>
            <!--end::Card Destinatários-->

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

<!--begin::Select2 Destinatários-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const radioTodos      = document.getElementById('radio_todos');
    const radioEspecificos = document.getElementById('radio_especificos');
    const blocoEspecificos = document.getElementById('bloco_especificos');
    const blocoTodosInfo   = document.getElementById('bloco_todos_info');
    const selectDest       = document.getElementById('select_destinatarios');
    const labelQtd         = document.getElementById('label_qtd_selecionados');
    const btnMarcarTodos   = document.getElementById('btn_selecionar_todos');
    const btnLimpar        = document.getElementById('btn_limpar_todos');

    // Inicializa Select2 se disponível
    let select2Ativo = false;
    if (typeof $ !== 'undefined' && $.fn && $.fn.select2) {
        $(selectDest).select2({
            placeholder: 'Buscar colaborador pelo nome ou departamento...',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() { return 'Nenhum colaborador encontrado'; },
                searching: function() { return 'Buscando...'; }
            }
        });
        $(selectDest).on('change', atualizarContador);
        select2Ativo = true;
    } else {
        selectDest.addEventListener('change', atualizarContador);
    }

    function atualizarContador() {
        const qtd = Array.from(selectDest.selectedOptions).length;
        labelQtd.textContent = qtd + ' selecionado(s)';
    }

    function alternarModo() {
        if (radioEspecificos.checked) {
            blocoEspecificos.classList.remove('d-none');
            blocoTodosInfo.classList.add('d-none');
        } else {
            blocoEspecificos.classList.add('d-none');
            blocoTodosInfo.classList.remove('d-none');
            // Limpa seleção ao voltar para "todos"
            if (select2Ativo) {
                $(selectDest).val(null).trigger('change');
            } else {
                Array.from(selectDest.options).forEach(o => o.selected = false);
            }
            atualizarContador();
        }
    }

    radioTodos.addEventListener('change', alternarModo);
    radioEspecificos.addEventListener('change', alternarModo);

    btnMarcarTodos.addEventListener('click', function () {
        const allIds = Array.from(selectDest.options).map(o => o.value);
        if (select2Ativo) {
            $(selectDest).val(allIds).trigger('change');
        } else {
            Array.from(selectDest.options).forEach(o => o.selected = true);
        }
        atualizarContador();
    });

    btnLimpar.addEventListener('click', function () {
        if (select2Ativo) {
            $(selectDest).val(null).trigger('change');
        } else {
            Array.from(selectDest.options).forEach(o => o.selected = false);
        }
        atualizarContador();
    });

    // Ao submeter: se "todos", adiciona um input hidden para garantir o valor correto
    document.getElementById('form_comunicado').addEventListener('submit', function (e) {
        if (radioTodos.checked) {
            // Garante que nenhum destinatário específico seja enviado (modo todos)
            if (select2Ativo) {
                $(selectDest).val(null);
            } else {
                Array.from(selectDest.options).forEach(o => o.selected = false);
            }
            // Sinaliza "todos" via campo hidden
            let hidden = document.getElementById('hidden_todos');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type  = 'hidden';
                hidden.name  = 'destinatarios[]';
                hidden.id    = 'hidden_todos';
                hidden.value = 'todos';
                this.appendChild(hidden);
            }
        }
    }, true); // captura antes do handler do TinyMCE
});
</script>
<!--end::Select2 Destinatários-->

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

