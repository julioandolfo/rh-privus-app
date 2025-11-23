<?php
/**
 * API para criar nova anotação
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
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para criar anotações']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $titulo = trim($_POST['titulo'] ?? '');
    $conteudo = trim($_POST['conteudo'] ?? '');
    $tipo = $_POST['tipo'] ?? 'geral';
    $cor = $_POST['cor'] ?? null;
    $prioridade = $_POST['prioridade'] ?? 'media';
    $categoria = trim($_POST['categoria'] ?? '');
    $tags = $_POST['tags'] ?? null;
    $data_vencimento = !empty($_POST['data_vencimento']) ? $_POST['data_vencimento'] : null;
    $fixada = isset($_POST['fixada']) ? (int)$_POST['fixada'] : 0;
    
    // Notificações
    $notificar_email = isset($_POST['notificar_email']) ? (int)$_POST['notificar_email'] : 0;
    $notificar_push = isset($_POST['notificar_push']) ? (int)$_POST['notificar_push'] : 0;
    $data_notificacao = !empty($_POST['data_notificacao']) ? $_POST['data_notificacao'] : null;
    
    // Destinatários
    $publico_alvo = $_POST['publico_alvo'] ?? 'especifico';
    
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
    }
    
    if (!empty($_POST['setores_ids'])) {
        if (is_string($_POST['setores_ids'])) {
            $setores_ids = json_decode($_POST['setores_ids'], true) ?: [];
        } elseif (is_array($_POST['setores_ids'])) {
            $setores_ids = $_POST['setores_ids'];
        }
    }
    
    if (!empty($_POST['cargos_ids'])) {
        if (is_string($_POST['cargos_ids'])) {
            $cargos_ids = json_decode($_POST['cargos_ids'], true) ?: [];
        } elseif (is_array($_POST['cargos_ids'])) {
            $cargos_ids = $_POST['cargos_ids'];
        }
    }
    
    // Para compatibilidade com campos únicos (mantém null se múltiplos)
    $empresa_id = !empty($empresas_ids) && count($empresas_ids) === 1 ? (int)$empresas_ids[0] : null;
    $setor_id = !empty($setores_ids) && count($setores_ids) === 1 ? (int)$setores_ids[0] : null;
    $cargo_id = !empty($cargos_ids) && count($cargos_ids) === 1 ? (int)$cargos_ids[0] : null;
    
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
        if (!empty($_POST['destinatarios_usuarios'])) {
            if (is_string($_POST['destinatarios_usuarios'])) {
                $destinatarios_usuarios_array = json_decode($_POST['destinatarios_usuarios'], true) ?: [];
            } elseif (is_array($_POST['destinatarios_usuarios'])) {
                $destinatarios_usuarios_array = $_POST['destinatarios_usuarios'];
            }
        }
        
        if (!empty($_POST['destinatarios_colaboradores'])) {
            if (is_string($_POST['destinatarios_colaboradores'])) {
                $destinatarios_colaboradores_array = json_decode($_POST['destinatarios_colaboradores'], true) ?: [];
            } elseif (is_array($_POST['destinatarios_colaboradores'])) {
                $destinatarios_colaboradores_array = $_POST['destinatarios_colaboradores'];
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
    
    // Valida tipo
    $tipos_validos = ['geral', 'lembrete', 'importante', 'urgente', 'informacao'];
    if (!in_array($tipo, $tipos_validos)) {
        $tipo = 'geral';
    }
    
    // Valida prioridade
    $prioridades_validas = ['baixa', 'media', 'alta', 'urgente'];
    if (!in_array($prioridade, $prioridades_validas)) {
        $prioridade = 'media';
    }
    
    // Processa tags
    if ($tags && is_string($tags)) {
        $tags_array = json_decode($tags, true);
        if (is_array($tags_array)) {
            $tags = json_encode($tags_array);
        } else {
            $tags = null;
        }
    } elseif ($tags && is_array($tags)) {
        $tags = json_encode($tags);
    } else {
        $tags = null;
    }
    
    // Valida data de notificação
    if ($data_notificacao) {
        $data_notificacao_dt = new DateTime($data_notificacao);
        $agora = new DateTime();
        if ($data_notificacao_dt < $agora) {
            throw new Exception('A data de notificação não pode ser no passado');
        }
    }
    
    // Se não tem data de notificação mas quer notificar, agenda para agora
    if (($notificar_email || $notificar_push) && !$data_notificacao) {
        $data_notificacao = date('Y-m-d H:i:s');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Verifica se campos de múltiplos IDs existem na tabela
        $stmt_check = $pdo->query("SHOW COLUMNS FROM anotacoes_sistema LIKE 'empresas_ids'");
        $tem_multiplos = $stmt_check->rowCount() > 0;
        
        // Insere anotação
        if ($tem_multiplos) {
            $stmt = $pdo->prepare("
                INSERT INTO anotacoes_sistema (
                    usuario_id, titulo, conteudo, tipo, cor, prioridade, categoria, tags,
                    data_vencimento, fixada, notificar_email, notificar_push, data_notificacao,
                    publico_alvo, empresa_id, setor_id, cargo_id,
                    empresas_ids, setores_ids, cargos_ids,
                    destinatarios_usuarios, destinatarios_colaboradores
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $usuario['id'], $titulo, $conteudo, $tipo, $cor, $prioridade, $categoria, $tags,
                $data_vencimento, $fixada, $notificar_email, $notificar_push, $data_notificacao,
                $publico_alvo, $empresa_id, $setor_id, $cargo_id,
                $empresas_ids_json, $setores_ids_json, $cargos_ids_json,
                $destinatarios_usuarios, $destinatarios_colaboradores
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO anotacoes_sistema (
                    usuario_id, titulo, conteudo, tipo, cor, prioridade, categoria, tags,
                    data_vencimento, fixada, notificar_email, notificar_push, data_notificacao,
                    publico_alvo, empresa_id, setor_id, cargo_id,
                    destinatarios_usuarios, destinatarios_colaboradores
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $usuario['id'], $titulo, $conteudo, $tipo, $cor, $prioridade, $categoria, $tags,
                $data_vencimento, $fixada, $notificar_email, $notificar_push, $data_notificacao,
                $publico_alvo, $empresa_id, $setor_id, $cargo_id,
                $destinatarios_usuarios, $destinatarios_colaboradores
            ]);
        }
        
        $anotacao_id = $pdo->lastInsertId();
        
        // Registra no histórico
        $stmt_hist = $pdo->prepare("
            INSERT INTO anotacoes_historico (anotacao_id, usuario_id, acao, dados_novos)
            VALUES (?, ?, 'criada', ?)
        ");
        $stmt_hist->execute([
            $anotacao_id,
            $usuario['id'],
            json_encode([
                'titulo' => $titulo,
                'tipo' => $tipo,
                'prioridade' => $prioridade
            ])
        ]);
        
        // Se notificação é para agora ou não tem data agendada, envia imediatamente
        if ($notificar_email || $notificar_push) {
            if (!$data_notificacao || strtotime($data_notificacao) <= time()) {
                require_once __DIR__ . '/../../includes/notificacoes.php';
                enviar_notificacoes_anotacao($anotacao_id, $pdo);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Anotação criada com sucesso!',
            'anotacao_id' => $anotacao_id
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

