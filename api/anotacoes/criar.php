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
    $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
    $setor_id = !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null;
    $cargo_id = !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null;
    $destinatarios_usuarios = !empty($_POST['destinatarios_usuarios']) ? json_encode($_POST['destinatarios_usuarios']) : null;
    $destinatarios_colaboradores = !empty($_POST['destinatarios_colaboradores']) ? json_encode($_POST['destinatarios_colaboradores']) : null;
    
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
        // Insere anotação
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

