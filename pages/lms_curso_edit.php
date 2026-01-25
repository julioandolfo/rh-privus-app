<?php
/**
 * Editar Curso
 */

$page_title = 'Editar Curso';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_curso_edit.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$curso_id = (int)($_GET['id'] ?? 0);

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
            redirect('lms_cursos.php', 'Você não tem permissão para editar este curso', 'error');
        }
    }
}

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descricao = sanitize($_POST['descricao'] ?? '');
    $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
    $setor_id = !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null;
    $cargo_id = !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null;
    $duracao_estimada = !empty($_POST['duracao_estimada']) ? (int)$_POST['duracao_estimada'] : null;
    $nivel_dificuldade = $_POST['nivel_dificuldade'] ?? 'iniciante';
    $status = $_POST['status'] ?? 'rascunho';
    $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    
    // Campos obrigatórios
    $obrigatorio = isset($_POST['obrigatorio']) && $_POST['obrigatorio'] == '1';
    $prazo_dias = !empty($_POST['prazo_dias']) ? (int)$_POST['prazo_dias'] : null;
    $prazo_tipo = $_POST['prazo_tipo'] ?? 'dias_apos_atribuicao';
    $data_limite = !empty($_POST['data_limite']) ? $_POST['data_limite'] : null;
    
    // Pontos de recompensa
    $pontos_recompensa = !empty($_POST['pontos_recompensa']) ? (int)$_POST['pontos_recompensa'] : 0;
    
    if (empty($titulo)) {
        redirect('lms_curso_edit.php?id=' . $curso_id, 'Preencha o título do curso!', 'error');
    }
    
    try {
        // Processa upload de imagem de capa
        $imagem_capa = $curso['imagem_capa'];
        if (isset($_FILES['imagem_capa']) && $_FILES['imagem_capa']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/lms/imagens/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = strtolower(pathinfo($_FILES['imagem_capa']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $filename = uniqid('curso_') . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['imagem_capa']['tmp_name'], $filepath)) {
                    // Remove imagem antiga se existir
                    if ($imagem_capa && file_exists(__DIR__ . '/../' . $imagem_capa)) {
                        @unlink(__DIR__ . '/../' . $imagem_capa);
                    }
                    $imagem_capa = 'uploads/lms/imagens/' . $filename;
                }
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE cursos 
            SET empresa_id = ?, setor_id = ?, cargo_id = ?, categoria_id = ?, titulo = ?, descricao = ?, 
                imagem_capa = ?, duracao_estimada = ?, nivel_dificuldade = ?, status = ?, 
                data_inicio = ?, data_fim = ?, obrigatorio = ?, prazo_dias = ?, prazo_tipo = ?, data_limite = ?,
                pontos_recompensa = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $empresa_id ?: null,
            $setor_id ?: null,
            $cargo_id ?: null,
            $categoria_id ?: null,
            $titulo,
            $descricao ?: null,
            $imagem_capa,
            $duracao_estimada,
            $nivel_dificuldade,
            $status,
            $data_inicio ?: null,
            $data_fim ?: null,
            $obrigatorio ? 1 : 0,
            $prazo_dias,
            $prazo_tipo,
            $data_limite ?: null,
            $pontos_recompensa,
            $curso_id
        ]);
        
        redirect('lms_curso_view.php?id=' . $curso_id, 'Curso atualizado com sucesso!', 'success');
        
    } catch (PDOException $e) {
        error_log("Erro ao atualizar curso: " . $e->getMessage());
        redirect('lms_curso_edit.php?id=' . $curso_id, 'Erro ao atualizar curso. Tente novamente.', 'error');
    }
}

// Busca empresas
require_once __DIR__ . '/../includes/select_colaborador.php';
$empresas = get_empresas_disponiveis($pdo, $usuario);

// Busca categorias
$stmt = $pdo->query("SELECT * FROM categorias_cursos WHERE status = 'ativo' ORDER BY ordem, nome");
$categorias = $stmt->fetchAll();

