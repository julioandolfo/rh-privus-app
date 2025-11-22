<?php
/**
 * Página de Gestão de PDIs
 */

$page_title = 'Planos de Desenvolvimento Individual';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('pdis.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Lista PDIs
$where = ["1=1"];
$params = [];

// Se for COLABORADOR, só vê seus próprios PDIs
if ($usuario['role'] === 'COLABORADOR' && $usuario['colaborador_id']) {
    $where[] = "p.colaborador_id = ?";
    $params[] = $usuario['colaborador_id'];
} elseif ($usuario['role'] === 'GESTOR') {
    // Se for GESTOR, só vê PDIs do seu setor
    $stmt_colab = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt_colab->execute([$usuario['id']]);
    $user_data = $stmt_colab->fetch();
    if ($user_data && $user_data['setor_id']) {
        $where[] = "c.setor_id = ?";
        $params[] = $user_data['setor_id'];
    }
}

$sql = "
    SELECT p.*,
           c.nome_completo as colaborador_nome,
           c.foto as colaborador_foto,
           u.nome as criado_por_nome
    FROM pdis p
    INNER JOIN colaboradores c ON p.colaborador_id = c.id
    LEFT JOIN usuarios u ON p.criado_por = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.created_at DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pdis = $stmt->fetchAll();

// Busca colaboradores disponíveis
require_once __DIR__ . '/../includes/select_colaborador.php';
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Planos de Desenvolvimento Individual (PDIs)</h2>
                        </div>
                        <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-criar-pdi">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Novo PDI
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Colaborador</th>
                                        <th>Título</th>
                                        <th>Status</th>
                                        <th>Progresso</th>
                                        <th>Data Início</th>
                                        <th>Criado por</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pdis as $pdi): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($pdi['colaborador_foto']): ?>
                                                <img src="<?= htmlspecialchars($pdi['colaborador_foto']) ?>" class="rounded-circle me-2" width="30" height="30" alt="">
                                                <?php else: ?>
                                                <div class="symbol symbol-circle symbol-30px me-2">
                                                    <div class="symbol-label bg-primary text-white" style="font-size: 12px;">
                                                        <?= strtoupper(substr($pdi['colaborador_nome'], 0, 1)) ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($pdi['colaborador_nome']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($pdi['titulo']) ?></td>
                                        <td>
                                            <?php
                                            $badge_class = [
                                                'rascunho' => 'badge-secondary',
                                                'ativo' => 'badge-success',
                                                'concluido' => 'badge-info',
                                                'cancelado' => 'badge-danger',
                                                'pausado' => 'badge-warning'
                                            ];
                                            $status_class = $badge_class[$pdi['status']] ?? 'badge-secondary';
                                            ?>
                                            <span class="badge <?= $status_class ?>"><?= ucfirst($pdi['status']) ?></span>
                                        </td>
                                        <td>
                                            <div class="progress" style="width: 100px; height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?= $pdi['progresso_percentual'] ?>%;" 
                                                     aria-valuenow="<?= $pdi['progresso_percentual'] ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?= $pdi['progresso_percentual'] ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($pdi['data_inicio'])) ?></td>
                                        <td><?= htmlspecialchars($pdi['criado_por_nome']) ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="pdi_view.php?id=<?= $pdi['id'] ?>" class="btn btn-sm btn-primary">
                                                    Ver
                                                </a>
                                                <?php if ($usuario['role'] !== 'COLABORADOR' && ($usuario['id'] == $pdi['criado_por'] || $usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH')): ?>
                                                <button type="button" class="btn btn-sm btn-light-warning btn-editar-pdi" data-pdi-id="<?= $pdi['id'] ?>">
                                                    <i class="ki-duotone ki-pencil fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-light-danger btn-excluir-pdi" data-pdi-id="<?= $pdi['id'] ?>" data-pdi-titulo="<?= htmlspecialchars($pdi['titulo']) ?>">
                                                    <i class="ki-duotone ki-trash fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                    </i>
                                                </button>
                                                <?php endif; ?>
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

<!-- Modal Criar PDI -->
<div class="modal fade" id="modal-criar-pdi" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo PDI</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-criar-pdi">
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                        <?= render_select_colaborador('colaborador_id', 'colaborador_id', null, $colaboradores, true) ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Objetivo Geral</label>
                        <textarea name="objetivo_geral" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Início *</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Fim Prevista</label>
                            <input type="date" name="data_fim_prevista" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-6 mb-2">
                            Status *
                            <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Rascunho: salva sem notificar o colaborador. Ativo: ativa o PDI e notifica o colaborador.">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </label>
                        <select name="status" class="form-select form-control-solid" required>
                            <option value="rascunho">📝 Rascunho (Salvar sem notificar)</option>
                            <option value="ativo" selected>✅ Ativo (Ativar e notificar colaborador)</option>
                        </select>
                        <small class="text-muted">Escolha "Ativo" para ativar o PDI e notificar o colaborador imediatamente</small>
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
                    </div>
                    
                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Objetivos do PDI</h5>
                                <p class="text-muted mb-0 small">Defina os objetivos que o colaborador deve alcançar durante o período do PDI</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" id="btn-adicionar-objetivo">
                                <i class="ki-duotone ki-plus fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Objetivo
                            </button>
                        </div>
                        <div id="objetivos-container">
                            <!-- Objetivos serão adicionados dinamicamente -->
                        </div>
                        <div class="alert alert-info d-flex align-items-center p-3" id="objetivos-empty-message">
                            <i class="ki-duotone ki-information-5 fs-2hx text-info me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div>
                                <strong>Nenhum objetivo adicionado ainda.</strong><br>
                                <small>Clique em "Adicionar Objetivo" para começar a definir os objetivos deste PDI.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Ações do PDI</h5>
                                <p class="text-muted mb-0 small">Especifique as ações concretas que serão realizadas para alcançar os objetivos</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" id="btn-adicionar-acao">
                                <i class="ki-duotone ki-plus fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Ação
                            </button>
                        </div>
                        <div id="acoes-container">
                            <!-- Ações serão adicionadas dinamicamente -->
                        </div>
                        <div class="alert alert-info d-flex align-items-center p-3 d-none" id="acoes-empty-message">
                            <i class="ki-duotone ki-information-5 fs-2hx text-info me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div>
                                <strong>Nenhuma ação adicionada ainda.</strong><br>
                                <small>Clique em "Adicionar Ação" para definir as ações que serão executadas.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-salvar-pdi">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
let objetivoIndex = 0;
let acaoIndex = 0;

// Variáveis para debounce e controle de estado
let atualizarMensagemObjetivosTimeout = null;
let atualizarMensagemAcoesTimeout = null;
let lastObjetivosCount = 0;
let lastAcoesCount = 0;

// Função para atualizar mensagem vazia de objetivos
function atualizarMensagemObjetivos(forcar = false) {
    const container = document.getElementById('objetivos-container');
    const emptyMsg = document.getElementById('objetivos-empty-message');
    const modal = document.getElementById('modal-criar-pdi');
    
    if (!container || !emptyMsg) return;
    
    // Só atualiza se o modal estiver visível
    // Verifica tanto 'show' quanto se o modal está sendo exibido (display block)
    if (modal) {
        const isVisible = modal.classList.contains('show') || 
                         window.getComputedStyle(modal).display !== 'none';
        if (!isVisible) {
            return;
        }
    }
    
    // Conta elementos primeiro
    const objetivos = container.querySelectorAll('.objetivo-item');
    const childrenCards = Array.from(container.children).filter(node => 
        node.nodeType === 1 && 
        node.classList.contains('card') && 
        !node.classList.contains('alert')
    );
    const totalObjetivos = objetivos.length > 0 ? objetivos.length : childrenCards.length;
    
    // Se o total mudou de >0 para 0, usa debounce mais longo
    // Se mudou de 0 para >0, atualiza imediatamente
    if (!forcar) {
        if (atualizarMensagemObjetivosTimeout) {
            clearTimeout(atualizarMensagemObjetivosTimeout);
        }
        
        // Se tinha objetivos e agora está vazio, aguarda mais tempo (pode ser temporário)
        const delay = (lastObjetivosCount > 0 && totalObjetivos === 0) ? 300 : 100;
        
        atualizarMensagemObjetivosTimeout = setTimeout(function() {
            atualizarMensagemObjetivos(true);
        }, delay);
        return;
    }
    
    const wasHidden = emptyMsg.classList.contains('d-none');
    
    // Log para debug
    const logData = {
        timestamp: new Date().toISOString(),
        objetivosByClass: objetivos.length,
        childrenCards: childrenCards.length,
        totalObjetivos: totalObjetivos,
        containerChildren: container.children.length,
        emptyMsgHiddenBefore: wasHidden,
        modalVisible: modal ? modal.classList.contains('show') : false
    };
    
    // Define o display baseado no total de objetivos
    const shouldShowEmpty = totalObjetivos === 0;
    
    // Só mostra a mensagem se realmente não houver objetivos e não for uma transição
    if (totalObjetivos > 0) {
        emptyMsg.classList.add('d-none');
        lastObjetivosCount = totalObjetivos;
    } else if (totalObjetivos === 0 && lastObjetivosCount === 0) {
        // Só mostra se já estava vazio antes (não é transição)
        emptyMsg.classList.remove('d-none');
    } else {
        // Está em transição, mantém oculto
        emptyMsg.classList.add('d-none');
        lastObjetivosCount = totalObjetivos;
    }
    
    const isHiddenNow = emptyMsg.classList.contains('d-none');
    
    // Log após atualizar
    logData.emptyMsgHiddenAfter = isHiddenNow;
    logData.shouldShowEmpty = shouldShowEmpty;
    logData.lastCount = lastObjetivosCount;
    
    console.log('[PDI] atualizarMensagemObjetivos:', logData);
    
    // Envia log para arquivo via AJAX
    fetch('<?= get_base_url() ?>/api/pdis/log.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            tipo: 'atualizarMensagemObjetivos',
            dados: logData
        })
    }).catch(err => console.error('Erro ao salvar log:', err));
}

