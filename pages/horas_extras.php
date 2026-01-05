<?php
/**
 * CRUD de Horas Extras - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';

require_page_permission('horas_extras.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
        $data_trabalho = $_POST['data_trabalho'] ?? date('Y-m-d');
        $quantidade_horas = str_replace(',', '.', $_POST['quantidade_horas'] ?? '0');
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        $tipo_pagamento = $_POST['tipo_pagamento'] ?? 'dinheiro';
        
        if (empty($colaborador_id) || empty($quantidade_horas) || $quantidade_horas <= 0) {
            redirect('horas_extras.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            require_once __DIR__ . '/../includes/banco_horas_functions.php';
            
            if ($tipo_pagamento === 'banco_horas') {
                // Adiciona ao banco de horas
                $motivo = sprintf(
                    'Hora extra trabalhada em %s',
                    date('d/m/Y', strtotime($data_trabalho))
                );
                
                $resultado = adicionar_horas_banco(
                    $colaborador_id,
                    $quantidade_horas,
                    'hora_extra',
                    null, // Será atualizado após inserir hora_extra
                    $motivo,
                    $observacoes,
                    $usuario['id'],
                    $data_trabalho
                );
                
                if (!$resultado['success']) {
                    redirect('horas_extras.php', 'Erro ao adicionar ao banco de horas: ' . $resultado['error'], 'error');
                }
                
                // Insere hora extra com tipo banco_horas
                // Se falhar, precisamos reverter a movimentação do banco
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO horas_extras (
                            colaborador_id, data_trabalho, quantidade_horas, 
                            valor_hora, percentual_adicional, valor_total, 
                            observacoes, usuario_id, tipo_pagamento, banco_horas_movimentacao_id
                        ) VALUES (?, ?, ?, 0, 0, 0, ?, ?, 'banco_horas', ?)
                    ");
                    $stmt->execute([
                        $colaborador_id, 
                        $data_trabalho, 
                        $quantidade_horas,
                        $observacoes, 
                        $usuario['id'],
                        $resultado['movimentacao_id']
                    ]);
                    
                    $hora_extra_id = $pdo->lastInsertId();
                    
                    // Atualiza a movimentação do banco com o ID da hora extra
                    $stmt_update = $pdo->prepare("
                        UPDATE banco_horas_movimentacoes 
                        SET origem_id = ? 
                        WHERE id = ?
                    ");
                    $stmt_update->execute([$hora_extra_id, $resultado['movimentacao_id']]);
                    
                    // Envia email de notificação se template estiver ativo
                    require_once __DIR__ . '/../includes/email_templates.php';
                    enviar_email_horas_extras($hora_extra_id);
                    
                    redirect('horas_extras.php', 'Hora extra adicionada ao banco de horas com sucesso!');
                    
                } catch (Exception $e) {
                    // Se falhar ao inserir hora_extra, reverte a movimentação do banco
                    require_once __DIR__ . '/../includes/banco_horas_functions.php';
                    remover_horas_banco(
                        $colaborador_id,
                        $quantidade_horas,
                        'ajuste_manual',
                        null,
                        'Reversão: Erro ao criar registro de hora extra',
                        'Erro: ' . $e->getMessage(),
                        $usuario['id'],
                        date('Y-m-d')
                    );
                    
                    redirect('horas_extras.php', 'Erro ao salvar hora extra: ' . $e->getMessage() . ' (Movimentação do banco revertida)', 'error');
                }
                
            } else {
                // Comportamento atual (pagar em dinheiro)
                // Busca dados do colaborador e empresa
                $stmt = $pdo->prepare("
                    SELECT c.salario, c.empresa_id, e.percentual_hora_extra
                    FROM colaboradores c
                    LEFT JOIN empresas e ON c.empresa_id = e.id
                    WHERE c.id = ?
                ");
                $stmt->execute([$colaborador_id]);
                $colab_data = $stmt->fetch();
                
                if (!$colab_data || !$colab_data['salario']) {
                    redirect('horas_extras.php', 'Colaborador não encontrado ou sem salário cadastrado!', 'error');
                }
                
                // Calcula valor da hora normal (assumindo 220 horas/mês)
                $valor_hora = $colab_data['salario'] / 220;
                $percentual_adicional = $colab_data['percentual_hora_extra'] ?? 50.00;
                
                // Calcula valor total da hora extra
                $valor_hora_extra = $valor_hora * (1 + ($percentual_adicional / 100));
                $valor_total = $valor_hora_extra * $quantidade_horas;
                
                // Insere hora extra com tipo dinheiro
                $stmt = $pdo->prepare("
                    INSERT INTO horas_extras (colaborador_id, data_trabalho, quantidade_horas, valor_hora, percentual_adicional, valor_total, observacoes, usuario_id, tipo_pagamento)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'dinheiro')
                ");
                $stmt->execute([
                    $colaborador_id, $data_trabalho, $quantidade_horas, $valor_hora, 
                    $percentual_adicional, $valor_total, $observacoes, $usuario['id']
                ]);
                
                $hora_extra_id = $pdo->lastInsertId();
                
                // Envia email de notificação se template estiver ativo
                require_once __DIR__ . '/../includes/email_templates.php';
                enviar_email_horas_extras($hora_extra_id);
                
                redirect('horas_extras.php', 'Hora extra cadastrada com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('horas_extras.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'remover_horas') {
        $colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
        $quantidade_horas = str_replace(',', '.', $_POST['quantidade_horas'] ?? '0');
        $motivo = sanitize($_POST['motivo'] ?? '');
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        $data_remocao = $_POST['data_movimentacao'] ?? date('Y-m-d');
        
        if (empty($colaborador_id) || empty($quantidade_horas) || $quantidade_horas <= 0 || empty($motivo)) {
            redirect('horas_extras.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            require_once __DIR__ . '/../includes/banco_horas_functions.php';
            
            // Remove horas do banco (a função já gerencia a transação internamente)
            $resultado = remover_horas_banco(
                $colaborador_id,
                $quantidade_horas,
                'remocao_manual',
                null,
                $motivo,
                $observacoes,
                $usuario['id'],
                $data_remocao
            );
            
            if (!$resultado['success']) {
                redirect('horas_extras.php', 'Erro: ' . $resultado['error'], 'error');
            }
            
            // Busca dados do colaborador para criar registro na tabela horas_extras
            $stmt_colab = $pdo->prepare("SELECT salario, empresa_id FROM colaboradores WHERE id = ?");
            $stmt_colab->execute([$colaborador_id]);
            $colab_data = $stmt_colab->fetch();
            
            if ($colab_data) {
                // Busca percentual da empresa
                $stmt_empresa = $pdo->prepare("SELECT percentual_hora_extra FROM empresas WHERE id = ?");
                $stmt_empresa->execute([$colab_data['empresa_id']]);
                $empresa_data = $stmt_empresa->fetch();
                $percentual_adicional = $empresa_data['percentual_hora_extra'] ?? 50;
                
                // Calcula valores (mesmo que não sejam usados, mantém consistência)
                $salario = (float)$colab_data['salario'];
                $valor_hora = $salario / 220; // Base mensal padrão
                $valor_hora_extra = $valor_hora * (1 + ($percentual_adicional / 100));
                $valor_total = $valor_hora_extra * $quantidade_horas;
                
                // Insere registro na tabela horas_extras para aparecer na listagem
                // Usa quantidade negativa para indicar remoção
                $stmt_insert = $pdo->prepare("
                    INSERT INTO horas_extras (
                        colaborador_id, data_trabalho, quantidade_horas, 
                        valor_hora, percentual_adicional, valor_total, 
                        observacoes, usuario_id, tipo_pagamento
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'banco_horas')
                ");
                $observacoes_completas = !empty($observacoes) 
                    ? "Remoção: {$motivo}. {$observacoes}" 
                    : "Remoção: {$motivo}";
                
                $stmt_insert->execute([
                    $colaborador_id,
                    $data_remocao,
                    -abs($quantidade_horas), // Quantidade negativa para indicar remoção
                    $valor_hora,
                    $percentual_adicional,
                    -abs($valor_total), // Valor negativo
                    $observacoes_completas,
                    $usuario['id']
                ]);
            }
            
            redirect('horas_extras.php', 'Horas removidas do banco com sucesso!');
            
        } catch (Exception $e) {
            redirect('horas_extras.php', 'Erro ao remover horas: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            require_once __DIR__ . '/../includes/banco_horas_functions.php';
            
            // Busca dados da hora extra antes de deletar
            $stmt = $pdo->prepare("
                SELECT he.*, c.nome_completo 
                FROM horas_extras he
                INNER JOIN colaboradores c ON he.colaborador_id = c.id
                WHERE he.id = ?
            ");
            $stmt->execute([$id]);
            $hora_extra = $stmt->fetch();
            
            if (!$hora_extra) {
                redirect('horas_extras.php', 'Hora extra não encontrada!', 'error');
            }
            
            $pdo->beginTransaction();
            
            // Se a hora extra foi adicionada ao banco de horas, precisa reverter
            if ($hora_extra['tipo_pagamento'] === 'banco_horas' && !empty($hora_extra['banco_horas_movimentacao_id'])) {
                // Busca a movimentação
                $stmt_mov = $pdo->prepare("
                    SELECT * FROM banco_horas_movimentacoes WHERE id = ?
                ");
                $stmt_mov->execute([$hora_extra['banco_horas_movimentacao_id']]);
                $movimentacao = $stmt_mov->fetch();
                
                if ($movimentacao) {
                    $quantidade_horas = abs($movimentacao['quantidade_horas']);
                    
                    // Se foi crédito (adição de horas), remove as horas
                    if ($movimentacao['tipo'] === 'credito') {
                        // Remove as horas que foram adicionadas
                        $resultado = remover_horas_banco(
                            $hora_extra['colaborador_id'],
                            $quantidade_horas,
                            'estorno_hora_extra',
                            $id,
                            'Estorno de hora extra excluída - ' . $hora_extra['nome_completo'],
                            'Hora extra de ' . date('d/m/Y', strtotime($hora_extra['data_trabalho'])) . ' foi excluída',
                            $usuario['id'],
                            date('Y-m-d')
                        );
                        
                        if (!$resultado['success']) {
                            $pdo->rollBack();
                            redirect('horas_extras.php', 'Erro ao reverter banco de horas: ' . $resultado['error'], 'error');
                        }
                    }
                    // Se foi débito (remoção de horas), adiciona as horas de volta
                    elseif ($movimentacao['tipo'] === 'debito') {
                        // Adiciona as horas de volta
                        $resultado = adicionar_horas_banco(
                            $hora_extra['colaborador_id'],
                            $quantidade_horas,
                            'estorno_remocao',
                            $id,
                            'Estorno de remoção de horas excluída - ' . $hora_extra['nome_completo'],
                            'Remoção de horas de ' . date('d/m/Y', strtotime($hora_extra['data_trabalho'])) . ' foi excluída',
                            $usuario['id'],
                            date('Y-m-d')
                        );
                        
                        if (!$resultado['success']) {
                            $pdo->rollBack();
                            redirect('horas_extras.php', 'Erro ao reverter banco de horas: ' . $resultado['error'], 'error');
                        }
                    }
                    
                    // Deleta a movimentação original
                    $stmt_del_mov = $pdo->prepare("DELETE FROM banco_horas_movimentacoes WHERE id = ?");
                    $stmt_del_mov->execute([$hora_extra['banco_horas_movimentacao_id']]);
                }
            }
            
            // Deleta a hora extra
            $stmt = $pdo->prepare("DELETE FROM horas_extras WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            
            redirect('horas_extras.php', 'Hora extra excluída com sucesso e banco de horas ajustado!');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            redirect('horas_extras.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca horas extras
// Usa LEFT JOIN para não perder registros mesmo se colaborador foi deletado ou mudou de empresa
$where_conditions = [];
$params = [];

if ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where_conditions[] = "(c.empresa_id IN ($placeholders) OR c.empresa_id IS NULL)";
        $params = array_merge($params, $usuario['empresas_ids']);
    } else {
        // Fallback para compatibilidade
        $where_conditions[] = "(c.empresa_id = ? OR c.empresa_id IS NULL)";
        $params[] = $usuario['empresa_id'] ?? 0;
    }
} elseif ($usuario['role'] !== 'ADMIN') {
    $where_conditions[] = "(c.empresa_id = ? OR c.empresa_id IS NULL)";
    $params[] = $usuario['empresa_id'] ?? 0;
}

$where = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT h.*, 
           COALESCE(c.nome_completo, 'Colaborador Removido') as colaborador_nome, 
           c.empresa_id,
           e.nome_fantasia as empresa_nome, 
           u.nome as usuario_nome,
           COALESCE(h.tipo_pagamento, 'dinheiro') as tipo_pagamento
    FROM horas_extras h
    LEFT JOIN colaboradores c ON h.colaborador_id = c.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    $where
    ORDER BY h.data_trabalho DESC, h.created_at DESC
");
$stmt->execute($params);
$horas_extras = $stmt->fetchAll();

// Busca colaboradores para o select usando função padronizada
$colaboradores_raw = get_colaboradores_disponiveis($pdo, $usuario);

// Adiciona dados extras necessários (salário e empresa_id) para todos os colaboradores
$colaboradores = [];
foreach ($colaboradores_raw as $colab) {
    $stmt = $pdo->prepare("SELECT salario, empresa_id FROM colaboradores WHERE id = ?");
    $stmt->execute([$colab['id']]);
    $colab_data = $stmt->fetch();
    if ($colab_data) {
        $colaboradores[] = array_merge($colab, [
            'salario' => $colab_data['salario'] ?? null,
            'empresa_id' => $colab_data['empresa_id'] ?? null
        ]);
    } else {
        // Se não encontrou dados, adiciona mesmo assim (pode ser colaborador sem salário)
        $colaboradores[] = array_merge($colab, [
            'salario' => null,
            'empresa_id' => null
        ]);
    }
}

// Busca percentuais das empresas para cálculo
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, percentual_hora_extra FROM empresas");
    $empresas_percentual = $stmt->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("SELECT id, percentual_hora_extra FROM empresas WHERE id IN ($placeholders)");
        $stmt->execute($usuario['empresas_ids']);
        $empresas_percentual = $stmt->fetchAll();
    } else {
        // Fallback para compatibilidade
        $stmt = $pdo->prepare("SELECT id, percentual_hora_extra FROM empresas WHERE id = ?");
        $stmt->execute([$usuario['empresa_id'] ?? 0]);
        $empresas_percentual = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->prepare("SELECT id, percentual_hora_extra FROM empresas WHERE id = ?");
    $stmt->execute([$usuario['empresa_id'] ?? 0]);
    $empresas_percentual = $stmt->fetchAll();
}

$page_title = 'Horas Extras';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Horas Extras</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="colaboradores.php" class="text-muted text-hover-primary">Colaboradores</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Horas Extras</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <!--begin::Card title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" data-kt-horaextra-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar horas extras" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end gap-2 flex-wrap" data-kt-horaextra-table-toolbar="base">
                        <!--begin::Exportar-->
                        <div class="btn-group">
                            <button type="button" class="btn btn-light-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ki-duotone ki-exit-down fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Exportar
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="#" id="exportar_excel">
                                        <i class="ki-duotone ki-file-sheet fs-2 me-2 text-success">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Exportar para Excel
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="exportar_csv">
                                        <i class="ki-duotone ki-file fs-2 me-2 text-info">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Exportar para CSV
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="exportar_pdf">
                                        <i class="ki-duotone ki-file-down fs-2 me-2 text-danger">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Exportar para PDF
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <!--end::Exportar-->
                        <!--begin::Filtros Avançados-->
                        <button type="button" class="btn btn-light-primary" id="kt_filtros_avancados_toggle">
                            <i class="ki-duotone ki-filter fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Filtros Avançados
                            <span class="badge badge-circle badge-primary ms-2" id="filtros_ativos_count" style="display: none;">0</span>
                        </button>
                        <!--end::Filtros Avançados-->
                        <!--begin::Remover horas do banco-->
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#kt_modal_remover_horas">
                            <i class="ki-duotone ki-minus fs-2"></i>
                            Remover Horas do Banco
                        </button>
                        <!--end::Remover horas do banco-->
                        <!--begin::Add hora extra-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_horaextra">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Nova Hora Extra
                        </button>
                        <!--end::Add hora extra-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Filtros Avançados-->
            <div class="card-body border-top d-none" id="kt_filtros_avancados_content">
                <div class="row g-5">
                    <!--begin::Colaborador-->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Colaborador</label>
                        <select class="form-select form-select-solid" id="filtro_colaborador" data-placeholder="Todos">
                            <option value="">Todos os colaboradores</option>
                            <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= $colab['id'] ?>"><?= htmlspecialchars($colab['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!--end::Colaborador-->
                    
                    <!--begin::Período-->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Data Início</label>
                        <input type="date" class="form-control form-control-solid" id="filtro_data_inicio" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Data Fim</label>
                        <input type="date" class="form-control form-control-solid" id="filtro_data_fim" />
                    </div>
                    <!--end::Período-->
                    
                    <!--begin::Tipo de Pagamento-->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tipo de Pagamento</label>
                        <select class="form-select form-select-solid" id="filtro_tipo_pagamento">
                            <option value="">Todos os tipos</option>
                            <option value="dinheiro">💰 R$ (Dinheiro)</option>
                            <option value="banco_horas">🏦 Banco de Horas</option>
                            <option value="remocao">⚠️ Remoção</option>
                        </select>
                    </div>
                    <!--end::Tipo de Pagamento-->
                    
                    <!--begin::Quantidade Horas-->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Horas Mínimas</label>
                        <input type="number" class="form-control form-control-solid" id="filtro_horas_min" placeholder="0" step="0.5" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Horas Máximas</label>
                        <input type="number" class="form-control form-control-solid" id="filtro_horas_max" placeholder="999" step="0.5" />
                    </div>
                    <!--end::Quantidade Horas-->
                    
                    <!--begin::Valor Total-->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Valor Mínimo (R$)</label>
                        <input type="number" class="form-control form-control-solid" id="filtro_valor_min" placeholder="0,00" step="0.01" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Valor Máximo (R$)</label>
                        <input type="number" class="form-control form-control-solid" id="filtro_valor_max" placeholder="999999,99" step="0.01" />
                    </div>
                    <!--end::Valor Total-->
                    
                    <!--begin::Ações-->
                    <div class="col-md-12">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" id="limpar_filtros">
                                <i class="ki-duotone ki-arrows-circle fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Limpar Filtros
                            </button>
                            <button type="button" class="btn btn-primary" id="aplicar_filtros">
                                <i class="ki-duotone ki-check fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Aplicar Filtros
                            </button>
                        </div>
                    </div>
                    <!--end::Ações-->
                </div>
                
                <!--begin::Resumo dos Filtros Ativos-->
                <div class="mt-5 d-none" id="filtros_ativos_resumo">
                    <div class="alert alert-primary d-flex align-items-center p-5">
                        <i class="ki-duotone ki-information-5 fs-2hx text-primary me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h5 class="mb-1">Filtros Ativos</h5>
                            <span id="filtros_ativos_texto"></span>
                        </div>
                    </div>
                </div>
                <!--end::Resumo dos Filtros Ativos-->
            </div>
            <!--end::Filtros Avançados-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Estatísticas-->
                <div class="row g-5 mb-7" id="estatisticas_horas_extras">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card bg-light-success h-100">
                            <div class="card-body">
                                <i class="ki-duotone ki-plus-circle fs-2x text-success mb-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="fw-bold text-success fs-2" id="stat_total_horas">0h</div>
                                <div class="fw-semibold text-gray-600 fs-7">Total de Horas</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card bg-light-primary h-100">
                            <div class="card-body">
                                <i class="ki-duotone ki-dollar fs-2x text-primary mb-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="fw-bold text-primary fs-2" id="stat_total_valor">R$ 0,00</div>
                                <div class="fw-semibold text-gray-600 fs-7">Total em R$</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card bg-light-info h-100">
                            <div class="card-body">
                                <i class="ki-duotone ki-time fs-2x text-info mb-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="fw-bold text-info fs-2" id="stat_banco_horas">0</div>
                                <div class="fw-semibold text-gray-600 fs-7">Banco de Horas</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card bg-light-warning h-100">
                            <div class="card-body">
                                <i class="ki-duotone ki-abstract-26 fs-2x text-warning mb-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="fw-bold text-warning fs-2" id="stat_total_registros">0</div>
                                <div class="fw-semibold text-gray-600 fs-7">Total de Registros</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Estatísticas-->
                
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_horas_extras_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-200px">Colaborador</th>
                            <th class="min-w-150px">Empresa</th>
                            <th class="min-w-100px">Data</th>
                            <th class="min-w-100px">Quantidade</th>
                            <th class="min-w-100px">Tipo</th>
                            <th class="min-w-120px">Valor Hora</th>
                            <th class="min-w-100px">% Adicional</th>
                            <th class="min-w-120px">Valor Total</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($horas_extras as $he): 
                            $is_remocao = ($he['quantidade_horas'] < 0);
                            $tipo_pagamento = $he['tipo_pagamento'] ?? 'dinheiro';
                        ?>
                        <tr>
                            <td><?= $he['id'] ?></td>
                            <td>
                                <a href="colaborador_view.php?id=<?= $he['colaborador_id'] ?>" class="text-gray-800 text-hover-primary mb-1">
                                    <?= htmlspecialchars($he['colaborador_nome']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($he['empresa_nome'] ?? '-') ?></td>
                            <td><?= date('d/m/Y', strtotime($he['data_trabalho'])) ?></td>
                            <td>
                                <?php if ($is_remocao): ?>
                                    <span class="text-gray-600">-<?= number_format(abs($he['quantidade_horas']), 2, ',', '.') ?>h</span>
                                <?php else: ?>
                                    <?= number_format($he['quantidade_horas'], 2, ',', '.') ?>h
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_remocao): ?>
                                    <span class="badge badge-light-warning">Remoção Banco</span>
                                <?php elseif ($tipo_pagamento === 'banco_horas'): ?>
                                    <span class="badge badge-info">Banco de Horas</span>
                                <?php else: ?>
                                    <span class="badge badge-success">R$</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tipo_pagamento === 'banco_horas'): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    R$ <?= number_format($he['valor_hora'], 2, ',', '.') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tipo_pagamento === 'banco_horas'): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <?= number_format($he['percentual_adicional'], 2, ',', '.') ?>%
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_remocao): ?>
                                    <span class="text-gray-600">-</span>
                                <?php elseif ($tipo_pagamento === 'banco_horas'): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <span class="text-success fw-bold">R$ <?= number_format($he['valor_total'], 2, ',', '.') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-light-danger" onclick="deletarHoraExtra(<?= $he['id'] ?>)">
                                    <i class="ki-duotone ki-trash fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Hora Extra-->
<div class="modal fade" id="kt_modal_horaextra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_horaextra_header">
                <h2 class="fw-bold">Nova Hora Extra</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_horaextra_form" method="POST" class="form">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                            <?= render_select_colaborador('colaborador_id', 'colaborador_id', null, $colaboradores, true) ?>
                            <?php if (empty($colaboradores)): ?>
                            <div class="alert alert-warning mt-2">
                                <i class="ki-duotone ki-information-5 fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <strong>Atenção:</strong> Nenhum colaborador disponível encontrado.
                            </div>
                            <?php else: ?>
                            <small class="text-muted">
                                <?= count($colaboradores) ?> colaborador(es) disponível(is)
                                <br>
                                <span class="text-warning">Nota: Para pagamento em dinheiro, o colaborador precisa ter salário cadastrado.</span>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Data do Trabalho</label>
                            <input type="date" name="data_trabalho" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" required />
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Quantidade de Horas</label>
                            <input type="text" name="quantidade_horas" id="quantidade_horas" class="form-control form-control-solid" placeholder="0,00" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Tipo de Pagamento</label>
                            <div class="form-check form-check-custom form-check-solid mb-3">
                                <input class="form-check-input" type="radio" name="tipo_pagamento" 
                                       id="tipo_pagamento_dinheiro" value="dinheiro" checked />
                                <label class="form-check-label" for="tipo_pagamento_dinheiro">
                                    Pagar em R$ (dinheiro)
                                </label>
                            </div>
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="tipo_pagamento" 
                                       id="tipo_pagamento_banco" value="banco_horas" />
                                <label class="form-check-label" for="tipo_pagamento_banco">
                                    Adicionar ao Banco de Horas
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mostrar saldo atual quando selecionar banco de horas -->
                    <div class="row mb-7" id="info_saldo_banco" style="display: none;">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="ki-duotone ki-information-5 fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <strong>Saldo atual:</strong> <span id="saldo_atual_colaborador">-</span> horas
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-7" id="card_calculo_dinheiro">
                        <div class="col-md-12">
                            <div class="card card-flush bg-light-primary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-calculator fs-2hx text-primary me-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-gray-600 fw-semibold">Valor Total Calculado:</span>
                                                <span class="text-primary fw-bold fs-2" id="valor_total_calculado">R$ 0,00</span>
                                            </div>
                                            <div class="text-gray-500 fs-7" id="detalhes_calculo">
                                                Selecione um colaborador e informe a quantidade de horas
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Observações</label>
                            <textarea name="observacoes" class="form-control form-control-solid" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success me-2" id="btn_salvar_e_adicionar">
                            <span class="indicator-label">
                                <i class="ki-duotone ki-add-files fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Salvar e Adicionar Outra
                            </span>
                            <span class="indicator-progress">
                                Aguarde... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn_salvar">
                            <span class="indicator-label">Salvar</span>
                            <span class="indicator-progress">
                                Aguarde... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Remover Horas do Banco-->
<div class="modal fade" id="kt_modal_remover_horas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_remover_horas_header">
                <h2 class="fw-bold">Remover Horas do Banco</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_remover_horas_form" method="POST" class="form">
                    <input type="hidden" name="action" value="remover_horas">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Colaborador</label>
                            <?= render_select_colaborador('colaborador_id', 'colaborador_id_remover', null, $colaboradores, true) ?>
                            <div id="saldo_atual_remover" class="mt-3" style="display: none;">
                                <div class="alert alert-info">
                                    <strong>Saldo atual:</strong> <span id="saldo_valor_remover">-</span> horas
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Quantidade de Horas a Remover</label>
                            <input type="text" name="quantidade_horas" id="quantidade_horas_remover" class="form-control form-control-solid" placeholder="0,00" required />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Data da Movimentação</label>
                            <input type="date" name="data_movimentacao" class="form-control form-control-solid" value="<?= date('Y-m-d') ?>" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Motivo</label>
                            <textarea name="motivo" class="form-control form-control-solid" rows="3" required placeholder="Informe o motivo da remoção de horas..."></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Observações</label>
                            <textarea name="observacoes" class="form-control form-control-solid" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success me-2" id="btn_remover_e_adicionar">
                            <span class="indicator-label">
                                <i class="ki-duotone ki-add-files fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Remover e Adicionar Outra
                            </span>
                            <span class="indicator-progress">
                                Aguarde... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <button type="submit" class="btn btn-warning" id="btn_remover">
                            <span class="indicator-label">Remover Horas</span>
                            <span class="indicator-progress">
                                Aguarde... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
// Dados dos colaboradores e empresas para cálculo
const colaboradoresData = {
    <?php foreach ($colaboradores as $colab): ?>
    <?= $colab['id'] ?>: {
        salario: <?= $colab['salario'] ?? 0 ?>,
        empresa_id: <?= $colab['empresa_id'] ?>
    },
    <?php endforeach; ?>
};

const empresasPercentual = {
    <?php foreach ($empresas_percentual as $emp): ?>
    <?= $emp['id'] ?>: <?= $emp['percentual_hora_extra'] ?? 50.00 ?>,
    <?php endforeach; ?>
};

// Função para calcular valor total
function calcularValorTotal() {
    const tipoPagamento = document.querySelector('input[name="tipo_pagamento"]:checked')?.value;
    
    // Se for banco de horas, não calcula valor monetário
    if (tipoPagamento === 'banco_horas') {
        return;
    }
    
    const colaboradorId = document.getElementById('colaborador_id')?.value;
    const quantidadeHoras = parseFloat(document.getElementById('quantidade_horas')?.value.replace(',', '.') || 0);
    
    const valorTotalEl = document.getElementById('valor_total_calculado');
    const detalhesEl = document.getElementById('detalhes_calculo');
    
    if (!colaboradorId || !colaboradoresData[colaboradorId]) {
        valorTotalEl.textContent = 'R$ 0,00';
        detalhesEl.textContent = 'Selecione um colaborador e informe a quantidade de horas';
        return;
    }
    
    const colabData = colaboradoresData[colaboradorId];
    const salario = colabData.salario;
    const percentual = empresasPercentual[colabData.empresa_id] || 50.00;
    
    if (!salario || salario <= 0) {
        valorTotalEl.textContent = 'R$ 0,00';
        detalhesEl.textContent = 'Colaborador sem salário cadastrado';
        return;
    }
    
    if (quantidadeHoras <= 0) {
        valorTotalEl.textContent = 'R$ 0,00';
        detalhesEl.textContent = 'Informe a quantidade de horas';
        return;
    }
    
    // Calcula valor da hora normal (220 horas/mês)
    const valorHora = salario / 220;
    
    // Calcula valor da hora extra com percentual adicional
    const valorHoraExtra = valorHora * (1 + (percentual / 100));
    
    // Calcula valor total
    const valorTotal = valorHoraExtra * quantidadeHoras;
    
    // Atualiza exibição
    valorTotalEl.textContent = 'R$ ' + valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Converte horas para horas:minutos
    const horasInteiras = Math.floor(quantidadeHoras);
    const minutos = Math.round((quantidadeHoras - horasInteiras) * 60);
    let horasMinutosTexto = '';
    if (horasInteiras > 0 && minutos > 0) {
        horasMinutosTexto = ` (${horasInteiras}h ${minutos}min)`;
    } else if (horasInteiras > 0 && minutos === 0) {
        horasMinutosTexto = ` (${horasInteiras}h)`;
    } else if (horasInteiras === 0 && minutos > 0) {
        horasMinutosTexto = ` (${minutos}min)`;
    }
    
    // Atualiza detalhes
    detalhesEl.innerHTML = `
        <div class="d-flex flex-column gap-1">
            <span>Salário: R$ ${salario.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
            <span>Valor Hora Normal: R$ ${valorHora.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
            <span>Percentual Adicional: ${percentual.toLocaleString('pt-BR', {minimumFractionDigits: 2})}%</span>
            <span>Valor Hora Extra: R$ ${valorHoraExtra.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
            <span class="fw-bold mt-1">${quantidadeHoras.toLocaleString('pt-BR', {minimumFractionDigits: 2})}h${horasMinutosTexto} × R$ ${valorHoraExtra.toLocaleString('pt-BR', {minimumFractionDigits: 2})} = R$ ${valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
        </div>
    `;
}

// Event listeners
document.getElementById('colaborador_id')?.addEventListener('change', function() {
    calcularValorTotal();
    atualizarSaldoBanco();
});
document.getElementById('quantidade_horas')?.addEventListener('input', calcularValorTotal);
document.getElementById('quantidade_horas')?.addEventListener('keyup', calcularValorTotal);

// Event listeners para tipo de pagamento
document.getElementById('tipo_pagamento_dinheiro')?.addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('card_calculo_dinheiro').style.display = 'block';
        document.getElementById('info_saldo_banco').style.display = 'none';
    }
});
document.getElementById('tipo_pagamento_banco')?.addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('card_calculo_dinheiro').style.display = 'none';
        document.getElementById('info_saldo_banco').style.display = 'block';
        atualizarSaldoBanco();
    }
});

// Função para atualizar saldo do banco de horas
function atualizarSaldoBanco() {
    const colaboradorId = document.getElementById('colaborador_id')?.value;
    if (!colaboradorId) {
        document.getElementById('saldo_atual_colaborador').textContent = '-';
        return;
    }
    
    fetch(`../api/banco_horas/saldo.php?colaborador_id=${colaboradorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const saldo = parseFloat(data.data.saldo_total_horas || 0);
                document.getElementById('saldo_atual_colaborador').textContent = 
                    saldo.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                document.getElementById('saldo_atual_colaborador').textContent = '0,00';
            }
        })
        .catch(error => {
            console.error('Erro ao buscar saldo:', error);
            document.getElementById('saldo_atual_colaborador').textContent = '-';
        });
}

// Atualizar saldo no modal de remover horas
document.getElementById('colaborador_id_remover')?.addEventListener('change', function() {
    const colaboradorId = this.value;
    const saldoDiv = document.getElementById('saldo_atual_remover');
    const saldoValor = document.getElementById('saldo_valor_remover');
    
    if (!colaboradorId) {
        saldoDiv.style.display = 'none';
        return;
    }
    
    saldoDiv.style.display = 'block';
    saldoValor.textContent = 'Carregando...';
    
    fetch(`../api/banco_horas/saldo.php?colaborador_id=${colaboradorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const saldo = parseFloat(data.data.saldo_total_horas || 0);
                saldoValor.textContent = saldo.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                saldoValor.textContent = '0,00';
            }
        })
        .catch(error => {
            console.error('Erro ao buscar saldo:', error);
            saldoValor.textContent = '-';
        });
});

// Função para validar entrada - aceita apenas números e vírgula
function validarEntradaHoras(input) {
    let valor = input.value;
    // Remove todos os caracteres que não são números ou vírgula (inclui +, -, espaços, etc)
    valor = valor.replace(/[^0-9,]/g, '');
    // Garante que há apenas uma vírgula
    const partes = valor.split(',');
    if (partes.length > 2) {
        valor = partes[0] + ',' + partes.slice(1).join('');
    }
    // Atualiza o valor apenas se mudou (evita loop infinito)
    if (input.value !== valor) {
        input.value = valor;
    }
}

// Função para validar tecla pressionada
function validarTeclaHoras(e) {
    // Permite teclas de controle (backspace, delete, tab, setas, etc)
    if (e.keyCode === 8 || e.keyCode === 9 || e.keyCode === 37 || e.keyCode === 39 || 
        e.keyCode === 46 || e.keyCode === 35 || e.keyCode === 36 || 
        (e.keyCode >= 35 && e.keyCode <= 40) || (e.ctrlKey && (e.keyCode === 65 || e.keyCode === 67 || e.keyCode === 86 || e.keyCode === 88))) {
        return true;
    }
    // Permite apenas números (0-9) e vírgula
    const char = String.fromCharCode(e.which || e.keyCode);
    if (!/[0-9,]/.test(char)) {
        e.preventDefault();
        return false;
    }
    // Evita múltiplas vírgulas
    if (char === ',' && e.target.value.includes(',')) {
        e.preventDefault();
        return false;
    }
    return true;
}

// Aplica validação no campo de quantidade de horas (modal adicionar)
const quantidadeHorasInput = document.getElementById('quantidade_horas');
if (quantidadeHorasInput) {
    quantidadeHorasInput.addEventListener('input', function(e) {
        validarEntradaHoras(this);
        calcularValorTotal();
    });
    quantidadeHorasInput.addEventListener('keypress', validarTeclaHoras);
    quantidadeHorasInput.addEventListener('paste', function(e) {
        setTimeout(() => {
            validarEntradaHoras(this);
            calcularValorTotal();
        }, 10);
    });
}

// Aplica validação no campo de quantidade de horas (modal remover)
const quantidadeHorasRemoverInput = document.getElementById('quantidade_horas_remover');
if (quantidadeHorasRemoverInput) {
    quantidadeHorasRemoverInput.addEventListener('input', function(e) {
        validarEntradaHoras(this);
    });
    quantidadeHorasRemoverInput.addEventListener('keypress', validarTeclaHoras);
    quantidadeHorasRemoverInput.addEventListener('paste', function(e) {
        setTimeout(() => {
            validarEntradaHoras(this);
        }, 10);
    });
}

// Máscara para quantidade de horas
if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
    jQuery('#quantidade_horas').mask('#0,00', {reverse: true});
    jQuery('#quantidade_horas_remover').mask('#0,00', {reverse: true});
    
    // Recalcula quando a máscara é aplicada
    jQuery('#quantidade_horas').on('input', function() {
        calcularValorTotal();
    });
}

// DataTables com Filtros Avançados
var KTHorasExtrasList = function() {
    var table;
    var datatable;
    
    var initDatatable = function() {
        table = document.getElementById('kt_horas_extras_table');
        if (!table) return;
        
        datatable = $(table).DataTable({
            "info": true,
            "order": [[3, 'desc']], // Ordena por data (coluna 3) decrescente
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            },
            "columnDefs": [
                { "orderable": false, "targets": 9 } // Desabilita ordenação na coluna de ações
            ]
        });
        
        // Filtro de busca simples
        const filterSearch = document.querySelector('[data-kt-horaextra-table-filter="search"]');
        if (filterSearch) {
            filterSearch.addEventListener('keyup', function(e) {
                datatable.search(e.target.value).draw();
            });
        }
        
        return datatable;
    };
    
    var initFiltrosAvancados = function() {
        // Toggle do painel de filtros
        const toggleBtn = document.getElementById('kt_filtros_avancados_toggle');
        const contentDiv = document.getElementById('kt_filtros_avancados_content');
        
        if (toggleBtn && contentDiv) {
            toggleBtn.addEventListener('click', function() {
                contentDiv.classList.toggle('d-none');
                
                // Anima o ícone
                const icon = this.querySelector('i');
                if (contentDiv.classList.contains('d-none')) {
                    icon.classList.remove('rotate-180');
                } else {
                    icon.classList.add('rotate-180');
                }
            });
        }
        
        // Função para aplicar filtros
        const aplicarFiltros = function() {
            if (!datatable) return;
            
            // Remove filtros anteriores
            $.fn.dataTable.ext.search = [];
            
            // Pega valores dos filtros
            const colaboradorId = document.getElementById('filtro_colaborador')?.value;
            const dataInicio = document.getElementById('filtro_data_inicio')?.value;
            const dataFim = document.getElementById('filtro_data_fim')?.value;
            const tipoPagamento = document.getElementById('filtro_tipo_pagamento')?.value;
            const horasMin = parseFloat(document.getElementById('filtro_horas_min')?.value) || null;
            const horasMax = parseFloat(document.getElementById('filtro_horas_max')?.value) || null;
            const valorMin = parseFloat(document.getElementById('filtro_valor_min')?.value) || null;
            const valorMax = parseFloat(document.getElementById('filtro_valor_max')?.value) || null;
            
            let filtrosAtivos = 0;
            let textoFiltros = [];
            
            // Adiciona filtro customizado
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                // data[1] = colaborador nome
                // data[3] = data (formato dd/mm/yyyy)
                // data[4] = quantidade horas
                // data[5] = tipo (badge HTML)
                // data[8] = valor total (formato HTML)
                
                // Filtro por colaborador
                if (colaboradorId) {
                    const row = datatable.row(dataIndex).node();
                    const linkColaborador = row.querySelector('td:nth-child(2) a');
                    if (linkColaborador) {
                        const href = linkColaborador.getAttribute('href');
                        const match = href.match(/id=(\d+)/);
                        if (!match || match[1] !== colaboradorId) {
                            return false;
                        }
                    }
                }
                
                // Filtro por data
                if (dataInicio || dataFim) {
                    const dataStr = data[3]; // dd/mm/yyyy
                    const partes = dataStr.split('/');
                    if (partes.length === 3) {
                        const dataRegistro = new Date(partes[2], partes[1] - 1, partes[0]);
                        
                        if (dataInicio) {
                            const dtInicio = new Date(dataInicio);
                            if (dataRegistro < dtInicio) return false;
                        }
                        
                        if (dataFim) {
                            const dtFim = new Date(dataFim);
                            dtFim.setHours(23, 59, 59); // Inclui todo o dia final
                            if (dataRegistro > dtFim) return false;
                        }
                    }
                }
                
                // Filtro por tipo de pagamento
                if (tipoPagamento) {
                    const tipoHtml = data[5].toLowerCase();
                    
                    if (tipoPagamento === 'remocao' && !tipoHtml.includes('remoção')) {
                        return false;
                    } else if (tipoPagamento === 'banco_horas' && !tipoHtml.includes('banco de horas')) {
                        return false;
                    } else if (tipoPagamento === 'dinheiro' && !tipoHtml.includes('r$')) {
                        return false;
                    }
                }
                
                // Filtro por quantidade de horas
                if (horasMin !== null || horasMax !== null) {
                    const horasStr = data[4].replace(/[^0-9,\-]/g, ''); // Remove tudo exceto números, vírgula e sinal negativo
                    const horas = Math.abs(parseFloat(horasStr.replace(',', '.'))); // Pega valor absoluto
                    
                    if (horasMin !== null && horas < horasMin) return false;
                    if (horasMax !== null && horas > horasMax) return false;
                }
                
                // Filtro por valor
                if (valorMin !== null || valorMax !== null) {
                    // Extrai valor do HTML (pode ser "R$ 123,45" ou "-" ou span com valor)
                    const valorHtml = data[8];
                    const valorMatch = valorHtml.match(/R\$\s*([\d.,]+)/);
                    
                    if (valorMatch) {
                        const valor = parseFloat(valorMatch[1].replace(/\./g, '').replace(',', '.'));
                        
                        if (valorMin !== null && valor < valorMin) return false;
                        if (valorMax !== null && valor > valorMax) return false;
                    } else {
                        // Se não tem valor (banco de horas ou remoção), só passa se não tiver filtro de valor
                        if (valorMin !== null || valorMax !== null) return false;
                    }
                }
                
                return true;
            });
            
            // Monta texto dos filtros ativos
            if (colaboradorId) {
                const nomeColaborador = document.getElementById('filtro_colaborador').selectedOptions[0]?.text;
                textoFiltros.push(`<strong>Colaborador:</strong> ${nomeColaborador}`);
                filtrosAtivos++;
            }
            
            if (dataInicio) {
                const dtInicioFormatada = new Date(dataInicio).toLocaleDateString('pt-BR');
                textoFiltros.push(`<strong>Data Início:</strong> ${dtInicioFormatada}`);
                filtrosAtivos++;
            }
            
            if (dataFim) {
                const dtFimFormatada = new Date(dataFim).toLocaleDateString('pt-BR');
                textoFiltros.push(`<strong>Data Fim:</strong> ${dtFimFormatada}`);
                filtrosAtivos++;
            }
            
            if (tipoPagamento) {
                const tipoTexto = document.getElementById('filtro_tipo_pagamento').selectedOptions[0]?.text;
                textoFiltros.push(`<strong>Tipo:</strong> ${tipoTexto}`);
                filtrosAtivos++;
            }
            
            if (horasMin !== null) {
                textoFiltros.push(`<strong>Horas Mínimas:</strong> ${horasMin}h`);
                filtrosAtivos++;
            }
            
            if (horasMax !== null) {
                textoFiltros.push(`<strong>Horas Máximas:</strong> ${horasMax}h`);
                filtrosAtivos++;
            }
            
            if (valorMin !== null) {
                textoFiltros.push(`<strong>Valor Mínimo:</strong> R$ ${valorMin.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`);
                filtrosAtivos++;
            }
            
            if (valorMax !== null) {
                textoFiltros.push(`<strong>Valor Máximo:</strong> R$ ${valorMax.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`);
                filtrosAtivos++;
            }
            
            // Atualiza contador de filtros
            const countBadge = document.getElementById('filtros_ativos_count');
            if (filtrosAtivos > 0) {
                countBadge.textContent = filtrosAtivos;
                countBadge.style.display = 'inline-block';
            } else {
                countBadge.style.display = 'none';
            }
            
            // Mostra resumo dos filtros
            const resumoDiv = document.getElementById('filtros_ativos_resumo');
            const textoDiv = document.getElementById('filtros_ativos_texto');
            
            if (filtrosAtivos > 0) {
                textoDiv.innerHTML = textoFiltros.join(' • ');
                resumoDiv.classList.remove('d-none');
            } else {
                resumoDiv.classList.add('d-none');
            }
            
            // Redesenha tabela
            datatable.draw();
            
            // Fecha painel de filtros após aplicar
            contentDiv.classList.add('d-none');
            
            // Mostra notificação
            if (filtrosAtivos > 0) {
                Swal.fire({
                    text: `${filtrosAtivos} filtro(s) aplicado(s) com sucesso!`,
                    icon: "success",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    },
                    timer: 2000
                });
            }
        };
        
        // Botão aplicar filtros
        const btnAplicar = document.getElementById('aplicar_filtros');
        if (btnAplicar) {
            btnAplicar.addEventListener('click', aplicarFiltros);
        }
        
        // Botão limpar filtros
        const btnLimpar = document.getElementById('limpar_filtros');
        if (btnLimpar) {
            btnLimpar.addEventListener('click', function() {
                // Limpa todos os campos
                document.getElementById('filtro_colaborador').value = '';
                document.getElementById('filtro_data_inicio').value = '';
                document.getElementById('filtro_data_fim').value = '';
                document.getElementById('filtro_tipo_pagamento').value = '';
                document.getElementById('filtro_horas_min').value = '';
                document.getElementById('filtro_horas_max').value = '';
                document.getElementById('filtro_valor_min').value = '';
                document.getElementById('filtro_valor_max').value = '';
                
                // Remove filtros do DataTable
                $.fn.dataTable.ext.search = [];
                
                // Esconde resumo
                document.getElementById('filtros_ativos_resumo').classList.add('d-none');
                document.getElementById('filtros_ativos_count').style.display = 'none';
                
                // Redesenha tabela
                datatable.draw();
                
                Swal.fire({
                    text: "Filtros removidos!",
                    icon: "info",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    },
                    timer: 1500
                });
            });
        }
        
        // Permite aplicar filtros com Enter nos campos
        const camposFiltro = [
            'filtro_data_inicio', 'filtro_data_fim', 
            'filtro_horas_min', 'filtro_horas_max',
            'filtro_valor_min', 'filtro_valor_max'
        ];
        
        camposFiltro.forEach(function(id) {
            const campo = document.getElementById(id);
            if (campo) {
                campo.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        aplicarFiltros();
                    }
                });
            }
        });
    };
    
    var atualizarEstatisticas = function() {
        if (!datatable) return;
        
        let totalHoras = 0;
        let totalValor = 0;
        let totalBancoHoras = 0;
        let totalRegistros = 0;
        
        // Percorre apenas as linhas visíveis (após filtros)
        datatable.rows({search: 'applied'}).every(function() {
            const data = this.data();
            totalRegistros++;
            
            // Extrai quantidade de horas (coluna 4)
            const horasStr = data[4].replace(/[^0-9,\-]/g, '');
            const horas = parseFloat(horasStr.replace(',', '.')) || 0;
            totalHoras += Math.abs(horas); // Soma valor absoluto
            
            // Verifica se é banco de horas (coluna 5)
            const tipoHtml = data[5].toLowerCase();
            if (tipoHtml.includes('banco de horas') || tipoHtml.includes('remoção')) {
                totalBancoHoras++;
            }
            
            // Extrai valor total (coluna 8) - apenas se for em R$
            const valorHtml = data[8];
            const valorMatch = valorHtml.match(/R\$\s*([\d.,]+)/);
            if (valorMatch) {
                const valor = parseFloat(valorMatch[1].replace(/\./g, '').replace(',', '.'));
                totalValor += valor;
            }
        });
        
        // Atualiza os cards de estatísticas
        document.getElementById('stat_total_horas').textContent = 
            totalHoras.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + 'h';
        
        document.getElementById('stat_total_valor').textContent = 
            'R$ ' + totalValor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        document.getElementById('stat_banco_horas').textContent = totalBancoHoras;
        
        document.getElementById('stat_total_registros').textContent = totalRegistros;
    };
    
    return {
        init: function() {
            initDatatable();
            initFiltrosAvancados();
            
            // Atualiza estatísticas inicialmente e sempre que a tabela for redesenhada
            if (datatable) {
                atualizarEstatisticas();
                datatable.on('draw', function() {
                    atualizarEstatisticas();
                });
            }
        },
        atualizarEstatisticas: atualizarEstatisticas
    };
}();

// Deletar hora extra
function deletarHoraExtra(id) {
    Swal.fire({
        text: "Tem certeza que deseja excluir esta hora extra?",
        icon: "warning",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, excluir!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-danger",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Inicializa quando jQuery e DataTables estiverem prontos
function waitForDependencies() {
    if (typeof jQuery !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        KTHorasExtrasList.init();
    } else {
        setTimeout(waitForDependencies, 100);
    }
}
waitForDependencies();

// ========================================
// FUNÇÕES DE EXPORTAÇÃO
// ========================================

// Função para obter dados filtrados
function obterDadosFiltrados() {
    const table = jQuery('#kt_horas_extras_table').DataTable();
    const dados = [];
    
    // Pega apenas as linhas visíveis (após filtros)
    table.rows({search: 'applied'}).every(function() {
        const row = this.node();
        const data = this.data();
        
        // Extrai dados limpos (sem HTML)
        const colaboradorNome = jQuery(row).find('td:nth-child(2)').text().trim();
        const empresaNome = jQuery(row).find('td:nth-child(3)').text().trim();
        const data_trabalho = jQuery(row).find('td:nth-child(4)').text().trim();
        const quantidade_horas = jQuery(row).find('td:nth-child(5)').text().trim();
        const tipo = jQuery(row).find('td:nth-child(6)').text().trim();
        const valor_hora = jQuery(row).find('td:nth-child(7)').text().trim();
        const percentual = jQuery(row).find('td:nth-child(8)').text().trim();
        const valor_total = jQuery(row).find('td:nth-child(9)').text().trim();
        
        dados.push({
            colaborador: colaboradorNome,
            empresa: empresaNome,
            data: data_trabalho,
            horas: quantidade_horas,
            tipo: tipo,
            valor_hora: valor_hora,
            percentual: percentual,
            valor_total: valor_total
        });
    });
    
    return dados;
}

// Exportar para CSV
document.getElementById('exportar_csv')?.addEventListener('click', function(e) {
    e.preventDefault();
    
    const dados = obterDadosFiltrados();
    
    if (dados.length === 0) {
        Swal.fire({
            text: "Não há dados para exportar!",
            icon: "warning",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
        return;
    }
    
    // Monta CSV
    let csv = 'Colaborador;Empresa;Data;Horas;Tipo;Valor Hora;% Adicional;Valor Total\n';
    
    dados.forEach(function(linha) {
        csv += `"${linha.colaborador}";"${linha.empresa}";"${linha.data}";"${linha.horas}";"${linha.tipo}";"${linha.valor_hora}";"${linha.percentual}";"${linha.valor_total}"\n`;
    });
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `horas_extras_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Swal.fire({
        text: `${dados.length} registro(s) exportado(s) com sucesso!`,
        icon: "success",
        buttonsStyling: false,
        confirmButtonText: "Ok",
        customClass: {
            confirmButton: "btn btn-success"
        },
        timer: 2000
    });
});

// Exportar para Excel (usando bibliotecas modernas)
document.getElementById('exportar_excel')?.addEventListener('click', function(e) {
    e.preventDefault();
    
    const dados = obterDadosFiltrados();
    
    if (dados.length === 0) {
        Swal.fire({
            text: "Não há dados para exportar!",
            icon: "warning",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
        return;
    }
    
    // Prepara dados para Excel
    const dadosExcel = dados.map(function(linha) {
        return {
            'Colaborador': linha.colaborador,
            'Empresa': linha.empresa,
            'Data': linha.data,
            'Horas': linha.horas,
            'Tipo': linha.tipo,
            'Valor Hora': linha.valor_hora,
            '% Adicional': linha.percentual,
            'Valor Total': linha.valor_total
        };
    });
    
    // Carrega biblioteca SheetJS se não estiver carregada
    if (typeof XLSX === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js';
        script.onload = function() {
            exportarParaExcel(dadosExcel);
        };
        document.head.appendChild(script);
    } else {
        exportarParaExcel(dadosExcel);
    }
});

function exportarParaExcel(dadosExcel) {
    const ws = XLSX.utils.json_to_sheet(dadosExcel);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Horas Extras');
    
    // Ajusta largura das colunas
    const colWidths = [
        { wch: 30 }, // Colaborador
        { wch: 25 }, // Empresa
        { wch: 12 }, // Data
        { wch: 10 }, // Horas
        { wch: 15 }, // Tipo
        { wch: 15 }, // Valor Hora
        { wch: 12 }, // % Adicional
        { wch: 15 }  // Valor Total
    ];
    ws['!cols'] = colWidths;
    
    XLSX.writeFile(wb, `horas_extras_${new Date().toISOString().split('T')[0]}.xlsx`);
    
    Swal.fire({
        text: `${dadosExcel.length} registro(s) exportado(s) com sucesso!`,
        icon: "success",
        buttonsStyling: false,
        confirmButtonText: "Ok",
        customClass: {
            confirmButton: "btn btn-success"
        },
        timer: 2000
    });
}

// Exportar para PDF
document.getElementById('exportar_pdf')?.addEventListener('click', function(e) {
    e.preventDefault();
    
    const dados = obterDadosFiltrados();
    
    if (dados.length === 0) {
        Swal.fire({
            text: "Não há dados para exportar!",
            icon: "warning",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
        return;
    }
    
    // Carrega jsPDF e autoTable se não estiverem carregadas
    if (typeof jsPDF === 'undefined') {
        const script1 = document.createElement('script');
        script1.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        
        const script2 = document.createElement('script');
        script2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js';
        
        script1.onload = function() {
            document.head.appendChild(script2);
            script2.onload = function() {
                exportarParaPDF(dados);
            };
        };
        document.head.appendChild(script1);
    } else {
        exportarParaPDF(dados);
    }
});

function exportarParaPDF(dados) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4'); // Modo paisagem
    
    // Título
    doc.setFontSize(16);
    doc.text('Relatório de Horas Extras', 14, 15);
    
    // Data de geração
    doc.setFontSize(10);
    doc.text(`Gerado em: ${new Date().toLocaleString('pt-BR')}`, 14, 22);
    
    // Prepara dados para tabela
    const corpo = dados.map(function(linha) {
        return [
            linha.colaborador,
            linha.empresa,
            linha.data,
            linha.horas,
            linha.tipo,
            linha.valor_hora,
            linha.percentual,
            linha.valor_total
        ];
    });
    
    // Gera tabela
    doc.autoTable({
        head: [['Colaborador', 'Empresa', 'Data', 'Horas', 'Tipo', 'Valor Hora', '% Adic.', 'Valor Total']],
        body: corpo,
        startY: 28,
        styles: {
            fontSize: 8,
            cellPadding: 2
        },
        headStyles: {
            fillColor: [54, 153, 255],
            textColor: 255,
            fontStyle: 'bold'
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245]
        },
        margin: { top: 28 }
    });
    
    // Estatísticas no final
    const finalY = doc.lastAutoTable.finalY + 10;
    doc.setFontSize(10);
    doc.text(`Total de registros: ${dados.length}`, 14, finalY);
    
    // Download
    doc.save(`horas_extras_${new Date().toISOString().split('T')[0]}.pdf`);
    
    Swal.fire({
        text: `${dados.length} registro(s) exportado(s) com sucesso!`,
        icon: "success",
        buttonsStyling: false,
        confirmButtonText: "Ok",
        customClass: {
            confirmButton: "btn btn-success"
        },
        timer: 2000
    });
}
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
        background-color: #f5f8fa !important;
        border: 1px solid #e4e6ef !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 44px !important;
        padding-left: 0 !important;
        color: #181c32 !important;
        font-weight: 400 !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
    }
    
    .select2-container .select2-selection--single .select2-selection__rendered {
        display: flex !important;
        align-items: center !important;
        color: #181c32 !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #a1a5b7 !important;
    }
    
    /* Força cor do texto em todos os Select2 - importante para sobrescrever estilos conflitantes */
    .select2-selection__rendered,
    .select2-selection__rendered *,
    .select2-container--default .select2-selection--single .select2-selection__rendered,
    .select2-container--default .select2-selection--single .select2-selection__rendered * {
        color: #181c32 !important;
    }
    
    /* Texto do colaborador selecionado no modal */
    #kt_modal_horaextra .select2-selection__rendered,
    #kt_modal_horaextra .select2-selection__rendered * {
        color: #181c32 !important;
    }
    
    /* Texto do colaborador no filtro */
    #kt_filtros_avancados_content .select2-selection__rendered,
    #kt_filtros_avancados_content .select2-selection__rendered * {
        color: #181c32 !important;
    }
    
    /* Dropdown options - garante texto escuro */
    .select2-results__option,
    .select2-results__option * {
        color: #181c32 !important;
    }
    
    .select2-results__option--highlighted,
    .select2-results__option--highlighted * {
        color: #ffffff !important;
    }
    
    /* Animação do painel de filtros */
    #kt_filtros_avancados_content {
        transition: all 0.3s ease-in-out;
        max-height: 1000px;
        overflow: hidden;
    }
    
    #kt_filtros_avancados_content.d-none {
        max-height: 0;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        opacity: 0;
    }
    
    /* Animação do ícone de filtro */
    .rotate-180 {
        transform: rotate(180deg);
        transition: transform 0.3s ease-in-out;
    }
    
    /* Estilos dos campos de filtro - fundo cinza consistente */
    #kt_filtros_avancados_content .form-control,
    #kt_filtros_avancados_content .form-select {
        background-color: #f5f8fa !important;
        border: 1px solid #e4e6ef !important;
        color: #181c32 !important;
        transition: all 0.2s ease;
    }
    
    #kt_filtros_avancados_content .form-control:focus,
    #kt_filtros_avancados_content .form-select:focus {
        background-color: #eef3f7 !important;
        border-color: #3699ff !important;
        box-shadow: 0 0 0 0.2rem rgba(54, 153, 255, 0.1) !important;
    }
    
    /* Placeholder dos inputs */
    #kt_filtros_avancados_content .form-control::placeholder {
        color: #a1a5b7 !important;
        opacity: 1;
    }
    
    /* Classe para campos preenchidos - destaque azul claro */
    #kt_filtros_avancados_content .campo-preenchido,
    #kt_filtros_avancados_content .form-control:not(:placeholder-shown),
    #kt_filtros_avancados_content input[type="date"]:valid:not([value=""]),
    #kt_filtros_avancados_content input[type="number"]:valid:not([value=""]) {
        background-color: #e8f4fc !important;
        border-color: #3699ff !important;
        font-weight: 500;
    }
    
    /* Select quando tem valor selecionado */
    #kt_filtros_avancados_content .form-select option:checked:not([value=""]) ~ .form-select {
        background-color: #e8f4fc !important;
        border-color: #3699ff !important;
    }
    
    /* Select2 dentro dos filtros - estilo consistente */
    #kt_filtros_avancados_content .select2-container--default .select2-selection--single {
        background-color: #f5f8fa !important;
        border: 1px solid #e4e6ef !important;
        height: 44px !important;
        display: flex !important;
        align-items: center !important;
    }
    
    #kt_filtros_avancados_content .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #181c32 !important;
        line-height: 44px !important;
        padding-left: 12px !important;
        font-weight: 400 !important;
    }
    
    #kt_filtros_avancados_content .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #a1a5b7 !important;
        font-weight: 400 !important;
    }
    
    /* Garante que o texto selecionado seja visível */
    #kt_filtros_avancados_content .select2-selection__rendered {
        color: #181c32 !important;
    }
    
    #kt_filtros_avancados_content .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
        top: 1px !important;
    }
    
    #kt_filtros_avancados_content .select2-container--default.select2-container--focus .select2-selection--single,
    #kt_filtros_avancados_content .select2-container--default.select2-container--open .select2-selection--single {
        background-color: #eef3f7 !important;
        border-color: #3699ff !important;
        box-shadow: 0 0 0 0.2rem rgba(54, 153, 255, 0.1) !important;
    }
    
    /* Select2 com valor selecionado - aplica destaque */
    #kt_filtros_avancados_content .select2-container.campo-preenchido .select2-selection--single {
        background-color: #e8f4fc !important;
        border-color: #3699ff !important;
        font-weight: 500;
    }
    
    #kt_filtros_avancados_content .select2-container.campo-preenchido .select2-selection__rendered {
        font-weight: 500 !important;
    }
    
    /* Dropdown do Select2 - Fundo e texto */
    .select2-container--default .select2-results__option {
        background-color: #ffffff !important;
        color: #181c32 !important;
        padding: 8px 12px !important;
    }
    
    .select2-container--default .select2-results__option:hover {
        background-color: #f5f8fa !important;
        color: #181c32 !important;
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3699ff !important;
        color: #ffffff !important;
    }
    
    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #e8f4fc !important;
        color: #3699ff !important;
        font-weight: 500 !important;
    }
    
    /* Container do dropdown */
    .select2-container--default .select2-results {
        background-color: #ffffff !important;
    }
    
    .select2-dropdown {
        background-color: #ffffff !important;
        border: 1px solid #e4e6ef !important;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1) !important;
    }
    
    /* Campo de busca dentro do dropdown */
    .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: #f5f8fa !important;
        border: 1px solid #e4e6ef !important;
        color: #181c32 !important;
        padding: 8px 12px !important;
    }
    
    .select2-container--default .select2-search--dropdown .select2-search__field:focus {
        background-color: #ffffff !important;
        border-color: #3699ff !important;
        outline: none !important;
    }
    
    /* Mensagem "Nenhum resultado" */
    .select2-container--default .select2-results__option.select2-results__message {
        background-color: #ffffff !important;
        color: #a1a5b7 !important;
    }
    
    /* Campos de data com cor de fundo consistente */
    #kt_filtros_avancados_content input[type="date"] {
        background-color: #f5f8fa !important;
        border: 1px solid #e4e6ef !important;
        color: #181c32 !important;
        padding: 0.75rem 1rem;
    }
    
    #kt_filtros_avancados_content input[type="date"]:focus {
        background-color: #eef3f7 !important;
        border-color: #3699ff !important;
        box-shadow: 0 0 0 0.2rem rgba(54, 153, 255, 0.1) !important;
    }
    
    #kt_filtros_avancados_content input[type="date"]:not(:placeholder-shown),
    #kt_filtros_avancados_content input[type="date"]:valid {
        background-color: #e8f4fc !important;
        border-color: #3699ff !important;
    }
    
    /* Campos number com cor de fundo consistente */
    #kt_filtros_avancados_content input[type="number"] {
        background-color: #f5f8fa !important;
        border: 1px solid #e4e6ef !important;
        color: #181c32 !important;
    }
    
    #kt_filtros_avancados_content input[type="number"]:focus {
        background-color: #eef3f7 !important;
        border-color: #3699ff !important;
        box-shadow: 0 0 0 0.2rem rgba(54, 153, 255, 0.1) !important;
    }
    
    /* Estilo do badge de contagem */
    #filtros_ativos_count {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }
    
    /* Melhora visual da tabela */
    #kt_horas_extras_table tbody tr {
        transition: all 0.2s ease;
    }
    
    #kt_horas_extras_table tbody tr:hover {
        background-color: #f8f9fa;
        transform: scale(1.01);
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    
    /* Destaque nas badges */
    .badge {
        font-weight: 600;
        padding: 0.5rem 0.75rem;
    }
