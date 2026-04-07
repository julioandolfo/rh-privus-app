<?php
/**
 * API: Cria uma nova solicitação de pagamento PJ.
 * Recebe os 3 anexos (planilha, NFe, boleto), valida a planilha,
 * salva tudo e cria os registros do histórico.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pagamento_pj_functions.php';
require_once __DIR__ . '/../includes/notificacoes.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$usuario = $_SESSION['usuario'];

try {
    $pdo = getDB();

    // Apenas COLABORADOR pode criar solicitação para si mesmo
    if (($usuario['role'] ?? '') !== 'COLABORADOR' || empty($usuario['colaborador_id'])) {
        echo json_encode(['success' => false, 'message' => 'Apenas colaboradores podem enviar solicitações']);
        exit;
    }

    $colaborador_id = (int) $usuario['colaborador_id'];

    $mes_referencia = trim($_POST['mes_referencia'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $valor_hora_input = str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $_POST['valor_hora'] ?? ''));
    $nfe_numero = trim($_POST['nfe_numero'] ?? '');
    $nfe_valor_input = str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $_POST['nfe_valor'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}$/', $mes_referencia)) {
        echo json_encode(['success' => false, 'message' => 'Mês de referência inválido']);
        exit;
    }

    // Busca dados do colaborador (verifica que é PJ e pega valor_hora)
    $stmt = $pdo->prepare("SELECT id, nome_completo, tipo_contrato, valor_hora, empresa_id FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);
    $colab = $stmt->fetch();
    if (!$colab) {
        echo json_encode(['success' => false, 'message' => 'Colaborador não encontrado']);
        exit;
    }

    // Define valor da hora (prioridade: input > cadastro)
    $valor_hora = $valor_hora_input !== '' ? (float)$valor_hora_input : (float)($colab['valor_hora'] ?? 0);
    if ($valor_hora <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor da hora não definido. Informe no formulário ou solicite ao admin que cadastre.']);
        exit;
    }

    // Valida anexos obrigatórios
    foreach (['planilha', 'nfe', 'boleto'] as $tipo) {
        if (!isset($_FILES[$tipo]) || !is_uploaded_file($_FILES[$tipo]['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => "Anexo obrigatório faltando: $tipo"]);
            exit;
        }
    }

    // Salva planilha temporariamente para validar
    $tmp_dir = __DIR__ . '/../uploads/tmp_planilhas/';
    if (!file_exists($tmp_dir)) @mkdir($tmp_dir, 0755, true);
    $tmp_path = $tmp_dir . 'tmp_' . time() . '_' . uniqid() . '.csv';
    if (!copy($_FILES['planilha']['tmp_name'], $tmp_path)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar planilha']);
        exit;
    }

    $validacao = validar_planilha_pagamento_pj($tmp_path, $mes_referencia);
    @unlink($tmp_path);

    if (!$validacao['valido']) {
        echo json_encode([
            'success' => false,
            'message' => 'Planilha inválida. Corrija os erros e tente novamente.',
            'erros' => $validacao['erros']
        ]);
        exit;
    }

    // Faz upload definitivo dos 3 arquivos
    $up_planilha = upload_anexo_pagamento_pj($_FILES['planilha'], $colaborador_id, 'planilha', $mes_referencia);
    if (!$up_planilha['success']) {
        echo json_encode(['success' => false, 'message' => 'Erro planilha: ' . $up_planilha['error']]);
        exit;
    }
    $up_nfe = upload_anexo_pagamento_pj($_FILES['nfe'], $colaborador_id, 'nfe', $mes_referencia);
    if (!$up_nfe['success']) {
        @unlink(__DIR__ . '/../' . $up_planilha['path']);
        echo json_encode(['success' => false, 'message' => 'Erro NFe: ' . $up_nfe['error']]);
        exit;
    }
    $up_boleto = upload_anexo_pagamento_pj($_FILES['boleto'], $colaborador_id, 'boleto', $mes_referencia);
    if (!$up_boleto['success']) {
        @unlink(__DIR__ . '/../' . $up_planilha['path']);
        @unlink(__DIR__ . '/../' . $up_nfe['path']);
        echo json_encode(['success' => false, 'message' => 'Erro Boleto: ' . $up_boleto['error']]);
        exit;
    }

    $total_horas = (float) $validacao['total_horas'];
    $valor_total = round($total_horas * $valor_hora, 2);
    $nfe_valor = $nfe_valor_input !== '' ? (float)$nfe_valor_input : null;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO solicitacoes_pagamento_pj
        (colaborador_id, mes_referencia, valor_hora_aplicado, total_horas, valor_total,
         planilha_anexo, planilha_nome_original, nfe_anexo, nfe_nome_original,
         nfe_numero, nfe_valor, boleto_anexo, boleto_nome_original,
         status, observacoes_colaborador, validacao_planilha)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'enviada', ?, ?)
    ");
    $stmt->execute([
        $colaborador_id,
        $mes_referencia,
        $valor_hora,
        $total_horas,
        $valor_total,
        $up_planilha['path'],
        $up_planilha['nome_original'],
        $up_nfe['path'],
        $up_nfe['nome_original'],
        $nfe_numero ?: null,
        $nfe_valor,
        $up_boleto['path'],
        $up_boleto['nome_original'],
        $observacoes ?: null,
        json_encode($validacao, JSON_UNESCAPED_UNICODE)
    ]);
    $solicitacao_id = $pdo->lastInsertId();

    // Insere linhas parseadas da planilha
    $stmt_linha = $pdo->prepare("
        INSERT INTO solicitacoes_pagamento_pj_horas
        (solicitacao_id, data_trabalho, hora_inicio, hora_fim, pausa_minutos, horas_trabalhadas, projeto, descricao)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($validacao['linhas'] as $linha) {
        $stmt_linha->execute([
            $solicitacao_id,
            $linha['data_trabalho'],
            $linha['hora_inicio'],
            $linha['hora_fim'],
            $linha['pausa_minutos'],
            $linha['horas_trabalhadas'],
            $linha['projeto'] ?: null,
            $linha['descricao'] ?: null
        ]);
    }

    // Log de auditoria
    log_solicitacao_pj(
        $pdo,
        $solicitacao_id,
        'criada',
        "Solicitação criada para $mes_referencia. Total: {$total_horas}h × R$ {$valor_hora} = R$ {$valor_total}",
        $usuario['id'],
        $colaborador_id
    );

    $pdo->commit();

    // Notifica admins/RH
    try {
        $stmt_admins = $pdo->prepare("SELECT id FROM usuarios WHERE role IN ('ADMIN','RH') AND status = 'ativo'");
        $stmt_admins->execute();
        $admins = $stmt_admins->fetchAll();
        foreach ($admins as $adm) {
            criar_notificacao(
                $adm['id'],
                null,
                'pagamento_pj',
                'Nova Solicitação de Pagamento PJ',
                $colab['nome_completo'] . ' enviou uma solicitação de pagamento referente a ' . $mes_referencia,
                'admin_solicitacoes_pagamento_pj.php?view=' . $solicitacao_id,
                $solicitacao_id,
                'solicitacao_pj'
            );
        }
    } catch (Exception $e) {
        error_log('Erro ao notificar admins: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Solicitação enviada com sucesso!',
        'data' => [
            'id' => $solicitacao_id,
            'total_horas' => $total_horas,
            'valor_total' => $valor_total,
            'avisos' => $validacao['avisos']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro criar solicitacao PJ: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
