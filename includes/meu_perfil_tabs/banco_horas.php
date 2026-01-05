<?php
/**
 * Tab: Banco de Horas - Meu Perfil
 */
?>

<div class="row mb-7">
    <!-- Card de Saldo Atual -->
    <div class="col-md-12">
        <div class="card card-flush mb-5">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Saldo Atual</span>
                    <span class="text-muted fw-semibold fs-7">Saldo de horas disponível no banco</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <?php
                $saldo_total = ($saldo_banco_horas['saldo_horas'] ?? 0) + (($saldo_banco_horas['saldo_minutos'] ?? 0) / 60);
                $cor_saldo = $saldo_total >= 0 ? 'success' : 'danger';
                $icone_saldo = $saldo_total >= 0 ? 'arrow-up' : 'arrow-down';
                ?>
                
                <div class="text-center py-10">
                    <i class="ki-duotone ki-time fs-5x text-<?= $cor_saldo ?> mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="fw-bold fs-1 text-<?= $cor_saldo ?> mb-3">
                        <?= number_format(abs($saldo_total), 2, ',', '.') ?>h
                    </div>
                    <div class="text-muted fs-5">
                        <?php if ($saldo_total > 0): ?>
                            Você possui horas positivas no banco
                        <?php elseif ($saldo_total < 0): ?>
                            Você está devendo horas no banco
                        <?php else: ?>
                            Seu saldo está zerado
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Histórico de Movimentações -->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Histórico de Movimentações</span>
                    <span class="text-muted fw-semibold fs-7">Últimas movimentações do banco de horas</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($historico_banco_horas)): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-information-5 fs-5x text-muted mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="fw-bold fs-4 text-gray-700">Nenhuma movimentação registrada</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-100px">Data</th>
                                    <th class="min-w-100px">Tipo</th>
                                    <th class="min-w-150px">Origem</th>
                                    <th class="min-w-100px">Quantidade</th>
                                    <th class="min-w-100px">Saldo Anterior</th>
                                    <th class="min-w-100px">Saldo Posterior</th>
                                    <th class="min-w-200px">Motivo</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600">
                                <?php foreach ($historico_banco_horas as $mov): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($mov['data_movimentacao'])) ?></td>
                                    <td>
                                        <?php if ($mov['tipo'] === 'credito'): ?>
                                            <span class="badge badge-success">Crédito</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Débito</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $origem_labels = [
                                            'hora_extra' => 'Hora Extra',
                                            'ocorrencia' => 'Ocorrência',
                                            'ajuste_manual' => 'Ajuste Manual',
                                            'remocao_manual' => 'Remoção Manual',
                                            'estorno_hora_extra' => 'Estorno de Hora Extra',
                                            'estorno_remocao' => 'Estorno de Remoção'
                                        ];
                                        echo htmlspecialchars($origem_labels[$mov['origem']] ?? ucfirst($mov['origem']));
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($mov['tipo'] === 'credito'): ?>
                                            <span class="text-success fw-bold">+<?= number_format($mov['quantidade_horas'], 2, ',', '.') ?>h</span>
                                        <?php else: ?>
                                            <span class="text-danger fw-bold">-<?= number_format($mov['quantidade_horas'], 2, ',', '.') ?>h</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($mov['saldo_anterior'], 2, ',', '.') ?>h</td>
                                    <td>
                                        <?php 
                                        $saldo_posterior = $mov['saldo_posterior'];
                                        $cor_posterior = $saldo_posterior >= 0 ? 'success' : 'danger';
                                        ?>
                                        <span class="text-<?= $cor_posterior ?> fw-bold">
                                            <?= number_format($saldo_posterior, 2, ',', '.') ?>h
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-gray-800" title="<?= htmlspecialchars($mov['motivo']) ?>">
                                            <?= htmlspecialchars(mb_substr($mov['motivo'], 0, 50)) ?><?= mb_strlen($mov['motivo']) > 50 ? '...' : '' ?>
                                        </div>
                                        <?php if (!empty($mov['observacoes'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars(mb_substr($mov['observacoes'], 0, 30)) ?><?= mb_strlen($mov['observacoes']) > 30 ? '...' : '' ?></small>
                                        <?php endif; ?>
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