// Função para atualizar mensagem vazia de ações
function atualizarMensagemAcoes(forcar = false) {
    const container = document.getElementById('acoes-container');
    const emptyMsg = document.getElementById('acoes-empty-message');
    const modal = document.getElementById('modal-criar-pdi');
    
    if (!container || !emptyMsg) return;
    
    // Só atualiza se o modal estiver visível
    // Verifica tanto 'show' quanto se o modal está sendo exibido (display block)
    if (modal) {
        const isVisible = modal.classList.contains('show') || 
                         window.getComputedStyle(modal).display !== 'none';
        if (!isVisible) {
            return;
        }
    }
    
    // Conta elementos primeiro
    const acoes = container.querySelectorAll('.acao-item');
    const childrenCards = Array.from(container.children).filter(node => 
        node.nodeType === 1 && 
        node.classList.contains('card') && 
        !node.classList.contains('alert')
    );
    const totalAcoes = acoes.length > 0 ? acoes.length : childrenCards.length;
    
    // Se o total mudou de >0 para 0, usa debounce mais longo
    // Se mudou de 0 para >0, atualiza imediatamente
    if (!forcar) {
        if (atualizarMensagemAcoesTimeout) {
            clearTimeout(atualizarMensagemAcoesTimeout);
        }
        
        const delay = (lastAcoesCount > 0 && totalAcoes === 0) ? 300 : 100;
        
        atualizarMensagemAcoesTimeout = setTimeout(function() {
            atualizarMensagemAcoes(true);
        }, delay);
        return;
    }
    
    const wasHidden = emptyMsg.classList.contains('d-none');
    
    // Log para debug
    const logData = {
        timestamp: new Date().toISOString(),
        acoesByClass: acoes.length,
        childrenCards: childrenCards.length,
        totalAcoes: totalAcoes,
        containerChildren: container.children.length,
        emptyMsgHiddenBefore: wasHidden,
        modalVisible: modal ? modal.classList.contains('show') : false
    };
    
    // Define o display baseado no total de ações
    const shouldShowEmpty = totalAcoes === 0;
    
    if (totalAcoes > 0) {
        emptyMsg.classList.add('d-none');
        lastAcoesCount = totalAcoes;
    } else if (totalAcoes === 0 && lastAcoesCount === 0) {
        emptyMsg.classList.remove('d-none');
    } else {
        emptyMsg.classList.add('d-none');
        lastAcoesCount = totalAcoes;
    }
    
    const isHiddenNow = emptyMsg.classList.contains('d-none');
    
    // Log após atualizar
    logData.emptyMsgHiddenAfter = isHiddenNow;
    logData.shouldShowEmpty = shouldShowEmpty;
    logData.lastCount = lastAcoesCount;
    
    console.log('[PDI] atualizarMensagemAcoes:', logData);
    
    // Envia log para arquivo via AJAX
    fetch('<?= get_base_url() ?>/api/pdis/log.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            tipo: 'atualizarMensagemAcoes',
            dados: logData
        })
    }).catch(err => console.error('Erro ao salvar log:', err));
}

