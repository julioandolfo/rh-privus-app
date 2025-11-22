<?php
/**
 * Página de Gestão de Reuniões 1:1
 */

$page_title = 'Reuniões 1:1';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('reunioes_1on1.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Lista reuniões
$where = ["1=1"];
$params = [];

// Se for GESTOR, só vê suas próprias reuniões como líder
if ($usuario['role'] === 'GESTOR') {
    $stmt_colab = $pdo->prepare("SELECT colaborador_id FROM usuarios WHERE id = ?");
    $stmt_colab->execute([$usuario['id']]);
    $user_colab = $stmt_colab->fetch();
    if ($user_colab && $user_colab['colaborador_id']) {
        $where[] = "r.lider_id = ?";
        $params[] = $user_colab['colaborador_id'];
    }
}

// Se for COLABORADOR, só vê suas próprias reuniões
if ($usuario['role'] === 'COLABORADOR' && $usuario['colaborador_id']) {
    $where[] = "r.liderado_id = ?";
    $params[] = $usuario['colaborador_id'];
}

$sql = "
    SELECT r.*,
           cl.nome_completo as lider_nome,
           cl.foto as lider_foto,
           cd.nome_completo as liderado_nome,
           cd.foto as liderado_foto
    FROM reunioes_1on1 r
    INNER JOIN colaboradores cl ON r.lider_id = cl.id
    INNER JOIN colaboradores cd ON r.liderado_id = cd.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.data_reuniao DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reunioes = $stmt->fetchAll();

// Busca colaboradores disponíveis
require_once __DIR__ . '/../includes/select_colaborador.php';
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);

