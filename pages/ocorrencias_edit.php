<?php
/**
 * Editar Ocorrência - Versão Melhorada com Campos Dinâmicos
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';
require_once __DIR__ . '/../includes/banco_horas_functions.php';

require_page_permission('ocorrencias_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$ocorrencia_id = $_GET['id'] ?? null;

// Busca ocorrência existente
if (!$ocorrencia_id) {
    redirect('ocorrencias_list.php', 'ID da ocorrência não informado!', 'error');
}

$stmt = $pdo->prepare("
    SELECT o.*, t.*, c.nome_completo as colaborador_nome
    FROM ocorrencias o
    LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
    LEFT JOIN colaboradores c ON o.colaborador_id = c.id
    WHERE o.id = ?
");
$stmt->execute([$ocorrencia_id]);
$ocorrencia_existente = $stmt->fetch();

if (!$ocorrencia_existente) {
    redirect('ocorrencias_list.php', 'Ocorrência não encontrada!', 'error');
}

// Verifica permissão
if (!can_access_colaborador($ocorrencia_existente['colaborador_id'])) {
    redirect('ocorrencias_list.php', 'Você não tem permissão para editar esta ocorrência.', 'error');
}

$colaborador_id = $ocorrencia_existente['colaborador_id'];

// Processa POST ANTES de incluir o header (para evitar erro de headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ocorrencia_id_edit = $_POST['ocorrencia_id'] ?? $ocorrencia_id;
    $colaborador_id = $_POST['colaborador_id'] ?? null;
    // TIPO DE OCORRÊNCIA NÃO PODE SER ALTERADO - usa o existente
    $tipo_ocorrencia_id = $ocorrencia_existente['tipo_ocorrencia_id'];
    $tipo = sanitize($_POST['tipo'] ?? ''); // Mantido para compatibilidade
    $descricao = sanitize($_POST['descricao'] ?? '');
    $data_ocorrencia = $_POST['data_ocorrencia'] ?? date('Y-m-d');
    $hora_ocorrencia = $_POST['hora_ocorrencia'] ?? null;
    $tempo_atraso_minutos = !empty($_POST['tempo_atraso_minutos']) ? (int)$_POST['tempo_atraso_minutos'] : null;
    $horario_esperado = !empty($_POST['horario_esperado']) ? $_POST['horario_esperado'] : null;
    $horario_real = !empty($_POST['horario_real']) ? $_POST['horario_real'] : null;
    $tipo_ponto = !empty($_POST['tipo_ponto']) ? $_POST['tipo_ponto'] : null;
    // Valida se tipo_ponto é um valor válido do ENUM
    if ($tipo_ponto && !in_array($tipo_ponto, ['entrada', 'almoco', 'cafe', 'saida'])) {
        $tipo_ponto = null;
    }
    $considera_dia_inteiro = isset($_POST['considera_dia_inteiro']) && $_POST['considera_dia_inteiro'] == '1';
    $apenas_informativa = isset($_POST['apenas_informativa']) && $_POST['apenas_informativa'] == '1';
    $severidade = $_POST['severidade'] ?? null;
    $tags = !empty($_POST['tags']) ? json_encode($_POST['tags']) : null;
    $campos_dinamicos_valores = !empty($_POST['campos_dinamicos']) ? json_encode($_POST['campos_dinamicos']) : null;
    
    if (empty($colaborador_id) || empty($tipo_ocorrencia_id) || empty($data_ocorrencia)) {
        redirect('ocorrencias_edit.php?id=' . $ocorrencia_id_edit, 'Preencha os campos obrigatórios!', 'error');
    }
    
        // Verifica permissão para editar ocorrência neste colaborador
        if (!can_access_colaborador($colaborador_id)) {
            redirect('ocorrencias_edit.php?id=' . $ocorrencia_id_edit, 'Você não tem permissão para editar ocorrência neste colaborador.', 'error');
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
        
        // Valida campos obrigatórios baseado no tipo
        if ($tipo_ocorrencia_data && $tipo_ocorrencia_id) {
            // Valida tempo de atraso se obrigatório (mas não se considerar dia inteiro estiver marcado)
            if ($tipo_ocorrencia_data['permite_tempo_atraso'] && empty($tempo_atraso_minutos) && !$considera_dia_inteiro) {
                redirect('ocorrencias_edit.php?id=' . $ocorrencia_id_edit, 'Informe o tempo de atraso em minutos ou marque "Considerar como falta do dia inteiro"!', 'error');
            }
            
            // Valida tipo de ponto se obrigatório
            if ($tipo_ocorrencia_data['permite_tipo_ponto'] && empty($tipo_ponto)) {
                redirect('ocorrencias_edit.php?id=' . $ocorrencia_id_edit, 'Selecione o tipo de ponto!', 'error');
            }
            
            // Valida campos dinâmicos se existirem
            $campos_dinamicos = get_campos_dinamicos_tipo($tipo_ocorrencia_id);
            if (!empty($campos_dinamicos) && !empty($_POST['campos_dinamicos'])) {
                $erros_validacao = validar_campos_dinamicos($campos_dinamicos, $_POST['campos_dinamicos']);
                if (!empty($erros_validacao)) {
                    redirect('ocorrencias_edit.php?id=' . $ocorrencia_id_edit, implode('<br>', $erros_validacao), 'error');
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
        
        // Busca dados anteriores da ocorrência para reversão de banco de horas
        $ocorrencia_anterior = $ocorrencia_existente;
        $tem_desconto_banco_anterior = !empty($ocorrencia_anterior['desconta_banco_horas']) && $ocorrencia_anterior['desconta_banco_horas'] == 1;
        $movimentacao_banco_anterior = $ocorrencia_anterior['banco_horas_movimentacao_id'] ?? null;
        
        // Atualiza ocorrência
        $stmt = $pdo->prepare("
            UPDATE ocorrencias SET
                descricao = ?, 
                data_ocorrencia = ?, 
                hora_ocorrencia = ?, 
                tempo_atraso_minutos = ?, 
                horario_esperado = ?,
                horario_real = ?,
                tipo_ponto = ?, 
                severidade = ?,
                considera_dia_inteiro = ?,
                apenas_informativa = ?,
                tags = ?, 
                campos_dinamicos = ?,
                valor_desconto = NULL,
                desconta_banco_horas = 0,
                banco_horas_movimentacao_id = NULL,
                horas_descontadas = NULL
            WHERE id = ?
        ");
        $stmt->execute([
            $descricao, 
            $data_ocorrencia,
            $hora_ocorrencia,
            $tempo_atraso_minutos,
            $horario_esperado,
            $horario_real,
            $tipo_ponto,
            $severidade,
            $considera_dia_inteiro ? 1 : 0,
            $apenas_informativa ? 1 : 0,
            $tags,
            $campos_dinamicos_valores,
            $ocorrencia_id_edit
        ]);
        
        $ocorrencia_id = $ocorrencia_id_edit;
        
        // Reverte desconto anterior do banco de horas se existia
        if ($tem_desconto_banco_anterior && $movimentacao_banco_anterior) {
            // Busca movimentação anterior
            $stmt = $pdo->prepare("SELECT * FROM banco_horas_movimentacoes WHERE id = ?");
            $stmt->execute([$movimentacao_banco_anterior]);
            $movimentacao_anterior = $stmt->fetch();
            
            if ($movimentacao_anterior) {
                // Reverte adicionando as horas de volta
                $horas_reverter = abs($movimentacao_anterior['quantidade_horas']);
                adicionar_horas_banco(
                    $colaborador_id,
                    $horas_reverter,
                    'ocorrencia_edicao',
                    $ocorrencia_id,
                    'Reversão de desconto por edição de ocorrência',
                    'Horas revertidas ao editar ocorrência #' . $ocorrencia_id,
                    $usuario['id']
                );
            }
        }
        
        // Se for apenas informativa, não calcula desconto nem banco de horas
        if (!$apenas_informativa) {
            // Verifica tipo de desconto escolhido
            $tipo_desconto = $_POST['tipo_desconto'] ?? 'dinheiro';
            $desconta_banco_horas = ($tipo_desconto === 'banco_horas') ? true : false;
            
            if ($desconta_banco_horas && $tipo_ocorrencia_data && $tipo_ocorrencia_data['permite_desconto_banco_horas']) {
                // Desconta do banco de horas
                $resultado = descontar_horas_banco_ocorrencia($ocorrencia_id, $usuario['id']);
                
                if (!$resultado['success']) {
                    // Log erro mas não impede edição da ocorrência
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
        } else {
            // Se for apenas informativa, garante que não há desconto
            $stmt = $pdo->prepare("UPDATE ocorrencias SET valor_desconto = NULL, desconta_banco_horas = 0, horas_descontadas = NULL WHERE id = ?");
            $stmt->execute([$ocorrencia_id]);
        }
        
        // Processa anexos
        if (!empty($_FILES['anexos']['name'][0])) {
            foreach ($_FILES['anexos']['name'] as $key => $name) {
                if (!empty($name) && $_FILES['anexos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $name,
                        'type' => $_FILES['anexos']['type'][$key],
                        'tmp_name' => $_FILES['anexos']['tmp_name'][$key],
                        'error' => $_FILES['anexos']['error'][$key],
                        'size' => $_FILES['anexos']['size'][$key]
                    ];
                    
                    $upload_result = upload_anexo_ocorrencia($file, $ocorrencia_id);
                    if ($upload_result['success']) {
                        try {
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
                        } catch (PDOException $e) {
                            error_log("Erro ao salvar anexo no banco: " . $e->getMessage());
                            // Não interrompe o processo, apenas loga o erro
                        }
                    } else {
                        error_log("Erro no upload do anexo: " . ($upload_result['error'] ?? 'Erro desconhecido'));
                    }
                } else {
                    if ($_FILES['anexos']['error'][$key] !== UPLOAD_ERR_OK) {
                        error_log("Erro no upload do arquivo (código: " . $_FILES['anexos']['error'][$key] . "): " . $name);
                    }
                }
            }
        }
        
        // Registra histórico
        registrar_historico_ocorrencia($ocorrencia_id, 'editada', $usuario['id'], null, null, null, 'Ocorrência editada');
        
        $pdo->commit();
        
        redirect('ocorrencia_view.php?id=' . $ocorrencia_id, 'Ocorrência atualizada com sucesso!');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('ocorrencias_edit.php?id=' . $ocorrencia_id, 'Erro ao atualizar: ' . $e->getMessage(), 'error');
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

// Busca tags da ocorrência existente
$tags_ocorrencia = [];
if (!empty($ocorrencia_existente['tags'])) {
    $tags_array = json_decode($ocorrencia_existente['tags'], true);
    if ($tags_array) {
        $tags_ocorrencia = $tags_array;
    }
}

// Busca campos dinâmicos da ocorrência existente
$campos_dinamicos_existentes = [];
if (!empty($ocorrencia_existente['campos_dinamicos'])) {
    $campos_dinamicos_existentes = json_decode($ocorrencia_existente['campos_dinamicos'], true);
}

// Agora inclui o header (após processar POST para evitar erro de headers already sent)
$page_title = 'Editar Ocorrência';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Editar Ocorrência</h1>
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
                <li class="breadcrumb-item text-gray-900">Editar</li>
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
                    <span class="card-label fw-bold fs-3 mb-1">Editar Ocorrência</span>
                    <span class="text-muted fw-semibold fs-7">Atualize os dados da ocorrência (o colaborador e o tipo de ocorrência não podem ser alterados)</span>
                </h3>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <form id="kt_form_ocorrencia" method="POST" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="ocorrencia_id" value="<?= $ocorrencia_id ?>" />
                    <input type="hidden" name="tipo_ocorrencia_id" value="<?= $ocorrencia_existente['tipo_ocorrencia_id'] ?>" />
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                            <?php 
                            // Busca nome do colaborador para exibir
                            $stmt_colab = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
                            $stmt_colab->execute([$colaborador_id]);
                            $colab_nome = $stmt_colab->fetch();
                            ?>
                            <input type="text" class="form-control form-control-solid" value="<?= htmlspecialchars($colab_nome['nome_completo'] ?? 'N/A') ?>" disabled style="background-color: #f1f1f1; cursor: not-allowed;" />
                            <input type="hidden" name="colaborador_id" value="<?= $colaborador_id ?>" />
                            <small class="text-muted d-block mt-2">O colaborador não pode ser alterado</small>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Tipo de Ocorrência</label>
                            <select name="tipo_ocorrencia_id" id="tipo_ocorrencia_id" class="form-select form-select-solid" disabled required style="background-color: #f1f1f1; cursor: not-allowed;">
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
                                    $selected = ($tipo['id'] == $ocorrencia_existente['tipo_ocorrencia_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $tipo['id'] ?>" <?= $selected ?> 
                                        data-permite-tempo="<?= $tipo['permite_tempo_atraso'] ?>"
                                        data-permite-ponto="<?= $tipo['permite_tipo_ponto'] ?>"
                                        data-permite-dia-inteiro="<?= $tipo['permite_considerar_dia_inteiro'] ?? 0 ?>"
                                        data-permite-desconto-banco="<?= $tipo['permite_desconto_banco_horas'] ?? 0 ?>"
                                        data-calcula-desconto="<?= $tipo['calcula_desconto'] ?? 0 ?>"
                                        data-valor-desconto="<?= $tipo['valor_desconto'] ?? 0 ?>"
                                        data-severidade="<?= htmlspecialchars($tipo['severidade'] ?? 'moderada') ?>"
                                        data-requer-aprovacao="<?= $tipo['requer_aprovacao'] ?? 0 ?>"
                                        data-template="<?= htmlspecialchars($tipo['template_descricao'] ?? '') ?>"
                                        data-codigo="<?= htmlspecialchars($tipo['codigo'] ?? '') ?>">
                                        <?= htmlspecialchars($tipo['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($categoria_atual !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="required fw-semibold fs-6 mb-2">Data da Ocorrência</label>
                            <input type="date" name="data_ocorrencia" id="data_ocorrencia" class="form-control form-control-solid" value="<?= $ocorrencia_existente['data_ocorrencia'] ?? date('Y-m-d') ?>" required />
                        </div>
                        <div class="col-md-3">
                            <label class="fw-semibold fs-6 mb-2">Hora da Ocorrência</label>
                            <input type="time" name="hora_ocorrencia" id="hora_ocorrencia" class="form-control form-control-solid" value="<?= $ocorrencia_existente['hora_ocorrencia'] ?? '' ?>" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Severidade</label>
                            <select name="severidade" id="severidade" class="form-select form-select-solid">
                                <option value="leve" <?= ($ocorrencia_existente['severidade'] ?? 'moderada') == 'leve' ? 'selected' : '' ?>>Leve</option>
                                <option value="moderada" <?= ($ocorrencia_existente['severidade'] ?? 'moderada') == 'moderada' ? 'selected' : '' ?>>Moderada</option>
                                <option value="grave" <?= ($ocorrencia_existente['severidade'] ?? 'moderada') == 'grave' ? 'selected' : '' ?>>Grave</option>
                                <option value="critica" <?= ($ocorrencia_existente['severidade'] ?? 'moderada') == 'critica' ? 'selected' : '' ?>>Crítica</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Tags</label>
                            <select name="tags[]" id="tags" class="form-select form-select-solid" multiple>
                                <?php 
                                $tags_disponiveis = get_tags_ocorrencias();
                                foreach ($tags_disponiveis as $tag): 
                                    $selected_tag = in_array($tag['id'], $tags_ocorrencia) ? 'selected' : '';
                                ?>
                                    <option value="<?= $tag['id'] ?>" data-cor="<?= htmlspecialchars($tag['cor']) ?>" <?= $selected_tag ?>>
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
                            <input type="number" name="tempo_atraso_minutos" id="tempo_atraso_minutos" class="form-control form-control-solid" min="1" placeholder="Ex: 15" value="<?= $ocorrencia_existente['tempo_atraso_minutos'] ?? '' ?>" />
                            <small class="text-muted d-block mt-1">Informe quantos minutos de atraso</small>
                            <small class="text-primary fw-semibold d-block mt-1" id="tempo_convertido">
                                <i class="ki-duotone ki-time fs-6 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span id="tempo_convertido_texto">-</span>
                            </small>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-semibold fs-6 mb-2">Horário Esperado</label>
                            <input type="time" name="horario_esperado" id="horario_esperado" class="form-control form-control-solid" value="<?= $ocorrencia_existente['horario_esperado'] ?? '' ?>" />
                            <small class="text-muted d-block mt-1">Horário que deveria ter chegado</small>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-semibold fs-6 mb-2">Horário Real</label>
                            <input type="time" name="horario_real" id="horario_real" class="form-control form-control-solid" value="<?= $ocorrencia_existente['horario_real'] ?? '' ?>" />
                            <small class="text-muted d-block mt-1">Horário que realmente chegou</small>
                        </div>
                    </div>
                    
                    <!-- Campo: Considerar Dia Inteiro -->
                    <div class="row mb-7" id="campo_considera_dia_inteiro" style="display: none;">
                        <div class="col-md-12">
                            <div class="card card-flush bg-light-warning">
                                <div class="card-body">
                                    <div class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="considera_dia_inteiro" id="considera_dia_inteiro" value="1" <?= (!empty($ocorrencia_existente['considera_dia_inteiro']) && $ocorrencia_existente['considera_dia_inteiro'] == 1) ? 'checked' : '' ?> />
                                        <label class="form-check-label fw-bold" for="considera_dia_inteiro">
                                            Considerar como falta do dia inteiro (8 horas)
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Quando marcado, esta ocorrência será tratada como falta completa do dia (8 horas de trabalho) ao invés de apenas minutos de atraso.
                                        <br><strong>Importante:</strong> Se marcar esta opção, o campo de minutos será ignorado e será considerado como falta do dia inteiro.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-7" id="campo_tipo_ponto" style="display: none;">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Tipo de Ponto</label>
                            <select name="tipo_ponto" id="tipo_ponto" class="form-select form-select-solid">
                                <option value="">Selecione...</option>
                                <option value="entrada" <?= ($ocorrencia_existente['tipo_ponto'] ?? '') == 'entrada' ? 'selected' : '' ?>>Entrada</option>
                                <option value="almoco" <?= ($ocorrencia_existente['tipo_ponto'] ?? '') == 'almoco' ? 'selected' : '' ?>>Almoço</option>
                                <option value="cafe" <?= ($ocorrencia_existente['tipo_ponto'] ?? '') == 'cafe' ? 'selected' : '' ?>>Café</option>
                                <option value="saida" <?= ($ocorrencia_existente['tipo_ponto'] ?? '') == 'saida' ? 'selected' : '' ?>>Saída</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Campo: Apenas Informativa -->
                    <div class="row mb-7" id="campo_apenas_informativa" style="display: none;">
                        <div class="col-md-12">
                            <div class="card card-flush bg-light-success">
                                <div class="card-body">
                                    <div class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="apenas_informativa" id="apenas_informativa" value="1" <?= (!empty($ocorrencia_existente['apenas_informativa']) && $ocorrencia_existente['apenas_informativa'] == 1) ? 'checked' : '' ?> />
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
                    
                    <!-- Campo: Tipo de Desconto (R$ ou Banco de Horas) -->
                    <div class="row mb-7" id="campo_tipo_desconto" style="display: none;">
                        <div class="col-md-12">
                            <div class="card card-flush bg-light-info">
                                <div class="card-body">
                                    <h5 class="fw-bold mb-4">Como será descontado?</h5>
                                    <div class="form-check form-check-custom form-check-solid mb-5">
                                        <input class="form-check-input" type="radio" name="tipo_desconto" 
                                               id="tipo_desconto_dinheiro" value="dinheiro" <?= (empty($ocorrencia_existente['desconta_banco_horas']) || $ocorrencia_existente['desconta_banco_horas'] == 0) ? 'checked' : '' ?> />
                                        <label class="form-check-label fw-bold" for="tipo_desconto_dinheiro">
                                            <i class="ki-duotone ki-dollar fs-2 text-danger me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Descontar do Pagamento (R$)
                                        </label>
                                        <div class="text-muted fs-7 ms-8 mt-1">
                                            O valor será descontado no fechamento de pagamento do mês
                                        </div>
                                    </div>
                                    <div class="form-check form-check-custom form-check-solid mb-3" id="opcao_banco_horas_container">
                                        <input class="form-check-input" type="radio" name="tipo_desconto" 
                                               id="tipo_desconto_banco_horas" value="banco_horas" <?= (!empty($ocorrencia_existente['desconta_banco_horas']) && $ocorrencia_existente['desconta_banco_horas'] == 1) ? 'checked' : '' ?> />
                                        <label class="form-check-label fw-bold" for="tipo_desconto_banco_horas">
                                            <i class="ki-duotone ki-time fs-2 text-warning me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Descontar do Banco de Horas (Ficar Devendo Horas)
                                        </label>
                                        <div class="text-muted fs-7 ms-8 mt-1">
                                            O colaborador ficará devendo horas no banco de horas
                                        </div>
                                    </div>
                                    <input type="hidden" name="desconta_banco_horas" id="desconta_banco_horas" value="0" />
                                    
                                    <!-- Informações do Banco de Horas (quando selecionado) -->
                                    <div id="info_desconto_banco" style="display: none;" class="mt-4 p-4 bg-light-warning rounded">
                                        <h6 class="fw-bold mb-3">Informações do Banco de Horas</h6>
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
                                    
                                    <!-- Informações do Desconto em R$ (quando selecionado) -->
                                    <div id="info_desconto_dinheiro" style="display: none;" class="mt-4 p-4 bg-light-danger rounded">
                                        <h6 class="fw-bold mb-3">Informações do Desconto em Dinheiro</h6>
                                        <div>
                                            <strong>Valor a descontar:</strong> 
                                            <span id="valor_desconto_ocorrencia" class="fw-bold fs-4 text-danger">-</span>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            Este valor será calculado automaticamente e descontado no fechamento de pagamento.
                                        </small>
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
                            <textarea name="descricao" id="descricao" class="form-control form-control-solid" rows="5" placeholder="Descreva detalhadamente a ocorrência..."><?= htmlspecialchars($ocorrencia_existente['descricao'] ?? '') ?></textarea>
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
                        <a href="ocorrencia_view.php?id=<?= $ocorrencia_id ?>" class="btn btn-light me-3">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Atualizar Ocorrência</span>
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
// Inicializa campos ao carregar página
(function() {
    // Aguarda o DOM estar pronto
    function inicializarCampos() {
        const tipoOcorrenciaId = document.getElementById('tipo_ocorrencia_id');
        if (tipoOcorrenciaId) {
            // Campo já está disabled, não precisa de proteção extra
            if (tipoOcorrenciaId.value) {
                // Simula mudança de tipo para carregar campos dinâmicos
                tipoOcorrenciaId.dispatchEvent(new Event('change'));
                
                // Carrega campos dinâmicos com valores existentes
                <?php if (!empty($ocorrencia_existente['tipo_ocorrencia_id'])): ?>
                setTimeout(function() {
                    carregarCamposDinamicosFormulario(<?= $ocorrencia_existente['tipo_ocorrencia_id'] ?>);
                    // Atualiza valores após carregar campos
                    setTimeout(function() {
                        atualizarConversaoTempo();
                        atualizarInfoDescontoBanco();
                        atualizarValorDescontoDinheiro();
                    }, 200);
                }, 300);
                <?php endif; ?>
            }
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarCampos);
    } else {
        inicializarCampos();
    }
})();

// Mostra/esconde campos condicionais baseado no tipo selecionado
document.getElementById('tipo_ocorrencia_id').addEventListener('change', function() {
    const tipoId = this.value;
    const option = this.options[this.selectedIndex];
    const permiteTempo = option.getAttribute('data-permite-tempo') === '1';
    const permitePonto = option.getAttribute('data-permite-ponto') === '1';
    const permiteDiaInteiro = option.getAttribute('data-permite-dia-inteiro') === '1';
    const severidade = option.getAttribute('data-severidade') || 'moderada';
    const requerAprovacao = option.getAttribute('data-requer-aprovacao') === '1';
    const template = option.getAttribute('data-template') || '';
    const permiteDescontoBanco = option.getAttribute('data-permite-desconto-banco') === '1';
    const calculaDesconto = option.getAttribute('data-calcula-desconto') === '1';
    const codigoTipo = option.getAttribute('data-codigo') || '';
    
    // Atualiza severidade
    document.getElementById('severidade').value = severidade;
    
    // Mostra/esconde campo "Apenas Informativa" (só aparece se o tipo permite desconto)
    const campoApenasInformativa = document.getElementById('campo_apenas_informativa');
    if (permiteDescontoBanco || calculaDesconto) {
        if (campoApenasInformativa) {
            campoApenasInformativa.style.display = 'block';
        }
    } else {
        if (campoApenasInformativa) {
            campoApenasInformativa.style.display = 'none';
            document.getElementById('apenas_informativa').checked = false;
        }
    }
    
    // Mostra/esconde campo de tipo de desconto (R$ ou Banco de Horas)
    const campoTipoDesconto = document.getElementById('campo_tipo_desconto');
    const opcaoBancoHoras = document.getElementById('opcao_banco_horas_container');
    
    console.log('permiteDescontoBanco:', permiteDescontoBanco);
    console.log('calculaDesconto:', calculaDesconto);
    console.log('campoTipoDesconto encontrado:', campoTipoDesconto);
    
    // Mostra campo se permite desconto em R$ OU banco de horas
    if (permiteDescontoBanco || calculaDesconto) {
        if (campoTipoDesconto) {
            campoTipoDesconto.style.display = 'block';
            console.log('Campo tipo desconto EXIBIDO');
        }
        
        // Mostra/esconde opção de banco de horas
        if (opcaoBancoHoras) {
            if (permiteDescontoBanco) {
                opcaoBancoHoras.style.display = 'block';
                console.log('Opção banco horas EXIBIDA');
            } else {
                opcaoBancoHoras.style.display = 'none';
                console.log('Opção banco horas OCULTA');
                // Se não permite banco de horas, força seleção de dinheiro
                const radioDinheiro = document.getElementById('tipo_desconto_dinheiro');
                if (radioDinheiro) {
                    radioDinheiro.checked = true;
                }
                document.getElementById('desconta_banco_horas').value = '0';
            }
        }
        
        // Se só permite um tipo, seleciona automaticamente
        if (permiteDescontoBanco && !calculaDesconto) {
            const radioBanco = document.getElementById('tipo_desconto_banco_horas');
            if (radioBanco) {
                radioBanco.checked = true;
            }
            document.getElementById('desconta_banco_horas').value = '1';
            atualizarInfoDescontoBanco();
        } else if (!permiteDescontoBanco && calculaDesconto) {
            const radioDinheiro = document.getElementById('tipo_desconto_dinheiro');
            if (radioDinheiro) {
                radioDinheiro.checked = true;
            }
            document.getElementById('desconta_banco_horas').value = '0';
        } else {
            atualizarInfoDescontoBanco();
            atualizarValorDescontoDinheiro();
        }
    } else {
        if (campoTipoDesconto) {
            campoTipoDesconto.style.display = 'none';
            console.log('Campo tipo desconto OCULTO');
        }
        document.getElementById('desconta_banco_horas').value = '0';
        document.getElementById('info_desconto_banco').style.display = 'none';
        document.getElementById('info_desconto_dinheiro').style.display = 'none';
    }
    
    // Mostra/esconde campo de considerar dia inteiro primeiro
    const campoDiaInteiro = document.getElementById('campo_considera_dia_inteiro');
    if (permiteDiaInteiro) {
        campoDiaInteiro.style.display = 'block';
    } else {
        campoDiaInteiro.style.display = 'none';
        document.getElementById('considera_dia_inteiro').checked = false;
    }
    
    // Mostra/esconde campo de tempo de atraso
    const campoTempo = document.getElementById('campo_tempo_atraso');
    const consideraDiaInteiro = document.getElementById('considera_dia_inteiro')?.checked;
    
    console.log('permiteTempo:', permiteTempo);
    console.log('campoTempo encontrado:', campoTempo);
    console.log('permiteDiaInteiro:', permiteDiaInteiro);
    console.log('consideraDiaInteiro:', consideraDiaInteiro);
    
    if (permiteTempo && campoTempo) {
        // Se permite considerar dia inteiro E está marcado, esconde o campo de minutos
        if (permiteDiaInteiro && consideraDiaInteiro) {
            campoTempo.style.display = 'none';
            console.log('Campo tempo OCULTO (dia inteiro marcado)');
            document.getElementById('tempo_atraso_minutos').value = '';
            document.getElementById('tempo_atraso_minutos').required = false;
            document.getElementById('tempo_atraso_minutos').disabled = true;
        } else {
            campoTempo.style.display = 'block';
            console.log('Campo tempo EXIBIDO');
            document.getElementById('tempo_atraso_minutos').disabled = false;
            // Só torna obrigatório se não permitir considerar dia inteiro OU se não estiver marcado como dia inteiro
            document.getElementById('tempo_atraso_minutos').required = !permiteDiaInteiro || !consideraDiaInteiro;
        }
    } else {
        if (campoTempo) {
            campoTempo.style.display = 'none';
            console.log('Campo tempo OCULTO (não permite tempo)');
        }
        const tempoInput = document.getElementById('tempo_atraso_minutos');
        if (tempoInput) {
            tempoInput.value = '';
            tempoInput.required = false;
            tempoInput.disabled = false;
        }
    }
    
    // Re-adiciona listener após mudança de tipo de ocorrência (quando campo é habilitado)
    if (permiteTempo && (!permiteDiaInteiro || !consideraDiaInteiro)) {
        setTimeout(function() {
            adicionarListenerTempoAtraso();
            atualizarConversaoTempo();
        }, 100);
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
    
    // Inicializa valores existentes após carregar campos
    setTimeout(function() {
        <?php if (!empty($campos_dinamicos_existentes)): ?>
        const camposExistentes = <?= json_encode($campos_dinamicos_existentes) ?>;
        Object.keys(camposExistentes).forEach(function(codigo) {
            const campo = document.getElementById('campo_' + codigo);
            if (campo) {
                campo.value = camposExistentes[codigo];
            }
        });
        <?php endif; ?>
    }, 500);
    
    // Atualiza campo oculto tipo para compatibilidade
    document.getElementById('tipo').value = option.text.trim();
    
    // Atualiza valores quando tipo de ocorrência muda
    atualizarConversaoTempo();
    atualizarInfoDescontoBanco();
    atualizarValorDescontoDinheiro();
    
    // Atualiza visibilidade dos campos baseado em "apenas informativa"
    atualizarCamposApenasInformativa();
});

// Função para atualizar visibilidade dos campos quando "apenas informativa" muda
function atualizarCamposApenasInformativa() {
    const apenasInformativa = document.getElementById('apenas_informativa')?.checked;
    const campoTipoDesconto = document.getElementById('campo_tipo_desconto');
    
    if (apenasInformativa) {
        // Se marcado como informativa, esconde campos de desconto
        if (campoTipoDesconto) {
            campoTipoDesconto.style.display = 'none';
        }
        // Desmarca opções de desconto
        document.getElementById('tipo_desconto_dinheiro').checked = false;
        document.getElementById('tipo_desconto_banco_horas').checked = false;
        document.getElementById('desconta_banco_horas').value = '0';
        document.getElementById('info_desconto_banco').style.display = 'none';
        document.getElementById('info_desconto_dinheiro').style.display = 'none';
    } else {
        // Se desmarcado, mostra campos de desconto se o tipo permitir
        const tipoOcorrencia = document.getElementById('tipo_ocorrencia_id');
        const option = tipoOcorrencia?.options[tipoOcorrencia.selectedIndex];
        const permiteDescontoBanco = option?.getAttribute('data-permite-desconto-banco') === '1';
        const calculaDesconto = option?.getAttribute('data-calcula-desconto') === '1';
        
        if ((permiteDescontoBanco || calculaDesconto) && campoTipoDesconto) {
            campoTipoDesconto.style.display = 'block';
            // Reativa os listeners do tipo de ocorrência para atualizar campos
            tipoOcorrencia.dispatchEvent(new Event('change'));
        }
    }
}

// Listener para checkbox "Apenas Informativa"
document.getElementById('apenas_informativa')?.addEventListener('change', function() {
    atualizarCamposApenasInformativa();
});

// Função para converter minutos em horas:minutos
function converterMinutosParaHorasMinutos(minutos) {
    if (!minutos || minutos <= 0) {
        return '-';
    }
    const horas = Math.floor(minutos / 60);
    const mins = minutos % 60;
    if (horas > 0 && mins > 0) {
        return `${horas}h ${mins}min`;
    } else if (horas > 0) {
        return `${horas}h`;
    } else {
        return `${mins}min`;
    }
}

// Função para atualizar conversão de tempo
function atualizarConversaoTempo() {
    const tempoAtraso = parseInt(document.getElementById('tempo_atraso_minutos')?.value || 0);
    const tempoConvertidoTexto = document.getElementById('tempo_convertido_texto');
    const tempoConvertidoContainer = document.getElementById('tempo_convertido');
    
    if (tempoConvertidoTexto && tempoConvertidoContainer) {
        if (tempoAtraso > 0) {
            const tempoFormatado = converterMinutosParaHorasMinutos(tempoAtraso);
            tempoConvertidoTexto.textContent = `Equivale a: ${tempoFormatado}`;
            tempoConvertidoContainer.style.display = 'block';
        } else {
            tempoConvertidoTexto.textContent = '-';
            tempoConvertidoContainer.style.display = 'none';
        }
    }
}

// Função para atualizar valores quando tempo de atraso muda
function atualizarValoresTempoAtraso() {
    console.log('atualizarValoresTempoAtraso chamada');
    const tempoAtraso = document.getElementById('tempo_atraso_minutos')?.value;
    const consideraDiaInteiro = document.getElementById('considera_dia_inteiro')?.checked;
    console.log('Tempo atraso:', tempoAtraso);
    console.log('Considera dia inteiro:', consideraDiaInteiro);
    
    // Atualiza conversão de tempo
    atualizarConversaoTempo();
    
    // Pequeno delay para garantir que o valor foi atualizado
    setTimeout(function() {
        atualizarInfoDescontoBanco();
        atualizarValorDescontoDinheiro();
    }, 50);
}

// Listener global para campo de tempo de atraso (atualiza em tempo real)
function adicionarListenerTempoAtraso() {
    const tempoAtraso = document.getElementById('tempo_atraso_minutos');
    if (tempoAtraso) {
        // Remove listeners anteriores se existirem (usando clone para remover todos)
        const novoInput = tempoAtraso.cloneNode(true);
        tempoAtraso.parentNode.replaceChild(novoInput, tempoAtraso);
        
        // Adiciona listeners no novo elemento
        const campoAtualizado = document.getElementById('tempo_atraso_minutos');
        if (campoAtualizado) {
            // Listener de input (atualiza enquanto digita)
            campoAtualizado.addEventListener('input', function() {
                atualizarValoresTempoAtraso();
            });
            
            // Listener de change (atualiza quando perde o foco)
            campoAtualizado.addEventListener('change', function() {
                atualizarValoresTempoAtraso();
            });
            
            // Listener de keyup (atualiza quando solta a tecla)
            campoAtualizado.addEventListener('keyup', function() {
                atualizarValoresTempoAtraso();
            });
        }
    }
}

// Usa delegação de eventos para garantir que funcione mesmo quando campo é criado dinamicamente
document.addEventListener('input', function(e) {
    if (e.target && e.target.id === 'tempo_atraso_minutos') {
        console.log('[HORADESCONTA] Evento input capturado no campo tempo_atraso_minutos, valor:', e.target.value);
        atualizarValoresTempoAtraso();
    }
});

document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'tempo_atraso_minutos') {
        console.log('[HORADESCONTA] Evento change capturado no campo tempo_atraso_minutos, valor:', e.target.value);
        atualizarValoresTempoAtraso();
    }
});

document.addEventListener('keyup', function(e) {
    if (e.target && e.target.id === 'tempo_atraso_minutos') {
        console.log('[HORADESCONTA] Evento keyup capturado no campo tempo_atraso_minutos, valor:', e.target.value);
        atualizarValoresTempoAtraso();
    }
});

// Adiciona listener quando a página carrega (para garantir que funcione se campo já existir)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            adicionarListenerTempoAtraso();
            atualizarConversaoTempo();
        }, 200);
    });
} else {
    setTimeout(function() {
        adicionarListenerTempoAtraso();
        atualizarConversaoTempo();
    }, 200);
}

// Função para atualizar informações de desconto do banco de horas
function atualizarInfoDescontoBanco() {
    // Busca colaborador_id de diferentes formas (select ou input hidden)
    let colaboradorId = null;
    const colaboradorSelect = document.getElementById('colaborador_id');
    const colaboradorHidden = document.querySelector('input[name="colaborador_id"]');
    
    if (colaboradorSelect) {
        colaboradorId = colaboradorSelect.value;
    } else if (colaboradorHidden) {
        colaboradorId = colaboradorHidden.value;
    }
    
    const tipoOcorrencia = document.getElementById('tipo_ocorrencia_id');
    const option = tipoOcorrencia?.options[tipoOcorrencia.selectedIndex];
    const codigoTipo = option?.getAttribute('data-codigo') || '';
    const tempoAtraso = parseFloat(document.getElementById('tempo_atraso_minutos')?.value || 0);
    
    console.log('[HORADESCONTA] atualizarInfoDescontoBanco - colaboradorId:', colaboradorId);
    
    if (!colaboradorId || !codigoTipo) {
        document.getElementById('info_desconto_banco').style.display = 'none';
        return;
    }
    
    // Calcula horas a descontar baseado no tipo
    let horasDescontar = 0;
    const consideraDiaInteiro = document.getElementById('considera_dia_inteiro')?.checked;
    
    if (codigoTipo === 'falta' || codigoTipo === 'ausencia_injustificada') {
        // Para falta: se considerar dia inteiro OU não tem tempo de atraso informado, calcula como dia inteiro
        if (consideraDiaInteiro || tempoAtraso === 0) {
            horasDescontar = 8; // Jornada padrão
        } else {
            // Se tem tempo de atraso informado e não está marcado como dia inteiro, calcula proporcional
            horasDescontar = tempoAtraso / 60; // Converte minutos para horas
        }
    } else if (['atraso_entrada', 'atraso_almoco', 'atraso_cafe', 'saida_antecipada'].includes(codigoTipo)) {
        if (consideraDiaInteiro) {
            horasDescontar = 8; // Considera como falta do dia inteiro
        } else {
            horasDescontar = tempoAtraso / 60; // Converte minutos para horas
        }
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
                
                // Mostra info baseado no tipo selecionado
                const tipoDesconto = document.querySelector('input[name="tipo_desconto"]:checked')?.value;
                if (tipoDesconto === 'banco_horas') {
                    document.getElementById('info_desconto_banco').style.display = 'block';
                    document.getElementById('info_desconto_dinheiro').style.display = 'none';
                } else {
                    document.getElementById('info_desconto_banco').style.display = 'none';
                    document.getElementById('info_desconto_dinheiro').style.display = 'block';
                    atualizarValorDescontoDinheiro();
                }
            }
        })
        .catch(error => {
            console.error('Erro ao buscar saldo:', error);
        });
}

// Função para calcular e atualizar valor do desconto em R$
function atualizarValorDescontoDinheiro() {
    console.log('[HORADESCONTA] === atualizarValorDescontoDinheiro chamada ===');
    
    // Busca colaborador_id de diferentes formas (select ou input hidden)
    let colaboradorId = null;
    const colaboradorSelect = document.getElementById('colaborador_id');
    const colaboradorHidden = document.querySelector('input[name="colaborador_id"]');
    
    if (colaboradorSelect) {
        colaboradorId = colaboradorSelect.value;
    } else if (colaboradorHidden) {
        colaboradorId = colaboradorHidden.value;
    }
    
    const tipoOcorrencia = document.getElementById('tipo_ocorrencia_id');
    const option = tipoOcorrencia?.options[tipoOcorrencia.selectedIndex];
    
    console.log('[HORADESCONTA] colaboradorId:', colaboradorId);
    console.log('[HORADESCONTA] colaboradorSelect encontrado:', colaboradorSelect);
    console.log('[HORADESCONTA] colaboradorHidden encontrado:', colaboradorHidden);
    console.log('[HORADESCONTA] option:', option);
    
    // Verifica se o tipo de desconto é dinheiro ou se não há seleção (padrão)
    const tipoDesconto = document.querySelector('input[name="tipo_desconto"]:checked')?.value;
    const apenasInformativa = document.getElementById('apenas_informativa')?.checked;
    const containerInfo = document.getElementById('info_desconto_dinheiro');
    
    console.log('[HORADESCONTA] Tipo de desconto:', tipoDesconto);
    console.log('[HORADESCONTA] Container encontrado:', containerInfo);
    console.log('[HORADESCONTA] Apenas informativa:', apenasInformativa);
    
    // Se for apenas informativa, não mostra desconto
    if (apenasInformativa) {
        if (containerInfo) {
            containerInfo.style.display = 'none';
        }
        console.log('[HORADESCONTA] Ocorrência é apenas informativa, não calcula desconto');
        return;
    }
    
    // Garante que o container está visível ANTES de calcular se for desconto em dinheiro
    // Mostra container se for desconto em dinheiro OU se não houver seleção (padrão dinheiro)
    if (containerInfo && (tipoDesconto === 'dinheiro' || !tipoDesconto)) {
        containerInfo.style.display = 'block';
        console.log('[HORADESCONTA] Container info_desconto_dinheiro FORÇADO a exibir');
    }
    
    if (!colaboradorId || !option) {
        console.log('[HORADESCONTA] Sem colaborador ou tipo de ocorrência');
        const elementoValor = document.getElementById('valor_desconto_ocorrencia');
        if (elementoValor) {
            elementoValor.textContent = '-';
        }
        return;
    }
    
    const valorFixo = parseFloat(option.getAttribute('data-valor-desconto') || 0);
    const codigoTipo = option.getAttribute('data-codigo') || '';
    const tempoAtrasoInput = document.getElementById('tempo_atraso_minutos');
    const tempoAtraso = parseFloat(tempoAtrasoInput?.value || 0);
    const consideraDiaInteiro = document.getElementById('considera_dia_inteiro')?.checked;
    
    console.log('[HORADESCONTA] valorFixo:', valorFixo);
    console.log('[HORADESCONTA] codigoTipo:', codigoTipo);
    console.log('[HORADESCONTA] tempoAtraso:', tempoAtraso);
    console.log('[HORADESCONTA] consideraDiaInteiro:', consideraDiaInteiro);
    
    // Se tem valor fixo, usa ele
    if (valorFixo > 0) {
        const elementoValor = document.getElementById('valor_desconto_ocorrencia');
        if (elementoValor) {
            elementoValor.textContent = 'R$ ' + valorFixo.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            console.log('[HORADESCONTA] Valor fixo atualizado:', elementoValor.textContent);
        }
        return;
    }
    
    // Busca informações do colaborador
    fetch(`../api/colaboradores/info.php?colaborador_id=${colaboradorId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('valor_desconto_ocorrencia').textContent = 'Erro ao buscar dados do colaborador';
                console.error('[HORADESCONTA] Erro na API:', data.error);
                return;
            }
            
            if (!data.data.salario || data.data.salario <= 0) {
                document.getElementById('valor_desconto_ocorrencia').textContent = 'Salário não informado';
                console.warn('[HORADESCONTA] Salário não encontrado para colaborador:', colaboradorId, data);
                return;
            }
            
            const salario = parseFloat(data.data.salario);
            const jornadaDiaria = parseFloat(data.data.jornada_diaria_horas || 8);
            const horasMes = 220; // Padrão CLT
            const valorHora = salario / horasMes;
            let valorDesconto = 0;
            
            console.log('[HORADESCONTA] salario:', salario);
            console.log('[HORADESCONTA] jornadaDiaria:', jornadaDiaria);
            console.log('[HORADESCONTA] valorHora:', valorHora);
            
            // Calcula desconto baseado no tipo
            if (codigoTipo === 'falta' || codigoTipo === 'ausencia_injustificada') {
                // Para falta: se considerar dia inteiro OU não tem tempo de atraso informado, calcula como dia inteiro
                if (consideraDiaInteiro || (tempoAtraso === 0 && !document.getElementById('tempo_atraso_minutos')?.value)) {
                    // Falta completa = jornada diária
                    valorDesconto = valorHora * jornadaDiaria;
                    console.log('[HORADESCONTA] Calculado como falta completa (dia inteiro):', valorDesconto, '(jornada:', jornadaDiaria, 'h, valor/hora:', valorHora, ')');
                } else if (tempoAtraso > 0) {
                    // Se tem tempo de atraso informado e não está marcado como dia inteiro, calcula proporcional
                    const valorMinuto = valorHora / 60;
                    valorDesconto = valorMinuto * tempoAtraso;
                    console.log('[HORADESCONTA] Calculado proporcional aos minutos (falta com tempo):', valorDesconto, '(minutos:', tempoAtraso, ', valor/minuto:', valorMinuto, ')');
                } else {
                    // Se não tem tempo informado e não está marcado como dia inteiro, mostra 0
                    valorDesconto = 0;
                    console.log('[HORADESCONTA] Sem tempo informado e não é dia inteiro, valor:', valorDesconto);
                }
            } else if (['atraso_entrada', 'atraso_almoco', 'atraso_cafe', 'saida_antecipada'].includes(codigoTipo)) {
                if (consideraDiaInteiro) {
                    // Considera como falta do dia inteiro
                    valorDesconto = valorHora * jornadaDiaria;
                    console.log('[HORADESCONTA] Calculado como dia inteiro:', valorDesconto, '(jornada:', jornadaDiaria, 'h, valor/hora:', valorHora, ')');
                } else if (tempoAtraso > 0) {
                    // Calcula proporcional aos minutos
                    const valorMinuto = valorHora / 60;
                    valorDesconto = valorMinuto * tempoAtraso;
                    console.log('[HORADESCONTA] Calculado proporcional aos minutos:', valorDesconto, '(minutos:', tempoAtraso, ', valor/minuto:', valorMinuto, ')');
                } else {
                    // Se não tem tempo informado, mostra 0
                    valorDesconto = 0;
                    console.log('[HORADESCONTA] Sem tempo informado, valor:', valorDesconto);
                }
            }
            
            const elementoValor = document.getElementById('valor_desconto_ocorrencia');
            const tipoDescontoAtual = document.querySelector('input[name="tipo_desconto"]:checked')?.value;
            
            console.log('[HORADESCONTA] Elemento valor_desconto_ocorrencia encontrado:', elementoValor);
            console.log('[HORADESCONTA] Container info_desconto_dinheiro encontrado:', containerInfo);
            console.log('[HORADESCONTA] Tipo de desconto selecionado:', tipoDescontoAtual);
            console.log('[HORADESCONTA] Valor calculado:', valorDesconto);
            
            if (elementoValor) {
                const valorFormatado = valorDesconto > 0 
                    ? 'R$ ' + valorDesconto.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                    : 'R$ 0,00';
                
                elementoValor.textContent = valorFormatado;
                console.log('[HORADESCONTA] Valor atualizado para:', valorFormatado);
                console.log('[HORADESCONTA] Elemento após atualização:', elementoValor.textContent);
                
                // Garante que o container está visível se o tipo de desconto for dinheiro ou se não houver seleção
                const apenasInformativaAtual = document.getElementById('apenas_informativa')?.checked;
                if (containerInfo && (tipoDescontoAtual === 'dinheiro' || !tipoDescontoAtual) && !apenasInformativaAtual) {
                    containerInfo.style.display = 'block';
                    console.log('[HORADESCONTA] Container info_desconto_dinheiro exibido');
                } else if (containerInfo && apenasInformativaAtual) {
                    containerInfo.style.display = 'none';
                    console.log('[HORADESCONTA] Container info_desconto_dinheiro oculto (apenas informativa)');
                }
            } else {
                console.error('[HORADESCONTA] Elemento valor_desconto_ocorrencia não encontrado!');
            }
        })
        .catch(error => {
            console.error('[HORADESCONTA] Erro ao calcular desconto:', error);
            document.getElementById('valor_desconto_ocorrencia').textContent = 'Erro ao calcular';
        });
}

// Event listeners para radio buttons de tipo de desconto
document.getElementById('tipo_desconto_dinheiro')?.addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('desconta_banco_horas').value = '0';
        document.getElementById('info_desconto_banco').style.display = 'none';
        document.getElementById('info_desconto_dinheiro').style.display = 'block';
        atualizarValorDescontoDinheiro();
    }
});

document.getElementById('tipo_desconto_banco_horas')?.addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('desconta_banco_horas').value = '1';
        document.getElementById('info_desconto_banco').style.display = 'block';
        document.getElementById('info_desconto_dinheiro').style.display = 'none';
        atualizarInfoDescontoBanco();
    }
});

// Atualiza quando colaborador muda (se for um select - na edição é hidden, então não precisa de listener)
const colaboradorSelect = document.getElementById('colaborador_id');
if (colaboradorSelect && colaboradorSelect.tagName === 'SELECT') {
    colaboradorSelect.addEventListener('change', function() {
        atualizarInfoDescontoBanco();
        atualizarValorDescontoDinheiro();
    });
}

// Atualiza quando considera dia inteiro muda
document.getElementById('considera_dia_inteiro')?.addEventListener('change', function() {
    const campoTempo = document.getElementById('campo_tempo_atraso');
    const tempoInput = document.getElementById('tempo_atraso_minutos');
    const tipoOcorrencia = document.getElementById('tipo_ocorrencia_id');
    const option = tipoOcorrencia?.options[tipoOcorrencia.selectedIndex];
    const permiteTempo = option?.getAttribute('data-permite-tempo') === '1';
    
    if (this.checked) {
        // Se marcou dia inteiro, esconde campo de minutos
        if (campoTempo) {
            campoTempo.style.display = 'none';
        }
        if (tempoInput) {
            tempoInput.required = false;
            tempoInput.disabled = true;
            tempoInput.value = '';
        }
        // Esconde conversão de tempo
        const tempoConvertidoContainer = document.getElementById('tempo_convertido');
        if (tempoConvertidoContainer) {
            tempoConvertidoContainer.style.display = 'none';
        }
        // Atualiza valores imediatamente (considera como dia inteiro)
        setTimeout(function() {
            atualizarInfoDescontoBanco();
            atualizarValorDescontoDinheiro();
        }, 100);
    } else {
        // Se desmarcou, mostra e habilita campo de minutos
        if (permiteTempo && campoTempo) {
            campoTempo.style.display = 'block';
        }
        if (tempoInput) {
            tempoInput.disabled = false;
            if (permiteTempo) {
                tempoInput.required = true;
            }
            // Re-adiciona listener quando campo é habilitado
            setTimeout(function() {
                adicionarListenerTempoAtraso();
                atualizarConversaoTempo();
            }, 50);
        }
        // Atualiza valores imediatamente
        setTimeout(function() {
            atualizarConversaoTempo();
            atualizarInfoDescontoBanco();
            atualizarValorDescontoDinheiro();
        }, 100);
    }
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
    
    // Valida tempo de atraso se obrigatório (mas não se considerar dia inteiro estiver marcado)
    if (permiteTempo) {
        const consideraDiaInteiro = document.getElementById('considera_dia_inteiro')?.checked;
        if (!consideraDiaInteiro) {
            const tempoAtraso = document.getElementById('tempo_atraso_minutos').value;
            if (!tempoAtraso || tempoAtraso <= 0) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: 'Informe o tempo de atraso em minutos ou marque "Considerar como falta do dia inteiro"!',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'Ok, entendi!',
                        customClass: {
                            confirmButton: 'btn fw-bold btn-primary'
                        }
                    });
                } else {
                    alert('Informe o tempo de atraso em minutos ou marque "Considerar como falta do dia inteiro"!');
                }
                return false;
            }
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
