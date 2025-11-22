<?php
/**
 * Página de Análise de Emoções
 */

$page_title = 'Como você está se sentindo?';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$usuario_id = $usuario['id'] ?? null;
$colaborador_id = $usuario['colaborador_id'] ?? null;

// Verifica se já registrou emoção hoje
$data_hoje = date('Y-m-d');
$ja_registrou = false;

if ($usuario_id) {
    $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE usuario_id = ? AND data_registro = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$usuario_id, $data_hoje]);
} else if ($colaborador_id) {
    $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE colaborador_id = ? AND data_registro = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$colaborador_id, $data_hoje]);
}

$emocao_hoje = $stmt->fetch();
if ($emocao_hoje) {
    $ja_registrou = true;
}

// Busca histórico de emoções (últimos 30 dias)
if ($usuario_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM emocoes 
        WHERE usuario_id = ? 
        AND data_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY data_registro DESC
    ");
    $stmt->execute([$usuario_id]);
} else if ($colaborador_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM emocoes 
        WHERE colaborador_id = ? 
        AND data_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY data_registro DESC
    ");
    $stmt->execute([$colaborador_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE 1=0");
    $stmt->execute();
}

$historico = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Análise de Emoções</h1>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        <div class="row g-5 g-xl-8">
            <!--begin::Col-->
            <div class="col-xl-8">
                <!--begin::Card-->
                <div class="card card-flush mb-5">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Como você está se sentindo?</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php if ($ja_registrou): ?>
                            <div class="alert alert-success d-flex align-items-center p-5 mb-10">
                                <i class="ki-duotone ki-check-circle fs-2hx text-success me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-dark">Emoção já registrada hoje!</h4>
                                    <span>Você já registrou sua emoção hoje. Volte amanhã para registrar novamente.</span>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column align-items-center p-10">
                                <div class="mb-5">
                                    <?php
                                    $niveis = [
                                        1 => ['emoji' => '😢', 'nome' => 'Muito triste', 'cor' => 'danger'],
                                        2 => ['emoji' => '😔', 'nome' => 'Triste', 'cor' => 'warning'],
                                        3 => ['emoji' => '😐', 'nome' => 'Neutro', 'cor' => 'info'],
                                        4 => ['emoji' => '🙂', 'nome' => 'Feliz', 'cor' => 'success'],
                                        5 => ['emoji' => '😄', 'nome' => 'Muito feliz', 'cor' => 'success']
                                    ];
                                    $nivel = $emocao_hoje['nivel_emocao'];
                                    $emoji_info = $niveis[$nivel];
                                    ?>
                                    <div class="text-center">
                                        <div class="fs-1 mb-3"><?= $emoji_info['emoji'] ?></div>
                                        <div class="fs-3 fw-bold text-gray-800 mb-2"><?= $emoji_info['nome'] ?></div>
                                        <?php if (!empty($emocao_hoje['descricao'])): ?>
                                            <div class="text-gray-600"><?= htmlspecialchars($emocao_hoje['descricao']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <form id="form_emocao">
                                <div class="d-flex flex-column align-items-center mb-10">
                                    <h3 class="text-center mb-5">Selecione como você está se sentindo:</h3>
                                    
                                    <div class="d-flex gap-5 mb-10">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="1" class="d-none" required>
                                            <div class="emocao-option" data-nivel="1">
                                                <div class="fs-1">😢</div>
                                                <div class="text-muted small">Muito triste</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="2" class="d-none" required>
                                            <div class="emocao-option" data-nivel="2">
                                                <div class="fs-1">😔</div>
                                                <div class="text-muted small">Triste</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="3" class="d-none" required>
                                            <div class="emocao-option" data-nivel="3">
                                                <div class="fs-1">😐</div>
                                                <div class="text-muted small">Neutro</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="4" class="d-none" required>
                                            <div class="emocao-option" data-nivel="4">
                                                <div class="fs-1">🙂</div>
                                                <div class="text-muted small">Feliz</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="5" class="d-none" required>
                                            <div class="emocao-option" data-nivel="5">
                                                <div class="fs-1">😄</div>
                                                <div class="text-muted small">Muito feliz</div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="w-100 mb-5">
                                        <label class="form-label">Nos conte o que te faz sentir assim</label>
                                        <textarea name="descricao" class="form-control form-control-solid" rows="4" placeholder="Fique à vontade para falar o que sente. Essa informação é privada e será lida somente por alguém que quer te ver feliz!"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <span class="indicator-label">Enviar humor</span>
                                        <span class="indicator-progress">Enviando...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center text-muted small">
                                <i class="ki-duotone ki-information-5 fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <span class="ms-2">Ganhe 50 pontos ao registrar sua emoção!</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-4">
                <!--begin::Card-->
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Histórico</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Últimos 30 dias</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php if (empty($historico)): ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhuma emoção registrada ainda.</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php
                                $niveis_emoji = [1 => '😢', 2 => '😔', 3 => '😐', 4 => '🙂', 5 => '😄'];
                                foreach ($historico as $emocao):
                                    $data_formatada = date('d/m/Y', strtotime($emocao['data_registro']));
                                ?>
                                    <div class="timeline-item mb-5">
                                        <div class="timeline-line w-40px"></div>
                                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                                            <div class="symbol-label bg-light">
                                                <span class="fs-2"><?= $niveis_emoji[$emocao['nivel_emocao']] ?></span>
                                            </div>
                                        </div>
                                        <div class="timeline-content mb-0 mt-n1">
                                            <div class="pe-3 mb-5">
                                                <div class="fs-5 fw-semibold mb-2"><?= $data_formatada ?></div>
                                                <?php if (!empty($emocao['descricao'])): ?>
                                                    <div class="text-gray-600"><?= htmlspecialchars($emocao['descricao']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
        </div>
    </div>
</div>
<!--end::Post-->

<style>
.emocao-option {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    transition: all 0.3s;
    cursor: pointer;
    border: 2px solid transparent;
}

.emocao-option:hover {
    background-color: #f5f8fa;
    transform: scale(1.1);
}

input[type="radio"]:checked + .emocao-option {
    border-color: #009ef7;
    background-color: #f1faff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seleção visual de emoção
    document.querySelectorAll('.emocao-option').forEach(function(option) {
        option.addEventListener('click', function() {
            const radio = this.previousElementSibling;
            radio.checked = true;
            
            // Remove seleção anterior
            document.querySelectorAll('.emocao-option').forEach(function(opt) {
                opt.style.borderColor = 'transparent';
                opt.style.backgroundColor = '';
            });
            
            // Marca selecionado
            this.style.borderColor = '#009ef7';
            this.style.backgroundColor = '#f1faff';
        });
    });
    
    // Submit do formulário
    const form = document.getElementById('form_emocao');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const indicator = btn.querySelector('.indicator-label');
            const progress = btn.querySelector('.indicator-progress');
            
            btn.setAttribute('data-kt-indicator', 'on');
            indicator.style.display = 'none';
            progress.style.display = 'inline-block';
            
            fetch('../api/registrar_emocao.php', {
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
                        location.reload();
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
                Swal.fire({
                    text: "Erro ao registrar emoção",
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
            })
            .finally(() => {
                btn.removeAttribute('data-kt-indicator');
                indicator.style.display = 'inline-block';
                progress.style.display = 'none';
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

