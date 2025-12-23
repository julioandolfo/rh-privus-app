<?php
/**
 * Kanban de Seleção - Drag & Drop
 */

$page_title = 'Kanban de Seleção';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/recrutamento_functions.php';

require_page_permission('kanban_selecao.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$filtro_vaga = !empty($_GET['vaga_id']) ? (int)$_GET['vaga_id'] : null;

// Busca colunas do Kanban
$colunas = buscar_colunas_kanban();

// Busca candidaturas
$filtros = [];
if ($filtro_vaga) {
    $filtros['vaga_id'] = $filtro_vaga;
}
$candidaturas = buscar_candidaturas_kanban($filtros);

// Busca vagas para filtro
$stmt = $pdo->query("SELECT id, titulo FROM vagas WHERE status = 'aberta' ORDER BY titulo");
$vagas = $stmt->fetchAll();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <!-- Título da Página -->
                <div class="mb-5">
                    <div class="d-flex align-items-center mb-2">
                        <i class="ki-duotone ki-chart-simple fs-2x text-primary me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                        <div>
                            <h1 class="text-gray-800 fw-bold mb-1">Kanban de Seleção</h1>
                            <p class="text-gray-600 fs-6 mb-0">
                                Gerencie o processo seletivo dos candidatos. Arraste os cards entre as etapas para atualizar o status.
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
                                <input type="text" id="kt_filter_search" class="form-control form-control-solid w-250px ps-13" placeholder="Buscar candidato..." />
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <select id="filtroVaga" class="form-select form-select-solid w-250px">
                                <option value="">Todas as vagas</option>
                                <?php foreach ($vagas as $vaga): ?>
                                <option value="<?= $vaga['id'] ?>" <?= $filtro_vaga == $vaga['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vaga['titulo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div id="kanbanContainer" class="d-flex overflow-auto pb-5" style="gap: 1rem;">
                            <?php foreach ($colunas as $coluna): ?>
                            <div class="flex-shrink-0" style="width: 320px;" data-coluna="<?= htmlspecialchars($coluna['codigo']) ?>">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header border-0 align-items-center py-4 kanban-column-header" 
                                         data-column-color="<?= htmlspecialchars($coluna['cor']) ?>"
                                         style="background: linear-gradient(135deg, <?= htmlspecialchars($coluna['cor']) ?> 0%, <?= htmlspecialchars($coluna['cor']) ?>dd 100%);">
                                        <div class="d-flex flex-column w-100">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center">
                                                    <?php if ($coluna['icone']): ?>
                                                    <i class="ki-duotone ki-<?= htmlspecialchars($coluna['icone']) ?> fs-2x kanban-header-icon text-white me-3">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    <?php endif; ?>
                                                    <h3 class="kanban-header-title text-white fw-bold mb-0 fs-5"><?= htmlspecialchars($coluna['nome']) ?></h3>
                                                    <!-- Indicador de cor visível -->
                                                    <span class="ms-2 kanban-header-color-dot" style="background-color: <?= htmlspecialchars($coluna['cor']) ?>; width: 12px; height: 12px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.5); display: inline-block;"></span>
                                                </div>
                                                <span class="badge badge-circle badge-light badge-active fs-6 fw-bold" id="count-<?= htmlspecialchars($coluna['codigo']) ?>">0</span>
                                            </div>
                                            <div class="kanban-header-subtitle text-white opacity-75 fs-7">
                                                <i class="ki-duotone ki-user fs-6 me-1">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <span id="count-text-<?= htmlspecialchars($coluna['codigo']) ?>">candidato(s)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-4 kanban-column" style="min-height: 600px;">
                                        <?php
                                        $candidaturas_coluna = array_filter($candidaturas, function($c) use ($coluna) {
                                            $coluna_candidatura = $c['coluna_kanban'] ?? 'novos_candidatos';
                                            // Se for entrevista manual sem coluna, coloca na coluna entrevistas
                                            if (!empty($c['is_entrevista_manual']) && empty($c['coluna_kanban'])) {
                                                $coluna_candidatura = 'entrevistas';
                                            }
                                            return $coluna_candidatura === $coluna['codigo'];
                                        });
                                        ?>
                                        
                                        <div class="text-center py-10 empty-message" style="display: <?= empty($candidaturas_coluna) ? 'block' : 'none' ?>;">
                                            <i class="ki-duotone ki-information-5 fs-3x text-gray-400 mb-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            <p class="text-gray-500 fs-6 mb-0">Nenhum candidato nesta etapa</p>
                                        </div>
                                        
                                        <?php foreach ($candidaturas_coluna as $candidatura): ?>
                                        <?php 
                                        $is_entrevista_manual = !empty($candidatura['is_entrevista_manual']);
                                        $entrevista_id = $is_entrevista_manual ? str_replace('entrevista_', '', $candidatura['id']) : null;
                                        ?>
                                        <div class="card shadow-sm mb-3 kanban-card cursor-move" 
                                             draggable="true" 
                                             data-candidatura-id="<?= $candidatura['id'] ?>"
                                             data-coluna-atual="<?= htmlspecialchars($coluna['codigo']) ?>"
                                             data-coluna-cor="<?= htmlspecialchars($coluna['cor']) ?>"
                                             data-nome="<?= strtolower(htmlspecialchars($candidatura['nome_completo'])) ?>"
                                             data-is-entrevista="<?= $is_entrevista_manual ? '1' : '0' ?>">
                                            <div class="kanban-card-color-indicator" style="background-color: <?= htmlspecialchars($coluna['cor']) ?>;"></div>
                                            <?php if ($is_entrevista_manual): ?>
                                            <div class="position-absolute top-0 end-0 m-2">
                                                <span class="badge badge-light-warning">Entrevista Manual</span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="card-body p-4">
                                                <div class="d-flex align-items-start mb-3">
                                                    <div class="symbol symbol-45px symbol-circle me-3">
                                                        <div class="symbol-label bg-light-primary text-primary fw-bold fs-4">
                                                            <?= strtoupper(substr($candidatura['nome_completo'], 0, 1)) ?>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 min-w-0">
                                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                                            <?php if ($is_entrevista_manual): ?>
                                                            <a href="entrevista_view.php?id=<?= $entrevista_id ?>" class="text-gray-800 text-hover-primary fw-bold d-block fs-6">
                                                                <?= htmlspecialchars($candidatura['nome_completo']) ?>
                                                            </a>
                                                            <?php else: ?>
                                                            <a href="candidatura_view.php?id=<?= $candidatura['id'] ?>" class="text-gray-800 text-hover-primary fw-bold d-block fs-6">
                                                                <?= htmlspecialchars($candidatura['nome_completo']) ?>
                                                            </a>
                                                            <?php endif; ?>
                                                            <span class="kanban-stage-badge" style="background-color: <?= htmlspecialchars($coluna['cor']) ?>20; border-left: 3px solid <?= htmlspecialchars($coluna['cor']) ?>;"></span>
                                                        </div>
                                                        <span class="text-muted fw-semibold d-block fs-7 mt-1">
                                                            <?= htmlspecialchars($candidatura['vaga_titulo'] ?? 'Sem vaga') ?>
                                                        </span>
                                                        <?php if ($is_entrevista_manual && !empty($candidatura['entrevista_titulo'])): ?>
                                                        <span class="text-muted fw-semibold d-block fs-7">
                                                            <i class="ki-duotone ki-calendar fs-6"></i> <?= htmlspecialchars($candidatura['entrevista_titulo']) ?>
                                                        </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!$is_entrevista_manual && !empty($candidatura['nota_geral'])): ?>
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge badge-light-info fs-7 me-2">Nota</span>
                                                        <div class="d-flex align-items-center">
                                                            <span class="text-gray-800 fw-bold fs-5 me-1"><?= $candidatura['nota_geral'] ?></span>
                                                            <span class="text-gray-500 fs-7">/10</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="separator separator-dashed my-3"></div>
                                                
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-500 fs-7">Data</span>
                                                        <span class="text-gray-800 fw-semibold fs-7">
                                                            <?= date('d/m/Y', strtotime($candidatura['data_candidatura'] ?? $candidatura['created_at'])) ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($is_entrevista_manual): ?>
                                                    <a href="entrevista_view.php?id=<?= $entrevista_id ?>" 
                                                       class="btn btn-sm btn-light-primary">
                                                        Ver Entrevista
                                                    </a>
                                                    <?php else: ?>
                                                    <a href="candidatura_view.php?id=<?= $candidatura['id'] ?>" 
                                                       class="btn btn-sm btn-light-primary">
                                                        Ver Detalhes
                                                    </a>
                                                    <?php endif; ?>
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
// Atualiza contadores e mensagens de vazio
function atualizarContadores() {
    <?php foreach ($colunas as $coluna): ?>
    const count<?= $coluna['codigo'] ?> = document.querySelectorAll('[data-coluna-atual="<?= $coluna['codigo'] ?>"]').length;
    document.getElementById('count-<?= $coluna['codigo'] ?>').textContent = count<?= $coluna['codigo'] ?>;
    const countTextEl<?= $coluna['codigo'] ?> = document.getElementById('count-text-<?= $coluna['codigo'] ?>');
    if (countTextEl<?= $coluna['codigo'] ?>) {
        countTextEl<?= $coluna['codigo'] ?>.textContent = count<?= $coluna['codigo'] ?> === 1 ? 'candidato' : 'candidato(s)';
    }
    
    // Atualiza mensagem de vazio
    const colunaElement<?= $coluna['codigo'] ?> = document.querySelector(`[data-coluna="<?= $coluna['codigo'] ?>"] .kanban-column`);
    if (colunaElement<?= $coluna['codigo'] ?>) {
        const emptyMessage<?= $coluna['codigo'] ?> = colunaElement<?= $coluna['codigo'] ?>.querySelector('.empty-message');
        // Conta apenas cards visíveis (não ocultos pelo filtro de busca)
        const visibleCards<?= $coluna['codigo'] ?> = Array.from(colunaElement<?= $coluna['codigo'] ?>.querySelectorAll('.kanban-card')).filter(card => {
            return card.style.display !== 'none' && window.getComputedStyle(card).display !== 'none';
        });
        const hasCards<?= $coluna['codigo'] ?> = visibleCards<?= $coluna['codigo'] ?>.length > 0;
        
        if (emptyMessage<?= $coluna['codigo'] ?>) {
            emptyMessage<?= $coluna['codigo'] ?>.style.display = hasCards<?= $coluna['codigo'] ?> ? 'none' : 'block';
        }
    }
    <?php endforeach; ?>
}

// Drag and Drop
let draggedElement = null;
let draggedFromColumn = null;

function inicializarDragAndDrop() {
    // Remove listeners antigos se existirem
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.removeEventListener('dragstart', handleDragStart);
        card.removeEventListener('dragend', handleDragEnd);
    });
    
    document.querySelectorAll('.kanban-column').forEach(column => {
        column.removeEventListener('dragover', handleDragOver);
        column.removeEventListener('dragleave', handleDragLeave);
        column.removeEventListener('drop', handleDrop);
    });
    
    // Adiciona novos listeners
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    document.querySelectorAll('.kanban-column').forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('dragleave', handleDragLeave);
        column.addEventListener('drop', handleDrop);
    });
}

