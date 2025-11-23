<?php
/**
 * Configuração de Etapas do Processo Seletivo
 */

$page_title = 'Etapas do Processo Seletivo';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('etapas_processo.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca etapas padrão (com proteção contra duplicatas)
$stmt = $pdo->query("
    SELECT * FROM processo_seletivo_etapas
    WHERE vaga_id IS NULL
    ORDER BY ordem ASC, id ASC
");
$etapas_padrao = $stmt->fetchAll();

// Remove duplicatas por ID (segurança adicional caso haja duplicatas no banco)
$etapas_unicas = [];
$ids_vistos = [];
foreach ($etapas_padrao as $etapa) {
    if (!in_array($etapa['id'], $ids_vistos)) {
        $etapas_unicas[] = $etapa;
        $ids_vistos[] = $etapa['id'];
    }
}
$etapas_padrao = $etapas_unicas;

$tipos_etapas = [
    'triagem' => 'Triagem',
    'entrevista_rh' => 'Entrevista RH',
    'entrevista_gestor' => 'Entrevista Gestor',
    'entrevista_tecnica' => 'Entrevista Técnica',
    'entrevista_diretoria' => 'Entrevista Diretoria',
    'teste_tecnico' => 'Teste Técnico',
    'formulario_cultura' => 'Formulário de Cultura',
    'dinamica_grupo' => 'Dinâmica de Grupo',
    'aprovacao' => 'Aprovação',
    'outro' => 'Outro'
];
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Etapas Padrão do Processo Seletivo</h2>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaEtapa">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Etapa
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Ordem</th>
                                        <th>Nome</th>
                                        <th>Código</th>
                                        <th>Tipo</th>
                                        <th>Obrigatória</th>
                                        <th>Permite Pular</th>
                                        <th>Cor Kanban</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($etapas_padrao)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-10">
                                            Nenhuma etapa cadastrada ainda. Clique em "Nova Etapa" para começar.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($etapas_padrao as $etapa): ?>
                                    <tr>
                                        <td><?= $etapa['ordem'] ?></td>
                                        <td><strong><?= htmlspecialchars($etapa['nome']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($etapa['codigo']) ?></code></td>
                                        <td><?= htmlspecialchars($tipos_etapas[$etapa['tipo']] ?? $etapa['tipo']) ?></td>
                                        <td>
                                            <?php if ($etapa['obrigatoria']): ?>
                                            <span class="badge badge-light-success">Sim</span>
                                            <?php else: ?>
                                            <span class="badge badge-light-secondary">Não</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($etapa['permite_pular']): ?>
                                            <span class="badge badge-light-warning">Sim</span>
                                            <?php else: ?>
                                            <span class="badge badge-light-secondary">Não</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: <?= htmlspecialchars($etapa['cor_kanban']) ?>; color: white;">
                                                <?= htmlspecialchars($etapa['cor_kanban']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($etapa['ativo']): ?>
                                            <span class="badge badge-light-success">Ativa</span>
                                            <?php else: ?>
                                            <span class="badge badge-light-danger">Inativa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-light-warning btn-editar-etapa" 
                                                        data-etapa-id="<?= $etapa['id'] ?>">
                                                    Editar
                                                </button>
                                                <button class="btn btn-sm btn-light-danger btn-excluir-etapa" 
                                                        data-etapa-id="<?= $etapa['id'] ?>"
                                                        data-etapa-nome="<?= htmlspecialchars($etapa['nome']) ?>">
                                                    Excluir
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova/Editar Etapa -->
<div class="modal fade" id="modalNovaEtapa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Etapa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEtapa">
                <div class="modal-body">
                    <input type="hidden" name="etapa_id" id="etapa_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Código (único) *</label>
                        <input type="text" name="codigo" class="form-control" required>
                        <small class="form-text text-muted">Identificador único (ex: entrevista_rh)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo *</label>
                        <select name="tipo" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos_etapas as $codigo => $nome): ?>
                            <option value="<?= $codigo ?>"><?= htmlspecialchars($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cor Kanban</label>
                            <input type="color" name="cor_kanban" class="form-control form-control-color" value="#6c757d">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tempo Médio (min)</label>
                            <input type="number" name="tempo_medio_minutos" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="obrigatoria" id="obrigatoria" value="1" checked>
                                <label class="form-check-label" for="obrigatoria">Obrigatória</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permite_pular" id="permite_pular" value="1">
                                <label class="form-check-label" for="permite_pular">Permite Pular</label>
                            </div>
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

<script>
document.getElementById('formEtapa').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/etapas/salvar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Etapa salva com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao salvar etapa');
    }
});

// Editar etapa
document.querySelectorAll('.btn-editar-etapa').forEach(btn => {
    btn.addEventListener('click', async function() {
        const etapaId = this.dataset.etapaId;
        
        try {
            const response = await fetch(`../api/recrutamento/etapas/detalhes.php?id=${etapaId}`);
            const data = await response.json();
            
            if (data.success) {
                const etapa = data.etapa;
                document.getElementById('etapa_id').value = etapa.id;
                document.querySelector('[name="nome"]').value = etapa.nome;
                document.querySelector('[name="codigo"]').value = etapa.codigo;
                document.querySelector('[name="tipo"]').value = etapa.tipo;
                document.querySelector('[name="ordem"]').value = etapa.ordem;
                document.querySelector('[name="cor_kanban"]').value = etapa.cor_kanban;
                document.querySelector('[name="tempo_medio_minutos"]').value = etapa.tempo_medio_minutos || '';
                document.querySelector('[name="descricao"]').value = etapa.descricao || '';
                document.getElementById('obrigatoria').checked = etapa.obrigatoria == 1;
                document.getElementById('permite_pular').checked = etapa.permite_pular == 1;
                
                document.querySelector('#modalNovaEtapa .modal-title').textContent = 'Editar Etapa';
                const modal = new bootstrap.Modal(document.getElementById('modalNovaEtapa'));
                modal.show();
            }
        } catch (error) {
            alert('Erro ao carregar etapa');
        }
    });
});

// Excluir etapa
document.querySelectorAll('.btn-excluir-etapa').forEach(btn => {
    btn.addEventListener('click', async function() {
        const etapaId = this.dataset.etapaId;
        const etapaNome = this.dataset.etapaNome;
        
        if (!confirm(`Deseja realmente excluir a etapa "${etapaNome}"?\n\nEsta ação não pode ser desfeita.`)) {
            return;
        }
        
        try {
            const response = await fetch(`../api/recrutamento/etapas/excluir.php?id=${etapaId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Etapa excluída com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            alert('Erro ao excluir etapa');
            console.error(error);
        }
    });
});

// Reset modal ao fechar
document.getElementById('modalNovaEtapa').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formEtapa').reset();
    document.getElementById('etapa_id').value = '';
    document.querySelector('#modalNovaEtapa .modal-title').textContent = 'Nova Etapa';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

