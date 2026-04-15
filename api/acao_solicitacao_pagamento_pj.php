<?php
/**
 * API: Ações administrativas em uma solicitação PJ
 * Ações: aprovar, rejeitar, marcar_paga, get_detalhes
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
    $acao = $_REQUEST['acao'] ?? '';
    $solicitacao_id = (int)($_REQUEST['solicitacao_id'] ?? 0);

    if (!$solicitacao_id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    // Busca a solicitação
    $stmt = $pdo->prepare("
        SELECT s.*, c.nome_completo as colaborador_nome, c.empresa_id, c.tipo_contrato
        FROM solicitacoes_pagamento_pj s
        INNER JOIN colaboradores c ON s.colaborador_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$solicitacao_id]);
    $sol = $stmt->fetch();
    if (!$sol) {
        echo json_encode(['success' => false, 'message' => 'Solicitação não encontrada']);
        exit;
    }

    // Verifica permissão: ADMIN/RH ou o próprio colaborador (somente get_detalhes)
    $is_admin = in_array($usuario['role'] ?? '', ['ADMIN', 'RH']);
    $is_dono = ($usuario['role'] === 'COLABORADOR' && (int)$usuario['colaborador_id'] === (int)$sol['colaborador_id']);

    if (!$is_admin && !($is_dono && $acao === 'get_detalhes')) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }

    if ($acao === 'get_detalhes') {
        // Busca linhas da planilha
        $stmt = $pdo->prepare("SELECT * FROM solicitacoes_pagamento_pj_horas WHERE solicitacao_id = ? ORDER BY data_trabalho");
        $stmt->execute([$solicitacao_id]);
        $linhas = $stmt->fetchAll();

        // Busca log
        $stmt = $pdo->prepare("
            SELECT l.*, u.nome as usuario_nome
            FROM solicitacoes_pagamento_pj_log l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE l.solicitacao_id = ?
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$solicitacao_id]);
        $log = $stmt->fetchAll();

        // Conferência: busca solicitação anterior aprovada/paga (apenas para admin)
        $solicitacao_anterior = null;
        if ($is_admin) {
            $stmt = $pdo->prepare("
                SELECT id, mes_referencia, total_horas, valor_hora_aplicado, valor_total, status, data_aprovacao
                FROM solicitacoes_pagamento_pj
                WHERE colaborador_id = ?
                  AND id <> ?
                  AND status IN ('aprovada', 'paga')
                ORDER BY mes_referencia DESC, data_aprovacao DESC
                LIMIT 1
            ");
            $stmt->execute([$sol['colaborador_id'], $solicitacao_id]);
            $solicitacao_anterior = $stmt->fetch() ?: null;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'solicitacao' => $sol,
                'linhas' => $linhas,
                'log' => $log,
                'solicitacao_anterior' => $solicitacao_anterior
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Daqui pra baixo: só admin/rh
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }

    if ($acao === 'aprovar') {
        if (!in_array($sol['status'], ['enviada', 'em_analise'])) {
            echo json_encode(['success' => false, 'message' => 'Solicitação não pode ser aprovada (status atual: ' . $sol['status'] . ')']);
            exit;
        }

        $gerar_fechamento = !empty($_POST['gerar_fechamento']);

        $pdo->beginTransaction();

        $fechamento_id_gerado = null;
        if ($gerar_fechamento) {
            // Cria fechamento extra individual referente a este pagamento
            $stmt = $pdo->prepare("
                INSERT INTO fechamentos_pagamento
                (empresa_id, tipo_fechamento, subtipo_fechamento, mes_referencia, data_fechamento, descricao, total_colaboradores, usuario_id, status)
                VALUES (?, 'extra', 'individual', ?, CURDATE(), ?, 1, ?, 'fechado')
            ");
            $descricao = "Pagamento PJ - Solicitação #{$solicitacao_id} - {$sol['colaborador_nome']}";
            $stmt->execute([
                $sol['empresa_id'],
                $sol['mes_referencia'],
                $descricao,
                $usuario['id']
            ]);
            $fechamento_id_gerado = $pdo->lastInsertId();

            // Cria item do fechamento
            $stmt = $pdo->prepare("
                INSERT INTO fechamentos_pagamento_itens
                (fechamento_id, colaborador_id, salario_base, horas_extras, valor_horas_extras, descontos, valor_total, valor_manual, inclui_salario, inclui_horas_extras, inclui_bonus_automaticos, motivo)
                VALUES (?, ?, 0, ?, 0, 0, ?, ?, 0, 0, 0, ?)
            ");
            $motivo_item = "Solicitação PJ #{$solicitacao_id}: {$sol['total_horas']}h × R$ {$sol['valor_hora_aplicado']}";
            $stmt->execute([
                $fechamento_id_gerado,
                $sol['colaborador_id'],
                $sol['total_horas'],
                $sol['valor_total'],
                $sol['valor_total'],
                $motivo_item
            ]);

            // Atualiza total_pagamento do fechamento
            $stmt = $pdo->prepare("UPDATE fechamentos_pagamento SET total_pagamento = ? WHERE id = ?");
            $stmt->execute([$sol['valor_total'], $fechamento_id_gerado]);
        }

        $stmt = $pdo->prepare("
            UPDATE solicitacoes_pagamento_pj
            SET status = 'aprovada',
                aprovado_por = ?,
                data_aprovacao = NOW(),
                fechamento_pagamento_id = ?,
                observacoes_admin = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $usuario['id'],
            $fechamento_id_gerado,
            trim($_POST['observacoes_admin'] ?? '') ?: null,
            $solicitacao_id
        ]);

        log_solicitacao_pj(
            $pdo,
            $solicitacao_id,
            'aprovada',
            'Aprovada por ' . ($usuario['nome'] ?? 'admin') . ($fechamento_id_gerado ? " | Fechamento gerado #$fechamento_id_gerado" : ''),
            $usuario['id'],
            $sol['colaborador_id']
        );

        $pdo->commit();

        // Notifica o colaborador
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ? LIMIT 1");
            $stmt->execute([$sol['colaborador_id']]);
            $u = $stmt->fetch();
            if ($u) {
                criar_notificacao(
                    $u['id'],
                    $sol['colaborador_id'],
                    'pagamento_pj',
                    'Solicitação Aprovada',
                    'Sua solicitação de pagamento PJ de ' . $sol['mes_referencia'] . ' foi aprovada.',
                    'solicitar_pagamento_pj.php',
                    $solicitacao_id,
                    'solicitacao_pj'
                );
            }
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'message' => 'Solicitação aprovada' . ($fechamento_id_gerado ? ' e fechamento gerado' : ''),
            'fechamento_id' => $fechamento_id_gerado
        ]);
        exit;
    }

    if ($acao === 'rejeitar') {
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            echo json_encode(['success' => false, 'message' => 'Informe o motivo da rejeição']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE solicitacoes_pagamento_pj
            SET status = 'rejeitada', motivo_rejeicao = ?, aprovado_por = ?, data_aprovacao = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$motivo, $usuario['id'], $solicitacao_id]);

        log_solicitacao_pj($pdo, $solicitacao_id, 'rejeitada', $motivo, $usuario['id'], $sol['colaborador_id']);

        // Notifica o colaborador
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ? LIMIT 1");
            $stmt->execute([$sol['colaborador_id']]);
            $u = $stmt->fetch();
            if ($u) {
                criar_notificacao(
                    $u['id'],
                    $sol['colaborador_id'],
                    'pagamento_pj',
                    'Solicitação Rejeitada',
                    'Sua solicitação de pagamento PJ de ' . $sol['mes_referencia'] . ' foi rejeitada. Motivo: ' . $motivo,
                    'solicitar_pagamento_pj.php',
                    $solicitacao_id,
                    'solicitacao_pj'
                );
            }
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Solicitação rejeitada']);
        exit;
    }

    if ($acao === 'marcar_paga') {
        if ($sol['status'] !== 'aprovada') {
            echo json_encode(['success' => false, 'message' => 'Apenas solicitações aprovadas podem ser marcadas como pagas']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE solicitacoes_pagamento_pj SET status = 'paga', data_pagamento = NOW() WHERE id = ?");
        $stmt->execute([$solicitacao_id]);

        log_solicitacao_pj($pdo, $solicitacao_id, 'paga', 'Marcada como paga', $usuario['id'], $sol['colaborador_id']);

        echo json_encode(['success' => true, 'message' => 'Marcada como paga']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação inválida']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro acao solicitacao PJ: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