</style>
<!--end::Select2 CSS-->

<script>
// Variáveis globais para controlar se deve adicionar outra
var adicionarOutra = false;
var colaboradorAnterior = null;
var removerOutra = false;
var colaboradorAnteriorRemover = null;

// Manipula o envio do formulário de adicionar hora extra
document.getElementById('kt_modal_horaextra_form')?.addEventListener('submit', function(e) {
    const btnClicked = document.activeElement;
    
    // Verifica qual botão foi clicado
    if (btnClicked && btnClicked.id === 'btn_salvar_e_adicionar') {
        e.preventDefault();
        adicionarOutra = true;
        enviarFormularioHoraExtra(this, btnClicked);
    } else if (btnClicked && btnClicked.id === 'btn_salvar') {
        adicionarOutra = false;
        // Deixa o form submeter normalmente
    }
});

// Manipula o envio do formulário de remover horas
document.getElementById('kt_modal_remover_horas_form')?.addEventListener('submit', function(e) {
    const btnClicked = document.activeElement;
    
    // Verifica qual botão foi clicado
    if (btnClicked && btnClicked.id === 'btn_remover_e_adicionar') {
        e.preventDefault();
        removerOutra = true;
        enviarFormularioRemoverHoras(this, btnClicked);
    } else if (btnClicked && btnClicked.id === 'btn_remover') {
        removerOutra = false;
        // Deixa o form submeter normalmente
    }
});