function handleDragStart(e) {
    draggedElement = this;
    draggedFromColumn = this.dataset.colunaAtual;
    this.style.opacity = '0.5';
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.outerHTML);
    e.dataTransfer.setData('text/plain', this.dataset.candidaturaId);
}

function handleDragEnd() {
    this.style.opacity = '1';
    // Remove estilos de drag over de todas as colunas
    document.querySelectorAll('.kanban-column').forEach(col => {
        col.style.backgroundColor = '';
        col.style.border = '';
    });
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'move';
    this.style.backgroundColor = '#e1f0ff';
    this.style.border = '2px dashed #009ef7';
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    // Só remove o estilo se realmente saiu da coluna (não apenas entrou em um filho)
    if (!this.contains(e.relatedTarget)) {
        this.style.backgroundColor = '';
        this.style.border = '';
    }
}

async function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.style.backgroundColor = '';
    this.style.border = '';
    
    if (!draggedElement) return;
    
    const candidaturaId = draggedElement.dataset.candidaturaId;
    const colunaDestino = this.closest('[data-coluna]').dataset.coluna;
    
    if (!colunaDestino) {
        console.error('Coluna destino não encontrada');
        return;
    }
    
    if (draggedFromColumn === colunaDestino) {
        return; // Mesma coluna, não faz nada
    }
    
    // Move visualmente
    this.appendChild(draggedElement);
    draggedElement.dataset.colunaAtual = colunaDestino;
    
    // Atualiza contadores
    atualizarContadores();
    
    // Salva no servidor
    try {
        const isEntrevista = draggedElement.dataset.isEntrevista === '1';
        const formData = new FormData();
        formData.append('candidatura_id', candidaturaId);
        formData.append('coluna_codigo', colunaDestino);
        if (isEntrevista) {
            formData.append('is_entrevista', '1');
        }
        
        const response = await fetch('../api/recrutamento/kanban/mover.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (!data.success) {
            alert('Erro ao mover: ' + data.message);
            // Reverte movimento
            const colunaOrigem = document.querySelector(`[data-coluna="${draggedFromColumn}"] .kanban-column`);
            if (colunaOrigem) {
                colunaOrigem.appendChild(draggedElement);
                draggedElement.dataset.colunaAtual = draggedFromColumn;
                atualizarContadores();
            }
        } else {
            // Se moveu para contratado ou aprovados, abre modal para cadastrar colaborador
            if ((colunaDestino === 'contratado' || colunaDestino === 'aprovados') && !isEntrevista) {
                // Aguarda um pouco para garantir que o movimento foi salvo
                setTimeout(() => {
                    abrirModalCadastrarColaborador(candidaturaId, draggedElement);
                }, 500);
            }
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao mover candidatura');
        // Reverte movimento
        const colunaOrigem = document.querySelector(`[data-coluna="${draggedFromColumn}"] .kanban-column`);
        if (colunaOrigem) {
            colunaOrigem.appendChild(draggedElement);
            draggedElement.dataset.colunaAtual = draggedFromColumn;
            atualizarContadores();
        }
    }
    
    draggedElement = null;
    draggedFromColumn = null;
}

