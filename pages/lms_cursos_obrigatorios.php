<?php
/**
 * Gestão de Cursos Obrigatórios
 */

$page_title = 'Cursos Obrigatórios';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_cursos_obrigatorios.php');

require_once __DIR__ . '/../includes/lms_obrigatorios.php';
require_once __DIR__ . '/../includes/select_colaborador.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'atribuir') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        $colaborador_ids = $_POST['colaborador_ids'] ?? [];
        $prazo_dias = !empty($_POST['prazo_dias']) ? (int)$_POST['prazo_dias'] : null;
        $data_limite = !empty($_POST['data_limite']) ? $_POST['data_limite'] : null;
        
        if (!$curso_id || empty($colaborador_ids)) {
            redirect('lms_cursos_obrigatorios.php', 'Selecione um curso e pelo menos um colaborador!', 'error');
        }
        
        try {
            $atribuidos = 0;
            $erros = 0;
            foreach ($colaborador_ids as $colab_id) {
                $colab_id = (int)$colab_id;
                if ($colab_id > 0) {
                    // Calcula data limite
                    $data_limite_final = null;
                    if ($data_limite) {
                        $data_limite_final = $data_limite;
                    } elseif ($prazo_dias) {
                        $data_base = new DateTime();
                        $data_base->modify("+{$prazo_dias} days");
                        $data_limite_final = $data_base->format('Y-m-d');
                    }
                    
                    // Verifica se já está atribuído
                    $stmt_check = $pdo->prepare("SELECT id FROM cursos_obrigatorios_colaboradores WHERE curso_id = ? AND colaborador_id = ?");
                    $stmt_check->execute([$curso_id, $colab_id]);
                    if (!$stmt_check->fetch()) {
                        $stmt = $pdo->prepare("
                            INSERT INTO cursos_obrigatorios_colaboradores 
                            (curso_id, colaborador_id, atribuido_por_usuario_id, data_atribuicao, data_limite, status)
                            VALUES (?, ?, ?, CURDATE(), ?, 'pendente')
                        ");
                        $stmt->execute([$curso_id, $colab_id, $usuario['id'], $data_limite_final]);
                        $atribuidos++;
                    } else {
                        $erros++;
                    }
                }
            }
            $msg = "Curso atribuído com sucesso para {$atribuidos} colaborador(es)!";
            if ($erros > 0) {
                $msg .= " {$erros} já estavam atribuídos.";
            }
            redirect('lms_cursos_obrigatorios.php', $msg, 'success');
        } catch (Exception $e) {
            error_log("Erro ao atribuir curso: " . $e->getMessage());
            redirect('lms_cursos_obrigatorios.php', 'Erro ao atribuir curso.', 'error');
        }
    } elseif ($action === 'cancelar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE cursos_obrigatorios_colaboradores SET status = 'cancelado' WHERE id = ?");
                $stmt->execute([$id]);
                redirect('lms_cursos_obrigatorios.php', 'Atribuição cancelada com sucesso!', 'success');
            } catch (PDOException $e) {
                error_log("Erro ao cancelar: " . $e->getMessage());
                redirect('lms_cursos_obrigatorios.php', 'Erro ao cancelar atribuição.', 'error');
            }
        }
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_curso = $_GET['curso'] ?? '';
$filtro_colaborador = $_GET['colaborador'] ?? '';

$where = [];
$params = [];

if ($filtro_status) {
    $where[] = "coc.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_curso) {
    $where[] = "coc.curso_id = ?";
    $params[] = $filtro_curso;
}

if ($filtro_colaborador) {
    $where[] = "(c.nome_completo LIKE ? OR c.cpf LIKE ?)";
    $busca = "%{$filtro_colaborador}%";
    $params[] = $busca;
    $params[] = $busca;
}

// Restrições por role
if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "c.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Busca atribuições
$sql = "
    SELECT coc.*,
           c.titulo as curso_titulo,
           c.imagem_capa,
           col.nome_completo as colaborador_nome,
           col.cpf as colaborador_cpf,
           e.nome_fantasia as empresa_nome,
           DATEDIFF(coc.data_limite, CURDATE()) as dias_restantes,
           (SELECT COUNT(*) FROM progresso_colaborador pc 
            WHERE pc.colaborador_id = coc.colaborador_id 
            AND pc.curso_id = coc.curso_id 
            AND pc.status = 'concluido') as aulas_concluidas,
           (SELECT COUNT(*) FROM aulas a WHERE a.curso_id = coc.curso_id AND a.status = 'publicado') as total_aulas
    FROM cursos_obrigatorios_colaboradores coc
    INNER JOIN cursos c ON c.id = coc.curso_id
    INNER JOIN colaboradores col ON col.id = coc.colaborador_id
    LEFT JOIN empresas e ON col.empresa_id = e.id
    $where_sql
    ORDER BY coc.data_limite ASC, coc.status ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $atribuicoes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar atribuições: " . $e->getMessage());
    $atribuicoes = [];
}

// Busca cursos para filtro
$stmt = $pdo->query("SELECT id, titulo FROM cursos WHERE status = 'publicado' ORDER BY titulo");
$cursos_filtro = $stmt->fetchAll();

