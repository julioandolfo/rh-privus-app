<?php
/**
 * Tab: Horas adicionais (prestação) - Meu Perfil
 */
require_once __DIR__ . '/../horas_extras_ui.php';

$resumido_prestador = colaborador_ocorrencias_flags_sem_detalhe();
?>

<div class="card">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1">Horas adicionais da prestação</span>
            <span class="text-muted fw-semibold fs-7"><?= $resumido_prestador ? hx_ui_contexto_prestador() : 'Registros de horas adicionais vinculados à sua prestação de serviço' ?></span>
        </h3>
    </div>
    <div class="card-body pt-0">
        <?php if ($resumido_prestador): ?>
            <div class="alert alert-primary d-flex align-items-start p-5 mb-6">
                <i class="ki-duotone ki-information-5 fs-2hx text-primary me-4 mt-1">
                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                </i>
                <div>
                    <h4 class="mb-2 text-gray-900">Como funciona</h4>
                    <p class="mb-0 text-gray-700"><?= htmlspecialchars(hx_ui_consulte_gestor_valores()) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($horas_extras_colaborador)): ?>
            <div class="text-center py-10">
                <i class="ki-duotone ki-chart-simple fs-5x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="fw-bold fs-4 text-gray-700">Nenhuma hora adicional registrada</div>
                <div class="text-muted mt-2">Se precisar registrar tempo adicional, use <strong>Solicitar horas adicionais</strong> no menu.</div>
            </div>
        <?php elseif ($resumido_prestador): ?>
            <div class="d-flex flex-column gap-4">
                <?php foreach ($horas_extras_colaborador as $he): ?>
                <?php
                $is_remocao = $he['quantidade_horas'] < 0;
                ?>
                <div class="card card-flush border border-dashed border-gray-300 border-start border-4 border-primary">
                    <div class="card-body py-5 px-5">
                        <div class="fw-bold text-gray-900 fs-5 mb-1">
                            <?= $is_remocao ? 'Ajuste de horas' : 'Hora adicional registrada' ?>
                        </div>
                        <div class="text-gray-700 mb-2">
                            Data de referência: <strong><?= date('d/m/Y', strtotime($he['data_trabalho'])) ?></strong>
                            <?php if (!$is_remocao): ?>
                            · Quantidade: <strong><?= number_format($he['quantidade_horas'], 2, ',', '.') ?>h</strong>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted fs-7 mb-0"><?= htmlspecialchars(hx_ui_consulte_gestor_valores()) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-125px">Data</th>
                            <th class="min-w-100px">Quantidade</th>
                            <th class="min-w-125px">Compensação</th>
                            <th class="min-w-100px">Valor/Hora</th>
                            <th class="min-w-100px">Adicional %</th>
                            <th class="min-w-125px">Total (R$)</th>
                            <th class="min-w-200px">Observações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($horas_extras_colaborador as $he): ?>
                        <?php
                        $tipo_pagamento = $he['tipo_pagamento'] ?? 'dinheiro';
                        $is_remocao = $he['quantidade_horas'] < 0;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= date('d/m/Y', strtotime($he['data_trabalho'])) ?></div>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($he['created_at'])) ?></small>
                            </td>
                            <td>
                                <?php if ($is_remocao): ?>
                                    <span class="badge badge-danger fs-6"><?= number_format($he['quantidade_horas'], 2, ',', '.') ?>h</span>
                                <?php else: ?>
                                    <span class="badge badge-success fs-6">+<?= number_format($he['quantidade_horas'], 2, ',', '.') ?>h</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tipo_pagamento === 'banco_horas'): ?>
                                    <span class="badge badge-info">Saldo de horas</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">Valor (R$)</span>
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
                            <td>
                                <?php if (!empty($he['observacoes'])): ?>
                                    <span title="<?= htmlspecialchars($he['observacoes']) ?>">
                                        <?= htmlspecialchars(mb_substr($he['observacoes'], 0, 50)) ?><?= mb_strlen($he['observacoes']) > 50 ? '...' : '' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row g-5 mt-5">
                <?php
                $total_horas = 0;
                $total_valor = 0;
                $total_banco = 0;
                
                foreach ($horas_extras_colaborador as $he) {
                    if ($he['tipo_pagamento'] === 'banco_horas') {
                        $total_banco += $he['quantidade_horas'];
                    } else {
                        $total_valor += $he['valor_total'];
                    }
                    $total_horas += $he['quantidade_horas'];
                }
                ?>
                
                <div class="col-md-4">
                    <div class="card bg-light-primary">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-time fs-3x text-primary mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-primary"><?= number_format($total_horas, 2, ',', '.') ?>h</div>
                            <div class="text-gray-700">Total de horas (referência)</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card bg-light-success">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-dollar fs-3x text-success mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-success">R$ <?= number_format($total_valor, 2, ',', '.') ?></div>
                            <div class="text-gray-700">Total em valor (R$)</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card bg-light-info">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-time fs-3x text-info mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-info"><?= number_format($total_banco, 2, ',', '.') ?>h</div>
                            <div class="text-gray-700">Total em saldo de horas</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
