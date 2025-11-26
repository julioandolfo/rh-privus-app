<?php
/**
 * Adicionar Novo Curso
 */

$page_title = 'Novo Curso';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_curso_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

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
    
    if (empty($titulo)) {
        redirect('lms_curso_add.php', 'Preencha o título do curso!', 'error');
    }
    
    // Valida empresa para RH
    if ($usuario['role'] === 'RH' && $empresa_id) {
        if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
            if (!in_array($empresa_id, $usuario['empresas_ids'])) {
                redirect('lms_curso_add.php', 'Você não tem permissão para criar cursos nesta empresa!', 'error');
            }
        }
    }
    
    try {
        // Processa upload de imagem de capa
        $imagem_capa = null;
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
                    $imagem_capa = 'uploads/lms/imagens/' . $filename;
                }
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO cursos 
            (empresa_id, setor_id, cargo_id, categoria_id, titulo, descricao, imagem_capa, duracao_estimada, nivel_dificuldade, status, data_inicio, data_fim, obrigatorio, prazo_dias, prazo_tipo, data_limite, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $usuario['id']
        ]);
        
        $curso_id = $pdo->lastInsertId();
        redirect('lms_curso_view.php?id=' . $curso_id, 'Curso criado com sucesso!', 'success');
        
    } catch (PDOException $e) {
        error_log("Erro ao criar curso: " . $e->getMessage());
        redirect('lms_curso_add.php', 'Erro ao criar curso. Tente novamente.', 'error');
    }
}

// Busca empresas
require_once __DIR__ . '/../includes/select_colaborador.php';
$empresas = get_empresas_disponiveis($pdo, $usuario);

// Busca categorias
$stmt = $pdo->query("SELECT * FROM categorias_cursos WHERE status = 'ativo' ORDER BY ordem, nome");
$categorias = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Novo Curso</h1>
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
                <li class="breadcrumb-item text-gray-900">Novo Curso</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="lms_cursos.php" class="btn btn-light">Voltar</a>
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
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="5"></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Categoria</label>
                        <select name="categoria_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)): ?>
                    <div class="col-md-6">
                        <label class="form-label">Empresa</label>
                        <select name="empresa_id" class="form-select" id="empresaSelect">
                            <option value="">Todas</option>
                            <?php foreach ($empresas as $empresa): ?>
                            <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nome_fantasia']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <label class="form-label">Duração Estimada (minutos)</label>
                        <input type="number" name="duracao_estimada" class="form-control" min="0">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Nível de Dificuldade</label>
                        <select name="nivel_dificuldade" class="form-select">
                            <option value="iniciante">Iniciante</option>
                            <option value="intermediario">Intermediário</option>
                            <option value="avancado">Avançado</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="rascunho">Rascunho</option>
                            <option value="publicado">Publicado</option>
                            <option value="arquivado">Arquivado</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Data de Início</label>
                        <input type="date" name="data_inicio" class="form-control">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Data de Fim</label>
                        <input type="date" name="data_fim" class="form-control">
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Imagem de Capa</label>
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
                            <input class="form-check-input" type="checkbox" name="obrigatorio" value="1" id="obrigatorioCheck">
                            <label class="form-check-label" for="obrigatorioCheck">
                                <strong>Este curso é obrigatório</strong>
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6" id="prazoTipoDiv" style="display: none;">
                        <label class="form-label">Tipo de Prazo</label>
                        <select name="prazo_tipo" class="form-select">
                            <option value="dias_apos_atribuicao">Dias após atribuição</option>
                            <option value="dias_apos_admissao">Dias após admissão</option>
                            <option value="data_fixa">Data fixa</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6" id="prazoDiasDiv" style="display: none;">
                        <label class="form-label">Prazo (dias)</label>
                        <input type="number" name="prazo_dias" class="form-control" min="1">
                    </div>
                    
                    <div class="col-md-6" id="dataLimiteDiv" style="display: none;">
                        <label class="form-label">Data Limite</label>
                        <input type="date" name="data_limite" class="form-control">
                    </div>
                    
                    <!-- Botões -->
                    <div class="col-12">
                        <hr class="my-5">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="lms_cursos.php" class="btn btn-light">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar Curso</button>
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
    document.getElementById('prazoDiasDiv').style.display = checked ? 'block' : 'none';
    document.getElementById('dataLimiteDiv').style.display = checked && document.querySelector('[name="prazo_tipo"]').value === 'data_fixa' ? 'block' : 'none';
});

document.querySelector('[name="prazo_tipo"]').addEventListener('change', function() {
    const isDataFixa = this.value === 'data_fixa';
    document.getElementById('prazoDiasDiv').style.display = isDataFixa ? 'none' : 'block';
    document.getElementById('dataLimiteDiv').style.display = isDataFixa ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