// Inicializa drag and drop quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    inicializarDragAndDrop();
});

// Filtro de vaga
document.addEventListener('DOMContentLoaded', function() {
    const filtroVaga = document.getElementById('filtroVaga');
    if (filtroVaga) {
        filtroVaga.addEventListener('change', function() {
            const vagaId = this.value;
            if (vagaId) {
                window.location.href = 'kanban_selecao.php?vaga_id=' + vagaId;
            } else {
                window.location.href = 'kanban_selecao.php';
            }
        });
    }
    
    // Busca de candidatos
    const searchInput = document.getElementById('kt_filter_search');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.kanban-card').forEach(card => {
                const nome = card.dataset.nome || '';
                if (nome.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
            // Atualiza mensagens de vazio após filtrar
            atualizarContadores();
        });
    }
});

// Estilos adicionais com suporte a dark/light mode
const style = document.createElement('style');
style.textContent = `
    /* Light Mode - Cores padrão */
    .kanban-column-header {
        background: linear-gradient(135deg, var(--column-color) 0%, var(--column-color-dd) 100%);
    }
    
    .kanban-header-icon,
    .kanban-header-title,
    .kanban-header-subtitle {
        color: white;
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
    #kanbanContainer {
        scrollbar-width: thin;
    }
    
    [data-bs-theme="light"] #kanbanContainer {
        scrollbar-color: #e4e6ef transparent;
    }
    
    [data-bs-theme="dark"] #kanbanContainer {
        scrollbar-color: #3f3f5f transparent;
    }
    
    #kanbanContainer::-webkit-scrollbar {
        height: 8px;
    }
    
    #kanbanContainer::-webkit-scrollbar-track {
        background: transparent;
    }
    
    [data-bs-theme="light"] #kanbanContainer::-webkit-scrollbar-thumb {
        background-color: #e4e6ef;
    }
    
    [data-bs-theme="dark"] #kanbanContainer::-webkit-scrollbar-thumb {
        background-color: #3f3f5f;
    }
    
    #kanbanContainer::-webkit-scrollbar-thumb:hover {
        background-color: #b5b5c3;
    }
    
    [data-bs-theme="dark"] #kanbanContainer::-webkit-scrollbar-thumb:hover {
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
    
    /* Aplicar cores dinâmicas das colunas */
    .kanban-column-header[data-column-color] {
        --column-color: attr(data-column-color);
    }
`;

