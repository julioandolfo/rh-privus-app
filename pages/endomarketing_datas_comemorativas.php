<?php
/**
 * Datas Comemorativas - Endomarketing
 */

$page_title = 'Datas Comemorativas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('endomarketing_datas_comemorativas.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'adicionar') {
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $data_comemoracao = $_POST['data_comemoracao'] ?? null;
        $tipo = $_POST['tipo'] ?? 'nacional';
        $recorrente = isset($_POST['recorrente']) ? 1 : 0;
        $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
        $setor_id = !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null;
        
        if (empty($nome) || empty($data_comemoracao)) {
            redirect('endomarketing_datas_comemorativas.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO datas_comemorativas (nome, descricao, data_comemoracao, tipo, recorrente, empresa_id, setor_id, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$nome, $descricao, $data_comemoracao, $tipo, $recorrente, $empresa_id, $setor_id]);
            
            redirect('endomarketing_datas_comemorativas.php', 'Data comemorativa cadastrada com sucesso!', 'success');
        } catch (PDOException $e) {
            redirect('endomarketing_datas_comemorativas.php', 'Erro ao cadastrar: ' . $e->getMessage(), 'error');
        }
    } elseif ($acao === 'editar') {
        $id = (int)$_POST['id'];
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $data_comemoracao = $_POST['data_comemoracao'] ?? null;
        $tipo = $_POST['tipo'] ?? 'nacional';
        $recorrente = isset($_POST['recorrente']) ? 1 : 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
        $setor_id = !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE datas_comemorativas 
                SET nome = ?, descricao = ?, data_comemoracao = ?, tipo = ?, recorrente = ?, ativo = ?, empresa_id = ?, setor_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$nome, $descricao, $data_comemoracao, $tipo, $recorrente, $ativo, $empresa_id, $setor_id, $id]);
            
            redirect('endomarketing_datas_comemorativas.php', 'Data comemorativa atualizada com sucesso!', 'success');
        } catch (PDOException $e) {
            redirect('endomarketing_datas_comemorativas.php', 'Erro ao atualizar: ' . $e->getMessage(), 'error');
        }
    } elseif ($acao === 'excluir') {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM datas_comemorativas WHERE id = ?");
            $stmt->execute([$id]);
            
            redirect('endomarketing_datas_comemorativas.php', 'Data comemorativa excluída com sucesso!', 'success');
        } catch (PDOException $e) {
            redirect('endomarketing_datas_comemorativas.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca datas comemorativas
$where = [];
$params = [];

if ($usuario['role'] === 'RH') {
    $where[] = "(dc.tipo IN ('nacional', 'internacional', 'empresa') AND (dc.empresa_id IS NULL OR dc.empresa_id = ?))";
    $params[] = $usuario['empresa_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $where[] = "(dc.tipo IN ('nacional', 'internacional', 'setor') AND (dc.setor_id IS NULL OR dc.setor_id = ?))";
    $params[] = $setor_id;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT dc.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor
    FROM datas_comemorativas dc
    LEFT JOIN empresas e ON dc.empresa_id = e.id
    LEFT JOIN setores s ON dc.setor_id = s.id
    $where_sql
    ORDER BY MONTH(dc.data_comemoracao), DAY(dc.data_comemoracao)
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$datas = $stmt->fetchAll();

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
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Datas Comemorativas</h1>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_adicionar_data">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Adicionar Data
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        <!--begin::Card-->
        <div class="card card-flush">
            <div class="card-header pt-7">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-800">Datas Comemorativas</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Gerencie as datas comemorativas da empresa</span>
                </h3>
            </div>
            <div class="card-body pt-6">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5 datatable">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-100px">Data</th>
                                <th class="min-w-200px">Nome</th>
                                <th class="min-w-100px">Tipo</th>
                                <th class="min-w-100px">Recorrente</th>
                                <th class="min-w-150px">Vinculado a</th>
                                <th class="min-w-100px">Status</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($datas as $data): ?>
                            <tr>
                                <td>
                                    <?php
                                    $data_formatada = date('d/m', strtotime($data['data_comemoracao']));
                                    echo $data_formatada;
                                    ?>
                                </td>
                                <td>
                                    <span class="text-gray-800 fw-bold"><?= htmlspecialchars($data['nome']) ?></span>
                                    <?php if (!empty($data['descricao'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($data['descricao']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $tipos = [
                                        'nacional' => ['badge' => 'badge-light-primary', 'text' => 'Nacional'],
                                        'internacional' => ['badge' => 'badge-light-info', 'text' => 'Internacional'],
                                        'empresa' => ['badge' => 'badge-light-success', 'text' => 'Empresa'],
                                        'setor' => ['badge' => 'badge-light-warning', 'text' => 'Setor'],
                                        'personalizada' => ['badge' => 'badge-light-secondary', 'text' => 'Personalizada']
                                    ];
                                    $tipo_info = $tipos[$data['tipo']] ?? $tipos['personalizada'];
                                    ?>
                                    <span class="badge <?= $tipo_info['badge'] ?>"><?= $tipo_info['text'] ?></span>
                                </td>
                                <td>
                                    <?php if ($data['recorrente']): ?>
                                        <span class="badge badge-light-success">Sim</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($data['empresa_nome']): ?>
                                        <span class="text-gray-800"><?= htmlspecialchars($data['empresa_nome']) ?></span>
                                    <?php elseif ($data['nome_setor']): ?>
                                        <span class="text-gray-800"><?= htmlspecialchars($data['nome_setor']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($data['ativo']): ?>
                                        <span class="badge badge-light-success">Ativa</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                        Ações
                                        <i class="ki-duotone ki-down fs-5 ms-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </a>
                                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                                        <div class="menu-item px-3">
                                            <a href="#" class="menu-link px-3" onclick="editarData(<?= $data['id'] ?>)">Editar</a>
                                        </div>
                                        <div class="menu-item px-3">
                                            <a href="#" class="menu-link px-3 text-danger" onclick="excluirData(<?= $data['id'] ?>, '<?= htmlspecialchars($data['nome'], ENT_QUOTES) ?>')">Excluir</a>
                                        </div>
                                    </div>
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

<!--begin::Modal Adicionar Data-->
<div class="modal fade" id="kt_modal_adicionar_data" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <form id="form_adicionar_data" method="POST">
                <input type="hidden" name="acao" value="adicionar">
                <div class="modal-header">
                    <h2 class="fw-bold">Adicionar Data Comemorativa</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <label class="form-label required">Nome da Data Comemorativa</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-5">
                        <label class="form-label required">Data</label>
                        <input type="date" name="data_comemoracao" class="form-control" required>
                        <small class="text-muted">Apenas dia/mês será considerado (ano será ignorado para datas recorrentes)</small>
                    </div>
                    <div class="mb-5">
                        <label class="form-label required">Tipo</label>
                        <select name="tipo" id="tipo_data" class="form-select" required>
                            <option value="nacional">Nacional</option>
                            <option value="internacional">Internacional</option>
                            <option value="empresa">Empresa</option>
                            <option value="setor">Setor</option>
                            <option value="personalizada">Personalizada</option>
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
                    <div class="mb-5">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="recorrente" id="recorrente" value="1" checked>
                            <label class="form-check-label" for="recorrente">Recorrente (repete todo ano)</label>
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

<!--begin::Modal Editar Data-->
<div class="modal fade" id="kt_modal_editar_data" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <form id="form_editar_data" method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h2 class="fw-bold">Editar Data Comemorativa</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <label class="form-label required">Nome da Data Comemorativa</label>
                        <input type="text" name="nome" id="edit_nome" class="form-control" required>
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="edit_descricao" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-5">
                        <label class="form-label required">Data</label>
                        <input type="date" name="data_comemoracao" id="edit_data_comemoracao" class="form-control" required>
                        <small class="text-muted">Apenas dia/mês será considerado (ano será ignorado para datas recorrentes)</small>
                    </div>
                    <div class="mb-5">
                        <label class="form-label required">Tipo</label>
                        <select name="tipo" id="edit_tipo_data" class="form-select" required>
                            <option value="nacional">Nacional</option>
                            <option value="internacional">Internacional</option>
                            <option value="empresa">Empresa</option>
                            <option value="setor">Setor</option>
                            <option value="personalizada">Personalizada</option>
                        </select>
                    </div>
                    <div class="mb-5" id="edit_empresa_group" style="display: none;">
                        <label class="form-label">Empresa</label>
                        <select name="empresa_id" id="edit_empresa_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nome_fantasia']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-5" id="edit_setor_group" style="display: none;">
                        <label class="form-label">Setor</label>
                        <select name="setor_id" id="edit_setor_id" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($setores as $setor): ?>
                                <option value="<?= $setor['id'] ?>"><?= htmlspecialchars($setor['nome_setor']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-5">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="recorrente" id="edit_recorrente" value="1">
                            <label class="form-check-label" for="edit_recorrente">Recorrente (repete todo ano)</label>
                        </div>
                    </div>
                    <div class="mb-5">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ativo" id="edit_ativo" value="1">
                            <label class="form-check-label" for="edit_ativo">Ativa</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal Adicionar
    const tipoSelect = document.getElementById('tipo_data');
    const empresaGroup = document.getElementById('empresa_group');
    const setorGroup = document.getElementById('setor_group');
    
    if (tipoSelect) {
        tipoSelect.addEventListener('change', function() {
            if (this.value === 'empresa') {
                empresaGroup.style.display = 'block';
                setorGroup.style.display = 'none';
            } else if (this.value === 'setor') {
                empresaGroup.style.display = 'none';
                setorGroup.style.display = 'block';
            } else {
                empresaGroup.style.display = 'none';
                setorGroup.style.display = 'none';
            }
        });
    }
    
    // Modal Editar
    const editTipoSelect = document.getElementById('edit_tipo_data');
    const editEmpresaGroup = document.getElementById('edit_empresa_group');
    const editSetorGroup = document.getElementById('edit_setor_group');
    
    if (editTipoSelect) {
        editTipoSelect.addEventListener('change', function() {
            if (this.value === 'empresa') {
                editEmpresaGroup.style.display = 'block';
                editSetorGroup.style.display = 'none';
            } else if (this.value === 'setor') {
                editEmpresaGroup.style.display = 'none';
                editSetorGroup.style.display = 'block';
            } else {
                editEmpresaGroup.style.display = 'none';
                editSetorGroup.style.display = 'none';
            }
        });
    }
});

function editarData(id) {
    // Busca os dados da data comemorativa
    fetch(`../api/endomarketing/buscar_data.php?id=${id}`)
        .then(response => response.json())
        .then(result => {
            if (result.success && result.data) {
                const data = result.data;
                
                // Preenche o formulário
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_nome').value = data.nome || '';
                document.getElementById('edit_descricao').value = data.descricao || '';
                document.getElementById('edit_data_comemoracao').value = data.data_comemoracao || '';
                document.getElementById('edit_tipo_data').value = data.tipo || 'nacional';
                document.getElementById('edit_recorrente').checked = data.recorrente == 1;
                document.getElementById('edit_ativo').checked = data.ativo == 1;
                
                // Define empresa ou setor
                if (data.empresa_id) {
                    document.getElementById('edit_empresa_id').value = data.empresa_id;
                } else {
                    document.getElementById('edit_empresa_id').value = '';
                }
                
                if (data.setor_id) {
                    document.getElementById('edit_setor_id').value = data.setor_id;
                } else {
                    document.getElementById('edit_setor_id').value = '';
                }
                
                // Mostra/esconde grupos baseado no tipo
                const editTipoSelect = document.getElementById('edit_tipo_data');
                const editEmpresaGroup = document.getElementById('edit_empresa_group');
                const editSetorGroup = document.getElementById('edit_setor_group');
                
                if (data.tipo === 'empresa') {
                    editEmpresaGroup.style.display = 'block';
                    editSetorGroup.style.display = 'none';
                } else if (data.tipo === 'setor') {
                    editEmpresaGroup.style.display = 'none';
                    editSetorGroup.style.display = 'block';
                } else {
                    editEmpresaGroup.style.display = 'none';
                    editSetorGroup.style.display = 'none';
                }
                
                // Abre o modal
                const modal = new bootstrap.Modal(document.getElementById('kt_modal_editar_data'));
                modal.show();
            } else {
                Swal.fire({
                    text: result.error || 'Erro ao buscar dados da data comemorativa',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            Swal.fire({
                text: 'Erro ao buscar dados da data comemorativa',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        });
}

function excluirData(id, nome) {
    Swal.fire({
        text: `Deseja realmente excluir a data comemorativa "${nome}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

