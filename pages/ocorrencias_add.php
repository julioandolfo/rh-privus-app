<?php
/**
 * Adicionar Ocorrência - Versão Melhorada com Campos Dinâmicos
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

if ($_SESSION['usuario']['role'] === 'COLABORADOR') {
    redirect('dashboard.php', 'Você não tem permissão para lançar ocorrências.', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $_GET['colaborador_id'] ?? null;

// Processa POST ANTES de incluir o header (para evitar erro de headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = $_POST['colaborador_id'] ?? null;
    $tipo_ocorrencia_id = $_POST['tipo_ocorrencia_id'] ?? null;
    $tipo = sanitize($_POST['tipo'] ?? ''); // Mantido para compatibilidade
    $descricao = sanitize($_POST['descricao'] ?? '');
    $data_ocorrencia = $_POST['data_ocorrencia'] ?? date('Y-m-d');
    $hora_ocorrencia = $_POST['hora_ocorrencia'] ?? null;
    $tempo_atraso_minutos = !empty($_POST['tempo_atraso_minutos']) ? (int)$_POST['tempo_atraso_minutos'] : null;
    $tipo_ponto = $_POST['tipo_ponto'] ?? null;
    
    if (empty($colaborador_id) || (empty($tipo_ocorrencia_id) && empty($tipo)) || empty($data_ocorrencia)) {
        redirect('ocorrencias_add.php', 'Preencha os campos obrigatórios!', 'error');
    }
    
    // Verifica permissão para lançar ocorrência neste colaborador
    if (!can_access_colaborador($colaborador_id)) {
        redirect('ocorrencias_add.php', 'Você não tem permissão para lançar ocorrência neste colaborador.', 'error');
    }
    
    try {
        // Se não tiver tipo_ocorrencia_id, usa o tipo antigo para compatibilidade
        if (empty($tipo_ocorrencia_id) && !empty($tipo)) {
            $stmt = $pdo->prepare("SELECT id FROM tipos_ocorrencias WHERE codigo = ? LIMIT 1");
            $codigo_map = [
                'atraso' => 'atraso_entrada',
                'falta' => 'falta',
                'ausência injustificada' => 'ausencia_injustificada',
                'falha operacional' => 'falha_operacional',
                'desempenho baixo' => 'desempenho_baixo',
                'comportamento inadequado' => 'comportamento_inadequado',
                'advertência' => 'advertencia',
                'elogio' => 'elogio'
            ];
            $codigo = $codigo_map[$tipo] ?? null;
            if ($codigo) {
                $stmt->execute([$codigo]);
                $tipo_data = $stmt->fetch();
                $tipo_ocorrencia_id = $tipo_data['id'] ?? null;
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO ocorrencias (
                colaborador_id, usuario_id, tipo, tipo_ocorrencia_id, 
                descricao, data_ocorrencia, hora_ocorrencia, 
                tempo_atraso_minutos, tipo_ponto
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id, 
            $usuario['id'], 
            $tipo, 
            $tipo_ocorrencia_id,
            $descricao, 
            $data_ocorrencia,
            $hora_ocorrencia,
            $tempo_atraso_minutos,
            $tipo_ponto
        ]);
        
        $ocorrencia_id = $pdo->lastInsertId();
        
        // Envia email de ocorrência se template estiver ativo
        require_once __DIR__ . '/../includes/email_templates.php';
        enviar_email_ocorrencia($ocorrencia_id);
        
        redirect('colaborador_view.php?id=' . $colaborador_id, 'Ocorrência registrada com sucesso!');
    } catch (PDOException $e) {
        redirect('ocorrencias_add.php', 'Erro ao registrar: ' . $e->getMessage(), 'error');
    }
}

// Busca colaboradores disponíveis
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo' ORDER BY nome_completo");
} elseif ($usuario['role'] === 'RH') {
    $stmt = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_completo");
    $stmt->execute([$usuario['empresa_id']]);
} elseif ($usuario['role'] === 'GESTOR') {
    // Busca setor do gestor
    $stmt_user = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt_user->execute([$usuario['id']]);
    $user_data = $stmt_user->fetch();
    $setor_id = $user_data['setor_id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT id, nome_completo FROM colaboradores WHERE setor_id = ? AND status = 'ativo' ORDER BY nome_completo");
    $stmt->execute([$setor_id]);
} else {
    $colaboradores = [];
}
$colaboradores = isset($stmt) ? $stmt->fetchAll() : [];

// Busca tipos de ocorrências ativos do banco
try {
    $stmt = $pdo->query("SELECT * FROM tipos_ocorrencias WHERE status = 'ativo' ORDER BY categoria, nome");
    $tipos_ocorrencias_db = $stmt->fetchAll();
} catch (PDOException $e) {
    // Se a tabela não existir, usa tipos padrão
    $tipos_ocorrencias_db = [];
}

// Agora inclui o header (após processar POST para evitar erro de headers already sent)
$page_title = 'Nova Ocorrência';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Nova Ocorrência</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item">
                    <a href="ocorrencias_list.php" class="text-muted text-hover-primary">Ocorrências</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Nova</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="ocorrencias_list.php" class="btn btn-sm btn-light">
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
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Registrar Nova Ocorrência</span>
                    <span class="text-muted fw-semibold fs-7">Preencha os dados da ocorrência</span>
                </h3>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <form id="kt_form_ocorrencia" method="POST" class="form">
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                            <select name="colaborador_id" id="colaborador_id" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= $colab['id'] ?>" <?= $colaborador_id == $colab['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($colab['nome_completo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Tipo de Ocorrência</label>
                            <select name="tipo_ocorrencia_id" id="tipo_ocorrencia_id" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php 
                                $categoria_atual = '';
                                foreach ($tipos_ocorrencias_db as $tipo): 
                                    if ($categoria_atual !== $tipo['categoria']):
                                        if ($categoria_atual !== '') echo '</optgroup>';
                                        $categoria_atual = $tipo['categoria'];
                                        $categoria_labels = [
                                            'pontualidade' => 'Pontualidade',
                                            'comportamento' => 'Comportamento',
                                            'desempenho' => 'Desempenho',
                                            'outros' => 'Outros'
                                        ];
                                        echo '<optgroup label="' . htmlspecialchars($categoria_labels[$categoria_atual] ?? ucfirst($categoria_atual)) . '">';
                                    endif;
                                ?>
                                    <option value="<?= $tipo['id'] ?>" 
                                        data-permite-tempo="<?= $tipo['permite_tempo_atraso'] ?>"
                                        data-permite-ponto="<?= $tipo['permite_tipo_ponto'] ?>">
                                        <?= htmlspecialchars($tipo['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($categoria_atual !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="required fw-semibold fs-6 mb-2">Data da Ocorrência</label>
                            <input type="date" name="data_ocorrencia" id="data_ocorrencia" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" required />
                        </div>
                        <div class="col-md-3">
                            <label class="fw-semibold fs-6 mb-2">Hora da Ocorrência</label>
                            <input type="time" name="hora_ocorrencia" id="hora_ocorrencia" class="form-control form-control-solid" />
                        </div>
                    </div>
                    
                    <!-- Campos condicionais -->
                    <div class="row mb-7" id="campo_tempo_atraso" style="display: none;">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Tempo de Atraso (minutos)</label>
                            <input type="number" name="tempo_atraso_minutos" id="tempo_atraso_minutos" class="form-control form-control-solid" min="1" placeholder="Ex: 15" />
                            <small class="text-muted">Informe quantos minutos de atraso</small>
                        </div>
                    </div>
                    
                    <div class="row mb-7" id="campo_tipo_ponto" style="display: none;">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Tipo de Ponto</label>
                            <select name="tipo_ponto" id="tipo_ponto" class="form-select form-select-solid">
                                <option value="">Selecione...</option>
                                <option value="entrada">Entrada</option>
                                <option value="almoco">Almoço</option>
                                <option value="cafe">Café</option>
                                <option value="saida">Saída</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Descrição</label>
                            <textarea name="descricao" id="descricao" class="form-control form-control-solid" rows="5" placeholder="Descreva detalhadamente a ocorrência..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Campo oculto para compatibilidade -->
                    <input type="hidden" name="tipo" id="tipo" value="" />
                    
                    <div class="text-center pt-15">
                        <a href="ocorrencias_list.php" class="btn btn-light me-3">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Registrar Ocorrência</span>
                        </button>
                    </div>
                </form>
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<script>
// Preenche hora atual do computador do usuário
(function() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const horaAtual = hours + ':' + minutes;
    
    // Aguarda o DOM estar pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            const campoHora = document.getElementById('hora_ocorrencia');
            if (campoHora) {
                campoHora.value = horaAtual;
            }
        });
    } else {
        const campoHora = document.getElementById('hora_ocorrencia');
        if (campoHora) {
            campoHora.value = horaAtual;
        }
    }
})();

// Mostra/esconde campos condicionais baseado no tipo selecionado
document.getElementById('tipo_ocorrencia_id').addEventListener('change', function() {
    const tipoId = this.value;
    const option = this.options[this.selectedIndex];
    const permiteTempo = option.getAttribute('data-permite-tempo') === '1';
    const permitePonto = option.getAttribute('data-permite-ponto') === '1';
    
    // Mostra/esconde campo de tempo de atraso
    const campoTempo = document.getElementById('campo_tempo_atraso');
    if (permiteTempo) {
        campoTempo.style.display = 'block';
        document.getElementById('tempo_atraso_minutos').required = true;
    } else {
        campoTempo.style.display = 'none';
        document.getElementById('tempo_atraso_minutos').value = '';
        document.getElementById('tempo_atraso_minutos').required = false;
    }
    
    // Mostra/esconde campo de tipo de ponto
    const campoPonto = document.getElementById('campo_tipo_ponto');
    if (permitePonto) {
        campoPonto.style.display = 'block';
        document.getElementById('tipo_ponto').required = true;
    } else {
        campoPonto.style.display = 'none';
        document.getElementById('tipo_ponto').value = '';
        document.getElementById('tipo_ponto').required = false;
    }
    
    // Atualiza campo oculto tipo para compatibilidade
    document.getElementById('tipo').value = option.text.trim();
});

// Validação do formulário
document.getElementById('kt_form_ocorrencia').addEventListener('submit', function(e) {
    const tipoId = document.getElementById('tipo_ocorrencia_id').value;
    const option = document.getElementById('tipo_ocorrencia_id').options[document.getElementById('tipo_ocorrencia_id').selectedIndex];
    const permiteTempo = option.getAttribute('data-permite-tempo') === '1';
    const permitePonto = option.getAttribute('data-permite-ponto') === '1';
    
    // Valida tempo de atraso se obrigatório
    if (permiteTempo) {
        const tempoAtraso = document.getElementById('tempo_atraso_minutos').value;
        if (!tempoAtraso || tempoAtraso <= 0) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: 'Informe o tempo de atraso em minutos!',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok, entendi!',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            } else {
                alert('Informe o tempo de atraso em minutos!');
            }
            return false;
        }
    }
    
    // Valida tipo de ponto se obrigatório
    if (permitePonto) {
        const tipoPonto = document.getElementById('tipo_ponto').value;
        if (!tipoPonto) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: 'Selecione o tipo de ponto!',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok, entendi!',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            } else {
                alert('Selecione o tipo de ponto!');
            }
            return false;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