// Busca líderes (colaboradores que têm liderados OU todos se for ADMIN/RH)
$lideres = [];
if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
    // Para ADMIN/RH, pode selecionar qualquer colaborador como líder
    $lideres = $colaboradores;
} else {
    // Para GESTOR, só mostra líderes que têm liderados
    $stmt_lideres = $pdo->query("
        SELECT DISTINCT c.id, c.nome_completo, c.foto
        FROM colaboradores c
        WHERE EXISTS (
            SELECT 1 FROM colaboradores c2 WHERE c2.lider_id = c.id AND c2.status = 'ativo'
        )
        AND c.status = 'ativo'
        ORDER BY c.nome_completo
    ");
    $lideres = $stmt_lideres->fetchAll();
}

// Busca líder do colaborador logado (se for colaborador)
$meu_lider = null;
if ($usuario['role'] === 'COLABORADOR' && $usuario['colaborador_id']) {
    $stmt_lider = $pdo->prepare("SELECT lider_id FROM colaboradores WHERE id = ?");
    $stmt_lider->execute([$usuario['colaborador_id']]);
    $lider_id = $stmt_lider->fetchColumn();
    if ($lider_id) {
        $stmt_lider_data = $pdo->prepare("SELECT id, nome_completo, foto FROM colaboradores WHERE id = ? AND status = 'ativo'");
        $stmt_lider_data->execute([$lider_id]);
        $meu_lider = $stmt_lider_data->fetch();
    }
}
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Reuniões 1:1</h2>
                        </div>
                        <div class="card-toolbar">
                            <?php if ($usuario['role'] === 'COLABORADOR'): ?>
                                <?php if ($meu_lider): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-solicitar-reuniao">
                                    <i class="ki-duotone ki-plus fs-2"></i>
                                    Solicitar Reunião 1:1
                                </button>
                                <?php else: ?>
                                <span class="text-muted">Você não possui um líder atribuído</span>
                                <?php endif; ?>
                            <?php else: ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-criar-reuniao">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Reunião
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Líder</th>
                                        <th>Liderado</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reunioes as $reuniao): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($reuniao['lider_foto']): ?>
                                                <img src="<?= htmlspecialchars($reuniao['lider_foto']) ?>" class="rounded-circle me-2" width="30" height="30" alt="">
                                                <?php else: ?>
                                                <div class="symbol symbol-circle symbol-30px me-2">
                                                    <div class="symbol-label bg-primary text-white" style="font-size: 12px;">
                                                        <?= strtoupper(substr($reuniao['lider_nome'], 0, 1)) ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($reuniao['lider_nome']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($reuniao['liderado_foto']): ?>
                                                <img src="<?= htmlspecialchars($reuniao['liderado_foto']) ?>" class="rounded-circle me-2" width="30" height="30" alt="">
                                                <?php else: ?>
                                                <div class="symbol symbol-circle symbol-30px me-2">
                                                    <div class="symbol-label bg-success text-white" style="font-size: 12px;">
                                                        <?= strtoupper(substr($reuniao['liderado_nome'], 0, 1)) ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($reuniao['liderado_nome']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($reuniao['data_reuniao'])) ?></td>
                                        <td>
                                            <?php
                                            $badge_class = [
                                                'agendada' => 'badge-warning',
                                                'solicitada' => 'badge-info',
                                                'realizada' => 'badge-success',
                                                'cancelada' => 'badge-danger',
                                                'reagendada' => 'badge-info'
                                            ];
                                            $status_class = $badge_class[$reuniao['status']] ?? 'badge-secondary';
                                            ?>
                                            <span class="badge <?= $status_class ?>"><?= ucfirst($reuniao['status']) ?></span>
                                        </td>
                                        <td>
                                            <a href="reuniao_1on1_view.php?id=<?= $reuniao['id'] ?>" class="btn btn-sm btn-primary">
                                                Ver
                                            </a>
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

<!-- Modal Criar Reunião -->
<div class="modal fade" id="modal-criar-reuniao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Reunião 1:1</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-criar-reuniao">
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Líder</label>
                        <?= render_select_colaborador('lider_id', 'select-lider', null, $lideres, true) ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Liderado</label>
                        <?= render_select_colaborador('liderado_id', 'select-liderado', null, $colaboradores, true) ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Data</label>
                        <input type="date" name="data_reuniao" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-semibold fs-6 mb-2">Hora Início</label>
                            <input type="time" name="hora_inicio" class="form-control form-control-solid">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-semibold fs-6 mb-2">Hora Fim</label>
                            <input type="time" name="hora_fim" class="form-control form-control-solid">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-semibold fs-6 mb-2">Assuntos Tratados</label>
                        <textarea name="assuntos_tratados" class="form-control form-control-solid" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-semibold fs-6 mb-2">Próximos Passos</label>
                        <textarea name="proximos_passos" class="form-control form-control-solid" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-salvar-reuniao">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Solicitar Reunião (para colaboradores) -->
<?php if ($usuario['role'] === 'COLABORADOR'): ?>
<div class="modal fade" id="modal-solicitar-reuniao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Solicitar Reunião 1:1</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!$meu_lider): ?>
                <div class="alert alert-warning d-flex align-items-center p-5">
                    <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1">Líder não atribuído</h4>
                        <span>Você não possui um líder atribuído. Entre em contato com o RH para solicitar uma reunião.</span>
                    </div>
                </div>
                <?php else: ?>
                <form id="form-solicitar-reuniao">
                    <input type="hidden" name="lider_id" value="<?= $meu_lider['id'] ?>">
                    <input type="hidden" name="liderado_id" value="<?= $usuario['colaborador_id'] ?>">
                    
                    <div class="mb-3">
                        <label class="fw-semibold fs-6 mb-2">Líder</label>
                        <div class="d-flex align-items-center p-3 bg-light rounded">
                            <?php if ($meu_lider['foto']): ?>
                            <img src="<?= htmlspecialchars($meu_lider['foto']) ?>" class="rounded-circle me-3" width="50" height="50" alt="" style="object-fit: cover;">
                            <?php else: ?>
                            <div class="symbol symbol-circle symbol-50px me-3">
                                <div class="symbol-label bg-primary text-white fs-3">
                                    <?= strtoupper(substr($meu_lider['nome_completo'], 0, 1)) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span class="fw-bold fs-5"><?= htmlspecialchars($meu_lider['nome_completo']) ?></span>
                                <br><small class="text-muted">Seu líder direto</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Data Sugerida *</label>
                        <input type="date" name="data_reuniao" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                        <small class="text-muted">Selecione uma data futura</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-semibold fs-6 mb-2">Hora Sugerida</label>
                            <input type="time" name="hora_inicio" class="form-control form-control-solid">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-semibold fs-6 mb-2">Motivo da Solicitação</label>
                            <select name="motivo_solicitacao" class="form-select form-control-solid">
                                <option value="">Selecione...</option>
                                <option value="feedback">Solicitar Feedback</option>
                                <option value="desenvolvimento">Desenvolvimento Profissional</option>
                                <option value="dificuldade">Dificuldade no Trabalho</option>
                                <option value="sugestao">Sugestão/Melhoria</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Descrição do Motivo *</label>
                        <textarea name="assuntos_tratados" class="form-control form-control-solid" rows="4" placeholder="Descreva o motivo da solicitação da reunião..." required></textarea>
                        <small class="text-muted">Seja específico sobre o que deseja discutir</small>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <?php if ($meu_lider): ?>
                <button type="button" class="btn btn-primary" id="btn-solicitar-reuniao">Solicitar</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
    }
