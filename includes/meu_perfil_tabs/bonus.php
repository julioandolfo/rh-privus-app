<?php
/**
 * Tab: Bônus - Meu Perfil
 */
?>

<div class="card">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1">Meus Bônus Ativos</span>
            <span class="text-muted fw-semibold fs-7">Bônus e benefícios ativos no momento</span>
        </h3>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($bonus_colaborador)): ?>
            <div class="text-center py-10">
                <i class="ki-duotone ki-gift fs-5x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="fw-bold fs-4 text-gray-700">Nenhum bônus ativo</div>
                <div class="text-muted">Você não possui bônus cadastrados no momento</div>
            </div>
        <?php else: ?>
            <div class="row g-5">
                <?php foreach ($bonus_colaborador as $bonus): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border border-dashed border-gray-300 hover-elevate-up">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-5">
                                <div class="symbol symbol-50px me-3">
                                    <span class="symbol-label bg-light-warning">
                                        <i class="ki-duotone ki-star fs-2x text-warning">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-gray-900 fs-5"><?= htmlspecialchars($bonus['tipo_bonus_nome']) ?></div>
                                    <?php if (!empty($bonus['tipo_bonus_descricao'])): ?>
                                        <div class="text-muted fs-7"><?= htmlspecialchars($bonus['tipo_bonus_descricao']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($bonus['valor'])): ?>
                            <div class="mb-3">
                                <label class="fw-bold text-gray-700 mb-1">Valor</label>
                                <div class="fs-4 fw-bold text-success">R$ <?= number_format($bonus['valor'], 2, ',', '.') ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($bonus['data_inicio']) || !empty($bonus['data_fim'])): ?>
                            <div class="mb-3">
                                <label class="fw-bold text-gray-700 mb-1">Período</label>
                                <div class="text-gray-800">
                                    <?php if (!empty($bonus['data_inicio'])): ?>
                                        De: <?= date('d/m/Y', strtotime($bonus['data_inicio'])) ?>
                                        <br>
                                    <?php endif; ?>
                                    <?php if (!empty($bonus['data_fim'])): ?>
                                        Até: <?= date('d/m/Y', strtotime($bonus['data_fim'])) ?>
                                    <?php else: ?>
                                        <span class="badge badge-success">Permanente</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($bonus['observacoes'])): ?>
                            <div class="mb-3">
                                <label class="fw-bold text-gray-700 mb-1">Observações</label>
                                <div class="text-gray-700 fs-7"><?= htmlspecialchars($bonus['observacoes']) ?></div>
                            </div>
                            <?php endif; ?>

                            <div class="text-center mt-5">
                                <span class="badge badge-light-success fs-7">Ativo</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Resumo de Bônus -->
            <div class="row g-5 mt-5">
                <?php
                $total_bonus = count($bonus_colaborador);
                $valor_total_bonus = 0;
                
                foreach ($bonus_colaborador as $bonus) {
                    if (!empty($bonus['valor'])) {
                        $valor_total_bonus += $bonus['valor'];
                    }
                }
                ?>
                
                <div class="col-md-6">
                    <div class="card bg-light-warning">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-star fs-3x text-warning mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-warning"><?= $total_bonus ?></div>
                            <div class="text-gray-700">Total de Bônus Ativos</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card bg-light-success">
                        <div class="card-body text-center">
                            <i class="ki-duotone ki-dollar fs-3x text-success mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-2 text-success">R$ <?= number_format($valor_total_bonus, 2, ',', '.') ?></div>
                            <div class="text-gray-700">Valor Total em Bônus</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
