<?php
/**
 * Enviar Feedback - Sistema de Feedbacks
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';

require_page_permission('feedback_enviar.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca colaboradores disponíveis
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);

// Verifica se veio de uma solicitação aceita
$destinatario_pre_selecionado = $_GET['destinatario_id'] ?? null;

// Busca itens de avaliação ativos (sem duplicatas)
$stmt_itens = $pdo->query("
    SELECT DISTINCT id, nome, descricao, ordem 
    FROM feedback_itens 
    WHERE status = 'ativo' 
    ORDER BY ordem, nome
");
$itens_raw = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

// Remove duplicatas por ID
$itens_avaliacao = [];
$ids_vistos = [];
foreach ($itens_raw as $item) {
    if (!in_array($item['id'], $ids_vistos)) {
        $itens_avaliacao[] = $item;
        $ids_vistos[] = $item['id'];
    }
}

// Templates de feedback
$templates = [
    0 => 'Nenhum modelo',
    1 => 'Modelo: parar / continuar / começar',
    2 => 'Modelo: SCI - Situação/Comportamento/Impacto',
    3 => 'Modelo: Comunicação Não Violenta',
    4 => 'Modelo: 1 on 1',
    5 => 'Modelo: ótimas ideias',
    6 => 'Modelo: boa reunião',
    7 => 'Modelo: claro e direto',
    14 => 'Modelo: falha prazos'
];

$page_title = 'Enviar Feedback';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Enviar Feedback</h1>
            <span class="text-muted fw-semibold fs-7">Selecione um colaborador para enviar um feedback sobre desempenho</span>
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
                <form id="kt_form_feedback" method="POST" class="form">
                    <!--begin::Seleção de Colaborador e Anônimo-->
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Selecione um colaborador</label>
                            <?= render_select_colaborador('destinatario_colaborador_id', 'destinatario_colaborador_id', $destinatario_pre_selecionado, $colaboradores, true) ?>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Feedback Anônimo</label>
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="anonimo" id="anonimo_checkbox" value="1" />
                                <label class="form-check-label" for="anonimo_checkbox">
                                    Tornar esse feedback anônimo para o colaborador selecionado
                                </label>
                            </div>
                        </div>
                    </div>
                    <!--end::Seleção de Colaborador e Anônimo-->
                    
                    <!--begin::Itens de Avaliação-->
                    <?php if (!empty($itens_avaliacao)): ?>
                    <div class="mb-10">
                        <label class="fw-semibold fs-6 mb-4">Itens da empresa</label>
                        <p class="text-muted fs-7 mb-5">Atribua um ou mais itens da empresa a esse feedback</p>
                        
                        <div class="row g-5">
                            <?php foreach ($itens_avaliacao as $item): ?>
                            <div class="col-md-6">
                                <div class="card card-flush bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title fs-6 fw-bold mb-2"><?= htmlspecialchars($item['nome']) ?></h5>
                                        <p class="text-muted fs-7 mb-4"><?= htmlspecialchars($item['descricao']) ?></p>
                                        
                                        <div class="rating-stars" data-item-id="<?= $item['id'] ?>">
                                            <input type="hidden" name="avaliacoes[<?= $item['id'] ?>]" class="rating-value" value="0">
                                            <div class="stars-container d-flex gap-2 align-items-center">
                                                <span class="star-btn" data-rating="1" data-item="<?= $item['id'] ?>" title="Praticou minimamente o comportamento/valor">☆</span>
                                                <span class="star-btn" data-rating="2" data-item="<?= $item['id'] ?>" title="Praticou parcialmente o comportamento/valor">☆</span>
                                                <span class="star-btn" data-rating="3" data-item="<?= $item['id'] ?>" title="Praticou moderadamente o comportamento/valor">☆</span>
                                                <span class="star-btn" data-rating="4" data-item="<?= $item['id'] ?>" title="Praticou muito o comportamento/valor">☆</span>
                                                <span class="star-btn" data-rating="5" data-item="<?= $item['id'] ?>" title="Praticou por completo o comportamento/valor">☆</span>
                                                <button type="button" class="btn-clear-rating btn btn-sm btn-light ms-2" data-item="<?= $item['id'] ?>" title="Limpar avaliação" style="display: none;">
                                                    <i class="ki-duotone ki-cross fs-6">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <!--end::Itens de Avaliação-->
                    
                    <!--begin::Template e Conteúdo-->
                    <div class="row mb-7">
                        <div class="col-md-12 mb-5">
                            <label class="fw-semibold fs-6 mb-2">Escolha um modelo de Feedback</label>
                            <select name="template_id" id="template_select" class="form-select form-select-solid">
                                <?php foreach ($templates as $id => $nome): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Descreva seu feedback</label>
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="ki-duotone ki-information-5 fs-6">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Não é possível adicionar imagens.
                                </small>
                            </div>
                            <textarea name="conteudo" id="feedback_conteudo" class="form-control form-control-solid" rows="8" placeholder="Exemplo: lembre de atualizar as informações no sistema antes de enviar os dados aos clientes. De repente podemos nos reunir para alinhar esse procedimento, o que você acha?" required></textarea>
                            
                            <!-- Templates ocultos (em texto puro) -->
                            <textarea class="d-none" id="template_1" data-template="1">Você deve parar de:
(descreva o que seu colega deve parar de fazer)

Você deve continuar fazendo:
(descreva a sugestão que o seu colega faz bem e deve continuar fazendo)

Você deve começar a fazer:
(descreva algo que seu colega ainda não faz mas você sugere que ele deva fazer)</textarea>
                            <textarea class="d-none" id="template_2" data-template="2">Descreva a situação específica em que algo ocorreu:

Explique o comportamento dessa pessoa naquela situação:

Descreva qual impacto o comportamento gerou:</textarea>
                            <textarea class="d-none" id="template_3" data-template="3">Primeiro passo: observar o que ocorre sem julgar. Falar os fatos.

Segundo passo: Expressar seu sentimento de acordo com o ocorrido.

Terceiro passo: Identificar sua necessidade e deixar a pessoa ciente do que você esperava naquela situação.

Quarto passo: fazer um pedido para que suas expectativas sejam atendidas.</textarea>
                            <textarea class="d-none" id="template_4" data-template="4">Pontos conversados
- Qual sua visão de futuro aqui na equipe?
- O que você mais gosta de fazer e o que menos gosta?
- Qual sua maior dificuldade?
- Como gestor, como consigo te ajudar no seu dia-a-dia?

Plano de ação
- Realizar o curso X
- Realizar as atividades Y e Z

Pontos que ficaram para o próximo encontro
- Assunto X ...</textarea>
                            <textarea class="d-none" id="template_5" data-template="5">Muito legal! Você tem ajudado a equipe com suas ideias e sugestões de melhorias, continue assim!</textarea>
                            <textarea class="d-none" id="template_6" data-template="6">A reunião que você organizou estava muito boa. Agenda preparada e reunião concisa otimizam nosso tempo, parabéns!</textarea>
                            <textarea class="d-none" id="template_7" data-template="7">A atividade X que você realizou foi muito legal!

Entretanto você mostrou alguma deficiência nos pontos Y e Z.

Isso faz parecer que você não entende direito sobre esse assunto.

Tenho certeza que se você estudar mais sobre isso vai arrasar na próxima vez.</textarea>
                            <textarea class="d-none" id="template_14" data-template="14">Você precisa de mais atenção com relação aos prazos, combinamos que a atividade X seria entregue na data Y e não foi isso que aconteceu.</textarea>
                        </div>
                    </div>
                    <!--end::Template e Conteúdo-->
                    
                    <!--begin::Feedback Presencial-->
                    <div class="mb-7">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="presencial" id="presencial_checkbox" value="1" />
                            <label class="form-check-label fw-semibold fs-6" for="presencial_checkbox">
                                Feedback presencial
                            </label>
                        </div>
                        <small class="text-muted">Marque se esse feedback foi dado presencialmente</small>
                    </div>
                    <!--end::Feedback Presencial-->
                    
                    <!--begin::Anotações Internas-->
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Anotações Internas</label>
                        <p class="text-muted fs-7 mb-3">Essas anotações são suas. São privadas e aparecerão apenas para você.</p>
                        <textarea name="anotacoes_internas" id="anotacoes_internas" class="form-control form-control-solid" rows="4" placeholder="Escreva suas anotações internas aqui"></textarea>
                    </div>
                    <!--end::Anotações Internas-->
                    
                    <!--begin::Actions-->
                    <div class="d-flex justify-content-end">
                        <button type="submit" id="kt_submit_feedback" class="btn btn-primary">
                            <span class="indicator-label">Enviar Feedback</span>
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
    /* Ajusta a altura do Select2 */
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
    
    .select2-container .select2-selection--single .select2-selection__rendered img,
    .select2-container .select2-selection--single .select2-selection__rendered .symbol {
        margin-right: 8px !important;
    }
