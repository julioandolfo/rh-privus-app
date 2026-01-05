<?php
/**
 * Tab: Dados Pessoais - Meu Perfil
 */
?>

<div class="row g-5">
    <!-- Informações Profissionais -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-duotone ki-suitcase fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Informações Profissionais
                </h3>
            </div>
            <div class="card-body">
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Empresa</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['empresa_nome'] ?? 'Não informado') ?></div>
                </div>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Cargo</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['nome_cargo'] ?? 'Não informado') ?></div>
                </div>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Setor</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['nome_setor'] ?? 'Não informado') ?></div>
                </div>
                <?php if (!empty($colaborador['nivel_nome'])): ?>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Nível Hierárquico</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['nivel_nome']) ?></div>
                </div>
                <?php endif; ?>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Tipo de Contrato</label>
                    <div class="fs-5">
                        <?php 
                        $tipo_contrato_map = [
                            'PJ' => 'Pessoa Jurídica (PJ)',
                            'CLT' => 'CLT',
                            'Estágio' => 'Estágio',
                            'Terceirizado' => 'Terceirizado'
                        ];
                        echo htmlspecialchars($tipo_contrato_map[$colaborador['tipo_contrato']] ?? $colaborador['tipo_contrato'] ?? 'Não informado');
                        ?>
                    </div>
                </div>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Data de Início</label>
                    <div class="fs-5"><?= $colaborador['data_inicio'] ? date('d/m/Y', strtotime($colaborador['data_inicio'])) : 'Não informado' ?></div>
                </div>
                <div>
                    <label class="fw-bold text-gray-700 mb-1">Status</label>
                    <div>
                        <?php
                        $status_colors = [
                            'ativo' => 'success',
                            'pausado' => 'warning',
                            'desligado' => 'danger'
                        ];
                        $status_labels = [
                            'ativo' => 'Ativo',
                            'pausado' => 'Pausado',
                            'desligado' => 'Desligado'
                        ];
                        $status = $colaborador['status'] ?? 'ativo';
                        ?>
                        <span class="badge badge-<?= $status_colors[$status] ?? 'secondary' ?> fs-6">
                            <?= $status_labels[$status] ?? ucfirst($status) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informações Pessoais -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-duotone ki-profile-circle fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Informações Pessoais
                </h3>
            </div>
            <div class="card-body">
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Nome Completo</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['nome_completo']) ?></div>
                </div>
                <?php if (!empty($colaborador['cpf'])): ?>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">CPF</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['cpf']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($colaborador['rg'])): ?>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">RG</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['rg']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($colaborador['data_nascimento'])): ?>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Data de Nascimento</label>
                    <div class="fs-5"><?= date('d/m/Y', strtotime($colaborador['data_nascimento'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($colaborador['telefone'])): ?>
                <div class="mb-5">
                    <label class="fw-bold text-gray-700 mb-1">Telefone</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['telefone']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($colaborador['email_pessoal'])): ?>
                <div>
                    <label class="fw-bold text-gray-700 mb-1">E-mail Pessoal</label>
                    <div class="fs-5"><?= htmlspecialchars($colaborador['email_pessoal']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Líder Direto (se houver) -->
    <?php if (!empty($colaborador['lider_nome'])): ?>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-duotone ki-profile-user fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    Líder Direto
                </h3>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <?php if (!empty($colaborador['lider_foto'])): ?>
                        <img src="../<?= htmlspecialchars($colaborador['lider_foto']) ?>" alt="Líder" class="w-60px h-60px rounded me-3" style="object-fit: cover;" />
                    <?php else: ?>
                        <div class="symbol symbol-60px me-3">
                            <div class="symbol-label fs-2 fw-bold bg-light-primary text-primary">
                                <?= strtoupper(substr($colaborador['lider_nome'], 0, 1)) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-bold fs-4"><?= htmlspecialchars($colaborador['lider_nome']) ?></div>
                        <div class="text-muted">Seu líder direto</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Endereço (se houver) -->
    <?php if (!empty($colaborador['cep']) || !empty($colaborador['logradouro'])): ?>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-duotone ki-geolocation fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Endereço
                </h3>
            </div>
            <div class="card-body">
                <?php if (!empty($colaborador['logradouro'])): ?>
                <div class="mb-3">
                    <label class="fw-bold text-gray-700 mb-1">Logradouro</label>
                    <div><?= htmlspecialchars($colaborador['logradouro']) ?><?php if (!empty($colaborador['numero'])): ?>, <?= htmlspecialchars($colaborador['numero']) ?><?php endif; ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($colaborador['complemento'])): ?>
                <div class="mb-3">
                    <label class="fw-bold text-gray-700 mb-1">Complemento</label>
                    <div><?= htmlspecialchars($colaborador['complemento']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($colaborador['bairro'])): ?>
                <div class="mb-3">
                    <label class="fw-bold text-gray-700 mb-1">Bairro</label>
                    <div><?= htmlspecialchars($colaborador['bairro']) ?></div>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="fw-bold text-gray-700 mb-1">Cidade/Estado</label>
                    <div>
                        <?= htmlspecialchars($colaborador['cidade_endereco'] ?? '') ?>
                        <?php if (!empty($colaborador['estado_endereco'])): ?>
                            - <?= htmlspecialchars($colaborador['estado_endereco']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($colaborador['cep'])): ?>
                <div>
                    <label class="fw-bold text-gray-700 mb-1">CEP</label>
                    <div><?= htmlspecialchars($colaborador['cep']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
