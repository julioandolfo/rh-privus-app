<?php
/**
 * Meus Pagamentos - Página do Colaborador para ver e enviar documentos
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/upload_documento.php';

require_page_permission('meus_pagamentos.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Verifica se o usuário tem colaborador_id (deve ser COLABORADOR)
$colaborador_id = $usuario['colaborador_id'] ?? null;

if (empty($colaborador_id)) {
    // Se não tem colaborador_id, redireciona ou mostra erro
    $_SESSION['error'] = 'Você precisa estar vinculado a um colaborador para acessar esta página.';
    header('Location: dashboard.php');
    exit;
}

// Busca fechamentos fechados do colaborador
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        f.id as fechamento_id,
        f.mes_referencia,
        f.data_fechamento,
        f.status as fechamento_status,
        f.tipo_fechamento,
        f.documento_obrigatorio,
        e.nome_fantasia as empresa_nome,
        i.id as item_id,
        i.valor_total,
        i.salario_base,
        i.valor_horas_extras,
        i.descontos,
        i.adicionais,
        i.documento_anexo,
        i.documento_status,
        i.documento_data_envio,
        i.documento_data_aprovacao,
        i.documento_observacoes,
        u_aprovador.nome as aprovador_nome
    FROM fechamentos_pagamento f
    INNER JOIN fechamentos_pagamento_itens i ON f.id = i.fechamento_id
    LEFT JOIN empresas e ON f.empresa_id = e.id
    LEFT JOIN usuarios u_aprovador ON i.documento_aprovado_por = u_aprovador.id
    WHERE i.colaborador_id = ? AND f.status = 'fechado'
    ORDER BY f.mes_referencia DESC, f.data_fechamento DESC
");
$stmt->execute([$colaborador_id]);
$fechamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por fechamento e recalcula valores
$fechamentos_agrupados = [];
foreach ($fechamentos as $fechamento) {
    $fechamento_id = $fechamento['fechamento_id'];
    if (!isset($fechamentos_agrupados[$fechamento_id])) {
        $fechamentos_agrupados[$fechamento_id] = [
            'fechamento_id' => $fechamento['fechamento_id'],
            'mes_referencia' => $fechamento['mes_referencia'],
            'data_fechamento' => $fechamento['data_fechamento'],
            'tipo_fechamento' => $fechamento['tipo_fechamento'],
            'empresa_nome' => $fechamento['empresa_nome'],
            'documento_obrigatorio' => $fechamento['documento_obrigatorio'],
            'itens' => []
        ];
    }
    $fechamentos_agrupados[$fechamento_id]['itens'][] = $fechamento;
}

// Recalcula valor total para cada fechamento (igual à API de detalhes)
foreach ($fechamentos_agrupados as $fechamento_id => &$fechamento_data) {
    if ($fechamento_data['tipo_fechamento'] === 'regular') {
        // Busca bônus virtuais (fechamentos extras individuais/grupais abertos)
        // Mesma lógica da API get_detalhes_pagamento.php (linhas 229-269)
        $mes_referencia = $fechamento_data['mes_referencia'];
        $stmt = $pdo->prepare("
            SELECT SUM(fpi.valor_total) as total_bonus_extra
            FROM fechamentos_pagamento fp
            INNER JOIN fechamentos_pagamento_itens fpi ON fp.id = fpi.fechamento_id
            WHERE fp.tipo_fechamento = 'extra'
            AND (fp.subtipo_fechamento = 'individual' OR fp.subtipo_fechamento = 'grupal')
            AND fp.status = 'aberto'
            AND fpi.colaborador_id = ?
            AND fp.mes_referencia = ?
        ");
        $stmt->execute([$colaborador_id, $mes_referencia]);
        $bonus_extra = $stmt->fetch();
        $total_bonus_extra = (float)($bonus_extra['total_bonus_extra'] ?? 0);
        
        // Busca bônus do fechamento regular (mesma lógica da API)
        $stmt = $pdo->prepare("
            SELECT 
                fb.*, 
                tb.tipo_valor
            FROM fechamentos_pagamento_bonus fb
            INNER JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
            WHERE fb.fechamento_pagamento_id = ? AND fb.colaborador_id = ?
        ");
        $stmt->execute([$fechamento_id, $colaborador_id]);
        $bonus_list = $stmt->fetchAll();
        
        // Calcula total de bônus (excluindo informativos)
        $total_bonus_reg = 0;
        foreach ($bonus_list as $b) {
            $tipo_valor = $b['tipo_valor'] ?? 'variavel';
            if ($tipo_valor !== 'informativo') {
                $total_bonus_reg += (float)($b['valor'] ?? 0);
            }
        }
        
        $total_bonus = $total_bonus_reg + $total_bonus_extra;
        
        // Busca adiantamentos descontados neste fechamento
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(valor_descontar), 0) as total_adiantamentos
            FROM fechamentos_pagamento_adiantamentos
            WHERE fechamento_desconto_id = ? AND colaborador_id = ?
        ");
        $stmt->execute([$fechamento_id, $colaborador_id]);
        $adiantamentos = $stmt->fetch();
        $total_adiantamentos = (float)($adiantamentos['total_adiantamentos'] ?? 0);
        
        // Recalcula valor total para cada item
        foreach ($fechamento_data['itens'] as &$item) {
            $descontos_originais = (float)($item['descontos'] ?? 0);
            
            // Verifica se os adiantamentos já estão incluídos no campo descontos
            // Quando um fechamento regular é criado, os adiantamentos são somados ao campo descontos
            // Então, se há adiantamentos descontados neste fechamento, eles já devem estar incluídos
            // Se o campo descontos é menor que os adiantamentos, significa que não estão incluídos (caso raro)
            if ($total_adiantamentos > 0 && $descontos_originais < $total_adiantamentos) {
                // Adiantamentos não estão incluídos, soma ao total
                $descontos_totais = $descontos_originais + $total_adiantamentos;
            } else {
                // Adiantamentos já estão incluídos no campo descontos (comportamento padrão)
                // ou não há adiantamentos para descontar
                $descontos_totais = $descontos_originais;
            }
            
            $valor_total_recalculado = 
                (float)($item['salario_base'] ?? 0) + 
                (float)($item['valor_horas_extras'] ?? 0) + 
                $total_bonus + 
                (float)($item['adicionais'] ?? 0) - 
                $descontos_totais;
            
            $item['valor_total'] = $valor_total_recalculado;
        }
        unset($item);
    }
}
unset($fechamento_data);

// Calcula estatísticas
$stats = [
    'total' => count($fechamentos_agrupados),
    'pendente' => 0,
    'enviado' => 0,
    'aprovado' => 0,
    'rejeitado' => 0
];

foreach ($fechamentos_agrupados as $fechamento) {
    foreach ($fechamento['itens'] as $item) {
        $status = $item['documento_status'] ?? 'pendente';
        if ($status === 'pendente') {
            $stats['pendente']++;
        } elseif ($status === 'enviado') {
            $stats['enviado']++;
        } elseif ($status === 'aprovado') {
            $stats['aprovado']++;
        } elseif ($status === 'rejeitado') {
            $stats['rejeitado']++;
        }
    }
}

$page_title = 'Meus Pagamentos';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Meus Pagamentos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Meus Pagamentos</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Aviso Navegador Gmail-->
        <div id="aviso_navegador_gmail" class="alert alert-warning d-none mb-5" role="alert">
            <div class="d-flex align-items-center">
                <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="flex-grow-1">
                    <h4 class="alert-heading fw-bold mb-2">Atenção: Navegador Limitado Detectado</h4>
                    <p class="mb-2">
                        Você está acessando o sistema pelo navegador do Gmail, que tem limitações para upload de arquivos. 
                        Para enviar documentos corretamente, abra este link no seu navegador padrão (Chrome, Safari, Firefox).
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-warning btn-sm" onclick="abrirNoNavegadorPadrao()">
                            <i class="ki-duotone ki-external-link fs-5 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Abrir no Navegador Padrão
                        </button>
                        <button type="button" class="btn btn-light btn-sm" onclick="document.getElementById('aviso_navegador_gmail').classList.add('d-none')">
                            Fechar
                        </button>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <strong>Como fazer:</strong> Toque nos três pontos (⋮) no canto superior direito e selecione "Abrir no Chrome" ou "Abrir no Safari".
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Aviso Navegador Gmail-->
        
        <!--begin::Estatísticas-->
        <div class="row g-3 mb-5">
            <div class="col-md-3">
                <div class="card bg-light-primary">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-muted fw-semibold d-block">Total</span>
                                <span class="text-gray-800 fw-bold fs-2"><?= $stats['total'] ?></span>
                            </div>
                            <i class="ki-duotone ki-wallet fs-1 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-warning">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-muted fw-semibold d-block">Pendente</span>
                                <span class="text-gray-800 fw-bold fs-2"><?= $stats['pendente'] ?></span>
                            </div>
                            <i class="ki-duotone ki-time fs-1 text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-info">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-muted fw-semibold d-block">Enviado</span>
                                <span class="text-gray-800 fw-bold fs-2"><?= $stats['enviado'] ?></span>
                            </div>
                            <i class="ki-duotone ki-file-up fs-1 text-info">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-success">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <span class="text-muted fw-semibold d-block">Aprovado</span>
                                <span class="text-gray-800 fw-bold fs-2"><?= $stats['aprovado'] ?></span>
                            </div>
                            <i class="ki-duotone ki-check-circle fs-1 text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Estatísticas-->
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h2 class="fw-bold">Fechamentos de Pagamento</h2>
                </div>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($fechamentos_agrupados)): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-information-5 fs-3x mb-3 text-muted">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="text-muted">Nenhum fechamento encontrado.</div>
                    </div>
                <?php else: ?>
                    <!-- Versão Desktop: Tabela -->
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table table-row-bordered table-row-dashed gy-4 align-middle" id="kt_meus_pagamentos_table">
                            <thead>
                                <tr class="fw-bold fs-6 text-gray-800">
                                    <th>Mês/Ano</th>
                                    <th>Empresa</th>
                                    <th>Valor Total</th>
                                    <th>Status Documento</th>
                                    <th>Data Envio</th>
                                    <th>Observações</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fechamentos_agrupados as $fechamento): 
                                    // Verifica se há itens antes de acessar
                                    if (empty($fechamento['itens']) || !is_array($fechamento['itens'])) {
                                        continue;
                                    }
                                    $item = $fechamento['itens'][0]; // Pega primeiro item (todos têm mesmo status)
                                    $total_fechamento = array_sum(array_column($fechamento['itens'], 'valor_total'));
                                    $status = $item['documento_status'] ?? 'pendente';
                                ?>
                                <tr>
                                    <td>
                                        <span class="text-gray-800 fw-bold">
                                            <?= date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')) ?>
                                        </span>
                                        <div class="text-muted fs-7">
                                            Fechado em <?= date('d/m/Y', strtotime($fechamento['data_fechamento'])) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($fechamento['empresa_nome']) ?></td>
                                    <td>
                                        <span class="text-success fw-bold">R$ <?= number_format($total_fechamento, 2, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'pendente' => '<span class="badge badge-light-danger">Pendente</span>',
                                            'enviado' => '<span class="badge badge-light-warning">Enviado</span>',
                                            'aprovado' => '<span class="badge badge-light-success">Aprovado</span>',
                                            'rejeitado' => '<span class="badge badge-light-danger">Rejeitado</span>'
                                        ];
                                        echo $badges[$status] ?? '<span class="badge badge-light-secondary">-</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($item['documento_data_envio']): ?>
                                            <?= date('d/m/Y H:i', strtotime($item['documento_data_envio'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['documento_observacoes']): ?>
                                            <span class="text-gray-600" title="<?= htmlspecialchars($item['documento_observacoes']) ?>">
                                                <?= htmlspecialchars(mb_substr($item['documento_observacoes'], 0, 50)) ?>
                                                <?= mb_strlen($item['documento_observacoes']) > 50 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-light-info" 
                                                    onclick="verDetalhesPagamentoColaborador(<?= $fechamento['fechamento_id'] ?>, <?= $colaborador_id ?>)">
                                                <i class="ki-duotone ki-eye fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                Ver Detalhes
                                            </button>
                                            <?php if ($status === 'pendente' || $status === 'rejeitado'): ?>
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        onclick="enviarDocumento(<?= $fechamento['fechamento_id'] ?>, <?= $item['item_id'] ?>)">
                                                    <i class="ki-duotone ki-file-up fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Enviar Documento
                                                </button>
                                            <?php elseif ($status === 'enviado' || $status === 'aprovado'): ?>
                                                <button type="button" class="btn btn-sm btn-light-<?= $status === 'aprovado' ? 'success' : 'info' ?>" 
                                                        onclick="verDocumento(<?= $fechamento['fechamento_id'] ?>, <?= $item['item_id'] ?>)">
                                                    <i class="ki-duotone ki-<?= $status === 'aprovado' ? 'check-circle' : 'eye' ?> fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <?php if ($status === 'enviado'): ?>
                                                        <span class="path3"></span>
                                                        <?php endif; ?>
                                                    </i>
                                                    Ver Documento
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Versão Mobile: Cards -->
                    <div class="d-lg-none">
                        <?php foreach ($fechamentos_agrupados as $fechamento): 
                            // Verifica se há itens antes de acessar
                            if (empty($fechamento['itens']) || !is_array($fechamento['itens'])) {
                                continue;
                            }
                            $item = $fechamento['itens'][0];
                            $total_fechamento = array_sum(array_column($fechamento['itens'], 'valor_total'));
                            $status = $item['documento_status'] ?? 'pendente';
                            $badges = [
                                'pendente' => '<span class="badge badge-light-danger">Pendente</span>',
                                'enviado' => '<span class="badge badge-light-warning">Enviado</span>',
                                'aprovado' => '<span class="badge badge-light-success">Aprovado</span>',
                                'rejeitado' => '<span class="badge badge-light-danger">Rejeitado</span>'
                            ];
                        ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="fw-bold text-gray-800 mb-1">
                                            <?= date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')) ?>
                                        </h5>
                                        <div class="text-muted fs-7">
                                            Fechado em <?= date('d/m/Y', strtotime($fechamento['data_fechamento'])) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-success fw-bold fs-4 mb-1">R$ <?= number_format($total_fechamento, 2, ',', '.') ?></div>
                                        <?= $badges[$status] ?? '<span class="badge badge-light-secondary">-</span>' ?>
                                    </div>
                                </div>
                                
                                <div class="separator separator-dashed my-3"></div>
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="text-muted fs-7 mb-1">Empresa</div>
                                        <div class="fw-semibold"><?= htmlspecialchars($fechamento['empresa_nome']) ?></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-muted fs-7 mb-1">Data Envio</div>
                                        <div class="fw-semibold">
                                            <?php if ($item['documento_data_envio']): ?>
                                                <?= date('d/m/Y H:i', strtotime($item['documento_data_envio'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($item['documento_observacoes']): ?>
                                <div class="mb-3">
                                    <div class="text-muted fs-7 mb-1">Observações</div>
                                    <div class="text-gray-600"><?= htmlspecialchars($item['documento_observacoes']) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex flex-column gap-2 mt-3">
                                    <button type="button" class="btn btn-light-info w-100" 
                                            onclick="verDetalhesPagamentoColaborador(<?= $fechamento['fechamento_id'] ?>, <?= $colaborador_id ?>)">
                                        <i class="ki-duotone ki-eye fs-5 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Ver Detalhes
                                    </button>
                                    <?php if ($status === 'pendente' || $status === 'rejeitado'): ?>
                                        <button type="button" class="btn btn-primary w-100" 
                                                onclick="enviarDocumento(<?= $fechamento['fechamento_id'] ?>, <?= $item['item_id'] ?>)">
                                            <i class="ki-duotone ki-file-up fs-5 me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Enviar Documento
                                        </button>
                                    <?php elseif ($status === 'enviado' || $status === 'aprovado'): ?>
                                        <button type="button" class="btn btn-light-<?= $status === 'aprovado' ? 'success' : 'info' ?> w-100" 
                                                onclick="verDocumento(<?= $fechamento['fechamento_id'] ?>, <?= $item['item_id'] ?>)">
                                            <i class="ki-duotone ki-<?= $status === 'aprovado' ? 'check-circle' : 'eye' ?> fs-5 me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <?php if ($status === 'enviado'): ?>
                                                <span class="path3"></span>
                                                <?php endif; ?>
                                            </i>
                                            Ver Documento
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Enviar Documento-->
<div class="modal fade" id="kt_modal_enviar_documento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Enviar Documento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_form_enviar_documento" enctype="multipart/form-data">
                    <input type="hidden" name="fechamento_id" id="doc_fechamento_id">
                    <input type="hidden" name="item_id" id="doc_item_id">
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-3">Documento (Nota Fiscal, Recibo, etc.)</label>
                        
                        <!-- Input file customizado -->
                        <div class="position-relative" id="doc_arquivo_container">
                            <!-- Botão customizado para mobile -->
                            <button type="button" id="btn_escolher_arquivo_mobile" class="btn btn-primary w-100 d-lg-none" style="min-height: 56px; padding: 12px 20px; position: relative; z-index: 1;">
                                <i class="ki-duotone ki-file-up fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span id="doc_arquivo_label_mobile">Escolher Arquivo</span>
                            </button>
                            
                            <!-- Input visual para desktop -->
                            <div id="doc_arquivo_visual_desktop" class="form-control form-control-solid d-none d-lg-flex align-items-center" style="min-height: 50px; padding: 12px 20px; position: relative; z-index: 1;">
                                <i class="ki-duotone ki-file-up fs-3 me-2 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span id="doc_arquivo_label_desktop">Clique para escolher arquivo ou arraste aqui</span>
                            </div>
                            
                            <!-- Input file real (invisível mas clicável) -->
                            <input type="file" name="documento" id="doc_arquivo" 
                                   class="form-control form-control-solid" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" 
                                   required
                                   style="position: absolute; top: 0; left: 0; width: 100%; opacity: 0; cursor: pointer; z-index: 2;">
                            
                            <!-- Nome do arquivo selecionado -->
                            <div id="doc_arquivo_nome" class="mt-2 text-gray-600 fw-semibold" style="display: none;">
                                <i class="ki-duotone ki-check-circle fs-5 text-success me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span id="doc_arquivo_nome_texto"></span>
                            </div>
                            
                            <!-- Mensagem de erro -->
                            <div id="doc_arquivo_erro" class="alert alert-danger mt-2" style="display: none;">
                                <i class="ki-duotone ki-information-5 fs-5 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <span id="doc_arquivo_erro_texto"></span>
                            </div>
                        </div>
                        
                        <div class="form-text mt-2">Formatos aceitos: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, WEBP (máx. 10MB)</div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btn_enviar_documento">
                            <span class="indicator-label">Enviar</span>
                            <span class="indicator-progress">Enviando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Ver Documento-->
<div class="modal fade" id="kt_modal_ver_documento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Visualizar Documento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="doc_preview_container">
                    <!-- Conteúdo será preenchido via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="doc_download_link" class="btn btn-primary" download>
                    <i class="ki-duotone ki-file-down fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Download
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Detalhes Completos do Pagamento-->
<div class="modal fade" id="kt_modal_detalhes_pagamento_colaborador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-1000px modal-fullscreen-lg-down" style="max-width: 1000px;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="kt_modal_detalhes_pagamento_colaborador_titulo">Detalhes do Pagamento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="kt_modal_detalhes_pagamento_colaborador_conteudo">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <div class="text-muted mt-3">Carregando detalhes...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
// Detecta se está sendo aberto no navegador do Gmail ou outro navegador limitado
function detectarNavegadorLimitado(mostrarAviso = true) {
    const userAgent = navigator.userAgent.toLowerCase();
    const isMobile = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent);
    
    // Detecta navegador do Gmail (geralmente contém "wv" ou "Gmail" no user agent)
    // "wv" indica WebView, que é usado pelo Gmail app
    const isGmailBrowser = userAgent.includes('wv') || 
                          userAgent.includes('gmail') ||
                          (window.navigator.standalone === false && userAgent.includes('android') && !userAgent.includes('chrome')) ||
                          (isMobile && !userAgent.includes('chrome') && !userAgent.includes('safari') && !userAgent.includes('firefox') && !userAgent.includes('samsung'));
    
    // Verifica se tem APIs necessárias para upload de arquivos
    const temFileAPI = typeof File !== 'undefined' && typeof FileReader !== 'undefined';
    const temInputFile = document.createElement('input').type === 'file';
    
    // Se detectou navegador limitado ou não tem APIs necessárias
    if (isGmailBrowser || !temFileAPI || !temInputFile) {
        if (mostrarAviso) {
            const avisoDiv = document.getElementById('aviso_navegador_gmail');
            if (avisoDiv) {
                avisoDiv.classList.remove('d-none');
            }
        }
        return true;
    }
    
    return false;
}

// Tenta abrir no navegador padrão
function abrirNoNavegadorPadrao() {
    const urlAtual = window.location.href;
    
    // Tenta diferentes métodos para abrir no navegador padrão
    try {
        // Método 1: Intent URL (Android)
        if (/android/i.test(navigator.userAgent)) {
            window.location.href = 'intent://' + urlAtual.replace(/https?:\/\//, '') + '#Intent;scheme=https;package=com.android.chrome;end';
            setTimeout(function() {
                // Fallback: tenta abrir diretamente
                window.open(urlAtual, '_system');
            }, 500);
        } else {
            // Método 2: Para iOS, tenta abrir no Safari
            window.open(urlAtual, '_system');
        }
    } catch (e) {
        // Fallback: mostra instruções
        Swal.fire({
            title: 'Como Abrir no Navegador Padrão',
            html: `
                <div class="text-start">
                    <p class="mb-3"><strong>Android:</strong></p>
                    <ol class="text-start mb-3">
                        <li>Toque nos três pontos (⋮) no canto superior direito</li>
                        <li>Selecione "Abrir no Chrome" ou "Abrir no navegador"</li>
                    </ol>
                    <p class="mb-3"><strong>iOS:</strong></p>
                    <ol class="text-start">
                        <li>Toque no ícone de compartilhar</li>
                        <li>Selecione "Abrir no Safari"</li>
                    </ol>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Entendi'
        });
    }
}

// Executa detecção ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    detectarNavegadorLimitado();
});

// Enviar documento
function enviarDocumento(fechamentoId, itemId) {
    document.getElementById('doc_fechamento_id').value = fechamentoId;
    document.getElementById('doc_item_id').value = itemId;
    
    const fileInput = document.getElementById('doc_arquivo');
    if (fileInput) {
        fileInput.value = '';
    }
    
    // Reseta labels
    const labelMobile = document.getElementById('doc_arquivo_label_mobile');
    const labelDesktop = document.getElementById('doc_arquivo_label_desktop');
    const nomeArquivo = document.getElementById('doc_arquivo_nome');
    
    if (labelMobile) labelMobile.textContent = 'Escolher Arquivo';
    if (labelDesktop) labelDesktop.textContent = 'Clique para escolher arquivo ou arraste aqui';
    if (nomeArquivo) nomeArquivo.style.display = 'none';
    
    // Esconde erros anteriores
    esconderErroArquivo();
    
    const modalEl = document.getElementById('kt_modal_enviar_documento');
    const modal = new bootstrap.Modal(modalEl);
    
    // Reseta flag quando modal é fechado
    modalEl.addEventListener('hidden.bs.modal', function() {
        inputArquivoConfigurado = false;
        esconderErroArquivo();
    }, { once: true });
    
    modal.show();
    
    // Verifica navegador limitado quando modal é aberto (sem mostrar banner, apenas erro no modal)
    setTimeout(function() {
        const isGmailBrowser = detectarNavegadorLimitado(false);
        if (isGmailBrowser) {
            // Mostra aviso no modal
            mostrarErroArquivo('Você está usando o navegador do Gmail, que não suporta upload de arquivos. Abra este link no seu navegador padrão (Chrome, Safari ou Firefox) para enviar documentos.');
        }
    }, 100);
    
    // Configura eventos após o modal ser exibido
    setTimeout(function() {
        inputArquivoConfigurado = false;
        configurarInputArquivo();
    }, 300);
}

// Variável para controlar se já foi configurado
let inputArquivoConfigurado = false;

// Mostra mensagem de erro
function mostrarErroArquivo(mensagem) {
    const erroDiv = document.getElementById('doc_arquivo_erro');
    const erroTexto = document.getElementById('doc_arquivo_erro_texto');
    if (erroDiv && erroTexto) {
        erroTexto.textContent = mensagem;
        erroDiv.style.display = 'flex';
        erroDiv.style.alignItems = 'center';
        
        // Remove o erro após 5 segundos
        setTimeout(function() {
            erroDiv.style.display = 'none';
        }, 5000);
    }
}

// Esconde mensagem de erro
function esconderErroArquivo() {
    const erroDiv = document.getElementById('doc_arquivo_erro');
    if (erroDiv) {
        erroDiv.style.display = 'none';
    }
}

// Configura eventos do input de arquivo
function configurarInputArquivo() {
    if (inputArquivoConfigurado) return;
    
    const fileInput = document.getElementById('doc_arquivo');
    const btnMobile = document.getElementById('btn_escolher_arquivo_mobile');
    const labelMobile = document.getElementById('doc_arquivo_label_mobile');
    const labelDesktop = document.getElementById('doc_arquivo_label_desktop');
    const nomeArquivo = document.getElementById('doc_arquivo_nome');
    const nomeArquivoTexto = document.getElementById('doc_arquivo_nome_texto');
    
    if (!fileInput) return;
    
    // Faz o botão mobile acionar o input com tratamento de erro
    if (btnMobile) {
        btnMobile.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Esconde erros anteriores
            esconderErroArquivo();
            
            // Verifica se estamos em mobile
            const isMobile = window.innerWidth < 992;
            
            try {
                // Tenta acionar o input
                if (fileInput) {
                    // Verifica se o input está visível/acessível
                    const rect = fileInput.getBoundingClientRect();
                    if (rect.width === 0 || rect.height === 0) {
                        mostrarErroArquivo('Erro: O campo de arquivo não está acessível. Tente atualizar a página.');
                        return;
                    }
                    
                    // Verifica se o input está desabilitado
                    if (fileInput.disabled) {
                        mostrarErroArquivo('Erro: O campo de arquivo está desabilitado.');
                        return;
                    }
                    
                    // Tenta focar no input primeiro (pode ajudar em alguns navegadores mobile)
                    try {
                        fileInput.focus();
                    } catch (focusError) {
                        console.warn('Não foi possível focar no input:', focusError);
                    }
                    
                    // Tenta clicar no input
                    fileInput.click();
                    
                    // No mobile, verifica após um tempo se o diálogo foi aberto
                    if (isMobile) {
                        let dialogAberto = false;
                        const checkInterval = setInterval(function() {
                            // Se o input recebeu foco ou mudou, o diálogo foi aberto
                            if (document.activeElement === fileInput || fileInput.files.length > 0) {
                                dialogAberto = true;
                                clearInterval(checkInterval);
                            }
                        }, 50);
                        
                        // Após 500ms, se não detectou abertura, mostra aviso
                        setTimeout(function() {
                            clearInterval(checkInterval);
                            if (!dialogAberto && fileInput.files.length === 0) {
                                // Verifica se está no navegador do Gmail
                                const userAgent = navigator.userAgent.toLowerCase();
                                const isGmailBrowser = userAgent.includes('wv') || 
                                                      userAgent.includes('gmail') ||
                                                      (window.navigator.standalone === false && userAgent.includes('android') && !userAgent.includes('chrome'));
                                
                                if (isGmailBrowser) {
                                    mostrarErroArquivo('Você está usando o navegador do Gmail, que não suporta upload de arquivos. Abra este link no seu navegador padrão (Chrome, Safari ou Firefox) para enviar documentos.');
                                    const avisoDiv = document.getElementById('aviso_navegador_gmail');
                                    if (avisoDiv) {
                                        avisoDiv.classList.remove('d-none');
                                    }
                                } else {
                                    mostrarErroArquivo('Não foi possível abrir o seletor de arquivos. Verifique se o navegador tem permissão para acessar arquivos ou tente tocar diretamente na área do botão.');
                                }
                            }
                        }, 500);
                    }
                } else {
                    mostrarErroArquivo('Erro: Campo de arquivo não encontrado. Tente atualizar a página.');
                }
            } catch (error) {
                console.error('Erro ao acionar input de arquivo:', error);
                
                // Verifica se está no navegador do Gmail
                const userAgent = navigator.userAgent.toLowerCase();
                const isGmailBrowser = userAgent.includes('wv') || 
                                      userAgent.includes('gmail') ||
                                      (window.navigator.standalone === false && userAgent.includes('android') && !userAgent.includes('chrome'));
                
                // Mensagens específicas baseadas no tipo de erro
                let mensagemErro = '';
                if (isGmailBrowser) {
                    mensagemErro = 'Você está usando o navegador do Gmail, que não suporta upload de arquivos. ';
                    mensagemErro += 'Por favor, abra este link no seu navegador padrão (Chrome, Safari ou Firefox) para enviar documentos.';
                } else if (error.name === 'SecurityError' || error.message.includes('permission') || error.message.includes('Permission')) {
                    mensagemErro = 'Erro de permissão: O navegador bloqueou o acesso aos arquivos. ';
                    if (isMobile) {
                        mensagemErro += 'No mobile, verifique as configurações de permissão do navegador nas configurações do dispositivo ou tente usar outro navegador (Chrome, Firefox).';
                    } else {
                        mensagemErro += 'Verifique as configurações de permissão do navegador ou tente usar outro navegador.';
                    }
                } else if (error.name === 'InvalidStateError') {
                    mensagemErro = 'Erro: O campo de arquivo está em estado inválido. Tente atualizar a página.';
                } else {
                    mensagemErro = 'Erro ao abrir seletor de arquivos: ' + (error.message || 'Erro desconhecido') + '. ';
                    if (isMobile) {
                        mensagemErro += 'Tente atualizar a página ou usar outro navegador.';
                    } else {
                        mensagemErro += 'Tente atualizar a página.';
                    }
                }
                
                mostrarErroArquivo(mensagemErro);
                
                // Se detectou navegador do Gmail, mostra banner também
                if (isGmailBrowser) {
                    const avisoDiv = document.getElementById('aviso_navegador_gmail');
                    if (avisoDiv) {
                        avisoDiv.classList.remove('d-none');
                    }
                }
            }
        });
    }
    
    // Também adiciona listener direto no input para detectar problemas
    fileInput.addEventListener('click', function(e) {
        esconderErroArquivo();
    });
    
    // Atualiza labels quando arquivo é selecionado
    fileInput.addEventListener('change', function(e) {
        esconderErroArquivo();
        
        const file = e.target.files[0];
        if (file) {
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
            
            // Verifica tamanho do arquivo
            if (file.size > 10 * 1024 * 1024) {
                mostrarErroArquivo('Arquivo muito grande! O tamanho máximo é 10MB. Arquivo selecionado: ' + fileSize + 'MB');
                fileInput.value = '';
                return;
            }
            
            // Atualiza labels
            if (labelMobile) {
                labelMobile.textContent = fileName.length > 25 ? fileName.substring(0, 22) + '...' : fileName;
            }
            if (labelDesktop) {
                labelDesktop.textContent = fileName.length > 40 ? fileName.substring(0, 37) + '...' : fileName;
            }
            
            // Mostra nome completo do arquivo
            if (nomeArquivoTexto) {
                nomeArquivoTexto.textContent = `${fileName} (${fileSize} MB)`;
            }
            if (nomeArquivo) {
                nomeArquivo.style.display = 'block';
            }
        } else {
            if (labelMobile) {
                labelMobile.textContent = 'Escolher Arquivo';
            }
            if (labelDesktop) {
                labelDesktop.textContent = 'Clique para escolher arquivo ou arraste aqui';
            }
            if (nomeArquivo) {
                nomeArquivo.style.display = 'none';
            }
        }
    });
    
    // Detecta se o input não está funcionando após tentativa
    fileInput.addEventListener('focus', function() {
        esconderErroArquivo();
    });
    
    inputArquivoConfigurado = true;
}

// Configura eventos na inicialização também (caso o modal já esteja no DOM)
document.addEventListener('DOMContentLoaded', function() {
    configurarInputArquivo();
});

// Ver detalhes completos do pagamento (colaborador)
function verDetalhesPagamentoColaborador(fechamentoId, colaboradorId) {
    const titulo = document.getElementById('kt_modal_detalhes_pagamento_colaborador_titulo');
    const conteudo = document.getElementById('kt_modal_detalhes_pagamento_colaborador_conteudo');
    
    // Mostra loading
    conteudo.innerHTML = `
        <div class="text-center py-10">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <div class="text-muted mt-3">Carregando detalhes...</div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_detalhes_pagamento_colaborador'));
    modal.show();
    
    // Busca dados via API (mesma função do admin)
    fetch(`../api/get_detalhes_pagamento.php?fechamento_id=${fechamentoId}&colaborador_id=${colaboradorId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                const d = data.data;
                
                // Garante que os objetos existam antes de usar
                d.fechamento = d.fechamento || {};
                d.item = d.item || {};
                d.bonus = d.bonus || { total: 0, total_desconto_ocorrencias: 0, lista: [] };
                d.ocorrencias = d.ocorrencias || { total_descontos: 0, descontos: [] };
                d.horas_extras = d.horas_extras || { resumo: { horas_dinheiro: 0, valor_dinheiro: 0, horas_banco: 0 }, total_horas: 0, registros: [] };
                d.periodo = d.periodo || { inicio_formatado: '-', fim_formatado: '-' };
                d.adiantamentos_descontados = d.adiantamentos_descontados || [];
                d.adiantamentos_pendentes = d.adiantamentos_pendentes || [];
                d.documento = d.documento || {};
                
                titulo.textContent = `Detalhes do Pagamento - ${d.fechamento.mes_referencia_formatado || '-'}`;
                
                // Usa o mesmo HTML do modal do admin (código completo igual ao fechamento_pagamentos.php)
                let html = `
                    <div class="mb-10">
                        <!-- Informações do Fechamento -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Informações do Fechamento</h3>
                            </div>
                            <div class="card-body">
                                <!-- Versão Desktop -->
                                <div class="d-none d-md-block">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <strong>Mês/Ano de Referência:</strong><br>
                                            <span class="text-gray-800">${d.fechamento.mes_referencia_formatado || '-'}</span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Data de Fechamento:</strong><br>
                                            <span class="text-gray-800">${d.fechamento.data_fechamento_formatada || '-'}</span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Empresa:</strong><br>
                                            <span class="text-gray-800">${d.fechamento.empresa_nome || '-'}</span>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Período:</strong><br>
                                            <span class="text-gray-800">${d.periodo.inicio_formatado} até ${d.periodo.fim_formatado}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Versão Mobile -->
                                <div class="d-md-none">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="d-flex flex-column">
                                                <strong class="text-muted fs-7 mb-1">Mês/Ano de Referência</strong>
                                                <span class="text-gray-800 fw-semibold">${d.fechamento.mes_referencia_formatado || '-'}</span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex flex-column">
                                                <strong class="text-muted fs-7 mb-1">Data de Fechamento</strong>
                                                <span class="text-gray-800 fw-semibold">${d.fechamento.data_fechamento_formatada || '-'}</span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex flex-column">
                                                <strong class="text-muted fs-7 mb-1">Empresa</strong>
                                                <span class="text-gray-800 fw-semibold">${d.fechamento.empresa_nome || '-'}</span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex flex-column">
                                                <strong class="text-muted fs-7 mb-1">Período</strong>
                                                <span class="text-gray-800 fw-semibold">${d.periodo.inicio_formatado} até ${d.periodo.fim_formatado}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resumo Financeiro -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Resumo Financeiro</h3>
                            </div>
                            <div class="card-body">
                                <!-- Versão Desktop: Tabela -->
                                <div class="d-none d-md-block">
                                    <div class="table-responsive">
                                        <table class="table table-row-bordered table-row-dashed gy-4">
                                            <tbody>
                                                <tr>
                                                    <td class="fw-bold">Salário Base</td>
                                                    <td class="text-end">
                                                        <span class="text-gray-800 fw-bold">R$ ${parseFloat(d.item.salario_base || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Horas Extras (Total)</td>
                                                    <td class="text-end">
                                                        <span class="text-gray-800 fw-bold">${parseFloat(d.item.horas_extras || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Valor Horas Extras</td>
                                                    <td class="text-end">
                                                        <span class="text-success fw-bold">R$ ${parseFloat(d.item.valor_horas_extras || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Total de Bônus</td>
                                                    <td class="text-end">
                                                        <span class="text-success fw-bold">R$ ${parseFloat(d.bonus.total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                    </td>
                                                </tr>
                                                ${(d.bonus.total_desconto_ocorrencias || 0) > 0 ? `
                                                <tr>
                                                    <td class="fw-bold text-danger">Desconto por Ocorrências (Bônus)</td>
                                                    <td class="text-end">
                                                        <span class="text-danger fw-bold">-R$ ${parseFloat(d.bonus.total_desconto_ocorrencias || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                    </td>
                                                </tr>
                                                ` : ''}
                                                ${(d.item.descontos || 0) > 0 || (d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0) ? `
                                                <tr>
                                                    <td class="fw-bold text-danger">Descontos</td>
                                                    <td class="text-end">
                                                        <span class="text-danger fw-bold">-R$ ${parseFloat(d.item.descontos || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                        ${(d.ocorrencias.total_descontos || 0) > 0 || (d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0) ? `
                                                        <br><small class="text-muted fs-8">
                                                            ${(d.ocorrencias.total_descontos || 0) > 0 ? 'Ocorrências: R$ ' + parseFloat(d.ocorrencias.total_descontos || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : ''}
                                                            ${(d.ocorrencias.total_descontos || 0) > 0 && d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0 ? ' | ' : ''}
                                                            ${d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0 ? 'Adiantamentos: R$ ' + d.adiantamentos_descontados.reduce((sum, a) => sum + parseFloat(a.valor_descontar || 0), 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : ''}
                                                        </small>
                                                        ` : ''}
                                                    </td>
                                                </tr>
                                                ` : ''}
                                                ${(d.item.adicionais || 0) > 0 ? `
                                                <tr>
                                                    <td class="fw-bold text-success">Adicionais</td>
                                                    <td class="text-end">
                                                        <span class="text-success fw-bold">+R$ ${parseFloat(d.item.adicionais || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                    </td>
                                                </tr>
                                                ` : ''}
                                                <tr class="border-top border-2">
                                                    <td class="fw-bold fs-4">Valor Total</td>
                                                    <td class="text-end">
                                                        <span class="text-success fw-bold fs-3">R$ ${parseFloat(d.item.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Versão Mobile: Cards -->
                                <div class="d-md-none">
                                    <div class="d-flex flex-column gap-3">
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                            <span class="fw-bold">Salário Base</span>
                                            <span class="text-gray-800 fw-bold">R$ ${parseFloat(d.item.salario_base || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                            <span class="fw-bold">Horas Extras (Total)</span>
                                            <span class="text-gray-800 fw-bold">${parseFloat(d.item.horas_extras || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light-success rounded">
                                            <span class="fw-bold">Valor Horas Extras</span>
                                            <span class="text-success fw-bold">R$ ${parseFloat(d.item.valor_horas_extras || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light-success rounded">
                                            <span class="fw-bold">Total de Bônus</span>
                                            <span class="text-success fw-bold">R$ ${parseFloat(d.bonus.total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                        </div>
                                        ${(d.bonus.total_desconto_ocorrencias || 0) > 0 ? `
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light-danger rounded">
                                            <span class="fw-bold text-danger">Desconto por Ocorrências (Bônus)</span>
                                            <span class="text-danger fw-bold">-R$ ${parseFloat(d.bonus.total_desconto_ocorrencias || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                        </div>
                                        ` : ''}
                                        ${(d.item.descontos || 0) > 0 || (d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0) ? `
                                        <div class="d-flex flex-column p-3 bg-light-danger rounded">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="fw-bold text-danger">Descontos</span>
                                                <span class="text-danger fw-bold">-R$ ${parseFloat(d.item.descontos || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                            </div>
                                            ${(d.ocorrencias.total_descontos || 0) > 0 || (d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0) ? `
                                            <div class="text-muted fs-8">
                                                ${(d.ocorrencias.total_descontos || 0) > 0 ? 'Ocorrências: R$ ' + parseFloat(d.ocorrencias.total_descontos || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : ''}
                                                ${(d.ocorrencias.total_descontos || 0) > 0 && d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0 ? '<br>' : ''}
                                                ${d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0 ? 'Adiantamentos: R$ ' + d.adiantamentos_descontados.reduce((sum, a) => sum + parseFloat(a.valor_descontar || 0), 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : ''}
                                            </div>
                                            ` : ''}
                                        </div>
                                        ` : ''}
                                        ${(d.item.adicionais || 0) > 0 ? `
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light-success rounded">
                                            <span class="fw-bold text-success">Adicionais</span>
                                            <span class="text-success fw-bold">+R$ ${parseFloat(d.item.adicionais || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                        </div>
                                        ` : ''}
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light-primary rounded border border-2 border-primary">
                                            <span class="fw-bold fs-4">Valor Total</span>
                                            <span class="text-success fw-bold fs-3">R$ ${parseFloat(d.item.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detalhes de Horas Extras -->
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Detalhes de Horas Extras</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-5">
                                    <h5 class="fw-bold mb-3">Resumo</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-success rounded">
                                                <i class="ki-duotone ki-money fs-2x text-success me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Horas Pagas em R$</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.resumo.horas_dinheiro || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                    <div class="text-success fs-6">R$ ${parseFloat(d.horas_extras.resumo.valor_dinheiro || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-info rounded">
                                                <i class="ki-duotone ki-time fs-2x text-info me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Horas em Banco</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.resumo.horas_banco || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-3 bg-light-primary rounded">
                                                <i class="ki-duotone ki-chart-simple fs-2x text-primary me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-muted fs-7">Total de Horas</div>
                                                    <div class="fw-bold fs-4">${parseFloat(d.horas_extras.total_horas || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${(d.horas_extras.registros && d.horas_extras.registros.length > 0) ? `
                                <h5 class="fw-bold mb-3">Registros Individuais</h5>
                                <!-- Versão Desktop: Tabela -->
                                <div class="d-none d-md-block">
                                    <div class="table-responsive">
                                        <table class="table table-row-bordered table-row-dashed gy-4">
                                            <thead>
                                                <tr class="fw-bold">
                                                    <th>Data</th>
                                                    <th>Quantidade</th>
                                                    <th>Valor/Hora</th>
                                                    <th>% Adicional</th>
                                                    <th>Valor Total</th>
                                                    <th>Tipo</th>
                                                    <th>Observações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${d.horas_extras.registros.map(he => `
                                                    <tr>
                                                        <td>${new Date(he.data_trabalho + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                                        <td>${parseFloat(he.quantidade_horas).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</td>
                                                        <td>R$ ${parseFloat(he.valor_hora || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        <td>${parseFloat(he.percentual_adicional || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
                                                        <td class="fw-bold">R$ ${parseFloat(he.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        <td>
                                                            ${he.tipo_pagamento === 'banco_horas' 
                                                                ? '<span class="badge badge-light-info">Banco de Horas</span>' 
                                                                : '<span class="badge badge-light-success">Dinheiro</span>'}
                                                        </td>
                                                        <td>${he.observacoes || '-'}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Versão Mobile: Cards -->
                                <div class="d-md-none">
                                    ${d.horas_extras.registros.map(he => `
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <div class="fw-bold">${new Date(he.data_trabalho + 'T00:00:00').toLocaleDateString('pt-BR')}</div>
                                                        <div class="text-muted fs-7">${he.tipo_pagamento === 'banco_horas' ? 'Banco de Horas' : 'Dinheiro'}</div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold text-success">R$ ${parseFloat(he.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                        <div class="text-muted fs-7">${parseFloat(he.quantidade_horas).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}h</div>
                                                    </div>
                                                </div>
                                                <div class="separator separator-dashed my-2"></div>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <div class="text-muted fs-7">Valor/Hora</div>
                                                        <div class="fw-semibold">R$ ${parseFloat(he.valor_hora || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-muted fs-7">% Adicional</div>
                                                        <div class="fw-semibold">${parseFloat(he.percentual_adicional || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</div>
                                                    </div>
                                                </div>
                                                ${he.observacoes ? `
                                                <div class="mt-2">
                                                    <div class="text-muted fs-7">Observações</div>
                                                    <div class="text-gray-600">${he.observacoes}</div>
                                                </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                                ` : '<p class="text-muted">Nenhuma hora extra registrada neste período.</p>'}
                            </div>
                        </div>
                        
                        <!-- Detalhes de Bônus -->
                        ${(d.bonus.lista && d.bonus.lista.length > 0) ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Detalhes de Bônus</h3>
                            </div>
                            <div class="card-body">
                                <!-- Versão Desktop: Tabela -->
                                <div class="d-none d-md-block">
                                    <div class="table-responsive">
                                        <table class="table table-row-bordered table-row-dashed gy-4">
                                            <thead>
                                                <tr class="fw-bold">
                                                    <th>Tipo de Bônus</th>
                                                    <th>Tipo</th>
                                                    <th>Valor Original</th>
                                                    <th>Desconto Ocorrências</th>
                                                    <th>Valor Final</th>
                                                    <th>Observações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${d.bonus.lista.map(b => {
                                                    const tipoValor = b.tipo_valor || 'variavel';
                                                    const tipoLabel = tipoValor === 'fixo' ? 'Valor Fixo' : tipoValor === 'informativo' ? 'Informativo' : 'Variável';
                                                    const tipoBadge = tipoValor === 'fixo' ? 'primary' : tipoValor === 'informativo' ? 'info' : 'success';
                                                    const valorOriginal = parseFloat(b.valor_original || b.valor || 0);
                                                    const descontoOcorrencias = parseFloat(b.desconto_ocorrencias || 0);
                                                    const valorFinal = parseFloat(b.valor || 0);
                                                    
                                                    return `
                                                        <tr>
                                                            <td class="fw-bold">${b.tipo_bonus_nome}</td>
                                                            <td><span class="badge badge-light-${tipoBadge}">${tipoLabel}</span></td>
                                                            <td>R$ ${valorOriginal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                            <td class="text-danger">${descontoOcorrencias > 0 ? '-R$ ' + descontoOcorrencias.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
                                                            <td class="fw-bold text-success">R$ ${valorFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                            <td>${b.observacoes || '-'}</td>
                                                        </tr>
                                                    `;
                                                }).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Versão Mobile: Cards -->
                                <div class="d-md-none">
                                    ${d.bonus.lista.map(b => {
                                        const tipoValor = b.tipo_valor || 'variavel';
                                        const tipoLabel = tipoValor === 'fixo' ? 'Valor Fixo' : tipoValor === 'informativo' ? 'Informativo' : 'Variável';
                                        const tipoBadge = tipoValor === 'fixo' ? 'primary' : tipoValor === 'informativo' ? 'info' : 'success';
                                        const valorOriginal = parseFloat(b.valor_original || b.valor || 0);
                                        const descontoOcorrencias = parseFloat(b.desconto_ocorrencias || 0);
                                        const valorFinal = parseFloat(b.valor || 0);
                                        
                                        return `
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <div class="fw-bold">${b.tipo_bonus_nome}</div>
                                                            <span class="badge badge-light-${tipoBadge}">${tipoLabel}</span>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="fw-bold text-success fs-5">R$ ${valorFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                        </div>
                                                    </div>
                                                    <div class="separator separator-dashed my-2"></div>
                                                    <div class="row g-2">
                                                        <div class="col-6">
                                                            <div class="text-muted fs-7">Valor Original</div>
                                                            <div class="fw-semibold">R$ ${valorOriginal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="text-muted fs-7">Desconto Ocorrências</div>
                                                            <div class="fw-semibold text-danger">${descontoOcorrencias > 0 ? '-R$ ' + descontoOcorrencias.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</div>
                                                        </div>
                                                    </div>
                                                    ${b.observacoes ? `
                                                    <div class="mt-2">
                                                        <div class="text-muted fs-7">Observações</div>
                                                        <div class="text-gray-600">${b.observacoes}</div>
                                                    </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Ocorrências com Desconto -->
                        ${(d.ocorrencias.descontos && d.ocorrencias.descontos.length > 0) ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Ocorrências com Desconto em R$</h3>
                            </div>
                            <div class="card-body">
                                <!-- Versão Desktop: Tabela -->
                                <div class="d-none d-md-block">
                                    <div class="table-responsive">
                                        <table class="table table-row-bordered table-row-dashed gy-4">
                                            <thead>
                                                <tr class="fw-bold">
                                                    <th>Data</th>
                                                    <th>Tipo</th>
                                                    <th>Descrição</th>
                                                    <th>Valor Desconto</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${d.ocorrencias.descontos.map(occ => `
                                                    <tr>
                                                        <td>${new Date(occ.data_ocorrencia + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                                        <td><span class="badge badge-light-danger">${occ.tipo_ocorrencia_nome || occ.tipo_ocorrencia_codigo || '-'}</span></td>
                                                        <td>${occ.descricao || '-'}</td>
                                                        <td class="text-danger fw-bold">-R$ ${parseFloat(occ.valor_desconto || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Versão Mobile: Cards -->
                                <div class="d-md-none">
                                    ${d.ocorrencias.descontos.map(occ => `
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <div class="fw-bold">${new Date(occ.data_ocorrencia + 'T00:00:00').toLocaleDateString('pt-BR')}</div>
                                                        <span class="badge badge-light-danger mt-1">${occ.tipo_ocorrencia_nome || occ.tipo_ocorrencia_codigo || '-'}</span>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold text-danger fs-5">-R$ ${parseFloat(occ.valor_desconto || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                    </div>
                                                </div>
                                                ${occ.descricao ? `
                                                <div class="separator separator-dashed my-2"></div>
                                                <div>
                                                    <div class="text-muted fs-7">Descrição</div>
                                                    <div class="text-gray-600">${occ.descricao}</div>
                                                </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Adiantamentos Descontados -->
                        ${d.adiantamentos_descontados && d.adiantamentos_descontados.length > 0 ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="ki-duotone ki-wallet fs-2 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Adiantamentos Descontados
                                </h3>
                                <div class="card-toolbar">
                                    <span class="badge badge-light-danger fs-4 fw-bold">
                                        Total: -R$ ${parseFloat(d.adiantamentos_descontados.reduce((sum, ad) => sum + (parseFloat(ad.valor_descontar || 0)), 0)).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Versão Desktop: Tabela -->
                                <div class="d-none d-md-block">
                                    <div class="table-responsive">
                                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                            <thead>
                                                <tr class="fw-bold text-muted">
                                                    <th class="min-w-100px">Data do Adiantamento</th>
                                                    <th class="min-w-100px">Mês de Desconto</th>
                                                    <th class="min-w-150px">Valor</th>
                                                    <th class="min-w-200px">Observações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${d.adiantamentos_descontados.map(ad => `
                                                    <tr>
                                                        <td>${ad.fechamento_data || '-'}</td>
                                                        <td>
                                                            <span class="badge badge-light-info">${ad.mes_desconto_formatado || ad.mes_desconto || '-'}</span>
                                                        </td>
                                                        <td class="text-danger fw-bold">-R$ ${parseFloat(ad.valor_descontar || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        <td>${ad.observacoes || '-'}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Versão Mobile: Cards -->
                                <div class="d-md-none">
                                    ${d.adiantamentos_descontados.map(ad => `
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <div class="fw-bold">${ad.fechamento_data || '-'}</div>
                                                        <span class="badge badge-light-info mt-1">${ad.mes_desconto_formatado || ad.mes_desconto || '-'}</span>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold text-danger fs-5">-R$ ${parseFloat(ad.valor_descontar || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                    </div>
                                                </div>
                                                ${ad.observacoes ? `
                                                <div class="separator separator-dashed my-2"></div>
                                                <div>
                                                    <div class="text-muted fs-7">Observações</div>
                                                    <div class="text-gray-600">${ad.observacoes}</div>
                                                </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Adiantamentos Pendentes -->
                        ${d.adiantamentos_pendentes && d.adiantamentos_pendentes.length > 0 ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="ki-duotone ki-time fs-2 text-warning me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Adiantamentos Pendentes
                                    <span class="badge badge-light-warning ms-2">${d.adiantamentos_pendentes.length}</span>
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info d-flex align-items-center p-3 mb-5">
                                    <i class="ki-duotone ki-information-5 fs-2hx text-info me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold">Atenção:</span>
                                        <span>Estes adiantamentos ainda não foram descontados. Serão descontados automaticamente quando o fechamento do mês de desconto for criado.</span>
                                    </div>
                                </div>
                                <!-- Versão Desktop: Tabela -->
                                <div class="d-none d-md-block">
                                    <div class="table-responsive">
                                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                            <thead>
                                                <tr class="fw-bold text-muted">
                                                    <th class="min-w-100px">Data do Adiantamento</th>
                                                    <th class="min-w-100px">Mês de Desconto</th>
                                                    <th class="min-w-150px">Valor a Descontar</th>
                                                    <th class="min-w-200px">Observações</th>
                                                    <th class="min-w-100px text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${d.adiantamentos_pendentes.map(ad => `
                                                    <tr>
                                                        <td>${ad.fechamento_data || '-'}</td>
                                                        <td>
                                                            <span class="badge ${ad.mes_desconto === d.fechamento.mes_referencia ? 'badge-light-success' : 'badge-light-warning'}">
                                                                ${ad.mes_desconto_formatado || ad.mes_desconto || '-'}
                                                            </span>
                                                            ${ad.mes_desconto === d.fechamento.mes_referencia ? '<span class="badge badge-light-success ms-1">Será descontado</span>' : ''}
                                                        </td>
                                                        <td class="fw-bold">R$ ${parseFloat(ad.valor_descontar || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                        <td>${ad.observacoes || '-'}</td>
                                                        <td class="text-center">
                                                            ${ad.mes_desconto === d.fechamento.mes_referencia ? 
                                                                '<span class="badge badge-light-success">Será descontado</span>' : 
                                                                '<span class="badge badge-light-warning">Aguardando</span>'}
                                                        </td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                            <tfoot>
                                                <tr class="fw-bold">
                                                    <td colspan="2" class="text-end">Total Pendente:</td>
                                                    <td class="text-warning">R$ ${parseFloat(d.adiantamentos_pendentes.reduce((sum, ad) => sum + (parseFloat(ad.valor_descontar || 0)), 0)).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                                    <td colspan="2"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                <!-- Versão Mobile: Cards -->
                                <div class="d-md-none">
                                    ${d.adiantamentos_pendentes.map(ad => `
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <div class="fw-bold">${ad.fechamento_data || '-'}</div>
                                                        <div class="mt-1">
                                                            <span class="badge ${ad.mes_desconto === d.fechamento.mes_referencia ? 'badge-light-success' : 'badge-light-warning'}">
                                                                ${ad.mes_desconto_formatado || ad.mes_desconto || '-'}
                                                            </span>
                                                            ${ad.mes_desconto === d.fechamento.mes_referencia ? '<span class="badge badge-light-success ms-1">Será descontado</span>' : ''}
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold fs-5">R$ ${parseFloat(ad.valor_descontar || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                                        <div class="mt-1">
                                                            ${ad.mes_desconto === d.fechamento.mes_referencia ? 
                                                                '<span class="badge badge-light-success">Será descontado</span>' : 
                                                                '<span class="badge badge-light-warning">Aguardando</span>'}
                                                        </div>
                                                    </div>
                                                </div>
                                                ${ad.observacoes ? `
                                                <div class="separator separator-dashed my-2"></div>
                                                <div>
                                                    <div class="text-muted fs-7">Observações</div>
                                                    <div class="text-gray-600">${ad.observacoes}</div>
                                                </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    `).join('')}
                                    <div class="card bg-light-warning">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold">Total Pendente:</span>
                                                <span class="fw-bold text-warning fs-4">R$ ${parseFloat(d.adiantamentos_pendentes.reduce((sum, ad) => sum + (parseFloat(ad.valor_descontar || 0)), 0)).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Status do Documento -->
                        ${d.documento.status ? `
                        <div class="card card-flush mb-5">
                            <div class="card-header">
                                <h3 class="card-title">Status do Documento</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Status:</strong><br>
                                        ${d.documento.status === 'aprovado' ? '<span class="badge badge-light-success">Aprovado</span>' :
                                          d.documento.status === 'enviado' ? '<span class="badge badge-light-warning">Enviado</span>' :
                                          d.documento.status === 'rejeitado' ? '<span class="badge badge-light-danger">Rejeitado</span>' :
                                          '<span class="badge badge-light-secondary">Pendente</span>'}
                                    </div>
                                    ${d.documento.data_envio ? `
                                    <div class="col-md-6 mb-3">
                                        <strong>Data de Envio:</strong><br>
                                        <span class="text-gray-800">${new Date(d.documento.data_envio).toLocaleString('pt-BR')}</span>
                                    </div>
                                    ` : ''}
                                    ${d.documento.data_aprovacao ? `
                                    <div class="col-md-6 mb-3">
                                        <strong>Data de Aprovação:</strong><br>
                                        <span class="text-gray-800">${new Date(d.documento.data_aprovacao).toLocaleString('pt-BR')}</span>
                                    </div>
                                    ` : ''}
                                    ${d.documento.observacoes ? `
                                    <div class="col-md-12 mb-3">
                                        <strong>Observações:</strong><br>
                                        <span class="text-gray-800">${d.documento.observacoes}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                conteudo.innerHTML = html;
            } else {
                conteudo.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="ki-duotone ki-information-5 fs-2x me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        ${data.message || 'Erro ao carregar detalhes do pagamento'}
                    </div>
                `;
            }
        })
        .catch(error => {
            conteudo.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2x me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Erro ao carregar detalhes do pagamento
                </div>
            `;
        });
}

// Ver documento
function verDocumento(fechamentoId, itemId) {
    // Busca dados do documento via API
    fetch(`../api/get_documento_pagamento.php?fechamento_id=${fechamentoId}&item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const doc = data.data;
                const container = document.getElementById('doc_preview_container');
                const downloadLink = document.getElementById('doc_download_link');
                
                downloadLink.href = '../' + doc.documento_anexo;
                downloadLink.download = doc.documento_nome || 'documento';
                
                // Verifica se é imagem para preview
                const isImage = doc.documento_anexo.match(/\.(jpg|jpeg|png|gif|webp)$/i);
                
                if (isImage) {
                    container.innerHTML = `
                        <div class="text-center">
                            <img src="../${doc.documento_anexo}" class="img-fluid" alt="Documento" style="max-height: 600px;">
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-file fs-3x text-primary mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-gray-600">Clique em "Download" para baixar o documento</div>
                            <div class="text-muted fs-7 mt-2">${doc.documento_nome || 'documento'}</div>
                        </div>
                    `;
                }
                
                const modal = new bootstrap.Modal(document.getElementById('kt_modal_ver_documento'));
                modal.show();
            } else {
                Swal.fire('Erro', data.message || 'Erro ao carregar documento', 'error');
            }
        })
        .catch(error => {
            Swal.fire('Erro', 'Erro ao carregar documento', 'error');
        });
}

// Submit do formulário de envio
document.getElementById('kt_form_enviar_documento')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const btn = document.getElementById('btn_enviar_documento');
    
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    fetch('../api/upload_documento_pagamento.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (data.success) {
            Swal.fire('Sucesso!', data.message, 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Erro', data.message, 'error');
        }
    })
    .catch(error => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        Swal.fire('Erro', 'Erro ao enviar documento', 'error');
    });
});

// Inicializa DataTable
(function() {
    function waitForDataTable() {
        if (typeof jQuery !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
            $('#kt_meus_pagamentos_table').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                pageLength: 10,
                order: [[0, 'desc']],
                responsive: false, // Desabilita responsive do DataTable pois já temos versão mobile customizada
                autoWidth: false
            });
        } else {
            setTimeout(waitForDataTable, 100);
        }
    }
    waitForDataTable();
})();
</script>

<style>
/* Garante que o modal tenha tamanho correto no desktop */
@media (min-width: 992px) {
    #kt_modal_detalhes_pagamento_colaborador .modal-dialog {
        max-width: 1000px !important;
    }
}

