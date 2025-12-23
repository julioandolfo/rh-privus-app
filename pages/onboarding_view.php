<?php
/**
 * Detalhes do Processo de Onboarding
 */

$page_title = 'Detalhes do Onboarding';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('onboarding.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$onboarding_id = (int)($_GET['id'] ?? 0);

if (!$onboarding_id) {
    redirect('onboarding.php', 'Onboarding não encontrado', 'error');
}

// DEBUG - ANTES DA QUERY
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Primeiro testa query simples
$stmt_simple = $pdo->prepare("SELECT * FROM onboarding WHERE id = ?");
$stmt_simple->execute([$onboarding_id]);
$onboarding_simple = $stmt_simple->fetch(PDO::FETCH_ASSOC);

if (!$onboarding_simple) {
    echo "Onboarding não encontrado com query simples. ID: $onboarding_id";
    exit;
}

// Busca dados da entrevista se existir
$entrevista = null;
if (!empty($onboarding_simple['entrevista_id'])) {
    $stmt_ent = $pdo->prepare("SELECT * FROM entrevistas WHERE id = ?");
    $stmt_ent->execute([$onboarding_simple['entrevista_id']]);
    $entrevista = $stmt_ent->fetch(PDO::FETCH_ASSOC);
}

// Busca dados da candidatura se existir
$candidatura = null;
$candidato = null;
$vaga = null;
if (!empty($onboarding_simple['candidatura_id'])) {
    $stmt_cand = $pdo->prepare("
        SELECT cand.*, c.nome_completo, c.email, c.telefone, v.titulo as vaga_titulo
        FROM candidaturas cand
        INNER JOIN candidatos c ON cand.candidato_id = c.id
        INNER JOIN vagas v ON cand.vaga_id = v.id
        WHERE cand.id = ?
    ");
    $stmt_cand->execute([$onboarding_simple['candidatura_id']]);
    $candidatura = $stmt_cand->fetch(PDO::FETCH_ASSOC);
}

// Busca vaga da entrevista manual
$vaga_entrevista = null;
if ($entrevista && !empty($entrevista['vaga_id_manual'])) {
    $stmt_vaga = $pdo->prepare("SELECT titulo FROM vagas WHERE id = ?");
    $stmt_vaga->execute([$entrevista['vaga_id_manual']]);
    $vaga_entrevista = $stmt_vaga->fetch(PDO::FETCH_ASSOC);
}

// Busca responsável
$responsavel = null;
if (!empty($onboarding_simple['responsavel_id'])) {
    $stmt_resp = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt_resp->execute([$onboarding_simple['responsavel_id']]);
    $responsavel = $stmt_resp->fetch(PDO::FETCH_ASSOC);
}

// Busca colaborador
$colaborador = null;
if (!empty($onboarding_simple['colaborador_id'])) {
    $stmt_col = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
    $stmt_col->execute([$onboarding_simple['colaborador_id']]);
    $colaborador = $stmt_col->fetch(PDO::FETCH_ASSOC);
}

// Busca mentor
$mentor = null;
if (!empty($onboarding_simple['mentor_id'])) {
    $stmt_mentor = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
    $stmt_mentor->execute([$onboarding_simple['mentor_id']]);
    $mentor = $stmt_mentor->fetch(PDO::FETCH_ASSOC);
}

// Monta objeto onboarding
$onboarding = $onboarding_simple;
$onboarding['is_entrevista_manual'] = (!empty($onboarding_simple['entrevista_id']) && empty($onboarding_simple['candidatura_id'])) ? 1 : 0;

if ($candidatura) {
    $onboarding['candidato_nome'] = $candidatura['nome_completo'];
    $onboarding['candidato_email'] = $candidatura['email'];
    $onboarding['candidato_telefone'] = $candidatura['telefone'];
    $onboarding['vaga_titulo'] = $candidatura['vaga_titulo'];
} elseif ($entrevista) {
    $onboarding['candidato_nome'] = $entrevista['candidato_nome_manual'];
    $onboarding['candidato_email'] = $entrevista['candidato_email_manual'];
    $onboarding['candidato_telefone'] = $entrevista['candidato_telefone_manual'];
    $onboarding['vaga_titulo'] = $vaga_entrevista['titulo'] ?? null;
} else {
    $onboarding['candidato_nome'] = 'Desconhecido';
    $onboarding['candidato_email'] = null;
    $onboarding['candidato_telefone'] = null;
    $onboarding['vaga_titulo'] = null;
}

$onboarding['colaborador_nome'] = $colaborador['nome_completo'] ?? null;
$onboarding['responsavel_nome'] = $responsavel['nome'] ?? null;
$onboarding['mentor_nome'] = $mentor['nome_completo'] ?? null;

if (!$onboarding) {
    redirect('onboarding.php', 'Onboarding não encontrado', 'error');
}

// Busca tarefas
$stmt = $pdo->prepare("
    SELECT t.*, u.nome as responsavel_nome
    FROM onboarding_tarefas t
    LEFT JOIN usuarios u ON t.responsavel_id = u.id
    WHERE t.onboarding_id = ?
    ORDER BY t.etapa, t.id ASC
");
$stmt->execute([$onboarding_id]);
$tarefas = $stmt->fetchAll();

// Agrupa tarefas por etapa
$tarefas_por_etapa = [];
foreach ($tarefas as $tarefa) {
    $etapa = $tarefa['etapa'];
    if (!isset($tarefas_por_etapa[$etapa])) {
        $tarefas_por_etapa[$etapa] = [];
    }
    $tarefas_por_etapa[$etapa][] = $tarefa;
}
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card mb-5">
                    <div class="card-header">
                        <h2>
                            Onboarding - <?= htmlspecialchars($onboarding['colaborador_nome'] ?: $onboarding['candidato_nome']) ?>
                            <?php if (!empty($onboarding['is_entrevista_manual'])): ?>
                            <span class="badge badge-light-warning ms-2">Entrevista Manual</span>
                            <?php endif; ?>
                        </h2>
                        <div class="card-toolbar">
                            <a href="kanban_onboarding.php" class="btn btn-light-primary me-2">Kanban</a>
                            <a href="onboarding.php" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Candidato:</strong> <?= htmlspecialchars($onboarding['candidato_nome']) ?></p>
                                <?php if (!empty($onboarding['candidato_email'])): ?>
                                <p><strong>Email:</strong> <?= htmlspecialchars($onboarding['candidato_email']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($onboarding['candidato_telefone'])): ?>
                                <p><strong>Telefone:</strong> <?= htmlspecialchars($onboarding['candidato_telefone']) ?></p>
                                <?php endif; ?>
                                <p><strong>Vaga:</strong> <?= htmlspecialchars($onboarding['vaga_titulo'] ?? 'Não informada') ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge badge-light-primary"><?= ucfirst($onboarding['status']) ?></span>
                                </p>
                                <p><strong>Responsável:</strong> <?= htmlspecialchars($onboarding['responsavel_nome']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Data Início:</strong> <?= date('d/m/Y', strtotime($onboarding['data_inicio'])) ?></p>
                                <?php if ($onboarding['data_previsao_conclusao']): ?>
                                <p><strong>Previsão:</strong> <?= date('d/m/Y', strtotime($onboarding['data_previsao_conclusao'])) ?></p>
                                <?php endif; ?>
                                <?php if ($onboarding['mentor_nome']): ?>
                                <p><strong>Mentor:</strong> <?= htmlspecialchars($onboarding['mentor_nome']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tarefas por Etapa -->
                <?php foreach ($tarefas_por_etapa as $etapa => $tarefas_etapa): ?>
                <div class="card mb-5">
                    <div class="card-header">
                        <h3><?= ucfirst($etapa) ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tarefa</th>
                                        <th>Tipo</th>
                                        <th>Responsável</th>
                                        <th>Status</th>
                                        <th>Vencimento</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tarefas_etapa as $tarefa): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($tarefa['titulo']) ?></strong>
                                            <?php if ($tarefa['descricao']): ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($tarefa['descricao']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= ucfirst($tarefa['tipo']) ?></td>
                                        <td><?= htmlspecialchars($tarefa['responsavel_nome'] ?? '-') ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pendente' => 'warning',
                                                'em_andamento' => 'info',
                                                'concluida' => 'success',
                                                'cancelada' => 'danger'
                                            ];
                                            $color = $status_colors[$tarefa['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-light-<?= $color ?>"><?= ucfirst($tarefa['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($tarefa['data_vencimento']): ?>
                                            <?= date('d/m/Y', strtotime($tarefa['data_vencimento'])) ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($tarefa['status'] !== 'concluida'): ?>
                                            <button class="btn btn-sm btn-light-success btn-concluir-tarefa" 
                                                    data-tarefa-id="<?= $tarefa['id'] ?>">
                                                Concluir
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Histórico / Timeline do Onboarding -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="ki-duotone ki-notepad-edit fs-2 me-2 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Histórico e Anotações
                        </h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalHistorico">
                                <i class="ki-duotone ki-plus fs-4"></i>
                                Adicionar Registro
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="historicoContainer">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar Histórico -->
<div class="modal fade" id="modalHistorico" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalHistoricoTitle">Adicionar Registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formHistorico">
                <input type="hidden" name="id" id="historicoId" value="">
                <input type="hidden" name="onboarding_id" value="<?= $onboarding_id ?>">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo" id="historicoTipo" class="form-select" required>
                                <option value="anotacao">📝 Anotação</option>
                                <option value="andamento">📊 Andamento</option>
                                <option value="documento">📄 Documento</option>
                                <option value="contato">📞 Contato</option>
                                <option value="problema">⚠️ Problema</option>
                                <option value="outro">📌 Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status_andamento" id="historicoStatus" class="form-select">
                                <option value="pendente">🟡 Pendente</option>
                                <option value="em_andamento" selected>🔵 Em Andamento</option>
                                <option value="concluido">🟢 Concluído</option>
                                <option value="cancelado">🔴 Cancelado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" id="historicoTitulo" class="form-control" required placeholder="Ex: Documentos entregues, Reunião realizada...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="historicoDescricao" class="form-control" rows="4" placeholder="Detalhes, observações, próximos passos..."></textarea>
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

<script>
const onboardingId = <?= $onboarding_id ?>;

// Ícones e cores para tipos
const tipoConfig = {
    'anotacao': { icon: 'notepad-edit', color: 'primary', label: 'Anotação' },
    'andamento': { icon: 'chart-simple', color: 'info', label: 'Andamento' },
    'documento': { icon: 'document', color: 'warning', label: 'Documento' },
    'contato': { icon: 'phone', color: 'success', label: 'Contato' },
    'problema': { icon: 'shield-cross', color: 'danger', label: 'Problema' },
    'outro': { icon: 'bookmark', color: 'secondary', label: 'Outro' }
};

const statusConfig = {
    'pendente': { color: 'warning', label: 'Pendente' },
    'em_andamento': { color: 'info', label: 'Em Andamento' },
    'concluido': { color: 'success', label: 'Concluído' },
    'cancelado': { color: 'danger', label: 'Cancelado' }
};

// Carrega histórico
async function carregarHistorico() {
    try {
        const response = await fetch(`../api/recrutamento/onboarding/historico_listar.php?onboarding_id=${onboardingId}`);
        const data = await response.json();
        
        const container = document.getElementById('historicoContainer');
        
        if (!data.success || !data.historicos || data.historicos.length === 0) {
            container.innerHTML = `
                <div class="text-center py-10">
                    <i class="ki-duotone ki-notepad fs-3x text-gray-400 mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    <p class="text-gray-500 fs-6 mb-0">Nenhum registro ainda.</p>
                    <p class="text-muted fs-7">Clique em "Adicionar Registro" para começar a documentar o onboarding.</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="timeline">';
        
        data.historicos.forEach(item => {
            const tipo = tipoConfig[item.tipo] || tipoConfig['outro'];
            const status = statusConfig[item.status_andamento] || statusConfig['em_andamento'];
            const dataFormatada = new Date(item.data_registro).toLocaleDateString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
            
            html += `
                <div class="timeline-item mb-4">
                    <div class="timeline-line w-40px"></div>
                    <div class="timeline-icon symbol symbol-circle symbol-40px">
                        <div class="symbol-label bg-light-${tipo.color}">
                            <i class="ki-duotone ki-${tipo.icon} fs-2 text-${tipo.color}">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <div class="timeline-content mb-10 mt-n1">
                        <div class="card shadow-sm">
                            <div class="card-header py-3">
                                <div class="card-title d-flex align-items-center">
                                    <span class="badge badge-light-${tipo.color} me-2">${tipo.label}</span>
                                    <span class="fw-bold">${escapeHtml(item.titulo)}</span>
                                </div>
                                <div class="card-toolbar">
                                    <span class="badge badge-${status.color} me-2">${status.label}</span>
                                    <button class="btn btn-sm btn-icon btn-light-warning me-1" onclick="editarHistorico(${item.id})" title="Editar">
                                        <i class="ki-duotone ki-pencil fs-5"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                    <button class="btn btn-sm btn-icon btn-light-danger" onclick="excluirHistorico(${item.id})" title="Excluir">
                                        <i class="ki-duotone ki-trash fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                    </button>
                                </div>
                            </div>
                            ${item.descricao ? `<div class="card-body py-3"><p class="mb-0 text-gray-700">${escapeHtml(item.descricao).replace(/\n/g, '<br>')}</p></div>` : ''}
                            <div class="card-footer py-2">
                                <small class="text-muted">
                                    <i class="ki-duotone ki-user fs-6 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    ${escapeHtml(item.usuario_nome)} • ${dataFormatada}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Erro ao carregar histórico:', error);
        document.getElementById('historicoContainer').innerHTML = `
            <div class="alert alert-danger">Erro ao carregar histórico</div>
        `;
    }
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Guardar dados para edição
let historicosCache = [];

// Editar histórico
async function editarHistorico(id) {
    try {
        const response = await fetch(`../api/recrutamento/onboarding/historico_listar.php?onboarding_id=${onboardingId}`);
        const data = await response.json();
        
        const item = data.historicos.find(h => h.id == id);
        if (!item) {
            alert('Registro não encontrado');
            return;
        }
        
        document.getElementById('historicoId').value = item.id;
        document.getElementById('historicoTipo').value = item.tipo;
        document.getElementById('historicoStatus').value = item.status_andamento;
        document.getElementById('historicoTitulo').value = item.titulo;
        document.getElementById('historicoDescricao').value = item.descricao || '';
        document.getElementById('modalHistoricoTitle').textContent = 'Editar Registro';
        
        const modal = new bootstrap.Modal(document.getElementById('modalHistorico'));
        modal.show();
        
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao carregar registro');
    }
}

// Excluir histórico
async function excluirHistorico(id) {
    if (!confirm('Tem certeza que deseja excluir este registro?')) return;
    
    try {
        const formData = new FormData();
        formData.append('id', id);
        
        const response = await fetch('../api/recrutamento/onboarding/historico_excluir.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            carregarHistorico();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao excluir registro');
    }
}

// Submit do formulário
document.getElementById('formHistorico').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/onboarding/historico_salvar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalHistorico')).hide();
            this.reset();
            document.getElementById('historicoId').value = '';
            document.getElementById('modalHistoricoTitle').textContent = 'Adicionar Registro';
            carregarHistorico();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao salvar registro');
    }
});

// Limpa formulário ao abrir modal para novo registro
document.getElementById('modalHistorico').addEventListener('show.bs.modal', function(e) {
    if (!document.getElementById('historicoId').value) {
        document.getElementById('formHistorico').reset();
        document.getElementById('modalHistoricoTitle').textContent = 'Adicionar Registro';
    }
});

// Carrega histórico ao carregar página
document.addEventListener('DOMContentLoaded', function() {
    carregarHistorico();
});

document.querySelectorAll('.btn-concluir-tarefa').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Deseja marcar esta tarefa como concluída?')) return;
        
        const tarefaId = this.dataset.tarefaId;
        
        try {
            const response = await fetch(`../api/recrutamento/onboarding/concluir_tarefa.php?id=${tarefaId}`, {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Tarefa concluída!');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            alert('Erro ao concluir tarefa');
        }
    });
});
</script>

<style>
/* Timeline Styles */
.timeline {
    position: relative;
}

.timeline-item {
    display: flex;
    align-items: flex-start;
    position: relative;
}

.timeline-line {
    position: absolute;
    left: 19px;
    top: 40px;
    bottom: -30px;
    width: 2px;
    background-color: #e4e6ef;
}

.timeline-item:last-child .timeline-line {
    display: none;
}

.timeline-icon {
    z-index: 1;
    flex-shrink: 0;
}

.timeline-content {
    flex-grow: 1;
    padding-left: 1rem;
}

.timeline-content .card {
    border-left: 3px solid;
}

.timeline-content .card:has(.badge-light-primary) {
    border-left-color: var(--bs-primary);
}

.timeline-content .card:has(.badge-light-info) {
    border-left-color: var(--bs-info);
}

.timeline-content .card:has(.badge-light-warning) {
    border-left-color: var(--bs-warning);
}

.timeline-content .card:has(.badge-light-success) {
    border-left-color: var(--bs-success);
}

.timeline-content .card:has(.badge-light-danger) {
    border-left-color: var(--bs-danger);
}

.timeline-content .card:has(.badge-light-secondary) {
    border-left-color: var(--bs-secondary);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

