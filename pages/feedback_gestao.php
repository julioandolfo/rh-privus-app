<?php
/**
 * Gestão de Feedbacks - RH/ADMIN
 * Visualiza todos os feedbacks do sistema
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// Verifica permissão - apenas ADMIN e RH
if (!has_role(['ADMIN', 'RH'])) {
    redirect('dashboard.php', 'Você não tem permissão para acessar esta página.', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca colaboradores para filtros
$stmt_colab = $pdo->query("
    SELECT DISTINCT
        c.id,
        c.nome_completo as nome,
        c.foto,
        e.nome_fantasia as empresa_nome
    FROM colaboradores c
    INNER JOIN setores s ON c.setor_id = s.id
    INNER JOIN empresas e ON s.empresa_id = e.id
    WHERE c.status = 'ativo'
    ORDER BY c.nome_completo
");
$colaboradores = $stmt_colab->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Gestão de Feedbacks';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Gestão de Feedbacks</h1>
            <span class="text-muted fw-semibold fs-7">Visualize e gerencie todos os feedbacks e solicitações do sistema</span>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Tabs-->
<div class="mb-5">
    <div class="container-xxl">
        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-0 fs-4 fw-semibold mb-n2">
            <li class="nav-item">
                <a class="nav-link text-active-primary pb-4 active" data-bs-toggle="tab" href="#tab_feedbacks" id="link_feedbacks">
                    <i class="ki-duotone ki-message-text fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Feedbacks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-active-primary pb-4" data-bs-toggle="tab" href="#tab_solicitacoes" id="link_solicitacoes">
                    <i class="ki-duotone ki-notification-bing fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Solicitações
                </a>
            </li>
        </ul>
    </div>
</div>
<!--end::Tabs-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Tab Content-->
        <div class="tab-content" id="myTabContent">
            
            <!--begin::Tab Feedbacks-->
            <div class="tab-pane fade show active" id="tab_feedbacks" role="tabpanel">
        
        <!--begin::Card - Estatísticas-->
        <div class="card mb-5">
            <div class="card-body pt-6">
                <div class="row g-4" id="stats_container">
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-circle symbol-50px me-3">
                                <div class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-message-text fs-2x text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold text-gray-800" id="stat_total">-</div>
                                <div class="fs-7 text-muted">Total de Feedbacks</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-circle symbol-50px me-3">
                                <div class="symbol-label bg-light-warning">
                                    <i class="ki-duotone ki-shield fs-2x text-warning">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold text-gray-800" id="stat_anonimos">-</div>
                                <div class="fs-7 text-muted">Feedbacks Anônimos</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-circle symbol-50px me-3">
                                <div class="symbol-label bg-light-info">
                                    <i class="ki-duotone ki-people fs-2x text-info">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold text-gray-800" id="stat_presenciais">-</div>
                                <div class="fs-7 text-muted">Feedbacks Presenciais</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-circle symbol-50px me-3">
                                <div class="symbol-label bg-light-success">
                                    <i class="ki-duotone ki-profile-user fs-2x text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold text-gray-800" id="stat_participantes">-</div>
                                <div class="fs-7 text-muted">Participantes Únicos</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Filtros-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Filtros</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Remetente</label>
                        <select class="form-select form-select-solid" id="filtro_remetente">
                            <option value="">Todos</option>
                            <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= $colab['id'] ?>"><?= htmlspecialchars($colab['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Destinatário</label>
                        <select class="form-select form-select-solid" id="filtro_destinatario">
                            <option value="">Todos</option>
                            <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= $colab['id'] ?>"><?= htmlspecialchars($colab['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Data Início</label>
                        <input type="date" class="form-control form-control-solid" id="filtro_data_inicio">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Data Fim</label>
                        <input type="date" class="form-control form-control-solid" id="filtro_data_fim">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Tipo</label>
                        <select class="form-select form-select-solid" id="filtro_anonimo">
                            <option value="">Todos</option>
                            <option value="nao">Não Anônimo</option>
                            <option value="sim">Anônimo</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Presencial</label>
                        <select class="form-select form-select-solid" id="filtro_presencial">
                            <option value="">Todos</option>
                            <option value="nao">Não Presencial</option>
                            <option value="sim">Presencial</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <button type="button" class="btn btn-primary" id="btn_filtrar">
                            <i class="ki-duotone ki-magnifier fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Filtrar
                        </button>
                        <button type="button" class="btn btn-light" id="btn_limpar">
                            <i class="ki-duotone ki-cross fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Limpar Filtros
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Lista de Feedbacks-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Feedbacks</span>
                    <span class="text-muted fw-semibold fs-7" id="total_registros">Carregando...</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div id="feedbacks_container">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
            </div>
            <!--end::Tab Feedbacks-->
            
            <!--begin::Tab Solicitações-->
            <div class="tab-pane fade" id="tab_solicitacoes" role="tabpanel">
                
                <!--begin::Card - Estatísticas Solicitações-->
                <div class="card mb-5">
                    <div class="card-body pt-6">
                        <div class="row g-4" id="stats_solicitacoes_container">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-light-primary">
                                            <i class="ki-duotone ki-notification-bing fs-2x text-primary">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold text-gray-800" id="stat_sol_total">-</div>
                                        <div class="fs-7 text-muted">Total de Solicitações</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-light-warning">
                                            <i class="ki-duotone ki-timer fs-2x text-warning">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold text-gray-800" id="stat_sol_pendentes">-</div>
                                        <div class="fs-7 text-muted">Pendentes</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-light-success">
                                            <i class="ki-duotone ki-check-circle fs-2x text-success">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold text-gray-800" id="stat_sol_aceitas">-</div>
                                        <div class="fs-7 text-muted">Aceitas</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-light-danger">
                                            <i class="ki-duotone ki-cross-circle fs-2x text-danger">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold text-gray-800" id="stat_sol_recusadas">-</div>
                                        <div class="fs-7 text-muted">Recusadas</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-circle symbol-50px me-3">
                                        <div class="symbol-label bg-light-info">
                                            <i class="ki-duotone ki-check-square fs-2x text-info">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fs-4 fw-bold text-gray-800" id="stat_sol_concluidas">-</div>
                                        <div class="fs-7 text-muted">Concluídas</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
                
                <!--begin::Card - Filtros Solicitações-->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Filtros</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Solicitante</label>
                                <select class="form-select form-select-solid" id="filtro_sol_solicitante">
                                    <option value="">Todos</option>
                                    <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= $colab['id'] ?>"><?= htmlspecialchars($colab['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Solicitado</label>
                                <select class="form-select form-select-solid" id="filtro_sol_solicitado">
                                    <option value="">Todos</option>
                                    <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= $colab['id'] ?>"><?= htmlspecialchars($colab['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-select form-select-solid" id="filtro_sol_status">
                                    <option value="">Todos</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="aceita">Aceita</option>
                                    <option value="recusada">Recusada</option>
                                    <option value="concluida">Concluída</option>
                                    <option value="expirada">Expirada</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Data Início</label>
                                <input type="date" class="form-control form-control-solid" id="filtro_sol_data_inicio">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Data Fim</label>
                                <input type="date" class="form-control form-control-solid" id="filtro_sol_data_fim">
                            </div>
                            <div class="col-md-12">
                                <button type="button" class="btn btn-primary" id="btn_filtrar_sol">
                                    <i class="ki-duotone ki-magnifier fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Filtrar
                                </button>
                                <button type="button" class="btn btn-light" id="btn_limpar_sol">
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Limpar Filtros
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
                
                <!--begin::Card - Lista de Solicitações-->
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Solicitações</span>
                            <span class="text-muted fw-semibold fs-7" id="total_registros_sol">Carregando...</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div id="solicitacoes_container">
                            <div class="text-center py-10">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
                
            </div>
            <!--end::Tab Solicitações-->
            
        </div>
        <!--end::Tab Content-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Scripts-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPage = 1;
let filtros = {};

function formatarData(dataStr) {
    const data = new Date(dataStr);
    return data.toLocaleDateString('pt-BR', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function renderizarAvaliacoes(avaliacoes) {
    if (!avaliacoes || avaliacoes.length === 0) {
        return '';
    }
    
    let html = '<div class="d-flex flex-wrap gap-2 mb-3">';
    avaliacoes.forEach(function(av) {
        let estrelas = '';
        for (let i = 1; i <= 5; i++) {
            estrelas += i <= av.nota ? '★' : '☆';
        }
        html += `
            <span class="badge badge-light-primary d-flex align-items-center gap-1">
                <span>${av.item_nome}:</span>
                <span style="color: #ffc700;">${estrelas}</span>
            </span>
        `;
    });
    html += '</div>';
    return html;
}

function renderizarFeedbacks(feedbacks, stats) {
    const container = document.getElementById('feedbacks_container');
    
    // Atualiza estatísticas
    if (stats) {
        document.getElementById('stat_total').textContent = stats.total_feedbacks || 0;
        document.getElementById('stat_anonimos').textContent = stats.total_anonimos || 0;
        document.getElementById('stat_presenciais').textContent = stats.total_presenciais || 0;
        // Participantes únicos (soma de remetentes e destinatários únicos)
        const participantes = (parseInt(stats.total_remetentes || 0) + parseInt(stats.total_destinatarios || 0));
        document.getElementById('stat_participantes').textContent = participantes;
    }
    
    if (!feedbacks || feedbacks.length === 0) {
        container.innerHTML = `
            <div class="text-center py-10">
                <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <p class="text-muted fs-5">Nenhum feedback encontrado com os filtros aplicados.</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="table-responsive">';
    html += '<table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">';
    html += '<thead>';
    html += '<tr class="fw-bold text-muted">';
    html += '<th class="min-w-150px">Remetente</th>';
    html += '<th class="min-w-150px">Destinatário</th>';
    html += '<th class="min-w-200px">Conteúdo</th>';
    html += '<th class="min-w-100px">Avaliações</th>';
    html += '<th class="min-w-100px">Informações</th>';
    html += '<th class="min-w-120px">Data</th>';
    html += '<th class="min-w-100px text-end">Ações</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    feedbacks.forEach(function(feedback) {
        const remetenteFoto = feedback.remetente_foto ? `../${feedback.remetente_foto}` : null;
        const remetenteNome = feedback.anonimo ? 'Anônimo' : (feedback.remetente_nome || 'N/A');
        const remetenteInicial = remetenteNome.charAt(0).toUpperCase();
        
        const destinatarioFoto = feedback.destinatario_foto ? `../${feedback.destinatario_foto}` : null;
        const destinatarioNome = feedback.destinatario_nome || 'N/A';
        const destinatarioInicial = destinatarioNome.charAt(0).toUpperCase();
        
        const conteudoPreview = feedback.conteudo.length > 80 ? feedback.conteudo.substring(0, 80) + '...' : feedback.conteudo;
        
        html += '<tr>';
        
        // Remetente
        html += '<td>';
        html += '<div class="d-flex align-items-center">';
        html += '<div class="symbol symbol-circle symbol-40px me-3">';
        if (remetenteFoto && !feedback.anonimo) {
            html += `<img src="${remetenteFoto}" alt="${remetenteNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-6 fw-bold bg-light text-primary\\'>${remetenteInicial}</div>';">`;
        } else {
            html += `<div class="symbol-label fs-6 fw-bold bg-light text-primary">${remetenteInicial}</div>`;
        }
        html += '</div>';
        html += `<div class="d-flex flex-column">`;
        html += `<span class="text-gray-800 fw-bold fs-6">${remetenteNome}</span>`;
        if (feedback.remetente_email && !feedback.anonimo) {
            html += `<span class="text-muted fs-7">${feedback.remetente_email}</span>`;
        }
        html += '</div>';
        html += '</div>';
        html += '</td>';
        
        // Destinatário
        html += '<td>';
        html += '<div class="d-flex align-items-center">';
        html += '<div class="symbol symbol-circle symbol-40px me-3">';
        if (destinatarioFoto) {
            html += `<img src="${destinatarioFoto}" alt="${destinatarioNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-6 fw-bold bg-light text-primary\\'>${destinatarioInicial}</div>';">`;
        } else {
            html += `<div class="symbol-label fs-6 fw-bold bg-light text-primary">${destinatarioInicial}</div>`;
        }
        html += '</div>';
        html += `<div class="d-flex flex-column">`;
        html += `<span class="text-gray-800 fw-bold fs-6">${destinatarioNome}</span>`;
        if (feedback.destinatario_email) {
            html += `<span class="text-muted fs-7">${feedback.destinatario_email}</span>`;
        }
        html += '</div>';
        html += '</div>';
        html += '</td>';
        
        // Conteúdo
        html += '<td>';
        html += `<div class="text-gray-700 fs-6" title="${feedback.conteudo.replace(/"/g, '&quot;')}">${conteudoPreview.replace(/\n/g, '<br>')}</div>`;
        html += '</td>';
        
        // Avaliações
        html += '<td>';
        const avaliacoesHtml = renderizarAvaliacoes(feedback.avaliacoes);
        if (avaliacoesHtml) {
            html += avaliacoesHtml;
        } else {
            html += '<span class="text-muted fs-7">Nenhuma</span>';
        }
        html += '</td>';
        
        // Informações
        html += '<td>';
        html += '<div class="d-flex flex-column gap-1">';
        if (feedback.anonimo) {
            html += '<span class="badge badge-light-warning">Anônimo</span>';
        }
        if (feedback.presencial) {
            html += '<span class="badge badge-light-info">Presencial</span>';
        }
        if (feedback.respostas && feedback.respostas.length > 0) {
            html += `<span class="badge badge-light-success">${feedback.respostas.length} resposta(s)</span>`;
        }
        html += '</div>';
        html += '</td>';
        
        // Data
        html += '<td>';
        html += `<span class="text-gray-800 fw-semibold fs-7">${formatarData(feedback.created_at)}</span>`;
        html += '</td>';
        
        // Ações
        html += '<td class="text-end">';
        html += `<a href="ver_feedback.php?id=${feedback.id}" class="btn btn-sm btn-light-primary" target="_blank" title="Ver detalhes do feedback">`;
        html += '<i class="ki-duotone ki-eye fs-5">';
        html += '<span class="path1"></span>';
        html += '<span class="path2"></span>';
        html += '<span class="path3"></span>';
        html += '</i>';
        html += ' Ver';
        html += '</a>';
        html += '</td>';
        
        html += '</tr>';
    });
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    
    container.innerHTML = html;
}

function carregarFeedbacks() {
    const container = document.getElementById('feedbacks_container');
    container.innerHTML = `
        <div class="text-center py-10">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    `;
    
    const params = new URLSearchParams({
        page: currentPage,
        ...filtros
    });
    
    fetch(`../api/feedback/gestao.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarFeedbacks(data.data, data.stats);
                document.getElementById('total_registros').textContent = `${data.total} feedback(s) encontrado(s)`;
            } else {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            container.innerHTML = `
                <div class="alert alert-danger">
                    Erro ao carregar feedbacks.
                </div>
            `;
        });
}

// ========== SOLICITAÇÕES ==========
let currentPageSol = 1;
let filtrosSol = {};

function getStatusBadge(status) {
    const badges = {
        'pendente': '<span class="badge badge-warning">Pendente</span>',
        'aceita': '<span class="badge badge-success">Aceita</span>',
        'recusada': '<span class="badge badge-danger">Recusada</span>',
        'concluida': '<span class="badge badge-primary">Concluída</span>',
        'expirada': '<span class="badge badge-secondary">Expirada</span>'
    };
    return badges[status] || status;
}

function renderizarSolicitacoes(solicitacoes, stats) {
    const container = document.getElementById('solicitacoes_container');
    
    // Atualiza estatísticas
    if (stats) {
        document.getElementById('stat_sol_total').textContent = stats.total_solicitacoes || 0;
        document.getElementById('stat_sol_pendentes').textContent = stats.total_pendentes || 0;
        document.getElementById('stat_sol_aceitas').textContent = stats.total_aceitas || 0;
        document.getElementById('stat_sol_recusadas').textContent = stats.total_recusadas || 0;
        document.getElementById('stat_sol_concluidas').textContent = stats.total_concluidas || 0;
    }
    
    if (!solicitacoes || solicitacoes.length === 0) {
        container.innerHTML = `
            <div class="text-center py-10">
                <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <p class="text-muted fs-5">Nenhuma solicitação encontrada com os filtros aplicados.</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="table-responsive">';
    html += '<table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">';
    html += '<thead>';
    html += '<tr class="fw-bold text-muted">';
    html += '<th class="min-w-150px">Solicitante</th>';
    html += '<th class="min-w-150px">Solicitado</th>';
    html += '<th class="min-w-200px">Mensagem</th>';
    html += '<th class="min-w-100px">Status</th>';
    html += '<th class="min-w-100px">Prazo</th>';
    html += '<th class="min-w-120px">Data Solicitação</th>';
    html += '<th class="min-w-120px">Data Resposta</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    solicitacoes.forEach(function(sol) {
        const solicitanteFoto = sol.solicitante_foto ? `../${sol.solicitante_foto}` : null;
        const solicitanteNome = sol.solicitante_nome || 'N/A';
        const solicitanteInicial = solicitanteNome.charAt(0).toUpperCase();
        
        const solicitadoFoto = sol.solicitado_foto ? `../${sol.solicitado_foto}` : null;
        const solicitadoNome = sol.solicitado_nome || 'N/A';
        const solicitadoInicial = solicitadoNome.charAt(0).toUpperCase();
        
        const mensagemPreview = sol.mensagem ? (sol.mensagem.length > 60 ? sol.mensagem.substring(0, 60) + '...' : sol.mensagem) : '<span class="text-muted">Sem mensagem</span>';
        
        html += '<tr>';
        
        // Solicitante
        html += '<td>';
        html += '<div class="d-flex align-items-center">';
        html += '<div class="symbol symbol-circle symbol-40px me-3">';
        if (solicitanteFoto) {
            html += `<img src="${solicitanteFoto}" alt="${solicitanteNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-6 fw-bold bg-light text-primary\\'>${solicitanteInicial}</div>';">`;
        } else {
            html += `<div class="symbol-label fs-6 fw-bold bg-light text-primary">${solicitanteInicial}</div>`;
        }
        html += '</div>';
        html += `<div class="d-flex flex-column">`;
        html += `<span class="text-gray-800 fw-bold fs-6">${solicitanteNome}</span>`;
        if (sol.solicitante_email) {
            html += `<span class="text-muted fs-7">${sol.solicitante_email}</span>`;
        }
        html += '</div>';
        html += '</div>';
        html += '</td>';
        
        // Solicitado
        html += '<td>';
        html += '<div class="d-flex align-items-center">';
        html += '<div class="symbol symbol-circle symbol-40px me-3">';
        if (solicitadoFoto) {
            html += `<img src="${solicitadoFoto}" alt="${solicitadoNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-6 fw-bold bg-light text-primary\\'>${solicitadoInicial}</div>';">`;
        } else {
            html += `<div class="symbol-label fs-6 fw-bold bg-light text-primary">${solicitadoInicial}</div>`;
        }
        html += '</div>';
        html += `<div class="d-flex flex-column">`;
        html += `<span class="text-gray-800 fw-bold fs-6">${solicitadoNome}</span>`;
        if (sol.solicitado_email) {
            html += `<span class="text-muted fs-7">${sol.solicitado_email}</span>`;
        }
        html += '</div>';
        html += '</div>';
        html += '</td>';
        
        // Mensagem
        html += '<td>';
        html += `<div class="text-gray-700 fs-6" title="${sol.mensagem ? sol.mensagem.replace(/"/g, '&quot;') : ''}">${mensagemPreview}</div>`;
        if (sol.resposta_mensagem) {
            html += `<div class="text-muted fs-7 mt-1"><strong>Resposta:</strong> ${sol.resposta_mensagem.substring(0, 40)}${sol.resposta_mensagem.length > 40 ? '...' : ''}</div>`;
        }
        html += '</td>';
        
        // Status
        html += '<td>';
        html += getStatusBadge(sol.status);
        if (sol.status === 'concluida' && sol.feedback_id) {
            html += `<br><a href="ver_feedback.php?id=${sol.feedback_id}" class="text-primary fs-7" target="_blank">Ver Feedback</a>`;
        }
        html += '</td>';
        
        // Prazo
        html += '<td>';
        if (sol.prazo) {
            const prazo = new Date(sol.prazo + 'T00:00:00');
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            const isPrazoVencido = prazo < hoje && sol.status === 'pendente';
            html += `<span class="${isPrazoVencido ? 'text-danger fw-bold' : 'text-gray-700'}">${prazo.toLocaleDateString('pt-BR')}</span>`;
            if (isPrazoVencido) {
                html += '<br><span class="badge badge-danger">Vencido</span>';
            }
        } else {
            html += '<span class="text-muted">Sem prazo</span>';
        }
        html += '</td>';
        
        // Data Solicitação
        html += '<td>';
        html += `<span class="text-gray-800 fw-semibold fs-7">${formatarData(sol.created_at)}</span>`;
        html += '</td>';
        
        // Data Resposta
        html += '<td>';
        if (sol.respondida_at) {
            html += `<span class="text-gray-800 fw-semibold fs-7">${formatarData(sol.respondida_at)}</span>`;
        } else {
            html += '<span class="text-muted">-</span>';
        }
        html += '</td>';
        
        html += '</tr>';
    });
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    
    container.innerHTML = html;
}

function carregarSolicitacoes() {
    const container = document.getElementById('solicitacoes_container');
    container.innerHTML = `
        <div class="text-center py-10">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    `;
    
    const params = new URLSearchParams({
        page: currentPageSol,
        ...filtrosSol
    });
    
    fetch(`../api/feedback/gestao_solicitacoes.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarSolicitacoes(data.data, data.stats);
                document.getElementById('total_registros_sol').textContent = `${data.total} solicitação(ões) encontrada(s)`;
            } else {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            container.innerHTML = `
                <div class="alert alert-danger">
                    Erro ao carregar solicitações.
                </div>
            `;
        });
}

document.addEventListener('DOMContentLoaded', function() {
    // Carrega feedbacks iniciais
    carregarFeedbacks();
    
    // Botão filtrar feedbacks
    document.getElementById('btn_filtrar').addEventListener('click', function() {
        filtros = {};
        
        const remetente = document.getElementById('filtro_remetente').value;
        const destinatario = document.getElementById('filtro_destinatario').value;
        const dataInicio = document.getElementById('filtro_data_inicio').value;
        const dataFim = document.getElementById('filtro_data_fim').value;
        const anonimo = document.getElementById('filtro_anonimo').value;
        const presencial = document.getElementById('filtro_presencial').value;
        
        if (remetente) filtros.remetente_id = remetente;
        if (destinatario) filtros.destinatario_id = destinatario;
        if (dataInicio) filtros.data_inicio = dataInicio;
        if (dataFim) filtros.data_fim = dataFim;
        if (anonimo) filtros.anonimo = anonimo;
        if (presencial) filtros.presencial = presencial;
        
        currentPage = 1;
        carregarFeedbacks();
    });
    
    // Botão limpar feedbacks
    document.getElementById('btn_limpar').addEventListener('click', function() {
        document.getElementById('filtro_remetente').value = '';
        document.getElementById('filtro_destinatario').value = '';
        document.getElementById('filtro_data_inicio').value = '';
        document.getElementById('filtro_data_fim').value = '';
        document.getElementById('filtro_anonimo').value = '';
        document.getElementById('filtro_presencial').value = '';
        
        filtros = {};
        currentPage = 1;
        carregarFeedbacks();
    });
    
    // Botão filtrar solicitações
    document.getElementById('btn_filtrar_sol').addEventListener('click', function() {
        filtrosSol = {};
        
        const solicitante = document.getElementById('filtro_sol_solicitante').value;
        const solicitado = document.getElementById('filtro_sol_solicitado').value;
        const status = document.getElementById('filtro_sol_status').value;
        const dataInicio = document.getElementById('filtro_sol_data_inicio').value;
        const dataFim = document.getElementById('filtro_sol_data_fim').value;
        
        if (solicitante) filtrosSol.solicitante_id = solicitante;
        if (solicitado) filtrosSol.solicitado_id = solicitado;
        if (status) filtrosSol.status = status;
        if (dataInicio) filtrosSol.data_inicio = dataInicio;
        if (dataFim) filtrosSol.data_fim = dataFim;
        
        currentPageSol = 1;
        carregarSolicitacoes();
    });
    
    // Botão limpar solicitações
    document.getElementById('btn_limpar_sol').addEventListener('click', function() {
        document.getElementById('filtro_sol_solicitante').value = '';
        document.getElementById('filtro_sol_solicitado').value = '';
        document.getElementById('filtro_sol_status').value = '';
        document.getElementById('filtro_sol_data_inicio').value = '';
        document.getElementById('filtro_sol_data_fim').value = '';
        
        filtrosSol = {};
        currentPageSol = 1;
        carregarSolicitacoes();
    });
    
    // Ao trocar de aba, carrega dados
    document.getElementById('link_solicitacoes').addEventListener('shown.bs.tab', function() {
        carregarSolicitacoes();
    });
});
</script>
<!--end::Scripts-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

