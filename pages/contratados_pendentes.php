<?php
/**
 * Lista de Contratados Pendentes de Cadastro
 * Candidatos/Entrevistas marcados como contratados mas ainda não cadastrados como colaboradores
 */

$page_title = 'Contratados Pendentes';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('kanban_selecao.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca candidaturas pendentes (coluna contratado OU status contratado_pendente)
$where_candidaturas = ["(c.coluna_kanban = 'contratado' OR c.status = 'contratado_pendente')", "c.status != 'contratado'"];
$params_candidaturas = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where_candidaturas[] = "v.empresa_id IN ($placeholders)";
        $params_candidaturas = array_merge($params_candidaturas, $usuario['empresas_ids']);
    }
}

$sql_candidaturas = "
    SELECT c.id, 
           cand.nome_completo,
           cand.email,
           cand.telefone,
           v.titulo as vaga_titulo,
           v.empresa_id,
           e.nome_fantasia as empresa_nome,
           c.created_at,
           c.updated_at,
           0 as is_entrevista
    FROM candidaturas c
    INNER JOIN candidatos cand ON c.candidato_id = cand.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    LEFT JOIN empresas e ON v.empresa_id = e.id
    WHERE " . implode(' AND ', $where_candidaturas) . "
    ORDER BY c.updated_at DESC
";

$stmt = $pdo->prepare($sql_candidaturas);
$stmt->execute($params_candidaturas);
$candidaturas = $stmt->fetchAll();

// Busca entrevistas manuais pendentes (coluna contratado OU status contratado_pendente)
$where_entrevistas = ["ent.candidatura_id IS NULL", "(ent.coluna_kanban = 'contratado' OR ent.status = 'contratado_pendente')", "ent.status != 'contratado'"];
$params_entrevistas = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where_entrevistas[] = "(ent.vaga_id_manual IS NULL OR v.empresa_id IN ($placeholders))";
        $params_entrevistas = array_merge($params_entrevistas, $usuario['empresas_ids']);
    }
}

$sql_entrevistas = "
    SELECT ent.id,
           ent.candidato_nome_manual as nome_completo,
           ent.candidato_email_manual as email,
           ent.candidato_telefone_manual as telefone,
           v.titulo as vaga_titulo,
           v.empresa_id,
           e.nome_fantasia as empresa_nome,
           ent.created_at,
           ent.updated_at,
           1 as is_entrevista
    FROM entrevistas ent
    LEFT JOIN vagas v ON ent.vaga_id_manual = v.id
    LEFT JOIN empresas e ON v.empresa_id = e.id
    WHERE " . implode(' AND ', $where_entrevistas) . "
    ORDER BY ent.updated_at DESC
";

$stmt = $pdo->prepare($sql_entrevistas);
$stmt->execute($params_entrevistas);
$entrevistas = $stmt->fetchAll();

// Combina candidaturas e entrevistas
$pendentes = array_merge($candidaturas, $entrevistas);