</style>
<!--end::Select2 CSS-->

<!--begin::Scripts-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/select-colaborador.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sistema de estrelas usando event delegation (funciona mesmo após clone do formulário)
    document.addEventListener('click', function(e) {
        const starBtn = e.target.closest('.star-btn');
        if (starBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            const rating = parseInt(starBtn.getAttribute('data-rating'));
            const itemId = starBtn.getAttribute('data-item');
            const container = starBtn.closest('.rating-stars');
            if (!container) return;
            
            const ratingValue = container.querySelector('.rating-value');
            const currentRating = parseInt(ratingValue.value || 0);
            const clearBtn = container.querySelector('.btn-clear-rating');
            
            // Se clicar na mesma estrela que já está selecionada, zera
            if (currentRating === rating) {
                // Zera avaliação
                const stars = container.querySelectorAll('.star-btn');
                stars.forEach(function(s) {
                    s.textContent = '☆';
                    s.style.color = '#ccc';
                });
                ratingValue.value = 0;
                if (clearBtn) clearBtn.style.display = 'none';
            } else {
                // Atualiza visual
                const stars = container.querySelectorAll('.star-btn');
                stars.forEach(function(s, index) {
                    if (index < rating) {
                        s.textContent = '★';
                        s.style.color = '#ffc700';
                    } else {
                        s.textContent = '☆';
                        s.style.color = '#ccc';
                    }
                });
                
                // Atualiza input hidden
                ratingValue.value = rating;
                if (clearBtn) clearBtn.style.display = 'inline-block';
            }
        }
    });
    
    // Função para inicializar hover nas estrelas (chamada após clone do formulário)
    function initStarHover() {
        document.querySelectorAll('.star-btn').forEach(function(star) {
            // Adiciona listeners de hover diretamente
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                const container = this.closest('.rating-stars');
                if (!container) return;
                
                const stars = container.querySelectorAll('.star-btn');
                stars.forEach(function(s, index) {
                    if (index < rating) {
                        s.style.color = '#ffc700';
                    } else {
                        s.style.color = '#ccc';
                    }
                });
            });
            
            star.addEventListener('mouseleave', function() {
                const container = this.closest('.rating-stars');
                if (!container) return;
                
                const stars = container.querySelectorAll('.star-btn');
                const currentRating = parseInt(container.querySelector('.rating-value').value || 0);
                
                stars.forEach(function(s, index) {
                    if (index < currentRating) {
                        s.textContent = '★';
                        s.style.color = '#ffc700';
                    } else {
                        s.textContent = '☆';
                        s.style.color = '#ccc';
                    }
                });
            });
        });
    }
    
    // Inicializa hover nas estrelas
    initStarHover();
    
    // Sistema de templates
    const templateSelect = document.getElementById('template_select');
    const conteudoTextarea = document.getElementById('feedback_conteudo');
    let lastSelectedTemplate = '0';
    
    templateSelect.addEventListener('change', function() {
        const templateId = this.value;
        
        if (templateId === '0') {
            lastSelectedTemplate = '0';
            return;
        }
        
        const templateElement = document.getElementById('template_' + templateId);
        if (templateElement) {
            // Se já tem conteúdo e não é o mesmo template, pergunta se quer substituir
            if (conteudoTextarea.value.trim() !== '' && lastSelectedTemplate !== templateId) {
                if (confirm('Deseja substituir o conteúdo atual pelo modelo selecionado?')) {
                    conteudoTextarea.value = templateElement.value;
                }
            } else {
                // Se está vazio ou é o mesmo template, substitui diretamente
                conteudoTextarea.value = templateElement.value;
            }
            lastSelectedTemplate = templateId;
        }
    });
    
    // Botões para limpar avaliação (delegation para funcionar com elementos criados dinamicamente)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-clear-rating')) {
            e.preventDefault();
            e.stopPropagation();
            const btn = e.target.closest('.btn-clear-rating');
            const itemId = btn.getAttribute('data-item');
            const container = document.querySelector('.rating-stars[data-item-id="' + itemId + '"]');
            if (container) {
                const stars = container.querySelectorAll('.star-btn');
                const ratingValue = container.querySelector('.rating-value');
                stars.forEach(function(s) {
                    s.textContent = '☆';
                    s.style.color = '#ccc';
                });
                ratingValue.value = 0;
                btn.style.display = 'none';
            }
        }
    });
    
    // Submit do formulário
    const form = document.getElementById('kt_form_feedback');
    const submitBtn = document.getElementById('kt_submit_feedback');
    let isSubmitting = false; // Flag para evitar múltiplos envios
    let lastSubmitTime = 0; // Timestamp do último envio
    let submitCount = 0; // Contador de submissões para debug
    
    if (form && submitBtn) {
        // Remove TODOS os event listeners existentes
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        const cleanForm = document.getElementById('kt_form_feedback');
        const cleanBtn = document.getElementById('kt_submit_feedback');
        
        // Reinicializa Select2 e hover nas estrelas após clone
        if (typeof window.initSelectColaboradorManual === 'function') {
            setTimeout(function() {
                window.initSelectColaboradorManual();
            }, 100);
        }
        initStarHover();
        
        cleanForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation(); // Impede outros listeners
            
            submitCount++;
            console.log('▶️ Submit tentado #' + submitCount);
            
            const now = Date.now();
            const timeSinceLastSubmit = now - lastSubmitTime;
            
            // Previne múltiplos envios (duplo clique ou múltiplas submissões)
            if (isSubmitting) {
                console.warn('🚫 Bloqueado: Já está enviando (tentativa #' + submitCount + ')');
                return false;
            }
            
            // Previne submissões muito rápidas (menos de 3 segundos)
            if (lastSubmitTime > 0 && timeSinceLastSubmit < 3000) {
                console.warn('🚫 Bloqueado: Aguarde ' + Math.ceil((3000 - timeSinceLastSubmit) / 1000) + 's antes de enviar novamente');
                return false;
            }
            
            // Marca como enviando IMEDIATAMENTE
            console.log('✅ Enviando feedback... (tentativa #' + submitCount + ')');
            isSubmitting = true;
            lastSubmitTime = now;
            cleanBtn.setAttribute('data-kt-indicator', 'on');
            cleanBtn.disabled = true;
            
            const formData = new FormData(cleanForm);
            
            // Adiciona timestamp único para evitar duplicação
            const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            formData.append('request_id', requestId);
            
            // Coleta avaliações
            const avaliacoes = {};
            document.querySelectorAll('.rating-stars').forEach(function(container) {
                const itemId = container.getAttribute('data-item-id');
                const rating = container.querySelector('.rating-value').value;
                if (rating > 0) {
                    avaliacoes[itemId] = rating;
                }
            });
            
            // Adiciona avaliações ao FormData
            Object.keys(avaliacoes).forEach(function(itemId) {
                formData.append('avaliacoes[' + itemId + ']', avaliacoes[itemId]);
            });
            
            console.log('📤 Enviando para API... Request ID:', requestId);
            
            fetch('../api/feedback/enviar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('📡 Status da resposta:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.text().then(text => {
                    console.log('📄 Resposta raw:', text.substring(0, 200));
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('❌ Erro ao fazer parse do JSON:', e);
                        console.error('Resposta recebida:', text);
                        throw new Error('Resposta inválida do servidor. Verifique o console.');
                    }
                });
            })
            .then(data => {
                console.log('📨 Resposta da API:', data);
                if (data.success) {
                    console.log('✅ Feedback enviado com sucesso!');
                    
                    // Mostra toast de pontos se ganhou
                    if (data.pontos_ganhos && window.processarRespostaPontos) {
                        window.processarRespostaPontos(data, 'enviar_feedback');
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
                        window.location.href = 'feedback_meus.php';
                    });
                } else {
                    console.error('❌ Erro na API:', data.message);
                    Swal.fire({
                        text: data.message || 'Erro ao enviar feedback',
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
                console.error('❌ Erro ao enviar:', error);
                Swal.fire({
                    text: 'Erro ao enviar feedback: ' + error.message,
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
        }, { once: false }); // Permite múltiplos submits, mas controlados pela flag
        
        // Proteção adicional: desabilita o botão imediatamente ao clicar
        cleanBtn.addEventListener('click', function(e) {
            console.log('🖱️ Botão clicado (isSubmitting=' + isSubmitting + ')');
            if (isSubmitting) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                console.warn('🚫 Bloqueado: Botão clicado durante envio');
                return false;
            }
        }, true); // useCapture = true para executar antes de outros listeners
    }
});
</script>
<style>
.star-btn {
    font-size: 2rem;
    cursor: pointer;
    color: #ccc;
    transition: color 0.2s;
    user-select: none;
}
.star-btn:hover {
    color: #ffc700;
}
.stars-container {
    justify-content: center;
}
</style>
<!--end::Scripts-->

