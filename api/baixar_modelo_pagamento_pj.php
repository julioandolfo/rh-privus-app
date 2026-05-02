<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pagamento_pj_functions.php';

require_login();

// Limpa qualquer output buffer pendente
while (ob_get_level()) ob_end_clean();

// Gera arquivo XLSX em diretório temporário
$tmp_dir = sys_get_temp_dir();
$tmp_file = $tmp_dir . DIRECTORY_SEPARATOR . 'modelo_horas_' . uniqid() . '.xlsx';

gerar_modelo_xlsx_pagamento_pj($tmp_file);

if (!file_exists($tmp_file)) {
    http_response_code(500);
    echo 'Erro ao gerar arquivo';
    exit;
}

$filename = 'modelo_horas_trabalhadas.xlsx';
$filesize = filesize($tmp_file);

// Força download em todos os browsers (inclusive os que ignorariam o tipo XLSX)
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $filesize);
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

readfile($tmp_file);
flush();
@unlink($tmp_file);
exit;
