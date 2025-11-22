<?php
/**
 * Ver Feedback - Visualizar feedback completo e responder
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$usuario_id = $usuario['id'] ?? null;
$colaborador_id = $usuario['colaborador_id'] ?? null;

$feedback_id = $_GET['id'] ?? null;

if (empty($feedback_id)) {
    header('Location: feedback_meus.php');
    exit;
}

// Busca o feedback
$sql = "
    SELECT 
        f.id,
        f.remetente_usuario_id,
        f.remetente_colaborador_id,
        f.destinatario_usuario_id,
        f.destinatario_colaborador_id,
        f.template_id,
        f.conteudo,
        f.anonimo,
        f.presencial,
        f.anotacoes_internas,
        f.created_at,
        f.updated_at,
        -- Remetente
        COALESCE(ru.nome, rc.nome_completo) as remetente_nome,
        COALESCE(rc.foto, NULL) as remetente_foto,
        -- Destinatário
        COALESCE(du.nome, dc.nome_completo) as destinatario_nome,
        COALESCE(dc.foto, NULL) as destinatario_foto,
        -- Verifica se é remetente ou destinatário
        CASE 
            WHEN (f.remetente_usuario_id = ? OR f.remetente_colaborador_id = ?) THEN 'remetente'
            ELSE 'destinatario'
        END as tipo_relacao
    FROM feedbacks f
    LEFT JOIN usuarios ru ON f.remetente_usuario_id = ru.id
    LEFT JOIN colaboradores rc ON f.remetente_colaborador_id = rc.id OR (f.remetente_usuario_id = ru.id AND ru.colaborador_id = rc.id)
    LEFT JOIN usuarios du ON f.destinatario_usuario_id = du.id
    LEFT JOIN colaboradores dc ON f.destinatario_colaborador_id = dc.id OR (f.destinatario_usuario_id = du.id AND du.colaborador_id = dc.id)
    WHERE f.id = ? AND f.status = 'ativo'
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $usuario_id ?? 0,
    $colaborador_id ?? 0,
    $feedback_id
]);
$feedback = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$feedback) {
    header('Location: feedback_meus.php');
    exit;
}

// Verifica se o usuário tem acesso a este feedback
$tem_acesso = false;
if ($usuario_id) {
    $tem_acesso = ($feedback['remetente_usuario_id'] == $usuario_id) || 
                  ($feedback['destinatario_usuario_id'] == $usuario_id);
} elseif ($colaborador_id) {
    $tem_acesso = ($feedback['remetente_colaborador_id'] == $colaborador_id) || 
                  ($feedback['destinatario_colaborador_id'] == $colaborador_id);
}

if (!$tem_acesso) {
    header('Location: feedback_meus.php');
    exit;
}

$is_remetente = $feedback['tipo_relacao'] === 'remetente';

// Busca TODOS os itens de avaliação disponíveis e suas notas (se avaliados)
$stmt_av = $pdo->prepare("
    SELECT 
        fi.id as item_id,
        fi.nome as item_nome,
        fi.descricao as item_descricao,
        fi.ordem,
        COALESCE(fa.nota, 0) as nota
    FROM feedback_itens fi
    LEFT JOIN feedback_avaliacoes fa ON fi.id = fa.item_id AND fa.feedback_id = ?
    WHERE fi.status = 'ativo'
    ORDER BY fi.ordem, fi.nome
");
$stmt_av->execute([$feedback_id]);
$avaliacoes = $stmt_av->fetchAll(PDO::FETCH_ASSOC);

// Busca respostas
$stmt_resp = $pdo->prepare("
    SELECT 
        fr.id,
        fr.resposta,
        fr.resposta_pai_id,
        fr.created_at,
        COALESCE(u.nome, c.nome_completo) as autor_nome,
        COALESCE(c.foto, NULL) as autor_foto,
        fr.usuario_id,
        fr.colaborador_id,
        CASE 
            WHEN (fr.usuario_id = ? OR fr.colaborador_id = ?) THEN 'eu'
            ELSE 'outro'
        END as autor_tipo
    FROM feedback_respostas fr
    LEFT JOIN usuarios u ON fr.usuario_id = u.id
    LEFT JOIN colaboradores c ON fr.colaborador_id = c.id OR (fr.usuario_id = u.id AND u.colaborador_id = c.id)
    WHERE fr.feedback_id = ? AND fr.status = 'ativo'
    ORDER BY fr.created_at ASC
");
$stmt_resp->execute([
    $usuario_id ?? 0,
    $colaborador_id ?? 0,
    $feedback_id
]);
$respostas = $stmt_resp->fetchAll(PDO::FETCH_ASSOC);

// Se for anônimo e usuário não for o remetente, oculta nome do remetente
if ($feedback['anonimo'] && !$is_remetente) {
    $feedback['remetente_nome'] = 'Anônimo';
    $feedback['remetente_foto'] = null;
}

// Se usuário não for remetente, não mostra anotações internas
if (!$is_remetente) {
    $feedback['anotacoes_internas'] = null;
}

$page_title = 'Ver Feedback';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Ver Feedback</h1>
            <span class="text-muted fw-semibold fs-7">Visualize o feedback completo e responda</span>
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
        <div class="row g-5 g-xl-8">
            <div class="col-xl-12">
                <!--begin::Card-->
                <div class="card card-flush">
                    <div class="card-body pt-6">
                        <!--begin::Header do Feedback-->
                        <div class="d-flex align-items-start gap-5 mb-7">
                            <div class="symbol symbol-circle symbol-70px">
                                <?php
                                $foto = $is_remetente ? $feedback['destinatario_foto'] : $feedback['remetente_foto'];
                                $nome = $is_remetente ? $feedback['destinatario_nome'] : $feedback['remetente_nome'];
                                $inicial = strtoupper(substr($nome, 0, 1));
                                ?>
                                <?php if ($foto): ?>
                                    <img src="../uploads/fotos/<?= htmlspecialchars($foto) ?>" alt="<?= htmlspecialchars($nome) ?>" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'symbol-label fs-3 fw-bold bg-primary text-white\'><?= $inicial ?></div>';">
                                <?php else: ?>
                                    <div class="symbol-label fs-3 fw-bold bg-primary text-white"><?= $inicial ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h3 class="fw-bold text-gray-800 mb-1">
                                            <?= $is_remetente ? 'Para: ' . htmlspecialchars($feedback['destinatario_nome']) : 'De: ' . htmlspecialchars($feedback['remetente_nome']) ?>
                                        </h3>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($feedback['presencial']): ?>
                                                <span class="badge badge-light-info">Presencial</span>
                                            <?php endif; ?>
                                            <?php if ($feedback['anonimo'] && !$is_remetente): ?>
                                                <span class="badge badge-light-warning">Anônimo</span>
                                            <?php endif; ?>
                                            <span class="text-muted fs-7"><?= date('d/m/Y H:i', strtotime($feedback['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!--begin::Avaliações-->
                                <?php if (!empty($avaliacoes)): ?>
                                    <div class="mb-5">
                                        <h5 class="fw-bold mb-4">Avaliações por Itens:</h5>
                                        <div class="row g-3">
                                            <?php foreach ($avaliacoes as $av): ?>
                                                <div class="col-md-6">
                                                    <div class="card border border-gray-300 h-100">
                                                        <div class="card-body p-4">
                                                            <div class="fw-bold text-gray-800 mb-2 fs-5">
                                                                <?= htmlspecialchars($av['item_nome']) ?>
                                                            </div>
                                                            <?php if ($av['item_descricao']): ?>
                                                                <div class="text-muted fs-7 mb-3">
                                                                    <?= htmlspecialchars($av['item_descricao']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <span style="color: <?= $i <= $av['nota'] ? '#ffc700' : '#e0e0e0' ?>; font-size: 2rem; line-height: 1;"><?= $i <= $av['nota'] ? '★' : '☆' ?></span>
                                                                    <?php endfor; ?>
                                                                </div>
                                                                <?php if ($av['nota'] > 0): ?>
                                                                    <span class="badge badge-light-primary fs-6 ms-2"><?= $av['nota'] ?>/5</span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-light-secondary fs-7 ms-2">Não avaliado</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <!--end::Avaliações-->
                                
                                <!--begin::Conteúdo-->
                                <div class="mb-5">
                                    <h5 class="fw-bold mb-3">Conteúdo do Feedback:</h5>
                                    <div class="p-4 bg-light rounded">
                                        <div class="text-gray-800" style="white-space: pre-wrap;"><?= htmlspecialchars($feedback['conteudo']) ?></div>
                                    </div>
                                </div>
                                <!--end::Conteúdo-->
                                
                                <!--begin::Anotações Internas (apenas para remetente)-->
                                <?php if ($is_remetente && $feedback['anotacoes_internas']): ?>
                                    <div class="mb-5">
                                        <h5 class="fw-bold mb-3">Anotações Internas:</h5>
                                        <div class="p-4 bg-light-warning rounded">
                                            <div class="text-gray-800" style="white-space: pre-wrap;"><?= htmlspecialchars($feedback['anotacoes_internas']) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <!--end::Anotações Internas-->
                            </div>
                        </div>
                        <!--end::Header do Feedback-->
                        
                        <!--begin::Respostas-->
                        <div class="separator my-7"></div>
                        <div class="mb-7">
                            <h4 class="fw-bold mb-4">Respostas (<?= count($respostas) ?>)</h4>
                            
                            <?php if (empty($respostas)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="ki-duotone ki-message-text fs-3x mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <p>Nenhuma resposta ainda. Seja o primeiro a responder!</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($respostas as $resp): ?>
                                        <?php
                                        $autorFoto = $resp['autor_foto'] ? '../uploads/fotos/' . $resp['autor_foto'] : null;
                                        $autorNome = $resp['autor_nome'] ?: 'Usuário';
                                        $autorInicial = strtoupper(substr($autorNome, 0, 1));
                                        $isMinhaResposta = $resp['autor_tipo'] === 'eu';
                                        ?>
                                        <div class="timeline-item mb-5">
                                            <div class="timeline-line w-40px"></div>
                                            <div class="timeline-icon symbol symbol-circle symbol-40px">
                                                <?php if ($autorFoto): ?>
                                                    <img src="<?= htmlspecialchars($autorFoto) ?>" alt="<?= htmlspecialchars($autorNome) ?>" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'symbol-label fs-6 fw-bold bg-light-primary text-primary\'><?= htmlspecialchars($autorInicial) ?></div>';">
                                                <?php else: ?>
                                                    <div class="symbol-label fs-6 fw-bold bg-light-primary text-primary"><?= htmlspecialchars($autorInicial) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="timeline-content mb-0">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="fw-bold text-gray-800"><?= htmlspecialchars($autorNome) ?></span>
                                                    <?php if ($isMinhaResposta): ?>
                                                        <span class="badge badge-light-success ms-2">Você</span>
                                                    <?php endif; ?>
                                                    <span class="text-muted ms-2 fs-7"><?= date('d/m/Y H:i', strtotime($resp['created_at'])) ?></span>
                                                </div>
                                                <div class="p-3 bg-light rounded">
                                                    <div class="text-gray-800" style="white-space: pre-wrap;"><?= htmlspecialchars($resp['resposta']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!--end::Respostas-->
                        
                        <!--begin::Formulário de Resposta-->
                        <div class="separator my-7"></div>
                        <div>
                            <h4 class="fw-bold mb-4">Responder ao Feedback</h4>
                            <form id="form_resposta" class="form">
                                <input type="hidden" name="feedback_id" value="<?= $feedback_id ?>">
                                <div class="mb-5">
                                    <label class="form-label">Sua resposta</label>
                                    <textarea name="resposta" class="form-control form-control-solid" rows="5" placeholder="Digite sua resposta aqui..." required></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="feedback_meus.php" class="btn btn-light">Cancelar</a>
                                    <button type="submit" class="btn btn-primary" id="btn_enviar_resposta">
                                        <span class="indicator-label">Enviar Resposta</span>
                                        <span class="indicator-progress">Enviando...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <!--end::Formulário de Resposta-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
        </div>
    </div>
</div>
<!--end::Post-->

<!--begin::Scripts-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form_resposta');
    const btnEnviar = document.getElementById('btn_enviar_resposta');
    let isSubmitting = false;
    let lastSubmitTime = 0;
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            const now = Date.now();
            const timeSinceLastSubmit = now - lastSubmitTime;
            
            if (isSubmitting) {
                console.warn('Bloqueado: Já está enviando');
                return false;
            }
            
            // Previne submissões muito rápidas (menos de 2 segundos)
            if (lastSubmitTime > 0 && timeSinceLastSubmit < 2000) {
                console.warn('Bloqueado: Aguarde antes de enviar novamente');
                return false;
            }
            
            const resposta = form.querySelector('[name="resposta"]').value.trim();
            
            if (!resposta) {
                Swal.fire({
                    text: 'Digite uma resposta antes de enviar',
                    icon: "warning",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
                return;
            }
            
            // Marca como enviando IMEDIATAMENTE
            isSubmitting = true;
            lastSubmitTime = now;
            btnEnviar.setAttribute('data-kt-indicator', 'on');
            btnEnviar.disabled = true;
            
            const formData = new FormData(form);
            
            // Adiciona timestamp único para evitar duplicação
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
                        // Recarrega a página para mostrar a nova resposta
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        text: data.message || 'Erro ao enviar resposta',
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                    // Libera apenas em caso de erro
                    isSubmitting = false;
                    btnEnviar.removeAttribute('data-kt-indicator');
                    btnEnviar.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    text: 'Erro ao enviar resposta',
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
                // Libera apenas em caso de erro
                isSubmitting = false;
                btnEnviar.removeAttribute('data-kt-indicator');
                btnEnviar.disabled = false;
            });
        });
        
        // Proteção adicional: desabilita o botão imediatamente ao clicar
        if (btnEnviar) {
            btnEnviar.addEventListener('click', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    console.warn('Bloqueado: Botão clicado durante envio');
                    return false;
                }
            }, true); // useCapture = true para executar antes de outros listeners
        }
    }
});
</script>
<!--end::Scripts-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

