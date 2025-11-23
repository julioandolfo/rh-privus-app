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

require_page_permission('ocorrencias_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $_GET['colaborador_id'] ?? null;

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = $_POST['colaborador_id'] ?? null;
    $severidade = $_POST['severidade'] ?? 'moderada';
    $motivo = sanitize($_POST['motivo'] ?? '');
    
    if (empty($colaborador_id) || empty($motivo)) {
        redirect('ocorrencias_rapida.php', 'Preencha todos os campos obrigatórios!', 'error');
    }
    
    // Verifica permissão para lançar ocorrência neste colaborador
    if (!can_access_colaborador($colaborador_id)) {
        redirect('ocorrencias_rapida.php', 'Você não tem permissão para lançar ocorrência neste colaborador.', 'error');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Busca ou cria tipo de ocorrência "Ocorrência Rápida"
        $stmt = $pdo->prepare("SELECT id FROM tipos_ocorrencias WHERE codigo = 'ocorrencia_rapida' LIMIT 1");
        $stmt->execute();
        $tipo_rapida = $stmt->fetch();
        
        $tipo_ocorrencia_id = null;
        if ($tipo_rapida) {
            $tipo_ocorrencia_id = $tipo_rapida['id'];
        } else {
            // Cria tipo de ocorrência rápida se não existir
            $stmt = $pdo->prepare("
                INSERT INTO tipos_ocorrencias 
                (nome, codigo, categoria, severidade, permite_tempo_atraso, permite_tipo_ponto, 
                 requer_aprovacao, conta_advertencia, calcula_desconto, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'Ocorrência Rápida',
                'ocorrencia_rapida',
                'geral',
                $severidade,
                0, // não permite tempo de atraso
                0, // não permite tipo de ponto
                0, // não requer aprovação
                0, // não conta advertência
                0, // não calcula desconto
                'ativo'
            ]);
            $tipo_ocorrencia_id = $pdo->lastInsertId();
        }
        
        // Insere a ocorrência
        $stmt = $pdo->prepare("
            INSERT INTO ocorrencias (
                colaborador_id, usuario_id, tipo, tipo_ocorrencia_id, 
                descricao, data_ocorrencia, severidade, status_aprovacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id, 
            $usuario['id'], 
            'Ocorrência Rápida',
            $tipo_ocorrencia_id,
            $motivo, 
            date('Y-m-d'),
            $severidade,
            'aprovada' // sempre aprovada para ocorrências rápidas
        ]);
        
        $ocorrencia_id = $pdo->lastInsertId();
        
        // Registra histórico
        registrar_historico_ocorrencia($ocorrencia_id, 'ocorrencia_criada', 'Ocorrência rápida criada', $usuario['id']);
        
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
        
        redirect('ocorrencias_rapida.php', 'Ocorrência rápida registrada com sucesso!', 'success');
        
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
                        <span>Use este formulário para registrar ocorrências simples do dia a dia de forma rápida e direta. Apenas informe o colaborador, a severidade e o motivo.</span>
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
                                    <label class="required fw-semibold fs-6 mb-2">Severidade</label>
                                    <select name="severidade" id="severidade" class="form-select form-select-solid" required>
                                        <option value="leve">Leve</option>
                                        <option value="moderada" selected>Moderada</option>
                                        <option value="grave">Grave</option>
                                        <option value="critica">Crítica</option>
                                    </select>
                                    <div class="form-text">Selecione o nível de severidade da ocorrência</div>
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
    
    // Auto-focus no motivo após selecionar colaborador
    $('#colaborador_id').on('change', function() {
        if ($(this).val()) {
            setTimeout(function() {
                $('#motivo').focus();
            }, 100);
        }
    });
    
    // Atalho de teclado: Enter no textarea não submete, precisa usar Ctrl+Enter
    $('#motivo').on('keydown', function(e) {
        if (e.key === 'Enter' && e.ctrlKey) {
            $('#form_ocorrencia_rapida').submit();
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

