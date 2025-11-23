<?php
/**
 * CRUD de Tipos de Ocorrências - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

require_page_permission('tipos_ocorrencias.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $nome = sanitize($_POST['nome'] ?? '');
        $codigo = sanitize($_POST['codigo'] ?? '');
        
        // Gera código automaticamente se não fornecido ou vazio
        if (empty($codigo) && !empty($nome)) {
            $codigo = strtolower($nome);
            // Remove acentos
            $codigo = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $codigo);
            // Remove caracteres especiais, mantém apenas letras, números e espaços
            $codigo = preg_replace('/[^a-z0-9\s]/', '', $codigo);
            // Substitui espaços por underscore
            $codigo = preg_replace('/\s+/', '_', $codigo);
            // Remove underscores duplicados
            $codigo = preg_replace('/_+/', '_', $codigo);
            // Remove underscores do início/fim
            $codigo = trim($codigo, '_');
        }
        
        $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
        $categoria = $_POST['categoria'] ?? ''; // Mantido para compatibilidade
        
        // Se categoria_id foi fornecido mas categoria não, busca o código da categoria
        if ($categoria_id && empty($categoria)) {
            $stmt_cat = $pdo->prepare("SELECT codigo FROM ocorrencias_categorias WHERE id = ?");
            $stmt_cat->execute([$categoria_id]);
            $cat_data = $stmt_cat->fetch();
            if ($cat_data) {
                $categoria = $cat_data['codigo'];
            }
        }
        
        $severidade = $_POST['severidade'] ?? 'moderada';
        $permite_tempo_atraso = isset($_POST['permite_tempo_atraso']) ? 1 : 0;
        $permite_tipo_ponto = isset($_POST['permite_tipo_ponto']) ? 1 : 0;
        $requer_aprovacao = isset($_POST['requer_aprovacao']) ? 1 : 0;
        $conta_advertencia = isset($_POST['conta_advertencia']) ? 1 : 0;
        $calcula_desconto = isset($_POST['calcula_desconto']) ? 1 : 0;
        $valor_desconto = !empty($_POST['valor_desconto']) ? (float)$_POST['valor_desconto'] : null;
        $template_descricao = sanitize($_POST['template_descricao'] ?? '');
        
        // Processa validações customizadas (vem como JSON string do campo hidden)
        $validacoes_customizadas = null;
        if (!empty($_POST['validacoes_customizadas'])) {
            $validacoes_array = json_decode($_POST['validacoes_customizadas'], true);
            if ($validacoes_array && is_array($validacoes_array)) {
                $validacoes_customizadas = json_encode($validacoes_array);
            }
        }
        $notificar_colaborador = isset($_POST['notificar_colaborador']) ? 1 : 0;
        $notificar_colaborador_sistema = isset($_POST['notificar_colaborador_sistema']) ? 1 : 0;
        $notificar_colaborador_email = isset($_POST['notificar_colaborador_email']) ? 1 : 0;
        $notificar_colaborador_push = isset($_POST['notificar_colaborador_push']) ? 1 : 0;
        $notificar_gestor = isset($_POST['notificar_gestor']) ? 1 : 0;
        $notificar_gestor_sistema = isset($_POST['notificar_gestor_sistema']) ? 1 : 0;
        $notificar_gestor_email = isset($_POST['notificar_gestor_email']) ? 1 : 0;
        $notificar_gestor_push = isset($_POST['notificar_gestor_push']) ? 1 : 0;
        $notificar_rh = isset($_POST['notificar_rh']) ? 1 : 0;
        $notificar_rh_sistema = isset($_POST['notificar_rh_sistema']) ? 1 : 0;
        $notificar_rh_email = isset($_POST['notificar_rh_email']) ? 1 : 0;
        $notificar_rh_push = isset($_POST['notificar_rh_push']) ? 1 : 0;
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome)) {
            redirect('tipos_ocorrencias.php', 'Preencha o nome do tipo de ocorrência!', 'error');
        }
        
        // Garante que código não está vazio
        if (empty($codigo)) {
            redirect('tipos_ocorrencias.php', 'Erro ao gerar código. Tente novamente.', 'error');
        }
        
        // Valida código único
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("SELECT id FROM tipos_ocorrencias WHERE codigo = ?");
                $stmt->execute([$codigo]);
                if ($stmt->fetch()) {
                    redirect('tipos_ocorrencias.php', 'Código já existe!', 'error');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO tipos_ocorrencias 
                    (nome, codigo, categoria, categoria_id, severidade, permite_tempo_atraso, permite_tipo_ponto, 
                     requer_aprovacao, conta_advertencia, calcula_desconto, valor_desconto, 
                     template_descricao, validacoes_customizadas, notificar_colaborador, 
                     notificar_colaborador_sistema, notificar_colaborador_email, notificar_colaborador_push,
                     notificar_gestor, notificar_gestor_sistema, notificar_gestor_email, notificar_gestor_push,
                     notificar_rh, notificar_rh_sistema, notificar_rh_email, notificar_rh_push, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nome, $codigo, $categoria, $categoria_id, $severidade, $permite_tempo_atraso, $permite_tipo_ponto,
                    $requer_aprovacao, $conta_advertencia, $calcula_desconto, $valor_desconto,
                    $template_descricao, $validacoes_customizadas, $notificar_colaborador,
                    $notificar_colaborador_sistema, $notificar_colaborador_email, $notificar_colaborador_push,
                    $notificar_gestor, $notificar_gestor_sistema, $notificar_gestor_email, $notificar_gestor_push,
                    $notificar_rh, $notificar_rh_sistema, $notificar_rh_email, $notificar_rh_push, $status
                ]);
                
                $tipo_id = $pdo->lastInsertId();
                
                // Processa campos dinâmicos se existirem
                if (isset($_POST['campos_dinamicos']) && is_array($_POST['campos_dinamicos'])) {
                    processar_campos_dinamicos($tipo_id, $_POST['campos_dinamicos']);
                }
                
                redirect('tipos_ocorrencias.php', 'Tipo de ocorrência cadastrado com sucesso!');
            } else {
                $id = $_POST['id'] ?? 0;
                
                // Verifica código único (exceto o próprio registro)
                $stmt = $pdo->prepare("SELECT id FROM tipos_ocorrencias WHERE codigo = ? AND id != ?");
                $stmt->execute([$codigo, $id]);
                if ($stmt->fetch()) {
                    redirect('tipos_ocorrencias.php', 'Código já existe!', 'error');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE tipos_ocorrencias SET 
                    nome = ?, codigo = ?, categoria = ?, categoria_id = ?, severidade = ?, 
                    permite_tempo_atraso = ?, permite_tipo_ponto = ?, 
                    requer_aprovacao = ?, conta_advertencia = ?, calcula_desconto = ?, 
                    valor_desconto = ?, template_descricao = ?, validacoes_customizadas = ?,
                    notificar_colaborador = ?, notificar_colaborador_sistema = ?, notificar_colaborador_email = ?, notificar_colaborador_push = ?,
                    notificar_gestor = ?, notificar_gestor_sistema = ?, notificar_gestor_email = ?, notificar_gestor_push = ?,
                    notificar_rh = ?, notificar_rh_sistema = ?, notificar_rh_email = ?, notificar_rh_push = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nome, $codigo, $categoria, $categoria_id, $severidade, $permite_tempo_atraso, $permite_tipo_ponto,
                    $requer_aprovacao, $conta_advertencia, $calcula_desconto, $valor_desconto,
                    $template_descricao, $validacoes_customizadas, $notificar_colaborador,
                    $notificar_colaborador_sistema, $notificar_colaborador_email, $notificar_colaborador_push,
                    $notificar_gestor, $notificar_gestor_sistema, $notificar_gestor_email, $notificar_gestor_push,
                    $notificar_rh, $notificar_rh_sistema, $notificar_rh_email, $notificar_rh_push, $status, $id
                ]);
                
                // Processa campos dinâmicos
                if (isset($_POST['campos_dinamicos']) && is_array($_POST['campos_dinamicos'])) {
                    processar_campos_dinamicos($id, $_POST['campos_dinamicos']);
                }
                
                redirect('tipos_ocorrencias.php', 'Tipo de ocorrência atualizado com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('tipos_ocorrencias.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            // Verifica se há ocorrências usando este tipo
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ocorrencias WHERE tipo_ocorrencia_id = ?");
            $stmt->execute([$id]);
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                redirect('tipos_ocorrencias.php', 'Não é possível excluir: existem ' . $total . ' ocorrência(s) usando este tipo!', 'error');
            }
            
            $stmt = $pdo->prepare("DELETE FROM tipos_ocorrencias WHERE id = ?");
            $stmt->execute([$id]);
            redirect('tipos_ocorrencias.php', 'Tipo de ocorrência excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('tipos_ocorrencias.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca categorias ativas
$stmt_categorias = $pdo->query("SELECT * FROM ocorrencias_categorias WHERE ativo = 1 ORDER BY ordem, nome");
$categorias_disponiveis = $stmt_categorias->fetchAll();

// Busca tipos de ocorrências com JOIN em categorias
$stmt = $pdo->query("
    SELECT t.*, c.nome as categoria_nome, c.codigo as categoria_codigo, c.cor as categoria_cor
    FROM tipos_ocorrencias t
    LEFT JOIN ocorrencias_categorias c ON t.categoria_id = c.id
    ORDER BY c.ordem, c.nome, t.nome
");
$tipos = $stmt->fetchAll();

$page_title = 'Tipos de Ocorrências';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Tipos de Ocorrências</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Tipos de Ocorrências</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <!--begin::Card title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" data-kt-tipo-ocorrencia-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar tipos" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-tipo-ocorrencia-table-toolbar="base">
                        <!--begin::Add tipo-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_tipo_ocorrencia">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Novo Tipo
                        </button>
                        <!--end::Add tipo-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_tipos_ocorrencias_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-150px">Código</th>
                            <th class="min-w-120px">Categoria</th>
                            <th class="min-w-100px">Severidade</th>
                            <th class="min-w-100px">Aprovação</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($tipos as $tipo): ?>
                        <tr>
                            <td><?= $tipo['id'] ?></td>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($tipo['nome']) ?></a>
                            </td>
                            <td>
                                <span class="badge badge-light-info"><?= htmlspecialchars($tipo['codigo']) ?></span>
                            </td>
                            <td>
                                <?php if ($tipo['categoria_nome']): ?>
                                <span class="badge" style="background-color: <?= htmlspecialchars($tipo['categoria_cor'] ?? '#6c757d') ?>20; color: <?= htmlspecialchars($tipo['categoria_cor'] ?? '#6c757d') ?>;">
                                    <?= htmlspecialchars($tipo['categoria_nome']) ?>
                                </span>
                                <?php else: ?>
                                <span class="badge badge-light-secondary">
                                    <?= htmlspecialchars($tipo['categoria'] ?? 'Sem categoria') ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $severidade_labels = [
                                    'leve' => 'Leve',
                                    'moderada' => 'Moderada',
                                    'grave' => 'Grave',
                                    'critica' => 'Crítica'
                                ];
                                $severidade_colors = [
                                    'leve' => 'badge-light-success',
                                    'moderada' => 'badge-light-info',
                                    'grave' => 'badge-light-warning',
                                    'critica' => 'badge-light-danger'
                                ];
                                $severidade = $tipo['severidade'] ?? 'moderada';
                                ?>
                                <span class="badge <?= $severidade_colors[$severidade] ?? 'badge-light-info' ?>">
                                    <?= $severidade_labels[$severidade] ?? 'Moderada' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($tipo['requer_aprovacao'] ?? 0): ?>
                                    <span class="badge badge-light-warning">Sim</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary">Não</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tipo['status'] === 'ativo'): ?>
                                    <span class="badge badge-light-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="#" class="btn btn-sm btn-light btn-flex btn-center btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                    Ações 
                                    <i class="ki-duotone ki-down fs-5 ms-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </a>
                                <!--begin::Menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" onclick="editarTipoOcorrencia(<?= htmlspecialchars(json_encode($tipo)) ?>); return false;">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" data-kt-tipo-ocorrencia-table-filter="delete_row" data-tipo-id="<?= $tipo['id'] ?>" data-tipo-nome="<?= htmlspecialchars($tipo['nome']) ?>">Excluir</a>
                                    </div>
                                    <!--end::Menu item-->
                                </div>
                                <!--end::Menu-->
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Tipo de Ocorrência-->
<div class="modal fade" id="kt_modal_tipo_ocorrencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_tipo_ocorrencia_header">
                <h2 class="fw-bold">Novo Tipo de Ocorrência</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_tipo_ocorrencia_form" method="POST" class="form">
                    <input type="hidden" name="action" id="tipo_ocorrencia_action" value="add">
                    <input type="hidden" name="id" id="tipo_ocorrencia_id">
                    
                    <!-- Abas -->
                    <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-7">
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary ms-0 me-10 active" data-bs-toggle="tab" href="#tab_basico">Básico</a>
                        </li>
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#tab_configuracoes">Configurações</a>
                        </li>
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#tab_campos">Campos Dinâmicos</a>
                        </li>
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#tab_notificacoes">Notificações</a>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- Aba Básico -->
                        <div class="tab-pane fade show active" id="tab_basico">
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <label class="required fw-semibold fs-6 mb-2">Nome do Tipo de Ocorrência</label>
                                    <input type="text" name="nome" id="nome" class="form-control form-control-solid mb-3 mb-lg-0" required placeholder="Ex: Atraso na Entrada" />
                                    <small class="text-muted">O código será gerado automaticamente baseado no nome</small>
                                    <input type="hidden" name="codigo" id="codigo" />
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-6">
                                    <label class="required fw-semibold fs-6 mb-2">
                                        Categoria
                                        <i class="ki-duotone ki-information-5 fs-6 text-primary ms-1" data-bs-toggle="tooltip" title="Categoria ajuda a organizar os tipos de ocorrências em grupos lógicos.">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </label>
                                    <select name="categoria_id" id="categoria_id" class="form-select form-select-solid" required>
                                        <option value="">Selecione uma categoria...</option>
                                        <?php foreach ($categorias_disponiveis as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" data-codigo="<?= htmlspecialchars($cat['codigo']) ?>">
                                            <?= htmlspecialchars($cat['nome']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="categoria" id="categoria" value="" />
                                    <small class="text-muted">Usado para agrupar tipos similares no formulário</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="required fw-semibold fs-6 mb-2">
                                        Severidade
                                        <i class="ki-duotone ki-information-5 fs-6 text-primary ms-1" data-bs-toggle="tooltip" title="Define o nível de gravidade da ocorrência. Leve = menor impacto, Crítica = maior impacto.">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </label>
                                    <select name="severidade" id="severidade" class="form-select form-select-solid" required>
                                        <option value="leve">Leve - Baixo impacto</option>
                                        <option value="moderada" selected>Moderada - Impacto médio</option>
                                        <option value="grave">Grave - Alto impacto</option>
                                        <option value="critica">Crítica - Impacto muito alto</option>
                                    </select>
                                    <small class="text-muted">Usado para priorização e filtros</small>
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-6">
                                    <label class="fw-semibold fs-6 mb-2">Status</label>
                                    <select name="status" id="status" class="form-select form-select-solid">
                                        <option value="ativo">Ativo</option>
                                        <option value="inativo">Inativo</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="fw-semibold fs-6 mb-2">
                                        Texto Padrão de Descrição
                                        <i class="ki-duotone ki-information-5 fs-6 text-primary ms-1" data-bs-toggle="tooltip" title="Texto que será usado automaticamente quando criar uma ocorrência deste tipo. Use {colaborador}, {data}, {hora} para informações dinâmicas.">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </label>
                                    <textarea name="template_descricao" id="template_descricao" class="form-control form-control-solid" rows="3" placeholder="Exemplo: O colaborador {colaborador} apresentou atraso no dia {data} às {hora}."></textarea>
                                    <small class="text-muted">
                                        <strong>Como funciona:</strong> Quando alguém criar uma ocorrência deste tipo e não preencher a descrição, este texto será usado automaticamente. 
                                        <br><strong>Variáveis disponíveis:</strong> {colaborador}, {data}, {hora}
                                    </small>
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-6">
                                    <div class="card card-flush">
                                        <div class="card-body">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="permite_tempo_atraso" id="permite_tempo_atraso" value="1" />
                                                <label class="form-check-label fw-semibold" for="permite_tempo_atraso">
                                                    Permite informar tempo de atraso
                                                </label>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                Quando marcado, o formulário de ocorrência mostrará um campo para informar quantos minutos de atraso. 
                                                Útil para ocorrências de pontualidade.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card card-flush">
                                        <div class="card-body">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="permite_tipo_ponto" id="permite_tipo_ponto" value="1" />
                                                <label class="form-check-label fw-semibold" for="permite_tipo_ponto">
                                                    Permite informar tipo de ponto
                                                </label>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                Quando marcado, o formulário mostrará um campo para selecionar o tipo de ponto (Entrada, Almoço, Café, Saída). 
                                                Útil para ocorrências relacionadas a registro de ponto.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Aba Configurações -->
                        <div class="tab-pane fade" id="tab_configuracoes">
                            <div class="alert alert-info mb-7">
                                <i class="ki-duotone ki-information-5 fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <strong>Configurações Avançadas:</strong> Estas opções controlam como o sistema trata este tipo de ocorrência automaticamente.
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <div class="card card-flush mb-5">
                                        <div class="card-header">
                                            <div class="card-title">
                                                <div class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="requer_aprovacao" id="requer_aprovacao" value="1" />
                                                    <label class="form-check-label fw-bold fs-5" for="requer_aprovacao">
                                                        Requer Aprovação
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-gray-700 mb-0">
                                                <strong>Como funciona:</strong> Quando marcado, ocorrências deste tipo ficam com status "Pendente" após serem criadas. 
                                                Um administrador ou RH precisa aprovar ou rejeitar antes que a ocorrência seja considerada válida.
                                            </p>
                                            <p class="text-gray-600 mt-2 mb-0">
                                                <strong>Quando usar:</strong> Para ocorrências graves ou críticas que precisam de validação antes de serem aplicadas ao colaborador.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <div class="card card-flush mb-5">
                                        <div class="card-header">
                                            <div class="card-title">
                                                <div class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="conta_advertencia" id="conta_advertencia" value="1" />
                                                    <label class="form-check-label fw-bold fs-5" for="conta_advertencia">
                                                        Conta para Advertências Progressivas
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-gray-700 mb-0">
                                                <strong>Como funciona:</strong> Quando marcado, cada ocorrência deste tipo conta para o sistema de advertências progressivas do colaborador. 
                                                O sistema conta quantas ocorrências o colaborador teve e aplica advertências automaticamente conforme as regras configuradas.
                                            </p>
                                            <p class="text-gray-600 mt-2 mb-0">
                                                <strong>Exemplo:</strong> Se configurado que 3 ocorrências = advertência verbal, após 3 ocorrências deste tipo, 
                                                o sistema criará automaticamente uma advertência verbal para o colaborador.
                                            </p>
                                            <p class="text-gray-600 mt-2 mb-0">
                                                <strong>Quando usar:</strong> Para ocorrências que devem contar para o histórico disciplinar do colaborador (atrasos, faltas, etc).
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <div class="card card-flush mb-5">
                                        <div class="card-header">
                                            <div class="card-title">
                                                <div class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="calcula_desconto" id="calcula_desconto" value="1" />
                                                    <label class="form-check-label fw-bold fs-5" for="calcula_desconto">
                                                        Calcula Desconto Automático
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-gray-700 mb-0">
                                                <strong>Como funciona:</strong> Quando marcado, o sistema calcula automaticamente um desconto no salário do colaborador baseado nesta ocorrência.
                                            </p>
                                            <p class="text-gray-600 mt-2 mb-0">
                                                <strong>Opções de cálculo:</strong>
                                            </p>
                                            <ul class="text-gray-600 mt-2">
                                                <li><strong>Valor fixo:</strong> Se você informar um valor abaixo, será descontado esse valor fixo</li>
                                                <li><strong>Por tempo de atraso:</strong> Se deixar vazio, calcula proporcionalmente ao tempo de atraso informado na ocorrência</li>
                                            </ul>
                                            <p class="text-gray-600 mt-2 mb-0">
                                                <strong>Quando usar:</strong> Para ocorrências que geram descontos salariais (atrasos, faltas, etc).
                                            </p>
                                            <div class="mt-5" id="campo_valor_desconto" style="display: none;">
                                                <label class="fw-semibold fs-6 mb-2">Valor Fixo do Desconto (R$)</label>
                                                <input type="number" name="valor_desconto" id="valor_desconto" class="form-control form-control-solid" step="0.01" min="0" placeholder="0.00" />
                                                <small class="text-muted">
                                                    <strong>Informe um valor fixo</strong> para descontar sempre esse valor, ou <strong>deixe vazio</strong> para calcular automaticamente baseado no tempo de atraso informado na ocorrência.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <label class="fw-semibold fs-6 mb-2">Regras de Validação</label>
                                    <div class="alert alert-info mb-5">
                                        <i class="ki-duotone ki-shield-check fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <strong>Validações Automáticas:</strong> Configure regras que o sistema verificará automaticamente quando alguém criar uma ocorrência deste tipo. 
                                        Se alguma regra não for atendida, o sistema impedirá o cadastro e mostrará uma mensagem de erro.
                                    </div>
                                    
                                    <div class="card card-flush mb-5">
                                        <div class="card-header">
                                            <div class="card-title">
                                                <h4 class="fw-bold">Validação de Datas</h4>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-5">
                                                <div class="col-md-6">
                                                    <div class="card card-flush bg-light">
                                                        <div class="card-body">
                                                            <div class="form-check form-check-custom form-check-solid mb-3">
                                                                <input class="form-check-input" type="checkbox" id="validacao_nao_permitir_futuro" />
                                                                <label class="form-check-label fw-bold" for="validacao_nao_permitir_futuro">
                                                                    Não permitir datas futuras
                                                                </label>
                                                            </div>
                                                            <p class="text-gray-700 mb-0 fs-7">
                                                                <strong>Como funciona:</strong> Quando marcado, o sistema não permitirá criar ocorrências com data futura (posterior à data atual).
                                                            </p>
                                                            <p class="text-gray-600 mt-2 mb-0 fs-7">
                                                                <strong>Exemplo:</strong> Se hoje é 15/01/2024, não será possível criar uma ocorrência para 16/01/2024 ou qualquer data futura.
                                                            </p>
                                                            <p class="text-gray-600 mt-2 mb-0 fs-7">
                                                                <strong>Quando usar:</strong> Para garantir que ocorrências sejam registradas apenas para eventos que já aconteceram, evitando registros antecipados.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card card-flush bg-light">
                                                        <div class="card-body">
                                                            <div class="form-check form-check-custom form-check-solid mb-3">
                                                                <input class="form-check-input" type="checkbox" id="validacao_nao_permitir_passado" />
                                                                <label class="form-check-label fw-bold" for="validacao_nao_permitir_passado">
                                                                    Não permitir datas muito antigas (mais de 1 ano)
                                                                </label>
                                                            </div>
                                                            <p class="text-gray-700 mb-0 fs-7">
                                                                <strong>Como funciona:</strong> Quando marcado, o sistema não permitirá criar ocorrências com data anterior a 1 ano da data atual.
                                                            </p>
                                                            <p class="text-gray-600 mt-2 mb-0 fs-7">
                                                                <strong>Exemplo:</strong> Se hoje é 15/01/2024, não será possível criar uma ocorrência para 14/01/2023 ou qualquer data anterior.
                                                            </p>
                                                            <p class="text-gray-600 mt-2 mb-0 fs-7">
                                                                <strong>Quando usar:</strong> Para evitar registros de ocorrências muito antigas que podem não ser mais relevantes ou válidas.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card card-flush">
                                        <div class="card-header">
                                            <div class="card-title">
                                                <h4 class="fw-bold">Validação de Tempo de Atraso</h4>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-gray-700 mb-5 fs-7">
                                                <strong>Importante:</strong> Estas validações só funcionam se o tipo de ocorrência tiver a opção "Permite informar tempo de atraso" marcada na aba Básico.
                                            </p>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card card-flush bg-light">
                                                        <div class="card-body">
                                                            <label class="fw-bold fs-6 mb-3">
                                                                Atraso máximo permitido (minutos)
                                                                <i class="ki-duotone ki-information-5 fs-6 text-primary ms-1" data-bs-toggle="tooltip" title="Define o limite máximo de minutos de atraso que pode ser informado ao criar uma ocorrência deste tipo.">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                </i>
                                                            </label>
                                                            <input type="number" id="validacao_max_atraso" class="form-control form-control-solid mb-3" min="0" placeholder="Ex: 120 (2 horas)" />
                                                            <p class="text-gray-700 mb-0 fs-7">
                                                                <strong>Como funciona:</strong> Se informado um valor, o sistema não permitirá criar ocorrências com tempo de atraso maior que este valor.
                                                            </p>
                                                            <p class="text-gray-600 mt-2 mb-0 fs-7">
                                                                <strong>Exemplo:</strong> Se definir 120 minutos (2 horas), não será possível registrar um atraso de 3 horas. O sistema mostrará erro.
                                                            </p>
                                                            <p class="text-gray-600 mt-2 mb-0 fs-7">
                                                                <strong>Quando usar:</strong> Para limitar atrasos que podem ser registrados, evitando valores exagerados ou erros de digitação.
                                                            </p>
                                                            <small class="text-muted d-block mt-2">
                                                                <strong>Dica:</strong> Deixe vazio para não ter limite máximo.
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card card-flush bg-light">
                                                        <div class="card-body">
                                                            <label class="fw-bold fs-6 mb-3">
                                                                Atraso mínimo permitido (minutos)
                                                                <i class="ki-duotone ki-information-5 fs-6 text-primary ms-1" data-bs-toggle="tooltip" title="Define o limite mínimo de minutos de atraso que pode ser informado ao criar uma ocorrência deste tipo.">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                </i>
                                                            </label>
                                                            <input type="number" id="validacao_min_atraso" class="form-control form-control-solid mb-3" min="0" placeholder="Ex: 5" />
                                                            <p class="text-gray-700 mb-0 fs-7">
                                                                <strong>Como funciona:</strong> Se informado um valor, o sistema não permitirá criar ocorrências com tempo de atraso menor que este valor.
                                                            </p>
                                                            <p class="text-gray-600 mt-2 mb-0 fs-7">
                                                                <strong>Exemplo:</strong> Se definir 5 minutos, não será possível registrar um atraso de 2 minutos. O sistema mostrará erro.
                                                            </p>
                                                            <p class="text-gray-600 mt-2 mb-0 fs-7">
                                                                <strong>Quando usar:</strong> Para garantir que apenas atrasos significativos sejam registrados, evitando registros de atrasos muito pequenos que podem ser tolerados.
                                                            </p>
                                                            <small class="text-muted d-block mt-2">
                                                                <strong>Dica:</strong> Deixe vazio para não ter limite mínimo.
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="validacoes_customizadas" id="validacoes_customizadas" />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Aba Campos Dinâmicos -->
                        <div class="tab-pane fade" id="tab_campos">
                            <div class="alert alert-info mb-7">
                                <i class="ki-duotone ki-information-5 fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <strong>Campos Dinâmicos:</strong> Adicione campos personalizados que aparecerão no formulário de ocorrências deste tipo.
                            </div>
                            <div id="campos_dinamicos_container">
                                <!-- Campos serão adicionados aqui via JavaScript -->
                            </div>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="adicionarCampoDinamico()">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Adicionar Campo
                            </button>
                        </div>
                        
                        <!-- Aba Notificações -->
                        <div class="tab-pane fade" id="tab_notificacoes">
                            <div class="alert alert-info mb-7">
                                <i class="ki-duotone ki-notification-bing fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <strong>Notificações Automáticas:</strong> Configure quais perfis devem ser notificados e através de quais canais quando uma ocorrência deste tipo for criada. Você pode escolher entre: 
                                <strong>notificação interna</strong> (dentro do sistema), <strong>e-mail</strong> e <strong>push notification</strong> (no celular).
                            </div>
                            
                            <!-- Notificar Colaborador -->
                            <div class="card card-flush mb-7">
                                <div class="card-header">
                                    <div class="card-title">
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" name="notificar_colaborador" id="notificar_colaborador" value="1" checked />
                                            <label class="form-check-label fw-bold fs-5" for="notificar_colaborador">
                                                Notificar Colaborador
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-gray-700 mb-4 fs-7">
                                        O colaborador que recebeu a ocorrência será notificado através dos canais selecionados abaixo.
                                    </p>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_colaborador_sistema" id="notificar_colaborador_sistema" value="1" checked />
                                                <label class="form-check-label" for="notificar_colaborador_sistema">
                                                    <i class="ki-duotone ki-notification-status fs-4 text-primary me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                    </i>
                                                    Notificação Interna
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Notificação dentro do sistema</small>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_colaborador_email" id="notificar_colaborador_email" value="1" checked />
                                                <label class="form-check-label" for="notificar_colaborador_email">
                                                    <i class="ki-duotone ki-sms fs-4 text-success me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    E-mail
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Envio de e-mail</small>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_colaborador_push" id="notificar_colaborador_push" value="1" checked />
                                                <label class="form-check-label" for="notificar_colaborador_push">
                                                    <i class="ki-duotone ki-phone fs-4 text-info me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Push Notification
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Notificação no celular</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notificar Gestor -->
                            <div class="card card-flush mb-7">
                                <div class="card-header">
                                    <div class="card-title">
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" name="notificar_gestor" id="notificar_gestor" value="1" checked />
                                            <label class="form-check-label fw-bold fs-5" for="notificar_gestor">
                                                Notificar Gestor
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-gray-700 mb-4 fs-7">
                                        O gestor direto do colaborador será notificado através dos canais selecionados abaixo.
                                    </p>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_gestor_sistema" id="notificar_gestor_sistema" value="1" checked />
                                                <label class="form-check-label" for="notificar_gestor_sistema">
                                                    <i class="ki-duotone ki-notification-status fs-4 text-primary me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                    </i>
                                                    Notificação Interna
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Notificação dentro do sistema</small>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_gestor_email" id="notificar_gestor_email" value="1" checked />
                                                <label class="form-check-label" for="notificar_gestor_email">
                                                    <i class="ki-duotone ki-sms fs-4 text-success me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    E-mail
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Envio de e-mail</small>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_gestor_push" id="notificar_gestor_push" value="1" checked />
                                                <label class="form-check-label" for="notificar_gestor_push">
                                                    <i class="ki-duotone ki-phone fs-4 text-info me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Push Notification
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Notificação no celular</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notificar RH -->
                            <div class="card card-flush mb-7">
                                <div class="card-header">
                                    <div class="card-title">
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" name="notificar_rh" id="notificar_rh" value="1" checked />
                                            <label class="form-check-label fw-bold fs-5" for="notificar_rh">
                                                Notificar RH
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-gray-700 mb-4 fs-7">
                                        Todos os usuários com perfil de RH serão notificados através dos canais selecionados abaixo.
                                    </p>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_rh_sistema" id="notificar_rh_sistema" value="1" checked />
                                                <label class="form-check-label" for="notificar_rh_sistema">
                                                    <i class="ki-duotone ki-notification-status fs-4 text-primary me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                    </i>
                                                    Notificação Interna
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Notificação dentro do sistema</small>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_rh_email" id="notificar_rh_email" value="1" checked />
                                                <label class="form-check-label" for="notificar_rh_email">
                                                    <i class="ki-duotone ki-sms fs-4 text-success me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    E-mail
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Envio de e-mail</small>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="notificar_rh_push" id="notificar_rh_push" value="1" checked />
                                                <label class="form-check-label" for="notificar_rh_push">
                                                    <i class="ki-duotone ki-phone fs-4 text-info me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Push Notification
                                                </label>
                                            </div>
                                            <small class="text-muted d-block ms-8">Notificação no celular</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center pt-15">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal - Tipo de Ocorrência-->

<script>
"use strict";
var KTTiposOcorrenciasList = function() {
    var t, n;
    
    var initDeleteHandlers = function() {
        n.querySelectorAll('[data-kt-tipo-ocorrencia-table-filter="delete_row"]').forEach(function(element) {
            element.addEventListener("click", function(e) {
                e.preventDefault();
                const tipoId = this.getAttribute("data-tipo-id");
                const tipoNome = this.getAttribute("data-tipo-nome");
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: "Tem certeza que deseja excluir " + tipoNome + "?",
                        icon: "warning",
                        showCancelButton: true,
                        buttonsStyling: false,
                        confirmButtonText: "Sim, excluir!",
                        cancelButtonText: "Não, cancelar",
                        customClass: {
                            confirmButton: "btn fw-bold btn-danger",
                            cancelButton: "btn fw-bold btn-active-light-primary"
                        }
                    }).then(function(result) {
                        if (result.value) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="${tipoId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            Swal.fire({
                                text: tipoNome + " não foi excluído.",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, entendi!",
                                customClass: {
                                    confirmButton: "btn fw-bold btn-primary"
                                }
                            });
                        }
                    });
                } else {
                    if (confirm("Tem certeza que deseja excluir " + tipoNome + "?")) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${tipoId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            });
        });
    };
    
    return {
        init: function() {
            n = document.querySelector("#kt_tipos_ocorrencias_table");
            
            if (n) {
                t = $(n).DataTable({
                    info: false,
                    order: [],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: 7 }
                    ]
                });
                
                // Busca customizada
                document.querySelector('[data-kt-tipo-ocorrencia-table-filter="search"]').addEventListener("keyup", function(e) {
                    t.search(e.target.value).draw();
                });
                
                // Inicializa handlers de exclusão
                initDeleteHandlers();
                
                // Reinicializa apenas os handlers após draw
                t.on("draw", function() {
                    initDeleteHandlers();
                    
                    // Inicialização manual de componentes específicos se necessário
                    // Evita chamar KTMenu.createInstances() que causa conflito com o menu lateral
                    var menus = document.querySelectorAll('#kt_tipos_ocorrencias_table [data-kt-menu="true"]');
                    if (menus && menus.length > 0) {
                        menus.forEach(function(el) {
                            if (typeof KTMenu !== 'undefined') {
                                // Tenta reinicializar apenas este elemento
                                try {
                                    KTMenu.init(el);
                                } catch (e) {}
                            }
                        });
                    }
                });
            }
        }
    };
}();

// Aguarda jQuery e SweetAlert estarem disponíveis
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    if (typeof Swal === 'undefined') {
        setTimeout(function() {
            if (typeof Swal !== 'undefined') {
                KTTiposOcorrenciasList.init();
            } else {
                console.warn('SweetAlert2 não foi carregado, usando fallback');
                KTTiposOcorrenciasList.init();
            }
        }, 100);
    } else {
        $(document).ready(function() {
            KTTiposOcorrenciasList.init();
        });
    }
})();

// Variável global para armazenar campos dinâmicos
var camposDinamicosCount = 0;

// Gera código automaticamente baseado no nome
function gerarCodigoCampo(nome) {
    return nome
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Remove acentos
        .replace(/[^a-z0-9\s]/g, '') // Remove caracteres especiais
        .replace(/\s+/g, '_') // Substitui espaços por underscore
        .replace(/_+/g, '_') // Remove underscores duplicados
        .replace(/^_|_$/g, ''); // Remove underscores do início/fim
}

function adicionarCampoDinamico(campo = null) {
    camposDinamicosCount++;
    const container = document.getElementById('campos_dinamicos_container');
    const campoId = campo ? campo.id : '';
    const campoIndex = campo ? campo.ordem : camposDinamicosCount - 1;
    
    const campoHtml = `
        <div class="card mb-5 campo-dinamico-item" data-campo-index="${campoIndex}">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold">Campo ${campoIndex + 1}</h3>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-light-danger" onclick="removerCampoDinamico(this)">
                        <i class="ki-duotone ki-trash fs-2"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <input type="hidden" name="campos_dinamicos[${campoIndex}][id]" value="${campoId}">
                <input type="hidden" name="campos_dinamicos[${campoIndex}][codigo]" class="campo-codigo-hidden" value="${campo ? campo.codigo : ''}">
                
                <div class="row mb-5">
                    <div class="col-md-6">
                        <label class="required fw-semibold fs-6 mb-2">Nome do Campo</label>
                        <input type="text" name="campos_dinamicos[${campoIndex}][nome]" class="form-control form-control-solid campo-nome" value="${campo ? campo.nome : ''}" required placeholder="Ex: Horário Esperado" />
                        <small class="text-muted">Nome que aparecerá no formulário</small>
                    </div>
                    <div class="col-md-6">
                        <label class="required fw-semibold fs-6 mb-2">Tipo de Campo</label>
                        <select name="campos_dinamicos[${campoIndex}][tipo_campo]" class="form-select form-select-solid campo-tipo" required>
                            <option value="text" ${campo && campo.tipo_campo === 'text' ? 'selected' : ''}>Texto</option>
                            <option value="textarea" ${campo && campo.tipo_campo === 'textarea' ? 'selected' : ''}>Texto Longo</option>
                            <option value="number" ${campo && campo.tipo_campo === 'number' ? 'selected' : ''}>Número</option>
                            <option value="date" ${campo && campo.tipo_campo === 'date' ? 'selected' : ''}>Data</option>
                            <option value="time" ${campo && campo.tipo_campo === 'time' ? 'selected' : ''}>Hora</option>
                            <option value="select" ${campo && campo.tipo_campo === 'select' ? 'selected' : ''}>Lista de Opções</option>
                            <option value="checkbox" ${campo && campo.tipo_campo === 'checkbox' ? 'selected' : ''}>Caixa de Seleção</option>
                            <option value="radio" ${campo && campo.tipo_campo === 'radio' ? 'selected' : ''}>Opções Únicas</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-5">
                    <div class="col-md-12">
                        <label class="fw-semibold fs-6 mb-2">Texto de Ajuda (opcional)</label>
                        <input type="text" name="campos_dinamicos[${campoIndex}][placeholder]" class="form-control form-control-solid" value="${campo ? campo.placeholder : ''}" placeholder="Ex: Informe o horário que deveria ter chegado" />
                        <small class="text-muted">Texto que aparecerá como dica dentro do campo (opcional)</small>
                    </div>
                </div>
                
                <input type="hidden" name="campos_dinamicos[${campoIndex}][label]" class="campo-label-hidden" value="${campo ? (campo.label || campo.nome) : ''}" />
                
                <div class="row mb-5">
                    <div class="col-md-6">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="campos_dinamicos[${campoIndex}][obrigatorio]" value="1" ${campo && campo.obrigatorio == 1 ? 'checked' : ''} />
                            <label class="form-check-label">Este campo é obrigatório</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-semibold fs-6 mb-2">Valor Padrão (opcional)</label>
                        <input type="text" name="campos_dinamicos[${campoIndex}][valor_padrao]" class="form-control form-control-solid" value="${campo ? campo.valor_padrao : ''}" placeholder="Valor que aparecerá preenchido" />
                    </div>
                </div>
                
                <div class="row mb-5 campo-opcoes-container" style="display: ${(campo && (campo.tipo_campo === 'select' || campo.tipo_campo === 'radio')) ? 'block' : 'none'};">
                    <div class="col-md-12">
                        <label class="fw-semibold fs-6 mb-2">Opções Disponíveis</label>
                        <textarea name="campos_dinamicos[${campoIndex}][opcoes_text]" class="form-control form-control-solid campo-opcoes" rows="4" placeholder="Digite uma opção por linha:&#10;Opção 1&#10;Opção 2&#10;Opção 3">${campo && campo.opcoes ? JSON.parse(campo.opcoes).join('\n') : ''}</textarea>
                        <small class="text-muted">Digite uma opção por linha. Exemplo: Sim, Não, Talvez</small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', campoHtml);
    
    // Adiciona listeners para gerar código automaticamente e mostrar/esconder opções
    const campoItem = container.querySelector(`[data-campo-index="${campoIndex}"]`);
    const nomeInput = campoItem.querySelector('.campo-nome');
    const codigoHidden = campoItem.querySelector('.campo-codigo-hidden');
    const labelHidden = campoItem.querySelector('.campo-label-hidden');
    const tipoSelect = campoItem.querySelector('.campo-tipo');
    const opcoesContainer = campoItem.querySelector('.campo-opcoes-container');
    
    // Gera código e atualiza label quando nome muda
    nomeInput.addEventListener('input', function() {
        if (!campo || !campo.id) { // Só gera se for campo novo
            codigoHidden.value = gerarCodigoCampo(this.value);
            labelHidden.value = this.value; // Label igual ao nome
        }
    });
    
    // Se é campo novo, inicializa label
    if (!campo || !campo.id) {
        labelHidden.value = nomeInput.value;
    }
    
    // Mostra/esconde campo de opções baseado no tipo
    tipoSelect.addEventListener('change', function() {
        if (this.value === 'select' || this.value === 'radio') {
            opcoesContainer.style.display = 'block';
        } else {
            opcoesContainer.style.display = 'none';
        }
    });
    
    // Se já tem opções, mostra o container
    if (campo && (campo.tipo_campo === 'select' || campo.tipo_campo === 'radio')) {
        opcoesContainer.style.display = 'block';
    }
}

function removerCampoDinamico(btn) {
    btn.closest('.campo-dinamico-item').remove();
}

// Gera código automaticamente baseado no nome do tipo
function gerarCodigoTipo(nome) {
    return nome
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Remove acentos
        .replace(/[^a-z0-9\s]/g, '') // Remove caracteres especiais
        .replace(/\s+/g, '_') // Substitui espaços por underscore
        .replace(/_+/g, '_') // Remove underscores duplicados
        .replace(/^_|_$/g, ''); // Remove underscores do início/fim
}

// Atualiza código quando nome muda
document.getElementById('nome').addEventListener('input', function() {
    const codigoField = document.getElementById('codigo');
    // Só atualiza se código estiver vazio ou se estiver editando e código for igual ao gerado anteriormente
    if (!codigoField.value || codigoField.value === gerarCodigoTipo(this.value)) {
        codigoField.value = gerarCodigoTipo(this.value);
    }
});

// Processa validações antes de enviar
function processarValidacoes() {
    const validacoes = {};
    
    if (document.getElementById('validacao_nao_permitir_futuro').checked) {
        validacoes.nao_permitir_futuro = true;
    }
    
    if (document.getElementById('validacao_nao_permitir_passado').checked) {
        validacoes.nao_permitir_passado = true;
    }
    
    const maxAtraso = document.getElementById('validacao_max_atraso').value;
    if (maxAtraso) {
        validacoes.max_atraso_minutos = parseInt(maxAtraso);
    }
    
    const minAtraso = document.getElementById('validacao_min_atraso').value;
    if (minAtraso) {
        validacoes.min_atraso_minutos = parseInt(minAtraso);
    }
    
    document.getElementById('validacoes_customizadas').value = JSON.stringify(validacoes);
}

// Carrega validações ao editar
function carregarValidacoes(validacoesJson) {
    if (!validacoesJson) return;
    
    try {
        const validacoes = typeof validacoesJson === 'string' 
            ? JSON.parse(validacoesJson) 
            : validacoesJson;
        
        if (validacoes.nao_permitir_futuro) {
            document.getElementById('validacao_nao_permitir_futuro').checked = true;
        }
        
        if (validacoes.nao_permitir_passado) {
            document.getElementById('validacao_nao_permitir_passado').checked = true;
        }
        
        if (validacoes.max_atraso_minutos) {
            document.getElementById('validacao_max_atraso').value = validacoes.max_atraso_minutos;
        }
        
        if (validacoes.min_atraso_minutos) {
            document.getElementById('validacao_min_atraso').value = validacoes.min_atraso_minutos;
        }
    } catch (e) {
        console.error('Erro ao carregar validações:', e);
    }
}

function editarTipoOcorrencia(tipo) {
    document.getElementById('kt_modal_tipo_ocorrencia_header').querySelector('h2').textContent = 'Editar Tipo de Ocorrência';
    document.getElementById('tipo_ocorrencia_action').value = 'edit';
    document.getElementById('tipo_ocorrencia_id').value = tipo.id;
    document.getElementById('nome').value = tipo.nome || '';
    document.getElementById('codigo').value = tipo.codigo || '';
    document.getElementById('categoria_id').value = tipo.categoria_id || '';
    // Atualiza campo oculto categoria para compatibilidade
    var categoriaSelect = document.getElementById('categoria_id');
    if (categoriaSelect.selectedOptions.length > 0) {
        document.getElementById('categoria').value = categoriaSelect.selectedOptions[0].dataset.codigo || '';
    }
    document.getElementById('severidade').value = tipo.severidade || 'moderada';
    document.getElementById('status').value = tipo.status || 'ativo';
    document.getElementById('template_descricao').value = tipo.template_descricao || '';
    document.getElementById('permite_tempo_atraso').checked = tipo.permite_tempo_atraso == 1;
    document.getElementById('permite_tipo_ponto').checked = tipo.permite_tipo_ponto == 1;
    document.getElementById('requer_aprovacao').checked = tipo.requer_aprovacao == 1;
    document.getElementById('conta_advertencia').checked = tipo.conta_advertencia == 1;
    document.getElementById('calcula_desconto').checked = tipo.calcula_desconto == 1;
    document.getElementById('valor_desconto').value = tipo.valor_desconto || '';
    document.getElementById('notificar_colaborador').checked = tipo.notificar_colaborador != 0;
    document.getElementById('notificar_colaborador_sistema').checked = tipo.notificar_colaborador_sistema != 0;
    document.getElementById('notificar_colaborador_email').checked = tipo.notificar_colaborador_email != 0;
    document.getElementById('notificar_colaborador_push').checked = tipo.notificar_colaborador_push != 0;
    document.getElementById('notificar_gestor').checked = tipo.notificar_gestor != 0;
    document.getElementById('notificar_gestor_sistema').checked = tipo.notificar_gestor_sistema != 0;
    document.getElementById('notificar_gestor_email').checked = tipo.notificar_gestor_email != 0;
    document.getElementById('notificar_gestor_push').checked = tipo.notificar_gestor_push != 0;
    document.getElementById('notificar_rh').checked = tipo.notificar_rh != 0;
    document.getElementById('notificar_rh_sistema').checked = tipo.notificar_rh_sistema != 0;
    document.getElementById('notificar_rh_email').checked = tipo.notificar_rh_email != 0;
    document.getElementById('notificar_rh_push').checked = tipo.notificar_rh_push != 0;
    
    // Atualiza estados dos checkboxes de canais
    setTimeout(function() {
        var notificarColab = document.getElementById('notificar_colaborador');
        var notificarGestor = document.getElementById('notificar_gestor');
        var notificarRh = document.getElementById('notificar_rh');
        
        if (notificarColab && typeof toggleCanaisNotificacao === 'function') {
            toggleCanaisNotificacao(notificarColab, 'notificar_colaborador');
        }
        if (notificarGestor && typeof toggleCanaisNotificacao === 'function') {
            toggleCanaisNotificacao(notificarGestor, 'notificar_gestor');
        }
        if (notificarRh && typeof toggleCanaisNotificacao === 'function') {
            toggleCanaisNotificacao(notificarRh, 'notificar_rh');
        }
    }, 200);
    
    // Carrega validações de forma visual
    carregarValidacoes(tipo.validacoes_customizadas);
    
    // Mostra/esconde campo de valor desconto
    toggleCampoValorDesconto();
    
    // Carrega campos dinâmicos
    carregarCamposDinamicos(tipo.id);
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_tipo_ocorrencia'));
    modal.show();
}

function toggleCampoValorDesconto() {
    const calculaDesconto = document.getElementById('calcula_desconto').checked;
    document.getElementById('campo_valor_desconto').style.display = calculaDesconto ? 'block' : 'none';
}

function carregarCamposDinamicos(tipoId) {
    // Limpa container
    document.getElementById('campos_dinamicos_container').innerHTML = '';
    camposDinamicosCount = 0;
    
    // Busca campos via AJAX
    fetch(`api/ocorrencias/get_campos_dinamicos.php?tipo_id=${tipoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.campos) {
                data.campos.forEach(campo => {
                    adicionarCampoDinamico(campo);
                });
            }
        })
        .catch(error => {
            console.error('Erro ao carregar campos dinâmicos:', error);
        });
}

// Event listeners
document.getElementById('calcula_desconto').addEventListener('change', toggleCampoValorDesconto);

// Processa validações antes de enviar formulário
document.getElementById('kt_modal_tipo_ocorrencia_form').addEventListener('submit', function(e) {
    processarValidacoes();
    
    // Gera código se estiver vazio
    const nome = document.getElementById('nome').value;
    const codigo = document.getElementById('codigo').value;
    if (!codigo && nome) {
        document.getElementById('codigo').value = gerarCodigoTipo(nome);
    }
});

// Gera código automaticamente ao digitar nome (se código estiver vazio)
document.getElementById('nome').addEventListener('input', function() {
    const codigoField = document.getElementById('codigo');
    if (!codigoField.value) {
        codigoField.value = gerarCodigoTipo(this.value);
    }
});

// Inicializa tooltips do Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Atualiza campo categoria oculto quando categoria_id mudar
    var categoriaIdSelect = document.getElementById('categoria_id');
    if (categoriaIdSelect) {
        categoriaIdSelect.addEventListener('change', function() {
            var categoriaHidden = document.getElementById('categoria');
            if (categoriaHidden && this.selectedOptions.length > 0) {
                categoriaHidden.value = this.selectedOptions[0].dataset.codigo || '';
            }
        });
    }
});

// Reinicializa tooltips quando modal é aberto
document.getElementById('kt_modal_tipo_ocorrencia').addEventListener('shown.bs.modal', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Reset modal ao fechar
document.getElementById('kt_modal_tipo_ocorrencia').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_tipo_ocorrencia_form').reset();
    document.getElementById('kt_modal_tipo_ocorrencia_header').querySelector('h2').textContent = 'Novo Tipo de Ocorrência';
    document.getElementById('tipo_ocorrencia_action').value = 'add';
    document.getElementById('tipo_ocorrencia_id').value = '';
});

// Controla checkboxes de canais de notificação
function toggleCanaisNotificacao(checkboxPrincipal, prefixo) {
    var sistema = document.getElementById(prefixo + '_sistema');
    var email = document.getElementById(prefixo + '_email');
    var push = document.getElementById(prefixo + '_push');
    
    if (sistema && email && push) {
        var habilitado = checkboxPrincipal.checked;
        sistema.disabled = !habilitado;
        email.disabled = !habilitado;
        push.disabled = !habilitado;
        
        // Se desabilitar, desmarca os canais também
        if (!habilitado) {
            sistema.checked = false;
            email.checked = false;
            push.checked = false;
        }
    }
}

// Adiciona listeners para checkboxes principais
document.addEventListener('DOMContentLoaded', function() {
    // Colaborador
    var notificarColab = document.getElementById('notificar_colaborador');
    if (notificarColab) {
        notificarColab.addEventListener('change', function() {
            toggleCanaisNotificacao(this, 'notificar_colaborador');
        });
        // Inicializa estado
        toggleCanaisNotificacao(notificarColab, 'notificar_colaborador');
    }
    
    // Gestor
    var notificarGestor = document.getElementById('notificar_gestor');
    if (notificarGestor) {
        notificarGestor.addEventListener('change', function() {
            toggleCanaisNotificacao(this, 'notificar_gestor');
        });
        // Inicializa estado
        toggleCanaisNotificacao(notificarGestor, 'notificar_gestor');
    }
    
    // RH
    var notificarRh = document.getElementById('notificar_rh');
    if (notificarRh) {
        notificarRh.addEventListener('change', function() {
            toggleCanaisNotificacao(this, 'notificar_rh');
        });
        // Inicializa estado
        toggleCanaisNotificacao(notificarRh, 'notificar_rh');
    }
});

// Reinicializa quando modal é aberto
document.getElementById('kt_modal_tipo_ocorrencia').addEventListener('shown.bs.modal', function() {
    setTimeout(function() {
        var notificarColab = document.getElementById('notificar_colaborador');
        var notificarGestor = document.getElementById('notificar_gestor');
        var notificarRh = document.getElementById('notificar_rh');
        
        if (notificarColab) toggleCanaisNotificacao(notificarColab, 'notificar_colaborador');
        if (notificarGestor) toggleCanaisNotificacao(notificarGestor, 'notificar_gestor');
        if (notificarRh) toggleCanaisNotificacao(notificarRh, 'notificar_rh');
    }, 100);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

