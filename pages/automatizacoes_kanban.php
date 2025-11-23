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

// Busca templates de email para automações
$stmt = $pdo->query("SELECT codigo, nome FROM email_templates WHERE ativo = 1 ORDER BY nome ASC");
$templates_email = $stmt->fetchAll();

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
                            <select name="coluna_id" class="form-select" id="coluna_id">
                                <option value="">Nenhuma (Global)</option>
                                <?php foreach ($colunas as $coluna): ?>
                                <option value="<?= $coluna['id'] ?>"><?= htmlspecialchars($coluna['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Aplicar em Etapa</label>
                            <select name="etapa_id" class="form-select" id="etapa_id">
                                <option value="">Nenhuma</option>
                                <?php foreach ($etapas as $etapa): ?>
                                <option value="<?= $etapa['id'] ?>"><?= htmlspecialchars($etapa['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Seção de Condições (Visual) -->
                    <div class="card mb-3" id="secaoCondicoes">
                        <div class="card-header">
                            <h6 class="mb-0">Quando executar esta automação?</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cond_ao_entrar_coluna" value="1">
                                    <label class="form-check-label" for="cond_ao_entrar_coluna">
                                        Ao entrar na coluna/etapa selecionada
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cond_ao_sair_coluna" value="1">
                                    <label class="form-check-label" for="cond_ao_sair_coluna">
                                        Ao sair da coluna/etapa selecionada
                                    </label>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Após quantos dias sem atualização?</label>
                                    <input type="number" class="form-control" id="cond_dias_sem_atualizacao" min="0" placeholder="Deixe vazio para não usar">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Apenas se status for:</label>
                                    <select class="form-select" id="cond_status">
                                        <option value="">Qualquer status</option>
                                        <option value="novo">Novo</option>
                                        <option value="em_analise">Em Análise</option>
                                        <option value="entrevista">Entrevista</option>
                                        <option value="aprovado">Aprovado</option>
                                        <option value="rejeitado">Rejeitado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção de Configuração (Dinâmica baseada no tipo) -->
                    <div class="card mb-3" id="secaoConfiguracao">
                        <div class="card-header">
                            <h6 class="mb-0" id="tituloConfiguracao">Configurações da Automação</h6>
                        </div>
                        <div class="card-body" id="configuracaoCampos">
                            <!-- Campos serão inseridos dinamicamente via JavaScript -->
                            <p class="text-muted">Selecione um tipo de automação para ver as opções de configuração.</p>
                        </div>
                    </div>
                    
                    <!-- Campos JSON ocultos (para compatibilidade) -->
                    <input type="hidden" name="condicoes" id="condicoes_json">
                    <input type="hidden" name="configuracao" id="configuracao_json">
                    
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
    const tipoAutomacao = document.getElementById('tipoAutomacao');
    const configuracaoCampos = document.getElementById('configuracaoCampos');
    const tituloConfiguracao = document.getElementById('tituloConfiguracao');
    
    // Templates de email disponíveis
    const templatesEmail = <?= json_encode(array_map(function($t) { return ['codigo' => $t['codigo'], 'nome' => $t['nome']]; }, $templates_email)) ?>;
    
    // Funções auxiliares
    function atualizarCamposConfiguracao(tipo) {
        let html = '';
        
        if (tipo.startsWith('email_')) {
            tituloConfiguracao.textContent = 'Configurações de Email';
            html = `
                <div class="mb-3">
                    <label class="form-label">Template de Email *</label>
                    <select class="form-select" id="config_template" required>
                        <option value="">Selecione um template...</option>
                        ${templatesEmail.map(t => `<option value="${t.codigo}">${t.nome}</option>`).join('')}
                    </select>
                    <small class="form-text text-muted">Escolha o template que será usado para enviar o email.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Assunto Customizado (Opcional)</label>
                    <input type="text" class="form-control" id="config_assunto" placeholder="Deixe vazio para usar o assunto do template">
                    <small class="form-text text-muted">Se preenchido, substitui o assunto padrão do template.</small>
                </div>
            `;
        } else if (tipo.startsWith('push_')) {
            tituloConfiguracao.textContent = 'Configurações de Notificação Push';
            html = `
                <div class="mb-3">
                    <label class="form-label">Título da Notificação *</label>
                    <input type="text" class="form-control" id="config_titulo" required placeholder="Ex: Nova atualização na sua candidatura">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mensagem *</label>
                    <textarea class="form-control" id="config_mensagem" rows="3" required placeholder="Ex: Sua candidatura foi movida para a etapa de entrevista"></textarea>
                </div>
            `;
        } else if (tipo === 'notificacao_sistema') {
            tituloConfiguracao.textContent = 'Configurações de Notificação no Sistema';
            html = `
                <div class="mb-3">
                    <label class="form-label">Título da Notificação *</label>
                    <input type="text" class="form-control" id="config_titulo" required placeholder="Ex: Candidatura atualizada">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mensagem *</label>
                    <textarea class="form-control" id="config_mensagem" rows="3" required placeholder="Ex: O candidato foi movido para a próxima etapa"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo de Notificação</label>
                    <select class="form-select" id="config_tipo_notificacao">
                        <option value="info">Informativa</option>
                        <option value="success">Sucesso</option>
                        <option value="warning">Aviso</option>
                        <option value="error">Erro</option>
                    </select>
                </div>
            `;
        } else if (tipo === 'mover_automaticamente') {
            tituloConfiguracao.textContent = 'Configurações de Movimentação Automática';
            html = `
                <div class="mb-3">
                    <label class="form-label">Mover para Coluna</label>
                    <select class="form-select" id="config_coluna_destino">
                        <option value="">Selecione...</option>
                        <?php foreach ($colunas as $coluna): ?>
                        <option value="<?= $coluna['id'] ?>"><?= htmlspecialchars($coluna['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Para qual coluna o candidato será movido automaticamente?</small>
                </div>
            `;
        } else if (tipo === 'agendar_entrevista') {
            tituloConfiguracao.textContent = 'Configurações de Agendamento';
            html = `
                <div class="mb-3">
                    <label class="form-label">Tipo de Entrevista</label>
                    <select class="form-select" id="config_tipo_entrevista">
                        <option value="rh">RH</option>
                        <option value="tecnica">Técnica</option>
                        <option value="gestor">Gestor</option>
                        <option value="diretoria">Diretoria</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Dias para Agendar (após entrar na etapa)</label>
                    <input type="number" class="form-control" id="config_dias_agendamento" min="0" value="1" placeholder="1">
                    <small class="form-text text-muted">Quantos dias após entrar na etapa a entrevista deve ser agendada?</small>
                </div>
            `;
        } else if (tipo === 'lembrete') {
            tituloConfiguracao.textContent = 'Configurações de Lembrete';
            html = `
                <div class="mb-3">
                    <label class="form-label">Título do Lembrete *</label>
                    <input type="text" class="form-control" id="config_titulo" required placeholder="Ex: Revisar candidatura">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mensagem *</label>
                    <textarea class="form-control" id="config_mensagem" rows="3" required placeholder="Ex: Esta candidatura está aguardando sua análise"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Enviar para</label>
                    <select class="form-select" id="config_destinatario">
                        <option value="recrutador">Recrutador Responsável</option>
                        <option value="gestor">Gestor da Vaga</option>
                        <option value="ambos">Recrutador e Gestor</option>
                    </select>
                </div>
            `;
        } else {
            tituloConfiguracao.textContent = 'Configurações da Automação';
            html = '<p class="text-muted">Este tipo de automação não requer configurações adicionais.</p>';
        }
        
        configuracaoCampos.innerHTML = html;
    }
    
    function limparCondicoes() {
        document.getElementById('cond_ao_entrar_coluna').checked = false;
        document.getElementById('cond_ao_sair_coluna').checked = false;
        document.getElementById('cond_dias_sem_atualizacao').value = '';
        document.getElementById('cond_status').value = '';
    }
    
    function gerarJSONCondicoes() {
        const condicoes = {};
        
        if (document.getElementById('cond_ao_entrar_coluna').checked) {
            condicoes.ao_entrar_coluna = true;
        }
        if (document.getElementById('cond_ao_sair_coluna').checked) {
            condicoes.ao_sair_coluna = true;
        }
        const dias = document.getElementById('cond_dias_sem_atualizacao').value;
        if (dias) {
            condicoes.dias_sem_atualizacao = parseInt(dias);
        }
        const status = document.getElementById('cond_status').value;
        if (status) {
            condicoes.status = status;
        }
        
        return Object.keys(condicoes).length > 0 ? JSON.stringify(condicoes) : null;
    }
    
    function gerarJSONConfiguracao() {
        const tipo = tipoAutomacao.value;
        const config = {};
        
        if (tipo.startsWith('email_')) {
            const template = document.getElementById('config_template')?.value;
            const assunto = document.getElementById('config_assunto')?.value;
            if (template) config.template = template;
            if (assunto) config.assunto = assunto;
        } else if (tipo.startsWith('push_') || tipo === 'notificacao_sistema' || tipo === 'lembrete') {
            const titulo = document.getElementById('config_titulo')?.value;
            const mensagem = document.getElementById('config_mensagem')?.value;
            if (titulo) config.titulo = titulo;
            if (mensagem) config.mensagem = mensagem;
            if (tipo === 'notificacao_sistema') {
                const tipoNotif = document.getElementById('config_tipo_notificacao')?.value;
                if (tipoNotif) config.tipo = tipoNotif;
            }
            if (tipo === 'lembrete') {
                const destinatario = document.getElementById('config_destinatario')?.value;
                if (destinatario) config.destinatario = destinatario;
            }
        } else if (tipo === 'mover_automaticamente') {
            const colunaDestino = document.getElementById('config_coluna_destino')?.value;
            if (colunaDestino) config.coluna_destino = parseInt(colunaDestino);
        } else if (tipo === 'agendar_entrevista') {
            const tipoEntrevista = document.getElementById('config_tipo_entrevista')?.value;
            const dias = document.getElementById('config_dias_agendamento')?.value;
            if (tipoEntrevista) config.tipo_entrevista = tipoEntrevista;
            if (dias) config.dias_agendamento = parseInt(dias);
        }
        
        return Object.keys(config).length > 0 ? JSON.stringify(config) : null;
    }
    
    function preencherCondicoes(condicoes) {
        limparCondicoes();
        if (!condicoes) return;
        
        if (condicoes.ao_entrar_coluna) {
            document.getElementById('cond_ao_entrar_coluna').checked = true;
        }
        if (condicoes.ao_sair_coluna) {
            document.getElementById('cond_ao_sair_coluna').checked = true;
        }
        if (condicoes.dias_sem_atualizacao) {
            document.getElementById('cond_dias_sem_atualizacao').value = condicoes.dias_sem_atualizacao;
        }
        if (condicoes.status) {
            document.getElementById('cond_status').value = condicoes.status;
        }
    }
    
    function preencherConfiguracao(config, tipo) {
        if (!config) return;
        
        // Aguarda um pouco para os campos serem criados
        setTimeout(() => {
            if (tipo.startsWith('email_')) {
                if (config.template) {
                    const select = document.getElementById('config_template');
                    if (select) select.value = config.template;
                }
                if (config.assunto) {
                    const input = document.getElementById('config_assunto');
                    if (input) input.value = config.assunto;
                }
            } else if (tipo.startsWith('push_') || tipo === 'notificacao_sistema' || tipo === 'lembrete') {
                if (config.titulo) {
                    const input = document.getElementById('config_titulo');
                    if (input) input.value = config.titulo;
                }
                if (config.mensagem) {
                    const textarea = document.getElementById('config_mensagem');
                    if (textarea) textarea.value = config.mensagem;
                }
                if (tipo === 'notificacao_sistema' && config.tipo) {
                    const select = document.getElementById('config_tipo_notificacao');
                    if (select) select.value = config.tipo;
                }
                if (tipo === 'lembrete' && config.destinatario) {
                    const select = document.getElementById('config_destinatario');
                    if (select) select.value = config.destinatario;
                }
            } else if (tipo === 'mover_automaticamente' && config.coluna_destino) {
                const select = document.getElementById('config_coluna_destino');
                if (select) select.value = config.coluna_destino;
            } else if (tipo === 'agendar_entrevista') {
                if (config.tipo_entrevista) {
                    const select = document.getElementById('config_tipo_entrevista');
                    if (select) select.value = config.tipo_entrevista;
                }
                if (config.dias_agendamento) {
                    const input = document.getElementById('config_dias_agendamento');
                    if (input) input.value = config.dias_agendamento;
                }
            }
        }, 100);
    }
    
    // Atualiza campos quando tipo muda
    tipoAutomacao.addEventListener('change', function() {
        atualizarCamposConfiguracao(this.value);
    });
    
    // Limpa formulário ao fechar modal
    document.getElementById('modalNovaAutomacao').addEventListener('hidden.bs.modal', function() {
        formAutomacao.reset();
        document.getElementById('automacao_id').value = '';
        modalTitle.textContent = 'Nova Automação';
        document.getElementById('ativo').checked = true;
        configuracaoCampos.innerHTML = '<p class="text-muted">Selecione um tipo de automação para ver as opções de configuração.</p>';
        limparCondicoes();
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
                tipoAutomacao.value = automacao.tipo || '';
                document.getElementById('coluna_id').value = automacao.coluna_id || '';
                document.getElementById('etapa_id').value = automacao.etapa_id || '';
                
                // Atualiza campos de configuração baseado no tipo
                atualizarCamposConfiguracao(automacao.tipo);
                
                // Preenche condições visuais
                setTimeout(() => {
                    preencherCondicoes(automacao.condicoes);
                    preencherConfiguracao(automacao.configuracao, automacao.tipo);
                }, 200);
                
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
        
        // Gera JSONs a partir dos campos visuais
        const condicoesJSON = gerarJSONCondicoes();
        const configuracaoJSON = gerarJSONConfiguracao();
        
        // Valida campos obrigatórios baseado no tipo
        const tipo = tipoAutomacao.value;
        if (tipo.startsWith('email_')) {
            const template = document.getElementById('config_template')?.value;
            if (!template) {
                alert('Por favor, selecione um template de email.');
                return;
            }
        } else if (tipo.startsWith('push_') || tipo === 'notificacao_sistema' || tipo === 'lembrete') {
            const titulo = document.getElementById('config_titulo')?.value;
            const mensagem = document.getElementById('config_mensagem')?.value;
            if (!titulo || !mensagem) {
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }
        }
        
        const formData = new FormData(this);
        
        // Adiciona JSONs gerados aos dados do formulário
        if (condicoesJSON) {
            formData.set('condicoes', condicoesJSON);
        } else {
            formData.delete('condicoes');
        }
        
        if (configuracaoJSON) {
            formData.set('configuracao', configuracaoJSON);
        } else {
            formData.delete('configuracao');
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