// Adicionar objetivo
document.getElementById('btn-adicionar-objetivo').addEventListener('click', function() {
    const container = document.getElementById('objetivos-container');
    const objetivoHtml = `
        <div class="card mb-3 objetivo-item" data-index="${objetivoIndex}">
            <div class="card-header bg-light">
                <h6 class="mb-0">Objetivo ${objetivoIndex + 1}</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required fw-semibold fs-6 mb-2">
                        Título do Objetivo *
                        <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Descreva de forma clara e objetiva o que se pretende alcançar">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </label>
                    <input type="text" name="objetivos[${objetivoIndex}][objetivo]" class="form-control form-control-solid" placeholder="Ex: Melhorar habilidades de comunicação" required>
                    <small class="text-muted">Seja específico e mensurável</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-6 mb-2">
                        Descrição Detalhada
                        <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Explique como o objetivo será alcançado e quais são os critérios de sucesso">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </label>
                    <textarea name="objetivos[${objetivoIndex}][descricao]" class="form-control form-control-solid" rows="3" placeholder="Descreva os passos, recursos necessários e como será medido o progresso..."></textarea>
                    <small class="text-muted">Detalhe o caminho para alcançar este objetivo</small>
                </div>
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-semibold fs-6 mb-2">
                            Prazo para Conclusão
                            <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Data limite para alcançar este objetivo">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </label>
                        <input type="date" name="objetivos[${objetivoIndex}][prazo]" class="form-control form-control-solid" min="<?= date('Y-m-d') ?>">
                        <small class="text-muted">Data esperada de conclusão</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold fs-6 mb-2">
                            Ordem de Prioridade
                            <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Número que define a ordem de exibição (menor número = maior prioridade)">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </label>
                        <input type="number" name="objetivos[${objetivoIndex}][ordem]" class="form-control form-control-solid" value="${objetivoIndex + 1}" min="1" placeholder="1">
                        <small class="text-muted">Menor = maior prioridade</small>
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-light-danger w-100 btn-remover-objetivo">
                            <i class="ki-duotone ki-trash fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Remover Objetivo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', objetivoHtml);
    
    const item = container.querySelector(`[data-index="${objetivoIndex}"]`);
    if (item) {
        item.querySelector('.btn-remover-objetivo').addEventListener('click', function() {
            if (confirm('Tem certeza que deseja remover este objetivo?')) {
                item.remove();
                atualizarMensagemObjetivos();
            }
        });
        
        // Inicializa tooltips
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = item.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }
    
    objetivoIndex++;
    // Atualiza mensagem após inserir no DOM
    setTimeout(function() {
        atualizarMensagemObjetivos();
    }, 10);
});

// Adicionar ação
document.getElementById('btn-adicionar-acao').addEventListener('click', function() {
    const container = document.getElementById('acoes-container');
    const acaoHtml = `
        <div class="card mb-3 acao-item" data-index="${acaoIndex}">
            <div class="card-header bg-light">
                <h6 class="mb-0">Ação ${acaoIndex + 1}</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required fw-semibold fs-6 mb-2">
                        Título da Ação *
                        <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Descreva a ação específica que será realizada para alcançar o objetivo">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </label>
                    <input type="text" name="acoes[${acaoIndex}][acao]" class="form-control form-control-solid" placeholder="Ex: Participar de curso de comunicação" required>
                    <small class="text-muted">Ação concreta e executável</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-6 mb-2">
                        Descrição da Ação
                        <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Detalhe como a ação será executada, recursos necessários e responsáveis">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </label>
                    <textarea name="acoes[${acaoIndex}][descricao]" class="form-control form-control-solid" rows="3" placeholder="Descreva os detalhes da execução, materiais necessários, participantes envolvidos..."></textarea>
                    <small class="text-muted">Informe os detalhes de execução</small>
                </div>
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-semibold fs-6 mb-2">
                            Prazo para Execução
                            <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Data limite para executar esta ação">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </label>
                        <input type="date" name="acoes[${acaoIndex}][prazo]" class="form-control form-control-solid" min="<?= date('Y-m-d') ?>">
                        <small class="text-muted">Data esperada de execução</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold fs-6 mb-2">
                            Ordem de Execução
                            <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Número que define a ordem de execução (menor número = executar primeiro)">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </label>
                        <input type="number" name="acoes[${acaoIndex}][ordem]" class="form-control form-control-solid" value="${acaoIndex + 1}" min="1" placeholder="1">
                        <small class="text-muted">Menor = executar primeiro</small>
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-light-danger w-100 btn-remover-acao">
                            <i class="ki-duotone ki-trash fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Remover Ação
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', acaoHtml);
    
    const item = container.querySelector(`[data-index="${acaoIndex}"]`);
    if (item) {
        item.querySelector('.btn-remover-acao').addEventListener('click', function() {
            if (confirm('Tem certeza que deseja remover esta ação?')) {
                item.remove();
                atualizarMensagemAcoes();
            }
        });
        
        // Inicializa tooltips
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = item.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }
    
    acaoIndex++;
    // Atualiza mensagem após inserir no DOM
    setTimeout(function() {
        atualizarMensagemAcoes();
    }, 10);
});