/* Container do input de arquivo */
#doc_arquivo_container {
    min-height: 56px;
}

@media (min-width: 992px) {
    #doc_arquivo_container {
        min-height: 50px;
    }
}

/* Melhora o input de arquivo */
#doc_arquivo {
    position: absolute !important;
    opacity: 0 !important;
    width: 100% !important;
    cursor: pointer !important;
    z-index: 10 !important;
    top: 0 !important;
    left: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
}

#btn_escolher_arquivo_mobile {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 600;
    -webkit-tap-highlight-color: transparent;
    position: relative;
    z-index: 1;
    pointer-events: none;
}

#btn_escolher_arquivo_mobile:active {
    opacity: 0.8;
    transform: scale(0.98);
}

/* Melhora a área de toque no mobile */
@media (max-width: 991px) {
    #btn_escolher_arquivo_mobile {
        min-height: 56px;
        font-size: 18px;
    }
    
    #kt_modal_enviar_documento .modal-body {
        padding: 1.5rem;
    }
    
    /* Garante que o input cubra toda a área do botão no mobile */
    #doc_arquivo {
        height: 56px !important;
        min-height: 56px !important;
    }
}

@media (min-width: 992px) {
    /* No desktop, o input deve cobrir apenas a área visual do form-control */
    #doc_arquivo {
        height: 50px !important;
        min-height: 50px !important;
    }
    
    /* Garante que o div visual não interfira com o clique */
    #doc_arquivo_visual_desktop {
        pointer-events: none;
    }
    
    /* Garante que o div visual tenha altura correta */
    #doc_arquivo_visual_desktop {
        min-height: 50px;
        height: 50px;
    }
}

/* Estilo da mensagem de erro */
#doc_arquivo_erro {
    display: none;
    align-items: center;
    padding: 12px 16px;
    margin-top: 8px;
    border-radius: 6px;
    font-size: 14px;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Estilo do aviso de navegador Gmail */
#aviso_navegador_gmail {
    animation: slideDown 0.3s ease-out;
    border-left: 4px solid #ffc700;
}

#aviso_navegador_gmail .alert-heading {
    color: #664d03;
}

#aviso_navegador_gmail p {
    color: #664d03;
    margin-bottom: 0.5rem;
}

@media (max-width: 767px) {
    #aviso_navegador_gmail {
        padding: 1rem;
    }
    
    #aviso_navegador_gmail .d-flex {
        flex-direction: column;
    }
    
    #aviso_navegador_gmail .d-flex > i {
        margin-bottom: 1rem;
        margin-right: 0 !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