// Função para enviar formulário via AJAX
function enviarFormularioHoraExtra(form, btn) {
    // Mostra loading no botão
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    // Pega dados do formulário
    const formData = new FormData(form);
    colaboradorAnterior = formData.get('colaborador_id');
    
    // Envia via AJAX
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Remove loading
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        // Verifica se houve sucesso (procura por mensagem de sucesso no HTML)
        if (html.includes('alert-success') || html.includes('sucesso')) {
            // Fecha o modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_horaextra'));
            if (modal) {
                modal.hide();
            }
            
            // Mostra notificação de sucesso
            Swal.fire({
                text: "Hora extra cadastrada com sucesso!",
                icon: "success",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                },
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                if (adicionarOutra) {
                    // Reabre o modal após breve pausa
                    setTimeout(function() {
                        reabrirModalComColaborador();
                    }, 300);
                } else {
                    // Recarrega a página
                    window.location.reload();
                }
            });
        } else if (html.includes('alert-danger') || html.includes('Erro')) {
            // Extrai mensagem de erro
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const alertElement = doc.querySelector('.alert-danger');
            const mensagemErro = alertElement ? alertElement.textContent.trim() : 'Erro ao salvar hora extra';
            
            Swal.fire({
                text: mensagemErro,
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-danger"
                }
            });
        } else {
            // Erro genérico
            Swal.fire({
                text: "Erro ao processar a requisição",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-danger"
                }
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        
        // Remove loading
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        Swal.fire({
            text: "Erro ao enviar formulário: " + error.message,
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: {
                confirmButton: "btn btn-danger"
            }
        });
    });
}

