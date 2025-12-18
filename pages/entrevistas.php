<?php
/**
 * Gestão de Entrevistas
 */

$page_title = 'Entrevistas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('entrevistas.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$where = ["1=1"];
$params = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "(COALESCE(v.empresa_id, vm.empresa_id) IN ($placeholders))";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$filtro_status = $_GET['status'] ?? '';
if ($filtro_status) {
    $where[] = "e.status = ?";
    $params[] = $filtro_status;
}

$filtro_entrevistador = $_GET['entrevistador_id'] ?? '';
if ($filtro_entrevistador && $usuario['role'] === 'ADMIN') {
    $where[] = "e.entrevistador_id = ?";
    $params[] = (int)$filtro_entrevistador;
} elseif ($usuario['role'] !== 'ADMIN') {
    $where[] = "e.entrevistador_id = ?";
    $params[] = $usuario['id'];
}

$sql = "
    SELECT e.*,
           COALESCE(c.nome_completo, e.candidato_nome_manual) as candidato_nome,
           COALESCE(c.email, e.candidato_email_manual) as candidato_email,
           COALESCE(v.titulo, vm.titulo) as vaga_titulo,
           u.nome as entrevistador_nome,
           et.nome as etapa_nome,
           CASE WHEN e.candidatura_id IS NULL THEN 1 ELSE 0 END as is_manual
    FROM entrevistas e
    LEFT JOIN candidaturas cand ON e.candidatura_id = cand.id
    LEFT JOIN candidatos c ON cand.candidato_id = c.id
    LEFT JOIN vagas v ON cand.vaga_id = v.id
    LEFT JOIN vagas vm ON e.vaga_id_manual = vm.id
    LEFT JOIN usuarios u ON e.entrevistador_id = u.id
    LEFT JOIN processo_seletivo_etapas et ON e.etapa_id = et.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.data_agendada DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entrevistas = $stmt->fetchAll();

