<?php
/**
 * Tab: Horas Extras - Meu Perfil
 */
?>

<div class="card">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1">Minhas Horas Extras</span>
            <span class="text-muted fw-semibold fs-7">Histórico de horas extras trabalhadas</span>
        </h3>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($horas_extras_colaborador)): ?>
            <div class="text-center py-10">
                <i class="ki-duotone ki-chart-simple fs-5x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="fw-bold fs-4 text-gray-700">Nenhuma hora extra registrada</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-125px">Data</th>
                            <th class="min-w-100px">Quantidade</th>
                            <th class="min-w-125px">Tipo de Pagamento</th>
                            <th class="min-w-100px">Valor/Hora</th>
                            <th class="min-w-100px">Adicional</th>
                            <th class="min-w-125px">Total</th>
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
                                    <span class="badge badge-info">Banco de Horas</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">Dinheiro (R$)</span>
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

            <!-- Totalizadores -->
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
                            <div class="text-gray-700">Total de Horas</div>
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
                            <div class="text-gray-700">Total em Dinheiro</div>
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
                            <div class="text-gray-700">Total no Banco</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