// Função para enviar formulário de remover horas via AJAX
function enviarFormularioRemoverHoras(form, btn) {
    // Mostra loading no botão
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    // Pega dados do formulário
    const formData = new FormData(form);
    colaboradorAnteriorRemover = formData.get('colaborador_id');
    
    // Envia via AJAX
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Remove loading
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        // Verifica se houve sucesso
        if (html.includes('alert-success') || html.includes('sucesso')) {
            // Fecha o modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_remover_horas'));
            if (modal) {
                modal.hide();
            }
            
            // Mostra notificação de sucesso
            Swal.fire({
                text: "Horas removidas do banco com sucesso!",
                icon: "success",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                },
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                if (removerOutra) {
                    // Reabre o modal após breve pausa
                    setTimeout(function() {
                        reabrirModalRemoverComColaborador();
                    }, 300);
                } else {
                    // Recarrega a página
                    window.location.reload();
                }
            });
        } else if (html.includes('alert-danger') || html.includes('Erro')) {
            // Extrai mensagem de erro
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const alertElement = doc.querySelector('.alert-danger');
            const mensagemErro = alertElement ? alertElement.textContent.trim() : 'Erro ao remover horas';
            
            Swal.fire({
                text: mensagemErro,
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-danger"
                }
            });
        } else {
            Swal.fire({
                text: "Erro ao processar a requisição",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-danger"
                }
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        
        // Remove loading
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        Swal.fire({
            text: "Erro ao enviar formulário: " + error.message,
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: {
                confirmButton: "btn btn-danger"
            }
        });
    });
}

