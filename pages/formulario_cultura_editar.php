<?php
/**
 * Editor de Formulário de Cultura - Versão Melhorada
 */

$page_title = 'Editar Formulário de Cultura';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('formularios_cultura.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$formulario_id = (int)($_GET['id'] ?? 0);

if (!$formulario_id) {
    redirect('formularios_cultura.php', 'Formulário não encontrado', 'error');
}

// Busca formulário
$stmt = $pdo->prepare("SELECT * FROM formularios_cultura WHERE id = ?");
$stmt->execute([$formulario_id]);
$formulario = $stmt->fetch();

if (!$formulario) {
    redirect('formularios_cultura.php', 'Formulário não encontrado', 'error');
}

// Busca campos
$stmt = $pdo->prepare("SELECT * FROM formularios_cultura_campos WHERE formulario_id = ? ORDER BY ordem ASC");
$stmt->execute([$formulario_id]);
$campos = $stmt->fetchAll();

// Busca etapas para vincular
$stmt = $pdo->query("SELECT * FROM processo_seletivo_etapas WHERE vaga_id IS NULL AND ativo = 1 ORDER BY ordem ASC");
$etapas = $stmt->fetchAll();

$tipos_campo = [
    'text' => 'Texto',
    'textarea' => 'Área de Texto',
    'number' => 'Número',
    'select' => 'Seleção',
    'radio' => 'Radio',
    'checkbox' => 'Checkbox',
    'escala' => 'Escala'
];
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2 class="mb-0"><?= htmlspecialchars($formulario['nome']) ?></h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="formulario_cultura_analytics.php?id=<?= $formulario_id ?>" class="btn btn-info me-2">
                                <i class="ki-duotone ki-chart-simple fs-2"></i>
                                Ver Analytics
                            </a>
                            <a href="formularios_cultura.php" class="btn btn-light">
                                <i class="ki-duotone ki-arrow-left fs-2"></i>
                                Voltar
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <?php if ($formulario['descricao']): ?>
                        <p class="text-muted"><?= htmlspecialchars($formulario['descricao']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Card: Configurações do Formulário -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Configurações do Formulário</h3>
                    </div>
                    <div class="card-body">
                        <form id="formConfigFormulario">
                            <input type="hidden" name="formulario_id" value="<?= $formulario_id ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-5">
                                        <label class="form-label">Nome do Formulário *</label>
                                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($formulario['nome']) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-5">
                                        <label class="form-label">Status</label>
                                        <select name="ativo" class="form-select">
                                            <option value="1" <?= $formulario['ativo'] ? 'selected' : '' ?>>Ativo</option>
                                            <option value="0" <?= !$formulario['ativo'] ? 'selected' : '' ?>>Inativo</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($formulario['descricao'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Vincular à Etapa do Processo Seletivo</label>
                                <select name="etapa_id" class="form-select">
                                    <option value="">Nenhuma (aplicar manualmente)</option>
                                    <?php foreach ($etapas as $etapa): ?>
                                    <option value="<?= $etapa['id'] ?>" <?= $formulario['etapa_id'] == $etapa['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($etapa['nome']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="ki-duotone ki-information-5 fs-6 text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Se vinculado a uma etapa, o formulário será aplicado automaticamente quando o candidato chegar nesta etapa.
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ki-duotone ki-check fs-2"></i>
                                    Salvar Configurações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Painel de Edição -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Campos do Formulário</h3>
                                <div class="card-toolbar">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoCampo">
                                        <i class="ki-duotone ki-plus fs-2"></i>
                                        Adicionar Campo
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($campos)): ?>
                                <div class="text-center py-10">
                                    <i class="ki-duotone ki-file-up fs-3x text-muted mb-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <p class="text-muted">Nenhum campo adicionado ainda. Clique em "Adicionar Campo" para começar.</p>
                                </div>
                                <?php else: ?>
                                <div id="camposList" class="sortable-list">
                                    <?php foreach ($campos as $index => $campo): 
                                        $opcoes = $campo['opcoes'] ? json_decode($campo['opcoes'], true) : [];
                                    ?>
                                    <div class="card mb-3 campo-item" data-campo-id="<?= $campo['id'] ?>" data-ordem="<?= $campo['ordem'] ?>">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start">
                                                <div class="drag-handle me-3" style="cursor: move;">
                                                    <i class="ki-duotone ki-menu fs-2 text-muted">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                    </i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <h5 class="mb-1">
                                                                <?= htmlspecialchars($campo['label']) ?>
                                                                <?php if ($campo['obrigatorio']): ?>
                                                                <span class="badge badge-light-danger ms-2">Obrigatório</span>
                                                                <?php endif; ?>
                                                            </h5>
                                                            <div class="d-flex gap-3 text-muted small">
                                                                <span><i class="ki-duotone ki-tag fs-6"></i> <?= htmlspecialchars($tipos_campo[$campo['tipo_campo']] ?? $campo['tipo_campo']) ?></span>
                                                                <span><i class="ki-duotone ki-arrows-circle fs-6"></i> Ordem: <?= $campo['ordem'] ?></span>
                                                                <?php if ($campo['tipo_campo'] === 'escala'): ?>
                                                                <span><i class="ki-duotone ki-chart-simple fs-6"></i> Escala: <?= $campo['escala_min'] ?>-<?= $campo['escala_max'] ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-sm btn-light-warning btn-editar-campo" 
                                                                    data-campo-id="<?= $campo['id'] ?>"
                                                                    title="Editar">
                                                                <i class="ki-duotone ki-notepad-edit fs-5">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                </i>
                                                            </button>
                                                            <button class="btn btn-sm btn-light-danger btn-excluir-campo" 
                                                                    data-campo-id="<?= $campo['id'] ?>"
                                                                    title="Excluir">
                                                                <i class="ki-duotone ki-trash fs-5">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                </i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Preview do Campo -->
                                                    <div class="preview-campo mt-3 p-3 bg-light rounded">
                                                        <?php if ($campo['tipo_campo'] === 'text'): ?>
                                                        <input type="text" class="form-control" placeholder="<?= htmlspecialchars($campo['label']) ?>" disabled>
                                                        <?php elseif ($campo['tipo_campo'] === 'textarea'): ?>
                                                        <textarea class="form-control" rows="3" placeholder="<?= htmlspecialchars($campo['label']) ?>" disabled></textarea>
                                                        <?php elseif ($campo['tipo_campo'] === 'number'): ?>
                                                        <input type="number" class="form-control" placeholder="<?= htmlspecialchars($campo['label']) ?>" disabled>
                                                        <?php elseif ($campo['tipo_campo'] === 'select' && !empty($opcoes)): ?>
                                                        <select class="form-select" disabled>
                                                            <option><?= htmlspecialchars($campo['label']) ?></option>
                                                            <?php foreach ($opcoes as $opcao): ?>
                                                            <option><?= htmlspecialchars($opcao) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php elseif ($campo['tipo_campo'] === 'radio' && !empty($opcoes)): ?>
                                                        <?php foreach ($opcoes as $opcao): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="preview_radio_<?= $campo['id'] ?>" disabled>
                                                            <label class="form-check-label"><?= htmlspecialchars($opcao) ?></label>
                                                        </div>
                                                        <?php endforeach; ?>
                                                        <?php elseif ($campo['tipo_campo'] === 'checkbox' && !empty($opcoes)): ?>
                                                        <?php foreach ($opcoes as $opcao): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" disabled>
                                                            <label class="form-check-label"><?= htmlspecialchars($opcao) ?></label>
                                                        </div>
                                                        <?php endforeach; ?>
                                                        <?php elseif ($campo['tipo_campo'] === 'escala'): ?>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span><?= $campo['escala_min'] ?></span>
                                                            <input type="range" class="form-range" min="<?= $campo['escala_min'] ?>" max="<?= $campo['escala_max'] ?>" disabled>
                                                            <span><?= $campo['escala_max'] ?></span>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview do Formulário -->
                    <div class="col-lg-4">
                        <div class="card position-sticky" style="top: 20px;">
                            <div class="card-header">
                                <h3 class="card-title">Preview</h3>
                            </div>
                            <div class="card-body">
                                <form id="previewForm" class="preview-form">
                                    <?php foreach ($campos as $campo): 
                                        $opcoes = $campo['opcoes'] ? json_decode($campo['opcoes'], true) : [];
                                    ?>
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <?= htmlspecialchars($campo['label']) ?>
                                            <?php if ($campo['obrigatorio']): ?>
                                            <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php if ($campo['tipo_campo'] === 'text'): ?>
                                        <input type="text" class="form-control form-control-sm">
                                        <?php elseif ($campo['tipo_campo'] === 'textarea'): ?>
                                        <textarea class="form-control form-control-sm" rows="3"></textarea>
                                        <?php elseif ($campo['tipo_campo'] === 'number'): ?>
                                        <input type="number" class="form-control form-control-sm">
                                        <?php elseif ($campo['tipo_campo'] === 'select' && !empty($opcoes)): ?>
                                        <select class="form-select form-select-sm">
                                            <option>Selecione...</option>
                                            <?php foreach ($opcoes as $opcao): ?>
                                            <option><?= htmlspecialchars($opcao) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php elseif ($campo['tipo_campo'] === 'radio' && !empty($opcoes)): ?>
                                        <?php foreach ($opcoes as $opcao): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="preview_<?= $campo['id'] ?>">
                                            <label class="form-check-label"><?= htmlspecialchars($opcao) ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php elseif ($campo['tipo_campo'] === 'checkbox' && !empty($opcoes)): ?>
                                        <?php foreach ($opcoes as $opcao): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox">
                                            <label class="form-check-label"><?= htmlspecialchars($opcao) ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php elseif ($campo['tipo_campo'] === 'escala'): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="small"><?= $campo['escala_min'] ?></span>
                                            <input type="range" class="form-range" min="<?= $campo['escala_min'] ?>" max="<?= $campo['escala_max'] ?>">
                                            <span class="small"><?= $campo['escala_max'] ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($campos)): ?>
                                    <p class="text-muted text-center small">Adicione campos para ver o preview</p>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo/Editar Campo -->
<div class="modal fade" id="modalNovoCampo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Campo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCampo">
                <div class="modal-body">
                    <input type="hidden" name="formulario_id" value="<?= $formulario_id ?>">
                    <input type="hidden" name="campo_id" id="campo_id">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Label (Rótulo) *</label>
                                <input type="text" name="label" class="form-control" required id="campoLabel" placeholder="Ex: Como você descreveria nossa cultura?">
                                <small class="form-text text-muted">Texto que aparecerá para o candidato</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Ordem</label>
                                <input type="number" name="ordem" class="form-control" value="0" id="campoOrdem">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Campo *</label>
                        <select name="tipo_campo" class="form-select" required id="tipoCampo">
                            <option value="">Selecione o tipo...</option>
                            <?php foreach ($tipos_campo as $codigo => $nome): ?>
                            <option value="<?= $codigo ?>" data-icon="ki-tag"><?= htmlspecialchars($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Escolha o tipo de resposta esperada</small>
                    </div>
                    
                    <div class="mb-3" id="opcoesContainer" style="display: none;">
                        <label class="form-label">Opções *</label>
                        <textarea name="opcoes" class="form-control" rows="5" id="campoOpcoes" placeholder="Digite uma opção por linha&#10;Exemplo:&#10;Opção 1&#10;Opção 2&#10;Opção 3"></textarea>
                        <small class="form-text text-muted">Uma opção por linha. Para select, radio e checkbox.</small>
                    </div>
                    
                    <div class="row mb-3" id="escalaContainer" style="display: none;">
                        <div class="col-md-4">
                            <label class="form-label">Escala Mínima</label>
                            <input type="number" name="escala_min" class="form-control" value="1" id="escalaMin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Escala Máxima</label>
                            <input type="number" name="escala_max" class="form-control" value="5" id="escalaMax">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Peso na Pontuação</label>
                            <input type="number" name="peso" class="form-control" step="0.01" value="1.00" id="campoPeso">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="obrigatorio" id="campoObrigatorio" value="1" checked>
                            <label class="form-check-label" for="campoObrigatorio">
                                Campo obrigatório
                            </label>
                        </div>
                        <small class="form-text text-muted">Candidatos precisarão responder este campo</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-duotone ki-check fs-2"></i>
                        Salvar Campo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.sortable-list .campo-item {
    transition: transform 0.2s;
}
.sortable-list .campo-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.drag-handle {
    cursor: move;
}
.preview-campo {
    background-color: #f8f9fa;
    border: 1px dashed #dee2e6;
}
.preview-form {
    max-height: 600px;
    overflow-y: auto;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// Sortable para reordenar campos
let sortable = null;
if (document.getElementById('camposList')) {
    sortable = Sortable.create(document.getElementById('camposList'), {
        handle: '.drag-handle',
        animation: 150,
        onEnd: async function(evt) {
            const items = Array.from(evt.to.children);
            const updates = [];
            
            items.forEach((item, index) => {
                const campoId = item.dataset.campoId;
                const novaOrdem = index + 1;
                updates.push({ campoId, ordem: novaOrdem });
            });
            
            // Atualiza ordens
            for (const update of updates) {
                try {
                    const formData = new FormData();
                    formData.append('campo_id', update.campoId);
                    formData.append('formulario_id', <?= $formulario_id ?>);
                    formData.append('ordem', update.ordem);
                    
                    // Busca dados do campo atual
                    const campoItem = document.querySelector(`[data-campo-id="${update.campoId}"]`);
                    const label = campoItem.querySelector('h5').textContent.trim();
                    const tipo = campoItem.querySelector('.text-muted').textContent.match(/Texto|Área de Texto|Número|Seleção|Radio|Checkbox|Escala/)?.[0];
                    
                    // Envia apenas a ordem (API precisa aceitar apenas ordem)
                    await fetch('../api/recrutamento/formularios_cultura/atualizar_ordem.php', {
                        method: 'POST',
                        body: formData
                    });
                } catch (error) {
                    console.error('Erro ao atualizar ordem:', error);
                }
            }
            
            // Recarrega preview
            location.reload();
        }
    });
}

// Mostra/esconde campos baseado no tipo
document.getElementById('tipoCampo').addEventListener('change', function() {
    const tipo = this.value;
    const opcoesContainer = document.getElementById('opcoesContainer');
    const escalaContainer = document.getElementById('escalaContainer');
    
    if (['select', 'radio', 'checkbox'].includes(tipo)) {
        opcoesContainer.style.display = 'block';
        opcoesContainer.querySelector('textarea').required = true;
    } else {
        opcoesContainer.style.display = 'none';
        opcoesContainer.querySelector('textarea').required = false;
    }
    
    if (tipo === 'escala') {
        escalaContainer.style.display = 'block';
    } else {
        escalaContainer.style.display = 'none';
    }
});

// Reset modal
document.getElementById('modalNovoCampo').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formCampo').reset();
    document.getElementById('campo_id').value = '';
    document.getElementById('opcoesContainer').style.display = 'none';
    document.getElementById('escalaContainer').style.display = 'none';
    document.querySelector('#modalNovoCampo .modal-title').textContent = 'Novo Campo';
});

document.getElementById('formCampo').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Processa opções
    if (formData.get('opcoes')) {
        const opcoes = formData.get('opcoes').split('\n').filter(o => o.trim());
        if (opcoes.length === 0) {
            alert('Adicione pelo menos uma opção para este tipo de campo');
            return;
        }
        formData.set('opcoes', JSON.stringify(opcoes));
    }
    
    // Auto-ordem se não informada
    if (!formData.get('ordem') || formData.get('ordem') == '0') {
        const totalCampos = document.querySelectorAll('.campo-item').length;
        formData.set('ordem', totalCampos + 1);
    }
    
    try {
        const response = await fetch('../api/recrutamento/formularios_cultura/salvar_campo.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao salvar campo');
        console.error(error);
    }
});

// Editar campo
document.querySelectorAll('.btn-editar-campo').forEach(btn => {
    btn.addEventListener('click', async function() {
        const campoId = this.dataset.campoId;
        
        try {
            const response = await fetch(`../api/recrutamento/formularios_cultura/detalhes_campo.php?id=${campoId}`);
            const data = await response.json();
            
            if (data.success) {
                const campo = data.campo;
                document.getElementById('campo_id').value = campo.id;
                document.getElementById('campoLabel').value = campo.label;
                document.getElementById('tipoCampo').value = campo.tipo_campo;
                document.getElementById('campoOrdem').value = campo.ordem;
                document.getElementById('campoObrigatorio').checked = campo.obrigatorio == 1;
                
                if (campo.opcoes) {
                    const opcoes = JSON.parse(campo.opcoes);
                    document.getElementById('campoOpcoes').value = opcoes.join('\n');
                }
                
                if (campo.escala_min) {
                    document.getElementById('escalaMin').value = campo.escala_min;
                }
                if (campo.escala_max) {
                    document.getElementById('escalaMax').value = campo.escala_max;
                }
                if (campo.peso) {
                    document.getElementById('campoPeso').value = campo.peso;
                }
                
                // Trigger change para mostrar campos condicionais
                document.getElementById('tipoCampo').dispatchEvent(new Event('change'));
                
                document.querySelector('#modalNovoCampo .modal-title').textContent = 'Editar Campo';
                const modal = new bootstrap.Modal(document.getElementById('modalNovoCampo'));
                modal.show();
            }
        } catch (error) {
            alert('Erro ao carregar campo');
            console.error(error);
        }
    });
});

// Excluir campo
document.querySelectorAll('.btn-excluir-campo').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Deseja realmente excluir este campo?')) return;
        
        const campoId = this.dataset.campoId;
        
        try {
            const response = await fetch(`../api/recrutamento/formularios_cultura/excluir_campo.php?id=${campoId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            alert('Erro ao excluir campo');
            console.error(error);
        }
    });
});
// Salvar configurações do formulário
document.getElementById('formConfigFormulario').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    const btnSubmit = this.querySelector('button[type="submit"]');
    const originalText = btnSubmit.innerHTML;
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
    
    try {
        const response = await fetch('../api/recrutamento/formularios_cultura/atualizar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao salvar configurações'
        });
        console.error(error);
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = originalText;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
