<?php
/**
 * Solicitar Pagamento PJ - Página do Colaborador
 * Permite enviar mensalmente: planilha de horas + NFe + Boleto
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

if (($usuario['role'] ?? '') !== 'COLABORADOR' || empty($usuario['colaborador_id'])) {
    $_SESSION['error'] = 'Apenas colaboradores podem acessar esta página.';
    header('Location: dashboard.php');
    exit;
}

$colaborador_id = (int)$usuario['colaborador_id'];

// Busca dados do colaborador
$stmt = $pdo->prepare("SELECT id, nome_completo, tipo_contrato, valor_hora FROM colaboradores WHERE id = ?");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch();

// Busca solicitações do colaborador
$stmt = $pdo->prepare("
    SELECT s.*, u.nome as aprovador_nome
    FROM solicitacoes_pagamento_pj s
    LEFT JOIN usuarios u ON s.aprovado_por = u.id
    WHERE s.colaborador_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$colaborador_id]);
$solicitacoes = $stmt->fetchAll();

$page_title = 'Solicitar Pagamento';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="toolbar mb-5">
    <div class="container-fluid d-flex flex-stack">
        <div class="page-title">
            <h1 class="text-gray-900 fw-bold fs-3 mb-0">Solicitar Pagamento</h1>
            <span class="text-muted fs-7">Envie mensalmente sua planilha de horas, NFe e boleto</span>
        </div>
        <div>
            <a href="controle_horas_online.php" class="btn btn-light-info me-2">
                <i class="ki-duotone ki-laptop fs-2"><span class="path1"></span><span class="path2"></span></i>
                Usar Modelo Online
            </a>
            <a href="../api/baixar_modelo_pagamento_pj.php" class="btn btn-light-primary me-2">
                <i class="ki-duotone ki-file-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                Baixar Planilha Modelo
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_nova_solicitacao">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Solicitação
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php if ($colaborador['valor_hora']): ?>
    <div class="alert alert-info d-flex align-items-center mb-5">
        <i class="ki-duotone ki-information-5 fs-2x me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
        <div>
            Seu valor da hora cadastrado: <strong>R$ <?= number_format($colaborador['valor_hora'], 2, ',', '.') ?></strong>
            <br><small>Você pode editar o valor no momento da solicitação se necessário.</small>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning d-flex align-items-center mb-5">
        <i class="ki-duotone ki-information-5 fs-2x me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
        <div>
            Você ainda não tem um valor da hora cadastrado. Você poderá informar o valor manualmente em cada solicitação, ou pedir ao admin para cadastrar.
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Minhas Solicitações</h3></div>
        <div class="card-body">
            <?php if (empty($solicitacoes)): ?>
                <div class="text-center py-10 text-muted">
                    <i class="ki-duotone ki-document fs-3x mb-3"><span class="path1"></span><span class="path2"></span></i>
                    <p>Nenhuma solicitação enviada ainda.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Mês Ref.</th>
                            <th>Total Horas</th>
                            <th>Valor/Hora</th>
                            <th>Valor Total</th>
                            <th>Status</th>
                            <th>Enviada em</th>
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
                            <td><strong><?= htmlspecialchars(date('m/Y', strtotime($s['mes_referencia'].'-01'))) ?></strong></td>
                            <td><?= number_format($s['total_horas'], 2, ',', '.') ?>h</td>
                            <td>R$ <?= number_format($s['valor_hora_aplicado'], 2, ',', '.') ?></td>
                            <td><strong class="text-success">R$ <?= number_format($s['valor_total'], 2, ',', '.') ?></strong></td>
                            <td><?= $badges[$s['status']] ?? $s['status'] ?>
                                <?php if ($s['status'] === 'rejeitada' && $s['motivo_rejeicao']): ?>
                                    <br><small class="text-danger"><?= htmlspecialchars($s['motivo_rejeicao']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></small></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-light-info" onclick="verDetalhesSolicitacao(<?= $s['id'] ?>)">
                                    <i class="ki-duotone ki-eye fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
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

<!-- Modal Nova Solicitação -->
<div class="modal fade" id="kt_modal_nova_solicitacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nova Solicitação de Pagamento</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form_solicitacao_pj" enctype="multipart/form-data">
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label required">Mês de Referência</label>
                            <input type="month" name="mes_referencia" id="mes_referencia" class="form-control form-control-solid" required value="<?= date('Y-m') ?>" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Valor da Hora (R$)</label>
                            <input type="text" name="valor_hora" id="valor_hora" class="form-control form-control-solid" required value="<?= $colaborador['valor_hora'] ? number_format($colaborador['valor_hora'], 2, ',', '.') : '' ?>" placeholder="0,00" />
                            <small class="text-muted">Você pode editar este valor</small>
                        </div>
                    </div>

                    <div class="separator my-5"></div>

                    <h4>1. Planilha de Horas Trabalhadas</h4>
                    <p class="text-muted fs-7">
                        <a href="../api/baixar_modelo_pagamento_pj.php"><i class="ki-duotone ki-file-down fs-5"></i> Baixar modelo (XLSX)</a> — preencha no Excel/Google Sheets, depois exporte como <strong>CSV</strong> (Arquivo → Salvar como → CSV) e envie aqui.
                    </p>
                    <div class="mb-5">
                        <input type="file" name="planilha" id="planilha" class="form-control form-control-solid" accept=".csv" required />
                        <button type="button" class="btn btn-sm btn-light-primary mt-2" onclick="validarPlanilha()">
                            <i class="ki-duotone ki-check fs-5"><span class="path1"></span><span class="path2"></span></i>
                            Validar Planilha
                        </button>
                        <div id="resultado_validacao" class="mt-3"></div>
                    </div>

                    <div class="separator my-5"></div>

                    <h4>2. Nota Fiscal Eletrônica (NFe)</h4>
                    <div class="row mb-5">
                        <div class="col-md-12 mb-3">
                            <label class="form-label required">Arquivo PDF da NFe</label>
                            <input type="file" name="nfe" class="form-control form-control-solid" accept=".pdf" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número da NFe (opcional)</label>
                            <input type="text" name="nfe_numero" class="form-control form-control-solid" placeholder="Ex: 12345" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valor da NFe (opcional)</label>
                            <input type="text" name="nfe_valor" class="form-control form-control-solid" placeholder="0,00" />
                        </div>
                    </div>

                    <div class="separator my-5"></div>

                    <h4>3. Boleto para Recebimento</h4>
                    <div class="mb-5">
                        <label class="form-label required">Arquivo PDF do Boleto</label>
                        <input type="file" name="boleto" class="form-control form-control-solid" accept=".pdf" required />
                    </div>

                    <div class="separator my-5"></div>

                    <div class="mb-5">
                        <label class="form-label">Observações (opcional)</label>
                        <textarea name="observacoes" class="form-control form-control-solid" rows="3" placeholder="Informações adicionais..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_enviar_solicitacao" onclick="enviarSolicitacao()">
                    <span class="indicator-label">Enviar Solicitação</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Detalhes -->
<div class="modal fade" id="kt_modal_ver_solicitacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalhes da Solicitação</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conteudo_ver_solicitacao">
                <div class="text-center py-10"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
function validarPlanilha() {
    var fileInput = document.getElementById('planilha');
    var mesRef = document.getElementById('mes_referencia').value;
    var resultado = document.getElementById('resultado_validacao');

    if (!fileInput.files.length) {
        resultado.innerHTML = '<div class="alert alert-warning">Selecione um arquivo primeiro</div>';
        return;
    }
    if (!mesRef) {
        resultado.innerHTML = '<div class="alert alert-warning">Informe o mês de referência</div>';
        return;
    }

    resultado.innerHTML = '<div class="text-muted"><div class="spinner-border spinner-border-sm"></div> Validando...</div>';

    var fd = new FormData();
    fd.append('planilha', fileInput.files[0]);
    fd.append('mes_referencia', mesRef);

    fetch('../api/validar_planilha_pagamento_pj.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                resultado.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Erro ao validar') + '</div>';
                return;
            }
            var d = data.data;
            var html = '';
            if (d.valido) {
                html += '<div class="alert alert-success">';
                html += '<strong>✓ Planilha válida!</strong><br>';
                html += 'Total de linhas: <strong>' + d.total_linhas + '</strong><br>';
                html += 'Total de horas: <strong>' + parseFloat(d.total_horas).toFixed(2).replace('.',',') + 'h</strong>';
                html += '</div>';
            } else {
                html += '<div class="alert alert-danger"><strong>✗ Planilha inválida</strong><ul class="mb-0 mt-2">';
                d.erros.forEach(function(e) { html += '<li>' + e + '</li>'; });
                html += '</ul></div>';
            }
            if (d.avisos && d.avisos.length > 0) {
                html += '<div class="alert alert-warning"><strong>Avisos:</strong><ul class="mb-0 mt-2">';
                d.avisos.forEach(function(a) { html += '<li>' + a + '</li>'; });
                html += '</ul></div>';
            }
            resultado.innerHTML = html;
        })
        .catch(function() {
            resultado.innerHTML = '<div class="alert alert-danger">Erro de conexão</div>';
        });
}

function enviarSolicitacao() {
    var form = document.getElementById('form_solicitacao_pj');
    var btn = document.getElementById('btn_enviar_solicitacao');
    var fd = new FormData(form);

    btn.disabled = true;
    btn.querySelector('.indicator-label').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

    fetch('../api/criar_solicitacao_pagamento_pj.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.querySelector('.indicator-label').textContent = 'Enviar Solicitação';
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Solicitação Enviada!',
                    text: data.message,
                    confirmButtonText: 'OK'
                }).then(function() { location.reload(); });
            } else {
                var msg = data.message || 'Erro ao enviar';
                if (data.erros && data.erros.length) {
                    msg += '\n\n' + data.erros.join('\n');
                }
                Swal.fire({ icon: 'error', title: 'Erro', text: msg });
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.querySelector('.indicator-label').textContent = 'Enviar Solicitação';
            Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro de conexão' });
        });
}

function verDetalhesSolicitacao(id) {
    var conteudo = document.getElementById('conteudo_ver_solicitacao');
    conteudo.innerHTML = '<div class="text-center py-10"><div class="spinner-border text-primary"></div></div>';
    var modal = new bootstrap.Modal(document.getElementById('kt_modal_ver_solicitacao'));
    modal.show();

    fetch('../api/acao_solicitacao_pagamento_pj.php?acao=get_detalhes&solicitacao_id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                conteudo.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Erro') + '</div>';
                return;
            }
            var s = data.data.solicitacao;
            var linhas = data.data.linhas;
            var html = '';

            html += '<div class="row mb-5">';
            html += '<div class="col-md-4"><strong>Mês:</strong><br>' + s.mes_referencia + '</div>';
            html += '<div class="col-md-4"><strong>Total Horas:</strong><br>' + parseFloat(s.total_horas).toFixed(2).replace('.',',') + 'h</div>';
            html += '<div class="col-md-4"><strong>Valor Total:</strong><br><span class="text-success fw-bold">R$ ' + parseFloat(s.valor_total).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</span></div>';
            html += '</div>';

            html += '<h4>Anexos</h4><div class="row mb-5">';
            html += '<div class="col-md-4"><a href="../' + s.planilha_anexo + '" target="_blank" class="btn btn-light-primary w-100"><i class="ki-duotone ki-file fs-2"><span class="path1"></span><span class="path2"></span></i> Planilha</a></div>';
            html += '<div class="col-md-4"><a href="../' + s.nfe_anexo + '" target="_blank" class="btn btn-light-primary w-100"><i class="ki-duotone ki-file fs-2"><span class="path1"></span><span class="path2"></span></i> NFe</a></div>';
            html += '<div class="col-md-4"><a href="../' + s.boleto_anexo + '" target="_blank" class="btn btn-light-primary w-100"><i class="ki-duotone ki-file fs-2"><span class="path1"></span><span class="path2"></span></i> Boleto</a></div>';
            html += '</div>';

            html += '<h4>Horas Trabalhadas</h4><div class="table-responsive"><table class="table table-row-bordered table-row-dashed gy-4"><thead><tr class="fw-bold"><th>Data</th><th>Início</th><th>Fim</th><th>Pausa</th><th>Horas</th><th>Projeto</th><th>Descrição</th></tr></thead><tbody>';
            linhas.forEach(function(l) {
                var d = new Date(l.data_trabalho + 'T00:00:00').toLocaleDateString('pt-BR');
                html += '<tr><td>' + d + '</td><td>' + (l.hora_inicio || '-') + '</td><td>' + (l.hora_fim || '-') + '</td><td>' + (l.pausa_minutos || 0) + 'min</td><td><strong>' + parseFloat(l.horas_trabalhadas).toFixed(2).replace('.',',') + 'h</strong></td><td>' + (l.projeto || '-') + '</td><td>' + (l.descricao || '-') + '</td></tr>';
            });
            html += '</tbody></table></div>';

            if (s.observacoes_colaborador) {
                html += '<div class="mt-4"><strong>Suas observações:</strong><br>' + s.observacoes_colaborador + '</div>';
            }
            if (s.observacoes_admin) {
                html += '<div class="mt-4"><strong>Observações do admin:</strong><br>' + s.observacoes_admin + '</div>';
            }
            if (s.motivo_rejeicao) {
                html += '<div class="mt-4 alert alert-danger"><strong>Motivo da rejeição:</strong><br>' + s.motivo_rejeicao + '</div>';
            }

            conteudo.innerHTML = html;
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
