<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pagamento_pj_functions.php';

require_login();

$csv = gerar_modelo_csv_pagamento_pj();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modelo_horas_trabalhadas.csv"');
header('Content-Length: ' . strlen($csv));
echo $csv;
exit;