// Função para reabrir modal de remover mantendo colaborador selecionado
function reabrirModalRemoverComColaborador() {
    // Limpa o formulário
    const form = document.getElementById('kt_modal_remover_horas_form');
    if (form) {
        form.reset();
    }
    
    // Define data atual
    const dataInput = form.querySelector('input[name="data_movimentacao"]');
    if (dataInput) {
        dataInput.value = new Date().toISOString().split('T')[0];
    }
    
    // Restaura colaborador selecionado
    if (colaboradorAnteriorRemover) {
        const selectColaborador = document.getElementById('colaborador_id_remover');
        if (selectColaborador) {
            selectColaborador.value = colaboradorAnteriorRemover;
            
            // Atualiza Select2
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                jQuery('#colaborador_id_remover').val(colaboradorAnteriorRemover).trigger('change');
            }
            
            // Atualiza saldo
            const event = new Event('change');
            selectColaborador.dispatchEvent(event);
        }
    }
    
    // Limpa campos
    const quantidadeHoras = document.getElementById('quantidade_horas_remover');
    if (quantidadeHoras) {
        quantidadeHoras.value = '';
    }
    
    const motivo = form.querySelector('textarea[name="motivo"]');
    if (motivo) {
        motivo.value = '';
    }
    
    const observacoes = form.querySelector('textarea[name="observacoes"]');
    if (observacoes) {
        observacoes.value = '';
    }
    
    // Reabre o modal
    const modalElement = document.getElementById('kt_modal_remover_horas');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Foca no campo de horas após abrir
        setTimeout(function() {
            if (quantidadeHoras) {
                quantidadeHoras.focus();
            }
        }, 500);
    }
}