</style>
<!--end::Select2 CSS-->

<script>
// Inicializa Select2 nos modais
function initSelect2OnModal(modalId, selectId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.addEventListener('shown.bs.modal', function() {
        setTimeout(function() {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                console.error('jQuery ou Select2 não disponível');
                return;
            }
            
            const $select = jQuery('#' + selectId);
            if ($select.length === 0) return;
            
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            
            $select.select2({
                placeholder: 'Selecione...',
                allowClear: true,
                width: '100%',
                dropdownParent: jQuery('#' + modalId),
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
        }, 350);
    });
}

// Inicializa Select2 para modal de criar reunião
initSelect2OnModal('modal-criar-reuniao', 'select-lider');
initSelect2OnModal('modal-criar-reuniao', 'select-liderado');

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
    
    const existingAlert = document.getElementById('custom-alert');
    if (existingAlert) existingAlert.remove();
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(function() {
        const alert = document.getElementById('custom-alert');
        if (alert) alert.remove();
    }, 5000);
}

// Salva reunião
document.getElementById('btn-salvar-reuniao')?.addEventListener('click', function() {
    const form = document.getElementById('form-criar-reuniao');
    if (!form) return;
    
    const liderId = form.querySelector('[name="lider_id"]').value;
    const lideradoId = form.querySelector('[name="liderado_id"]').value;
    const dataReuniao = form.querySelector('[name="data_reuniao"]').value;
    
    if (!liderId || liderId === '') {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione um líder.');
        return;
    }
    
    if (!lideradoId || lideradoId === '') {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione um liderado.');
        return;
    }
    
    if (!dataReuniao) {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione a data da reunião.');
        return;
    }
    
    const formData = new FormData(form);
    const btnSalvar = document.getElementById('btn-salvar-reuniao');
    
    // Adiciona timestamp único para evitar duplicação
    const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    formData.append('request_id', requestId);
    
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    
    fetch('<?= get_base_url() ?>/api/reunioes_1on1/criar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Sucesso!', 'Reunião criada com sucesso!');
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', 'Erro', data.message || 'Erro ao criar reunião');
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = 'Salvar';
        }
    })
    .catch(error => {
        showAlert('error', 'Erro', 'Erro ao criar reunião. Tente novamente.');
        console.error(error);
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = 'Salvar';
    });
});

// Solicita reunião (colaborador)
const btnSolicitar = document.getElementById('btn-solicitar-reuniao');
if (btnSolicitar) {
    btnSolicitar.addEventListener('click', function() {
        const form = document.getElementById('form-solicitar-reuniao');
        if (!form) {
            alert('Formulário não encontrado');
            return;
        }
        
        // Validação básica
        const dataReuniao = form.querySelector('[name="data_reuniao"]').value;
        const assuntos = form.querySelector('[name="assuntos_tratados"]').value;
        
        if (!dataReuniao) {
            showAlert('error', 'Erro de Validação', 'Por favor, selecione uma data.');
            return;
        }
        
        if (!assuntos || assuntos.trim().length < 10) {
            showAlert('error', 'Erro de Validação', 'Por favor, descreva o motivo da reunião (mínimo 10 caracteres).');
            form.querySelector('[name="assuntos_tratados"]').focus();
            return;
        }
        
        const formData = new FormData(form);
        formData.append('status', 'solicitada');
        
        // Adiciona timestamp único para evitar duplicação
        const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        formData.append('request_id', requestId);
        
        btnSolicitar.disabled = true;
        btnSolicitar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
        
        fetch('<?= get_base_url() ?>/api/reunioes_1on1/criar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Sucesso!', 'Solicitação de reunião enviada com sucesso! Seu líder será notificado.');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert('error', 'Erro', data.message || 'Erro ao solicitar reunião');
                btnSolicitar.disabled = false;
                btnSolicitar.innerHTML = 'Solicitar';
            }
        })
        .catch(error => {
            showAlert('error', 'Erro', 'Erro ao solicitar reunião. Tente novamente.');
            console.error(error);
            btnSolicitar.disabled = false;
            btnSolicitar.innerHTML = 'Solicitar';
        });
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

