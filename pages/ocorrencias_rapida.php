<?php
/**
 * Ocorrência Rápida - Formulário Simplificado
 * Permite criar ocorrências rapidamente informando apenas colaborador, severidade e motivo
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';
require_once __DIR__ . '/../includes/notificacoes.php';
require_once __DIR__ . '/../includes/banco_horas_functions.php';

require_page_permission('ocorrencias_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $_GET['colaborador_id'] ?? null;

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = $_POST['colaborador_id'] ?? null;
    $tipo_ocorrencia_id = !empty($_POST['tipo_ocorrencia_id']) ? (int)$_POST['tipo_ocorrencia_id'] : null;
    $severidade = $_POST['severidade'] ?? 'moderada';
    $motivo = sanitize($_POST['motivo'] ?? '');
    $tempo_atraso_minutos = !empty($_POST['tempo_atraso_minutos']) ? (int)$_POST['tempo_atraso_minutos'] : null;
    $tipo_ponto = $_POST['tipo_ponto'] ?? null;
    $considera_dia_inteiro = isset($_POST['considera_dia_inteiro']) && $_POST['considera_dia_inteiro'] == '1';
    $apenas_informativa = isset($_POST['apenas_informativa']) && $_POST['apenas_informativa'] == '1';
    $desconta_banco_horas = isset($_POST['desconta_banco_horas']) && $_POST['desconta_banco_horas'] == '1';
    
    if (empty($colaborador_id) || empty($motivo) || empty($tipo_ocorrencia_id)) {
        redirect('ocorrencias_rapida.php', 'Preencha todos os campos obrigatórios!', 'error');
    }
    
    // Verifica permissão para lançar ocorrência neste colaborador
    if (!can_access_colaborador($colaborador_id)) {
        redirect('ocorrencias_rapida.php', 'Você não tem permissão para lançar ocorrência neste colaborador.', 'error');
    }
    
    // Valida se o tipo permite ocorrência rápida
    $stmt = $pdo->prepare("SELECT id, nome, codigo, requer_aprovacao, severidade, permite_tempo_atraso, permite_tipo_ponto, permite_desconto_banco_horas, permite_considerar_dia_inteiro FROM tipos_ocorrencias WHERE id = ? AND permite_ocorrencia_rapida = 1 AND status = 'ativo'");
    $stmt->execute([$tipo_ocorrencia_id]);
    $tipo_ocorrencia = $stmt->fetch();
    
    if (!$tipo_ocorrencia) {
        redirect('ocorrencias_rapida.php', 'Tipo de ocorrência selecionado não é válido para ocorrências rápidas.', 'error');
    }
    
    // Valida campos obrigatórios baseado no tipo
    if ($tipo_ocorrencia['permite_tempo_atraso'] && empty($tempo_atraso_minutos) && !$considera_dia_inteiro) {
        redirect('ocorrencias_rapida.php', 'Informe o tempo de atraso em minutos ou marque "Considerar como falta do dia inteiro"!', 'error');
    }
    
    if ($tipo_ocorrencia['permite_tipo_ponto'] && empty($tipo_ponto)) {
        redirect('ocorrencias_rapida.php', 'Selecione o tipo de ponto!', 'error');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Usa severidade do tipo se não foi informada, senão usa a informada
        $severidade_final = $severidade;
        if (!empty($tipo_ocorrencia['severidade'])) {
            $severidade_final = $tipo_ocorrencia['severidade'];
        }
        
        // Determina status de aprovação
        $status_aprovacao = $tipo_ocorrencia['requer_aprovacao'] ? 'pendente' : 'aprovada';
        
        // Insere a ocorrência
        $stmt = $pdo->prepare("
            INSERT INTO ocorrencias (
                colaborador_id, usuario_id, tipo, tipo_ocorrencia_id, 
                descricao, data_ocorrencia, severidade, status_aprovacao,
                tempo_atraso_minutos, tipo_ponto, considera_dia_inteiro, apenas_informativa, desconta_banco_horas
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id, 
            $usuario['id'], 
            $tipo_ocorrencia['nome'],
            $tipo_ocorrencia_id,
            $motivo, 
            date('Y-m-d'),
            $severidade_final,
            $status_aprovacao,
            $tempo_atraso_minutos,
            $tipo_ponto,
            $considera_dia_inteiro ? 1 : 0,
            $apenas_informativa ? 1 : 0,
            $desconta_banco_horas ? 1 : 0
        ]);
        
        $ocorrencia_id = $pdo->lastInsertId();
        
        // Se for apenas informativa, não desconta nada
        if ($apenas_informativa) {
            $stmt = $pdo->prepare("UPDATE ocorrencias SET valor_desconto = NULL, desconta_banco_horas = 0, horas_descontadas = NULL WHERE id = ?");
            $stmt->execute([$ocorrencia_id]);
        } elseif ($desconta_banco_horas && $tipo_ocorrencia['permite_desconto_banco_horas']) {
            // Verifica se deve descontar do banco de horas
            try {
                $resultado = descontar_horas_banco_ocorrencia($ocorrencia_id, $usuario['id']);
                
                if (!$resultado['success']) {
                    error_log('Erro ao descontar banco de horas na ocorrência rápida: ' . ($resultado['error'] ?? 'Erro desconhecido'));
                }
            } catch (Exception $e) {
                error_log("Erro ao debitar banco de horas na ocorrência rápida: " . $e->getMessage());
                // Não bloqueia a criação da ocorrência se houver erro no banco de horas
            }
        }
        
        // Registra histórico
        registrar_historico_ocorrencia($ocorrencia_id, 'criada', $usuario['id'], null, null, null, 'Ocorrência rápida criada');
        
        // Cria flag automática se o tipo de ocorrência gerar flag
        // Só cria se a ocorrência estiver aprovada
        if ($status_aprovacao === 'aprovada') {
            $resultado_flag = criar_flag_automatica($ocorrencia_id, $usuario['id']);
            if ($resultado_flag['success'] && isset($resultado_flag['flags_ativas']) && $resultado_flag['flags_ativas'] >= 3) {
                // Log de alerta (mas não desliga automaticamente)
                error_log("ALERTA: Colaborador ID {$colaborador_id} possui 3 ou mais flags ativas");
            }
        }
        
        // Envia notificações básicas
        $stmt_colab = $pdo->prepare("SELECT nome_completo, usuario_id FROM colaboradores WHERE id = ?");
        $stmt_colab->execute([$colaborador_id]);
        $colab = $stmt_colab->fetch();
        
        // Notifica colaborador (se tiver usuário vinculado)
        if (!empty($colab['usuario_id'])) {
            criar_notificacao(
                $colab['usuario_id'], 
                $colaborador_id, 
                'ocorrencia', 
                'Nova Ocorrência', 
                "Uma ocorrência foi registrada para você: {$motivo}", 
                "ocorrencia_view.php?id={$ocorrencia_id}", 
                $ocorrencia_id, 
                'ocorrencia'
            );
        }
        
        $pdo->commit();
        
        $mensagem = $status_aprovacao === 'pendente' 
            ? 'Ocorrência rápida registrada com sucesso! Aguardando aprovação.' 
            : 'Ocorrência rápida registrada com sucesso!';
        redirect('ocorrencias_rapida.php', $mensagem, 'success');
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao criar ocorrência rápida: " . $e->getMessage());
        redirect('ocorrencias_rapida.php', 'Erro ao registrar ocorrência. Tente novamente.', 'error');
    }
}

// Busca colaboradores disponíveis
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);

// Busca tipos de ocorrências que permitem ocorrência rápida
$stmt = $pdo->prepare("
    SELECT id, nome, codigo, severidade, permite_tempo_atraso, permite_tipo_ponto, permite_desconto_banco_horas, permite_considerar_dia_inteiro
    FROM tipos_ocorrencias 
    WHERE permite_ocorrencia_rapida = 1 
    AND status = 'ativo' 
    ORDER BY nome
");
$stmt->execute();
$tipos_rapidos = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!--begin::Main-->
<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
    <!--begin::Content wrapper-->
    <div class="d-flex flex-column flex-column-fluid">
        <!--begin::Toolbar-->
        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-0">
            <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
                <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                    <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                        Ocorrência Rápida
                    </h1>
                    <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                        <li class="breadcrumb-item text-muted">
                            <a href="dashboard.php" class="text-muted text-hover-primary">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <span class="bullet bg-gray-400 w-5px h-2px"></span>
                        </li>
                        <li class="breadcrumb-item text-muted">
                            <a href="ocorrencias_list.php" class="text-muted text-hover-primary">Ocorrências</a>
                        </li>
                        <li class="breadcrumb-item">
                            <span class="bullet bg-gray-400 w-5px h-2px"></span>
                        </li>
                        <li class="breadcrumb-item text-dark">Ocorrência Rápida</li>
                    </ul>
                </div>
            </div>
        </div>
        <!--end::Toolbar-->
        
        <!--begin::Content-->
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-xxl">
                
                <!--begin::Alert-->
                <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                    <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Ocorrência Rápida</h4>
                        <span>Use este formulário para registrar ocorrências simples do dia a dia de forma rápida e direta. Selecione o colaborador, o tipo de ocorrência e descreva o motivo.</span>
                    </div>
                </div>
                <!--end::Alert-->
                
                <!--begin::Card-->
                <div class="card">
                    <div class="card-body">
                        <form id="form_ocorrencia_rapida" method="POST" action="">
                            
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                                    <?php echo render_select_colaborador('colaborador_id', 'colaborador_id', $colaborador_id, $colaboradores, true, 'mb-0'); ?>
                                    <div class="form-text">Selecione o colaborador para o qual a ocorrência será registrada</div>
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <label class="required fw-semibold fs-6 mb-2">Tipo de Ocorrência</label>
                                    <?php if (empty($tipos_rapidos)): ?>
                                        <div class="alert alert-warning">
                                            <i class="ki-duotone ki-information-5 fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            <strong>Atenção:</strong> Nenhum tipo de ocorrência configurado para ocorrências rápidas. 
                                            Configure em <a href="tipos_ocorrencias.php">Tipos de Ocorrências</a> marcando a opção "Disponível em Ocorrências Rápidas".
                                        </div>
                                    <?php else: ?>
                                        <select name="tipo_ocorrencia_id" id="tipo_ocorrencia_id" class="form-select form-select-solid" required>
                                            <option value="">Selecione o tipo de ocorrência...</option>
                                            <?php foreach ($tipos_rapidos as $tipo): ?>
                                                <option value="<?= $tipo['id'] ?>" 
                                                    data-severidade="<?= htmlspecialchars($tipo['severidade'] ?? 'moderada') ?>"
                                                    data-permite-tempo="<?= $tipo['permite_tempo_atraso'] ?? 0 ?>"
                                                    data-permite-ponto="<?= $tipo['permite_tipo_ponto'] ?? 0 ?>"
                                                    data-permite-desconto-banco="<?= $tipo['permite_desconto_banco_horas'] ?? 0 ?>"
                                                    data-codigo="<?= htmlspecialchars($tipo['codigo'] ?? '') ?>">
                                                    <?= htmlspecialchars($tipo['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Selecione o tipo de ocorrência que deseja registrar</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <label class="fw-semibold fs-6 mb-2">Severidade</label>
                                    <select name="severidade" id="severidade" class="form-select form-select-solid">
                                        <option value="leve">Leve</option>
                                        <option value="moderada" selected>Moderada</option>
                                        <option value="grave">Grave</option>
                                        <option value="critica">Crítica</option>
                                    </select>
                                    <div class="form-text">A severidade será ajustada automaticamente conforme o tipo selecionado, mas você pode alterar se necessário</div>
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
                                    <label class="required fw-semibold fs-6 mb-2">Tipo de Ponto</label>
                                    <select name="tipo_ponto" id="tipo_ponto" class="form-select form-select-solid">
                                        <option value="">Selecione...</option>
                                        <option value="entrada">Entrada</option>
                                        <option value="almoco">Almoço</option>
                                        <option value="cafe">Café</option>
                                        <option value="saida">Saída</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Campo: Considerar Dia Inteiro -->
                            <div class="row mb-7" id="campo_considera_dia_inteiro" style="display: none;">
                                <div class="col-md-12">
                                    <div class="card card-flush bg-light-warning">
                                        <div class="card-body">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="considera_dia_inteiro" id="considera_dia_inteiro" value="1" />
                                                <label class="form-check-label fw-bold" for="considera_dia_inteiro">
                                                    Considerar como falta do dia inteiro (8 horas)
                                                </label>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                Quando marcado, esta ocorrência será tratada como falta completa do dia (8 horas de trabalho) ao invés de apenas minutos de atraso.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Campo: Apenas Informativa -->
                            <div class="row mb-7" id="campo_apenas_informativa" style="display: none;">
                                <div class="col-md-12">
                                    <div class="card card-flush bg-light-success">
                                        <div class="card-body">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="apenas_informativa" id="apenas_informativa" value="1" />
                                                <label class="form-check-label fw-bold" for="apenas_informativa">
                                                    <i class="ki-duotone ki-information-5 fs-2 text-success me-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                    </i>
                                                    Marcar como Apenas Informativa (Sem Impacto Financeiro)
                                                </label>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                Quando marcado, esta ocorrência será registrada apenas para fins de registro/documentação. 
                                                <strong>Não será descontado do pagamento nem do banco de horas.</strong>
                                                <br>Use esta opção quando houver atestado médico, justificativa aceita ou outros casos onde a falta não deve gerar desconto.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Campo: Desconto Banco de Horas -->
                            <div class="row mb-7" id="campo_desconto_banco_horas" style="display: none;">
                                <div class="col-md-12">
                                    <div class="card card-flush bg-light-warning">
                                        <div class="card-body">
                                            <div class="form-check form-check-custom form-check-solid mb-3">
                                                <input class="form-check-input" type="checkbox" name="desconta_banco_horas" id="desconta_banco_horas" value="1" />
                                                <label class="form-check-label fw-bold" for="desconta_banco_horas">
                                                    Descontar do Banco de Horas
                                                </label>
                                            </div>
                                            <div id="info_desconto_banco" style="display: none;">
                                                <div class="alert alert-info mb-0">
                                                    <strong>Saldo Atual:</strong> <span id="saldo_atual_banco">0</span> horas<br>
                                                    <strong>Horas a Descontar:</strong> <span id="horas_descontar">0</span> horas<br>
                                                    <strong>Saldo Após Desconto:</strong> <span id="saldo_apos_desconto">0</span> horas
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-7">
                                <div class="col-md-12">
                                    <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                                    <textarea name="motivo" id="motivo" class="form-control form-control-solid" rows="4" placeholder="Descreva o motivo da ocorrência..." required></textarea>
                                    <div class="form-text">Descreva de forma clara e objetiva o motivo da ocorrência</div>
                                </div>
                            </div>
                            
                            <div class="text-center pt-5">
                                <button type="reset" class="btn btn-light me-3">Limpar</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ki-duotone ki-check fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Registrar Ocorrência
                                </button>
                            </div>
                            
                        </form>
                    </div>
                </div>
                <!--end::Card-->
                
            </div>
        </div>
        <!--end::Content-->
    </div>
    <!--end::Content wrapper-->
</div>
<!--end::Main-->

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

<!--begin::Select2 Script-->
<script src="../assets/js/select-colaborador.js"></script>
<!--end::Select2 Script-->

<script>
"use strict";

// Aguarda Select2 ser inicializado pelo script select-colaborador.js
$(document).ready(function() {
    // Aguarda um pouco para garantir que Select2 foi inicializado
    setTimeout(function() {
        // Foco no campo de colaborador após Select2 estar pronto
        var $select = $('#colaborador_id');
        if ($select.length && $select.hasClass('select2-hidden-accessible')) {
            $select.on('select2:open', function() {
                // Foca no campo de busca quando o dropdown abrir
                setTimeout(function() {
                    $('.select2-search__field').focus();
                }, 100);
            });
        }
    }, 500);
    
    // Auto-focus no tipo após selecionar colaborador
    $('#colaborador_id').on('change', function() {
        if ($(this).val()) {
            setTimeout(function() {
                $('#tipo_ocorrencia_id').focus();
            }, 100);
        }
    });
    
    // Atualiza severidade quando tipo de ocorrência muda
    $('#tipo_ocorrencia_id').on('change', function() {
        const option = $(this).find('option:selected');
        const severidadePadrao = option.data('severidade');
        const permiteTempo = option.data('permite-tempo') === '1' || option.data('permite-tempo') === 1;
        const permitePonto = option.data('permite-ponto') === '1' || option.data('permite-ponto') === 1;
        const permiteDiaInteiro = option.data('permite-dia-inteiro') === '1' || option.data('permite-dia-inteiro') === 1;
        const permiteDescontoBanco = option.data('permite-desconto-banco') === '1' || option.data('permite-desconto-banco') === 1;
        const codigoTipo = option.data('codigo') || '';
        
        if (severidadePadrao) {
            $('#severidade').val(severidadePadrao);
        }
        
        // Mostra/esconde campo "Apenas Informativa" (só aparece se o tipo permite desconto)
        const campoApenasInformativa = $('#campo_apenas_informativa');
        if (permiteDescontoBanco) {
            campoApenasInformativa.show();
        } else {
            campoApenasInformativa.hide();
            $('#apenas_informativa').prop('checked', false);
        }
        
        // Mostra/esconde campo de considerar dia inteiro primeiro
        const campoDiaInteiro = $('#campo_considera_dia_inteiro');
        if (permiteDiaInteiro) {
            campoDiaInteiro.show();
        } else {
            campoDiaInteiro.hide();
            $('#considera_dia_inteiro').prop('checked', false);
        }
        
        // Mostra/esconde campo de tempo de atraso
        const campoTempo = $('#campo_tempo_atraso');
        const consideraDiaInteiro = $('#considera_dia_inteiro').is(':checked');
        
        if (permiteTempo) {
            // Se permite considerar dia inteiro E está marcado, esconde o campo de minutos
            if (permiteDiaInteiro && consideraDiaInteiro) {
                campoTempo.hide();
                $('#tempo_atraso_minutos').val('').prop('required', false).prop('disabled', true);
            } else {
                campoTempo.show();
                $('#tempo_atraso_minutos').prop('disabled', false);
                $('#tempo_atraso_minutos').prop('required', !permiteDiaInteiro || !consideraDiaInteiro);
            }
        } else {
            campoTempo.hide();
            $('#tempo_atraso_minutos').val('').prop('required', false).prop('disabled', false);
        }
        
        // Mostra/esconde campo de tipo de ponto
        const campoPonto = $('#campo_tipo_ponto');
        if (permitePonto) {
            campoPonto.show();
            $('#tipo_ponto').prop('required', true);
        } else {
            campoPonto.hide();
            $('#tipo_ponto').val('').prop('required', false);
        }
        
        // Mostra/esconde campo de desconto banco de horas (só se não for apenas informativa)
        const campoDescontoBanco = $('#campo_desconto_banco_horas');
        const apenasInformativa = $('#apenas_informativa').is(':checked');
        if (permiteDescontoBanco && !apenasInformativa) {
            campoDescontoBanco.show();
            atualizarInfoDescontoBanco();
        } else {
            campoDescontoBanco.hide();
            $('#desconta_banco_horas').prop('checked', false);
            $('#info_desconto_banco').hide();
        }
        
        // Foca no campo apropriado após selecionar tipo
        if ($(this).val()) {
            setTimeout(function() {
                if (permiteTempo) {
                    $('#tempo_atraso_minutos').focus();
                } else if (permitePonto) {
                    $('#tipo_ponto').focus();
                } else {
                    $('#motivo').focus();
                }
            }, 100);
        }
    });
    
    // Atualiza informações de desconto do banco de horas
    function atualizarInfoDescontoBanco() {
        const colaboradorId = $('#colaborador_id').val();
        const tipoOcorrencia = $('#tipo_ocorrencia_id');
        const option = tipoOcorrencia.find('option:selected');
        const codigoTipo = option.data('codigo') || '';
        const tempoAtraso = parseFloat($('#tempo_atraso_minutos').val() || 0);
        const consideraDiaInteiro = $('#considera_dia_inteiro').is(':checked');
        
        if (!colaboradorId || !codigoTipo) {
            $('#info_desconto_banco').hide();
            return;
        }
        
        // Calcula horas a descontar baseado no tipo
        let horasDescontar = 0;
        if (codigoTipo === 'falta' || codigoTipo === 'ausencia_injustificada') {
            horasDescontar = 8; // Jornada padrão
        } else if (['atraso_entrada', 'atraso_almoco', 'atraso_cafe'].includes(codigoTipo)) {
            if (consideraDiaInteiro) {
                horasDescontar = 8; // Considera como falta do dia inteiro
            } else if (tempoAtraso > 0) {
                horasDescontar = Math.round((tempoAtraso / 60) * 100) / 100; // Converte minutos para horas
            }
        }
        
        if (horasDescontar > 0) {
            // Busca saldo atual via AJAX
            $.ajax({
                url: '../api/banco_horas/saldo.php',
                method: 'GET',
                data: { colaborador_id: colaboradorId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        const saldoAtual = parseFloat(response.data.saldo_total_horas || 0);
                        const saldoApos = saldoAtual - horasDescontar;
                        
                        $('#saldo_atual_banco').text(saldoAtual.toFixed(2));
                        $('#horas_descontar').text(horasDescontar.toFixed(2));
                        $('#saldo_apos_desconto').text(saldoApos.toFixed(2));
                        $('#info_desconto_banco').show();
                    }
                },
                error: function() {
                    $('#info_desconto_banco').hide();
                }
            });
        } else {
            $('#info_desconto_banco').hide();
        }
    }
    
    // Atualiza info quando tempo de atraso muda
    $('#tempo_atraso_minutos').on('input', function() {
        atualizarInfoDescontoBanco();
    });
    
    // Atualiza quando considera dia inteiro muda
    $('#considera_dia_inteiro').on('change', function() {
        const campoTempo = $('#campo_tempo_atraso');
        const tempoInput = $('#tempo_atraso_minutos');
        const tipoOcorrencia = $('#tipo_ocorrencia_id');
        const option = tipoOcorrencia.find('option:selected');
        const permiteTempo = option.data('permite-tempo') === '1' || option.data('permite-tempo') === 1;
        
        if (this.checked) {
            // Se marcou dia inteiro, esconde campo de minutos
            campoTempo.hide();
            tempoInput.prop('required', false).prop('disabled', true).val('');
        } else {
            // Se desmarcou, mostra e habilita campo de minutos
            if (permiteTempo) {
                campoTempo.show();
            }
            tempoInput.prop('disabled', false);
            if (permiteTempo) {
                tempoInput.prop('required', true);
            }
        }
        atualizarInfoDescontoBanco();
    });
    
    // Atualiza info quando colaborador muda
    $('#colaborador_id').on('change', function() {
        atualizarInfoDescontoBanco();
    });
    
    // Atalho de teclado: Enter no textarea não submete, precisa usar Ctrl+Enter
    $('#motivo').on('keydown', function(e) {
        if (e.key === 'Enter' && e.ctrlKey) {
            $('#form_ocorrencia_rapida').submit();
        }
    });
    
    // Listener para checkbox "Apenas Informativa"
    $('#apenas_informativa').on('change', function() {
        const apenasInformativa = $(this).is(':checked');
        const campoDescontoBanco = $('#campo_desconto_banco_horas');
        
        if (apenasInformativa) {
            // Se marcado como informativa, esconde campo de desconto banco de horas
            campoDescontoBanco.hide();
            $('#desconta_banco_horas').prop('checked', false);
            $('#info_desconto_banco').hide();
        } else {
            // Se desmarcado, mostra campo de desconto se o tipo permitir
            const tipoOcorrencia = $('#tipo_ocorrencia_id');
            const option = tipoOcorrencia.find('option:selected');
            const permiteDescontoBanco = option.data('permite-desconto-banco') === '1' || option.data('permite-desconto-banco') === 1;
            
            if (permiteDescontoBanco) {
                campoDescontoBanco.show();
                atualizarInfoDescontoBanco();
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

