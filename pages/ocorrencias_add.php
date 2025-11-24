<?php
/**
 * Adicionar Ocorrência - Versão Melhorada com Campos Dinâmicos
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

require_page_permission('ocorrencias_add.php');

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
    $severidade = $_POST['severidade'] ?? null;
    $tags = !empty($_POST['tags']) ? json_encode($_POST['tags']) : null;
    $campos_dinamicos_valores = !empty($_POST['campos_dinamicos']) ? json_encode($_POST['campos_dinamicos']) : null;
    
    if (empty($colaborador_id) || (empty($tipo_ocorrencia_id) && empty($tipo)) || empty($data_ocorrencia)) {
        redirect('ocorrencias_add.php', 'Preencha os campos obrigatórios!', 'error');
    }
    
    // Verifica permissão para lançar ocorrência neste colaborador
    if (!can_access_colaborador($colaborador_id)) {
        redirect('ocorrencias_add.php', 'Você não tem permissão para lançar ocorrência neste colaborador.', 'error');
    }
    
    try {
        // Busca dados do tipo de ocorrência
        $tipo_ocorrencia_data = null;
        if ($tipo_ocorrencia_id) {
            $stmt = $pdo->prepare("SELECT * FROM tipos_ocorrencias WHERE id = ?");
            $stmt->execute([$tipo_ocorrencia_id]);
            $tipo_ocorrencia_data = $stmt->fetch();
        }
        
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
                if ($tipo_ocorrencia_id) {
                    $stmt = $pdo->prepare("SELECT * FROM tipos_ocorrencias WHERE id = ?");
                    $stmt->execute([$tipo_ocorrencia_id]);
                    $tipo_ocorrencia_data = $stmt->fetch();
                }
            }
        }
        
        // Valida campos dinâmicos se existirem
        if ($tipo_ocorrencia_data && $tipo_ocorrencia_id) {
            $campos_dinamicos = get_campos_dinamicos_tipo($tipo_ocorrencia_id);
            if (!empty($campos_dinamicos) && !empty($_POST['campos_dinamicos'])) {
                $erros_validacao = validar_campos_dinamicos($campos_dinamicos, $_POST['campos_dinamicos']);
                if (!empty($erros_validacao)) {
                    redirect('ocorrencias_add.php', implode('<br>', $erros_validacao), 'error');
                }
            }
        }
        
        // Determina severidade (usa do tipo se não informada)
        if (!$severidade && $tipo_ocorrencia_data) {
            $severidade = $tipo_ocorrencia_data['severidade'] ?? 'moderada';
        }
        
        // Determina status de aprovação
        $status_aprovacao = 'aprovada';
        if ($tipo_ocorrencia_data && $tipo_ocorrencia_data['requer_aprovacao']) {
            $status_aprovacao = 'pendente';
        }
        
        // Calcula desconto se necessário
        $valor_desconto = null;
        if ($tipo_ocorrencia_data && $tipo_ocorrencia_data['calcula_desconto']) {
            // Será calculado após inserir a ocorrência
        }
        
        // Usa template de descrição se não houver descrição
        if (empty($descricao) && $tipo_ocorrencia_data && !empty($tipo_ocorrencia_data['template_descricao'])) {
            $descricao = $tipo_ocorrencia_data['template_descricao'];
            // Substitui variáveis
            $stmt_colab = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
            $stmt_colab->execute([$colaborador_id]);
            $colab = $stmt_colab->fetch();
            $descricao = str_replace('{colaborador}', $colab['nome_completo'] ?? '', $descricao);
            $descricao = str_replace('{data}', formatar_data($data_ocorrencia), $descricao);
            $descricao = str_replace('{hora}', $hora_ocorrencia ?? '', $descricao);
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO ocorrencias (
                colaborador_id, usuario_id, tipo, tipo_ocorrencia_id, 
                descricao, data_ocorrencia, hora_ocorrencia, 
                tempo_atraso_minutos, tipo_ponto, severidade, status_aprovacao,
                tags, campos_dinamicos
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $tipo_ponto,
            $severidade,
            $status_aprovacao,
            $tags,
            $campos_dinamicos_valores
        ]);
        
        $ocorrencia_id = $pdo->lastInsertId();
        
        // Verifica se deve descontar do banco de horas
        $desconta_banco_horas = isset($_POST['desconta_banco_horas']) && $_POST['desconta_banco_horas'] == '1';
        
        if ($desconta_banco_horas && $tipo_ocorrencia_data && $tipo_ocorrencia_data['permite_desconto_banco_horas']) {
            // Desconta do banco de horas
            require_once __DIR__ . '/../includes/banco_horas_functions.php';
            
            $resultado = descontar_horas_banco_ocorrencia($ocorrencia_id, $usuario['id']);
            
            if (!$resultado['success']) {
                // Log erro mas não impede criação da ocorrência
                error_log('Erro ao descontar banco de horas: ' . $resultado['error']);
            }
        } else {
            // Comportamento atual: calcula desconto em dinheiro
            if ($tipo_ocorrencia_data && $tipo_ocorrencia_data['calcula_desconto']) {
                $valor_desconto = calcular_desconto_ocorrencia($ocorrencia_id);
                if ($valor_desconto > 0) {
                    $stmt = $pdo->prepare("UPDATE ocorrencias SET valor_desconto = ? WHERE id = ?");
                    $stmt->execute([$valor_desconto, $ocorrencia_id]);
                }
            }
        }
        
        // Processa anexos
        if (!empty($_FILES['anexos']['name'][0])) {
            foreach ($_FILES['anexos']['name'] as $key => $name) {
                if (!empty($name)) {
                    $file = [
                        'name' => $name,
                        'type' => $_FILES['anexos']['type'][$key],
                        'tmp_name' => $_FILES['anexos']['tmp_name'][$key],
                        'error' => $_FILES['anexos']['error'][$key],
                        'size' => $_FILES['anexos']['size'][$key]
                    ];
                    
                    $upload_result = upload_anexo_ocorrencia($file, $ocorrencia_id);
                    if ($upload_result['success']) {
                        $stmt = $pdo->prepare("
                            INSERT INTO ocorrencias_anexos 
                            (ocorrencia_id, nome_arquivo, caminho_arquivo, tipo_mime, tamanho_bytes, uploaded_by)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $ocorrencia_id,
                            $upload_result['filename'],
                            $upload_result['path'],
                            $upload_result['mime_type'],
                            $upload_result['size'],
                            $usuario['id']
                        ]);
                    }
                }
            }
        }
        
        // Registra histórico
        registrar_historico_ocorrencia($ocorrencia_id, 'criada', $usuario['id'], null, null, null, 'Ocorrência criada');
        
        // Verifica e aplica advertências progressivas
        if ($tipo_ocorrencia_data && $tipo_ocorrencia_data['conta_advertencia']) {
            verificar_advertencias_progressivas($colaborador_id, $tipo_ocorrencia_id);
        }
        
        // Envia notificações
        enviar_notificacoes_ocorrencia($ocorrencia_id);
        
        // Envia email de ocorrência se template estiver ativo
        require_once __DIR__ . '/../includes/email_templates.php';
        enviar_email_ocorrencia($ocorrencia_id);
        
        $pdo->commit();
        
        $mensagem = $status_aprovacao === 'pendente' 
            ? 'Ocorrência registrada com sucesso! Aguardando aprovação.' 
            : 'Ocorrência registrada com sucesso!';
        
        redirect('colaborador_view.php?id=' . $colaborador_id, $mensagem);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('ocorrencias_add.php', 'Erro ao registrar: ' . $e->getMessage(), 'error');
    }
}

// Busca colaboradores disponíveis com foto
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);

// Debug: verifica se colaboradores foram encontrados
if (empty($colaboradores)) {
    error_log("Nenhum colaborador encontrado para role: " . ($usuario['role'] ?? 'N/A'));
}

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
                            <?= render_select_colaborador('colaborador_id', 'colaborador_id', $colaborador_id, $colaboradores, true) ?>
                            <?php if (empty($colaboradores)): ?>
                            <div class="alert alert-warning mt-2">
                                <i class="ki-duotone ki-information-5 fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <strong>Atenção:</strong> Nenhum colaborador encontrado. Verifique se há colaboradores ativos no sistema.
                                <br><small>Role atual: <?= htmlspecialchars($usuario['role'] ?? 'N/A') ?></small>
                            </div>
                            <?php else: ?>
                            <small class="text-muted"><?= count($colaboradores) ?> colaborador(es) disponível(is)</small>
                            <?php endif; ?>
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
                                        data-permite-ponto="<?= $tipo['permite_tipo_ponto'] ?>"
                                        data-severidade="<?= htmlspecialchars($tipo['severidade'] ?? 'moderada') ?>"
                                        data-requer-aprovacao="<?= $tipo['requer_aprovacao'] ?? 0 ?>"
                                        data-template="<?= htmlspecialchars($tipo['template_descricao'] ?? '') ?>">
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
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Severidade</label>
                            <select name="severidade" id="severidade" class="form-select form-select-solid">
                                <option value="leve">Leve</option>
                                <option value="moderada" selected>Moderada</option>
                                <option value="grave">Grave</option>
                                <option value="critica">Crítica</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Tags</label>
                            <select name="tags[]" id="tags" class="form-select form-select-solid" multiple>
                                <?php 
                                $tags_disponiveis = get_tags_ocorrencias();
                                foreach ($tags_disponiveis as $tag): 
                                ?>
                                    <option value="<?= $tag['id'] ?>" data-cor="<?= htmlspecialchars($tag['cor']) ?>">
                                        <?= htmlspecialchars($tag['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Mantenha Ctrl pressionado para selecionar múltiplas tags</small>
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
                    
                    <!-- Campo: Desconto Banco de Horas -->
                    <div class="row mb-7" id="campo_desconto_banco_horas" style="display: none;">
                        <div class="col-md-12">
                            <div class="card card-flush bg-light-warning">
                                <div class="card-body">
                                    <div class="form-check form-check-custom form-check-solid mb-3">
                                        <input class="form-check-input" type="checkbox" name="desconta_banco_horas" 
                                               id="desconta_banco_horas" value="1" />
                                        <label class="form-check-label fw-bold" for="desconta_banco_horas">
                                            Descontar do Banco de Horas
                                        </label>
                                    </div>
                                    <div id="info_desconto_banco" style="display: none;">
                                        <div class="d-flex flex-column gap-2">
                                            <div>
                                                <strong>Saldo atual:</strong> 
                                                <span id="saldo_atual_ocorrencia">-</span> horas
                                            </div>
                                            <div>
                                                <strong>Horas a descontar:</strong> 
                                                <span id="horas_descontar_ocorrencia">-</span> horas
                                            </div>
                                            <div>
                                                <strong>Saldo após:</strong> 
                                                <span id="saldo_apos_ocorrencia">-</span> horas
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos dinâmicos serão inseridos aqui -->
                    <div id="campos_dinamicos_container" class="mb-7"></div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Descrição</label>
                            <textarea name="descricao" id="descricao" class="form-control form-control-solid" rows="5" placeholder="Descreva detalhadamente a ocorrência..."></textarea>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-light-primary" onclick="carregarTemplateDescricao()">
                                    <i class="ki-duotone ki-file fs-2"></i>
                                    Usar Template
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Anexos</label>
                            <input type="file" name="anexos[]" id="anexos" class="form-control form-control-solid" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" />
                            <small class="text-muted">Você pode selecionar múltiplos arquivos (PDF, DOC, DOCX, XLS, XLSX ou imagens). Máximo 10MB por arquivo.</small>
                            <div id="anexos_preview" class="mt-3"></div>
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
    const severidade = option.getAttribute('data-severidade') || 'moderada';
    const requerAprovacao = option.getAttribute('data-requer-aprovacao') === '1';
    const template = option.getAttribute('data-template') || '';
    const permiteDescontoBanco = option.getAttribute('data-permite-desconto-banco') === '1';
    const codigoTipo = option.getAttribute('data-codigo') || '';
    
    // Atualiza severidade
    document.getElementById('severidade').value = severidade;
    
    // Mostra/esconde campo de desconto banco de horas
    const campoDescontoBanco = document.getElementById('campo_desconto_banco_horas');
    if (permiteDescontoBanco) {
        campoDescontoBanco.style.display = 'block';
        atualizarInfoDescontoBanco();
    } else {
        campoDescontoBanco.style.display = 'none';
        document.getElementById('desconta_banco_horas').checked = false;
        document.getElementById('info_desconto_banco').style.display = 'none';
    }
    
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
    
    // Carrega campos dinâmicos
    if (tipoId) {
        carregarCamposDinamicosFormulario(tipoId);
    } else {
        document.getElementById('campos_dinamicos_container').innerHTML = '';
    }
    
    // Atualiza campo oculto tipo para compatibilidade
    document.getElementById('tipo').value = option.text.trim();
    
    // Atualiza info de desconto banco quando tempo de atraso muda
    const tempoAtraso = document.getElementById('tempo_atraso_minutos');
    if (tempoAtraso) {
        tempoAtraso.addEventListener('input', atualizarInfoDescontoBanco);
    }
});

// Função para atualizar informações de desconto do banco de horas
function atualizarInfoDescontoBanco() {
    const colaboradorId = document.getElementById('colaborador_id')?.value;
    const tipoOcorrencia = document.getElementById('tipo_ocorrencia_id');
    const option = tipoOcorrencia?.options[tipoOcorrencia.selectedIndex];
    const codigoTipo = option?.getAttribute('data-codigo') || '';
    const tempoAtraso = parseFloat(document.getElementById('tempo_atraso_minutos')?.value || 0);
    
    if (!colaboradorId || !codigoTipo) {
        document.getElementById('info_desconto_banco').style.display = 'none';
        return;
    }
    
    // Calcula horas a descontar baseado no tipo
    let horasDescontar = 0;
    if (codigoTipo === 'falta' || codigoTipo === 'ausencia_injustificada') {
        horasDescontar = 8; // Jornada padrão
    } else if (['atraso_entrada', 'atraso_almoco', 'atraso_cafe', 'saida_antecipada'].includes(codigoTipo)) {
        horasDescontar = tempoAtraso / 60; // Converte minutos para horas
    }
    
    // Busca saldo atual
    fetch(`../api/banco_horas/saldo.php?colaborador_id=${colaboradorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const saldoAtual = parseFloat(data.data.saldo_total_horas || 0);
                const saldoApos = saldoAtual - horasDescontar;
                
                document.getElementById('saldo_atual_ocorrencia').textContent = 
                    saldoAtual.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('horas_descontar_ocorrencia').textContent = 
                    horasDescontar.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('saldo_apos_ocorrencia').textContent = 
                    saldoApos.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                // Mostra info se checkbox estiver marcado
                if (document.getElementById('desconta_banco_horas').checked) {
                    document.getElementById('info_desconto_banco').style.display = 'block';
                }
            }
        })
        .catch(error => {
            console.error('Erro ao buscar saldo:', error);
        });
}

// Event listener para checkbox de desconto banco de horas
document.getElementById('desconta_banco_horas')?.addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('info_desconto_banco').style.display = 'block';
        atualizarInfoDescontoBanco();
    } else {
        document.getElementById('info_desconto_banco').style.display = 'none';
    }
});

// Atualiza quando colaborador muda
document.getElementById('colaborador_id')?.addEventListener('change', function() {
    atualizarInfoDescontoBanco();
});

// Carrega campos dinâmicos no formulário
function carregarCamposDinamicosFormulario(tipoId) {
    fetch(`../api/ocorrencias/get_campos_dinamicos.php?tipo_id=${tipoId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('campos_dinamicos_container');
            container.innerHTML = '';
            
            if (data.success && data.campos && data.campos.length > 0) {
                data.campos.forEach(campo => {
                    const campoHtml = criarHtmlCampoDinamico(campo);
                    container.insertAdjacentHTML('beforeend', campoHtml);
                });
            }
        })
        .catch(error => {
            console.error('Erro ao carregar campos dinâmicos:', error);
        });
}

// Cria HTML para campo dinâmico
function criarHtmlCampoDinamico(campo) {
    const codigo = campo.codigo;
    const label = campo.label;
    const tipo = campo.tipo_campo;
    const obrigatorio = campo.obrigatorio == 1 ? 'required' : '';
    const placeholder = campo.placeholder || '';
    const valorPadrao = campo.valor_padrao || '';
    
    let inputHtml = '';
    
    switch(tipo) {
        case 'textarea':
            inputHtml = `<textarea name="campos_dinamicos[${codigo}]" id="campo_${codigo}" class="form-control form-control-solid" ${obrigatorio} placeholder="${placeholder}">${valorPadrao}</textarea>`;
            break;
        case 'number':
            inputHtml = `<input type="number" name="campos_dinamicos[${codigo}]" id="campo_${codigo}" class="form-control form-control-solid" ${obrigatorio} placeholder="${placeholder}" value="${valorPadrao}" />`;
            break;
        case 'date':
            inputHtml = `<input type="date" name="campos_dinamicos[${codigo}]" id="campo_${codigo}" class="form-control form-control-solid" ${obrigatorio} value="${valorPadrao}" />`;
            break;
        case 'time':
            inputHtml = `<input type="time" name="campos_dinamicos[${codigo}]" id="campo_${codigo}" class="form-control form-control-solid" ${obrigatorio} value="${valorPadrao}" />`;
            break;
        case 'select':
            const opcoes = campo.opcoes ? JSON.parse(campo.opcoes) : [];
            let optionsHtml = '<option value="">Selecione...</option>';
            opcoes.forEach(opcao => {
                optionsHtml += `<option value="${opcao}" ${opcao === valorPadrao ? 'selected' : ''}>${opcao}</option>`;
            });
            inputHtml = `<select name="campos_dinamicos[${codigo}]" id="campo_${codigo}" class="form-select form-select-solid" ${obrigatorio}>${optionsHtml}</select>`;
            break;
        case 'checkbox':
            inputHtml = `<div class="form-check form-check-custom form-check-solid">
                <input class="form-check-input" type="checkbox" name="campos_dinamicos[${codigo}]" id="campo_${codigo}" value="1" ${valorPadrao === '1' ? 'checked' : ''} />
                <label class="form-check-label" for="campo_${codigo}">${label}</label>
            </div>`;
            return `<div class="row mb-5">
                <div class="col-md-12">
                    ${inputHtml}
                </div>
            </div>`;
        case 'radio':
            const opcoesRadio = campo.opcoes ? JSON.parse(campo.opcoes) : [];
            let radiosHtml = '';
            opcoesRadio.forEach((opcao, index) => {
                radiosHtml += `<div class="form-check form-check-custom form-check-solid">
                    <input class="form-check-input" type="radio" name="campos_dinamicos[${codigo}]" id="campo_${codigo}_${index}" value="${opcao}" ${opcao === valorPadrao ? 'checked' : ''} />
                    <label class="form-check-label" for="campo_${codigo}_${index}">${opcao}</label>
                </div>`;
            });
            return `<div class="row mb-5">
                <div class="col-md-12">
                    <label class="fw-semibold fs-6 mb-2">${label} ${obrigatorio ? '<span class="text-danger">*</span>' : ''}</label>
                    ${radiosHtml}
                </div>
            </div>`;
        default:
            inputHtml = `<input type="text" name="campos_dinamicos[${codigo}]" id="campo_${codigo}" class="form-control form-control-solid" ${obrigatorio} placeholder="${placeholder}" value="${valorPadrao}" />`;
    }
    
    return `<div class="row mb-5">
        <div class="col-md-12">
            <label class="fw-semibold fs-6 mb-2">${label} ${obrigatorio ? '<span class="text-danger">*</span>' : ''}</label>
            ${inputHtml}
        </div>
    </div>`;
}

// Carrega template de descrição
function carregarTemplateDescricao() {
    const tipoId = document.getElementById('tipo_ocorrencia_id').value;
    if (!tipoId) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                text: 'Selecione um tipo de ocorrência primeiro!',
                icon: 'warning',
                buttonsStyling: false,
                confirmButtonText: 'Ok, entendi!',
                customClass: {
                    confirmButton: 'btn fw-bold btn-primary'
                }
            });
        }
        return;
    }
    
    fetch(`../api/ocorrencias/get_templates_descricao.php?tipo_id=${tipoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.templates && data.templates.length > 0) {
                // Mostra modal para selecionar template
                const opcoes = data.templates.map(t => t.nome).join('|');
                const templateEscolhido = prompt('Escolha um template:\n' + data.templates.map((t, i) => `${i + 1}. ${t.nome}`).join('\n') + '\n\nDigite o número:');
                
                if (templateEscolhido && data.templates[parseInt(templateEscolhido) - 1]) {
                    const template = data.templates[parseInt(templateEscolhido) - 1];
                    document.getElementById('descricao').value = template.template;
                }
            } else {
                // Usa template padrão do tipo
                const option = document.getElementById('tipo_ocorrencia_id').options[document.getElementById('tipo_ocorrencia_id').selectedIndex];
                const template = option.getAttribute('data-template') || '';
                if (template) {
                    document.getElementById('descricao').value = template;
                } else {
                    alert('Nenhum template disponível para este tipo.');
                }
            }
        })
        .catch(error => {
            console.error('Erro ao carregar templates:', error);
        });
}

// Preview de anexos
document.getElementById('anexos').addEventListener('change', function(e) {
    const preview = document.getElementById('anexos_preview');
    preview.innerHTML = '';
    
    Array.from(e.target.files).forEach((file, index) => {
        const div = document.createElement('div');
        div.className = 'd-flex align-items-center mb-2';
        div.innerHTML = `
            <i class="ki-duotone ki-file fs-2 me-2"></i>
            <span class="text-gray-800">${file.name}</span>
            <span class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
        `;
        preview.appendChild(div);
    });
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
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
    }
    
    .select2-container .select2-selection--single .select2-selection__rendered {
        display: flex !important;
        align-items: center !important;
    }
</style>
<!--end::Select2 CSS-->

<!--begin::Select Colaborador Script-->
<script src="../assets/js/select-colaborador.js"></script>
<!--end::Select Colaborador Script-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
