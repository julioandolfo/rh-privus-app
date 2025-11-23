<?php
/**
 * Editor de Landing Page da Vaga
 */

$page_title = 'Editor de Landing Page';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('vaga_landing_page.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$vaga_id = (int)($_GET['id'] ?? 0);

if (!$vaga_id) {
    redirect('vagas.php', 'Vaga não encontrada', 'error');
}

// Busca vaga
$stmt = $pdo->prepare("SELECT * FROM vagas WHERE id = ?");
$stmt->execute([$vaga_id]);
$vaga = $stmt->fetch();

if (!$vaga || !can_access_empresa($vaga['empresa_id'])) {
    redirect('vagas.php', 'Sem permissão', 'error');
}

// Busca ou cria landing page
$stmt = $pdo->prepare("SELECT * FROM vagas_landing_pages WHERE vaga_id = ?");
$stmt->execute([$vaga_id]);
$landing_page = $stmt->fetch();

if (!$landing_page) {
    $stmt = $pdo->prepare("INSERT INTO vagas_landing_pages (vaga_id, ativo) VALUES (?, 1)");
    $stmt->execute([$vaga_id]);
    $landing_page_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT * FROM vagas_landing_pages WHERE id = ?");
    $stmt->execute([$landing_page_id]);
    $landing_page = $stmt->fetch();
}

// Busca componentes
$stmt = $pdo->prepare("
    SELECT * FROM vagas_landing_page_componentes
    WHERE landing_page_id = ?
    ORDER BY ordem ASC
");
$stmt->execute([$landing_page['id']]);
$componentes = $stmt->fetchAll();

$tipos_componentes = [
    'hero' => 'Hero/Banner Principal',
    'sobre_vaga' => 'Sobre a Vaga',
    'requisitos' => 'Requisitos',
    'beneficios' => 'Benefícios',
    'processo_seletivo' => 'Processo Seletivo',
    'depoimentos' => 'Depoimentos',
    'cta' => 'Call to Action',
    'formulario' => 'Formulário de Candidatura',
    'custom' => 'Componente Customizado'
];
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Editor de Landing Page - <?= htmlspecialchars($vaga['titulo']) ?></h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="../vaga_publica.php?id=<?= $vaga_id ?>" target="_blank" class="btn btn-light-primary me-2">
                                <i class="ki-duotone ki-eye fs-2"></i>
                                Visualizar
                            </a>
                            <a href="vagas.php" class="btn btn-light">
                                Voltar
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <!-- Configurações Gerais -->
                        <div class="card mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Configurações Gerais</h3>
                            </div>
                            <div class="card-body">
                                <form id="formConfig" class="row g-3">
                                    <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Título da Página (SEO)</label>
                                        <input type="text" name="titulo_pagina" class="form-control" 
                                               value="<?= htmlspecialchars($landing_page['titulo_pagina'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Meta Descrição (SEO)</label>
                                        <input type="text" name="meta_descricao" class="form-control" 
                                               value="<?= htmlspecialchars($landing_page['meta_descricao'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Logo da Empresa</label>
                                        <input type="file" name="logo_empresa" class="form-control" accept="image/*">
                                        <?php if ($landing_page['logo_empresa']): ?>
                                        <img src="<?= htmlspecialchars($landing_page['logo_empresa']) ?>" 
                                             class="mt-2" style="max-height: 60px;">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Imagem Hero/Banner</label>
                                        <input type="file" name="imagem_hero" class="form-control" accept="image/*">
                                        <?php if ($landing_page['imagem_hero']): ?>
                                        <img src="<?= htmlspecialchars($landing_page['imagem_hero']) ?>" 
                                             class="mt-2" style="max-height: 100px;">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Cor Primária</label>
                                        <input type="color" name="cor_primaria" class="form-control form-control-color" 
                                               value="<?= htmlspecialchars($landing_page['cor_primaria'] ?? '#009ef7') ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Cor Secundária</label>
                                        <input type="color" name="cor_secundaria" class="form-control form-control-color" 
                                               value="<?= htmlspecialchars($landing_page['cor_secundaria'] ?? '#f1416c') ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Layout</label>
                                        <select name="layout" class="form-select">
                                            <option value="padrao" <?= ($landing_page['layout'] ?? 'padrao') === 'padrao' ? 'selected' : '' ?>>Padrão</option>
                                            <option value="moderno" <?= ($landing_page['layout'] ?? '') === 'moderno' ? 'selected' : '' ?>>Moderno</option>
                                            <option value="minimalista" <?= ($landing_page['layout'] ?? '') === 'minimalista' ? 'selected' : '' ?>>Minimalista</option>
                                            <option value="criativo" <?= ($landing_page['layout'] ?? '') === 'criativo' ? 'selected' : '' ?>>Criativo</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Componentes -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Componentes da Página</h3>
                                <div class="card-toolbar">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoComponente">
                                        <i class="ki-duotone ki-plus fs-2"></i>
                                        Novo Componente
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="componentesList" class="sortable-list">
                                    <?php foreach ($componentes as $componente): ?>
                                    <div class="card mb-3 componente-item" data-componente-id="<?= $componente['id'] ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h5><?= htmlspecialchars($componente['titulo'] ?: $tipos_componentes[$componente['tipo_componente']] ?? $componente['tipo_componente']) ?></h5>
                                                    <p class="text-muted mb-0">
                                                        Tipo: <?= htmlspecialchars($tipos_componentes[$componente['tipo_componente']] ?? $componente['tipo_componente']) ?> | 
                                                        Ordem: <?= $componente['ordem'] ?> |
                                                        <?= $componente['visivel'] ? '<span class="text-success">Visível</span>' : '<span class="text-muted">Oculto</span>' ?>
                                                    </p>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-light-warning btn-editar-componente" 
                                                            data-componente-id="<?= $componente['id'] ?>">
                                                        Editar
                                                    </button>
                                                    <button class="btn btn-sm btn-light-danger btn-excluir-componente" 
                                                            data-componente-id="<?= $componente['id'] ?>">
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
    </div>
</div>

<!-- Modal Novo/Editar Componente -->
<div class="modal fade" id="modalNovoComponente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Componente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formComponente">
                <div class="modal-body">
                    <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
                    <input type="hidden" name="componente_id" id="componente_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Componente *</label>
                        <select name="tipo_componente" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos_componentes as $codigo => $nome): ?>
                            <option value="<?= $codigo ?>"><?= htmlspecialchars($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Conteúdo</label>
                        <textarea name="conteudo" class="form-control" rows="5"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Imagem</label>
                        <input type="file" name="imagem" class="form-control" accept="image/*">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Visível</label>
                            <select name="visivel" class="form-select">
                                <option value="1">Sim</option>
                                <option value="0">Não</option>
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
// Salvar configurações
document.getElementById('formConfig').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/landing_pages/salvar_config.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Configurações salvas com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao salvar configurações');
    }
});

// Salvar componente
document.getElementById('formComponente').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/landing_pages/salvar_componente.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Componente salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao salvar componente');
    }
});

// Excluir componente
document.querySelectorAll('.btn-excluir-componente').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Deseja realmente excluir este componente?')) return;
        
        const componenteId = this.dataset.componenteId;
        
        try {
            const response = await fetch(`../api/recrutamento/landing_pages/excluir_componente.php?id=${componenteId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.closest('.componente-item').remove();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            alert('Erro ao excluir componente');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

