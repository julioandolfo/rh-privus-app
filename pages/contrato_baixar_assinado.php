<?php
/**
 * Redireciona para o PDF assinado hospedado na Autentique (URL retornada pela API).
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/autentique_service.php';

require_page_permission('contrato_view.php');

$pdo = getDB();
$contrato_id = intval($_GET['id'] ?? 0);

if ($contrato_id <= 0) {
    redirect('contratos.php', 'Contrato não encontrado.', 'error');
}

$stmt = $pdo->prepare('SELECT id, autentique_document_id FROM contratos WHERE id = ?');
$stmt->execute([$contrato_id]);
$contrato = $stmt->fetch();

if (!$contrato) {
    redirect('contratos.php', 'Contrato não encontrado.', 'error');
}

if (empty($contrato['autentique_document_id'])) {
    redirect('contrato_view.php?id=' . $contrato_id, 'Este contrato não está vinculado ao Autentique.', 'error');
}

try {
    $service = new AutentiqueService();
    $files = $service->obterArquivosDocumento($contrato['autentique_document_id']);

    if ($files === null) {
        redirect('contrato_view.php?id=' . $contrato_id, 'Documento não encontrado no Autentique.', 'warning');
    }

    $url = $files['signed'] ?? null;

    if (empty($url) || !is_string($url)) {
        redirect(
            'contrato_view.php?id=' . $contrato_id,
            'O PDF assinado ainda não está disponível. Conclua todas as assinaturas ou use Sincronizar com Autentique e tente novamente.',
            'warning'
        );
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        log_contrato('contrato_baixar_assinado: URL inválida retornada pela API para contrato_id=' . $contrato_id);
        redirect('contrato_view.php?id=' . $contrato_id, 'Não foi possível obter um link válido para o PDF assinado.', 'error');
    }

    header('Location: ' . $url, true, 302);
    exit;
} catch (Exception $e) {
    log_contrato('contrato_baixar_assinado: ' . $e->getMessage());
    redirect('contrato_view.php?id=' . $contrato_id, 'Erro ao consultar o Autentique: ' . $e->getMessage(), 'error');
}
