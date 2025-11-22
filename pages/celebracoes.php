<?php
/**
 * Página de Gestão de Celebrações
 */

$page_title = 'Celebrações';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('celebracoes.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Lista celebrações
$where = ["cel.status = 'ativo'"];
$params = [];

// Se for COLABORADOR, vê celebrações direcionadas a ele OU celebrações públicas que se aplicam a ele
if ($usuario['role'] === 'COLABORADOR' && $usuario['colaborador_id']) {
    // Busca dados do colaborador para filtrar celebrações públicas
    $stmt_colab = $pdo->prepare("SELECT empresa_id, setor_id, cargo_id FROM colaboradores WHERE id = ?");
    $stmt_colab->execute([$usuario['colaborador_id']]);
    $colab_data = $stmt_colab->fetch();
    
    $where_colaborador = [];
    $params_colaborador = [];
    
    // Celebrações específicas para ele
    $where_colaborador[] = "cel.destinatario_id = ?";
    $params_colaborador[] = $usuario['colaborador_id'];
    
    // Celebrações públicas que se aplicam a ele
    if ($colab_data) {
        // Celebrações para todos
        $where_colaborador[] = "cel.publico_alvo = 'todos'";
        
        // Celebrações para a empresa dele
        if ($colab_data['empresa_id']) {
            $where_colaborador[] = "(cel.publico_alvo = 'empresa' AND cel.empresa_id = ?)";
            $params_colaborador[] = $colab_data['empresa_id'];
        }
        
        // Celebrações para o setor dele
        if ($colab_data['setor_id']) {
            $where_colaborador[] = "(cel.publico_alvo = 'setor' AND cel.setor_id = ?)";
            $params_colaborador[] = $colab_data['setor_id'];
        }
        
        // Celebrações para o cargo dele
        if ($colab_data['cargo_id']) {
            $where_colaborador[] = "(cel.publico_alvo = 'cargo' AND cel.cargo_id = ?)";
            $params_colaborador[] = $colab_data['cargo_id'];
        }
    }
    
    $where[] = "(" . implode(' OR ', $where_colaborador) . ")";
    $params = array_merge($params, $params_colaborador);
}

$sql = "
    SELECT cel.*,
           cr.nome_completo as remetente_nome,
           cr.foto as remetente_foto,
           cd.nome_completo as destinatario_nome,
           cd.foto as destinatario_foto,
           u.nome as remetente_usuario_nome
    FROM celebracoes cel
    LEFT JOIN colaboradores cr ON cel.remetente_id = cr.id
    LEFT JOIN usuarios u ON cel.remetente_usuario_id = u.id
    LEFT JOIN colaboradores cd ON cel.destinatario_id = cd.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY cel.data_celebração DESC, cel.created_at DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$celebracoes = $stmt->fetchAll();

// Busca colaboradores disponíveis
require_once __DIR__ . '/../includes/select_colaborador.php';
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);

// Busca empresas para filtro
$empresas = [];
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) AND status = 'ativo' ORDER BY nome_fantasia");
        $stmt->execute($usuario['empresas_ids']);
        $empresas = $stmt->fetchAll();
    }
}

// Busca setores para filtro
$setores = [];
if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
    $stmt = $pdo->query("SELECT id, nome_setor, empresa_id FROM setores WHERE status = 'ativo' ORDER BY nome_setor");
    $setores = $stmt->fetchAll();
}

