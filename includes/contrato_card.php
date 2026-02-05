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
<div class="contrato-card">
    <div class="flex-grow-1">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <a href="contrato_view.php?id=<?= $contrato['id'] ?>" class="text-gray-800 text-hover-primary fw-bold fs-6 mb-0 flex-grow-1 me-2">
                <?= htmlspecialchars($contrato['titulo']) ?>
            </a>
            <span class="badge badge-light-<?= $status_info['class'] ?> flex-shrink-0"><?= $status_info['label'] ?></span>
        </div>
        <div class="text-gray-700 fs-7 mb-2">
            <strong><?= htmlspecialchars($contrato['colaborador_nome']) ?></strong>
        </div>
        <div class="text-gray-600 fs-7 mb-2">
            Criado: <?= date('d/m/Y', strtotime($contrato['created_at'])) ?>
        </div>
        <?php if ($contrato['assinaturas_pendentes'] > 0): ?>
        <div class="text-warning fs-7 mb-2">
            <i class="ki-duotone ki-clock fs-6">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <?= $contrato['assinaturas_pendentes'] ?> assinatura(s) pendente(s)
        </div>
        <?php endif; ?>
        <?php if ($contrato['total_signatarios'] > 0): ?>
        <div class="text-gray-600 fs-7 mb-2">
            <i class="ki-duotone ki-user fs-6">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <?= $contrato['total_signatarios'] ?> signatário(s)
        </div>
        <?php endif; ?>
    </div>
    
    <!--begin::Ações Rápidas-->
    <div class="d-flex gap-2 mt-3 pt-3 border-top flex-wrap">
        <?php if (!empty($contrato['pdf_path'])): ?>
        <a href="../<?= htmlspecialchars($contrato['pdf_path']) ?>" 
           target="_blank" 
           class="btn btn-sm btn-light-primary flex-fill"
           onclick="event.stopPropagation();"
           title="Baixar PDF">
            <i class="ki-duotone ki-file-down fs-5">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            PDF
        </a>
        <?php endif; ?>
        
        <?php if ($contrato['status'] === 'rascunho'): ?>
        <a href="contrato_enviar.php?id=<?= $contrato['id'] ?>" 
           class="btn btn-sm btn-light-success flex-fill"
           onclick="event.stopPropagation();"
           title="Enviar para Assinatura">
            <i class="ki-duotone ki-send fs-5">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Enviar
        </a>
        <?php endif; ?>
        
        <a href="contrato_view.php?id=<?= $contrato['id'] ?>" 
           class="btn btn-sm btn-light flex-fill"
           onclick="event.stopPropagation();"
           title="Ver Detalhes">
            <i class="ki-duotone ki-eye fs-5">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            Ver
        </a>
    </div>
    <!--end::Ações Rápidas-->
</div>

