<?php
/**
 * Editor de Formulário de Cultura
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
                    <div class="card-header">
                        <h2><?= htmlspecialchars($formulario['nome']) ?></h2>
                        <div class="card-toolbar">
                            <a href="formularios_cultura.php" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <p><?= htmlspecialchars($formulario['descricao'] ?? '') ?></p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Campos do Formulário</h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoCampo">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Novo Campo
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="camposList">
                            <?php foreach ($campos as $campo): ?>
                            <div class="card mb-3 campo-item" data-campo-id="<?= $campo['id'] ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h5><?= htmlspecialchars($campo['label']) ?></h5>
                                            <p class="text-muted mb-0">
                                                Tipo: <?= htmlspecialchars($tipos_campo[$campo['tipo_campo']] ?? $campo['tipo_campo']) ?> | 
                                                Ordem: <?= $campo['ordem'] ?> |
                                                <?= $campo['obrigatorio'] ? '<span class="text-danger">Obrigatório</span>' : '<span class="text-muted">Opcional</span>' ?>
                                            </p>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-light-warning btn-editar-campo" 
                                                    data-campo-id="<?= $campo['id'] ?>">
                                                Editar
                                            </button>
                                            <button class="btn btn-sm btn-light-danger btn-excluir-campo" 
                                                    data-campo-id="<?= $campo['id'] ?>">
                                                Excluir
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
                    
                    <div class="mb-3">
                        <label class="form-label">Label (Rótulo) *</label>
                        <input type="text" name="label" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Campo *</label>
                        <select name="tipo_campo" class="form-select" required id="tipoCampo">
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos_campo as $codigo => $nome): ?>
                            <option value="<?= $codigo ?>"><?= htmlspecialchars($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="opcoesContainer" style="display: none;">
                        <label class="form-label">Opções (uma por linha)</label>
                        <textarea name="opcoes" class="form-control" rows="5" placeholder="Opção 1&#10;Opção 2&#10;Opção 3"></textarea>
                        <small class="form-text text-muted">Para select, radio e checkbox</small>
                    </div>
                    
                    <div class="row mb-3" id="escalaContainer" style="display: none;">
                        <div class="col-md-4">
                            <label class="form-label">Escala Mínima</label>
                            <input type="number" name="escala_min" class="form-control" value="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Escala Máxima</label>
                            <input type="number" name="escala_max" class="form-control" value="5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Peso na Pontuação</label>
                            <input type="number" name="peso" class="form-control" step="0.01" value="1.00">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Obrigatório</label>
                            <select name="obrigatorio" class="form-select">
                                <option value="0">Não</option>
                                <option value="1">Sim</option>
                            </select>
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
// Mostra/esconde campos baseado no tipo
document.getElementById('tipoCampo').addEventListener('change', function() {
    const tipo = this.value;
    const opcoesContainer = document.getElementById('opcoesContainer');
    const escalaContainer = document.getElementById('escalaContainer');
    
    if (['select', 'radio', 'checkbox'].includes(tipo)) {
        opcoesContainer.style.display = 'block';
    } else {
        opcoesContainer.style.display = 'none';
    }
    
    if (tipo === 'escala') {
        escalaContainer.style.display = 'block';
    } else {
        escalaContainer.style.display = 'none';
    }
});

document.getElementById('formCampo').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Processa opções
    if (formData.get('opcoes')) {
        const opcoes = formData.get('opcoes').split('\n').filter(o => o.trim());
        formData.set('opcoes', JSON.stringify(opcoes));
    }
    
    try {
        const response = await fetch('../api/recrutamento/formularios_cultura/salvar_campo.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Campo salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao salvar campo');
    }
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
                this.closest('.campo-item').remove();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            alert('Erro ao excluir campo');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