<!--begin::Script para pré-selecionar destinatário de solicitação-->
<?php if (!empty($destinatario_pre_selecionado)): ?>
<script>
// Garante que o destinatário seja selecionado quando vier de uma solicitação aceita
document.addEventListener('DOMContentLoaded', function() {
    var destinatarioId = <?= json_encode($destinatario_pre_selecionado) ?>;
    
    if (destinatarioId) {
        console.log('📋 Pré-selecionando destinatário da solicitação:', destinatarioId);
        
        // Função para tentar selecionar o destinatário
        function trySelectDestinatario() {
            // Verifica se jQuery e Select2 estão disponíveis
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(trySelectDestinatario, 100);
                return;
            }
            
            var $ = window.jQuery;
            var $select = $('#destinatario_colaborador_id');
            
            // Verifica se o select existe
            if (!$select.length) {
                setTimeout(trySelectDestinatario, 100);
                return;
            }
            
            // Aguarda o Select2 ser inicializado
            if (!$select.hasClass('select2-hidden-accessible')) {
                setTimeout(trySelectDestinatario, 100);
                return;
            }
            
            // Define o valor e dispara eventos para o Select2 atualizar
            $select.val(destinatarioId).trigger('change.select2');
            
            console.log('✅ Destinatário pré-selecionado:', destinatarioId);
            
            // Scroll suave para o campo de conteúdo após selecionar
            setTimeout(function() {
                var conteudoField = document.getElementById('feedback_conteudo');
                if (conteudoField) {
                    conteudoField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    conteudoField.focus();
                }
            }, 500);
        }
        
        // Inicia tentativa após um delay para garantir que tudo está carregado
        setTimeout(trySelectDestinatario, 500);
    }
});
</script>
<?php endif; ?>
<!--end::Script para pré-selecionar destinatário de solicitação-->

