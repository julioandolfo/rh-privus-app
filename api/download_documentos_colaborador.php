<?php
/**
 * API para baixar todos os documentos de um colaborador em um arquivo ZIP
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$colaborador_id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;

if (empty($colaborador_id)) {
    http_response_code(400);
    die('Colaborador não informado.');
}

// Verifica permissão de acesso ao colaborador
if (!can_access_colaborador($colaborador_id)) {
    http_response_code(403);
    die('Você não tem permissão para acessar os documentos deste colaborador.');
}

// Busca dados do colaborador para nomear o arquivo
$stmt = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    http_response_code(404);
    die('Colaborador não encontrado.');
}

// Busca todos os documentos do colaborador
$stmt = $pdo->prepare("
    SELECT
        i.id as item_id,
        i.documento_anexo,
        i.documento_status,
        f.mes_referencia,
        f.tipo_fechamento,
        f.subtipo_fechamento
    FROM fechamentos_pagamento_itens i
    INNER JOIN fechamentos_pagamento f ON i.fechamento_id = f.id
    WHERE i.colaborador_id = ?
    AND i.documento_anexo IS NOT NULL
    AND i.documento_anexo != ''
    ORDER BY f.mes_referencia DESC
");
$stmt->execute([$colaborador_id]);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($documentos)) {
    http_response_code(404);
    die('Nenhum documento encontrado para este colaborador.');
}

// Verifica se ZipArchive está disponível
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('Extensão ZIP não disponível no servidor.');
}

$zip = new ZipArchive();
$zip_nome = tempnam(sys_get_temp_dir(), 'docs_') . '.zip';

if ($zip->open($zip_nome, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Erro ao criar arquivo ZIP.');
}

$subtipo_labels = [
    'bonus_especifico' => 'Bonus-Especifico',
    'individual'       => 'Individual',
    'grupal'           => 'Grupal',
    'adiantamento'     => 'Adiantamento',
    'acerto'           => 'Acerto',
];

$adicionados = 0;
$nomes_usados = [];

foreach ($documentos as $doc) {
    $filepath = __DIR__ . '/../' . $doc['documento_anexo'];
    if (!file_exists($filepath)) {
        continue;
    }

    $mes = date('Y-m', strtotime($doc['mes_referencia'] . '-01'));
    $tipo = $doc['tipo_fechamento'] === 'extra'
        ? 'Extra-' . ($subtipo_labels[$doc['subtipo_fechamento']] ?? ucfirst($doc['subtipo_fechamento'] ?? 'Extra'))
        : 'Regular';
    $ext  = pathinfo($filepath, PATHINFO_EXTENSION);

    $nome_base = "{$mes}_{$tipo}";
    $nome_arquivo = $nome_base . '.' . $ext;

    // Evita nomes duplicados no ZIP
    $contador = 1;
    while (isset($nomes_usados[$nome_arquivo])) {
        $nome_arquivo = $nome_base . "_{$contador}." . $ext;
        $contador++;
    }
    $nomes_usados[$nome_arquivo] = true;

    $zip->addFile($filepath, $nome_arquivo);
    $adicionados++;
}

$zip->close();

if ($adicionados === 0) {
    @unlink($zip_nome);
    http_response_code(404);
    die('Nenhum arquivo encontrado no servidor.');
}

// Nome do arquivo para download
$nome_colab = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $colaborador['nome_completo']);
$nome_download = "Documentos_{$nome_colab}.zip";

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nome_download . '"');
header('Content-Length: ' . filesize($zip_nome));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($zip_nome);
@unlink($zip_nome);
exit;
