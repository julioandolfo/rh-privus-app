<?php
/**
 * Fechamento de Pagamentos - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('fechamento_pagamentos.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'criar_fechamento') {
        $empresa_id = (int)($_POST['empresa_id'] ?? 0);
        $mes_referencia = $_POST['mes_referencia'] ?? '';
        $colaboradores_ids = $_POST['colaboradores'] ?? [];
        
        if (empty($empresa_id) || empty($mes_referencia) || empty($colaboradores_ids)) {
            redirect('fechamento_pagamentos.php', 'Preencha todos os campos obrigatórios!', 'error');
        }
        
        try {
            // Verifica se já existe fechamento para este mês
            $stmt = $pdo->prepare("SELECT id FROM fechamentos_pagamento WHERE empresa_id = ? AND mes_referencia = ?");
            $stmt->execute([$empresa_id, $mes_referencia]);
            if ($stmt->fetch()) {
                redirect('fechamento_pagamentos.php', 'Já existe um fechamento para este mês!', 'error');
            }
            
            // Cria fechamento
            $stmt = $pdo->prepare("
                INSERT INTO fechamentos_pagamento (empresa_id, mes_referencia, data_fechamento, total_colaboradores, usuario_id, status)
                VALUES (?, ?, CURDATE(), ?, ?, 'aberto')
            ");
            $stmt->execute([$empresa_id, $mes_referencia, count($colaboradores_ids), $usuario['id']]);
            $fechamento_id = $pdo->lastInsertId();
            
            // Busca período (primeiro e último dia do mês)
            $ano_mes = explode('-', $mes_referencia);
            $data_inicio = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
            $data_fim = date('Y-m-t', strtotime($data_inicio));
            
            $total_pagamento = 0;
            $total_horas_extras = 0;
            
            // Adiciona colaboradores ao fechamento
            foreach ($colaboradores_ids as $colab_id) {
                $colab_id = (int)$colab_id;
                
                // Busca dados do colaborador
                $stmt = $pdo->prepare("SELECT salario FROM colaboradores WHERE id = ?");
                $stmt->execute([$colab_id]);
                $colab = $stmt->fetch();
                
                if (!$colab || !$colab['salario']) continue;
                
                $salario_base = $colab['salario'];
                
                // Busca horas extras do período
                $stmt = $pdo->prepare("
                    SELECT SUM(quantidade_horas) as total_horas, SUM(valor_total) as total_valor
                    FROM horas_extras
                    WHERE colaborador_id = ? AND data_trabalho >= ? AND data_trabalho <= ?
                ");
                $stmt->execute([$colab_id, $data_inicio, $data_fim]);
                $he_data = $stmt->fetch();
                
                $horas_extras = $he_data['total_horas'] ?? 0;
                $valor_horas_extras = $he_data['total_valor'] ?? 0;
                
                // Busca bônus ativos do colaborador no período
                // Lógica: bônus está ativo se:
                // - É permanente (ambas datas NULL) OU
                // - Não tem data_inicio OU data_inicio <= fim do período
                // - E não tem data_fim OU data_fim >= início do período
                $stmt = $pdo->prepare("
                    SELECT SUM(valor) as total_bonus
                    FROM colaboradores_bonus
                    WHERE colaborador_id = ?
                    AND (
                        (data_inicio IS NULL AND data_fim IS NULL)
                        OR (
                            (data_inicio IS NULL OR data_inicio <= ?)
                            AND (data_fim IS NULL OR data_fim >= ?)
                        )
                    )
                ");
                $stmt->execute([$colab_id, $data_fim, $data_inicio]);
                $bonus_data = $stmt->fetch();
                $total_bonus = $bonus_data['total_bonus'] ?? 0;
                
                // Insere bônus no fechamento
                $stmt = $pdo->prepare("
                    SELECT cb.*, tb.nome as tipo_bonus_nome
                    FROM colaboradores_bonus cb
                    INNER JOIN tipos_bonus tb ON cb.tipo_bonus_id = tb.id
                    WHERE cb.colaborador_id = ?
                    AND (
                        (cb.data_inicio IS NULL AND cb.data_fim IS NULL)
                        OR (
                            (cb.data_inicio IS NULL OR cb.data_inicio <= ?)
                            AND (cb.data_fim IS NULL OR cb.data_fim >= ?)
                        )
                    )
                ");
                $stmt->execute([$colab_id, $data_fim, $data_inicio]);
                $bonus_list = $stmt->fetchAll();
                
                foreach ($bonus_list as $bonus) {
                    $stmt = $pdo->prepare("
                        INSERT INTO fechamentos_pagamento_bonus 
                        (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, observacoes)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $fechamento_id, 
                        $colab_id, 
                        $bonus['tipo_bonus_id'], 
                        $bonus['valor'],
                        'Bônus automático do fechamento'
                    ]);
                }
                
                $valor_total = $salario_base + $valor_horas_extras + $total_bonus;
                
                // Insere item
                $stmt = $pdo->prepare("
                    INSERT INTO fechamentos_pagamento_itens 
                    (fechamento_id, colaborador_id, salario_base, horas_extras, valor_horas_extras, valor_total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$fechamento_id, $colab_id, $salario_base, $horas_extras, $valor_horas_extras, $valor_total]);
                
                $total_pagamento += $valor_total;
                $total_horas_extras += $valor_horas_extras;
            }
            
            // Atualiza totais do fechamento
            $stmt = $pdo->prepare("
                UPDATE fechamentos_pagamento 
                SET total_pagamento = ?, total_horas_extras = ?
                WHERE id = ?
            ");
            $stmt->execute([$total_pagamento, $total_horas_extras, $fechamento_id]);
            
            redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Fechamento criado com sucesso!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao criar fechamento: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'atualizar_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $horas_extras = str_replace(',', '.', $_POST['horas_extras'] ?? '0');
        $valor_horas_extras = str_replace(['.', ','], ['', '.'], $_POST['valor_horas_extras'] ?? '0');
        $descontos = str_replace(['.', ','], ['', '.'], $_POST['descontos'] ?? '0');
        $adicionais = str_replace(['.', ','], ['', '.'], $_POST['adicionais'] ?? '0');
        $bonus_editados = $_POST['bonus_editados'] ?? [];
        
        try {
            // Busca item atual
            $stmt = $pdo->prepare("SELECT * FROM fechamentos_pagamento_itens WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                redirect('fechamento_pagamentos.php', 'Item não encontrado!', 'error');
            }
            
            // Processa bônus editados
            $total_bonus = 0;
            if (!empty($bonus_editados) && is_array($bonus_editados)) {
                // Remove bônus antigos do fechamento para este colaborador
                $stmt = $pdo->prepare("DELETE FROM fechamentos_pagamento_bonus WHERE fechamento_pagamento_id = ? AND colaborador_id = ?");
                $stmt->execute([$item['fechamento_id'], $item['colaborador_id']]);
                
                // Insere bônus editados
                foreach ($bonus_editados as $bonus_data) {
                    if (!empty($bonus_data['tipo_bonus_id']) && !empty($bonus_data['valor'])) {
                        $valor_bonus = str_replace(['.', ','], ['', '.'], $bonus_data['valor']);
                        $total_bonus += $valor_bonus;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO fechamentos_pagamento_bonus 
                            (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, observacoes)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $item['fechamento_id'],
                            $item['colaborador_id'],
                            (int)$bonus_data['tipo_bonus_id'],
                            $valor_bonus,
                            $bonus_data['observacoes'] ?? 'Bônus editado manualmente'
                        ]);
                    }
                }
            }
            
            $valor_total = $item['salario_base'] + $valor_horas_extras + $total_bonus - $descontos + $adicionais;
            
            // Atualiza item
            $stmt = $pdo->prepare("
                UPDATE fechamentos_pagamento_itens 
                SET horas_extras = ?, valor_horas_extras = ?, descontos = ?, adicionais = ?, valor_total = ?
                WHERE id = ?
            ");
            $stmt->execute([$horas_extras, $valor_horas_extras, $descontos, $adicionais, $valor_total, $item_id]);
            
            // Recalcula totais do fechamento
            $stmt = $pdo->prepare("
                SELECT SUM(valor_total) as total FROM fechamentos_pagamento_itens WHERE fechamento_id = ?
            ");
            $stmt->execute([$item['fechamento_id']]);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET total_pagamento = ? WHERE id = ?");
            $stmt->execute([$total, $item['fechamento_id']]);
            
            redirect('fechamento_pagamentos.php?view=' . $item['fechamento_id'], 'Item atualizado com sucesso!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao atualizar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'fechar') {
        $fechamento_id = (int)($_POST['fechamento_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET status = 'fechado' WHERE id = ?");
            $stmt->execute([$fechamento_id]);
            
            // Envia emails para cada colaborador do fechamento
            require_once __DIR__ . '/../includes/email_templates.php';
            $stmt_itens = $pdo->prepare("SELECT colaborador_id FROM fechamentos_pagamento_itens WHERE fechamento_id = ?");
            $stmt_itens->execute([$fechamento_id]);
            $itens = $stmt_itens->fetchAll();
            
            foreach ($itens as $item) {
                enviar_email_fechamento_pagamento($fechamento_id, $item['colaborador_id']);
            }
            
            redirect('fechamento_pagamentos.php?view=' . $fechamento_id, 'Fechamento concluído!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao fechar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $fechamento_id = (int)($_POST['fechamento_id'] ?? 0);
        try {
            // Verifica permissão
            $stmt = $pdo->prepare("SELECT empresa_id, status FROM fechamentos_pagamento WHERE id = ?");
            $stmt->execute([$fechamento_id]);
            $fechamento = $stmt->fetch();
            
            if (!$fechamento) {
                redirect('fechamento_pagamentos.php', 'Fechamento não encontrado!', 'error');
            }
            
            if ($usuario['role'] !== 'ADMIN' && $fechamento['empresa_id'] != $usuario['empresa_id']) {
                redirect('fechamento_pagamentos.php', 'Você não tem permissão para excluir este fechamento!', 'error');
            }
            
            // Deleta fechamento (os itens serão deletados automaticamente por CASCADE)
            $stmt = $pdo->prepare("DELETE FROM fechamentos_pagamento WHERE id = ?");
            $stmt->execute([$fechamento_id]);
            
            redirect('fechamento_pagamentos.php', 'Fechamento excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('fechamento_pagamentos.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca fechamentos
$where = '';
$params = [];
if ($usuario['role'] !== 'ADMIN') {
    $where = "WHERE f.empresa_id = ?";
    $params[] = $usuario['empresa_id'];
}

$stmt = $pdo->prepare("
    SELECT f.*, e.nome_fantasia as empresa_nome, u.nome as usuario_nome
    FROM fechamentos_pagamento f
    LEFT JOIN empresas e ON f.empresa_id = e.id
    LEFT JOIN usuarios u ON f.usuario_id = u.id
    $where
    ORDER BY f.mes_referencia DESC, f.created_at DESC
");
$stmt->execute($params);
$fechamentos = $stmt->fetchAll();

// Busca empresas para o select
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
} else {
    $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$usuario['empresa_id']]);
}
$empresas = $stmt->fetchAll();

// Se está visualizando um fechamento específico
$fechamento_view = null;
$itens_fechamento = [];
$bonus_por_colaborador = [];
if (isset($_GET['view'])) {
    $fechamento_id = (int)$_GET['view'];
    $stmt = $pdo->prepare("
        SELECT f.*, e.nome_fantasia as empresa_nome, u.nome as usuario_nome
        FROM fechamentos_pagamento f
        LEFT JOIN empresas e ON f.empresa_id = e.id
        LEFT JOIN usuarios u ON f.usuario_id = u.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fechamento_id]);
    $fechamento_view = $stmt->fetch();
    
    if ($fechamento_view) {
        // Verifica permissão
        if ($usuario['role'] !== 'ADMIN' && $fechamento_view['empresa_id'] != $usuario['empresa_id']) {
            redirect('fechamento_pagamentos.php', 'Você não tem permissão para visualizar este fechamento!', 'error');
        }
        
        // Busca itens (incluindo campos de documento)
        $stmt = $pdo->prepare("
            SELECT i.*, c.nome_completo as colaborador_nome, c.id as colaborador_id,
                   i.documento_anexo, i.documento_status, i.documento_data_envio,
                   i.documento_data_aprovacao, i.documento_observacoes,
                   u_aprovador.nome as aprovador_nome
            FROM fechamentos_pagamento_itens i
            INNER JOIN colaboradores c ON i.colaborador_id = c.id
            LEFT JOIN usuarios u_aprovador ON i.documento_aprovado_por = u_aprovador.id
            WHERE i.fechamento_id = ?
            ORDER BY c.nome_completo
        ");
        $stmt->execute([$fechamento_id]);
        $itens_fechamento = $stmt->fetchAll();
        
        // Calcula estatísticas de documentos
        $stats_pendentes = 0;
        $stats_enviados = 0;
        $stats_aprovados = 0;
        $stats_rejeitados = 0;
        
        foreach ($itens_fechamento as $item) {
            $status = $item['documento_status'] ?? 'pendente';
            if ($status === 'pendente') $stats_pendentes++;
            elseif ($status === 'enviado') $stats_enviados++;
            elseif ($status === 'aprovado') $stats_aprovados++;
            elseif ($status === 'rejeitado') $stats_rejeitados++;
        }
        
        // Busca período do fechamento para buscar bônus ativos
        $ano_mes = explode('-', $fechamento_view['mes_referencia']);
        $data_inicio_periodo = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
        $data_fim_periodo = date('Y-m-t', strtotime($data_inicio_periodo));
        
        // Busca bônus de cada colaborador no fechamento
        // Primeiro tenta buscar dos bônus salvos no fechamento
        foreach ($itens_fechamento as $item) {
            $stmt = $pdo->prepare("
                SELECT fb.*, tb.nome as tipo_bonus_nome
                FROM fechamentos_pagamento_bonus fb
                INNER JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
                WHERE fb.fechamento_pagamento_id = ? AND fb.colaborador_id = ?
            ");
            $stmt->execute([$fechamento_id, $item['colaborador_id']]);
            $bonus_salvos = $stmt->fetchAll();
            
            // Se não encontrou bônus salvos no fechamento, busca bônus ativos do colaborador
            if (empty($bonus_salvos)) {
                $stmt = $pdo->prepare("
                    SELECT cb.*, tb.nome as tipo_bonus_nome
                    FROM colaboradores_bonus cb
                    INNER JOIN tipos_bonus tb ON cb.tipo_bonus_id = tb.id
                    WHERE cb.colaborador_id = ?
                    AND (
                        (cb.data_inicio IS NULL AND cb.data_fim IS NULL)
                        OR (
                            (cb.data_inicio IS NULL OR cb.data_inicio <= ?)
                            AND (cb.data_fim IS NULL OR cb.data_fim >= ?)
                        )
                    )
                ");
                $stmt->execute([$item['colaborador_id'], $data_fim_periodo, $data_inicio_periodo]);
                $bonus_salvos = $stmt->fetchAll();
            }
            
            $bonus_por_colaborador[$item['colaborador_id']] = $bonus_salvos;
        }
    }
}

// Busca tipos de bônus para o modal de edição
$stmt = $pdo->query("SELECT * FROM tipos_bonus WHERE status = 'ativo' ORDER BY nome");
$tipos_bonus = $stmt->fetchAll();

$page_title = 'Fechamento de Pagamentos';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Fechamento de Pagamentos</h1>
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
                <li class="breadcrumb-item text-gray-900">Fechamento de Pagamentos</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <?php if ($fechamento_view): ?>
        <!-- Visualização de fechamento específico -->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Fechamento - <?= date('m/Y', strtotime($fechamento_view['mes_referencia'] . '-01')) ?></h3>
                <div class="card-toolbar">
                    <?php if ($fechamento_view['status'] === 'aberto'): ?>
                    <form method="POST" style="display: inline;" id="form_fechar_<?= $fechamento_view['id'] ?>">
                        <input type="hidden" name="action" value="fechar">
                        <input type="hidden" name="fechamento_id" value="<?= $fechamento_view['id'] ?>">
                        <button type="button" class="btn btn-success" onclick="fecharFechamento(<?= $fechamento_view['id'] ?>)">
                            Fechar Fechamento
                        </button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-danger ms-2" onclick="deletarFechamento(<?= $fechamento_view['id'] ?>, '<?= date('m/Y', strtotime($fechamento_view['mes_referencia'] . '-01')) ?>')">
                        <i class="ki-duotone ki-trash fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Excluir
                    </button>
                    <a href="fechamento_pagamentos.php" class="btn btn-light ms-2">Voltar</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-7">
                    <div class="col-md-3">
                        <strong>Empresa:</strong> <?= htmlspecialchars($fechamento_view['empresa_nome']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Mês/Ano:</strong> <?= date('m/Y', strtotime($fechamento_view['mes_referencia'] . '-01')) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong> 
                        <?php if ($fechamento_view['status'] === 'aberto'): ?>
                            <span class="badge badge-light-warning">Aberto</span>
                        <?php elseif ($fechamento_view['status'] === 'fechado'): ?>
                            <span class="badge badge-light-info">Fechado</span>
                        <?php else: ?>
                            <span class="badge badge-light-success">Pago</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Total:</strong> <span class="text-success fw-bold">R$ <?= number_format($fechamento_view['total_pagamento'], 2, ',', '.') ?></span>
                    </div>
                </div>
                
                <?php if ($fechamento_view['status'] === 'fechado'): ?>
                <!--begin::Estatísticas de Documentos-->
                <div class="row g-3 mb-7">
                    <div class="col-md-3">
                        <div class="card bg-light-danger">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Pendentes</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= $stats_pendentes ?></span>
                                    </div>
                                    <i class="ki-duotone ki-time fs-1 text-danger">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-warning">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Enviados</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= $stats_enviados ?></span>
                                    </div>
                                    <i class="ki-duotone ki-file-up fs-1 text-warning">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Aprovados</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= $stats_aprovados ?></span>
                                    </div>
                                    <i class="ki-duotone ki-check-circle fs-1 text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-info">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Total Itens</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= count($itens_fechamento) ?></span>
                                    </div>
                                    <i class="ki-duotone ki-people fs-1 text-info">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Estatísticas de Documentos-->
                <?php endif; ?>
                
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Salário Base</th>
                            <th>Horas Extras</th>
                            <th>Valor H.E.</th>
                            <th>Bônus</th>
                            <th>Descontos</th>
                            <th>Adicionais</th>
                            <th>Total</th>
                            <?php if ($fechamento_view['status'] === 'fechado'): ?>
                            <th>Documento</th>
                            <?php endif; ?>
                            <?php if ($fechamento_view['status'] === 'aberto'): ?>
                            <th>Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens_fechamento as $item): 
                            $bonus_colab = $bonus_por_colaborador[$item['colaborador_id']] ?? [];
                            $total_bonus = array_sum(array_column($bonus_colab, 'valor'));
                            $valor_total_com_bonus = $item['salario_base'] + $item['valor_horas_extras'] + $total_bonus + ($item['adicionais'] ?? 0) - ($item['descontos'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['colaborador_nome']) ?></strong>
                                <?php if (!empty($bonus_colab)): ?>
                                <br><small class="text-muted">
                                    <?php foreach ($bonus_colab as $bonus): ?>
                                    <span class="badge badge-light-success me-1"><?= htmlspecialchars($bonus['tipo_bonus_nome']) ?>: <?= formatar_moeda($bonus['valor']) ?></span>
                                    <?php endforeach; ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($item['salario_base'], 2, ',', '.') ?></td>
                            <td><?= number_format($item['horas_extras'], 2, ',', '.') ?>h</td>
                            <td>R$ <?= number_format($item['valor_horas_extras'], 2, ',', '.') ?></td>
                            <td>
                                <?php if ($total_bonus > 0): ?>
                                    <?php if (!empty($bonus_colab)): ?>
                                    <a href="#" class="text-success fw-bold text-hover-primary" onclick="mostrarDetalhesBonus(<?= htmlspecialchars(json_encode([
                                        'colaborador_nome' => $item['colaborador_nome'],
                                        'bonus' => $bonus_colab,
                                        'total' => $total_bonus
                                    ])) ?>); return false;" title="Clique para ver detalhes dos bônus">
                                        R$ <?= number_format($total_bonus, 2, ',', '.') ?>
                                    </a>
                                    <?php else: ?>
                                    <strong class="text-success">R$ <?= number_format($total_bonus, 2, ',', '.') ?></strong>
                                    <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">R$ 0,00</span>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($item['descontos'] ?? 0, 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($item['adicionais'] ?? 0, 2, ',', '.') ?></td>
                            <td><strong>R$ <?= number_format($valor_total_com_bonus, 2, ',', '.') ?></strong></td>
                            <?php if ($fechamento_view['status'] === 'fechado'): ?>
                            <td>
                                <?php
                                $status_doc = $item['documento_status'] ?? 'pendente';
                                $badges = [
                                    'pendente' => '<span class="badge badge-light-danger">Pendente</span>',
                                    'enviado' => '<span class="badge badge-light-warning">Enviado</span>',
                                    'aprovado' => '<span class="badge badge-light-success">Aprovado</span>',
                                    'rejeitado' => '<span class="badge badge-light-danger">Rejeitado</span>'
                                ];
                                echo $badges[$status_doc] ?? '<span class="badge badge-light-secondary">-</span>';
                                ?>
                                <?php if (!empty($item['documento_anexo'])): ?>
                                    <br><button type="button" class="btn btn-sm btn-light-primary mt-1" 
                                            onclick="verDocumentoAdmin(<?= $fechamento_view['id'] ?>, <?= $item['id'] ?>)">
                                        <i class="ki-duotone ki-eye fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Ver
                                    </button>
                                <?php endif; ?>
                                <?php if ($status_doc === 'enviado'): ?>
                                    <br><button type="button" class="btn btn-sm btn-success mt-1" 
                                            onclick="aprovarDocumento(<?= $item['id'] ?>)">
                                        <i class="ki-duotone ki-check fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Aprovar
                                    </button>
                                    <br><button type="button" class="btn btn-sm btn-danger mt-1" 
                                            onclick="rejeitarDocumento(<?= $item['id'] ?>)">
                                        <i class="ki-duotone ki-cross fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Rejeitar
                                    </button>
                                <?php endif; ?>
                                <?php if ($item['documento_observacoes']): ?>
                                    <br><small class="text-muted" title="<?= htmlspecialchars($item['documento_observacoes']) ?>">
                                        <?= htmlspecialchars(mb_substr($item['documento_observacoes'], 0, 30)) ?>
                                        <?= mb_strlen($item['documento_observacoes']) > 30 ? '...' : '' ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <?php if ($fechamento_view['status'] === 'aberto'): ?>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editarItem(<?= htmlspecialchars(json_encode($item)) ?>)">
                                    Editar
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Lista de fechamentos -->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" data-kt-fechamento-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar fechamentos" />
                    </div>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_fechamento">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Novo Fechamento
                    </button>
                </div>
            </div>
            <div class="card-body pt-0">
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_fechamentos_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-150px">Empresa</th>
                            <th class="min-w-100px">Mês/Ano</th>
                            <th class="min-w-100px">Data Fechamento</th>
                            <th class="min-w-100px">Colaboradores</th>
                            <th class="min-w-120px">Total Pagamento</th>
                            <th class="min-w-120px">Total H.E.</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($fechamentos as $fechamento): ?>
                        <tr>
                            <td><?= $fechamento['id'] ?></td>
                            <td><?= htmlspecialchars($fechamento['empresa_nome']) ?></td>
                            <td><?= date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')) ?></td>
                            <td><?= date('d/m/Y', strtotime($fechamento['data_fechamento'])) ?></td>
                            <td><?= $fechamento['total_colaboradores'] ?></td>
                            <td><strong>R$ <?= number_format($fechamento['total_pagamento'], 2, ',', '.') ?></strong></td>
                            <td>R$ <?= number_format($fechamento['total_horas_extras'], 2, ',', '.') ?></td>
                            <td>
                                <?php if ($fechamento['status'] === 'aberto'): ?>
                                    <span class="badge badge-light-warning">Aberto</span>
                                <?php elseif ($fechamento['status'] === 'fechado'): ?>
                                    <span class="badge badge-light-info">Fechado</span>
                                <?php else: ?>
                                    <span class="badge badge-light-success">Pago</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="fechamento_pagamentos.php?view=<?= $fechamento['id'] ?>" class="btn btn-sm btn-light-primary me-2">
                                    Ver
                                </a>
                                <button type="button" class="btn btn-sm btn-light-danger" onclick="deletarFechamento(<?= $fechamento['id'] ?>, '<?= date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')) ?>')">
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
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<?php if (isset($fechamento_view) && $fechamento_view): ?>
<!-- Modal Detalhes Bônus -->
<div class="modal fade" id="kt_modal_detalhes_bonus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="kt_modal_detalhes_bonus_titulo">Detalhes dos Bônus</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="kt_modal_detalhes_bonus_conteudo">
                    <!-- Conteúdo será preenchido via JavaScript -->
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!--begin::Modal - Criar Fechamento-->
<div class="modal fade" id="kt_modal_fechamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-750px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Fechamento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_fechamento_form" method="POST" class="form">
                    <input type="hidden" name="action" value="criar_fechamento">
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($empresas as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nome_fantasia']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Mês/Ano de Referência</label>
                            <input type="month" name="mes_referencia" class="form-control form-control-solid" value="<?= date('Y-m') ?>" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="required fw-semibold fs-6 mb-2">Colaboradores</label>
                            <div id="colaboradores_container" class="border rounded p-4" style="max-height: 300px; overflow-y: auto;">
                                <p class="text-muted">Selecione uma empresa primeiro</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Criar Fechamento</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Editar Item-->
<div class="modal fade" id="kt_modal_item" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Item</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_item_form" method="POST" class="form">
                    <input type="hidden" name="action" value="atualizar_item">
                    <input type="hidden" name="item_id" id="item_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador</label>
                            <input type="text" id="item_colaborador" class="form-control form-control-solid" readonly />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Salário Base</label>
                            <input type="text" id="item_salario_base" class="form-control form-control-solid" readonly />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Horas Extras</label>
                            <input type="text" name="horas_extras" id="item_horas_extras" class="form-control form-control-solid" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Valor Horas Extras</label>
                            <input type="text" name="valor_horas_extras" id="item_valor_horas_extras" class="form-control form-control-solid" />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Descontos</label>
                            <input type="text" name="descontos" id="item_descontos" class="form-control form-control-solid" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Adicionais</label>
                            <input type="text" name="adicionais" id="item_adicionais" class="form-control form-control-solid" />
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="fw-semibold fs-6 mb-0">Bônus</label>
                            <button type="button" class="btn btn-sm btn-primary" onclick="adicionarBonusItem()">
                                <i class="ki-duotone ki-plus fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Bônus
                            </button>
                        </div>
                        <div id="bonus_container" class="border rounded p-4">
                            <p class="text-muted mb-0">Nenhum bônus adicionado</p>
                        </div>
                        <input type="hidden" name="bonus_editados" id="bonus_editados_json" value="[]">
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

<script>
// Carrega colaboradores ao selecionar empresa
document.getElementById('empresa_id')?.addEventListener('change', function() {
    const empresaId = this.value;
    const container = document.getElementById('colaboradores_container');
    
    if (!empresaId) {
        container.innerHTML = '<p class="text-muted">Selecione uma empresa primeiro</p>';
        return;
    }
    
    container.innerHTML = '<p class="text-muted">Carregando...</p>';
    
    fetch(`../api/get_colaboradores.php?empresa_id=${empresaId}&status=ativo&com_salario=1`)
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(colab => {
                html += `
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="colaboradores[]" value="${colab.id}" id="colab_${colab.id}">
                        <label class="form-check-label" for="colab_${colab.id}">
                            ${colab.nome_completo} - Salário: R$ ${parseFloat(colab.salario || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                        </label>
                    </div>
                `;
            });
            container.innerHTML = html || '<p class="text-muted">Nenhum colaborador encontrado</p>';
        })
        .catch(() => {
            container.innerHTML = '<p class="text-danger">Erro ao carregar colaboradores</p>';
        });
});

// Variável global para armazenar bônus do item
let bonusItemAtual = [];

// Editar item
function editarItem(item) {
    document.getElementById('item_id').value = item.id;
    document.getElementById('item_colaborador').value = item.colaborador_nome;
    document.getElementById('item_salario_base').value = 'R$ ' + parseFloat(item.salario_base || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('item_horas_extras').value = parseFloat(item.horas_extras || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('item_valor_horas_extras').value = parseFloat(item.valor_horas_extras || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('item_descontos').value = parseFloat(item.descontos || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('item_adicionais').value = parseFloat(item.adicionais || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Busca bônus do colaborador no fechamento
    const colaboradorId = item.colaborador_id;
    const fechamentoId = <?= isset($fechamento_view) && $fechamento_view ? $fechamento_view['id'] : 0 ?>;
    
    if (fechamentoId && colaboradorId) {
        // Busca bônus salvos no fechamento
        fetch(`../api/get_bonus_fechamento.php?fechamento_id=${fechamentoId}&colaborador_id=${colaboradorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    bonusItemAtual = data.data;
                    renderizarBonusContainer();
                } else {
                    bonusItemAtual = [];
                    renderizarBonusContainer();
                }
            })
            .catch(() => {
                bonusItemAtual = [];
                renderizarBonusContainer();
            });
    } else {
        bonusItemAtual = [];
        renderizarBonusContainer();
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_item'));
    modal.show();
    
    // Aplica máscaras após o modal ser exibido
    setTimeout(() => {
        aplicarMascarasItem();
    }, 300);
}

// Aplicar máscaras nos campos do modal de editar item
function aplicarMascarasItem() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
        jQuery('#item_valor_horas_extras').mask('#.##0,00', {reverse: true});
        jQuery('#item_descontos').mask('#.##0,00', {reverse: true});
        jQuery('#item_adicionais').mask('#.##0,00', {reverse: true});
        jQuery('#item_horas_extras').mask('#0,00', {reverse: true});
    }
}

// Renderizar container de bônus
function renderizarBonusContainer() {
    const container = document.getElementById('bonus_container');
    if (!container) return;
    
    if (bonusItemAtual.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">Nenhum bônus adicionado</p>';
        document.getElementById('bonus_editados_json').value = '[]';
        return;
    }
    
    let html = '';
    bonusItemAtual.forEach((bonus, index) => {
        html += `
            <div class="d-flex align-items-center gap-3 mb-3 p-3 border rounded" data-bonus-index="${index}">
                <div class="flex-grow-1">
                    <select class="form-select form-select-sm mb-2 bonus_tipo" data-index="${index}" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($tipos_bonus as $tipo): ?>
                        <option value="<?= $tipo['id'] ?>" ${bonus.tipo_bonus_id == <?= $tipo['id'] ?> ? 'selected' : ''}>
                            <?= htmlspecialchars($tipo['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control bonus_valor" data-index="${index}" 
                               value="${parseFloat(bonus.valor || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" 
                               placeholder="0,00" required />
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-light-danger" onclick="removerBonusItem(${index})">
                    <i class="ki-duotone ki-trash fs-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
    atualizarBonusJSON();
    aplicarMascarasBonus();
}

// Adicionar novo bônus
function adicionarBonusItem() {
    bonusItemAtual.push({
        tipo_bonus_id: '',
        valor: 0,
        observacoes: ''
    });
    renderizarBonusContainer();
}

// Remover bônus
function removerBonusItem(index) {
    bonusItemAtual.splice(index, 1);
    renderizarBonusContainer();
}

// Atualizar JSON de bônus
function atualizarBonusJSON() {
    const bonusData = bonusItemAtual.map((bonus, index) => {
        const tipoSelect = document.querySelector(`.bonus_tipo[data-index="${index}"]`);
        const valorInput = document.querySelector(`.bonus_valor[data-index="${index}"]`);
        
        return {
            tipo_bonus_id: tipoSelect ? tipoSelect.value : '',
            valor: valorInput ? valorInput.value.replace(/[^0-9,]/g, '').replace(',', '.') : '0',
            observacoes: ''
        };
    }).filter(b => b.tipo_bonus_id && b.valor);
    
    document.getElementById('bonus_editados_json').value = JSON.stringify(bonusData);
}

// Aplicar máscaras nos campos de bônus
function aplicarMascarasBonus() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
        document.querySelectorAll('.bonus_valor').forEach(input => {
            jQuery(input).mask('#.##0,00', {reverse: true});
            jQuery(input).on('input', atualizarBonusJSON);
        });
        document.querySelectorAll('.bonus_tipo').forEach(select => {
            select.addEventListener('change', atualizarBonusJSON);
        });
    }
}

// Atualizar JSON ao submeter formulário
document.getElementById('kt_modal_item_form')?.addEventListener('submit', function(e) {
    atualizarBonusJSON();
    
    // Adiciona os bônus como campos hidden
    const bonusData = JSON.parse(document.getElementById('bonus_editados_json').value || '[]');
    bonusData.forEach((bonus, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `bonus_editados[${index}][tipo_bonus_id]`;
        input.value = bonus.tipo_bonus_id;
        this.appendChild(input);
        
        const inputValor = document.createElement('input');
        inputValor.type = 'hidden';
        inputValor.name = `bonus_editados[${index}][valor]`;
        inputValor.value = bonus.valor;
        this.appendChild(inputValor);
        
        const inputObs = document.createElement('input');
        inputObs.type = 'hidden';
        inputObs.name = `bonus_editados[${index}][observacoes]`;
        inputObs.value = bonus.observacoes || '';
        this.appendChild(inputObs);
    });
});

// DataTables
var KTFechamentosList = function() {
    var initDatatable = function() {
        const table = document.getElementById('kt_fechamentos_table');
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
        
        const filterSearch = document.querySelector('[data-kt-fechamento-table-filter="search"]');
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

function waitForDependencies() {
    if (typeof jQuery !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        KTFechamentosList.init();
    } else {
        setTimeout(waitForDependencies, 100);
    }
}
waitForDependencies();

// Deletar fechamento
function deletarFechamento(id, mesAno) {
    Swal.fire({
        text: `Tem certeza que deseja excluir o fechamento de ${mesAno}? Esta ação não pode ser desfeita!`,
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
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="fechamento_id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Mostrar detalhes dos bônus
function mostrarDetalhesBonus(dados) {
    const titulo = document.getElementById('kt_modal_detalhes_bonus_titulo');
    const conteudo = document.getElementById('kt_modal_detalhes_bonus_conteudo');
    
    titulo.textContent = `Bônus de ${dados.colaborador_nome}`;
    
    let html = '<div class="mb-7">';
    html += '<div class="d-flex justify-content-between align-items-center mb-5">';
    html += '<h4 class="fw-bold text-gray-800">Total de Bônus</h4>';
    html += '<span class="text-success fw-bold fs-2">R$ ' + parseFloat(dados.total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
    html += '</div>';
    
    if (dados.bonus && dados.bonus.length > 0) {
        html += '<div class="table-responsive">';
        html += '<table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">';
        html += '<thead>';
        html += '<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">';
        html += '<th class="min-w-150px">Tipo de Bônus</th>';
        html += '<th class="min-w-100px text-end">Valor</th>';
        html += '<th class="min-w-100px">Data Início</th>';
        html += '<th class="min-w-100px">Data Fim</th>';
        html += '<th class="min-w-200px">Observações</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        dados.bonus.forEach(function(bonus) {
            html += '<tr>';
            html += '<td><span class="fw-bold text-gray-800">' + (bonus.tipo_bonus_nome || '-') + '</span></td>';
            html += '<td class="text-end"><span class="fw-bold text-success">R$ ' + parseFloat(bonus.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span></td>';
            html += '<td>' + (bonus.data_inicio ? new Date(bonus.data_inicio + 'T00:00:00').toLocaleDateString('pt-BR') : '<span class="text-muted">Permanente</span>') + '</td>';
            html += '<td>' + (bonus.data_fim ? new Date(bonus.data_fim + 'T00:00:00').toLocaleDateString('pt-BR') : '<span class="text-muted">Permanente</span>') + '</td>';
            html += '<td>' + (bonus.observacoes || '-') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
    } else {
        html += '<div class="alert alert-info">Nenhum bônus encontrado.</div>';
    }
    
    html += '</div>';
    
    conteudo.innerHTML = html;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_detalhes_bonus'));
    modal.show();
}

// Fechar fechamento
function fecharFechamento(id) {
    Swal.fire({
        text: "Tem certeza que deseja fechar este fechamento?",
        icon: "question",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Sim, fechar!",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn fw-bold btn-success",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then(function(result) {
        if (result.value) {
            document.getElementById('form_fechar_' + id).submit();
        }
    });
}

// Aprovar documento
function aprovarDocumento(itemId) {
    Swal.fire({
        title: 'Aprovar Documento?',
        text: 'Tem certeza que deseja aprovar este documento?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, aprovar',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: "btn fw-bold btn-success",
            cancelButton: "btn fw-bold btn-active-light-primary"
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('acao', 'aprovar');
            formData.append('observacoes', '');
            
            fetch('../api/aprovar_documento_pagamento.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro', 'Erro ao processar solicitação', 'error');
            });
        }
    });
}

// Rejeitar documento
function rejeitarDocumento(itemId) {
    Swal.fire({
        title: 'Rejeitar Documento',
        input: 'textarea',
        inputLabel: 'Motivo da rejeição',
        inputPlaceholder: 'Digite o motivo da rejeição...',
        inputAttributes: {
            'aria-label': 'Digite o motivo da rejeição'
        },
        showCancelButton: true,
        confirmButtonText: 'Rejeitar',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: "btn fw-bold btn-danger",
            cancelButton: "btn fw-bold btn-active-light-primary"
        },
        inputValidator: (value) => {
            if (!value) {
                return 'O motivo da rejeição é obrigatório!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('acao', 'rejeitar');
            formData.append('observacoes', result.value);
            
            fetch('../api/aprovar_documento_pagamento.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro', 'Erro ao processar solicitação', 'error');
            });
        }
    });
}

// Ver documento (admin)
function verDocumentoAdmin(fechamentoId, itemId) {
    fetch(`../api/get_documento_pagamento.php?fechamento_id=${fechamentoId}&item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const doc = data.data;
                const isImage = doc.is_image;
                
                let html = '';
                if (isImage) {
                    html = `
                        <div class="text-center">
                            <img src="../${doc.documento_anexo}" class="img-fluid" alt="Documento" style="max-height: 600px;">
                        </div>
                        <div class="mt-5">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Status:</span>
                                <span class="fw-bold">${doc.documento_status === 'aprovado' ? 'Aprovado' : doc.documento_status === 'rejeitado' ? 'Rejeitado' : 'Enviado'}</span>
                            </div>
                            ${doc.documento_data_envio ? `<div class="d-flex justify-content-between mb-2"><span class="text-muted">Data Envio:</span><span>${new Date(doc.documento_data_envio).toLocaleString('pt-BR')}</span></div>` : ''}
                            ${doc.documento_data_aprovacao ? `<div class="d-flex justify-content-between mb-2"><span class="text-muted">Data Aprovação:</span><span>${new Date(doc.documento_data_aprovacao).toLocaleString('pt-BR')}</span></div>` : ''}
                            ${doc.documento_observacoes ? `<div class="mt-3"><strong>Observações:</strong><div class="text-gray-600">${doc.documento_observacoes}</div></div>` : ''}
                        </div>
                    `;
                } else {
                    html = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-file fs-3x text-primary mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-gray-600 mb-3">Clique em "Download" para baixar o documento</div>
                            <div class="text-muted fs-7 mb-5">${doc.documento_nome || 'documento'}</div>
                            <div class="text-start">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Status:</span>
                                    <span class="fw-bold">${doc.documento_status === 'aprovado' ? 'Aprovado' : doc.documento_status === 'rejeitado' ? 'Rejeitado' : 'Enviado'}</span>
                                </div>
                                ${doc.documento_data_envio ? `<div class="d-flex justify-content-between mb-2"><span class="text-muted">Data Envio:</span><span>${new Date(doc.documento_data_envio).toLocaleString('pt-BR')}</span></div>` : ''}
                                ${doc.documento_data_aprovacao ? `<div class="d-flex justify-content-between mb-2"><span class="text-muted">Data Aprovação:</span><span>${new Date(doc.documento_data_aprovacao).toLocaleString('pt-BR')}</span></div>` : ''}
                                ${doc.documento_observacoes ? `<div class="mt-3"><strong>Observações:</strong><div class="text-gray-600">${doc.documento_observacoes}</div></div>` : ''}
                            </div>
                        </div>
                    `;
                }
                
                Swal.fire({
                    title: 'Documento',
                    html: html,
                    width: isImage ? '80%' : '700px',
                    showCancelButton: true,
                    confirmButtonText: 'Download',
                    cancelButtonText: 'Fechar',
                    buttonsStyling: false,
                    customClass: {
                        popup: 'text-start',
                        confirmButton: "btn fw-bold btn-primary",
                        cancelButton: "btn fw-bold btn-active-light-primary"
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open('../' + doc.documento_anexo, '_blank');
                    }
                });
            } else {
                Swal.fire('Erro', data.message || 'Erro ao carregar documento', 'error');
            }
        })
        .catch(error => {
            Swal.fire('Erro', 'Erro ao carregar documento', 'error');
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

