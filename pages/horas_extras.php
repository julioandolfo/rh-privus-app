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
                    <div class="d-flex justify-content-end gap-2" data-kt-horaextra-table-toolbar="base">
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
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
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
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
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
                        <button type="submit" class="btn btn-warning">
                            <span class="indicator-label">Remover Horas</span>
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

// DataTables
var KTHorasExtrasList = function() {
    var initDatatable = function() {
        const table = document.getElementById('kt_horas_extras_table');
        if (!table) return;
        
        const datatable = $(table).DataTable({
            "info": true,
            "order": [],
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });
        
        // Filtro de busca
        const filterSearch = document.querySelector('[data-kt-horaextra-table-filter="search"]');
        if (filterSearch) {
            filterSearch.addEventListener('keyup', function(e) {
                datatable.search(e.target.value).draw();
            });
        }
    };
    
    return {
        init: function() {
            initDatatable();
        }
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

<script>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