// Função para exibir alerta bonito
function showAlert(tipo, titulo, mensagem) {
    const alertClass = tipo === 'success' ? 'alert-success' : tipo === 'error' ? 'alert-danger' : tipo === 'warning' ? 'alert-warning' : 'alert-info';
    const icon = tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'cross-circle' : tipo === 'warning' ? 'information-5' : 'information';
    
    const alertHtml = `
        <div class="alert alert-dismissible ${alertClass} d-flex align-items-center p-5 mb-10" role="alert" id="custom-alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <i class="ki-duotone ki-${icon} fs-2hx text-${tipo === 'success' ? 'success' : tipo === 'error' ? 'danger' : tipo === 'warning' ? 'warning' : 'info'} me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                ${tipo === 'warning' || tipo === 'info' ? '<span class="path3"></span>' : ''}
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-${tipo === 'success' ? 'success' : tipo === 'error' ? 'danger' : tipo === 'warning' ? 'warning' : 'info'}">${titulo}</h4>
                <span>${mensagem}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Remove alerta anterior se existir
    const existingAlert = document.getElementById('custom-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Adiciona novo alerta
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Remove automaticamente após 5 segundos
    setTimeout(function() {
        const alert = document.getElementById('custom-alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Salvar PDI (criar ou editar)
document.getElementById('btn-salvar-pdi').addEventListener('click', function() {
    const form = document.getElementById('form-criar-pdi');
    const btnSalvar = document.getElementById('btn-salvar-pdi');
    const pdiId = form.querySelector('[name="pdi_id"]')?.value;
    const isEdit = !!pdiId;
    
    // Validação no frontend
    const colaboradorId = form.querySelector('[name="colaborador_id"]').value;
    const titulo = form.querySelector('[name="titulo"]').value.trim();
    const dataInicio = form.querySelector('[name="data_inicio"]').value;
    const dataFimPrevista = form.querySelector('[name="data_fim_prevista"]').value;
    
    if (!colaboradorId || colaboradorId === '') {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione um colaborador.');
        return;
    }
    
    if (!titulo || titulo.length === 0) {
        showAlert('error', 'Erro de Validação', 'Por favor, preencha o título do PDI.');
        form.querySelector('[name="titulo"]').focus();
        return;
    }
    
    if (!dataInicio) {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione a data de início.');
        return;
    }
    
    // Valida data fim prevista se preenchida
    if (dataFimPrevista && dataFimPrevista < dataInicio) {
        showAlert('error', 'Erro de Validação', 'A data fim prevista deve ser posterior à data de início.');
        return;
    }
    
    const formData = new FormData(form);
    
    // Remove data_fim_prevista se estiver vazia
    if (!dataFimPrevista || dataFimPrevista === '') {
        formData.delete('data_fim_prevista');
    }
    
    // Processa objetivos
    const objetivos = [];
    document.querySelectorAll('.objetivo-item').forEach(function(item, index) {
        const objetivo = {
            objetivo: item.querySelector('[name*="[objetivo]"]').value,
            descricao: item.querySelector('[name*="[descricao]"]').value || null,
            prazo: item.querySelector('[name*="[prazo]"]').value || null,
            ordem: parseInt(item.querySelector('[name*="[ordem]"]').value) || index
        };
        if (objetivo.objetivo) {
            objetivos.push(objetivo);
        }
    });
    
    // Processa ações
    const acoes = [];
    document.querySelectorAll('.acao-item').forEach(function(item, index) {
        const acao = {
            acao: item.querySelector('[name*="[acao]"]').value,
            descricao: item.querySelector('[name*="[descricao]"]').value || null,
            prazo: item.querySelector('[name*="[prazo]"]').value || null,
            ordem: parseInt(item.querySelector('[name*="[ordem]"]').value) || index
        };
        if (acao.acao) {
            acoes.push(acao);
        }
    });
    
    formData.append('objetivos', JSON.stringify(objetivos));
    formData.append('acoes', JSON.stringify(acoes));
    
    // Adiciona timestamp único para evitar duplicação
    const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    formData.append('request_id', requestId);
    
    // Desabilita botão durante envio
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + (isEdit ? 'Atualizando...' : 'Salvando...');
    
    const apiUrl = isEdit ? '<?= get_base_url() ?>/api/pdis/editar.php' : '<?= get_base_url() ?>/api/pdis/criar.php';
    
    fetch(apiUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Sucesso!', isEdit ? 'PDI atualizado com sucesso!' : 'PDI criado com sucesso!');
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', 'Erro', data.message || (isEdit ? 'Erro ao atualizar PDI' : 'Erro ao criar PDI'));
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = isEdit ? 'Atualizar' : 'Salvar';
        }
    })
    .catch(error => {
        showAlert('error', 'Erro', (isEdit ? 'Erro ao atualizar PDI' : 'Erro ao criar PDI') + '. Tente novamente.');
        console.error(error);
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = isEdit ? 'Atualizar' : 'Salvar';
    });
});
</script>

<!--begin::Select2 CSS-->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 44px !important;
        padding: 0.75rem 1rem !important;
        display: flex !important;
        align-items: center !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 44px !important;
        padding-left: 0 !important;
    }
</style>
<!--end::Select2 CSS-->

<script>
// Inicializa Select2 no modal
document.getElementById('modal-criar-pdi')?.addEventListener('shown.bs.modal', function() {
    // Atualiza mensagens vazias quando o modal é aberto
    atualizarMensagemObjetivos();
    atualizarMensagemAcoes();
    
    setTimeout(function() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;
        
        const $select = jQuery('#colaborador_id');
        if ($select.length && !$select.hasClass('select2-hidden-accessible')) {
            $select.select2({
                placeholder: 'Selecione um colaborador...',
                allowClear: true,
                width: '100%',
                dropdownParent: jQuery('#modal-criar-pdi'),
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    const $option = jQuery(data.element);
                    const foto = $option.attr('data-foto') || null;
                    const nome = $option.attr('data-nome') || data.text || '';
                    let html = '<span style="display: flex; align-items: center;">';
                    if (foto) {
                        html += '<img src="' + foto + '" class="rounded-circle me-2" width="24" height="24" style="object-fit: cover;" onerror="this.src=\'../assets/media/avatars/blank.png\'" />';
                    } else {
                        const inicial = nome.charAt(0).toUpperCase();
                        html += '<span class="symbol symbol-circle symbol-24px me-2"><span class="symbol-label fs-7 fw-semibold bg-primary text-white">' + inicial + '</span></span>';
                    }
                    html += '<span>' + nome + '</span></span>';
                    return jQuery(html);
                },
                templateSelection: function(data) {
                    if (!data.id) return data.text;
                    const $option = jQuery(data.element);
                    const foto = $option.attr('data-foto') || null;
                    const nome = $option.attr('data-nome') || data.text || '';
                    let html = '<span style="display: flex; align-items: center;">';
                    if (foto) {
                        html += '<img src="' + foto + '" class="rounded-circle me-2" width="24" height="24" style="object-fit: cover;" onerror="this.src=\'../assets/media/avatars/blank.png\'" />';
                    } else {
                        const inicial = nome.charAt(0).toUpperCase();
                        html += '<span class="symbol symbol-circle symbol-24px me-2"><span class="symbol-label fs-7 fw-semibold bg-primary text-white">' + inicial + '</span></span>';
                    }
                    html += '<span>' + nome + '</span></span>';
                    return jQuery(html);
                }
            });
        }
    }, 350);
});

// Função auxiliar para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Função para editar PDI
function editarPDI(pdiData) {
    const form = document.getElementById('form-criar-pdi');
    const modal = new bootstrap.Modal(document.getElementById('modal-criar-pdi'));
    
    if (!pdiData || !pdiData.pdi) {
        showAlert('error', 'Erro', 'Dados do PDI não encontrados');
        return;
    }
    
    const pdi = pdiData.pdi;
    
    // Preenche formulário
    form.querySelector('[name="colaborador_id"]').value = pdi.colaborador_id || '';
    form.querySelector('[name="titulo"]').value = pdi.titulo || '';
    form.querySelector('[name="descricao"]').value = pdi.descricao || '';
    form.querySelector('[name="objetivo_geral"]').value = pdi.objetivo_geral || '';
    form.querySelector('[name="data_inicio"]').value = pdi.data_inicio || '';
    form.querySelector('[name="data_fim_prevista"]').value = pdi.data_fim_prevista || '';
    form.querySelector('[name="status"]').value = pdi.status || 'rascunho';
    
    // Adiciona ID do PDI para edição
    let inputId = form.querySelector('[name="pdi_id"]');
    if (!inputId) {
        inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'pdi_id';
        form.appendChild(inputId);
    }
    inputId.value = pdi.id;
    
    // Limpa objetivos e ações existentes
    const objetivosContainer = document.getElementById('objetivos-container');
    const acoesContainer = document.getElementById('acoes-container');
    
    if (objetivosContainer) objetivosContainer.innerHTML = '';
    if (acoesContainer) acoesContainer.innerHTML = '';
    
    objetivoIndex = 0;
    acaoIndex = 0;
    
    // Atualiza mensagens vazias imediatamente após limpar
    atualizarMensagemObjetivos();
    atualizarMensagemAcoes();
    
    // Carrega objetivos dos dados já recebidos
    if (pdiData.objetivos && pdiData.objetivos.length > 0) {
        pdiData.objetivos.forEach(function(obj) {
            const container = document.getElementById('objetivos-container');
            const objetivoHtml = `
                <div class="card mb-3 objetivo-item" data-index="${objetivoIndex}">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Objetivo ${objetivoIndex + 1}</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required fw-semibold fs-6 mb-2">
                                Título do Objetivo *
                                <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Descreva de forma clara e objetiva o que se pretende alcançar">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </label>
                            <input type="text" name="objetivos[${objetivoIndex}][objetivo]" class="form-control form-control-solid" placeholder="Ex: Melhorar habilidades de comunicação" value="${escapeHtml(obj.objetivo || '')}" required>
                            <small class="text-muted">Seja específico e mensurável</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-6 mb-2">
                                Descrição Detalhada
                                <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Explique como o objetivo será alcançado e quais são os critérios de sucesso">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </label>
                            <textarea name="objetivos[${objetivoIndex}][descricao]" class="form-control form-control-solid" rows="3" placeholder="Descreva os passos, recursos necessários e como será medido o progresso...">${escapeHtml(obj.descricao || '')}</textarea>
                            <small class="text-muted">Detalhe o caminho para alcançar este objetivo</small>
                        </div>
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label class="form-label fw-semibold fs-6 mb-2">
                                    Prazo para Conclusão
                                    <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Data limite para alcançar este objetivo">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </label>
                                <input type="date" name="objetivos[${objetivoIndex}][prazo]" class="form-control form-control-solid" min="<?= date('Y-m-d') ?>" value="${obj.prazo || ''}">
                                <small class="text-muted">Data esperada de conclusão</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold fs-6 mb-2">
                                    Ordem de Prioridade
                                    <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Número que define a ordem de exibição (menor número = maior prioridade)">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </label>
                                <input type="number" name="objetivos[${objetivoIndex}][ordem]" class="form-control form-control-solid" value="${obj.ordem || objetivoIndex + 1}" min="1" placeholder="1">
                                <small class="text-muted">Menor = maior prioridade</small>
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-light-danger w-100 btn-remover-objetivo">
                                    <i class="ki-duotone ki-trash fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Remover Objetivo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', objetivoHtml);
            
            const item = container.querySelector(`[data-index="${objetivoIndex}"]`);
            if (item) {
                item.querySelector('.btn-remover-objetivo').addEventListener('click', function() {
                    if (confirm('Tem certeza que deseja remover este objetivo?')) {
                        item.remove();
                        atualizarMensagemObjetivos();
                    }
                });
                
                // Inicializa tooltips
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    const tooltipTriggerList = item.querySelectorAll('[data-bs-toggle="tooltip"]');
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }
                
            }
            
            objetivoIndex++;
            
            // Atualiza mensagem após cada objetivo ser inserido
            requestAnimationFrame(function() {
                atualizarMensagemObjetivos();
            });
        });
    }
    
    // Carrega ações dos dados já recebidos
    if (pdiData.acoes && pdiData.acoes.length > 0) {
        pdiData.acoes.forEach(function(acao) {
            const container = document.getElementById('acoes-container');
            const acaoHtml = `
                <div class="card mb-3 acao-item" data-index="${acaoIndex}">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Ação ${acaoIndex + 1}</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required fw-semibold fs-6 mb-2">
                                Título da Ação *
                                <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Descreva a ação específica que será realizada para alcançar o objetivo">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </label>
                            <input type="text" name="acoes[${acaoIndex}][acao]" class="form-control form-control-solid" placeholder="Ex: Participar de curso de comunicação" value="${escapeHtml(acao.acao || '')}" required>
                            <small class="text-muted">Ação concreta e executável</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-6 mb-2">
                                Descrição da Ação
                                <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Detalhe como a ação será executada, recursos necessários e responsáveis">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </label>
                            <textarea name="acoes[${acaoIndex}][descricao]" class="form-control form-control-solid" rows="3" placeholder="Descreva os detalhes da execução, materiais necessários, participantes envolvidos...">${escapeHtml(acao.descricao || '')}</textarea>
                            <small class="text-muted">Informe os detalhes de execução</small>
                        </div>
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label class="form-label fw-semibold fs-6 mb-2">
                                    Prazo para Execução
                                    <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Data limite para executar esta ação">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </label>
                                <input type="date" name="acoes[${acaoIndex}][prazo]" class="form-control form-control-solid" min="<?= date('Y-m-d') ?>" value="${acao.prazo || ''}">
                                <small class="text-muted">Data esperada de execução</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold fs-6 mb-2">
                                    Ordem de Execução
                                    <i class="ki-duotone ki-information-5 fs-7 text-muted ms-1" data-bs-toggle="tooltip" title="Número que define a ordem de execução (menor número = executar primeiro)">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </label>
                                <input type="number" name="acoes[${acaoIndex}][ordem]" class="form-control form-control-solid" value="${acao.ordem || acaoIndex + 1}" min="1" placeholder="1">
                                <small class="text-muted">Menor = executar primeiro</small>
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-light-danger w-100 btn-remover-acao">
                                    <i class="ki-duotone ki-trash fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Remover Ação
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', acaoHtml);
            
            const item = container.querySelector(`[data-index="${acaoIndex}"]`);
            if (item) {
                item.querySelector('.btn-remover-acao').addEventListener('click', function() {
                    if (confirm('Tem certeza que deseja remover esta ação?')) {
                        item.remove();
                        atualizarMensagemAcoes();
                    }
                });
                
                // Inicializa tooltips
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    const tooltipTriggerList = item.querySelectorAll('[data-bs-toggle="tooltip"]');
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }
                
            }
            
            acaoIndex++;
            
            // Atualiza mensagem após cada ação ser inserida
            requestAnimationFrame(function() {
                atualizarMensagemAcoes();
            });
        });
    }
    
    // Atualiza mensagens vazias finais usando requestAnimationFrame para garantir que o DOM foi atualizado
    requestAnimationFrame(function() {
        atualizarMensagemObjetivos();
        atualizarMensagemAcoes();
        
        // Chama novamente após um pequeno delay para garantir renderização completa
        setTimeout(function() {
            atualizarMensagemObjetivos();
            atualizarMensagemAcoes();
        }, 100);
    });
    
    // Atualiza título do modal
    document.querySelector('#modal-criar-pdi .modal-title').textContent = 'Editar PDI';
    document.querySelector('#modal-criar-pdi #btn-salvar-pdi').textContent = 'Atualizar';
    
    // Inicializa Select2 se necessário
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
        setTimeout(function() {
            const select2El = jQuery('#colaborador_id');
            if (select2El.data('select2')) {
                // Se já existe, atualiza o valor
                select2El.val(pdi.colaborador_id).trigger('change');
            } else {
                // Se não existe, inicializa
                select2El.select2({
                    dropdownParent: jQuery('#modal-criar-pdi')
                });
                select2El.val(pdi.colaborador_id).trigger('change');
            }
        }, 100);
    }
    
    modal.show();
}

// Função para excluir PDI
function excluirPDI(pdiId, titulo) {
    if (!confirm('Tem certeza que deseja excluir o PDI "' + titulo + '"?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    fetch('<?= get_base_url() ?>/api/pdis/excluir.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ pdi_id: pdiId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Sucesso!', 'PDI excluído com sucesso!');
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', 'Erro', data.message || 'Erro ao excluir PDI');
        }
    })
    .catch(error => {
        showAlert('error', 'Erro', 'Erro ao excluir PDI');
        console.error(error);
    });
}

// Limpa formulário ao fechar modal
document.getElementById('modal-criar-pdi')?.addEventListener('hidden.bs.modal', function() {
    const form = document.getElementById('form-criar-pdi');
    if (!form) return;
    
    form.reset();
    
    const objetivosContainer = document.getElementById('objetivos-container');
    const acoesContainer = document.getElementById('acoes-container');
    
    if (objetivosContainer) objetivosContainer.innerHTML = '';
    if (acoesContainer) acoesContainer.innerHTML = '';
    
    objetivoIndex = 0;
    acaoIndex = 0;
    
    // Remove campo pdi_id se existir
    const pdiIdInput = form.querySelector('[name="pdi_id"]');
    if (pdiIdInput) {
        pdiIdInput.remove();
    }
    
    // Restaura título do modal
    const modalTitle = document.querySelector('#modal-criar-pdi .modal-title');
    const btnSalvar = document.querySelector('#modal-criar-pdi #btn-salvar-pdi');
    if (modalTitle) modalTitle.textContent = 'Novo PDI';
    if (btnSalvar) btnSalvar.textContent = 'Salvar';
    
    // Não atualiza mensagens vazias aqui pois o modal está fechado
    // As funções já verificam se o modal está visível
});

// Inicializa mensagens vazias e event listeners ao carregar
document.addEventListener('DOMContentLoaded', function() {
    atualizarMensagemObjetivos();
    atualizarMensagemAcoes();
    
    // Event listeners para editar PDI
    document.querySelectorAll('.btn-editar-pdi').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const pdiId = this.getAttribute('data-pdi-id');
            if (!pdiId) {
                showAlert('error', 'Erro', 'ID do PDI não encontrado');
                return;
            }
            
            // Carrega dados do PDI via API
            fetch('<?= get_base_url() ?>/api/pdis/detalhes.php?id=' + pdiId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta da API');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.pdi) {
                        editarPDI(data);
                    } else {
                        showAlert('error', 'Erro', data.message || 'Erro ao carregar dados do PDI');
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar detalhes do PDI:', error);
                    showAlert('error', 'Erro', 'Erro ao carregar dados do PDI: ' + error.message);
                });
        });
    });
    
    // Event listeners para excluir PDI
    document.querySelectorAll('.btn-excluir-pdi').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const pdiId = this.getAttribute('data-pdi-id');
            const pdiTitulo = this.getAttribute('data-pdi-titulo');
            excluirPDI(pdiId, pdiTitulo);
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

