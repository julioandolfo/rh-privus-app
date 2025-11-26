<?php
/**
 * Adicionar Nova Aula
 */

$page_title = 'Nova Aula';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_aula_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$curso_id = (int)($_GET['curso_id'] ?? 0);

if (!$curso_id) {
    redirect('lms_cursos.php', 'Curso não encontrado', 'error');
}

// Busca curso
$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    redirect('lms_cursos.php', 'Curso não encontrado', 'error');
}

// Valida acesso
if ($usuario['role'] === 'RH' && $curso['empresa_id']) {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        if (!in_array($curso['empresa_id'], $usuario['empresas_ids'])) {
            redirect('lms_cursos.php', 'Você não tem permissão para adicionar aulas neste curso', 'error');
        }
    }
}

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descricao = sanitize($_POST['descricao'] ?? '');
    $ordem = !empty($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
    $tipo_conteudo = $_POST['tipo_conteudo'] ?? 'video_youtube';
    $status = $_POST['status'] ?? 'rascunho';
    $duracao_minutos = !empty($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : null;
    $duracao_segundos = !empty($_POST['duracao_segundos']) ? (int)$_POST['duracao_segundos'] : null;
    
    // Campos específicos por tipo
    $url_youtube = null;
    $arquivo_video = null;
    $arquivo_pdf = null;
    $conteudo_texto = null;
    
    if ($tipo_conteudo === 'video_youtube') {
        $url_youtube = sanitize($_POST['url_youtube'] ?? '');
        if (empty($url_youtube)) {
            redirect('lms_aula_add.php?curso_id=' . $curso_id, 'Preencha a URL do YouTube!', 'error');
        }
    } elseif ($tipo_conteudo === 'video_upload') {
        if (isset($_FILES['arquivo_video']) && $_FILES['arquivo_video']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/lms/videos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = strtolower(pathinfo($_FILES['arquivo_video']['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4', 'webm', 'ogg'];
            
            if (in_array($ext, $allowed)) {
                $filename = uniqid('video_') . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['arquivo_video']['tmp_name'], $filepath)) {
                    $arquivo_video = 'uploads/lms/videos/' . $filename;
                }
            }
        }
        if (!$arquivo_video) {
            redirect('lms_aula_add.php?curso_id=' . $curso_id, 'Faça upload do vídeo!', 'error');
        }
    } elseif ($tipo_conteudo === 'pdf') {
        if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/lms/pdfs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = strtolower(pathinfo($_FILES['arquivo_pdf']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $filename = uniqid('pdf_') . '.pdf';
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $filepath)) {
                    $arquivo_pdf = 'uploads/lms/pdfs/' . $filename;
                }
            }
        }
        if (!$arquivo_pdf) {
            redirect('lms_aula_add.php?curso_id=' . $curso_id, 'Faça upload do PDF!', 'error');
        }
    } elseif ($tipo_conteudo === 'texto') {
        $conteudo_texto = $_POST['conteudo_texto'] ?? '';
        if (empty($conteudo_texto)) {
            redirect('lms_aula_add.php?curso_id=' . $curso_id, 'Preencha o conteúdo de texto!', 'error');
        }
    }
    
    if (empty($titulo)) {
        redirect('lms_aula_add.php?curso_id=' . $curso_id, 'Preencha o título da aula!', 'error');
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO aulas 
            (curso_id, titulo, descricao, ordem, tipo_conteudo, url_youtube, arquivo_video, arquivo_pdf, conteudo_texto, duracao_minutos, duracao_segundos, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $curso_id,
            $titulo,
            $descricao ?: null,
            $ordem,
            $tipo_conteudo,
            $url_youtube,
            $arquivo_video,
            $arquivo_pdf,
            $conteudo_texto,
            $duracao_minutos,
            $duracao_segundos,
            $status
        ]);
        
        $aula_id = $pdo->lastInsertId();
        redirect('lms_aulas.php?curso_id=' . $curso_id, 'Aula criada com sucesso!', 'success');
        
    } catch (PDOException $e) {
        error_log("Erro ao criar aula: " . $e->getMessage());
        redirect('lms_aula_add.php?curso_id=' . $curso_id, 'Erro ao criar aula. Tente novamente.', 'error');
    }
}