// Função para reabrir modal de adicionar mantendo colaborador selecionado
function reabrirModalComColaborador() {
    // Limpa o formulário
    const form = document.getElementById('kt_modal_horaextra_form');
    if (form) {
        form.reset();
    }
    
    // Define data atual
    const dataInput = form.querySelector('input[name="data_trabalho"]');
    if (dataInput) {
        dataInput.value = new Date().toISOString().split('T')[0];
    }
    
    // Restaura colaborador selecionado
    if (colaboradorAnterior) {
        const selectColaborador = document.getElementById('colaborador_id');
        if (selectColaborador) {
            selectColaborador.value = colaboradorAnterior;
            
            // Atualiza Select2
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                jQuery('#colaborador_id').val(colaboradorAnterior).trigger('change');
            }
        }
    }
    
    // Marca tipo de pagamento como dinheiro (padrão)
    const radioDinheiro = document.getElementById('tipo_pagamento_dinheiro');
    if (radioDinheiro) {
        radioDinheiro.checked = true;
        
        // Mostra card de cálculo
        document.getElementById('card_calculo_dinheiro').style.display = 'block';
        document.getElementById('info_saldo_banco').style.display = 'none';
    }
    
    // Limpa campos
    const quantidadeHoras = document.getElementById('quantidade_horas');
    if (quantidadeHoras) {
        quantidadeHoras.value = '';
    }
    
    const observacoes = form.querySelector('textarea[name="observacoes"]');
    if (observacoes) {
        observacoes.value = '';
    }
    
    // Atualiza cálculo
    calcularValorTotal();
    
    // Reabre o modal
    const modalElement = document.getElementById('kt_modal_horaextra');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Foca no campo de horas após abrir
        setTimeout(function() {
            if (quantidadeHoras) {
                quantidadeHoras.focus();
            }
        }, 500);
    }
}