$page_title = 'Editar: ' . $curso['titulo'];
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Editar Curso</h1>
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
                <li class="breadcrumb-item text-gray-900">Editar</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2 gap-2">
            <a href="lms_curso_view.php?id=<?= $curso_id ?>" class="btn btn-light">Voltar</a>
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
                    
                    <!-- Informações Básicas -->
                    <div class="col-12">
                        <h3 class="mb-4">Informações Básicas</h3>
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Título do Curso *</label>
                        <input type="text" name="titulo" class="form-control" required value="<?= htmlspecialchars($curso['titulo']) ?>">
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="5"><?= htmlspecialchars($curso['descricao'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Categoria</label>
                        <select name="categoria_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $curso['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)): ?>
                    <div class="col-md-6">
                        <label class="form-label">Empresa</label>
                        <select name="empresa_id" class="form-select" id="empresaSelect">
                            <option value="">Todas</option>
                            <?php foreach ($empresas as $empresa): ?>
                            <option value="<?= $empresa['id'] ?>" <?= $curso['empresa_id'] == $empresa['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($empresa['nome_fantasia']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <label class="form-label">Duração Estimada (minutos)</label>
                        <input type="number" name="duracao_estimada" class="form-control" min="0" value="<?= $curso['duracao_estimada'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Nível de Dificuldade</label>
                        <select name="nivel_dificuldade" class="form-select">
                            <option value="iniciante" <?= $curso['nivel_dificuldade'] == 'iniciante' ? 'selected' : '' ?>>Iniciante</option>
                            <option value="intermediario" <?= $curso['nivel_dificuldade'] == 'intermediario' ? 'selected' : '' ?>>Intermediário</option>
                            <option value="avancado" <?= $curso['nivel_dificuldade'] == 'avancado' ? 'selected' : '' ?>>Avançado</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="ki-duotone ki-medal-star text-warning me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            Pontos de Recompensa
                        </label>
                        <input type="number" name="pontos_recompensa" class="form-control" min="0" 
                               value="<?= $curso['pontos_recompensa'] ?? 0 ?>" 
                               placeholder="Ex: 100">
                        <small class="text-muted">Pontos que o colaborador ganha ao concluir este curso</small>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="rascunho" <?= $curso['status'] == 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
                            <option value="publicado" <?= $curso['status'] == 'publicado' ? 'selected' : '' ?>>Publicado</option>
                            <option value="arquivado" <?= $curso['status'] == 'arquivado' ? 'selected' : '' ?>>Arquivado</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Data de Início</label>
                        <input type="date" name="data_inicio" class="form-control" value="<?= $curso['data_inicio'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Data de Fim</label>
                        <input type="date" name="data_fim" class="form-control" value="<?= $curso['data_fim'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Imagem de Capa</label>
                        <?php if ($curso['imagem_capa']): ?>
                        <div class="mb-2">
                            <img src="../<?= htmlspecialchars($curso['imagem_capa']) ?>" class="img-thumbnail" style="max-width: 200px;" alt="Capa atual">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="imagem_capa" class="form-control" accept="image/*">
                        <div class="form-text">Formatos aceitos: JPG, PNG, GIF, WEBP</div>
                    </div>
                    
                    <!-- Curso Obrigatório -->
                    <div class="col-12">
                        <hr class="my-5">
                        <h3 class="mb-4">Configurações de Curso Obrigatório</h3>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="obrigatorio" value="1" id="obrigatorioCheck" <?= $curso['obrigatorio'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="obrigatorioCheck">
                                <strong>Este curso é obrigatório</strong>
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6" id="prazoTipoDiv" style="display: <?= $curso['obrigatorio'] ? 'block' : 'none' ?>;">
                        <label class="form-label">Tipo de Prazo</label>
                        <select name="prazo_tipo" class="form-select">
                            <option value="dias_apos_atribuicao" <?= $curso['prazo_tipo'] == 'dias_apos_atribuicao' ? 'selected' : '' ?>>Dias após atribuição</option>
                            <option value="dias_apos_admissao" <?= $curso['prazo_tipo'] == 'dias_apos_admissao' ? 'selected' : '' ?>>Dias após admissão</option>
                            <option value="data_fixa" <?= $curso['prazo_tipo'] == 'data_fixa' ? 'selected' : '' ?>>Data fixa</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6" id="prazoDiasDiv" style="display: <?= ($curso['obrigatorio'] && $curso['prazo_tipo'] != 'data_fixa') ? 'block' : 'none' ?>;">
                        <label class="form-label">Prazo (dias)</label>
                        <input type="number" name="prazo_dias" class="form-control" min="1" value="<?= $curso['prazo_dias'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-6" id="dataLimiteDiv" style="display: <?= ($curso['obrigatorio'] && $curso['prazo_tipo'] == 'data_fixa') ? 'block' : 'none' ?>;">
                        <label class="form-label">Data Limite</label>
                        <input type="date" name="data_limite" class="form-control" value="<?= $curso['data_limite'] ?? '' ?>">
                    </div>
                    
                    <!-- Botões -->
                    <div class="col-12">
                        <hr class="my-5">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="lms_curso_view.php?id=<?= $curso_id ?>" class="btn btn-light">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<script>
document.getElementById('obrigatorioCheck').addEventListener('change', function() {
    const checked = this.checked;
    document.getElementById('prazoTipoDiv').style.display = checked ? 'block' : 'none';
    const prazoTipo = document.querySelector('[name="prazo_tipo"]').value;
    document.getElementById('prazoDiasDiv').style.display = checked && prazoTipo !== 'data_fixa' ? 'block' : 'none';
    document.getElementById('dataLimiteDiv').style.display = checked && prazoTipo === 'data_fixa' ? 'block' : 'none';
});

document.querySelector('[name="prazo_tipo"]').addEventListener('change', function() {
    const isDataFixa = this.value === 'data_fixa';
    document.getElementById('prazoDiasDiv').style.display = isDataFixa ? 'none' : 'block';
    document.getElementById('dataLimiteDiv').style.display = isDataFixa ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

