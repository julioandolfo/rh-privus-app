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
    $empresa_id = isset($_POST['empresa_id']) ? ($_POST['empresa_id'] ? (int)$_POST['empresa_id'] : null) : $anotacao_atual['empresa_id'];
    $setor_id = isset($_POST['setor_id']) ? ($_POST['setor_id'] ? (int)$_POST['setor_id'] : null) : $anotacao_atual['setor_id'];
    $cargo_id = isset($_POST['cargo_id']) ? ($_POST['cargo_id'] ? (int)$_POST['cargo_id'] : null) : $anotacao_atual['cargo_id'];
    
    if (!empty($_POST['destinatarios_usuarios'])) {
        $destinatarios_usuarios = json_encode($_POST['destinatarios_usuarios']);
    } else {
        $destinatarios_usuarios = $anotacao_atual['destinatarios_usuarios'];
    }
    
    if (!empty($_POST['destinatarios_colaboradores'])) {
        $destinatarios_colaboradores = json_encode($_POST['destinatarios_colaboradores']);
    } else {
        $destinatarios_colaboradores = $anotacao_atual['destinatarios_colaboradores'];
    }
    
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
        
        // Atualiza anotação
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