// Função para aplicar cores das colunas baseado no tema (ajusta opacidade no dark mode)
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
    
    // Inicializa drag and drop
    inicializarDragAndDrop();
    
    // Inicializa contadores
    atualizarContadores();
    
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

// Função para abrir modal de cadastrar colaborador
function abrirModalCadastrarColaborador(candidaturaId, elementoCard) {
    // Remove prefixo se for entrevista
    const idLimpo = candidaturaId.toString().replace('entrevista_', '');
    const isEntrevista = candidaturaId.toString().startsWith('entrevista_');
    
    // Busca dados da candidatura/entrevista
    fetch(`../api/recrutamento/candidaturas/dados_cadastro.php?id=${idLimpo}&is_entrevista=${isEntrevista ? '1' : '0'}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Preenche modal com dados
                document.getElementById('modalCadastrarColaborador').querySelector('[name="candidatura_id"]').value = candidaturaId;
                document.getElementById('modalCadastrarColaborador').querySelector('[name="is_entrevista"]').value = isEntrevista ? '1' : '0';
                
                // Preenche campos do formulário
                if (data.dados) {
                    const form = document.getElementById('formCadastrarColaborador');
                    if (data.dados.nome_completo) form.querySelector('[name="nome_completo"]').value = data.dados.nome_completo;
                    if (data.dados.email) form.querySelector('[name="email_pessoal"]').value = data.dados.email;
                    if (data.dados.telefone) form.querySelector('[name="telefone"]').value = data.dados.telefone;
                    if (data.dados.cpf) form.querySelector('[name="cpf"]').value = data.dados.cpf;
                    if (data.dados.empresa_id) form.querySelector('[name="empresa_id"]').value = data.dados.empresa_id;
                    if (data.dados.setor_id) form.querySelector('[name="setor_id"]').value = data.dados.setor_id;
                    if (data.dados.cargo_id) form.querySelector('[name="cargo_id"]').value = data.dados.cargo_id;
                }
                
                // Abre modal
                const modal = new bootstrap.Modal(document.getElementById('modalCadastrarColaborador'));
                modal.show();
            } else {
                alert('Erro ao carregar dados: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar dados da candidatura');
        });
}
</script>

<!-- Modal Cadastrar Colaborador -->
<div class="modal fade" id="modalCadastrarColaborador" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cadastrar como Colaborador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCadastrarColaborador">
                <input type="hidden" name="candidatura_id" value="">
                <input type="hidden" name="is_entrevista" value="0">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ki-duotone ki-information-5 fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Preencha os dados abaixo para cadastrar como colaborador. Os campos marcados com * são obrigatórios.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome Completo *</label>
                            <input type="text" name="nome_completo" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CPF *</label>
                            <input type="text" name="cpf" class="form-control" required placeholder="000.000.000-00">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email_pessoal" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Empresa *</label>
                            <select name="empresa_id" class="form-select" required id="empresaSelect">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Setor *</label>
                            <select name="setor_id" class="form-select" required id="setorSelect">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cargo *</label>
                            <select name="cargo_id" class="form-select" required id="cargoSelect">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data de Início *</label>
                            <input type="date" name="data_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Contrato</label>
                            <select name="tipo_contrato" class="form-select">
                                <option value="CLT">CLT</option>
                                <option value="PJ">PJ</option>
                                <option value="Estágio">Estágio</option>
                                <option value="Terceirizado">Terceirizado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Salário</label>
                        <input type="text" name="salario" class="form-control" placeholder="0,00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cadastrar Colaborador</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Carrega empresas, setores e cargos ao abrir modal
document.getElementById('modalCadastrarColaborador').addEventListener('show.bs.modal', function() {
    carregarEmpresas();
});

// Quando seleciona empresa, carrega setores
document.getElementById('empresaSelect').addEventListener('change', function() {
    if (this.value) {
        carregarSetores(this.value);
    }
});

// Quando seleciona setor, carrega cargos
document.getElementById('setorSelect').addEventListener('change', function() {
    if (this.value) {
        carregarCargos(this.value);
    }
});

async function carregarEmpresas() {
    try {
        const response = await fetch('../api/empresas/listar.php');
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('empresaSelect');
            select.innerHTML = '<option value="">Selecione...</option>';
            data.empresas.forEach(empresa => {
                const option = document.createElement('option');
                option.value = empresa.id;
                option.textContent = empresa.nome_fantasia;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar empresas:', error);
    }
}

async function carregarSetores(empresaId) {
    try {
        const response = await fetch(`../api/setores/listar.php?empresa_id=${empresaId}`);
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('setorSelect');
            select.innerHTML = '<option value="">Selecione...</option>';
            data.setores.forEach(setor => {
                const option = document.createElement('option');
                option.value = setor.id;
                option.textContent = setor.nome_setor;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar setores:', error);
    }
}

async function carregarCargos(setorId) {
    try {
        const response = await fetch(`../api/cargos/listar.php?setor_id=${setorId}`);
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('cargoSelect');
            select.innerHTML = '<option value="">Selecione...</option>';
            data.cargos.forEach(cargo => {
                const option = document.createElement('option');
                option.value = cargo.id;
                option.textContent = cargo.nome_cargo;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar cargos:', error);
    }
}

// Submete formulário
document.getElementById('formCadastrarColaborador').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/colaborador/cadastrar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Colaborador cadastrado com sucesso!');
            bootstrap.Modal.getInstance(document.getElementById('modalCadastrarColaborador')).hide();
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao cadastrar colaborador');
        console.error(error);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