// Inicializa Select2 quando o modal de hora extra for aberto
document.getElementById('kt_modal_horaextra')?.addEventListener('shown.bs.modal', function() {
    console.log('Modal aberto - inicializando Select2...');
    
    setTimeout(function() {
        // Verifica se jQuery está disponível
        if (typeof jQuery === 'undefined') {
            console.error('jQuery não está disponível');
            return;
        }
        
        var $select = jQuery('#colaborador_id');
        console.log('Select encontrado:', $select.length);
        
        // Verifica se Select2 está carregado
        if (typeof jQuery.fn.select2 === 'undefined') {
            console.error('Select2 não está carregado');
            
            // Tenta carregar Select2
            if (!jQuery('script[src*="select2"]').length) {
                console.log('Carregando Select2...');
                jQuery.getScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', function() {
                    console.log('Select2 carregado! Inicializando...');
                    initSelect2OnModal();
                });
            }
            return;
        }
        
        initSelect2OnModal();
        
        function initSelect2OnModal() {
            var $select = jQuery('#colaborador_id');
            
            // Se já foi inicializado, destrói primeiro
            if ($select.hasClass('select2-hidden-accessible')) {
                console.log('Destruindo Select2 existente...');
                $select.select2('destroy');
            }
            
            console.log('Inicializando Select2...');
            
            $select.select2({
                placeholder: 'Selecione um colaborador...',
                allowClear: true,
                width: '100%',
                dropdownParent: jQuery('#kt_modal_horaextra'), // IMPORTANTE: define o parent como o modal
                minimumResultsForSearch: 0,
                language: {
                    noResults: function() { return 'Nenhum colaborador encontrado'; },
                    searching: function() { return 'Buscando...'; }
                },
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    if (!data.element) return data.text;
                    
                    var $option = jQuery(data.element);
                    var foto = $option.attr('data-foto') || null;
                    var nome = $option.attr('data-nome') || data.text || '';
                    
                    var html = '<span style="display: flex; align-items: center;">';
                    if (foto) {
                        html += '<img src="' + foto + '" class="rounded-circle me-2" width="32" height="32" style="object-fit: cover;" onerror="this.src=\'../assets/media/avatars/blank.png\'" />';
                    } else {
                        var inicial = nome.charAt(0).toUpperCase();
                        html += '<span class="symbol symbol-circle symbol-32px me-2"><span class="symbol-label fs-6 fw-semibold bg-primary text-white">' + inicial + '</span></span>';
                    }
                    html += '<span>' + nome + '</span></span>';
                    return jQuery(html);
                },
                templateSelection: function(data) {
                    if (!data.id) return data.text;
                    if (!data.element) return data.text;
                    
                    var $option = jQuery(data.element);
                    var foto = $option.attr('data-foto') || null;
                    var nome = $option.attr('data-nome') || data.text || '';
                    
                    var html = '<span style="display: flex; align-items: center;">';
                    if (foto) {
                        html += '<img src="' + foto + '" class="rounded-circle me-2" width="24" height="24" style="object-fit: cover;" onerror="this.src=\'../assets/media/avatars/blank.png\'" />';
                    } else {
                        var inicial = nome.charAt(0).toUpperCase();
                        html += '<span class="symbol symbol-circle symbol-24px me-2"><span class="symbol-label fs-7 fw-semibold bg-primary text-white">' + inicial + '</span></span>';
                    }
                    html += '<span>' + nome + '</span></span>';
                    return jQuery(html);
                }
            });
            
            console.log('Select2 inicializado com sucesso!');
        }
    }, 350);
});

