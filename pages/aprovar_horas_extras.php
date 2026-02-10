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
    $solicitacao_id = (int)($_POST['solicitacao_id'] ?? 0);
    
    if ($action === 'aprovar' || $action === 'rejeitar') {
        $observacoes_rh = sanitize($_POST['observacoes_rh'] ?? '');
        
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
                // Calcula valores
                $valor_hora = $solicitacao['salario'] / 220; // Assumindo 220 horas/mês
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
                
                // Envia email de notificação se template estiver ativo
                require_once __DIR__ . '/../includes/email_templates.php';
                enviar_email_horas_extras($hora_extra_id);
                
                // Envia notificação push para o colaborador
                require_once __DIR__ . '/../includes/push_notifications.php';
                enviar_push_colaborador(
                    $solicitacao['colaborador_id'],
                    'Horas Extras Aprovadas! ⏰',
                    'Suas ' . number_format($solicitacao['quantidade_horas'], 2, ',', '.') . ' horas extras foram aprovadas e serão pagas.',
                    'pages/meus_pagamentos.php',
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
                        <span class="text-muted mt-1 fw-semibold fs-7">Aprove ou rejeite as solicitações de horas extras</span>
                    </h3>
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
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-150px">Colaborador</th>
                                        <th class="min-w-100px">Empresa</th>
                                        <th class="min-w-100px">Data do Trabalho</th>
                                        <th class="min-w-100px">Quantidade</th>
                                        <th class="min-w-200px">Motivo</th>
                                        <th class="min-w-150px">Data Solicitação</th>
                                        <th class="min-w-200px text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitacoes as $solicitacao): ?>
                                        <tr>
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
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($solicitacao['empresa_nome'] ?? '-') ?></td>
                                            <td><?= formatar_data($solicitacao['data_trabalho']) ?></td>
                                            <td>
                                                <span class="badge badge-light-primary fs-6">
                                                    <?= number_format($solicitacao['quantidade_horas'], 2, ',', '.') ?>h
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($solicitacao['motivo']) ?>">
                                                    <?= htmlspecialchars($solicitacao['motivo']) ?>
                                                </div>
                                            </td>
                                            <td><?= formatar_data($solicitacao['created_at'], 'd/m/Y H:i') ?></td>
                                            <td class="text-end">
                                                <button type="button" 
                                                        class="btn btn-sm btn-success me-2" 
                                                        onclick="aprovarSolicitacao(<?= $solicitacao['id'] ?>)">
                                                    <i class="ki-duotone ki-check fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Aprovar
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="rejeitarSolicitacao(<?= $solicitacao['id'] ?>)">
                                                    <i class="ki-duotone ki-cross fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Rejeitar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
                        <label class="form-label">Observações (opcional)</label>
                        <textarea name="observacoes_rh" 
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

<script>
function aprovarSolicitacao(id) {
    document.getElementById('modal_titulo').textContent = 'Aprovar Solicitação';
    document.getElementById('form_action').value = 'aprovar';
    document.getElementById('form_solicitacao_id').value = id;
    document.getElementById('btn_confirmar').className = 'btn btn-success';
    document.getElementById('btn_confirmar').textContent = 'Aprovar';
    
    const modal = new bootstrap.Modal(document.getElementById('modal_acao'));
    modal.show();
}

function rejeitarSolicitacao(id) {
    document.getElementById('modal_titulo').textContent = 'Rejeitar Solicitação';
    document.getElementById('form_action').value = 'rejeitar';
    document.getElementById('form_solicitacao_id').value = id;
    document.getElementById('btn_confirmar').className = 'btn btn-danger';
    document.getElementById('btn_confirmar').textContent = 'Rejeitar';
    
    const modal = new bootstrap.Modal(document.getElementById('modal_acao'));
    modal.show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

