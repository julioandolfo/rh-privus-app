<?php
/**
 * Kanban de Onboarding
 */

$page_title = 'Kanban de Onboarding';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('onboarding.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca processos de onboarding
$where = ["1=1"];
$params = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "v.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$sql = "
    SELECT o.*,
           c.nome_completo as candidato_nome,
           col.nome_completo as colaborador_nome,
           v.titulo as vaga_titulo,
           u.nome as responsavel_nome,
           m.nome_completo as mentor_nome,
           COUNT(DISTINCT t.id) as total_tarefas,
           COUNT(DISTINCT CASE WHEN t.status = 'concluida' THEN t.id END) as tarefas_concluidas
    FROM onboarding o
    INNER JOIN candidaturas cand ON o.candidatura_id = cand.id
    INNER JOIN candidatos c ON cand.candidato_id = c.id
    INNER JOIN vagas v ON cand.vaga_id = v.id
    LEFT JOIN colaboradores col ON o.colaborador_id = col.id
    LEFT JOIN usuarios u ON o.responsavel_id = u.id
    LEFT JOIN colaboradores m ON o.mentor_id = m.id
    LEFT JOIN onboarding_tarefas t ON o.id = t.onboarding_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY o.id
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$onboardings = $stmt->fetchAll();

// Organiza por coluna
$colunas = [
    'contratado' => ['nome' => 'Contratado', 'cor' => '#009ef7'],
    'documentacao' => ['nome' => 'Documentação', 'cor' => '#ffc700'],
    'treinamento' => ['nome' => 'Treinamento', 'cor' => '#7239ea'],
    'integracao' => ['nome' => 'Integração', 'cor' => '#50cd89'],
    'acompanhamento' => ['nome' => 'Acompanhamento', 'cor' => '#f1416c'],
    'concluido' => ['nome' => 'Concluído', 'cor' => '#50cd89']
];
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <!-- Título da Página -->
                <div class="mb-5">
                    <div class="d-flex align-items-center mb-2">
                        <i class="ki-duotone ki-people fs-2x text-success me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div>
                            <h1 class="text-gray-800 fw-bold mb-1">Kanban de Onboarding</h1>
                            <p class="text-gray-600 fs-6 mb-0">
                                Acompanhe o processo de integração dos novos colaboradores. Gerencie tarefas e etapas de onboarding.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <input type="text" id="kt_filter_search_onboarding" class="form-control form-control-solid w-250px ps-13" placeholder="Buscar colaborador..." />
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div id="kanbanOnboarding" class="d-flex overflow-auto pb-5" style="gap: 1rem;">
                            <?php foreach ($colunas as $codigo => $coluna): ?>
                            <div class="flex-shrink-0" style="width: 320px;" data-coluna="<?= htmlspecialchars($codigo) ?>">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header border-0 align-items-center py-4 kanban-column-header" 
                                         data-column-color="<?= htmlspecialchars($coluna['cor']) ?>"
                                         style="background: linear-gradient(135deg, <?= htmlspecialchars($coluna['cor']) ?> 0%, <?= htmlspecialchars($coluna['cor']) ?>dd 100%);">
                                        <div class="d-flex flex-column w-100">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center">
                                                    <h3 class="kanban-header-title text-white fw-bold mb-0 fs-5"><?= htmlspecialchars($coluna['nome']) ?></h3>
                                                    <!-- Indicador de cor visível -->
                                                    <span class="ms-2 kanban-header-color-dot" style="background-color: <?= htmlspecialchars($coluna['cor']) ?>; width: 12px; height: 12px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.5); display: inline-block;"></span>
                                                </div>
                                                <span class="badge badge-circle badge-light badge-active fs-6 fw-bold" id="count-<?= htmlspecialchars($codigo) ?>">0</span>
                                            </div>
                                            <div class="kanban-header-subtitle text-white opacity-75 fs-7">
                                                <i class="ki-duotone ki-people fs-6 me-1">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <span id="count-text-<?= htmlspecialchars($codigo) ?>">processo(s)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-4 kanban-column" style="min-height: 600px;">
                                        <?php
                                        $onboardings_coluna = array_filter($onboardings, function($o) use ($codigo) {
                                            return ($o['coluna_kanban'] ?? 'contratado') === $codigo;
                                        });
                                        ?>
                                        
                                        <?php if (empty($onboardings_coluna)): ?>
                                        <div class="text-center py-10">
                                            <i class="ki-duotone ki-information-5 fs-3x text-gray-400 mb-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            <p class="text-gray-500 fs-6 mb-0">Nenhum processo nesta etapa</p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php foreach ($onboardings_coluna as $onboarding): ?>
                                        <div class="card shadow-sm mb-3 kanban-card cursor-move" 
                                             draggable="true" 
                                             data-onboarding-id="<?= $onboarding['id'] ?>"
                                             data-coluna-atual="<?= htmlspecialchars($codigo) ?>"
                                             data-coluna-cor="<?= htmlspecialchars($coluna['cor']) ?>"
                                             data-nome="<?= strtolower(htmlspecialchars($onboarding['colaborador_nome'] ?: $onboarding['candidato_nome'])) ?>">
                                            <div class="kanban-card-color-indicator" style="background-color: <?= htmlspecialchars($coluna['cor']) ?>;"></div>
                                            <div class="card-body p-4">
                                                <div class="d-flex align-items-start mb-3">
                                                    <div class="symbol symbol-45px symbol-circle me-3">
                                                        <div class="symbol-label bg-light-success text-success fw-bold fs-4">
                                                            <?= strtoupper(substr($onboarding['colaborador_nome'] ?: $onboarding['candidato_nome'], 0, 1)) ?>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 min-w-0">
                                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                                            <a href="onboarding_view.php?id=<?= $onboarding['id'] ?>" class="text-gray-800 text-hover-primary fw-bold d-block fs-6">
                                                                <?= htmlspecialchars($onboarding['colaborador_nome'] ?: $onboarding['candidato_nome']) ?>
                                                            </a>
                                                            <span class="kanban-stage-badge" style="background-color: <?= htmlspecialchars($coluna['cor']) ?>20; border-left: 3px solid <?= htmlspecialchars($coluna['cor']) ?>;"></span>
                                                        </div>
                                                        <span class="text-muted fw-semibold d-block fs-7 mt-1">
                                                            <?= htmlspecialchars($onboarding['vaga_titulo']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($onboarding['total_tarefas'] > 0): ?>
                                                <?php $percentual = ($onboarding['tarefas_concluidas'] / $onboarding['total_tarefas']) * 100; ?>
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <span class="text-gray-600 fw-semibold fs-7">Progresso</span>
                                                        <span class="text-gray-800 fw-bold fs-7"><?= round($percentual) ?>%</span>
                                                    </div>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percentual ?>%" aria-valuenow="<?= $percentual ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <div class="d-flex justify-content-between mt-1">
                                                        <span class="text-gray-500 fs-7">
                                                            <?= $onboarding['tarefas_concluidas'] ?>/<?= $onboarding['total_tarefas'] ?> tarefas concluídas
                                                        </span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="separator separator-dashed my-3"></div>
                                                
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <?php if ($onboarding['responsavel_nome']): ?>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-500 fs-7">Responsável</span>
                                                        <span class="text-gray-800 fw-semibold fs-7">
                                                            <?= htmlspecialchars($onboarding['responsavel_nome']) ?>
                                                        </span>
                                                    </div>
                                                    <?php endif; ?>
                                                    <a href="onboarding_view.php?id=<?= $onboarding['id'] ?>" 
                                                       class="btn btn-sm btn-light-primary">
                                                        Ver Detalhes
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
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

<script>
// Atualiza contadores
function atualizarContadores() {
    <?php foreach ($colunas as $codigo => $coluna): ?>
    const count<?= str_replace('-', '_', $codigo) ?> = document.querySelectorAll('[data-coluna-atual="<?= $codigo ?>"]').length;
    document.getElementById('count-<?= $codigo ?>').textContent = count<?= str_replace('-', '_', $codigo) ?>;
    const countTextEl = document.getElementById('count-text-<?= $codigo ?>');
    if (countTextEl) {
        countTextEl.textContent = count<?= str_replace('-', '_', $codigo) ?> === 1 ? 'processo' : 'processo(s)';
    }
    <?php endforeach; ?>
}

// Drag and Drop (similar ao Kanban de seleção)
let draggedElement = null;
let draggedFromColumn = null;

document.querySelectorAll('.kanban-card').forEach(card => {
    card.addEventListener('dragstart', function(e) {
        draggedElement = this;
        draggedFromColumn = this.dataset.colunaAtual;
        this.style.opacity = '0.5';
    });
    
    card.addEventListener('dragend', function() {
        this.style.opacity = '1';
    });
});

document.querySelectorAll('.kanban-column').forEach(column => {
    column.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.backgroundColor = '#e1f0ff';
        this.style.border = '2px dashed #009ef7';
    });
    
    column.addEventListener('dragleave', function() {
        this.style.backgroundColor = '#f5f8fa';
        this.style.border = 'none';
    });
    
    column.addEventListener('drop', async function(e) {
        e.preventDefault();
        this.style.backgroundColor = '#f5f8fa';
        this.style.border = 'none';
        
        if (!draggedElement) return;
        
        const onboardingId = draggedElement.dataset.onboardingId;
        const colunaDestino = this.closest('[data-coluna]').dataset.coluna;
        
        if (draggedFromColumn === colunaDestino) return;
        
        // Move visualmente
        this.appendChild(draggedElement);
        draggedElement.dataset.colunaAtual = colunaDestino;
        atualizarContadores();
        
        // Salva no servidor
        try {
            const formData = new FormData();
            formData.append('onboarding_id', onboardingId);
            formData.append('coluna', colunaDestino);
            
            const response = await fetch('../api/recrutamento/onboarding/mover.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (!data.success) {
                alert('Erro ao mover: ' + data.message);
                const colunaOrigem = document.querySelector(`[data-coluna="${draggedFromColumn}"] .kanban-column`);
                colunaOrigem.appendChild(draggedElement);
                draggedElement.dataset.colunaAtual = draggedFromColumn;
                atualizarContadores();
            }
        } catch (error) {
            console.error('Erro:', error);
            const colunaOrigem = document.querySelector(`[data-coluna="${draggedFromColumn}"] .kanban-column`);
            colunaOrigem.appendChild(draggedElement);
            draggedElement.dataset.colunaAtual = draggedFromColumn;
            atualizarContadores();
        }
        
        draggedElement = null;
        draggedFromColumn = null;
    });
});