// Busca cargos para filtro
$cargos = [];
if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
    $stmt = $pdo->query("SELECT id, nome_cargo FROM cargos WHERE status = 'ativo' ORDER BY nome_cargo");
    $cargos = $stmt->fetchAll();
}
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2><?= $usuario['role'] === 'COLABORADOR' ? 'Minhas Celebrações' : 'Celebrações' ?></h2>
                        </div>
                        <?php if ($usuario['role'] !== 'COLABORADOR'): ?>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-criar-celebração">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Celebração
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body pt-0">
                        <?php if (empty($celebracoes)): ?>
                        <div class="alert alert-info d-flex align-items-center p-5">
                            <i class="ki-duotone ki-information-5 fs-2hx text-primary me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <h4 class="mb-1">Nenhuma celebração encontrada</h4>
                                <span><?= $usuario['role'] === 'COLABORADOR' ? 'Você ainda não recebeu nenhuma celebração.' : 'Não há celebrações cadastradas no momento.' ?></span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="row g-5">
                            <?php foreach ($celebracoes as $cel): ?>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <?php if ($cel['imagem']): ?>
                            <img src="<?= htmlspecialchars($cel['imagem']) ?>" class="card-img-top" alt="" style="height: 200px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?php
                                    $tipos_icon = [
                                        'aniversario' => '🎂',
                                        'promocao' => '📈',
                                        'conquista' => '🏆',
                                        'reconhecimento' => '⭐',
                                        'outro' => '🎉'
                                    ];
                                    echo ($tipos_icon[$cel['tipo']] ?? '🎉') . ' ' . htmlspecialchars($cel['titulo']);
                                    ?>
                                </h5>
                                
                                <p class="text-muted mb-2">
                                    <small>
                                        De: <?= htmlspecialchars($cel['remetente_nome'] ?? $cel['remetente_usuario_nome'] ?? 'Sistema') ?><br>
                                        Para: <?php
                                        if ($cel['destinatario_nome']) {
                                            echo htmlspecialchars($cel['destinatario_nome']);
                                        } else {
                                            $publico_alvo_labels = [
                                                'todos' => 'Todos os colaboradores',
                                                'empresa' => 'Empresa',
                                                'setor' => 'Setor',
                                                'cargo' => 'Cargo',
                                                'especifico' => 'Colaboradores específicos'
                                            ];
                                            echo $publico_alvo_labels[$cel['publico_alvo']] ?? 'Público específico';
                                        }
                                        ?>
                                    </small>
                                </p>
                                
                                <?php if ($cel['descricao']): ?>
                                <p class="card-text"><?= nl2br(htmlspecialchars(substr($cel['descricao'], 0, 100))) ?><?= strlen($cel['descricao']) > 100 ? '...' : '' ?></p>
                                <?php endif; ?>
                                
                                <p class="text-muted mb-0">
                                    <small><?= date('d/m/Y', strtotime($cel['data_celebração'])) ?></small>
                                </p>
                            </div>
                            <div class="card-footer">
                                <a href="celebração_view.php?id=<?= $cel['id'] ?>" class="btn btn-sm btn-primary">
                                    Ver Detalhes
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Celebração -->
<?php if ($usuario['role'] !== 'COLABORADOR'): ?>
<div class="modal fade" id="modal-criar-celebração" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Celebração</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-criar-celebração" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Público Alvo *</label>
                        <select name="publico_alvo" class="form-select form-control-solid" id="publico-alvo-celebração" required>
                            <option value="todos">🎉 Todos os Colaboradores</option>
                            <option value="especifico">👤 Colaborador Específico</option>
                            <option value="empresa">🏢 Por Empresa</option>
                            <option value="setor">📁 Por Setor</option>
                            <option value="cargo">💼 Por Cargo</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="container-destinatario-especifico" style="display:none;">
                        <label class="required fw-semibold fs-6 mb-2">Destinatário</label>
                        <?= render_select_colaborador('destinatario_id', 'destinatario_id', null, $colaboradores, false) ?>
                    </div>
                    
                    <div class="mb-3" id="container-empresa" style="display:none;">
                        <label class="required fw-semibold fs-6 mb-2">Empresa</label>
                        <select name="empresa_id" class="form-select form-control-solid" id="empresa-celebração">
                            <option value="">Selecione...</option>
                            <?php foreach ($empresas as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nome_fantasia']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="container-setor" style="display:none;">
                        <label class="required fw-semibold fs-6 mb-2">Setor</label>
                        <select name="setor_id" class="form-select form-control-solid" id="setor-celebração">
                            <option value="">Selecione...</option>
                            <?php foreach ($setores as $setor): ?>
                            <option value="<?= $setor['id'] ?>" data-empresa-id="<?= $setor['empresa_id'] ?>"><?= htmlspecialchars($setor['nome_setor']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="container-cargo" style="display:none;">
                        <label class="required fw-semibold fs-6 mb-2">Cargo</label>
                        <select name="cargo_id" class="form-select form-control-solid" id="cargo-celebração">
                            <option value="">Selecione...</option>
                            <?php foreach ($cargos as $cargo): ?>
                            <option value="<?= $cargo['id'] ?>"><?= htmlspecialchars($cargo['nome_cargo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Tipo *</label>
                        <select name="tipo" class="form-select form-control-solid" required>
                            <option value="reconhecimento">⭐ Reconhecimento</option>
                            <option value="aniversario">🎂 Aniversário</option>
                            <option value="promocao">📈 Promoção</option>
                            <option value="conquista">🏆 Conquista</option>
                            <option value="outro">🎉 Outro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Título *</label>
                        <input type="text" name="titulo" class="form-control form-control-solid" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" class="form-control form-control-solid" rows="4"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-semibold fs-6 mb-2">Imagem</label>
                        <input type="file" name="imagem" id="imagem-celebração" class="form-control form-control-solid" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <small class="text-muted">Formatos aceitos: JPG, PNG, GIF, WEBP (máximo 5MB)</small>
                        <div id="preview-imagem-celebração" class="mt-3" style="display: none;">
                            <img id="img-preview-celebração" src="" alt="Preview" class="img-thumbnail" style="max-width: 300px; max-height: 300px; object-fit: cover;">
                            <button type="button" class="btn btn-sm btn-danger mt-2" id="btn-remover-imagem-celebração">
                                <i class="ki-duotone ki-cross fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Remover Imagem
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="required fw-semibold fs-6 mb-2">Data da Celebração *</label>
                        <input type="date" name="data_celebração" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-salvar-celebração">Salvar</button>
            </div>
        </div>
    </div>
</div>

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
</style>
<!--end::Select2 CSS-->

<script>
// Mostra/esconde campos baseado no público alvo
document.getElementById('publico-alvo-celebração')?.addEventListener('change', function() {
    const publicoAlvo = this.value;
    
    document.getElementById('container-destinatario-especifico').style.display = publicoAlvo === 'especifico' ? 'block' : 'none';
    document.getElementById('container-empresa').style.display = publicoAlvo === 'empresa' ? 'block' : 'none';
    document.getElementById('container-setor').style.display = publicoAlvo === 'setor' ? 'block' : 'none';
    document.getElementById('container-cargo').style.display = publicoAlvo === 'cargo' ? 'block' : 'none';
    
    // Requisitos
    const destinatarioSelect = document.getElementById('destinatario_id');
    const empresaSelect = document.getElementById('empresa-celebração');
    const setorSelect = document.getElementById('setor-celebração');
    const cargoSelect = document.getElementById('cargo-celebração');
    
    if (destinatarioSelect) destinatarioSelect.required = publicoAlvo === 'especifico';
    if (empresaSelect) empresaSelect.required = publicoAlvo === 'empresa';
    if (setorSelect) setorSelect.required = publicoAlvo === 'setor';
    if (cargoSelect) cargoSelect.required = publicoAlvo === 'cargo';
});

// Filtra setores por empresa
document.getElementById('empresa-celebração')?.addEventListener('change', function() {
    const empresaId = this.value;
    const setorSelect = document.getElementById('setor-celebração');
    if (!setorSelect) return;
    
    Array.from(setorSelect.options).forEach(option => {
        if (option.value === '') return;
        const optionEmpresaId = option.getAttribute('data-empresa-id');
        option.style.display = (!empresaId || optionEmpresaId == empresaId) ? '' : 'none';
    });
    
    if (empresaId) {
        setorSelect.value = '';
    }
});

// Inicializa Select2 no modal
document.getElementById('modal-criar-celebração')?.addEventListener('shown.bs.modal', function() {
    setTimeout(function() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;
        
        const $select = jQuery('#destinatario_id');
        if ($select.length && !$select.hasClass('select2-hidden-accessible')) {
            $select.select2({
                placeholder: 'Selecione um colaborador...',
                allowClear: true,
                width: '100%',
                dropdownParent: jQuery('#modal-criar-celebração'),
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
        }
    }, 350);
});

// Preview de imagem
const imagemInput = document.getElementById('imagem-celebração');
const previewDiv = document.getElementById('preview-imagem-celebração');
const previewImg = document.getElementById('img-preview-celebração');
const btnRemoverImagem = document.getElementById('btn-remover-imagem-celebração');

if (imagemInput) {
    imagemInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Valida tamanho (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Arquivo muito grande. Máximo 5MB');
                e.target.value = '';
                return;
            }
            
            // Valida tipo
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Formato não permitido. Use JPG, PNG, GIF ou WEBP');
                e.target.value = '';
                return;
            }
            
            // Mostra preview
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewDiv.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
}

if (btnRemoverImagem) {
    btnRemoverImagem.addEventListener('click', function() {
        if (imagemInput) {
            imagemInput.value = '';
        }
        previewDiv.style.display = 'none';
        previewImg.src = '';
    });
}

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

document.getElementById('btn-salvar-celebração')?.addEventListener('click', function() {
    const form = document.getElementById('form-criar-celebração');
    if (!form) return;
    
    // Validação no frontend
    const publicoAlvo = form.querySelector('[name="publico_alvo"]').value;
    const titulo = form.querySelector('[name="titulo"]').value.trim();
    const destinatarioId = form.querySelector('[name="destinatario_id"]')?.value;
    const empresaId = form.querySelector('[name="empresa_id"]')?.value;
    const setorId = form.querySelector('[name="setor_id"]')?.value;
    const cargoId = form.querySelector('[name="cargo_id"]')?.value;
    
    if (!titulo || titulo.length === 0) {
        showAlert('error', 'Erro de Validação', 'Por favor, preencha o título da celebração.');
        form.querySelector('[name="titulo"]').focus();
        return;
    }
    
    if (publicoAlvo === 'especifico' && (!destinatarioId || destinatarioId === '')) {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione um destinatário.');
        return;
    }
    
    if (publicoAlvo === 'empresa' && (!empresaId || empresaId === '')) {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione uma empresa.');
        return;
    }
    
    if (publicoAlvo === 'setor' && (!setorId || setorId === '')) {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione um setor.');
        return;
    }
    
    if (publicoAlvo === 'cargo' && (!cargoId || cargoId === '')) {
        showAlert('error', 'Erro de Validação', 'Por favor, selecione um cargo.');
        return;
    }
    
    const formData = new FormData(form);
    
    // Adiciona timestamp único para evitar duplicação
    const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    formData.append('request_id', requestId);
    
    // Validação de imagem antes de enviar
    const imagemFile = document.getElementById('imagem-celebração').files[0];
    if (imagemFile) {
        if (imagemFile.size > 5 * 1024 * 1024) {
            showAlert('error', 'Erro de Validação', 'Arquivo muito grande. Máximo 5MB.');
            return;
        }
    }
    
    const btnSalvar = document.getElementById('btn-salvar-celebração');
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    
    fetch('<?= get_base_url() ?>/api/celebracoes/criar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Sucesso!', 'Celebração criada com sucesso!');
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', 'Erro', data.message || 'Erro ao criar celebração');
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = 'Salvar';
        }
    })
    .catch(error => {
        showAlert('error', 'Erro', 'Erro ao criar celebração. Tente novamente.');
        console.error(error);
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = 'Salvar';
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

