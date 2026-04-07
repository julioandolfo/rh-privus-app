<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pagamento_pj_functions.php';

require_login();

// Gera arquivo XLSX em diretório temporário
$tmp_dir = sys_get_temp_dir();
$tmp_file = $tmp_dir . DIRECTORY_SEPARATOR . 'modelo_horas_' . uniqid() . '.xlsx';

gerar_modelo_xlsx_pagamento_pj($tmp_file);

if (!file_exists($tmp_file)) {
    http_response_code(500);
    echo 'Erro ao gerar arquivo';
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="modelo_horas_trabalhadas.xlsx"');
header('Content-Length: ' . filesize($tmp_file));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($tmp_file);
@unlink($tmp_file);
exit;