// Busca última ordem
$stmt = $pdo->prepare("SELECT MAX(ordem) as max_ordem FROM aulas WHERE curso_id = ?");
$stmt->execute([$curso_id]);
$ultima_ordem = $stmt->fetch()['max_ordem'] ?? 0;
$proxima_ordem = $ultima_ordem + 1;

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Nova Aula</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_cursos.php" class="text-muted text-hover-primary">Escola Privus</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_curso_view.php?id=<?= $curso_id ?>" class="text-muted text-hover-primary"><?= htmlspecialchars($curso['titulo']) ?></a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Nova Aula</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="lms_aulas.php?curso_id=<?= $curso_id ?>" class="btn btn-light">Voltar</a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="card">
            <div class="card-body pt-0">
                <form method="POST" enctype="multipart/form-data" class="row g-5">
                    
                    <div class="col-md-12">
                        <label class="form-label">Título da Aula *</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Ordem</label>
                        <input type="number" name="ordem" class="form-control" value="<?= $proxima_ordem ?>" min="1">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="rascunho">Rascunho</option>
                            <option value="publicado">Publicado</option>
                        </select>
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Tipo de Conteúdo *</label>
                        <select name="tipo_conteudo" class="form-select" id="tipoConteudo" required>
                            <option value="video_youtube">Vídeo YouTube</option>
                            <option value="video_upload">Vídeo Upload</option>
                            <option value="pdf">PDF</option>
                            <option value="texto">Texto</option>
                        </select>
                    </div>
                    
                    <!-- Campos por tipo -->
                    <div id="campoVideoYouTube" class="col-md-12">
                        <label class="form-label">URL do YouTube *</label>
                        <input type="text" name="url_youtube" class="form-control" placeholder="ID ou URL completa do vídeo">
                        <div class="form-text">Exemplo: dQw4w9WgXcQ ou https://www.youtube.com/watch?v=dQw4w9WgXcQ</div>
                    </div>
                    
                    <div id="campoVideoUpload" class="col-md-12" style="display: none;">
                        <label class="form-label">Arquivo de Vídeo *</label>
                        <input type="file" name="arquivo_video" class="form-control" accept="video/*">
                        <div class="form-text">Formatos aceitos: MP4, WEBM, OGG</div>
                    </div>
                    
                    <div id="campoPDF" class="col-md-12" style="display: none;">
                        <label class="form-label">Arquivo PDF *</label>
                        <input type="file" name="arquivo_pdf" class="form-control" accept=".pdf">
                    </div>
                    
                    <div id="campoTexto" class="col-md-12" style="display: none;">
                        <label class="form-label">Conteúdo de Texto *</label>
                        <textarea name="conteudo_texto" class="form-control" rows="10"></textarea>
                        <div class="form-text">Você pode usar HTML para formatar o conteúdo</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Duração (minutos)</label>
                        <input type="number" name="duracao_minutos" class="form-control" min="0">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Duração (segundos)</label>
                        <input type="number" name="duracao_segundos" class="form-control" min="0">
                    </div>
                    
                    <div class="col-12">
                        <hr class="my-5">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="lms_aulas.php?curso_id=<?= $curso_id ?>" class="btn btn-light">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar Aula</button>
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<script>
document.getElementById('tipoConteudo').addEventListener('change', function() {
    const tipo = this.value;
    
    // Esconde todos
    document.getElementById('campoVideoYouTube').style.display = 'none';
    document.getElementById('campoVideoUpload').style.display = 'none';
    document.getElementById('campoPDF').style.display = 'none';
    document.getElementById('campoTexto').style.display = 'none';
    
    // Remove required
    document.querySelector('[name="url_youtube"]').required = false;
    document.querySelector('[name="arquivo_video"]').required = false;
    document.querySelector('[name="arquivo_pdf"]').required = false;
    document.querySelector('[name="conteudo_texto"]').required = false;
    
    // Mostra e torna required o campo correto
    if (tipo === 'video_youtube') {
        document.getElementById('campoVideoYouTube').style.display = 'block';
        document.querySelector('[name="url_youtube"]').required = true;
    } else if (tipo === 'video_upload') {
        document.getElementById('campoVideoUpload').style.display = 'block';
        document.querySelector('[name="arquivo_video"]').required = true;
    } else if (tipo === 'pdf') {
        document.getElementById('campoPDF').style.display = 'block';
        document.querySelector('[name="arquivo_pdf"]').required = true;
    } else if (tipo === 'texto') {
        document.getElementById('campoTexto').style.display = 'block';
        document.querySelector('[name="conteudo_texto"]').required = true;
    }
});

// Inicializa campos ao carregar
document.getElementById('tipoConteudo').dispatchEvent(new Event('change'));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

