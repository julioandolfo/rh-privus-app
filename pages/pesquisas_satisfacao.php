<?php
/**
 * Página de Gestão de Pesquisas de Satisfação
 */

$page_title = 'Pesquisas de Satisfação';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('pesquisas_satisfacao.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Lista pesquisas
$stmt = $pdo->prepare("
    SELECT ps.*, u.nome as criado_por_nome,
           (SELECT COUNT(*) FROM pesquisas_satisfacao_envios WHERE pesquisa_id = ps.id) as total_envios,
           (SELECT COUNT(*) FROM pesquisas_satisfacao_envios WHERE pesquisa_id = ps.id AND respondida = 1) as total_respostas
    FROM pesquisas_satisfacao ps
    LEFT JOIN usuarios u ON ps.created_by = u.id
    ORDER BY ps.created_at DESC
");
$stmt->execute();
$pesquisas = $stmt->fetchAll();
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Pesquisas de Satisfação</h2>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-criar-pesquisa">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Pesquisa
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Título</th>
                                        <th>Status</th>
                                        <th>Período</th>
                                        <th>Respostas</th>
                                        <th>Criado por</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pesquisas as $pesquisa): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($pesquisa['titulo']) ?></strong>
                                            <?php if ($pesquisa['link_token']): ?>
                                            <br><small class="text-muted">
                                                Link: <a href="<?= get_base_url() ?>/pages/responder_pesquisa.php?token=<?= $pesquisa['link_token'] ?>" target="_blank">
                                                    <?= get_base_url() ?>/pages/responder_pesquisa.php?token=<?= substr($pesquisa['link_token'], 0, 20) ?>...
                                                </a>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = [
                                                'rascunho' => 'badge-secondary',
                                                'ativa' => 'badge-success',
                                                'finalizada' => 'badge-info',
                                                'cancelada' => 'badge-danger'
                                            ];
                                            $status_class = $badge_class[$pesquisa['status']] ?? 'badge-secondary';
                                            ?>
                                            <span class="badge <?= $status_class ?>"><?= ucfirst($pesquisa['status']) ?></span>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($pesquisa['data_inicio'])) ?>
                                            <?php if ($pesquisa['data_fim']): ?>
                                            até <?= date('d/m/Y', strtotime($pesquisa['data_fim'])) ?>
                                            <?php else: ?>
                                            (sem término)
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $pesquisa['total_respostas'] ?> / <?= $pesquisa['total_envios'] ?>
                                            <?php if ($pesquisa['total_envios'] > 0): ?>
                                            (<?= round(($pesquisa['total_respostas'] / $pesquisa['total_envios']) * 100, 1) ?>%)
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($pesquisa['criado_por_nome']) ?></td>
                                        <td>
                                            <?php if ($pesquisa['status'] === 'rascunho'): ?>
                                            <button class="btn btn-sm btn-success btn-publicar" data-id="<?= $pesquisa['id'] ?>">
                                                Publicar
                                            </button>
                                            <?php endif; ?>
                                            <a href="pesquisa_view.php?id=<?= $pesquisa['id'] ?>" class="btn btn-sm btn-primary">
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

<!-- Modal Criar Pesquisa -->
<div class="modal fade" id="modal-criar-pesquisa" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Pesquisa de Satisfação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-criar-pesquisa">
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Início *</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Público Alvo</label>
                        <select name="publico_alvo" class="form-select" id="publico_alvo">
                            <option value="todos">Todos</option>
                            <option value="empresa">Empresa</option>
                            <option value="setor">Setor</option>
                            <option value="cargo">Cargo</option>
                            <option value="especifico">Específico</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-check">
                            <input type="checkbox" name="enviar_email" value="1" checked class="form-check-input">
                            Enviar email
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="enviar_push" value="1" checked class="form-check-input">
                            Enviar notificação push
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="anonima" value="1" class="form-check-input">
                            Pesquisa anônima
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Campos da Pesquisa</h6>
                        <div id="campos-container">
                            <!-- Campos serão adicionados dinamicamente -->
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="btn-adicionar-campo">
                            + Adicionar Campo
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-salvar-pesquisa">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
let campoIndex = 0;

