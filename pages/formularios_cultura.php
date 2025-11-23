<?php
/**
 * Gestão de Formulários de Cultura
 */

$page_title = 'Formulários de Cultura';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('formularios_cultura.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca formulários
$stmt = $pdo->query("
    SELECT f.*, 
           e.nome as etapa_nome,
           COUNT(DISTINCT fc.id) as total_campos
    FROM formularios_cultura f
    LEFT JOIN processo_seletivo_etapas e ON f.etapa_id = e.id
    LEFT JOIN formularios_cultura_campos fc ON f.id = fc.formulario_id
    GROUP BY f.id
    ORDER BY f.created_at DESC
");
$formularios = $stmt->fetchAll();

// Busca etapas para vincular
$stmt = $pdo->query("SELECT * FROM processo_seletivo_etapas WHERE vaga_id IS NULL AND ativo = 1 ORDER BY ordem ASC");
$etapas = $stmt->fetchAll();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Formulários de Cultura</h2>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoFormulario">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Novo Formulário
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Nome</th>
                                        <th>Etapa Vinculada</th>
                                        <th>Campos</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($formularios as $formulario): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($formulario['nome']) ?></strong>
                                            <?php if ($formulario['descricao']): ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($formulario['descricao'], 0, 100)) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($formulario['etapa_nome']): ?>
                                            <span class="badge badge-light-info"><?= htmlspecialchars($formulario['etapa_nome']) ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">Não vinculado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $formulario['total_campos'] ?> campos</td>
                                        <td>
                                            <?php if ($formulario['ativo']): ?>
                                            <span class="badge badge-light-success">Ativo</span>
                                            <?php else: ?>
                                            <span class="badge badge-light-danger">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="formulario_cultura_analytics.php?id=<?= $formulario['id'] ?>" class="btn btn-sm btn-light-info">
                                                    <i class="ki-duotone ki-chart-simple fs-2 me-1">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Ver
                                                </a>
                                                <a href="formulario_cultura_editar.php?id=<?= $formulario['id'] ?>" class="btn btn-sm btn-light-primary">
                                                    Editar
                                                </a>
                                                <?php if (!$formulario['etapa_nome']): ?>
                                                <button class="btn btn-sm btn-light-info btn-vincular-etapa" 
                                                        data-formulario-id="<?= $formulario['id'] ?>"
                                                        data-formulario-nome="<?= htmlspecialchars($formulario['nome']) ?>"
                                                        title="Vincular à Etapa">
                                                    <i class="ki-duotone ki-link fs-2 me-1">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Vincular
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-light-danger btn-excluir-formulario" 
                                                        data-formulario-id="<?= $formulario['id'] ?>"
                                                        data-formulario-nome="<?= htmlspecialchars($formulario['nome']) ?>">
                                                    Excluir
                                                </button>
                                            </div>
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

<!-- Modal Novo Formulário -->
<div class="modal fade" id="modalNovoFormulario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Formulário de Cultura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formFormulario">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vincular à Etapa</label>
                        <select name="etapa_id" class="form-select">
                            <option value="">Nenhuma (aplicar manualmente)</option>
                            <?php foreach ($etapas as $etapa): ?>
                            <option value="<?= $etapa['id'] ?>"><?= htmlspecialchars($etapa['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar e Editar Campos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('formFormulario').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/formularios_cultura/criar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = 'formulario_cultura_editar.php?id=' + data.formulario_id;
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao criar formulário');
    }
});

// Vincular etapa
const etapasOptions = <?= json_encode(array_map(function($e) {
    return ['id' => $e['id'], 'nome' => $e['nome']];
}, $etapas)) ?>;

document.querySelectorAll('.btn-vincular-etapa').forEach(btn => {
    btn.addEventListener('click', async function() {
        const formularioId = this.dataset.formularioId;
        const formularioNome = this.dataset.formularioNome;
        
        // Busca dados do formulário
        let formularioData = null;
        try {
            const response = await fetch(`../api/recrutamento/formularios_cultura/detalhes.php?id=${formularioId}`);
            const data = await response.json();
            if (data.success) {
                formularioData = data.formulario;
            }
        } catch (error) {
            console.error('Erro ao buscar dados do formulário:', error);
        }
        
        let optionsHtml = '<option value="">Nenhuma (aplicar manualmente)</option>';
        etapasOptions.forEach(etapa => {
            const selected = formularioData && formularioData.etapa_id == etapa.id ? 'selected' : '';
            optionsHtml += `<option value="${etapa.id}" ${selected}>${etapa.nome}</option>`;
        });
        
        const modalHtml = `
            <div class="modal fade" id="modalVincularEtapa" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Vincular Formulário à Etapa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="formVincularEtapa">
                            <input type="hidden" name="formulario_id" value="${formularioId}">
                            <div class="modal-body">
                                <p class="mb-3">Formulário: <strong>${formularioNome}</strong></p>
                                <div class="mb-3">
                                    <label class="form-label">Selecione a Etapa</label>
                                    <select name="etapa_id" class="form-select">
                                        ${optionsHtml}
                                    </select>
                                    <div class="form-text">
                                        Se vinculado, o formulário será aplicado automaticamente quando o candidato chegar nesta etapa.
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
        `;
        
        // Remove modal anterior se existir
        const existingModal = document.getElementById('modalVincularEtapa');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Adiciona modal ao body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Mostra modal
        const modalEl = document.getElementById('modalVincularEtapa');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        
        // Handler do formulário
        const formHandler = async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Adiciona outros campos necessários
            if (formularioData) {
                formData.append('nome', formularioData.nome || '');
                formData.append('descricao', formularioData.descricao || '');
                formData.append('ativo', formularioData.ativo || '1');
            }
            
            try {
                const response = await fetch('../api/recrutamento/formularios_cultura/atualizar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Etapa vinculada com sucesso!');
                    modal.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                alert('Erro ao vincular etapa');
                console.error(error);
            }
        };
        
        document.getElementById('formVincularEtapa').addEventListener('submit', formHandler);
        
        // Remove modal quando fechado
        modalEl.addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    });
});

// Excluir formulário
document.querySelectorAll('.btn-excluir-formulario').forEach(btn => {
    btn.addEventListener('click', async function() {
        const formularioId = this.dataset.formularioId;
        const formularioNome = this.dataset.formularioNome;
        
        if (!confirm(`Deseja realmente excluir o formulário "${formularioNome}"?\n\nEsta ação não pode ser desfeita e excluirá todos os campos do formulário.`)) {
            return;
        }
        
        try {
            const response = await fetch(`../api/recrutamento/formularios_cultura/excluir.php?id=${formularioId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Formulário excluído com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            alert('Erro ao excluir formulário');
            console.error(error);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