// Busca usuários para filtro (apenas ADMIN)
$entrevistadores = [];
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome FROM usuarios WHERE role IN ('ADMIN', 'RH', 'GESTOR') ORDER BY nome");
    $entrevistadores = $stmt->fetchAll();
}
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Entrevistas</h2>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaEntrevista">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Entrevista
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <!-- Filtros -->
                        <div class="mb-5">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <select name="status" class="form-select">
                                        <option value="">Todos os status</option>
                                        <option value="agendada" <?= $filtro_status === 'agendada' ? 'selected' : '' ?>>Agendada</option>
                                        <option value="realizada" <?= $filtro_status === 'realizada' ? 'selected' : '' ?>>Realizada</option>
                                        <option value="cancelada" <?= $filtro_status === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                        <option value="reagendada" <?= $filtro_status === 'reagendada' ? 'selected' : '' ?>>Reagendada</option>
                                        <option value="nao_compareceu" <?= $filtro_status === 'nao_compareceu' ? 'selected' : '' ?>>Não Compareceu</option>
                                    </select>
                                </div>
                                <?php if ($usuario['role'] === 'ADMIN' && !empty($entrevistadores)): ?>
                                <div class="col-md-4">
                                    <select name="entrevistador_id" class="form-select">
                                        <option value="">Todos os entrevistadores</option>
                                        <?php foreach ($entrevistadores as $entrevistador): ?>
                                        <option value="<?= $entrevistador['id'] ?>" <?= $filtro_entrevistador == $entrevistador['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($entrevistador['nome']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-light-primary">Filtrar</button>
                                    <a href="entrevistas.php" class="btn btn-light">Limpar</a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Candidato</th>
                                        <th>Vaga</th>
                                        <th>Tipo</th>
                                        <th>Data/Hora</th>
                                        <th>Entrevistador</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entrevistas as $entrevista): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($entrevista['candidato_nome']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($entrevista['candidato_email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($entrevista['vaga_titulo']) ?></td>
                                        <td>
                                            <?php
                                            $tipos = [
                                                'telefone' => 'Telefone',
                                                'video' => 'Vídeo',
                                                'presencial' => 'Presencial',
                                                'grupo' => 'Grupo'
                                            ];
                                            ?>
                                            <?= htmlspecialchars($tipos[$entrevista['tipo']] ?? $entrevista['tipo']) ?>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($entrevista['data_agendada'])) ?>
                                            <?php if ($entrevista['duracao_minutos']): ?>
                                            <br><small class="text-muted"><?= $entrevista['duracao_minutos'] ?> min</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($entrevista['entrevistador_nome']) ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'agendada' => 'primary',
                                                'realizada' => 'success',
                                                'cancelada' => 'danger',
                                                'reagendada' => 'warning',
                                                'nao_compareceu' => 'danger'
                                            ];
                                            $color = $status_colors[$entrevista['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-light-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $entrevista['status'])) ?></span>
                                        </td>
                                        <td>
                                            <a href="entrevista_view.php?id=<?= $entrevista['id'] ?>" class="btn btn-sm btn-light-primary">
                                                Ver
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Entrevista -->
<div class="modal fade" id="modalNovaEntrevista" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Entrevista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEntrevista">
                <div class="modal-body">
                    <!-- Toggle entre candidatura existente e manual -->
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="entrevistaManual" name="entrevista_manual">
                            <label class="form-check-label" for="entrevistaManual">
                                <strong>Entrevista Manual (sem candidatura no sistema)</strong>
                            </label>
                        </div>
                        <small class="text-muted">Marque esta opção para criar uma entrevista com candidato que ainda não possui candidatura cadastrada</small>
                    </div>
                    
                    <!-- Campos para candidatura existente -->
                    <div id="camposCandidatura" class="mb-3">
                        <label class="form-label">Candidatura</label>
                        <select name="candidatura_id" class="form-select" id="candidaturaSelect">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    
                    <!-- Campos para entrevista manual -->
                    <div id="camposManual" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome do Candidato *</label>
                                <input type="text" name="candidato_nome" class="form-control" id="candidatoNome">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email do Candidato *</label>
                                <input type="email" name="candidato_email" class="form-control" id="candidatoEmail">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Telefone do Candidato</label>
                                <input type="text" name="candidato_telefone" class="form-control" id="candidatoTelefone" placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vaga</label>
                                <select name="vaga_id" class="form-select" id="vagaSelect">
                                    <option value="">Selecione uma vaga (opcional)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Coluna do Kanban</label>
                            <select name="coluna_kanban" class="form-select" id="colunaKanbanSelect">
                                <option value="entrevista">Entrevista</option>
                                <option value="triagem">Triagem</option>
                                <option value="avaliacao">Avaliação</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo" class="form-select" required>
                                <option value="presencial">Presencial</option>
                                <option value="video">Vídeo</option>
                                <option value="telefone">Telefone</option>
                                <option value="grupo">Grupo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data e Hora *</label>
                            <input type="datetime-local" name="data_agendada" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Duração (minutos)</label>
                            <input type="number" name="duracao_minutos" class="form-control" value="60">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Localização / Link</label>
                            <input type="text" name="localizacao" class="form-control" placeholder="Endereço ou link de videoconferência">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Carrega candidaturas para select
async function carregarCandidaturas() {
    try {
        const response = await fetch('../api/recrutamento/candidaturas/listar.php?status=triagem,entrevista,avaliacao');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('candidaturaSelect');
            select.innerHTML = '<option value="">Selecione...</option>';
            
            data.candidaturas.forEach(candidatura => {
                const option = document.createElement('option');
                option.value = candidatura.id;
                option.textContent = `${candidatura.nome_completo} - ${candidatura.vaga_titulo}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar candidaturas:', error);
    }
}

// Carrega vagas para select
async function carregarVagas() {
    try {
        const response = await fetch('../api/recrutamento/vagas/listar.php?status=aberta');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('vagaSelect');
            select.innerHTML = '<option value="">Selecione uma vaga (opcional)</option>';
            
            data.vagas.forEach(vaga => {
                const option = document.createElement('option');
                option.value = vaga.id;
                option.textContent = vaga.titulo;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar vagas:', error);
    }
}

// Toggle entre candidatura existente e manual
document.getElementById('entrevistaManual').addEventListener('change', function() {
    const isManual = this.checked;
    const camposCandidatura = document.getElementById('camposCandidatura');
    const camposManual = document.getElementById('camposManual');
    const candidaturaSelect = document.getElementById('candidaturaSelect');
    const candidatoNome = document.getElementById('candidatoNome');
    const candidatoEmail = document.getElementById('candidatoEmail');
    
    if (isManual) {
        camposCandidatura.style.display = 'none';
        camposManual.style.display = 'block';
        candidaturaSelect.removeAttribute('required');
        candidatoNome.setAttribute('required', 'required');
        candidatoEmail.setAttribute('required', 'required');
    } else {
        camposCandidatura.style.display = 'block';
        camposManual.style.display = 'none';
        candidaturaSelect.setAttribute('required', 'required');
        candidatoNome.removeAttribute('required');
        candidatoEmail.removeAttribute('required');
    }
});

document.getElementById('formEntrevista').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('entrevistador_id', '<?= $usuario['id'] ?>');
    
    // Se não é entrevista manual, remove campos manuais
    if (!document.getElementById('entrevistaManual').checked) {
        formData.delete('candidato_nome');
        formData.delete('candidato_email');
        formData.delete('candidato_telefone');
        formData.delete('vaga_id');
        formData.delete('coluna_kanban');
    } else {
        // Se é manual, remove candidatura_id
        formData.delete('candidatura_id');
    }
    
    try {
        const response = await fetch('../api/recrutamento/entrevistas/criar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Entrevista agendada com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao agendar entrevista');
    }
});

// Carrega dados ao abrir modal
document.getElementById('modalNovaEntrevista').addEventListener('show.bs.modal', function() {
    carregarCandidaturas();
    carregarVagas();
    // Reseta formulário
    document.getElementById('entrevistaManual').checked = false;
    document.getElementById('camposCandidatura').style.display = 'block';
    document.getElementById('camposManual').style.display = 'none';
    document.getElementById('candidaturaSelect').setAttribute('required', 'required');
    document.getElementById('candidatoNome').removeAttribute('required');
    document.getElementById('candidatoEmail').removeAttribute('required');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

