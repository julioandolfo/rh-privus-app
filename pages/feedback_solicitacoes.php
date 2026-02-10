<?php
/**
 * Minhas Solicitações de Feedback - Visualizar solicitações enviadas e recebidas
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$tipo_filtro = $_GET['tipo'] ?? 'recebidas'; // 'enviadas', 'recebidas'

$page_title = 'Solicitações de Feedback';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Solicitações de Feedback</h1>
            <span class="text-muted fw-semibold fs-7">Gerencie suas solicitações de feedback</span>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="feedback_solicitar.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Solicitar Feedback
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        <!--begin::Card-->
        <div class="card card-flush">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="btn-group" role="group">
                        <a href="?tipo=recebidas" class="btn btn-sm btn-light <?= $tipo_filtro === 'recebidas' ? 'active' : '' ?>">
                            <i class="ki-duotone ki-sms fs-4 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Recebidas
                        </a>
                        <a href="?tipo=enviadas" class="btn btn-sm btn-light <?= $tipo_filtro === 'enviadas' ? 'active' : '' ?>">
                            <i class="ki-duotone ki-send fs-4 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Enviadas
                        </a>
                    </div>
                </div>
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
</div>
<!--end::Post-->

<!--begin::Modal Responder Solicitação-->
<div class="modal fade" id="modal_responder" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_title">Responder Solicitação</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="form_responder_solicitacao">
                    <input type="hidden" name="solicitacao_id" id="solicitacao_id">
                    <input type="hidden" name="acao" id="acao_resposta">
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Mensagem (opcional)</label>
                        <textarea name="mensagem" id="mensagem_resposta" class="form-control form-control-solid" rows="4" placeholder="Digite uma mensagem explicando sua decisão..."></textarea>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btn_confirmar_resposta">
                            <span class="indicator-label">Confirmar</span>
                            <span class="indicator-progress">Enviando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Scripts-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const tipoFiltro = '<?= $tipo_filtro ?>';
let currentPage = 1;

function formatarData(dataStr) {
    const data = new Date(dataStr);
    const hoje = new Date();
    const ontem = new Date(hoje);
    ontem.setDate(ontem.getDate() - 1);
    
    if (data.toDateString() === hoje.toDateString()) {
        return 'Hoje às ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } else if (data.toDateString() === ontem.toDateString()) {
        return 'Ontem às ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } else {
        return data.toLocaleDateString('pt-BR', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

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

function renderizarSolicitacoes(solicitacoes) {
    const container = document.getElementById('solicitacoes_container');
    
    if (!solicitacoes || solicitacoes.length === 0) {
        container.innerHTML = `
            <div class="text-center py-10">
                <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <p class="text-muted fs-5">Nenhuma solicitação encontrada.</p>
                ${tipoFiltro === 'recebidas' ? '<a href="?tipo=enviadas" class="btn btn-sm btn-light-primary mt-3">Ver Solicitações Enviadas</a>' : '<a href="feedback_solicitar.php" class="btn btn-sm btn-primary mt-3">Solicitar Feedback</a>'}
            </div>
        `;
        return;
    }
    
    let html = '';
    
    solicitacoes.forEach(function(sol) {
        const isRecebida = tipoFiltro === 'recebidas';
        const foto = isRecebida ? sol.solicitante_foto : sol.solicitado_foto;
        const nome = isRecebida ? sol.solicitante_nome : sol.solicitado_nome;
        const inicial = nome ? nome.charAt(0).toUpperCase() : 'U';
        const prazoFormatado = sol.prazo ? new Date(sol.prazo).toLocaleDateString('pt-BR') : null;
        
        html += `
            <div class="card card-flush mb-5">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-5">
                        <div class="symbol symbol-circle symbol-50px">
                            ${foto ? 
                                `<img src="../${foto}" alt="${nome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-5 fw-bold bg-light text-primary\\'>${inicial}</div>';">` : 
                                `<div class="symbol-label fs-5 fw-bold bg-light text-primary">${inicial}</div>`
                            }
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <span class="fw-bold text-gray-800 fs-5">${isRecebida ? 'De: ' : 'Para: '}${nome}</span>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        ${getStatusBadge(sol.status)}
                                        ${prazoFormatado ? `<span class="badge badge-light-info">Prazo: ${prazoFormatado}</span>` : ''}
                                        <span class="text-muted fs-7">${formatarData(sol.created_at)}</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    ${isRecebida && sol.status === 'pendente' ? `
                                        <button class="btn btn-sm btn-light-success" onclick="responderSolicitacao(${sol.id}, 'aceitar')">
                                            <i class="ki-duotone ki-check fs-5"></i>
                                            Aceitar
                                        </button>
                                        <button class="btn btn-sm btn-light-danger" onclick="responderSolicitacao(${sol.id}, 'recusar')">
                                            <i class="ki-duotone ki-cross fs-5"></i>
                                            Recusar
                                        </button>
                                    ` : ''}
                                    ${sol.status === 'aceita' && sol.feedback_id ? `
                                        <a href="ver_feedback.php?id=${sol.feedback_id}" class="btn btn-sm btn-light-primary">
                                            <i class="ki-duotone ki-eye fs-5">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Ver Feedback
                                        </a>
                                    ` : ''}
                                </div>
                            </div>
                            
                            ${sol.mensagem ? `
                                <div class="mb-3">
                                    <span class="fw-semibold text-gray-700">Mensagem:</span>
                                    <p class="text-gray-600 mb-0 mt-1">${sol.mensagem}</p>
                                </div>
                            ` : ''}
                            
                            ${sol.resposta_mensagem ? `
                                <div class="p-3 bg-light rounded">
                                    <span class="fw-semibold text-gray-700">Resposta:</span>
                                    <p class="text-gray-600 mb-0 mt-1">${sol.resposta_mensagem}</p>
                                    ${sol.respondida_at ? `<span class="text-muted fs-7">${formatarData(sol.respondida_at)}</span>` : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
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
    
    fetch(`../api/feedback/listar_solicitacoes.php?tipo=${tipoFiltro}&page=${currentPage}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarSolicitacoes(data.data);
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

function responderSolicitacao(solicitacaoId, acao) {
    document.getElementById('solicitacao_id').value = solicitacaoId;
    document.getElementById('acao_resposta').value = acao;
    document.getElementById('modal_title').textContent = acao === 'aceitar' ? 'Aceitar Solicitação' : 'Recusar Solicitação';
    document.getElementById('mensagem_resposta').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('modal_responder'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    carregarSolicitacoes();
    
    // Form de responder solicitação
    const formResponder = document.getElementById('form_responder_solicitacao');
    const btnConfirmar = document.getElementById('btn_confirmar_resposta');
    let isSubmitting = false;
    
    if (formResponder) {
        formResponder.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (isSubmitting) return;
            
            isSubmitting = true;
            btnConfirmar.setAttribute('data-kt-indicator', 'on');
            btnConfirmar.disabled = true;
            
            const formData = new FormData(formResponder);
            
            fetch('../api/feedback/responder_solicitacao.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostra toast de pontos se ganhou
                    if (data.pontos_ganhos && window.processarRespostaPontos) {
                        window.processarRespostaPontos(data, 'responder_solicitacao_feedback');
                    }
                    
                    // Se tiver redirect (aceitar solicitação), redireciona após o SweetAlert
                    if (data.redirect) {
                        Swal.fire({
                            text: data.message,
                            icon: "success",
                            buttonsStyling: false,
                            confirmButtonText: "Ok, enviar feedback agora",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        }).then(function() {
                            window.location.href = data.redirect;
                        });
                    } else {
                        // Apenas recarrega a lista se não tiver redirect (recusar solicitação)
                        Swal.fire({
                            text: data.message,
                            icon: "success",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        }).then(function() {
                            bootstrap.Modal.getInstance(document.getElementById('modal_responder')).hide();
                            carregarSolicitacoes();
                        });
                    }
                } else {
                    Swal.fire({
                        text: data.message,
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                }
                isSubmitting = false;
                btnConfirmar.removeAttribute('data-kt-indicator');
                btnConfirmar.disabled = false;
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    text: 'Erro ao responder solicitação',
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
                isSubmitting = false;
                btnConfirmar.removeAttribute('data-kt-indicator');
                btnConfirmar.disabled = false;
            });
        });
    }
});
</script>
<!--end::Scripts-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