// Ordena por data de atualização
usort($pendentes, function($a, $b) {
    return strtotime($b['updated_at']) - strtotime($a['updated_at']);
});
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <!-- Título da Página -->
                <div class="mb-5">
                    <div class="d-flex align-items-center mb-2">
                        <i class="ki-duotone ki-user-tick fs-2x text-warning me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>
                            <h1 class="text-gray-800 fw-bold mb-1">Contratados Pendentes</h1>
                            <p class="text-gray-600 fs-6 mb-0">
                                Candidatos aprovados aguardando cadastro como colaborador.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <div class="d-flex align-items-center">
                                <span class="badge badge-warning fs-6 me-3"><?= count($pendentes) ?></span>
                                <span class="text-muted">pendentes de cadastro</span>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <a href="kanban_selecao.php" class="btn btn-light-primary">
                                <i class="ki-duotone ki-arrow-left fs-2"></i>
                                Voltar ao Kanban
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <?php if (empty($pendentes)): ?>
                        <div class="text-center py-15">
                            <i class="ki-duotone ki-check-circle fs-5x text-success mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <h3 class="text-muted">Nenhum contratado pendente</h3>
                            <p class="text-gray-600">Todos os candidatos contratados já foram cadastrados como colaboradores.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Candidato</th>
                                        <th>Vaga</th>
                                        <th>Empresa</th>
                                        <th>Tipo</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendentes as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-40px symbol-circle me-3">
                                                    <span class="symbol-label bg-light-primary text-primary fw-bold">
                                                        <?= strtoupper(substr($item['nome_completo'], 0, 1)) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($item['nome_completo']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($item['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($item['vaga_titulo'] ?? 'Não informada') ?></td>
                                        <td><?= htmlspecialchars($item['empresa_nome'] ?? 'Não informada') ?></td>
                                        <td>
                                            <?php if ($item['is_entrevista']): ?>
                                            <span class="badge badge-light-info">Entrevista Manual</span>
                                            <?php else: ?>
                                            <span class="badge badge-light-primary">Candidatura</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary" 
                                                    onclick="abrirModalCadastro('<?= $item['is_entrevista'] ? 'entrevista_' . $item['id'] : $item['id'] ?>', <?= $item['is_entrevista'] ?>)">
                                                <i class="ki-duotone ki-user-edit fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Cadastrar Colaborador
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success" 
                                                    onclick="marcarComoConcluido('<?= $item['is_entrevista'] ? 'entrevista_' . $item['id'] : $item['id'] ?>', <?= $item['is_entrevista'] ?>)"
                                                    title="Marcar como OK (já cadastrado manualmente)">
                                                <i class="ki-duotone ki-check fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                OK
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal Cadastrar Colaborador -->
<div class="modal fade" id="modalCadastrarColaborador" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cadastrar como Colaborador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCadastrarColaborador">
                <input type="hidden" name="candidatura_id" value="">
                <input type="hidden" name="is_entrevista" value="0">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ki-duotone ki-information-5 fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Preencha os dados abaixo para cadastrar como colaborador. Os campos marcados com * são obrigatórios.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome Completo *</label>
                            <input type="text" name="nome_completo" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CPF *</label>
                            <input type="text" name="cpf" class="form-control" required placeholder="000.000.000-00">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email_pessoal" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Empresa *</label>
                            <select name="empresa_id" class="form-select" required id="empresaSelect">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Setor *</label>
                            <select name="setor_id" class="form-select" required id="setorSelect">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cargo *</label>
                            <select name="cargo_id" class="form-select" required id="cargoSelect">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data de Início *</label>
                            <input type="date" name="data_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Contrato</label>
                            <select name="tipo_contrato" class="form-select">
                                <option value="CLT">CLT</option>
                                <option value="PJ">PJ</option>
                                <option value="Estágio">Estágio</option>
                                <option value="Terceirizado">Terceirizado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Salário</label>
                        <input type="text" name="salario" class="form-control" placeholder="0,00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cadastrar Colaborador</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Abre modal de cadastro de colaborador
function abrirModalCadastro(id, isEntrevista) {
    const idLimpo = id.toString().replace('entrevista_', '');
    
    // Busca dados
    fetch(`../api/recrutamento/candidaturas/dados_cadastro.php?id=${idLimpo}&is_entrevista=${isEntrevista ? '1' : '0'}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector('#formCadastrarColaborador [name="candidatura_id"]').value = id;
                document.querySelector('#formCadastrarColaborador [name="is_entrevista"]').value = isEntrevista ? '1' : '0';
                
                if (data.dados) {
                    const form = document.getElementById('formCadastrarColaborador');
                    if (data.dados.nome_completo) form.querySelector('[name="nome_completo"]').value = data.dados.nome_completo;
                    if (data.dados.email) form.querySelector('[name="email_pessoal"]').value = data.dados.email;
                    if (data.dados.telefone) form.querySelector('[name="telefone"]').value = data.dados.telefone;
                }
                
                const modal = new bootstrap.Modal(document.getElementById('modalCadastrarColaborador'));
                modal.show();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar dados');
        });
}

// Marca como concluído (já cadastrado manualmente)
async function marcarComoConcluido(id, isEntrevista) {
    if (!confirm('Confirma que este candidato já foi cadastrado como colaborador manualmente?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('candidatura_id', id);
        formData.append('is_entrevista', isEntrevista ? '1' : '0');
        formData.append('acao', 'marcar_concluido');
        
        const response = await fetch('../api/recrutamento/colaborador/marcar_concluido.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Marcado como concluído!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao marcar como concluído');
    }
}

// Carrega empresas ao abrir modal
document.getElementById('modalCadastrarColaborador').addEventListener('show.bs.modal', function() {
    carregarEmpresas();
});

// Quando seleciona empresa, carrega setores
document.getElementById('empresaSelect').addEventListener('change', function() {
    if (this.value) {
        carregarSetores(this.value);
    }
});

// Quando seleciona setor, carrega cargos
document.getElementById('setorSelect').addEventListener('change', function() {
    if (this.value) {
        carregarCargos(this.value);
    }
});

async function carregarEmpresas() {
    try {
        const response = await fetch('../api/empresas/listar.php');
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('empresaSelect');
            select.innerHTML = '<option value="">Selecione...</option>';
            data.empresas.forEach(empresa => {
                const option = document.createElement('option');
                option.value = empresa.id;
                option.textContent = empresa.nome_fantasia;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar empresas:', error);
    }
}

async function carregarSetores(empresaId) {
    try {
        const response = await fetch(`../api/setores/listar.php?empresa_id=${empresaId}`);
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('setorSelect');
            select.innerHTML = '<option value="">Selecione...</option>';
            data.setores.forEach(setor => {
                const option = document.createElement('option');
                option.value = setor.id;
                option.textContent = setor.nome_setor;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar setores:', error);
    }
}

async function carregarCargos(setorId) {
    try {
        const response = await fetch(`../api/cargos/listar.php?setor_id=${setorId}`);
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('cargoSelect');
            select.innerHTML = '<option value="">Selecione...</option>';
            data.cargos.forEach(cargo => {
                const option = document.createElement('option');
                option.value = cargo.id;
                option.textContent = cargo.nome_cargo;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar cargos:', error);
    }
}

// Submit do formulário
document.getElementById('formCadastrarColaborador').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/colaborador/cadastrar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Colaborador cadastrado com sucesso!');
            bootstrap.Modal.getInstance(document.getElementById('modalCadastrarColaborador')).hide();
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao cadastrar colaborador');
        console.error(error);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

