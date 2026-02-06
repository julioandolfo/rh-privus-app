<?php
/**
 * Solicitar Feedback - Permite colaboradores solicitarem feedback de outros
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca colaboradores disponíveis (todos menos o próprio usuário)
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);

$page_title = 'Solicitar Feedback';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Solicitar Feedback</h1>
            <span class="text-muted fw-semibold fs-7">Peça para alguém enviar um feedback sobre você</span>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="feedback_meus.php" class="btn btn-light">
                <i class="ki-duotone ki-arrow-left fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Voltar
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
            <div class="card-body pt-6">
                
                <!--begin::Explicação-->
                <div class="alert alert-primary d-flex align-items-center mb-7">
                    <i class="ki-duotone ki-information-5 fs-2x text-primary me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Como funciona?</h4>
                        <span class="fs-6">Você pode solicitar feedback de qualquer colaborador, gestor, RH ou administrador. A pessoa receberá uma notificação e poderá aceitar ou recusar sua solicitação. Se aceitar, ela enviará um feedback sobre você.</span>
                    </div>
                </div>
                <!--end::Explicação-->
                
                <form id="kt_form_solicitar" method="POST" class="form">
                    <!--begin::Seleção de Colaborador-->
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Para quem você quer solicitar feedback?</label>
                        <p class="text-muted fs-7 mb-3">Selecione um colaborador, gestor, RH ou administrador</p>
                        <?= render_select_colaborador('solicitado_colaborador_id', 'solicitado_colaborador_id', null, $colaboradores, true) ?>
                    </div>
                    <!--end::Seleção de Colaborador-->
                    
                    <!--begin::Mensagem-->
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Mensagem (opcional)</label>
                        <p class="text-muted fs-7 mb-3">Explique por que está solicitando este feedback ou sobre o que gostaria de receber feedback</p>
                        <textarea name="mensagem" id="mensagem_solicitacao" class="form-control form-control-solid" rows="5" placeholder="Exemplo: Gostaria de receber um feedback sobre minha atuação no projeto X que trabalhamos juntos recentemente. Quero entender como posso melhorar minha comunicação e trabalho em equipe."></textarea>
                    </div>
                    <!--end::Mensagem-->
                    
                    <!--begin::Prazo-->
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Prazo (opcional)</label>
                        <p class="text-muted fs-7 mb-3">Defina uma data limite para resposta (opcional)</p>
                        <input type="date" name="prazo" class="form-control form-control-solid" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>">
                    </div>
                    <!--end::Prazo-->
                    
                    <!--begin::Actions-->
                    <div class="d-flex justify-content-end gap-3">
                        <a href="feedback_meus.php" class="btn btn-light">Cancelar</a>
                        <button type="submit" id="kt_submit_solicitacao" class="btn btn-primary">
                            <span class="indicator-label">Enviar Solicitação</span>
                            <span class="indicator-progress">Enviando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                    <!--end::Actions-->
                </form>
            </div>
        </div>
        <!--end::Card-->
    </div>
</div>
<!--end::Post-->

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
        display: flex !important;
        align-items: center !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
    }
</style>
<!--end::Select2 CSS-->

<!--begin::Scripts-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/select-colaborador.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('kt_form_solicitar');
    const submitBtn = document.getElementById('kt_submit_solicitacao');
    let isSubmitting = false;
    let lastSubmitTime = 0;
    
    if (form && submitBtn) {
        // Remove event listeners existentes
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        const cleanForm = document.getElementById('kt_form_solicitar');
        const cleanBtn = document.getElementById('kt_submit_solicitacao');
        
        // Reinicializa Select2 após clone
        if (typeof window.initSelectColaboradorManual === 'function') {
            setTimeout(function() {
                window.initSelectColaboradorManual();
            }, 100);
        }
        
        cleanForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const now = Date.now();
            const timeSinceLastSubmit = now - lastSubmitTime;
            
            // Previne múltiplos envios
            if (isSubmitting) {
                console.warn('Bloqueado: Já está enviando');
                return false;
            }
            
            // Previne submissões muito rápidas (menos de 3 segundos)
            if (lastSubmitTime > 0 && timeSinceLastSubmit < 3000) {
                console.warn('Bloqueado: Aguarde antes de enviar novamente');
                return false;
            }
            
            // Validação
            const solicitadoId = document.querySelector('[name="solicitado_colaborador_id"]').value;
            if (!solicitadoId) {
                Swal.fire({
                    text: 'Por favor, selecione para quem você quer solicitar feedback',
                    icon: "warning",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
                return false;
            }
            
            // Marca como enviando IMEDIATAMENTE
            isSubmitting = true;
            lastSubmitTime = now;
            cleanBtn.setAttribute('data-kt-indicator', 'on');
            cleanBtn.disabled = true;
            
            const formData = new FormData(cleanForm);
            
            // Adiciona timestamp único para evitar duplicação
            const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            formData.append('request_id', requestId);
            
            fetch('../api/feedback/solicitar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostra toast de pontos se ganhou
                    if (data.pontos_ganhos && window.processarRespostaPontos) {
                        window.processarRespostaPontos(data, 'solicitar_feedback');
                    }
                    
                    Swal.fire({
                        text: data.message,
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    }).then(function() {
                        window.location.href = 'feedback_solicitacoes.php?tipo=enviadas';
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
                    // Libera apenas em caso de erro
                    isSubmitting = false;
                    cleanBtn.removeAttribute('data-kt-indicator');
                    cleanBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    text: 'Erro ao enviar solicitação de feedback',
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
                // Libera apenas em caso de erro
                isSubmitting = false;
                cleanBtn.removeAttribute('data-kt-indicator');
                cleanBtn.disabled = false;
            });
        });
        
        // Proteção adicional no clique do botão
        cleanBtn.addEventListener('click', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, true);
    }
});
</script>
<!--end::Scripts-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