// Inicializa Select2 no filtro de colaborador
function initFiltroColaboradorSelect2() {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
        setTimeout(initFiltroColaboradorSelect2, 200);
        return;
    }
    
    var $selectFiltro = jQuery('#filtro_colaborador');
    if ($selectFiltro.length > 0) {
        // Destrói se já existe
        if ($selectFiltro.hasClass('select2-hidden-accessible')) {
            $selectFiltro.select2('destroy');
        }
        
        // Inicializa com estilo consistente
        $selectFiltro.select2({
            placeholder: 'Todos os colaboradores',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 0,
            language: {
                noResults: function() { return 'Nenhum colaborador encontrado'; },
                searching: function() { return 'Buscando...'; }
            }
        });
        
        // Listener para mudanças no select
        $selectFiltro.on('select2:select select2:clear', function(e) {
            verificarCamposPreenchidos();
        });
        
        console.log('Select2 do filtro inicializado!');
    }
    
    // Inicializa também o select de tipo de pagamento como Select2
    var $selectTipo = jQuery('#filtro_tipo_pagamento');
    if ($selectTipo.length > 0) {
        if ($selectTipo.hasClass('select2-hidden-accessible')) {
            $selectTipo.select2('destroy');
        }
        
        $selectTipo.select2({
            placeholder: 'Todos os tipos',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: Infinity, // Desabilita busca (poucos itens)
            language: {
                noResults: function() { return 'Nenhum tipo encontrado'; }
            }
        });
        
        $selectTipo.on('select2:select select2:clear', function(e) {
            verificarCamposPreenchidos();
        });
    }
}

// Função para verificar campos preenchidos e destacá-los
function verificarCamposPreenchidos() {
    // Verifica inputs
    jQuery('#kt_filtros_avancados_content input').each(function() {
        var $input = jQuery(this);
        if ($input.val() && $input.val() !== '') {
            $input.addClass('campo-preenchido');
        } else {
            $input.removeClass('campo-preenchido');
        }
    });
    
    // Verifica selects nativos
    jQuery('#kt_filtros_avancados_content select:not(.select2-hidden-accessible)').each(function() {
        var $select = jQuery(this);
        if ($select.val() && $select.val() !== '') {
            $select.addClass('campo-preenchido');
        } else {
            $select.removeClass('campo-preenchido');
        }
    });
    
    // Verifica Select2
    jQuery('#kt_filtros_avancados_content .select2-hidden-accessible').each(function() {
        var $select = jQuery(this);
        var $container = $select.next('.select2-container');
        
        if ($select.val() && $select.val() !== '') {
            $container.addClass('campo-preenchido');
        } else {
            $container.removeClass('campo-preenchido');
        }
    });
}

// Adiciona listeners para mudanças nos campos
jQuery(document).ready(function() {
    initFiltroColaboradorSelect2();
    
    // Listeners para inputs de data e number
    jQuery('#kt_filtros_avancados_content input').on('change input', function() {
        verificarCamposPreenchidos();
    });
    
    // Listener para selects nativos
    jQuery('#kt_filtros_avancados_content select:not(.select2-hidden-accessible)').on('change', function() {
        verificarCamposPreenchidos();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

