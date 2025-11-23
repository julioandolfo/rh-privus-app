<?php
/**
 * Visualização Detalhada de Ocorrência
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

require_page_permission('ocorrencias_list.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$ocorrencia_id = $_GET['id'] ?? 0;

if (!$ocorrencia_id) {
    redirect('ocorrencias_list.php', 'Ocorrência não encontrada!', 'error');
}

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_comment') {
        $comentario = sanitize($_POST['comentario'] ?? '');
        $tipo_comentario = $_POST['tipo_comentario'] ?? 'comentario';
        
        if (empty($comentario)) {
            redirect('ocorrencia_view.php?id=' . $ocorrencia_id, 'Preencha o comentário!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ocorrencias_comentarios 
                (ocorrencia_id, usuario_id, comentario, tipo)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$ocorrencia_id, $usuario['id'], $comentario, $tipo_comentario]);
            
            registrar_historico_ocorrencia($ocorrencia_id, 'comentada', $usuario['id'], null, null, null, 'Comentário adicionado');
            
            redirect('ocorrencia_view.php?id=' . $ocorrencia_id, 'Comentário adicionado com sucesso!');
        } catch (PDOException $e) {
            redirect('ocorrencia_view.php?id=' . $ocorrencia_id, 'Erro ao adicionar comentário: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca dados da ocorrência
$stmt = $pdo->prepare("
    SELECT o.*, 
           c.nome_completo as colaborador_nome, c.email_pessoal,
           u.nome as usuario_nome,
           t.nome as tipo_ocorrencia_nome, t.categoria as tipo_categoria,
           aprovador.nome as aprovador_nome
    FROM ocorrencias o
    INNER JOIN colaboradores c ON o.colaborador_id = c.id
    LEFT JOIN usuarios u ON o.usuario_id = u.id
    LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
    LEFT JOIN usuarios aprovador ON o.aprovado_por = aprovador.id
    WHERE o.id = ?
");
$stmt->execute([$ocorrencia_id]);
$ocorrencia = $stmt->fetch();

if (!$ocorrencia) {
    redirect('ocorrencias_list.php', 'Ocorrência não encontrada!', 'error');
}

// Verifica permissão
if (!can_access_colaborador($ocorrencia['colaborador_id'])) {
    redirect('ocorrencias_list.php', 'Você não tem permissão para visualizar esta ocorrência.', 'error');
}

// Busca anexos
$stmt = $pdo->prepare("
    SELECT a.*, u.nome as uploaded_by_nome
    FROM ocorrencias_anexos a
    LEFT JOIN usuarios u ON a.uploaded_by = u.id
    WHERE a.ocorrencia_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$ocorrencia_id]);
$anexos = $stmt->fetchAll();

// Busca comentários
$stmt = $pdo->prepare("
    SELECT c.*, u.nome as usuario_nome, u.role as usuario_role
    FROM ocorrencias_comentarios c
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.ocorrencia_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$ocorrencia_id]);
$comentarios = $stmt->fetchAll();

// Busca histórico
$stmt = $pdo->prepare("
    SELECT h.*, u.nome as usuario_nome
    FROM ocorrencias_historico h
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    WHERE h.ocorrencia_id = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$ocorrencia_id]);
$historico = $stmt->fetchAll();

// Busca tags
$tags_disponiveis = get_tags_ocorrencias();
$tags_ocorrencia = [];
if (!empty($ocorrencia['tags'])) {
    $tags_array = json_decode($ocorrencia['tags'], true);
    if ($tags_array) {
        foreach ($tags_array as $tag_id) {
            foreach ($tags_disponiveis as $tag) {
                if ($tag['id'] == $tag_id) {
                    $tags_ocorrencia[] = $tag;
                    break;
                }
            }
        }
    }
}

// Busca campos dinâmicos se existirem
$campos_dinamicos_valores = [];
if (!empty($ocorrencia['campos_dinamicos'])) {
    $campos_dinamicos_valores = json_decode($ocorrencia['campos_dinamicos'], true);
}

$page_title = 'Detalhes da Ocorrência';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Detalhes da Ocorrência</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item">
                    <a href="ocorrencias_list.php" class="text-muted text-hover-primary">Ocorrências</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Detalhes</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="ocorrencias_list.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-arrow-left fs-2"></i>
                Voltar
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="row">
            <!-- Coluna Principal -->
            <div class="col-lg-8">
                <!-- Informações da Ocorrência -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Informações da Ocorrência</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-7">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 text-gray-500">Colaborador</label>
                                <div class="fw-bold fs-6">
                                    <a href="colaborador_view.php?id=<?= $ocorrencia['colaborador_id'] ?>" class="text-gray-800 text-hover-primary">
                                        <?= htmlspecialchars($ocorrencia['colaborador_nome']) ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 text-gray-500">Tipo</label>
                                <div class="fw-bold fs-6">
                                    <?php
                                    $categoria_colors = [
                                        'pontualidade' => 'badge-light-warning',
                                        'comportamento' => 'badge-light-danger',
                                        'desempenho' => 'badge-light-primary',
                                        'outros' => 'badge-light-secondary'
                                    ];
                                    ?>
                                    <span class="badge <?= $categoria_colors[$ocorrencia['tipo_categoria']] ?? 'badge-light-secondary' ?>">
                                        <?= htmlspecialchars($ocorrencia['tipo_ocorrencia_nome'] ?? $ocorrencia['tipo']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-7">
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 text-gray-500">Data da Ocorrência</label>
                                <div class="fw-bold fs-6"><?= formatar_data($ocorrencia['data_ocorrencia']) ?></div>
                            </div>
                            <?php if ($ocorrencia['hora_ocorrencia']): ?>
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 text-gray-500">Hora</label>
                                <div class="fw-bold fs-6"><?= substr($ocorrencia['hora_ocorrencia'], 0, 5) ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 text-gray-500">Severidade</label>
                                <div class="fw-bold fs-6">
                                    <?php
                                    $severidade = $ocorrencia['severidade'] ?? 'moderada';
                                    $severidade_labels = ['leve' => 'Leve', 'moderada' => 'Moderada', 'grave' => 'Grave', 'critica' => 'Crítica'];
                                    $severidade_colors = [
                                        'leve' => 'badge-light-success',
                                        'moderada' => 'badge-light-info',
                                        'grave' => 'badge-light-warning',
                                        'critica' => 'badge-light-danger'
                                    ];
                                    ?>
                                    <span class="badge <?= $severidade_colors[$severidade] ?? 'badge-light-info' ?>">
                                        <?= $severidade_labels[$severidade] ?? 'Moderada' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($ocorrencia['tempo_atraso_minutos']): ?>
                        <div class="row mb-7">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 text-gray-500">Tempo de Atraso</label>
                                <div class="fw-bold fs-6">
                                    <?php
                                    $horas = floor($ocorrencia['tempo_atraso_minutos'] / 60);
                                    $minutos = $ocorrencia['tempo_atraso_minutos'] % 60;
                                    echo ($horas > 0 ? $horas . 'h ' : '') . $minutos . 'min';
                                    ?>
                                </div>
                            </div>
                            <?php if ($ocorrencia['tipo_ponto']): ?>
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 text-gray-500">Tipo de Ponto</label>
                                <div class="fw-bold fs-6"><?= ucfirst($ocorrencia['tipo_ponto']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($campos_dinamicos_valores)): ?>
                        <div class="separator separator-dashed my-7"></div>
                        <h4 class="fw-bold mb-5">Campos Adicionais</h4>
                        <?php
                        if ($ocorrencia['tipo_ocorrencia_id']) {
                            $campos_dinamicos = get_campos_dinamicos_tipo($ocorrencia['tipo_ocorrencia_id']);
                            foreach ($campos_dinamicos as $campo) {
                                $valor = $campos_dinamicos_valores[$campo['codigo']] ?? '';
                                if ($valor !== '') {
                                    echo '<div class="row mb-5">';
                                    echo '<div class="col-md-12">';
                                    echo '<label class="fw-semibold fs-6 text-gray-500">' . htmlspecialchars($campo['label']) . '</label>';
                                    echo '<div class="fw-bold fs-6">' . htmlspecialchars($valor) . '</div>';
                                    echo '</div></div>';
                                }
                            }
                        }
                        ?>
                        <?php endif; ?>
                        
                        <div class="separator separator-dashed my-7"></div>
                        <div class="mb-7">
                            <label class="fw-semibold fs-6 text-gray-500 mb-2">Descrição</label>
                            <div class="fw-normal fs-6 text-gray-800">
                                <?= nl2br(htmlspecialchars($ocorrencia['descricao'] ?? '')) ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($tags_ocorrencia)): ?>
                        <div class="mb-7">
                            <label class="fw-semibold fs-6 text-gray-500 mb-2">Tags</label>
                            <div>
                                <?php foreach ($tags_ocorrencia as $tag): ?>
                                <span class="badge badge-light me-2" style="background-color: <?= htmlspecialchars($tag['cor']) ?>20; color: <?= htmlspecialchars($tag['cor']) ?>;">
                                    <?= htmlspecialchars($tag['nome']) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Comentários -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Comentários (<?= count($comentarios) ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($comentarios as $comentario): ?>
                        <div class="d-flex mb-7">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label fs-2 fw-semibold bg-light-primary text-primary">
                                    <?= strtoupper(substr($comentario['usuario_nome'] ?? 'U', 0, 1)) ?>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="text-gray-800 fw-bold me-2"><?= htmlspecialchars($comentario['usuario_nome'] ?? 'Usuário') ?></span>
                                    <span class="text-muted fs-7"><?= formatar_data($comentario['created_at'], 'd/m/Y H:i') ?></span>
                                    <?php if ($comentario['tipo'] === 'defesa'): ?>
                                    <span class="badge badge-light-warning ms-2">Defesa</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-gray-700">
                                    <?= nl2br(htmlspecialchars($comentario['comentario'])) ?>
                                </div>
                            </div>
                        </div>
                        <div class="separator separator-dashed"></div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($comentarios)): ?>
                        <div class="text-center text-muted py-10">
                            <i class="ki-duotone ki-message fs-3x mb-3"></i>
                            <p>Nenhum comentário ainda.</p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Formulário de Comentário -->
                        <form method="POST" class="mt-7">
                            <input type="hidden" name="action" value="add_comment">
                            <div class="mb-5">
                                <label class="fw-semibold fs-6 mb-2">Adicionar Comentário</label>
                                <textarea name="comentario" class="form-control form-control-solid" rows="3" required></textarea>
                            </div>
                            <?php if ($usuario['role'] === 'COLABORADOR' || ($usuario['colaborador_id'] ?? null) == $ocorrencia['colaborador_id']): ?>
                            <div class="mb-5">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="tipo_comentario" value="defesa" id="tipo_defesa" />
                                    <label class="form-check-label" for="tipo_defesa">
                                        Marcar como defesa do colaborador
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-duotone ki-message fs-2"></i>
                                Enviar Comentário
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Status e Aprovação -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-7">
                            <label class="fw-semibold fs-6 text-gray-500">Status de Aprovação</label>
                            <div class="mt-2">
                                <?php
                                $status_aprovacao = $ocorrencia['status_aprovacao'] ?? 'aprovada';
                                $status_colors = [
                                    'pendente' => 'badge-light-warning',
                                    'aprovada' => 'badge-light-success',
                                    'rejeitada' => 'badge-light-danger'
                                ];
                                $status_labels = [
                                    'pendente' => 'Pendente',
                                    'aprovada' => 'Aprovada',
                                    'rejeitada' => 'Rejeitada'
                                ];
                                ?>
                                <span class="badge <?= $status_colors[$status_aprovacao] ?? 'badge-light-success' ?> fs-6">
                                    <?= $status_labels[$status_aprovacao] ?? 'Aprovada' ?>
                                </span>
                            </div>
                            <?php if ($ocorrencia['aprovador_nome']): ?>
                            <div class="text-muted fs-7 mt-2">
                                Aprovado por: <?= htmlspecialchars($ocorrencia['aprovador_nome']) ?><br>
                                Em: <?= formatar_data($ocorrencia['data_aprovacao'] ?? '', 'd/m/Y H:i') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($ocorrencia['status_aprovacao'] === 'pendente' && has_role(['ADMIN', 'RH'])): ?>
                        <div class="d-flex gap-2">
                            <a href="ocorrencias_approve.php?id=<?= $ocorrencia_id ?>&acao=aprovar" class="btn btn-sm btn-success flex-grow-1">
                                Aprovar
                            </a>
                            <a href="ocorrencias_approve.php?id=<?= $ocorrencia_id ?>&acao=rejeitar" class="btn btn-sm btn-danger flex-grow-1">
                                Rejeitar
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Anexos -->
                <?php if (!empty($anexos)): ?>
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Anexos (<?= count($anexos) ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($anexos as $anexo): ?>
                        <div class="d-flex align-items-center mb-5">
                            <div class="symbol symbol-45px me-3">
                                <i class="ki-duotone ki-file fs-2x text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-gray-800"><?= htmlspecialchars($anexo['nome_arquivo']) ?></div>
                                <div class="text-muted fs-7">
                                    <?= format_file_size($anexo['tamanho_bytes'] ?? 0) ?> - 
                                    <?= formatar_data($anexo['created_at'], 'd/m/Y H:i') ?>
                                </div>
                            </div>
                            <a href="../<?= htmlspecialchars($anexo['caminho_arquivo']) ?>" target="_blank" class="btn btn-sm btn-light">
                                <i class="ki-duotone ki-download fs-2"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Informações Adicionais -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informações</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-5">
                            <label class="fw-semibold fs-6 text-gray-500">Registrado por</label>
                            <div class="fw-bold fs-6"><?= htmlspecialchars($ocorrencia['usuario_nome'] ?? 'N/A') ?></div>
                        </div>
                        <div class="mb-5">
                            <label class="fw-semibold fs-6 text-gray-500">Data de Registro</label>
                            <div class="fw-bold fs-6"><?= formatar_data($ocorrencia['created_at'], 'd/m/Y H:i') ?></div>
                        </div>
                        <?php if ($ocorrencia['valor_desconto']): ?>
                        <div class="mb-5">
                            <label class="fw-semibold fs-6 text-gray-500">Desconto Calculado</label>
                            <div class="fw-bold fs-6 text-danger">R$ <?= number_format($ocorrencia['valor_desconto'], 2, ',', '.') ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