document.getElementById('btn-adicionar-campo').addEventListener('click', function() {
    const container = document.getElementById('campos-container');
    const campoHtml = `
        <div class="card mb-3 campo-item" data-index="${campoIndex}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="campos[${campoIndex}][tipo]" class="form-select campo-tipo">
                            <option value="texto">Texto</option>
                            <option value="textarea">Texto Longo</option>
                            <option value="multipla_escolha">Múltipla Escolha</option>
                            <option value="checkbox_multiplo">Checkbox Múltiplo</option>
                            <option value="escala_1_5">Escala 1-5</option>
                            <option value="escala_1_10">Escala 1-10</option>
                            <option value="sim_nao">Sim/Não</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rótulo *</label>
                        <input type="text" name="campos[${campoIndex}][label]" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ordem</label>
                        <input type="number" name="campos[${campoIndex}][ordem]" class="form-control" value="${campoIndex}">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="campos[${campoIndex}][descricao]" class="form-control">
                    </div>
                </div>
                <div class="row mt-2 campo-opcoes" style="display:none;">
                    <div class="col-md-12">
                        <label class="form-label">Opções (uma por linha)</label>
                        <textarea name="campos[${campoIndex}][opcoes_text]" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-check">
                        <input type="checkbox" name="campos[${campoIndex}][obrigatorio]" value="1" class="form-check-input">
                        Obrigatório
                    </label>
                    <button type="button" class="btn btn-sm btn-danger float-end btn-remover-campo">Remover</button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', campoHtml);
    campoIndex++;
    
    // Mostra/esconde opções baseado no tipo
    const campoItem = container.querySelector(`[data-index="${campoIndex - 1}"]`);
    const tipoSelect = campoItem.querySelector('.campo-tipo');
    const opcoesDiv = campoItem.querySelector('.campo-opcoes');
    
    tipoSelect.addEventListener('change', function() {
        if (['multipla_escolha', 'checkbox_multiplo'].includes(this.value)) {
            opcoesDiv.style.display = 'block';
        } else {
            opcoesDiv.style.display = 'none';
        }
    });
    
    // Remove campo
    campoItem.querySelector('.btn-remover-campo').addEventListener('click', function() {
        campoItem.remove();
    });
});

document.getElementById('btn-salvar-pesquisa').addEventListener('click', function() {
    const form = document.getElementById('form-criar-pesquisa');
    const formData = new FormData(form);
    formData.append('tipo', 'satisfacao');
    
    // Adiciona timestamp único para evitar duplicação
    const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    formData.append('request_id', requestId);
    
    // Processa campos
    const campos = [];
    document.querySelectorAll('.campo-item').forEach(function(item, index) {
        const campo = {
            tipo: item.querySelector('[name*="[tipo]"]').value,
            label: item.querySelector('[name*="[label]"]').value,
            descricao: item.querySelector('[name*="[descricao]"]').value || null,
            obrigatorio: item.querySelector('[name*="[obrigatorio]"]').checked ? 1 : 0,
            ordem: parseInt(item.querySelector('[name*="[ordem]"]').value) || index
        };
        
        const opcoesText = item.querySelector('[name*="[opcoes_text]"]').value;
        if (opcoesText && ['multipla_escolha', 'checkbox_multiplo'].includes(campo.tipo)) {
            campo.opcoes = opcoesText.split('\n').filter(o => o.trim());
        }
        
        if (campo.label) {
            campos.push(campo);
        }
    });
    
    formData.append('campos', JSON.stringify(campos));
    
    fetch('<?= get_base_url() ?>/api/pesquisas/criar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pesquisa criada com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao criar pesquisa');
        console.error(error);
    });
});

// Publicar pesquisa
document.querySelectorAll('.btn-publicar').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const pesquisaId = this.dataset.id;
        const formData = new FormData();
        formData.append('pesquisa_id', pesquisaId);
        formData.append('tipo', 'satisfacao');
        
        fetch('<?= get_base_url() ?>/api/pesquisas/publicar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pesquisa publicada com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