<!--begin::Tutorial System-->
<link href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/introjs.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js"></script>
<script src="../assets/js/tutorial-system.js"></script>
<script>
// Configuração do tutorial para esta página
window.pageTutorial = {
    pageId: 'feedback_enviar',
    steps: [
        {
            title: 'Bem-vindo ao Envio de Feedback',
            intro: 'Este tutorial vai te guiar pelas principais funcionalidades do formulário de feedback. Vamos começar!'
        },
        {
            element: '#destinatario_colaborador_id',
            title: 'Seleção de Colaborador',
            intro: 'Comece selecionando o colaborador que receberá o feedback. Use a busca para encontrar rapidamente pelo nome ou CPF.'
        },
        {
            element: '#anonimo_checkbox',
            title: 'Feedback Anônimo',
            intro: 'Marque esta opção se desejar que o feedback seja anônimo. O colaborador receberá o feedback mas não saberá quem enviou.'
        },
        {
            element: '.rating-stars:first-of-type',
            title: 'Itens de Avaliação',
            intro: 'Avalie o colaborador nos itens da empresa usando as estrelas. Clique nas estrelas para atribuir uma nota de 1 a 5. Clique novamente na mesma estrela para remover a avaliação.'
        },
        {
            element: '#template_select',
            title: 'Modelo de Feedback',
            intro: 'Escolha um modelo de feedback para te ajudar a estruturar sua mensagem. Os modelos incluem formatos como "Parar/Continuar/Começar", SCI, Comunicação Não Violenta, etc.'
        },
        {
            element: '#feedback_conteudo',
            title: 'Conteúdo do Feedback',
            intro: 'Descreva seu feedback aqui. Se você selecionou um modelo, o texto será preenchido automaticamente. Você pode editar livremente o conteúdo.'
        },
        {
            element: '#presencial_checkbox',
            title: 'Feedback Presencial',
            intro: 'Marque esta opção se o feedback foi dado presencialmente (em uma conversa face a face). Isso ajuda a rastrear diferentes tipos de feedback.'
        },
        {
            element: '#anotacoes_internas',
            title: 'Anotações Internas',
            intro: 'Use este campo para fazer anotações privadas sobre o feedback. Essas anotações são apenas suas e não serão visíveis para o colaborador.'
        },
        {
            element: '#kt_submit_feedback',
            title: 'Enviar Feedback',
            intro: 'Após preencher todos os campos desejados, clique em "Enviar Feedback" para enviar. O colaborador receberá uma notificação sobre o novo feedback.'
        }
    ]
};
</script>
<!--end::Tutorial System-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

