<?php
/**
 * Portal do Colaborador - Player de Aula
 */

$page_title = 'Player de Aula';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_player_aula.php');

require_once __DIR__ . '/../includes/lms_functions.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Colaborador não encontrado', 'error');
}

$curso_id = (int)($_GET['curso_id'] ?? 0);
$aula_id = (int)($_GET['aula_id'] ?? 0);

if ($curso_id <= 0 || $aula_id <= 0) {
    redirect('lms_meus_cursos.php', 'Parâmetros inválidos', 'error');
}

// Busca dados da aula
$stmt = $pdo->prepare("
    SELECT a.*, c.titulo as curso_titulo
    FROM aulas a
    INNER JOIN cursos c ON c.id = a.curso_id
    WHERE a.id = ? AND a.curso_id = ?
");
$stmt->execute([$aula_id, $curso_id]);
$aula = $stmt->fetch();

if (!$aula) {
    redirect('lms_meus_cursos.php', 'Aula não encontrada', 'error');
}

// Verifica se pode acessar
if (!function_exists('pode_acessar_curso')) {
    require_once __DIR__ . '/../includes/lms_functions.php';
}

if (!pode_acessar_curso($colaborador_id, $curso_id)) {
    redirect('lms_meus_cursos.php', 'Você não tem permissão para acessar este curso', 'error');
}

// Inicia progresso
if (!function_exists('iniciar_progresso_aula') || !function_exists('criar_sessao_aula')) {
    require_once __DIR__ . '/../includes/lms_functions.php';
}

try {
    $progresso_id = iniciar_progresso_aula($colaborador_id, $curso_id, $aula_id);
    $sessao_id = criar_sessao_aula($progresso_id, $colaborador_id, $aula_id, $curso_id);
} catch (Exception $e) {
    error_log("Erro ao iniciar progresso: " . $e->getMessage());
    redirect('lms_meus_cursos.php', 'Erro ao iniciar aula. Tente novamente.', 'error');
}

// Busca progresso atual
$stmt = $pdo->prepare("SELECT * FROM progresso_colaborador WHERE id = ?");
$stmt->execute([$progresso_id]);
$progresso = $stmt->fetch();

// Busca todas as aulas do curso para navegação
$stmt = $pdo->prepare("
    SELECT a.id, a.titulo, a.ordem,
           pc.status as status_progresso
    FROM aulas a
    LEFT JOIN progresso_colaborador pc ON pc.aula_id = a.id AND pc.colaborador_id = ?
    WHERE a.curso_id = ? AND a.status = 'publicado'
    ORDER BY a.ordem ASC, a.id ASC
");
$stmt->execute([$colaborador_id, $curso_id]);
$todas_aulas = $stmt->fetchAll();

// Encontra aula atual e próxima/anterior
$aula_atual_index = null;
foreach ($todas_aulas as $index => $a) {
    if ($a['id'] == $aula_id) {
        $aula_atual_index = $index;
        break;
    }
}

$aula_anterior = $aula_atual_index > 0 ? $todas_aulas[$aula_atual_index - 1] : null;
$aula_proxima = $aula_atual_index < count($todas_aulas) - 1 ? $todas_aulas[$aula_atual_index + 1] : null;

// Busca campos personalizados se for aula de texto
$campos_personalizados = [];
if ($aula['tipo_conteudo'] == 'texto') {
    $stmt = $pdo->prepare("
        SELECT * FROM campos_personalizados_aula 
        WHERE aula_id = ? 
        ORDER BY ordem ASC
    ");
    $stmt->execute([$aula_id]);
    $campos_personalizados = $stmt->fetchAll();
}

// Atualiza título com nome da aula
if (!empty($aula['titulo'])) {
    $page_title = $aula['titulo'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0"><?= htmlspecialchars($aula['titulo']) ?></h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_meus_cursos.php" class="text-muted text-hover-primary">Meus Cursos</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_curso_detalhes.php?id=<?= $curso_id ?>" class="text-muted text-hover-primary"><?= htmlspecialchars($aula['curso_titulo']) ?></a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900"><?= htmlspecialchars($aula['titulo']) ?></li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="row">
            <!--begin::Col Principal-->
            <div class="col-lg-9">
                <!--begin::Card Player-->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title"><?= htmlspecialchars($aula['titulo']) ?></h3>
                        <?php if ($aula['descricao']): ?>
                        <div class="card-toolbar">
                            <p class="text-muted mb-0"><?= htmlspecialchars($aula['descricao']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!--begin::Player Container-->
                        <div id="lms-player-container" class="mb-5">
                            <?php if ($aula['tipo_conteudo'] == 'video_youtube'): ?>
                            <!-- Player YouTube -->
                            <div class="ratio ratio-16x9">
                                <iframe 
                                    id="youtube-player"
                                    src="https://www.youtube.com/embed/<?= htmlspecialchars($aula['url_youtube']) ?>?enablejsapi=1&origin=<?= urlencode(get_base_url()) ?>"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen>
                                </iframe>
                            </div>
                            
                            <?php elseif ($aula['tipo_conteudo'] == 'video_upload'): ?>
                            <!-- Player Vídeo Upload -->
                            <video 
                                id="video-player"
                                class="w-100"
                                controls
                                data-progresso-id="<?= $progresso_id ?>"
                                data-sessao-id="<?= $sessao_id ?>"
                                data-aula-id="<?= $aula_id ?>"
                                data-curso-id="<?= $curso_id ?>">
                                <source src="<?= htmlspecialchars($aula['arquivo_video']) ?>" type="video/mp4">
                                Seu navegador não suporta o elemento de vídeo.
                            </video>
                            
                            <?php elseif ($aula['tipo_conteudo'] == 'pdf'): ?>
                            <!-- Visualizador PDF -->
                            <div id="pdf-viewer" class="border rounded" style="height: 600px;">
                                <iframe 
                                    src="<?= htmlspecialchars($aula['arquivo_pdf']) ?>#toolbar=1&navpanes=0&scrollbar=1"
                                    class="w-100 h-100"
                                    frameborder="0">
                                </iframe>
                            </div>
                            
                            <?php elseif ($aula['tipo_conteudo'] == 'texto'): ?>
                            <!-- Conteúdo de Texto -->
                            <div id="texto-content" class="p-5">
                                <?php if (!empty($aula['conteudo_texto'])): ?>
                                <div class="content-texto">
                                    <?= $aula['conteudo_texto'] ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($campos_personalizados)): ?>
                                <div class="mt-5">
                                    <h4 class="mb-4">Preencha os campos abaixo:</h4>
                                    <form id="form-campos-personalizados">
                                        <?php foreach ($campos_personalizados as $campo): ?>
                                        <div class="mb-4">
                                            <label class="form-label fw-bold">
                                                <?= htmlspecialchars($campo['label']) ?>
                                                <?php if ($campo['obrigatorio']): ?>
                                                <span class="text-danger">*</span>
                                                <?php endif; ?>
                                            </label>
                                            
                                            <?php
                                            $tipo = $campo['tipo_campo'];
                                            $nome = $campo['nome_campo'];
                                            $opcoes = json_decode($campo['opcoes'] ?? '[]', true);
                                            ?>
                                            
                                            <?php if ($tipo == 'texto'): ?>
                                            <input type="text" name="<?= $nome ?>" class="form-control" placeholder="<?= htmlspecialchars($campo['placeholder'] ?? '') ?>" <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                                            
                                            <?php elseif ($tipo == 'textarea'): ?>
                                            <textarea name="<?= $nome ?>" class="form-control" rows="4" placeholder="<?= htmlspecialchars($campo['placeholder'] ?? '') ?>" <?= $campo['obrigatorio'] ? 'required' : '' ?>></textarea>
                                            
                                            <?php elseif ($tipo == 'select'): ?>
                                            <select name="<?= $nome ?>" class="form-select" <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($opcoes as $opcao): ?>
                                                <option value="<?= htmlspecialchars($opcao) ?>"><?= htmlspecialchars($opcao) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <?php elseif ($tipo == 'radio'): ?>
                                            <div>
                                                <?php foreach ($opcoes as $opcao): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="<?= $nome ?>" id="<?= $nome ?>_<?= md5($opcao) ?>" value="<?= htmlspecialchars($opcao) ?>" <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                                                    <label class="form-check-label" for="<?= $nome ?>_<?= md5($opcao) ?>">
                                                        <?= htmlspecialchars($opcao) ?>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <?php elseif ($tipo == 'checkbox'): ?>
                                            <div>
                                                <?php foreach ($opcoes as $opcao): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="<?= $nome ?>[]" id="<?= $nome ?>_<?= md5($opcao) ?>" value="<?= htmlspecialchars($opcao) ?>">
                                                    <label class="form-check-label" for="<?= $nome ?>_<?= md5($opcao) ?>">
                                                        <?= htmlspecialchars($opcao) ?>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!--end::Player Container-->
                        
                        <!--begin::Progresso-->
                        <div class="d-flex align-items-center justify-content-between mb-5">
                            <div class="flex-grow-1 me-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Progresso da Aula</span>
                                    <span class="text-muted" id="percentual-progresso"><?= round($progresso['percentual_conclusao'] ?? 0, 0) ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" id="barra-progresso" role="progressbar" style="width: <?= $progresso['percentual_conclusao'] ?? 0 ?>%"></div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" id="btn-concluir-aula" data-progresso-id="<?= $progresso_id ?>" data-aula-id="<?= $aula_id ?>" data-curso-id="<?= $curso_id ?>">
                                <i class="ki-duotone ki-check fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Marcar como Concluído
                            </button>
                        </div>
                        <!--end::Progresso-->
                        
                        <!--begin::Navegação-->
                        <div class="d-flex justify-content-between">
                            <?php if ($aula_anterior): ?>
                            <a href="lms_player_aula.php?curso_id=<?= $curso_id ?>&aula_id=<?= $aula_anterior['id'] ?>" class="btn btn-light">
                                <i class="ki-duotone ki-arrow-left fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Aula Anterior
                            </a>
                            <?php else: ?>
                            <span></span>
                            <?php endif; ?>
                            
                            <a href="lms_curso_detalhes.php?id=<?= $curso_id ?>" class="btn btn-light">
                                <i class="ki-duotone ki-grid fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Ver Todas as Aulas
                            </a>
                            
                            <?php if ($aula_proxima): ?>
                            <a href="lms_player_aula.php?curso_id=<?= $curso_id ?>&aula_id=<?= $aula_proxima['id'] ?>" class="btn btn-primary">
                                Próxima Aula
                                <i class="ki-duotone ki-arrow-right fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </a>
                            <?php else: ?>
                            <span></span>
                            <?php endif; ?>
                        </div>
                        <!--end::Navegação-->
                    </div>
                </div>
                <!--end::Card Player-->
            </div>
            <!--end::Col Principal-->
            
            <!--begin::Col Lateral-->
            <div class="col-lg-3">
                <!--begin::Card Lista de Aulas-->
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-6 mb-1">Aulas do Curso</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($todas_aulas as $index => $a): ?>
                            <a href="lms_player_aula.php?curso_id=<?= $curso_id ?>&aula_id=<?= $a['id'] ?>" 
                               class="list-group-item list-group-item-action <?= $a['id'] == $aula_id ? 'active' : '' ?>">
                                <div class="d-flex align-items-center">
                                    <?php if ($a['status_progresso'] == 'concluido'): ?>
                                    <i class="ki-duotone ki-check-circle fs-3 text-success me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php elseif ($a['status_progresso'] == 'em_andamento'): ?>
                                    <i class="ki-duotone ki-time fs-3 text-warning me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php else: ?>
                                    <i class="ki-duotone ki-circle fs-3 text-muted me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= htmlspecialchars($a['titulo']) ?></div>
                                        <div class="text-muted fs-7">Aula <?= $index + 1 ?></div>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!--end::Card Lista de Aulas-->
            </div>
            <!--end::Col Lateral-->
        </div>
        
    </div>
</div>
<!--end::Post-->

<!--begin::Scripts Player-->
<script src="https://www.youtube.com/iframe_api"></script>
<script>
// Variáveis globais
const PROGRESSO_ID = <?= $progresso_id ?>;
const SESSAO_ID = <?= $sessao_id ?>;
const AULA_ID = <?= $aula_id ?>;
const CURSO_ID = <?= $curso_id ?>;
const TIPO_CONTEUDO = '<?= $aula['tipo_conteudo'] ?>';
const DURACAO_TOTAL = <?= $aula['duracao_segundos'] ?? 0 ?>;

// Player seguro - será implementado no próximo arquivo
// Por enquanto, apenas estrutura básica
</script>
<script src="../assets/js/lms_player.js"></script>
<!--end::Scripts Player-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