// Busca cursos obrigatórios para atribuição
$stmt = $pdo->query("SELECT id, titulo FROM cursos WHERE obrigatorio = 1 AND status = 'publicado' ORDER BY titulo");
$cursos_obrigatorios = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Cursos Obrigatórios</h1>
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
                <li class="breadcrumb-item text-gray-900">Cursos Obrigatórios</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAtribuir">
                <i class="ki-duotone ki-plus fs-2"></i>
                Atribuir Curso
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Filtros-->
        <div class="card mb-5">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Buscar Colaborador</label>
                        <input type="text" name="colaborador" class="form-control" placeholder="Nome ou CPF..." value="<?= htmlspecialchars($filtro_colaborador) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Curso</label>
                        <select name="curso" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($cursos_filtro as $curso): ?>
                            <option value="<?= $curso['id'] ?>" <?= $filtro_curso == $curso['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($curso['titulo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="pendente" <?= $filtro_status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="em_andamento" <?= $filtro_status == 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                            <option value="concluido" <?= $filtro_status == 'concluido' ? 'selected' : '' ?>>Concluído</option>
                            <option value="vencido" <?= $filtro_status == 'vencido' ? 'selected' : '' ?>>Vencido</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Filtros-->
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Atribuições de Cursos Obrigatórios</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($atribuicoes) ?> atribuição(ões)</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Colaborador</th>
                                <th class="min-w-200px">Curso</th>
                                <th class="min-w-100px">Progresso</th>
                                <th class="min-w-100px">Prazo</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php if (empty($atribuicoes)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-10">
                                    <div class="text-muted">Nenhuma atribuição encontrada</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($atribuicoes as $atrib): ?>
                                <?php
                                $percentual = $atrib['total_aulas'] > 0 
                                    ? round(($atrib['aulas_concluidas'] / $atrib['total_aulas']) * 100, 0) 
                                    : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($atrib['colaborador_nome']) ?></div>
                                        <div class="text-muted fs-7"><?= htmlspecialchars($atrib['colaborador_cpf']) ?></div>
                                        <?php if ($atrib['empresa_nome']): ?>
                                        <div class="text-muted fs-7"><?= htmlspecialchars($atrib['empresa_nome']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($atrib['imagem_capa']): ?>
                                            <img src="../<?= htmlspecialchars($atrib['imagem_capa']) ?>" class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;" alt="">
                                            <?php endif; ?>
                                            <div class="fw-bold"><?= htmlspecialchars($atrib['curso_titulo']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress w-100 me-2" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= $percentual ?>%">
                                                    <?= $percentual ?>%
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-muted fs-7 mt-1"><?= $atrib['aulas_concluidas'] ?>/<?= $atrib['total_aulas'] ?> aulas</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= date('d/m/Y', strtotime($atrib['data_limite'])) ?></div>
                                        <?php if ($atrib['dias_restantes'] < 0): ?>
                                        <span class="badge badge-danger">Vencido há <?= abs($atrib['dias_restantes']) ?> dia(s)</span>
                                        <?php elseif ($atrib['dias_restantes'] <= 7): ?>
                                        <span class="badge badge-warning">Vence em <?= $atrib['dias_restantes'] ?> dia(s)</span>
                                        <?php else: ?>
                                        <span class="text-muted fs-7"><?= $atrib['dias_restantes'] ?> dias restantes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_classes = [
                                            'pendente' => 'warning',
                                            'em_andamento' => 'primary',
                                            'concluido' => 'success',
                                            'vencido' => 'danger',
                                            'cancelado' => 'secondary'
                                        ];
                                        $status_class = $status_classes[$atrib['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>"><?= ucfirst($atrib['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($atrib['status'] !== 'cancelado' && $atrib['status'] !== 'concluido'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja cancelar esta atribuição?');">
                                            <input type="hidden" name="action" value="cancelar">
                                            <input type="hidden" name="id" value="<?= $atrib['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="ki-duotone ki-cross fs-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal Atribuir-->
<div class="modal fade" id="modalAtribuir" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formAtribuir">
                <input type="hidden" name="action" value="atribuir">
                
                <div class="modal-header">
                    <h2 class="modal-title">Atribuir Curso Obrigatório</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-5">
                        <label class="form-label">Curso *</label>
                        <select name="curso_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($cursos_obrigatorios as $curso): ?>
                            <option value="<?= $curso['id'] ?>"><?= htmlspecialchars($curso['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Colaboradores *</label>
                        <select name="colaborador_ids[]" class="form-select" multiple required style="height: 200px;" id="selectColaboradores">
                            <?php
                            $colaboradores = get_colaboradores_disponiveis($pdo, $usuario);
                            foreach ($colaboradores as $colab):
                            ?>
                            <option value="<?= $colab['id'] ?>"><?= htmlspecialchars($colab['nome_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Use Ctrl+Click (Windows) ou Cmd+Click (Mac) para selecionar múltiplos colaboradores</div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Prazo (dias)</label>
                            <input type="number" name="prazo_dias" class="form-control" min="1" placeholder="Ex: 30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ou Data Limite</label>
                            <input type="date" name="data_limite" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Atribuir</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal Atribuir-->


<?php require_once __DIR__ . '/../includes/footer.php'; ?>

