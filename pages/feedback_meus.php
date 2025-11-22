<?php
/**
 * Meus Feedbacks - Visualizar Feedbacks Enviados e Recebidos
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('feedback_meus.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$tipo_filtro = $_GET['tipo'] ?? 'todos'; // 'enviados', 'recebidos', 'todos'

$page_title = 'Meus Feedbacks';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Meus Feedbacks</h1>
            <span class="text-muted fw-semibold fs-7">Visualize feedbacks enviados e recebidos</span>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="feedback_enviar.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Enviar Feedback
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
                        <a href="?tipo=todos" class="btn btn-sm btn-light <?= $tipo_filtro === 'todos' ? 'active' : '' ?>">
                            Todos
                        </a>
                        <a href="?tipo=enviados" class="btn btn-sm btn-light <?= $tipo_filtro === 'enviados' ? 'active' : '' ?>">
                            Enviados
                        </a>
                        <a href="?tipo=recebidos" class="btn btn-sm btn-light <?= $tipo_filtro === 'recebidos' ? 'active' : '' ?>">
                            Recebidos
                        </a>
                    </div>
                </div>
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
</div>
<!--end::Post-->

<!--begin::Scripts-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPage = 1;
const tipoFiltro = '<?= $tipo_filtro ?>';

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

function renderizarAvaliacoes(avaliacoes) {
    if (!avaliacoes || avaliacoes.length === 0) {
        return '';
    }
    
    let html = '<div class="d-flex flex-wrap gap-3 mb-4">';
    avaliacoes.forEach(function(av) {
        let estrelas = '';
        for (let i = 1; i <= 5; i++) {
            estrelas += i <= av.nota ? '★' : '☆';
        }
        html += `
            <div class="badge badge-light-primary d-flex align-items-center gap-2">
                <span>${av.item_nome}:</span>
                <span style="color: #ffc700;">${estrelas}</span>
            </div>
        `;
    });
    html += '</div>';
    return html;
}

function renderizarRespostas(respostas, feedbackId) {
    if (!respostas || respostas.length === 0) {
        return '';
    }
    
    let html = '<div class="mt-4 border-top pt-4">';
    html += '<h6 class="fw-bold mb-3">Respostas:</h6>';
    
    respostas.forEach(function(resp) {
        const autorFoto = resp.autor_foto ? `../${resp.autor_foto}` : null;
        const autorNome = resp.autor_nome || 'Usuário';
        const inicial = autorNome.charAt(0).toUpperCase();
        
        html += `
            <div class="d-flex gap-3 mb-4">
                <div class="symbol symbol-circle symbol-40px">
                    ${autorFoto ? 
                        `<img src="${autorFoto}" alt="${autorNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-6 fw-bold bg-light text-primary\\'>${inicial}</div>';">` : 
                        `<div class="symbol-label fs-6 fw-bold bg-light text-primary">${inicial}</div>`
                    }
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-1">
                        <span class="fw-bold text-gray-800">${autorNome}</span>
                        <span class="text-muted ms-2 fs-7">${formatarData(resp.created_at)}</span>
                    </div>
                    <div class="text-gray-700">${resp.resposta.replace(/\n/g, '<br>')}</div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    return html;
}

function renderizarFeedbacks(feedbacks) {
    const container = document.getElementById('feedbacks_container');
    
    if (!feedbacks || feedbacks.length === 0) {
        container.innerHTML = `
            <div class="text-center py-10">
                <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <p class="text-muted fs-5">Nenhum feedback encontrado.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    feedbacks.forEach(function(feedback) {
        const isRemetente = feedback.tipo_relacao === 'remetente';
        const remetenteFoto = feedback.remetente_foto ? `../${feedback.remetente_foto}` : null;
        const remetenteNome = feedback.remetente_nome || 'Usuário';
        const remetenteInicial = remetenteNome.charAt(0).toUpperCase();
        
        const destinatarioFoto = feedback.destinatario_foto ? `../${feedback.destinatario_foto}` : null;
        const destinatarioNome = feedback.destinatario_nome || 'Usuário';
        const destinatarioInicial = destinatarioNome.charAt(0).toUpperCase();
        
        // Limita o conteúdo para preview (primeiras 200 caracteres)
        const conteudoPreview = feedback.conteudo.length > 200 ? feedback.conteudo.substring(0, 200) + '...' : feedback.conteudo;
        const temMaisConteudo = feedback.conteudo.length > 200;
        
        html += `
            <div class="card card-flush mb-5" data-feedback-id="${feedback.id}">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-5 mb-5">
                        <div class="symbol symbol-circle symbol-50px">
                            ${isRemetente ? 
                                (destinatarioFoto ? 
                                    `<img src="${destinatarioFoto}" alt="${destinatarioNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-5 fw-bold bg-light text-primary\\'>${destinatarioInicial}</div>';">` : 
                                    `<div class="symbol-label fs-5 fw-bold bg-light text-primary">${destinatarioInicial}</div>`
                                ) :
                                (remetenteFoto ? 
                                    `<img src="${remetenteFoto}" alt="${remetenteNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-5 fw-bold bg-light text-primary\\'>${remetenteInicial}</div>';">` : 
                                    `<div class="symbol-label fs-5 fw-bold bg-light text-primary">${remetenteInicial}</div>`
                                )
                            }
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <span class="fw-bold text-gray-800">${isRemetente ? 'Para: ' + destinatarioNome : 'De: ' + remetenteNome}</span>
                                    ${feedback.presencial ? '<span class="badge badge-light-info ms-2">Presencial</span>' : ''}
                                    ${feedback.anonimo && !isRemetente ? '<span class="badge badge-light-warning ms-2">Anônimo</span>' : ''}
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted fs-7">${formatarData(feedback.created_at)}</span>
                                    <a href="ver_feedback.php?id=${feedback.id}" class="btn btn-sm btn-light-primary">
                                        <i class="ki-duotone ki-eye fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Ver detalhes
                                    </a>
                                </div>
                            </div>
                            ${renderizarAvaliacoes(feedback.avaliacoes)}
                            <div class="text-gray-700 mb-3">${conteudoPreview.replace(/\n/g, '<br>')}</div>
                            ${feedback.respostas && feedback.respostas.length > 0 ? `
                                <div class="mb-3">
                                    <span class="badge badge-light-info">${feedback.respostas.length} resposta(s)</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Adiciona listeners para formulários de resposta com proteção anti-duplicação
    const respostasEnviando = new Set();
    
    document.querySelectorAll('.form-resposta').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            const feedbackId = this.getAttribute('data-feedback-id');
            const resposta = this.querySelector('[name="resposta"]').value.trim();
            const btn = this.querySelector('button[type="submit"]');
            
            if (!resposta) {
                return;
            }
            
            // Previne múltiplos envios da mesma resposta
            const respostaKey = `${feedbackId}-${resposta}`;
            if (respostasEnviando.has(respostaKey)) {
                console.warn('Bloqueado: Resposta já está sendo enviada');
                return;
            }
            
            // Marca como enviando
            respostasEnviando.add(respostaKey);
            if (btn) {
                btn.disabled = true;
                const btnOriginal = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            }
            
            const formData = new FormData();
            formData.append('feedback_id', feedbackId);
            formData.append('resposta', resposta);
            
            // Adiciona request_id único
            const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            formData.append('request_id', requestId);
            
            console.log('Enviando resposta... Request ID:', requestId);
            
            fetch('../api/feedback/responder.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        text: data.message,
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    }).then(function() {
                        carregarFeedbacks();
                    });
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
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    text: 'Erro ao responder feedback',
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
            })
            .finally(() => {
                // Libera após delay
                setTimeout(() => {
                    respostasEnviando.delete(respostaKey);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = btn.innerHTML.includes('spinner') ? 'Enviar' : btn.innerHTML;
                    }
                }, 2000);
            });
        });
    });
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
    
    fetch(`../api/feedback/listar.php?tipo=${tipoFiltro}&page=${currentPage}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarFeedbacks(data.data);
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

document.addEventListener('DOMContentLoaded', function() {
    carregarFeedbacks();
});
</script>
<!--end::Scripts-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

