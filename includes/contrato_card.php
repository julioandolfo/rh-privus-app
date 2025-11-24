<?php
/**
 * Card de Contrato para Kanban
 */
$status_labels = [
    'rascunho' => ['label' => 'Rascunho', 'class' => 'warning'],
    'enviado' => ['label' => 'Enviado', 'class' => 'info'],
    'aguardando' => ['label' => 'Aguardando', 'class' => 'warning'],
    'assinado' => ['label' => 'Assinado', 'class' => 'success'],
    'cancelado' => ['label' => 'Cancelado', 'class' => 'danger'],
    'expirado' => ['label' => 'Expirado', 'class' => 'secondary']
];
$status_info = $status_labels[$contrato['status']] ?? ['label' => ucfirst($contrato['status']), 'class' => 'secondary'];
?>
<div class="contrato-card" onclick="window.location.href='contrato_view.php?id=<?= $contrato['id'] ?>'">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <h5 class="text-gray-900 fw-bold fs-6 mb-0"><?= htmlspecialchars($contrato['titulo']) ?></h5>
        <span class="badge badge-light-<?= $status_info['class'] ?>"><?= $status_info['label'] ?></span>
    </div>
    <div class="text-muted fs-7 mb-2">
        <strong><?= htmlspecialchars($contrato['colaborador_nome']) ?></strong>
    </div>
    <div class="text-muted fs-7 mb-2">
        Criado: <?= date('d/m/Y', strtotime($contrato['created_at'])) ?>
    </div>
    <?php if ($contrato['assinaturas_pendentes'] > 0): ?>
    <div class="text-warning fs-7">
        <i class="ki-duotone ki-clock fs-6">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
        <?= $contrato['assinaturas_pendentes'] ?> assinatura(s) pendente(s)
    </div>
    <?php endif; ?>
    <?php if ($contrato['total_signatarios'] > 0): ?>
    <div class="text-muted fs-7">
        <i class="ki-duotone ki-user fs-6">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
        <?= $contrato['total_signatarios'] ?> signatário(s)
    </div>
    <?php endif; ?>
</div>

