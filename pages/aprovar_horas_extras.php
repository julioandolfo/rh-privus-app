<?php
/**
 * Aprovar Solicitações de Horas Extras - RH
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// Apenas ADMIN e RH podem acessar
if (!can_show_menu(['ADMIN', 'RH']) || is_colaborador()) {
    redirect('dashboard.php', 'Acesso negado!', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Ação em massa - aprovar múltiplas
    if ($action === 'aprovar_mass') {
        $solicitacao_ids = $_POST['solicitacao_ids'] ?? [];
        $observacoes_rh = sanitize($_POST['observacoes_rh_mass'] ?? '');
        
        if (empty($solicitacao_ids)) {
            redirect('aprovar_horas_extras.php', 'Selecione pelo menos uma solicitação!', 'error');
        }
        
        $aprovadas = 0;
        $erros = [];
        
        foreach ($solicitacao_ids as $solicitacao_id) {
            $solicitacao_id = (int)$solicitacao_id;
            
            try {
                $pdo->beginTransaction();
                
                // Busca dados da solicitação
                $stmt = $pdo->prepare("
                    SELECT s.*, c.salario, c.empresa_id, e.percentual_hora_extra
                    FROM solicitacoes_horas_extras s
                    INNER JOIN colaboradores c ON s.colaborador_id = c.id
                    LEFT JOIN empresas e ON c.empresa_id = e.id
                    WHERE s.id = ? AND s.status = 'pendente'
                ");
                $stmt->execute([$solicitacao_id]);
                $solicitacao = $stmt->fetch();
                
                if (!$solicitacao) {
                    throw new Exception('Solicitação #' . $solicitacao_id . ' não encontrada ou já processada!');
                }
                
                // Verifica permissão de empresa
                if ($usuario['role'] !== 'ADMIN') {
                    if ($usuario['role'] === 'RH') {
                        if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                            if (!in_array($solicitacao['empresa_id'], $usuario['empresas_ids'])) {
                                throw new Exception('Sem permissão para aprovar solicitação #' . $solicitacao_id);
                            }
                        } else {
                            if ($solicitacao['empresa_id'] != ($usuario['empresa_id'] ?? 0)) {
                                throw new Exception('Sem permissão para aprovar solicitação #' . $solicitacao_id);
                            }
                        }
                    }
                }
                
                // Atualiza solicitação
                $stmt = $pdo->prepare("
                    UPDATE solicitacoes_horas_extras 
                    SET status = 'aprovada',
                        observacoes_rh = ?,
                        usuario_aprovacao_id = ?,
                        data_aprovacao = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $observacoes_rh,
                    $usuario['id'],
                    $solicitacao_id
                ]);
                
                // Calcula valores
                $valor_hora = $solicitacao['salario'] / 220;
                $percentual_adicional = $solicitacao['percentual_hora_extra'] ?? 50.00;
                $valor_total = $valor_hora * $solicitacao['quantidade_horas'] * (1 + ($percentual_adicional / 100));
                
                // Insere em horas_extras
                $stmt = $pdo->prepare("
                    INSERT INTO horas_extras (
                        colaborador_id, data_trabalho, quantidade_horas,
                        valor_hora, percentual_adicional, valor_total,
                        observacoes, usuario_id, tipo_pagamento
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'dinheiro')
                ");
                $stmt->execute([
                    $solicitacao['colaborador_id'],
                    $solicitacao['data_trabalho'],
                    $solicitacao['quantidade_horas'],
                    $valor_hora,
                    $percentual_adicional,
                    $valor_total,
                    'Solicitado pelo colaborador: ' . $solicitacao['motivo'] . ($observacoes_rh ? ' | RH: ' . $observacoes_rh : ''),
                    $usuario['id']
                ]);
                
                $hora_extra_id = $pdo->lastInsertId();
                
                // Envia email de notificação
                require_once __DIR__ . '/../includes/email_templates.php';
                enviar_email_horas_extras($hora_extra_id);
                
                // Envia notificação push
                require_once __DIR__ . '/../includes/push_notifications.php';
                enviar_push_colaborador(
                    $solicitacao['colaborador_id'],
                    'Horas Extras Aprovadas! ⏰',
                    'Suas ' . number_format($solicitacao['quantidade_horas'], 2, ',', '.') . ' horas extras foram aprovadas e serão pagas.',
                    get_base_url() . '/pages/meus_pagamentos.php',
                    'horas_extras',
                    $hora_extra_id,
                    'hora_extra'
                );
                
                $pdo->commit();
                $aprovadas++;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $erros[] = $e->getMessage();
            }
        }
        
        if ($aprovadas > 0) {
            $mensagem = $aprovadas . ' solicitação(ões) aprovada(s) com sucesso!';
            if (!empty($erros)) {
                $mensagem .= ' Erros: ' . implode(', ', $erros);
            }
            redirect('aprovar_horas_extras.php', $mensagem, 'success');
        } else {
            redirect('aprovar_horas_extras.php', 'Erro: ' . implode(', ', $erros), 'error');
        }
    }
    
    // Aprovar ou rejeitar individual
    if ($action === 'aprovar' || $action === 'rejeitar') {
        $solicitacao_id = (int)($_POST['solicitacao_id'] ?? 0);
        $observacoes_rh = sanitize($_POST['observacoes_rh'] ?? '');
        $quantidade_horas_editada = $_POST['quantidade_horas_editada'] ?? null;
        
        if (empty($solicitacao_id)) {
            redirect('aprovar_horas_extras.php', 'Solicitação não encontrada!', 'error');
        }
        
        try {
            $pdo->beginTransaction();
            
            // Busca dados da solicitação
            $stmt = $pdo->prepare("
                SELECT s.*, c.salario, c.empresa_id, e.percentual_hora_extra
                FROM solicitacoes_horas_extras s
                INNER JOIN colaboradores c ON s.colaborador_id = c.id
                LEFT JOIN empresas e ON c.empresa_id = e.id
                WHERE s.id = ? AND s.status = 'pendente'
            ");
            $stmt->execute([$solicitacao_id]);
            $solicitacao = $stmt->fetch();
            
            if (!$solicitacao) {
                throw new Exception('Solicitação não encontrada ou já processada!');
            }
            
            // Verifica permissão de empresa
            if ($usuario['role'] !== 'ADMIN') {
                if ($usuario['role'] === 'RH') {
                    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                        if (!in_array($solicitacao['empresa_id'], $usuario['empresas_ids'])) {
                            throw new Exception('Você não tem permissão para aprovar solicitações desta empresa!');
                        }
                    } else {
                        if ($solicitacao['empresa_id'] != ($usuario['empresa_id'] ?? 0)) {
                            throw new Exception('Você não tem permissão para aprovar solicitações desta empresa!');
                        }
                    }
                }
            }
            
            $status = ($action === 'aprovar') ? 'aprovada' : 'rejeitada';
            
            // Se foi editada a quantidade de horas, valida
            $quantidade_final = $solicitacao['quantidade_horas'];
            if ($action === 'aprovar' && !empty($quantidade_horas_editada)) {
                // Converte formato HH:MM para decimal
                if (preg_match('/^(\d{1,2}):(\d{2})$/', $quantidade_horas_editada, $matches)) {
                    $horas = intval($matches[1]);
                    $minutos = intval($matches[2]);
                    if ($minutos >= 60) {
                        throw new Exception('Minutos inválidos!');
                    }
                    $quantidade_final = $horas + ($minutos / 60);
                } else {
                    $quantidade_final = floatval(str_replace(',', '.', $quantidade_horas_editada));
                }
                
                if ($quantidade_final <= 0 || $quantidade_final > 8) {
                    throw new Exception('Quantidade de horas deve ser entre 0.01 e 8!');
                }
            }
            
            // Atualiza solicitação
            $stmt = $pdo->prepare("
                UPDATE solicitacoes_horas_extras 
                SET status = ?,
                    observacoes_rh = ?,
                    usuario_aprovacao_id = ?,
                    data_aprovacao = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $status,
                $observacoes_rh,
                $usuario['id'],
                $solicitacao_id
            ]);
            
            // Se aprovada, cria registro em horas_extras
            if ($action === 'aprovar') {
                // Calcula valores com a quantidade final (editada ou original)
                $valor_hora = $solicitacao['salario'] / 220;
                $percentual_adicional = $solicitacao['percentual_hora_extra'] ?? 50.00;
                $valor_total = $valor_hora * $quantidade_final * (1 + ($percentual_adicional / 100));
                
                // Insere em horas_extras
                $stmt = $pdo->prepare("
                    INSERT INTO horas_extras (
                        colaborador_id, data_trabalho, quantidade_horas,
                        valor_hora, percentual_adicional, valor_total,
                        observacoes, usuario_id, tipo_pagamento
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'dinheiro')
                ");
                $stmt->execute([
                    $solicitacao['colaborador_id'],
                    $solicitacao['data_trabalho'],
                    $quantidade_final,
                    $valor_hora,
                    $percentual_adicional,
                    $valor_total,
                    'Solicitado pelo colaborador: ' . $solicitacao['motivo'] . 
                    ($quantidade_final != $solicitacao['quantidade_horas'] ? ' | Quantidade ajustada pelo RH de ' . number_format($solicitacao['quantidade_horas'], 2) . 'h para ' . number_format($quantidade_final, 2) . 'h' : '') .
                    ($observacoes_rh ? ' | RH: ' . $observacoes_rh : ''),
                    $usuario['id']
                ]);
                
                $hora_extra_id = $pdo->lastInsertId();
                
                // Envia email de notificação se template estiver ativo
                require_once __DIR__ . '/../includes/email_templates.php';
                enviar_email_horas_extras($hora_extra_id);
                
                // Envia notificação push para o colaborador
                require_once __DIR__ . '/../includes/push_notifications.php';
                enviar_push_colaborador(
                    $solicitacao['colaborador_id'],
                    'Horas Extras Aprovadas! ⏰',
                    'Suas ' . number_format($quantidade_final, 2, ',', '.') . ' horas extras foram aprovadas e serão pagas.',
                    get_base_url() . '/pages/meus_pagamentos.php',
                    'horas_extras',
                    $hora_extra_id,
                    'hora_extra'
                );
            }
            
            $pdo->commit();
            
            $mensagem = ($action === 'aprovar') 
                ? 'Solicitação aprovada e horas extras registradas com sucesso!'
                : 'Solicitação rejeitada com sucesso!';
            
            redirect('aprovar_horas_extras.php', $mensagem, 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirect('aprovar_horas_extras.php', 'Erro: ' . $e->getMessage(), 'error');
        }
    }
    
    // Solicitar motivo - envia notificação ao colaborador
    if ($action === 'solicitar_motivo') {
        $solicitacao_id = (int)($_POST['solicitacao_id'] ?? 0);
        $observacao_solicitacao = sanitize($_POST['observacao_solicitacao'] ?? '');
        
        if (empty($solicitacao_id)) {
            redirect('aprovar_horas_extras.php', 'Solicitação não encontrada!', 'error');
        }
        
        try {
            $pdo->beginTransaction();
            
            // Busca dados da solicitação
            $stmt = $pdo->prepare("
                SELECT s.*, c.empresa_id, c.nome_completo
                FROM solicitacoes_horas_extras s
                INNER JOIN colaboradores c ON s.colaborador_id = c.id
                WHERE s.id = ? AND s.status = 'pendente'
            ");
            $stmt->execute([$solicitacao_id]);
            $solicitacao = $stmt->fetch();
            
            if (!$solicitacao) {
                throw new Exception('Solicitação não encontrada ou já processada!');
            }
            
            // Verifica permissão de empresa
            if ($usuario['role'] !== 'ADMIN') {
                if ($usuario['role'] === 'RH') {
                    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                        if (!in_array($solicitacao['empresa_id'], $usuario['empresas_ids'])) {
                            throw new Exception('Você não tem permissão para solicitar motivo desta solicitação!');
                        }
                    } else {
                        if ($solicitacao['empresa_id'] != ($usuario['empresa_id'] ?? 0)) {
                            throw new Exception('Você não tem permissão para solicitar motivo desta solicitação!');
                        }
                    }
                }
            }
            
            // Atualiza observacao_rh com a solicitação de motivo
            $observacao_completa = 'SOLICITAÇÃO DE MOTIVO: ' . $observacao_solicitacao;
            
            $stmt = $pdo->prepare("
                UPDATE solicitacoes_horas_extras 
                SET observacoes_rh = CONCAT(IFNULL(observacoes_rh, ''), ?)
                WHERE id = ?
            ");
            $stmt->execute([
                ($solicitacao['observacoes_rh'] ? '\n' : '') . $observacao_completa,
                $solicitacao_id
            ]);
            
            // Envia notificação push para o colaborador
            require_once __DIR__ . '/../includes/push_notifications.php';
            enviar_push_colaborador(
                $solicitacao['colaborador_id'],
                '📝 Motivo Necessário - Horas Extras',
                'O RH solicitou mais informações sobre suas horas extras do dia ' . formatar_data($solicitacao['data_trabalho']) . '. Clique para adicionar o motivo.',
                get_base_url() . '/pages/solicitar_horas_extras.php?acao=adicionar_motivo&id=' . $solicitacao_id,
                'horas_extras_motivo',
                $solicitacao_id,
                'solicitacao_horas_extras'
            );
            
            // Envia email para o colaborador
            require_once __DIR__ . '/../includes/email_templates.php';
            enviar_email_solicitacao_motivo($solicitacao_id, $observacao_solicitacao);
            
            $pdo->commit();
            
            redirect('aprovar_horas_extras.php', 'Solicitação de motivo enviada ao colaborador com sucesso!', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirect('aprovar_horas_extras.php', 'Erro: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca solicitações pendentes
$where = ["s.status = 'pendente'"];
$params = [];

// Filtra por empresa conforme permissões
if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "c.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    } else {
        $where[] = "c.empresa_id = ?";
        $params[] = $usuario['empresa_id'] ?? 0;
    }
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT s.*,
           c.nome_completo as colaborador_nome,
           c.salario,
           e.nome_fantasia as empresa_nome
    FROM solicitacoes_horas_extras s
    INNER JOIN colaboradores c ON s.colaborador_id = c.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    $where_sql
    ORDER BY s.created_at ASC
");
$stmt->execute($params);
$solicitacoes = $stmt->fetchAll();

$page_title = 'Aprovar Horas Extras';
include __DIR__ . '/../includes/header.php';

// Converte horas decimais para formato HH:MM
function decimalParaHorasMinutos($valor) {
    $horas = floor($valor);
    $minutos = round(($valor - $horas) * 60);
    return sprintf('%02d:%02d', $horas, $minutos);
}

// Retorna o dia da semana em português
function diaDaSemana($data) {
    $dias = [
        'Sunday' => 'Domingo',
        'Monday' => 'Segunda',
        'Tuesday' => 'Terça',
        'Wednesday' => 'Quarta',
        'Thursday' => 'Quinta',
        'Friday' => 'Sexta',
        'Saturday' => 'Sábado'
    ];
    $dia = date('l', strtotime($data));
    return $dias[$dia] ?? $dia;
}

// Verifica se foi solicitado motivo
function motivoFoiSolicitado($observacoes_rh) {
    return !empty($observacoes_rh) && strpos($observacoes_rh, 'SOLICITAÇÃO DE MOTIVO') !== false;
}
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    Aprovar Horas Extras
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="dashboard.php" class="text-muted text-hover-primary">Início</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-400 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">Aprovar Horas Extras</li>
                </ul>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Solicitações Pendentes</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Aprove, rejeite ou solicite mais informações</span>
                    </h3>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-success me-2" onclick="aprovarSelecionados()" id="btn_aprovar_selecionados" style="display: none;">
                            <i class="ki-duotone ki-check fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Aprovar Selecionados (<span id="contador_selecionados">0</span>)
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($solicitacoes)): ?>
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-check-circle fs-3x text-success mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <p class="text-muted fs-5">Nenhuma solicitação pendente</p>
                        </div>
                    <?php else: ?>
                        <form id="form_mass_action" method="POST">
                            <input type="hidden" name="action" value="aprovar_mass">
                            <div class="table-responsive">
                                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="w-40px">
                                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" id="check_all" onchange="toggleAll(this)">
                                                </div>
                                            </th>
                                            <th class="min-w-150px">Colaborador</th>
                                            <th class="min-w-100px">Empresa</th>
                                            <th class="min-w-120px">Data do Trabalho</th>
                                            <th class="min-w-100px">Quantidade</th>
                                            <th class="min-w-200px">Motivo</th>
                                            <th class="min-w-140px">Data Solicitação</th>
                                            <th class="min-w-120px text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($solicitacoes as $solicitacao): 
                                            $aguardandoMotivo = motivoFoiSolicitado($solicitacao['observacoes_rh'] ?? '');
                                        ?>
                                            <tr <?= $aguardandoMotivo ? 'class="bg-light-warning border-warning border border-dashed"' : '' ?>>
                                                <td>
                                                    <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                        <input class="form-check-input checkbox-solicitacao" 
                                                               type="checkbox" 
                                                               name="solicitacao_ids[]" 
                                                               value="<?= $solicitacao['id'] ?>"
                                                               data-horas="<?= decimalParaHorasMinutos($solicitacao['quantidade_horas']) ?>"
                                                               onchange="atualizarSelecionados()">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="symbol symbol-45px me-3">
                                                            <div class="symbol-label bg-light-primary text-primary fw-bold">
                                                                <?= mb_substr($solicitacao['colaborador_nome'], 0, 1) ?>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex justify-content-start flex-column">
                                                            <span class="text-dark fw-bold text-hover-primary fs-6">
                                                                <?= htmlspecialchars($solicitacao['colaborador_nome']) ?>
                                                            </span>
                                                            <?php if ($solicitacao['salario']): ?>
                                                                <span class="text-muted fw-semibold text-muted d-block fs-7">
                                                                    Salário: R$ <?= number_format($solicitacao['salario'], 2, ',', '.') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if (motivoFoiSolicitado($solicitacao['observacoes_rh'] ?? '')): ?>
                                                                <span class="badge badge-light-warning fs-8 mt-1" title="Motivo solicitado ao colaborador">
                                                                    <i class="ki-duotone ki-message-text-2 fs-8 me-1">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                        <span class="path3"></span>
                                                                    </i>
                                                                    Aguardando Motivo
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($solicitacao['empresa_nome'] ?? '-') ?></td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold"><?= diaDaSemana($solicitacao['data_trabalho']) ?></span>
                                                        <span class="text-muted fs-7"><?= formatar_data($solicitacao['data_trabalho']) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light-primary fs-6 quantidade-horas" data-id="<?= $solicitacao['id'] ?>">
                                                        <?= number_format($solicitacao['quantidade_horas'], 2, ',', '.') ?>h
                                                    </span>
                                                    <div class="text-muted fs-7"><?= decimalParaHorasMinutos($solicitacao['quantidade_horas']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($solicitacao['motivo']) ?>">
                                                        <?= htmlspecialchars($solicitacao['motivo']) ?>
                                                    </div>
                                                </td>
                                                <td><?= formatar_data($solicitacao['created_at'], 'd/m/Y H:i') ?></td>
                                                <td class="text-end">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm <?= $aguardandoMotivo ? 'btn-warning' : 'btn-light btn-active-light-primary' ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <?php if ($aguardandoMotivo): ?>
                                                                <i class="ki-duotone ki-message-text-2 fs-5 me-1">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                </i>
                                                            <?php endif; ?>
                                                            Ações
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item text-success" href="#" onclick="aprovarSolicitacao(<?= $solicitacao['id'] ?>, '<?= decimalParaHorasMinutos($solicitacao['quantidade_horas']) ?>', '<?= htmlspecialchars(addslashes($solicitacao['motivo'])) ?>'); return false;">
                                                                    <i class="ki-duotone ki-check fs-5 me-2">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                    </i>
                                                                    Aprovar
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="#" onclick="solicitarMotivo(<?= $solicitacao['id'] ?>, '<?= htmlspecialchars(addslashes($solicitacao['motivo'])) ?>'); return false;">
                                                                    <i class="ki-duotone ki-message-text-2 fs-5 me-2">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                        <span class="path3"></span>
                                                                    </i>
                                                                    Solicitar Motivo
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#" onclick="rejeitarSolicitacao(<?= $solicitacao['id'] ?>, '<?= htmlspecialchars(addslashes($solicitacao['motivo'])) ?>'); return false;">
                                                                    <i class="ki-duotone ki-cross fs-5 me-2">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                    </i>
                                                                    Rejeitar
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Modal de Aprovação/Rejeição -->
<div class="modal fade" id="modal_acao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_titulo">Aprovar Solicitação</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="form_acao" method="POST">
                <input type="hidden" name="action" id="form_action">
                <input type="hidden" name="solicitacao_id" id="form_solicitacao_id">
                <div class="modal-body">
                    <div class="mb-5">
                        <label class="form-label fw-bold">Motivo Informado pelo Colaborador:</label>
                        <div class="p-3 bg-light rounded" id="motivo_atual" style="max-height: 150px; overflow-y: auto;">
                            -
                        </div>
                    </div>
                    
                    <div class="mb-5" id="campo_edicao_horas">
                        <label class="form-label required">Quantidade de Horas</label>
                        <div class="input-group">
                            <input type="text" 
                                   name="quantidade_horas_editada" 
                                   id="quantidade_horas_editada"
                                   class="form-control" 
                                   placeholder="00:00"
                                   required>
                            <span class="input-group-text">HH:MM</span>
                        </div>
                        <div class="form-text">Digite no formato HH:MM. Ex: 02:30 para 2 horas e 30 minutos. Máximo: 8 horas</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Observações (opcional)</label>
                        <textarea name="observacoes_rh" 
                                  id="observacoes_rh"
                                  class="form-control" 
                                  rows="4" 
                                  placeholder="Adicione observações sobre a aprovação/rejeição..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn_confirmar">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Aprovação em Massa -->
<div class="modal fade" id="modal_acao_mass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Aprovar Solicitações Selecionadas</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="form_acao_mass" method="POST">
                <input type="hidden" name="action" value="aprovar_mass">
                <div id="mass_solicitacao_ids_container"></div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Você está aprovando <strong id="contador_mass">0</strong> solicitação(ões).
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Observações (serão aplicadas a todas as solicitações)</label>
                        <textarea name="observacoes_rh_mass" 
                                  class="form-control" 
                                  rows="4" 
                                  placeholder="Adicione observações que serão aplicadas a todas as solicitações aprovadas..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Aprovar Selecionados</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Solicitar Motivo -->
<div class="modal fade" id="modal_solicitar_motivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Solicitar Motivo ao Colaborador</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="form_solicitar_motivo" method="POST">
                <input type="hidden" name="action" value="solicitar_motivo">
                <input type="hidden" name="solicitacao_id" id="form_solicitacao_id_motivo">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        O colaborador receberá uma notificação para adicionar mais informações sobre o motivo.
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-bold">Motivo Atual:</label>
                        <div class="p-3 bg-light rounded" id="motivo_atual_solicitacao" style="max-height: 100px; overflow-y: auto;">
                            -
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">O que você precisa saber? (será enviado ao colaborador)</label>
                        <textarea name="observacao_solicitacao" 
                                  class="form-control" 
                                  rows="4" 
                                  required
                                  placeholder="Ex: Por favor, informe qual projeto você estava executando e qual a urgência. Seja específico sobre as atividades realizadas."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Enviar Solicitação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Variáveis para máscara de horas
let digitosDigitados = '';
const quantidadeHorasInput = document.getElementById('quantidade_horas_editada');

function aprovarSolicitacao(id, horasAtuais, motivo) {
    document.getElementById('modal_titulo').textContent = 'Aprovar Solicitação';
    document.getElementById('form_action').value = 'aprovar';
    document.getElementById('form_solicitacao_id').value = id;
    document.getElementById('btn_confirmar').className = 'btn btn-success';
    document.getElementById('btn_confirmar').textContent = 'Aprovar';
    document.getElementById('motivo_atual').textContent = motivo || 'Não informado';
    document.getElementById('quantidade_horas_editada').value = horasAtuais;
    digitosDigitados = horasAtuais.replace(':', '');
    
    // Mostra campo de edição de horas
    document.getElementById('campo_edicao_horas').style.display = 'block';
    document.getElementById('quantidade_horas_editada').required = true;
    
    const modal = new bootstrap.Modal(document.getElementById('modal_acao'));
    modal.show();
}

function rejeitarSolicitacao(id, motivo) {
    document.getElementById('modal_titulo').textContent = 'Rejeitar Solicitação';
    document.getElementById('form_action').value = 'rejeitar';
    document.getElementById('form_solicitacao_id').value = id;
    document.getElementById('btn_confirmar').className = 'btn btn-danger';
    document.getElementById('btn_confirmar').textContent = 'Rejeitar';
    document.getElementById('motivo_atual').textContent = motivo || 'Não informado';
    document.getElementById('observacoes_rh').value = '';
    
    // Esconde campo de edição de horas
    document.getElementById('campo_edicao_horas').style.display = 'none';
    document.getElementById('quantidade_horas_editada').required = false;
    
    const modal = new bootstrap.Modal(document.getElementById('modal_acao'));
    modal.show();
}

function solicitarMotivo(id, motivo) {
    document.getElementById('form_solicitacao_id_motivo').value = id;
    document.getElementById('motivo_atual_solicitacao').textContent = motivo || 'Não informado';
    
    const modal = new bootstrap.Modal(document.getElementById('modal_solicitar_motivo'));
    modal.show();
}

// Seleção em massa
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.checkbox-solicitacao');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    atualizarSelecionados();
}

function atualizarSelecionados() {
    const selecionados = document.querySelectorAll('.checkbox-solicitacao:checked');
    const contador = selecionados.length;
    
    document.getElementById('contador_selecionados').textContent = contador;
    
    if (contador > 0) {
        document.getElementById('btn_aprovar_selecionados').style.display = 'inline-flex';
    } else {
        document.getElementById('btn_aprovar_selecionados').style.display = 'none';
    }
}

function aprovarSelecionados() {
    const selecionados = document.querySelectorAll('.checkbox-solicitacao:checked');
    
    if (selecionados.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione pelo menos uma solicitação!',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    // Preenche o modal com os IDs selecionados
    const container = document.getElementById('mass_solicitacao_ids_container');
    container.innerHTML = '';
    
    selecionados.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'solicitacao_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    
    document.getElementById('contador_mass').textContent = selecionados.length;
    
    const modal = new bootstrap.Modal(document.getElementById('modal_acao_mass'));
    modal.show();
}

// Máscara HH:MM para o campo de edição de horas
if (quantidadeHorasInput) {
    quantidadeHorasInput.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' || e.key === 'Delete') {
            e.preventDefault();
            digitosDigitados = digitosDigitados.slice(0, -1);
            formatarCampoHoras();
            return;
        }
        
        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            
            if (digitosDigitados.length >= 4) {
                return;
            }
            
            digitosDigitados += e.key;
            formatarCampoHoras();
        }
    });
    
    quantidadeHorasInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasteData = e.clipboardData.getData('text').replace(/\D/g, '');
        digitosDigitados = pasteData.substring(0, 4);
        formatarCampoHoras();
    });
    
    quantidadeHorasInput.addEventListener('blur', function(e) {
        const value = e.target.value;
        const pattern = /^([0-7]?[0-9]):([0-5][0-9])$/;
        
        if (!pattern.test(value)) {
            e.target.value = '00:00';
            Swal.fire({
                icon: 'warning',
                title: 'Formato Inválido',
                text: 'Use o formato HH:MM (ex: 02:30 para 2 horas e 30 minutos)',
                confirmButtonText: 'OK'
            });
        } else {
            const [horas, minutos] = value.split(':').map(Number);
            const totalHoras = horas + (minutos / 60);
            
            if (totalHoras > 8) {
                e.target.value = '08:00';
                digitosDigitados = '0800';
                Swal.fire({
                    icon: 'warning',
                    title: 'Limite Excedido',
                    text: 'Máximo de 8 horas por solicitação. Valor ajustado para 08:00',
                    confirmButtonText: 'OK'
                });
            }
        }
    });
}

function formatarCampoHoras() {
    if (digitosDigitados.length === 0) {
        quantidadeHorasInput.value = '';
        return;
    }
    
    let horas = '00';
    let minutos = '00';
    
    if (digitosDigitados.length === 1) {
        quantidadeHorasInput.value = digitosDigitados;
        return;
    } else if (digitosDigitados.length === 2) {
        quantidadeHorasInput.value = digitosDigitados + ':';
        return;
    } else if (digitosDigitados.length === 3) {
        horas = digitosDigitados.substring(0, 2);
        minutos = digitosDigitados[2];
        quantidadeHorasInput.value = horas + ':' + minutos;
        return;
    } else {
        horas = digitosDigitados.substring(0, 2);
        minutos = digitosDigitados.substring(2, 4);
    }
    
    if (parseInt(horas) > 8) {
        horas = '08';
        minutos = '00';
        digitosDigitados = '0800';
    }
    
    if (parseInt(minutos) > 59) {
        minutos = '59';
        digitosDigitados = horas + '59';
    }
    
    quantidadeHorasInput.value = horas + ':' + minutos;
}

// Validação dos formulários
document.getElementById('form_acao').addEventListener('submit', function(e) {
    const action = document.getElementById('form_action').value;
    
    if (action === 'aprovar') {
        const quantidadeInput = document.getElementById('quantidade_horas_editada').value;
        const pattern = /^([0-7]?[0-9]):([0-5][0-9])$/;
        
        if (!pattern.test(quantidadeInput)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Formato Inválido',
                text: 'Use o formato HH:MM (ex: 02:30 para 2 horas e 30 minutos)',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        const [horas, minutos] = quantidadeInput.split(':').map(Number);
        const quantidade = horas + (minutos / 60);
        
        if (quantidade <= 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'A quantidade de horas deve ser maior que zero!',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        if (quantidade > 8) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Máximo de 8 horas por solicitação!',
                confirmButtonText: 'OK'
            });
            return false;
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
