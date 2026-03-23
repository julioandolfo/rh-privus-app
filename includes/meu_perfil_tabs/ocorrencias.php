<?php
/**
 * Tab: Ocorrências / Avisos - Meu Perfil
 */
$modo_aviso = !empty($ocorrencias_modo_aviso);
?>

<div class="card">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1"><?= $modo_aviso ? 'Avisos' : 'Minhas Ocorrências' ?></span>
            <span class="text-muted fw-semibold fs-7"><?= $modo_aviso ? 'Informações administrativas — fale com seu gestor' : 'Histórico de ocorrências registradas' ?></span>
        </h3>
    </div>
    <div class="card-body pt-0">
        <?php if ($modo_aviso): ?>
            <div class="alert alert-primary d-flex align-items-center p-5 mb-5">
                <i class="ki-duotone ki-notification-bing fs-2hx text-primary me-4">
                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-gray-900">Política de comunicação</h4>
                    <span class="text-gray-700">Os detalhes dos registros não são exibidos aqui. <strong>Procure seu gestor direto</strong> para entender o contexto.</span>
                </div>
            </div>
            <?php if (empty($ocorrencias)): ?>
            <div class="text-center py-10">
                <i class="ki-duotone ki-check-circle fs-5x text-success mb-5">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <div class="fw-bold fs-4 text-gray-700">Nenhum aviso no momento</div>
            </div>
            <?php else: ?>
            <div class="d-flex flex-column gap-4">
                <?php foreach ($ocorrencias as $oc): ?>
                <div class="card card-flush border border-dashed border-gray-300">
                    <div class="card-body d-flex align-items-center py-5 px-5">
                        <div class="symbol symbol-45px me-4">
                            <div class="symbol-label bg-light-primary">
                                <i class="ki-duotone ki-notification-on fs-2 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            </div>
                        </div>
                        <div>
                            <div class="fw-bold text-gray-900">Registro administrativo</div>
                            <div class="text-muted fs-7">Referência: <?= htmlspecialchars(formatar_data($oc['data_ocorrencia'])) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php elseif (empty($ocorrencias)): ?>
            <div class="text-center py-10">
                <i class="ki-duotone ki-shield-tick fs-5x text-success mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="fw-bold fs-4 text-gray-700">Nenhuma ocorrência registrada</div>
                <div class="text-muted">Você não possui ocorrências no sistema</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-125px">Data</th>
                            <th class="min-w-150px">Tipo</th>
                            <th class="min-w-100px">Severidade</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-250px">Descrição</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($ocorrencias as $oc): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= date('d/m/Y', strtotime($oc['data_ocorrencia'])) ?></div>
                                <?php if (!empty($oc['hora_ocorrencia'])): ?>
                                    <small class="text-muted"><?= date('H:i', strtotime($oc['hora_ocorrencia'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($oc['tipo_nome'])): ?>
                                    <span class="badge badge-light"><?= htmlspecialchars($oc['tipo_nome']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-light"><?= htmlspecialchars($oc['tipo']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $severidade = $oc['severidade'] ?? 'moderada';
                                $severidade_colors = [
                                    'leve' => 'info',
                                    'moderada' => 'warning',
                                    'grave' => 'danger',
                                    'critica' => 'dark'
                                ];
                                $severidade_labels = [
                                    'leve' => 'Leve',
                                    'moderada' => 'Moderada',
                                    'grave' => 'Grave',
                                    'critica' => 'Crítica'
                                ];
                                ?>
                                <span class="badge badge-<?= $severidade_colors[$severidade] ?? 'secondary' ?>">
                                    <?= $severidade_labels[$severidade] ?? ucfirst($severidade) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $status_aprovacao = $oc['status_aprovacao'] ?? 'pendente';
                                $status_colors = [
                                    'pendente' => 'warning',
                                    'aprovada' => 'success',
                                    'rejeitada' => 'danger'
                                ];
                                $status_labels = [
                                    'pendente' => 'Pendente',
                                    'aprovada' => 'Aprovada',
                                    'rejeitada' => 'Rejeitada'
                                ];
                                ?>
                                <span class="badge badge-<?= $status_colors[$status_aprovacao] ?? 'secondary' ?>">
                                    <?= $status_labels[$status_aprovacao] ?? ucfirst($status_aprovacao) ?>
                                </span>
                            </td>
                            <td>
                                <div title="<?= htmlspecialchars($oc['descricao'] ?? '') ?>">
                                    <?= htmlspecialchars(mb_substr($oc['descricao'] ?? '', 0, 60)) ?><?= mb_strlen($oc['descricao'] ?? '') > 60 ? '...' : '' ?>
                                </div>
                                
                                <?php if (!empty($oc['tempo_atraso_minutos'])): ?>
                                    <div class="mt-1">
                                        <span class="badge badge-light-danger">
                                            <?= $oc['tempo_atraso_minutos'] ?> minutos de atraso
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($oc['valor_desconto']) && $oc['valor_desconto'] > 0): ?>
                                    <div class="mt-1">
                                        <span class="badge badge-light-warning">
                                            Desconto: R$ <?= number_format($oc['valor_desconto'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($oc['desconta_banco_horas']) && $oc['desconta_banco_horas'] == 1): ?>
                                    <div class="mt-1">
                                        <span class="badge badge-light-info">
                                            Descontado do banco de horas
                                            <?php if (!empty($oc['horas_descontadas'])): ?>
                                                (<?= number_format($oc['horas_descontadas'], 2, ',', '.') ?>h)
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Resumo de Ocorrências -->
            <div class="row g-5 mt-5">
                <?php
                $total_ocorrencias = count($ocorrencias);
                $total_pendentes = 0;
                $total_aprovadas = 0;
                $total_com_desconto = 0;
                
                foreach ($ocorrencias as $oc) {
                    if (($oc['status_aprovacao'] ?? 'pendente') === 'pendente') {
                        $total_pendentes++;
                    }
                    if (($oc['status_aprovacao'] ?? 'pendente') === 'aprovada') {
                        $total_aprovadas++;
                    }
                    if (!empty($oc['valor_desconto']) && $oc['valor_desconto'] > 0) {
                        $total_com_desconto++;
                    }
                }
                ?>
                
                <div class="col-md-3">
                    <div class="card bg-light-primary">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-clipboard fs-3x text-primary mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-primary"><?= $total_ocorrencias ?></div>
                            <div class="text-gray-700">Total</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-light-warning">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-time fs-3x text-warning mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-warning"><?= $total_pendentes ?></div>
                            <div class="text-gray-700">Pendentes</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-light-success">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-check-circle fs-3x text-success mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-success"><?= $total_aprovadas ?></div>
                            <div class="text-gray-700">Aprovadas</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-light-danger">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-dollar fs-3x text-danger mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-danger"><?= $total_com_desconto ?></div>
                            <div class="text-gray-700">Com Desconto</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
