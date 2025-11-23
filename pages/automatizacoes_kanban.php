<?php
/**
 * Configuração de Automações do Kanban
 */

$page_title = 'Automações do Kanban';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('automatizacoes_kanban.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca colunas
$stmt = $pdo->query("SELECT * FROM kanban_colunas WHERE ativo = 1 ORDER BY ordem ASC");
$colunas = $stmt->fetchAll();

// Busca etapas
$stmt = $pdo->query("SELECT * FROM processo_seletivo_etapas WHERE vaga_id IS NULL AND ativo = 1 ORDER BY ordem ASC");
$etapas = $stmt->fetchAll();

// Busca automações
$stmt = $pdo->query("
    SELECT a.*, 
           c.nome as coluna_nome,
           e.nome as etapa_nome
    FROM kanban_automatizacoes a
    LEFT JOIN kanban_colunas c ON a.coluna_id = c.id
    LEFT JOIN processo_seletivo_etapas e ON a.etapa_id = e.id
    ORDER BY a.created_at DESC
");
$automatizacoes = $stmt->fetchAll();

$tipos_automacao = [
    'email_candidato' => 'Email ao Candidato',
    'email_recrutador' => 'Email ao Recrutador',
    'email_gestor' => 'Email ao Gestor',
    'push_candidato' => 'Push ao Candidato',
    'push_recrutador' => 'Push ao Recrutador',
    'notificacao_sistema' => 'Notificação no Sistema',
    'criar_tarefa' => 'Criar Tarefa',
    'criar_colaborador' => 'Criar Colaborador',
    'enviar_rejeicao' => 'Enviar Email de Rejeição',
    'enviar_aprovacao' => 'Enviar Email de Aprovação',
    'agendar_entrevista' => 'Agendar Entrevista',
    'calcular_nota' => 'Calcular Nota',
    'mover_automaticamente' => 'Mover Automaticamente',
    'adicionar_banco_talentos' => 'Adicionar ao Banco de Talentos',
    'fechar_vaga' => 'Fechar Vaga',
    'lembrete' => 'Lembrete',
    'relatorio' => 'Relatório'
];
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Automações do Kanban</h2>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaAutomacao">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Automação
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Aplicar em</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($automatizacoes as $automacao): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($automacao['nome']) ?></strong></td>
                                        <td><?= htmlspecialchars($tipos_automacao[$automacao['tipo']] ?? $automacao['tipo']) ?></td>
                                        <td>
                                            <?php if ($automacao['coluna_nome']): ?>
                                            <span class="badge badge-light-primary">Coluna: <?= htmlspecialchars($automacao['coluna_nome']) ?></span>
                                            <?php elseif ($automacao['etapa_nome']): ?>
                                            <span class="badge badge-light-info">Etapa: <?= htmlspecialchars($automacao['etapa_nome']) ?></span>
                                            <?php else: ?>
                                            <span class="badge badge-light-secondary">Global</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($automacao['ativo']): ?>
                                            <span class="badge badge-light-success">Ativa</span>
                                            <?php else: ?>
                                            <span class="badge badge-light-danger">Inativa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-light-warning btn-editar-automacao" 
                                                    data-automacao-id="<?= $automacao['id'] ?>">
                                                Editar
                                            </button>
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

<!-- Modal Nova/Editar Automação -->
<div class="modal fade" id="modalNovaAutomacao" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Automação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAutomacao">
                <div class="modal-body">
                    <input type="hidden" name="automacao_id" id="automacao_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Automação *</label>
                        <select name="tipo" class="form-select" required id="tipoAutomacao">
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos_automacao as $codigo => $nome): ?>
                            <option value="<?= $codigo ?>"><?= htmlspecialchars($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Aplicar em Coluna</label>
                            <select name="coluna_id" class="form-select">
                                <option value="">Nenhuma (Global)</option>
                                <?php foreach ($colunas as $coluna): ?>
                                <option value="<?= $coluna['id'] ?>"><?= htmlspecialchars($coluna['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Aplicar em Etapa</label>
                            <select name="etapa_id" class="form-select">
                                <option value="">Nenhuma</option>
                                <?php foreach ($etapas as $etapa): ?>
                                <option value="<?= $etapa['id'] ?>"><?= htmlspecialchars($etapa['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Condições (JSON)</label>
                        <textarea name="condicoes" class="form-control" rows="3" placeholder='{"ao_entrar_coluna": true}'></textarea>
                        <small class="form-text text-muted">Ex: {"ao_entrar_coluna": true, "dias_sem_atualizacao": 3}</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Configuração (JSON)</label>
                        <textarea name="configuracao" class="form-control" rows="3" placeholder='{"template": "confirmacao", "assunto": "..."}'></textarea>
                        <small class="form-text text-muted">Configurações específicas da automação</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" checked>
                            <label class="form-check-label" for="ativo">Ativa</label>
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
document.addEventListener('DOMContentLoaded', function() {
    const formAutomacao = document.getElementById('formAutomacao');
    const modalNovaAutomacao = new bootstrap.Modal(document.getElementById('modalNovaAutomacao'));
    const modalTitle = document.querySelector('#modalNovaAutomacao .modal-title');
    
    // Limpa formulário ao fechar modal
    document.getElementById('modalNovaAutomacao').addEventListener('hidden.bs.modal', function() {
        formAutomacao.reset();
        document.getElementById('automacao_id').value = '';
        modalTitle.textContent = 'Nova Automação';
        document.getElementById('ativo').checked = true;
    });
    
    // Botões de editar
    document.querySelectorAll('.btn-editar-automacao').forEach(btn => {
        btn.addEventListener('click', async function() {
            const automacaoId = this.dataset.automacaoId;
            
            try {
                const response = await fetch(`../api/recrutamento/automatizacoes/detalhes.php?id=${automacaoId}`);
                const data = await response.json();
                
                if (!data.success) {
                    alert('Erro: ' + data.message);
                    return;
                }
                
                const automacao = data.automacao;
                
                // Preenche formulário
                document.getElementById('automacao_id').value = automacao.id;
                document.querySelector('input[name="nome"]').value = automacao.nome || '';
                document.querySelector('select[name="tipo"]').value = automacao.tipo || '';
                document.querySelector('select[name="coluna_id"]').value = automacao.coluna_id || '';
                document.querySelector('select[name="etapa_id"]').value = automacao.etapa_id || '';
                
                // Preenche JSONs
                if (automacao.condicoes) {
                    document.querySelector('textarea[name="condicoes"]').value = JSON.stringify(automacao.condicoes, null, 2);
                } else {
                    document.querySelector('textarea[name="condicoes"]').value = '';
                }
                
                if (automacao.configuracao) {
                    document.querySelector('textarea[name="configuracao"]').value = JSON.stringify(automacao.configuracao, null, 2);
                } else {
                    document.querySelector('textarea[name="configuracao"]').value = '';
                }
                
                // Checkbox ativo
                document.getElementById('ativo').checked = automacao.ativo == 1;
                
                // Altera título do modal
                modalTitle.textContent = 'Editar Automação';
                
                // Abre modal
                modalNovaAutomacao.show();
                
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao carregar automação');
            }
        });
    });
    
    // Submit do formulário
    formAutomacao.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // Valida JSONs antes de enviar
        const condicoesText = formData.get('condicoes');
        if (condicoesText) {
            try {
                JSON.parse(condicoesText);
            } catch (e) {
                alert('Erro: JSON de Condições inválido');
                return;
            }
        }
        
        const configuracaoText = formData.get('configuracao');
        if (configuracaoText) {
            try {
                JSON.parse(configuracaoText);
            } catch (e) {
                alert('Erro: JSON de Configuração inválido');
                return;
            }
        }
        
        try {
            const response = await fetch('../api/recrutamento/automatizacoes/salvar.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Automação salva com sucesso!');
                modalNovaAutomacao.hide();
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao salvar automação');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

