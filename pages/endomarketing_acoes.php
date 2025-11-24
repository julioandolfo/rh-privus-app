<?php
/**
 * Ações de Endomarketing
 */

$page_title = 'Ações de Endomarketing';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('endomarketing_acoes.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'adicionar') {
        $data_comemorativa_id = !empty($_POST['data_comemorativa_id']) ? (int)$_POST['data_comemorativa_id'] : null;
        $titulo = sanitize($_POST['titulo'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $tipo_acao = $_POST['tipo_acao'] ?? 'evento';
        $data_inicio = $_POST['data_inicio'] ?? null;
        $data_fim = $_POST['data_fim'] ?? null;
        $orcamento = !empty($_POST['orcamento']) ? str_replace(['.', ','], ['', '.'], $_POST['orcamento']) : null;
        $responsavel_id = !empty($_POST['responsavel_id']) ? (int)$_POST['responsavel_id'] : null;
        $publico_alvo = $_POST['publico_alvo'] ?? 'todos';
        $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
        $setor_id = !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null;
        $cargo_id = !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null;
        
        if (empty($titulo) || empty($data_inicio)) {
            redirect('endomarketing_acoes.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO endomarketing_acoes 
                (data_comemorativa_id, titulo, descricao, tipo_acao, data_inicio, data_fim, orcamento, responsavel_id, publico_alvo, empresa_id, setor_id, cargo_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'planejado')
            ");
            $stmt->execute([
                $data_comemorativa_id, $titulo, $descricao, $tipo_acao, $data_inicio, $data_fim, 
                $orcamento, $responsavel_id, $publico_alvo, $empresa_id, $setor_id, $cargo_id
            ]);
            
            $acao_id = $pdo->lastInsertId();
            
            // Processa tarefas se houver
            if (isset($_POST['tarefas']) && is_array($_POST['tarefas'])) {
                foreach ($_POST['tarefas'] as $tarefa) {
                    if (!empty($tarefa['tarefa'])) {
                        $stmt_tarefa = $pdo->prepare("
                            INSERT INTO endomarketing_acoes_tarefas (acao_id, tarefa, responsavel_id, prazo)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt_tarefa->execute([
                            $acao_id,
                            sanitize($tarefa['tarefa']),
                            !empty($tarefa['responsavel_id']) ? (int)$tarefa['responsavel_id'] : null,
                            !empty($tarefa['prazo']) ? $tarefa['prazo'] : null
                        ]);
                    }
                }
            }
            
            redirect('endomarketing_acoes.php', 'Ação cadastrada com sucesso!', 'success');
        } catch (PDOException $e) {
            redirect('endomarketing_acoes.php', 'Erro ao cadastrar: ' . $e->getMessage(), 'error');
        }
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';

// Busca ações
$where = [];
$params = [];

if ($usuario['role'] === 'RH') {
    $where[] = "(ea.publico_alvo = 'todos' OR (ea.publico_alvo = 'empresa' AND ea.empresa_id = ?))";
    $params[] = $usuario['empresa_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $where[] = "(ea.publico_alvo = 'todos' OR (ea.publico_alvo = 'setor' AND ea.setor_id = ?))";
    $params[] = $setor_id;
}

if ($filtro_status) {
    $where[] = "ea.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_tipo) {
    $where[] = "ea.tipo_acao = ?";
    $params[] = $filtro_tipo;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT ea.*,
           dc.nome as data_comemorativa_nome,
           u.nome as responsavel_nome,
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           car.nome_cargo,
           (SELECT COUNT(*) FROM endomarketing_acoes_tarefas WHERE acao_id = ea.id) as total_tarefas,
           (SELECT COUNT(*) FROM endomarketing_acoes_tarefas WHERE acao_id = ea.id AND concluida = 1) as tarefas_concluidas
    FROM endomarketing_acoes ea
    LEFT JOIN datas_comemorativas dc ON ea.data_comemorativa_id = dc.id
    LEFT JOIN usuarios u ON ea.responsavel_id = u.id
    LEFT JOIN empresas e ON ea.empresa_id = e.id
    LEFT JOIN setores s ON ea.setor_id = s.id
    LEFT JOIN cargos car ON ea.cargo_id = car.id
    $where_sql
    ORDER BY ea.data_inicio DESC, ea.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$acoes = $stmt->fetchAll();

// Busca datas comemorativas para select
$stmt_datas = $pdo->query("SELECT id, nome, data_comemoracao FROM datas_comemorativas WHERE ativo = 1 ORDER BY MONTH(data_comemoracao), DAY(data_comemoracao)");
$datas_comemorativas = $stmt_datas->fetchAll();

// Busca usuários para responsáveis
if ($usuario['role'] === 'ADMIN') {
    $stmt_usuarios = $pdo->query("SELECT id, nome FROM usuarios WHERE status = 'ativo' ORDER BY nome");
} else {
    $stmt_usuarios = $pdo->prepare("SELECT id, nome FROM usuarios WHERE id = ? AND status = 'ativo'");
    $stmt_usuarios->execute([$usuario['id']]);
}
$usuarios_responsaveis = $stmt_usuarios->fetchAll();

// Busca empresas para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt_empresas->fetchAll();
} else {
    $empresas = [];
}

// Busca setores para filtro
if ($usuario['role'] === 'ADMIN') {
    $stmt_setores = $pdo->query("SELECT id, nome_setor FROM setores WHERE status = 'ativo' ORDER BY nome_setor");
    $setores = $stmt_setores->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id IN ($placeholders) AND status = 'ativo' ORDER BY nome_setor");
        $stmt_setores->execute($usuario['empresas_ids']);
        $setores = $stmt_setores->fetchAll();
    } else {
        $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
        $stmt_setores->execute([$usuario['empresa_id'] ?? 0]);
        $setores = $stmt_setores->fetchAll();
    }
} else {
    $stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE id = ? AND status = 'ativo'");
    $stmt_setores->execute([$setor_id]);
    $setores = $stmt_setores->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Ações de Endomarketing</h1>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_adicionar_acao">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Nova Ação
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        <!--begin::Filtros-->
        <div class="card card-flush mb-5">
            <div class="card-body pt-6">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="planejado" <?= $filtro_status === 'planejado' ? 'selected' : '' ?>>Planejado</option>
                            <option value="em_andamento" <?= $filtro_status === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                            <option value="concluido" <?= $filtro_status === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                            <option value="cancelado" <?= $filtro_status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="evento" <?= $filtro_tipo === 'evento' ? 'selected' : '' ?>>Evento</option>
                            <option value="premiacao" <?= $filtro_tipo === 'premiacao' ? 'selected' : '' ?>>Premiação</option>
                            <option value="comunicacao" <?= $filtro_tipo === 'comunicacao' ? 'selected' : '' ?>>Comunicação</option>
                            <option value="decoracao" <?= $filtro_tipo === 'decoracao' ? 'selected' : '' ?>>Decoração</option>
                            <option value="brinde" <?= $filtro_tipo === 'brinde' ? 'selected' : '' ?>>Brinde</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                        <a href="endomarketing_acoes.php" class="btn btn-light">Limpar</a>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Filtros-->
        
        <!--begin::Card-->
        <div class="card card-flush">
            <div class="card-header pt-7">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-800">Ações</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Gerencie as ações de endomarketing</span>
                </h3>
            </div>
            <div class="card-body pt-6">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5 datatable">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Título</th>
                                <th class="min-w-150px">Data Comemorativa</th>
                                <th class="min-w-100px">Tipo</th>
                                <th class="min-w-100px">Data Início</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-150px">Responsável</th>
                                <th class="min-w-100px">Progresso</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($acoes as $acao_item): ?>
                            <tr>
                                <td>
                                    <span class="text-gray-800 fw-bold"><?= htmlspecialchars($acao_item['titulo']) ?></span>
                                    <?php if (!empty($acao_item['descricao'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(substr($acao_item['descricao'], 0, 100)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($acao_item['data_comemorativa_nome']): ?>
                                        <span class="text-gray-800"><?= htmlspecialchars($acao_item['data_comemorativa_nome']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $tipos_acao = [
                                        'evento' => ['badge' => 'badge-light-primary', 'text' => 'Evento'],
                                        'premiacao' => ['badge' => 'badge-light-warning', 'text' => 'Premiação'],
                                        'comunicacao' => ['badge' => 'badge-light-info', 'text' => 'Comunicação'],
                                        'decoracao' => ['badge' => 'badge-light-success', 'text' => 'Decoração'],
                                        'brinde' => ['badge' => 'badge-light-danger', 'text' => 'Brinde'],
                                        'reuniao' => ['badge' => 'badge-light-secondary', 'text' => 'Reunião'],
                                        'outro' => ['badge' => 'badge-light-dark', 'text' => 'Outro']
                                    ];
                                    $tipo_info = $tipos_acao[$acao_item['tipo_acao']] ?? $tipos_acao['outro'];
                                    ?>
                                    <span class="badge <?= $tipo_info['badge'] ?>"><?= $tipo_info['text'] ?></span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($acao_item['data_inicio'])) ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'planejado' => 'badge-light-info',
                                        'em_andamento' => 'badge-light-warning',
                                        'concluido' => 'badge-light-success',
                                        'cancelado' => 'badge-light-danger',
                                        'adiado' => 'badge-light-secondary'
                                    ];
                                    $status_badge = $status_badges[$acao_item['status']] ?? 'badge-light-secondary';
                                    $status_text = ucfirst(str_replace('_', ' ', $acao_item['status']));
                                    ?>
                                    <span class="badge <?= $status_badge ?>"><?= $status_text ?></span>
                                </td>
                                <td>
                                    <?php if ($acao_item['responsavel_nome']): ?>
                                        <span class="text-gray-800"><?= htmlspecialchars($acao_item['responsavel_nome']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Não definido</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $total_tarefas = $acao_item['total_tarefas'] ?? 0;
                                    $tarefas_concluidas = $acao_item['tarefas_concluidas'] ?? 0;
                                    $percentual = $total_tarefas > 0 ? round(($tarefas_concluidas / $total_tarefas) * 100) : 0;
                                    ?>
                                    <div class="d-flex align-items-center">
                                        <div class="progress w-100 me-2" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $percentual ?>%" aria-valuenow="<?= $percentual ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= $percentual ?>%
                                            </div>
                                        </div>
                                        <span class="text-muted fs-7"><?= $tarefas_concluidas ?>/<?= $total_tarefas ?></span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <a href="endomarketing_acao_view.php?id=<?= $acao_item['id'] ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                        Ver Detalhes
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Card-->
    </div>
</div>
<!--end::Post-->

<!--begin::Modal Adicionar Ação-->
<div class="modal fade" id="kt_modal_adicionar_acao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <form id="form_adicionar_acao" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="adicionar">
                <div class="modal-header">
                    <h2 class="fw-bold">Nova Ação de Endomarketing</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <label class="form-label">Data Comemorativa (opcional)</label>
                        <select name="data_comemorativa_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($datas_comemorativas as $data): ?>
                                <option value="<?= $data['id'] ?>">
                                    <?= htmlspecialchars($data['nome']) ?> - <?= date('d/m', strtotime($data['data_comemoracao'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-5">
                        <label class="form-label required">Título</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row mb-5">
                        <div class="col-md-4">
                            <label class="form-label required">Tipo de Ação</label>
                            <select name="tipo_acao" class="form-select" required>
                                <option value="evento">Evento</option>
                                <option value="premiacao">Premiação</option>
                                <option value="comunicacao">Comunicação</option>
                                <option value="decoracao">Decoração</option>
                                <option value="brinde">Brinde</option>
                                <option value="reuniao">Reunião</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Orçamento</label>
                            <input type="text" name="orcamento" class="form-control" placeholder="0,00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Responsável</label>
                            <select name="responsavel_id" class="form-select">
                                <option value="">Selecione...</option>
                                <?php foreach ($usuarios_responsaveis as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $user['id'] == $usuario['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-5">
                        <label class="form-label required">Público Alvo</label>
                        <select name="publico_alvo" id="publico_alvo" class="form-select" required>
                            <option value="todos">Todos</option>
                            <option value="empresa">Empresa</option>
                            <option value="setor">Setor</option>
                            <option value="cargo">Cargo</option>
                            <option value="especifico">Específico</option>
                        </select>
                    </div>
                    <div class="mb-5" id="empresa_group" style="display: none;">
                        <label class="form-label">Empresa</label>
                        <select name="empresa_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nome_fantasia']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-5" id="setor_group" style="display: none;">
                        <label class="form-label">Setor</label>
                        <select name="setor_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($setores as $setor): ?>
                                <option value="<?= $setor['id'] ?>"><?= htmlspecialchars($setor['nome_setor']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-5" id="cargo_group" style="display: none;">
                        <label class="form-label">Cargo</label>
                        <select name="cargo_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php
                            $stmt_cargos = $pdo->query("SELECT id, nome_cargo FROM cargos WHERE status = 'ativo' ORDER BY nome_cargo");
                            $cargos = $stmt_cargos->fetchAll();
                            foreach ($cargos as $cargo):
                            ?>
                                <option value="<?= $cargo['id'] ?>"><?= htmlspecialchars($cargo['nome_cargo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <hr>
                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Tarefas/Checklist</h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="adicionarTarefa()">
                                <i class="ki-duotone ki-plus fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Tarefa
                            </button>
                        </div>
                        <div id="tarefas_container">
                            <!-- Tarefas serão adicionadas aqui -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
let tarefasCount = 0;

function adicionarTarefa() {
    tarefasCount++;
    const container = document.getElementById('tarefas_container');
    const tarefaDiv = document.createElement('div');
    tarefaDiv.className = 'card card-flush mb-3';
    tarefaDiv.id = 'tarefa_' + tarefasCount;
    tarefaDiv.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Tarefa ${tarefasCount}</h6>
                <button type="button" class="btn btn-sm btn-light-danger" onclick="removerTarefa(${tarefasCount})">
                    <i class="ki-duotone ki-trash fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    Remover
                </button>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tarefa *</label>
                    <input type="text" name="tarefas[${tarefasCount}][tarefa]" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Responsável</label>
                    <select name="tarefas[${tarefasCount}][responsavel_id]" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($usuarios_responsaveis as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Prazo</label>
                    <input type="date" name="tarefas[${tarefasCount}][prazo]" class="form-control">
                </div>
            </div>
        </div>
    `;
    container.appendChild(tarefaDiv);
}

function removerTarefa(id) {
    const tarefaDiv = document.getElementById('tarefa_' + id);
    if (tarefaDiv) {
        tarefaDiv.remove();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const publicoSelect = document.getElementById('publico_alvo');
    const empresaGroup = document.getElementById('empresa_group');
    const setorGroup = document.getElementById('setor_group');
    const cargoGroup = document.getElementById('cargo_group');
    
    if (publicoSelect) {
        publicoSelect.addEventListener('change', function() {
            empresaGroup.style.display = 'none';
            setorGroup.style.display = 'none';
            cargoGroup.style.display = 'none';
            
            if (this.value === 'empresa') {
                empresaGroup.style.display = 'block';
            } else if (this.value === 'setor') {
                setorGroup.style.display = 'block';
            } else if (this.value === 'cargo') {
                cargoGroup.style.display = 'block';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