// Busca de colaboradores
const searchInputOnboarding = document.getElementById('kt_filter_search_onboarding');
if (searchInputOnboarding) {
    searchInputOnboarding.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('#kanbanOnboarding .kanban-card').forEach(card => {
            const nome = card.dataset.nome || '';
            if (nome.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

// Estilos adicionais com suporte a dark/light mode
const style = document.createElement('style');
style.textContent = `
    /* Light Mode - Cores padrão */
    .kanban-column-header {
        /* Background será aplicado inline via PHP */
    }
    
    .kanban-header-icon,
    .kanban-header-title,
    .kanban-header-subtitle {
        color: white !important;
    }
    
    /* Indicador de cor no cabeçalho */
    .kanban-header-color-dot {
        box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
    }
    
    .kanban-column {
        background-color: #f5f8fa;
        transition: all 0.2s ease;
    }
    
    .kanban-card {
        transition: all 0.2s ease;
        border: 1px solid #e4e6ef;
        background-color: white;
    }
    
    .kanban-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
        border-color: #009ef7;
    }
    
    /* Dark Mode */
    [data-bs-theme="dark"] .kanban-column {
        background-color: #1e1e2d;
    }
    
    [data-bs-theme="dark"] .kanban-card {
        background-color: #2b2b40;
        border-color: #3f3f5f;
    }
    
    [data-bs-theme="dark"] .kanban-card:hover {
        border-color: #009ef7;
        box-shadow: 0 4px 12px rgba(0, 158, 247, 0.2) !important;
    }
    
    [data-bs-theme="dark"] .kanban-column-header {
        opacity: 0.9;
    }
    
    /* Scrollbar adaptável */
    #kanbanOnboarding {
        scrollbar-width: thin;
    }
    
    [data-bs-theme="light"] #kanbanOnboarding {
        scrollbar-color: #e4e6ef transparent;
    }
    
    [data-bs-theme="dark"] #kanbanOnboarding {
        scrollbar-color: #3f3f5f transparent;
    }
    
    #kanbanOnboarding::-webkit-scrollbar {
        height: 8px;
    }
    
    #kanbanOnboarding::-webkit-scrollbar-track {
        background: transparent;
    }
    
    [data-bs-theme="light"] #kanbanOnboarding::-webkit-scrollbar-thumb {
        background-color: #e4e6ef;
    }
    
    [data-bs-theme="dark"] #kanbanOnboarding::-webkit-scrollbar-thumb {
        background-color: #3f3f5f;
    }
    
    #kanbanOnboarding::-webkit-scrollbar-thumb:hover {
        background-color: #b5b5c3;
    }
    
    [data-bs-theme="dark"] #kanbanOnboarding::-webkit-scrollbar-thumb:hover {
        background-color: #5f5f7f;
    }
    
    .kanban-card[draggable="true"] {
        cursor: grab;
    }
    
    .kanban-card[draggable="true"]:active {
        cursor: grabbing;
    }
    
    /* Indicador de cor da etapa no card */
    .kanban-card {
        position: relative;
        overflow: hidden;
    }
    
    .kanban-card-color-indicator {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        z-index: 1;
        transition: width 0.2s ease;
    }
    
    .kanban-card:hover .kanban-card-color-indicator {
        width: 6px;
    }
    
    /* Badge da etapa no card */
    .kanban-stage-badge {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-left: 8px;
        display: inline-block;
    }
`;

// Função para aplicar cores das colunas baseado no tema
function aplicarCoresColunas() {
    document.querySelectorAll('.kanban-column-header[data-column-color]').forEach(header => {
        const color = header.getAttribute('data-column-color');
        if (color && header.style.background) {
            // Converte cor hex para rgba
            const hexToRgb = (hex) => {
                const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                return result ? {
                    r: parseInt(result[1], 16),
                    g: parseInt(result[2], 16),
                    b: parseInt(result[3], 16)
                } : null;
            };
            
            const rgb = hexToRgb(color);
            if (rgb) {
                const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                
                if (isDark) {
                    // Dark mode: usar rgba com opacidade para melhor contraste
                    header.style.background = `linear-gradient(135deg, rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.9) 0%, rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.8) 100%)`;
                } else {
                    // Light mode: gradiente normal (restaura do inline style)
                    header.style.background = `linear-gradient(135deg, ${color} 0%, ${color}dd 100%)`;
                }
            }
        }
    });
}

// Aplicar cores das colunas dinamicamente
document.addEventListener('DOMContentLoaded', function() {
    aplicarCoresColunas();
    
    // Observar mudanças no tema
    const observer = new MutationObserver(() => {
        aplicarCoresColunas();
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });
    
    // Também escutar eventos de mudança de tema do Metronic
    document.addEventListener('kt.thememode.change', function() {
        setTimeout(aplicarCoresColunas, 100);
    });
});

document.head.appendChild(style);

atualizarContadores();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

