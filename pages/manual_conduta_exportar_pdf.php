<?php
/**
 * Exportar Manual de Conduta em PDF
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/manual_conduta_functions.php';

require_page_permission('manual_conduta_view.php');

$manual = get_manual_conduta_ativo();

if (!$manual) {
    redirect('manual_conduta_view.php', 'Manual não encontrado!', 'error');
}

$pdf = gerar_pdf_manual_conduta($manual);

// Nome do arquivo
$nome_arquivo = 'Manual_de_Conduta_Privus_' . date('Y-m-d') . '.pdf';
if (!empty($manual['versao'])) {
    $nome_arquivo = 'Manual_de_Conduta_Privus_v' . str_replace('.', '_', $manual['versao']) . '_' . date('Y-m-d') . '.pdf';
}

// Output PDF
$pdf->Output($nome_arquivo, 'D'); // D = força download
exit;

