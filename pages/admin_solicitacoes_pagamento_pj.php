<?php
/**
 * Admin: Listagem de Solicitações de Pagamento PJ
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();
$usuario = $_SESSION['usuario'];

if (!in_array($usuario['role'] ?? '', ['ADMIN', 'RH'])) {
    $_SESSION['error'] = 'Sem permissão';
    header('Location: dashboard.php');
    exit;
}

$pdo = getDB();

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_mes = $_GET['mes'] ?? '';
$filtro_colab = $_GET['colaborador_id'] ?? '';

$where = ['1=1'];
$params = [];
if ($filtro_status) { $where[] = 's.status = ?'; $params[] = $filtro_status; }
if ($filtro_mes) { $where[] = 's.mes_referencia = ?'; $params[] = $filtro_mes; }
if ($filtro_colab) { $where[] = 's.colaborador_id = ?'; $params[] = (int)$filtro_colab; }

$sql = "
    SELECT s.*, c.nome_completo as colaborador_nome, e.nome_fantasia as empresa_nome,
           u.nome as aprovador_nome
    FROM solicitacoes_pagamento_pj s
    INNER JOIN colaboradores c ON s.colaborador_id = c.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN usuarios u ON s.aprovado_por = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitacoes = $stmt->fetchAll();

// Lista de colaboradores PJ para filtro
$stmt = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE tipo_contrato = 'PJ' ORDER BY nome_completo");
$colaboradores_pj = $stmt->fetchAll();

$page_title = 'Solicitações de Pagamento PJ';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="toolbar mb-5">
    <div class="container-fluid">
        <h1 class="text-gray-900 fw-bold fs-3 mb-0">Solicitações de Pagamento PJ</h1>
        <span class="text-muted fs-7">Aprovar/rejeitar pagamentos enviados por colaboradores PJ</span>
    </div>
</div>

<div class="container-fluid">
    <div class="card">
        <div class="card-header border-0 pt-6">
            <form method="get" class="d-flex gap-3 flex-wrap">
                <select name="status" class="form-select form-select-solid w-200px">
                    <option value="">Todos os status</option>
                    <option value="enviada" <?= $filtro_status === 'enviada' ? 'selected' : '' ?>>Enviada</option>
                    <option value="em_analise" <?= $filtro_status === 'em_analise' ? 'selected' : '' ?>>Em Análise</option>
                    <option value="aprovada" <?= $filtro_status === 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
                    <option value="rejeitada" <?= $filtro_status === 'rejeitada' ? 'selected' : '' ?>>Rejeitada</option>
                    <option value="paga" <?= $filtro_status === 'paga' ? 'selected' : '' ?>>Paga</option>
                </select>
                <input type="month" name="mes" value="<?= htmlspecialchars($filtro_mes) ?>" class="form-control form-control-solid w-200px" />
                <select name="colaborador_id" class="form-select form-select-solid w-250px">
                    <option value="">Todos os colaboradores</option>
                    <?php foreach ($colaboradores_pj as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filtro_colab == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="admin_solicitacoes_pagamento_pj.php" class="btn btn-light">Limpar</a>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($solicitacoes)): ?>
                <div class="text-center py-10 text-muted">Nenhuma solicitação encontrada</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Colaborador</th>
                            <th>Empresa</th>
                            <th>Mês Ref.</th>
                            <th>Horas</th>
                            <th>Valor/h</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Enviada</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($solicitacoes as $s):
                        $badges = [
                            'enviada' => '<span class="badge badge-light-info">Enviada</span>',
                            'em_analise' => '<span class="badge badge-light-warning">Em Análise</span>',
                            'aprovada' => '<span class="badge badge-light-success">Aprovada</span>',
                            'rejeitada' => '<span class="badge badge-light-danger">Rejeitada</span>',
                            'paga' => '<span class="badge badge-success">Paga</span>',
                        ];
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['colaborador_nome']) ?></strong></td>
                            <td><small><?= htmlspecialchars($s['empresa_nome'] ?? '-') ?></small></td>
                            <td><?= date('m/Y', strtotime($s['mes_referencia'].'-01')) ?></td>
                            <td><?= number_format($s['total_horas'], 2, ',', '.') ?>h</td>
                            <td>R$ <?= number_format($s['valor_hora_aplicado'], 2, ',', '.') ?></td>
                            <td><strong class="text-success">R$ <?= number_format($s['valor_total'], 2, ',', '.') ?></strong></td>
                            <td><?= $badges[$s['status']] ?? $s['status'] ?></td>
                            <td><small><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></small></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-light-info" onclick="verDetalhesAdmin(<?= $s['id'] ?>)">
                                    <i class="ki-duotone ki-eye fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i> Detalhes
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

<!-- Modal Detalhes Admin -->
<div class="modal fade" id="kt_modal_admin_solicitacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalhes da Solicitação</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conteudo_admin_solicitacao">
                <div class="text-center py-10"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
var __solicitacaoAtual = null;

function verDetalhesAdmin(id) {
    __solicitacaoAtual = id;
    var conteudo = document.getElementById('conteudo_admin_solicitacao');
    conteudo.innerHTML = '<div class="text-center py-10"><div class="spinner-border text-primary"></div></div>';
    var modal = new bootstrap.Modal(document.getElementById('kt_modal_admin_solicitacao'));
    modal.show();

    fetch('../api/acao_solicitacao_pagamento_pj.php?acao=get_detalhes&solicitacao_id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                conteudo.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Erro') + '</div>';
                return;
            }
            renderAdmin(data.data);
        });
}

function renderAdmin(d) {
    var s = d.solicitacao;
    var linhas = d.linhas;
    var log = d.log;
    var html = '';

    html += '<div class="row mb-5">';
    html += '<div class="col-md-3"><strong>Colaborador:</strong><br>' + s.colaborador_nome + '</div>';
    html += '<div class="col-md-3"><strong>Mês:</strong><br>' + s.mes_referencia + '</div>';
    html += '<div class="col-md-3"><strong>Total Horas:</strong><br>' + parseFloat(s.total_horas).toFixed(2).replace('.',',') + 'h</div>';
    html += '<div class="col-md-3"><strong>Valor Total:</strong><br><span class="text-success fw-bold fs-4">R$ ' + parseFloat(s.valor_total).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</span></div>';
    html += '</div>';

    html += '<h4>Anexos</h4><div class="row mb-5">';
    html += '<div class="col-md-4"><a href="../' + s.planilha_anexo + '" target="_blank" class="btn btn-light-primary w-100"><i class="ki-duotone ki-file fs-2"><span class="path1"></span><span class="path2"></span></i> Planilha CSV</a></div>';
    html += '<div class="col-md-4"><a href="../' + s.nfe_anexo + '" target="_blank" class="btn btn-light-primary w-100"><i class="ki-duotone ki-file fs-2"><span class="path1"></span><span class="path2"></span></i> NFe' + (s.nfe_numero ? ' #' + s.nfe_numero : '') + '</a></div>';
    html += '<div class="col-md-4"><a href="../' + s.boleto_anexo + '" target="_blank" class="btn btn-light-primary w-100"><i class="ki-duotone ki-file fs-2"><span class="path1"></span><span class="path2"></span></i> Boleto</a></div>';
    html += '</div>';

    html += '<h4>Horas Trabalhadas (' + linhas.length + ' linhas)</h4>';
    html += '<div class="table-responsive mb-5"><table class="table table-row-bordered table-row-dashed gy-4"><thead><tr class="fw-bold"><th>Data</th><th>Início</th><th>Fim</th><th>Pausa</th><th>Horas</th><th>Projeto</th><th>Descrição</th></tr></thead><tbody>';
    linhas.forEach(function(l) {
        var dt = new Date(l.data_trabalho + 'T00:00:00').toLocaleDateString('pt-BR');
        html += '<tr><td>' + dt + '</td><td>' + (l.hora_inicio || '-') + '</td><td>' + (l.hora_fim || '-') + '</td><td>' + (l.pausa_minutos || 0) + 'min</td><td><strong>' + parseFloat(l.horas_trabalhadas).toFixed(2).replace('.',',') + 'h</strong></td><td>' + (l.projeto || '-') + '</td><td>' + (l.descricao || '-') + '</td></tr>';
    });
    html += '</tbody></table></div>';

    if (s.observacoes_colaborador) {
        html += '<div class="alert alert-light"><strong>Observações do colaborador:</strong><br>' + s.observacoes_colaborador + '</div>';
    }

    // Histórico
    if (log && log.length) {
        html += '<h4>Histórico</h4><div class="timeline mb-5">';
        log.forEach(function(l) {
            var dt = new Date(l.created_at.replace(' ','T')).toLocaleString('pt-BR');
            html += '<div class="border-start border-2 ps-3 mb-2"><strong>' + l.acao + '</strong> <small class="text-muted">' + dt + (l.usuario_nome ? ' por ' + l.usuario_nome : '') + '</small><br><small>' + (l.detalhes || '') + '</small></div>';
        });
        html += '</div>';
    }

    // Ações (apenas se status permitir)
    if (s.status === 'enviada' || s.status === 'em_analise') {
        html += '<div class="separator my-5"></div>';
        html += '<h4>Ações</h4>';
        html += '<div class="mb-3"><label class="form-label">Observações (opcional)</label><textarea id="obs_admin_acao" class="form-control form-control-solid" rows="2"></textarea></div>';
        html += '<div class="form-check form-check-custom mb-3"><input class="form-check-input" type="checkbox" id="gerar_fechamento" checked /><label class="form-check-label ms-2" for="gerar_fechamento">Gerar fechamento de pagamento automaticamente ao aprovar</label></div>';
        html += '<div class="d-flex gap-2">';
        html += '<button class="btn btn-success" onclick="aprovarSolicitacao()"><i class="ki-duotone ki-check fs-2"><span class="path1"></span><span class="path2"></span></i> Aprovar</button>';
        html += '<button class="btn btn-danger" onclick="rejeitarSolicitacao()"><i class="ki-duotone ki-cross fs-2"><span class="path1"></span><span class="path2"></span></i> Rejeitar</button>';
        html += '</div>';
    } else if (s.status === 'aprovada') {
        html += '<div class="separator my-5"></div>';
        html += '<button class="btn btn-success" onclick="marcarPaga()"><i class="ki-duotone ki-check-circle fs-2"><span class="path1"></span><span class="path2"></span></i> Marcar como Paga</button>';
    }

    document.getElementById('conteudo_admin_solicitacao').innerHTML = html;
}

function aprovarSolicitacao() {
    var fd = new FormData();
    fd.append('acao', 'aprovar');
    fd.append('solicitacao_id', __solicitacaoAtual);
    fd.append('observacoes_admin', document.getElementById('obs_admin_acao').value);
    if (document.getElementById('gerar_fechamento').checked) fd.append('gerar_fechamento', '1');

    fetch('../api/acao_solicitacao_pagamento_pj.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Aprovada!', text:d.message }).then(function(){ location.reload(); });
            } else {
                Swal.fire({ icon:'error', title:'Erro', text:d.message });
            }
        });
}

function rejeitarSolicitacao() {
    Swal.fire({
        title: 'Motivo da rejeição',
        input: 'textarea',
        inputPlaceholder: 'Informe o motivo...',
        showCancelButton: true,
        confirmButtonText: 'Rejeitar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (!result.value) return;
        var fd = new FormData();
        fd.append('acao', 'rejeitar');
        fd.append('solicitacao_id', __solicitacaoAtual);
        fd.append('motivo', result.value);
        fetch('../api/acao_solicitacao_pagamento_pj.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    Swal.fire({ icon:'success', title:'Rejeitada', text:d.message }).then(function(){ location.reload(); });
                } else {
                    Swal.fire({ icon:'error', title:'Erro', text:d.message });
                }
            });
    });
}

function marcarPaga() {
    Swal.fire({
        title: 'Marcar como paga?',
        showCancelButton: true,
        confirmButtonText: 'Sim, marcar como paga'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        var fd = new FormData();
        fd.append('acao', 'marcar_paga');
        fd.append('solicitacao_id', __solicitacaoAtual);
        fetch('../api/acao_solicitacao_pagamento_pj.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    Swal.fire({ icon:'success', title:'OK', text:d.message }).then(function(){ location.reload(); });
                } else {
                    Swal.fire({ icon:'error', title:'Erro', text:d.message });
                }
            });
    });
}

// Auto-abre se ?view=ID
<?php if (!empty($_GET['view'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    verDetalhesAdmin(<?= (int)$_GET['view'] ?>);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
