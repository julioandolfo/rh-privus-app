<?php
/**
 * Página de Gestão de Pesquisas Rápidas
 */

$page_title = 'Pesquisas Rápidas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('pesquisas_rapidas.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Lista pesquisas rápidas
$stmt = $pdo->prepare("
    SELECT pr.*, u.nome as criado_por_nome,
           (SELECT COUNT(*) FROM pesquisas_rapidas_envios WHERE pesquisa_id = pr.id) as total_envios,
           (SELECT COUNT(*) FROM pesquisas_rapidas_envios WHERE pesquisa_id = pr.id AND respondida = 1) as total_respostas
    FROM pesquisas_rapidas pr
    LEFT JOIN usuarios u ON pr.created_by = u.id
    ORDER BY pr.created_at DESC
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
                            <h2>Pesquisas Rápidas</h2>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-criar-pesquisa">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Pesquisa Rápida
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Título</th>
                                        <th>Pergunta</th>
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
                                        <td><?= htmlspecialchars(substr($pesquisa['pergunta'], 0, 50)) ?><?= strlen($pesquisa['pergunta']) > 50 ? '...' : '' ?></td>
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
                                            <?= date('d/m/Y H:i', strtotime($pesquisa['data_inicio'])) ?>
                                            <?php if ($pesquisa['data_fim']): ?>
                                            até <?= date('d/m/Y H:i', strtotime($pesquisa['data_fim'])) ?>
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
                                            <a href="pesquisa_view.php?id=<?= $pesquisa['id'] ?>&tipo=rapida" class="btn btn-sm btn-primary">
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

<!-- Modal Criar Pesquisa Rápida -->
<div class="modal fade" id="modal-criar-pesquisa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Pesquisa Rápida</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-criar-pesquisa">
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Pergunta *</label>
                        <textarea name="pergunta" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Resposta *</label>
                        <select name="tipo_resposta" class="form-select" id="tipo-resposta" required>
                            <option value="sim_nao">Sim/Não</option>
                            <option value="multipla_escolha">Múltipla Escolha</option>
                            <option value="texto_curto">Texto Curto</option>
                            <option value="escala_1_5">Escala 1-5</option>
                            <option value="escala_1_10">Escala 1-10</option>
                            <option value="numero">Número</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="opcoes-container" style="display:none;">
                        <label class="form-label">Opções (uma por linha) *</label>
                        <textarea name="opcoes_text" class="form-control" rows="4" id="opcoes-text"></textarea>
                        <small class="text-muted">Digite uma opção por linha</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data/Hora Início *</label>
                            <input type="datetime-local" name="data_inicio_datetime" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data/Hora Fim</label>
                            <input type="datetime-local" name="data_fim_datetime" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Público Alvo</label>
                        <select name="publico_alvo" class="form-select">
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
// Mostra/esconde opções baseado no tipo
document.getElementById('tipo-resposta').addEventListener('change', function() {
    const opcoesContainer = document.getElementById('opcoes-container');
    const opcoesText = document.getElementById('opcoes-text');
    
    if (this.value === 'multipla_escolha') {
        opcoesContainer.style.display = 'block';
        opcoesText.required = true;
    } else {
        opcoesContainer.style.display = 'none';
        opcoesText.required = false;
    }
});

// Salva pesquisa
document.getElementById('btn-salvar-pesquisa').addEventListener('click', function() {
    const form = document.getElementById('form-criar-pesquisa');
    const formData = new FormData(form);
    formData.append('tipo', 'rapida');
    
    // Adiciona timestamp único para evitar duplicação
    const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    formData.append('request_id', requestId);
    
    // Processa opções se for múltipla escolha
    const tipoResposta = document.getElementById('tipo-resposta').value;
    if (tipoResposta === 'multipla_escolha') {
        const opcoesText = document.getElementById('opcoes-text').value;
        const opcoes = opcoesText.split('\n').filter(o => o.trim());
        formData.append('opcoes', JSON.stringify(opcoes));
    }
    
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
        formData.append('tipo', 'rapida');
        
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

