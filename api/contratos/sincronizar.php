<?php
/**
 * API: Sincroniza status dos contratos com Autentique sob demanda (botão admin)
 * Reaproveita a mesma lógica do cron/verificar_assinaturas_contratos.php
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/autentique_service.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$usuario = $_SESSION['usuario'];

if (!in_array($usuario['role'] ?? '', ['ADMIN', 'RH'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();

    // Verifica configuração Autentique
    $stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Autentique não está configurado']);
        exit;
    }

    // Busca contratos pendentes
    $stmt = $pdo->prepare("
        SELECT c.id, c.status, c.autentique_document_id
        FROM contratos c
        WHERE c.status IN ('enviado', 'aguardando')
          AND c.autentique_document_id IS NOT NULL
        ORDER BY c.created_at ASC
    ");
    $stmt->execute();
    $contratos = $stmt->fetchAll();

    $total = count($contratos);
    $atualizados = 0;
    $sem_mudanca = 0;
    $erros = 0;

    if ($total === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Nenhum contrato pendente para sincronizar',
            'total' => 0,
            'atualizados' => 0,
            'sem_mudanca' => 0,
            'erros' => 0
        ]);
        exit;
    }

    $service = new AutentiqueService();

    foreach ($contratos as $contrato) {
        $contrato_id = $contrato['id'];
        $document_id = $contrato['autentique_document_id'];
        $status_anterior = $contrato['status'];

        try {
            $status_autentique = $service->consultarStatus($document_id);
            if (!$status_autentique) {
                $erros++;
                continue;
            }

            $signers_api = $status_autentique['signers'] ?? [];
            if (empty($signers_api)) {
                $sem_mudanca++;
                continue;
            }

            foreach ($signers_api as $signer) {
                $signer_email = $signer['email'] ?? null;
                if (!$signer_email) continue;

                $stmt_check = $pdo->prepare("
                    SELECT id FROM contratos_signatarios
                    WHERE contrato_id = ? AND LOWER(email) = LOWER(?)
                ");
                $stmt_check->execute([$contrato_id, $signer_email]);
                if (!$stmt_check->fetch()) continue;

                $stmt_update = $pdo->prepare("
                    UPDATE contratos_signatarios
                    SET assinado = ?,
                        data_assinatura = COALESCE(?, data_assinatura),
                        autentique_signer_id = COALESCE(?, autentique_signer_id),
                        link_publico = COALESCE(?, link_publico)
                    WHERE LOWER(email) = LOWER(?) AND contrato_id = ?
                ");
                $stmt_update->execute([
                    !empty($signer['signed']) ? 1 : 0,
                    $signer['signedAt'] ?? null,
                    $signer['id'] ?? null,
                    $signer['link'] ?? null,
                    $signer_email,
                    $contrato_id
                ]);
            }

            // Recalcula status
            $stmt_count = $pdo->prepare("
                SELECT COUNT(*) as total, SUM(assinado) as assinados
                FROM contratos_signatarios WHERE contrato_id = ?
            ");
            $stmt_count->execute([$contrato_id]);
            $check = $stmt_count->fetch();
            $total_sig = (int)($check['total'] ?? 0);
            $assinados = (int)($check['assinados'] ?? 0);

            $novo_status = null;
            if ($total_sig > 0 && $assinados === $total_sig) {
                $novo_status = 'assinado';
            } elseif ($assinados > 0) {
                $novo_status = 'aguardando';
            }

            if ($novo_status && $novo_status !== $status_anterior) {
                $stmt_up = $pdo->prepare("UPDATE contratos SET status = ? WHERE id = ?");
                $stmt_up->execute([$novo_status, $contrato_id]);
                $atualizados++;

                // Registra evento
                try {
                    $stmt_ev = $pdo->prepare("
                        INSERT INTO contratos_eventos (contrato_id, tipo_evento, dados_json)
                        VALUES (?, 'manual.sincronizacao', ?)
                    ");
                    $stmt_ev->execute([$contrato_id, json_encode([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'usuario_id' => $usuario['id'],
                        'status_anterior' => $status_anterior,
                        'status_novo' => $novo_status,
                        'assinados' => $assinados,
                        'total' => $total_sig
                    ], JSON_UNESCAPED_UNICODE)]);
                } catch (Exception $e) {}
            } else {
                $sem_mudanca++;
            }

            usleep(300000); // 300ms entre chamadas
        } catch (Exception $e) {
            $erros++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Sincronização concluída: $atualizados atualizado(s), $sem_mudanca sem mudança, $erros erro(s)",
        'total' => $total,
        'atualizados' => $atualizados,
        'sem_mudanca' => $sem_mudanca,
        'erros' => $erros
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
