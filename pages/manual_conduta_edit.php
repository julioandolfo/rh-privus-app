<?php
/**
 * Edição do Manual de Conduta
 */

$page_title = 'Editar Manual de Conduta';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/manual_conduta_functions.php';

require_page_permission('manual_conduta_edit.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca manual existente ou cria novo
$manual = get_manual_conduta_ativo();
$manual_id = $manual['id'] ?? null;

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $conteudo = $_POST['conteudo'] ?? '';
    $versao = trim($_POST['versao'] ?? '');
    $motivo_alteracao = trim($_POST['motivo_alteracao'] ?? '');
    $publicar = isset($_POST['publicar']) && $_POST['publicar'] === '1';
    
    if (empty($titulo) || empty($conteudo)) {
        redirect('manual_conduta_edit.php', 'Preencha todos os campos obrigatórios!', 'error');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Se não existe manual, cria novo
        if (!$manual_id) {
            $stmt = $pdo->prepare("
                INSERT INTO manual_conduta (titulo, conteudo, versao, ativo, criado_por, publicado_em, publicado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $publicado_em = $publicar ? date('Y-m-d H:i:s') : null;
            $publicado_por = $publicar ? $usuario['id'] : null;
            
            $stmt->execute([
                $titulo,
                $conteudo,
                $versao ?: '1.0',
                $publicar ? 1 : 0,
                $usuario['id'],
                $publicado_em,
                $publicado_por
            ]);
            
            $manual_id = $pdo->lastInsertId();
            
            // Registra histórico
            registrar_historico_manual($manual_id, '', $conteudo, $versao ?: '1.0', $motivo_alteracao);
        } else {
            // Atualiza manual existente
            $conteudo_anterior = $manual['conteudo'];
            $versao_anterior = $manual['versao'];
            
            // Calcula nova versão se não informada
            $nova_versao = $versao ?: calcular_proxima_versao($versao_anterior);
            
            $stmt = $pdo->prepare("
                UPDATE manual_conduta 
                SET titulo = ?, conteudo = ?, versao = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $conteudo, $nova_versao, $manual_id]);
            
            // Se está publicando, atualiza dados de publicação
            if ($publicar && !$manual['publicado_em']) {
                $stmt = $pdo->prepare("
                    UPDATE manual_conduta 
                    SET ativo = 1, publicado_em = NOW(), publicado_por = ?
                    WHERE id = ?
                ");
                $stmt->execute([$usuario['id'], $manual_id]);
            }
            
            // Registra histórico apenas se houve alteração
            if ($conteudo_anterior !== $conteudo || $versao_anterior !== $nova_versao) {
                registrar_historico_manual($manual_id, $conteudo_anterior, $conteudo, $nova_versao, $motivo_alteracao);
            }
        }
        
        $pdo->commit();
        redirect('manual_conduta_view.php', 'Manual salvo com sucesso!', 'success');
    } catch (PDOException $e) {
        $pdo->rollBack();
        redirect('manual_conduta_edit.php', 'Erro ao salvar manual: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Editar Manual de Conduta</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="manual_conduta_view.php" class="text-muted text-hover-primary">Manual de Conduta</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Editar</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="manual_conduta_view.php" class="btn btn-light">Cancelar</a>
            <a href="manual_conduta_estatisticas.php" class="btn btn-light-primary">
                <i class="ki-duotone ki-chart-simple fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Estatísticas
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <form method="POST" id="form_manual">
            <!--begin::Card-->
            <div class="card mb-5">
                <div class="card-header border-0 pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Informações do Manual</span>
                    </h3>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-10">
                        <label class="form-label required">Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($manual['titulo'] ?? 'Manual de Conduta Privus') ?>" required />
                    </div>
                    
                    <div class="row mb-10">
                        <div class="col-md-6">
                            <label class="form-label">Versão</label>
                            <input type="text" name="versao" id="versao" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($manual['versao'] ?? '') ?>" 
                                   placeholder="Ex: 1.0, 2.1" />
                            <div class="form-text">Deixe em branco para incrementar automaticamente</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="publicar" id="publicar" value="1" 
                                       <?= ($manual['ativo'] ?? false) ? 'checked' : '' ?> />
                                <label class="form-check-label" for="publicar">
                                    Publicar manual (tornar visível para todos)
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-10">
                        <label class="form-label required">Conteúdo</label>
                        <textarea id="conteudo" name="conteudo" class="form-control" rows="20" required><?= htmlspecialchars($manual['conteudo'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-10">
                        <label class="form-label">Motivo da Alteração (opcional)</label>
                        <textarea name="motivo_alteracao" class="form-control form-control-solid" rows="3" 
                                  placeholder="Descreva o motivo desta alteração para o histórico..."></textarea>
                    </div>
                </div>
                <div class="card-footer border-0 pt-0">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="manual_conduta_view.php" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-duotone ki-check fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Salvar Manual
                        </button>
                    </div>
                </div>
            </div>
            <!--end::Card-->
        </form>
        
    </div>
</div>
<!--end::Post-->

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
            }
        });
    }
    
    // Inicializa TinyMCE após carregar
    setTimeout(initTinyMCE, 500);
    
    // Salva conteúdo do TinyMCE antes de submeter
    document.getElementById('form_manual').addEventListener('submit', function(e) {
        if (typeof tinymce !== 'undefined' && tinymce.get('conteudo')) {
            tinymce.get('conteudo').save();
        }
    });
});
</script>
<!--end::TinyMCE-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

