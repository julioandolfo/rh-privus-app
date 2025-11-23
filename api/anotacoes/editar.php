<?php
/**
 * API para editar anotação
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para editar anotações']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $anotacao_id = (int)($_POST['id'] ?? 0);
    
    if ($anotacao_id <= 0) {
        throw new Exception('ID da anotação inválido');
    }
    
    // Busca anotação atual
    $stmt = $pdo->prepare("SELECT * FROM anotacoes_sistema WHERE id = ?");
    $stmt->execute([$anotacao_id]);
    $anotacao_atual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anotacao_atual) {
        throw new Exception('Anotação não encontrada');
    }
    
    // Verifica permissão (só pode editar se for ADMIN/RH ou se for o criador)
    if (!has_role(['ADMIN', 'RH']) && $anotacao_atual['usuario_id'] != $usuario['id']) {
        throw new Exception('Você não tem permissão para editar esta anotação');
    }
    
    $titulo = trim($_POST['titulo'] ?? $anotacao_atual['titulo']);
    $conteudo = trim($_POST['conteudo'] ?? $anotacao_atual['conteudo']);
    $tipo = $_POST['tipo'] ?? $anotacao_atual['tipo'];
    $cor = $_POST['cor'] ?? $anotacao_atual['cor'];
    $prioridade = $_POST['prioridade'] ?? $anotacao_atual['prioridade'];
    $categoria = trim($_POST['categoria'] ?? $anotacao_atual['categoria']);
    $tags = $_POST['tags'] ?? $anotacao_atual['tags'];
    $data_vencimento = isset($_POST['data_vencimento']) ? ($_POST['data_vencimento'] ?: null) : $anotacao_atual['data_vencimento'];
    $fixada = isset($_POST['fixada']) ? (int)$_POST['fixada'] : $anotacao_atual['fixada'];
    $status = $_POST['status'] ?? $anotacao_atual['status'];
    
    // Notificações
    $notificar_email = isset($_POST['notificar_email']) ? (int)$_POST['notificar_email'] : $anotacao_atual['notificar_email'];
    $notificar_push = isset($_POST['notificar_push']) ? (int)$_POST['notificar_push'] : $anotacao_atual['notificar_push'];
    $data_notificacao = isset($_POST['data_notificacao']) ? ($_POST['data_notificacao'] ?: null) : $anotacao_atual['data_notificacao'];
    
    // Destinatários
    $publico_alvo = $_POST['publico_alvo'] ?? $anotacao_atual['publico_alvo'];
    
    // Processa empresas, setores e cargos (múltiplos)
    $empresas_ids = [];
    $setores_ids = [];
    $cargos_ids = [];
    
    if (!empty($_POST['empresas_ids'])) {
        if (is_string($_POST['empresas_ids'])) {
            $empresas_ids = json_decode($_POST['empresas_ids'], true) ?: [];
        } elseif (is_array($_POST['empresas_ids'])) {
            $empresas_ids = $_POST['empresas_ids'];
        }
    } elseif (isset($anotacao_atual['empresas_ids']) && !empty($anotacao_atual['empresas_ids'])) {
        $empresas_ids = json_decode($anotacao_atual['empresas_ids'], true) ?: [];
    }
    
    if (!empty($_POST['setores_ids'])) {
        if (is_string($_POST['setores_ids'])) {
            $setores_ids = json_decode($_POST['setores_ids'], true) ?: [];
        } elseif (is_array($_POST['setores_ids'])) {
            $setores_ids = $_POST['setores_ids'];
        }
    } elseif (isset($anotacao_atual['setores_ids']) && !empty($anotacao_atual['setores_ids'])) {
        $setores_ids = json_decode($anotacao_atual['setores_ids'], true) ?: [];
    }
    
    if (!empty($_POST['cargos_ids'])) {
        if (is_string($_POST['cargos_ids'])) {
            $cargos_ids = json_decode($_POST['cargos_ids'], true) ?: [];
        } elseif (is_array($_POST['cargos_ids'])) {
            $cargos_ids = $_POST['cargos_ids'];
        }
    } elseif (isset($anotacao_atual['cargos_ids']) && !empty($anotacao_atual['cargos_ids'])) {
        $cargos_ids = json_decode($anotacao_atual['cargos_ids'], true) ?: [];
    }
    
    // Para compatibilidade com campos únicos (mantém null se múltiplos)
    $empresa_id = !empty($empresas_ids) && count($empresas_ids) === 1 ? (int)$empresas_ids[0] : (isset($_POST['empresa_id']) ? ($_POST['empresa_id'] ? (int)$_POST['empresa_id'] : null) : $anotacao_atual['empresa_id']);
    $setor_id = !empty($setores_ids) && count($setores_ids) === 1 ? (int)$setores_ids[0] : (isset($_POST['setor_id']) ? ($_POST['setor_id'] ? (int)$_POST['setor_id'] : null) : $anotacao_atual['setor_id']);
    $cargo_id = !empty($cargos_ids) && count($cargos_ids) === 1 ? (int)$cargos_ids[0] : (isset($_POST['cargo_id']) ? ($_POST['cargo_id'] ? (int)$_POST['cargo_id'] : null) : $anotacao_atual['cargo_id']);
    
    // Salva arrays como JSON para uso futuro
    $empresas_ids_json = !empty($empresas_ids) ? json_encode($empresas_ids) : null;
    $setores_ids_json = !empty($setores_ids) ? json_encode($setores_ids) : null;
    $cargos_ids_json = !empty($cargos_ids) ? json_encode($cargos_ids) : null;
    
    // Processa destinatários
    $destinatarios_usuarios_array = [];
    $destinatarios_colaboradores_array = [];
    
    // Se for "atribuir_mim", adiciona o usuário atual
    if ($publico_alvo === 'atribuir_mim') {
        $destinatarios_usuarios_array[] = $usuario['id'];
        $publico_alvo = 'especifico'; // Salva como específico no banco
    } else {
        // Processa destinatários enviados
        if (isset($_POST['destinatarios_usuarios'])) {
            if (is_string($_POST['destinatarios_usuarios'])) {
                $destinatarios_usuarios_array = json_decode($_POST['destinatarios_usuarios'], true) ?: [];
            } elseif (is_array($_POST['destinatarios_usuarios'])) {
                $destinatarios_usuarios_array = $_POST['destinatarios_usuarios'];
            }
        } else {
            // Mantém os destinatários atuais se não foram enviados
            if (!empty($anotacao_atual['destinatarios_usuarios'])) {
                $destinatarios_usuarios_array = json_decode($anotacao_atual['destinatarios_usuarios'], true) ?: [];
            }
        }
        
        if (isset($_POST['destinatarios_colaboradores'])) {
            if (is_string($_POST['destinatarios_colaboradores'])) {
                $destinatarios_colaboradores_array = json_decode($_POST['destinatarios_colaboradores'], true) ?: [];
            } elseif (is_array($_POST['destinatarios_colaboradores'])) {
                $destinatarios_colaboradores_array = $_POST['destinatarios_colaboradores'];
            }
        } else {
            // Mantém os destinatários atuais se não foram enviados
            if (!empty($anotacao_atual['destinatarios_colaboradores'])) {
                $destinatarios_colaboradores_array = json_decode($anotacao_atual['destinatarios_colaboradores'], true) ?: [];
            }
        }
    }
    
    $destinatarios_usuarios = !empty($destinatarios_usuarios_array) ? json_encode($destinatarios_usuarios_array) : null;
    $destinatarios_colaboradores = !empty($destinatarios_colaboradores_array) ? json_encode($destinatarios_colaboradores_array) : null;
    
    // Validações
    if (empty($titulo)) {
        throw new Exception('Título é obrigatório');
    }
    if (empty($conteudo)) {
        throw new Exception('Conteúdo é obrigatório');
    }
    
    // Processa tags
    if ($tags && is_string($tags)) {
        $tags_array = json_decode($tags, true);
        if (is_array($tags_array)) {
            $tags = json_encode($tags_array);
        }
    } elseif ($tags && is_array($tags)) {
        $tags = json_encode($tags);
    }
    
    // Se status mudou para concluída, registra data de conclusão
    $data_conclusao = $anotacao_atual['data_conclusao'];
    if ($status === 'concluida' && $anotacao_atual['status'] !== 'concluida') {
        $data_conclusao = date('Y-m-d H:i:s');
    } elseif ($status !== 'concluida') {
        $data_conclusao = null;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Salva dados anteriores para histórico
        $dados_anteriores = json_encode([
            'titulo' => $anotacao_atual['titulo'],
            'conteudo' => $anotacao_atual['conteudo'],
            'tipo' => $anotacao_atual['tipo'],
            'prioridade' => $anotacao_atual['prioridade'],
            'status' => $anotacao_atual['status']
        ]);
        
        // Verifica se campos de múltiplos IDs existem na tabela
        $stmt_check = $pdo->query("SHOW COLUMNS FROM anotacoes_sistema LIKE 'empresas_ids'");
        $tem_multiplos = $stmt_check->rowCount() > 0;
        
        // Atualiza anotação
        if ($tem_multiplos) {
            $stmt = $pdo->prepare("
                UPDATE anotacoes_sistema SET
                    titulo = ?, conteudo = ?, tipo = ?, cor = ?, prioridade = ?,
                    categoria = ?, tags = ?, data_vencimento = ?, fixada = ?,
                    status = ?, data_conclusao = ?,
                    notificar_email = ?, notificar_push = ?, data_notificacao = ?,
                    publico_alvo = ?, empresa_id = ?, setor_id = ?, cargo_id = ?,
                    empresas_ids = ?, setores_ids = ?, cargos_ids = ?,
                    destinatarios_usuarios = ?, destinatarios_colaboradores = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $titulo, $conteudo, $tipo, $cor, $prioridade,
                $categoria, $tags, $data_vencimento, $fixada,
                $status, $data_conclusao,
                $notificar_email, $notificar_push, $data_notificacao,
                $publico_alvo, $empresa_id, $setor_id, $cargo_id,
                $empresas_ids_json, $setores_ids_json, $cargos_ids_json,
                $destinatarios_usuarios, $destinatarios_colaboradores,
                $anotacao_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE anotacoes_sistema SET
                    titulo = ?, conteudo = ?, tipo = ?, cor = ?, prioridade = ?,
                    categoria = ?, tags = ?, data_vencimento = ?, fixada = ?,
                    status = ?, data_conclusao = ?,
                    notificar_email = ?, notificar_push = ?, data_notificacao = ?,
                    publico_alvo = ?, empresa_id = ?, setor_id = ?, cargo_id = ?,
                    destinatarios_usuarios = ?, destinatarios_colaboradores = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $titulo, $conteudo, $tipo, $cor, $prioridade,
                $categoria, $tags, $data_vencimento, $fixada,
                $status, $data_conclusao,
                $notificar_email, $notificar_push, $data_notificacao,
                $publico_alvo, $empresa_id, $setor_id, $cargo_id,
                $destinatarios_usuarios, $destinatarios_colaboradores,
                $anotacao_id
            ]);
        }
        
        // Registra no histórico
        $stmt_hist = $pdo->prepare("
            INSERT INTO anotacoes_historico (anotacao_id, usuario_id, acao, dados_anteriores, dados_novos)
            VALUES (?, ?, 'editada', ?, ?)
        ");
        $stmt_hist->execute([
            $anotacao_id,
            $usuario['id'],
            $dados_anteriores,
            json_encode([
                'titulo' => $titulo,
                'tipo' => $tipo,
                'prioridade' => $prioridade,
                'status' => $status
            ])
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Anotação atualizada com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

