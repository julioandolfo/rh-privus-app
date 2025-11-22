<?php
/**
 * API para Criar Celebração
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/engajamento.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verifica se módulo está ativo
if (!engajamento_modulo_ativo('celebracoes')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Módulo de celebrações está desativado']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $request_id = $_POST['request_id'] ?? null;
    $publico_alvo = $_POST['publico_alvo'] ?? 'especifico';
    $destinatario_id = (int)($_POST['destinatario_id'] ?? 0);
    $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
    $setor_id = !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null;
    $cargo_id = !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null;
    $tipo = $_POST['tipo'] ?? 'reconhecimento';
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $data_celebração = $_POST['data_celebração'] ?? date('Y-m-d');
    
    if (empty($titulo)) {
        throw new Exception('Título é obrigatório');
    }
    
    // Proteção ATÔMICA contra requisições duplicadas usando GET_LOCK do MySQL
    if ($request_id) {
        $lockName = 'celebração_' . $request_id;
        $stmt = $pdo->prepare("SELECT GET_LOCK(?, 0) as lock_result");
        $stmt->execute([$lockName]);
        $lockResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lockResult['lock_result'] != 1) {
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Celebração já está sendo processada.'
            ]);
            return;
        }
    }
    
    // Processa upload de imagem se houver
    $imagem = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/celebracoes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($ext, $allowed)) {
            throw new Exception('Formato de imagem não permitido. Use JPG, PNG, GIF ou WEBP');
        }
        
        // Valida tamanho (máximo 5MB)
        if ($_FILES['imagem']['size'] > 5 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 5MB');
        }
        
        $filename = uniqid() . '_' . time() . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $filepath)) {
            $imagem = 'uploads/celebracoes/' . $filename;
        } else {
            throw new Exception('Erro ao fazer upload da imagem');
        }
    }
    
    // Valida público alvo
    if ($publico_alvo === 'especifico' && $destinatario_id <= 0) {
        throw new Exception('Destinatário é obrigatório');
    }
    if ($publico_alvo === 'empresa' && !$empresa_id) {
        throw new Exception('Empresa é obrigatória');
    }
    if ($publico_alvo === 'setor' && !$setor_id) {
        throw new Exception('Setor é obrigatório');
    }
    if ($publico_alvo === 'cargo' && !$cargo_id) {
        throw new Exception('Cargo é obrigatório');
    }
    
    $remetente_id = $usuario['colaborador_id'] ?? null;
    $remetente_usuario_id = $usuario['id'] ?? null;
    
    // Busca colaboradores alvo
    $colaboradores_alvo = [];
    if ($publico_alvo === 'todos') {
        $stmt = $pdo->query("SELECT id FROM colaboradores WHERE status = 'ativo'");
        $colaboradores_alvo = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($publico_alvo === 'especifico') {
        $colaboradores_alvo = [$destinatario_id];
    } elseif ($publico_alvo === 'empresa') {
        $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE empresa_id = ? AND status = 'ativo'");
        $stmt->execute([$empresa_id]);
        $colaboradores_alvo = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($publico_alvo === 'setor') {
        $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE setor_id = ? AND status = 'ativo'");
        $stmt->execute([$setor_id]);
        $colaboradores_alvo = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($publico_alvo === 'cargo') {
        $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE cargo_id = ? AND status = 'ativo'");
        $stmt->execute([$cargo_id]);
        $colaboradores_alvo = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    if (empty($colaboradores_alvo)) {
        throw new Exception('Nenhum colaborador encontrado para o público alvo selecionado');
    }
    
    // Verifica duplicação: celebração idêntica nos últimos 30 segundos
    $where_dup = ["remetente_usuario_id = ?", "titulo = ?", "data_celebração = ?", "created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)"];
    $params_dup = [$usuario['id'], $titulo, $data_celebração];
    
    if ($publico_alvo === 'especifico' && $destinatario_id > 0) {
        $where_dup[] = "destinatario_id = ?";
        $params_dup[] = $destinatario_id;
    } elseif ($publico_alvo === 'empresa' && $empresa_id) {
        $where_dup[] = "empresa_id = ?";
        $params_dup[] = $empresa_id;
    } elseif ($publico_alvo === 'setor' && $setor_id) {
        $where_dup[] = "setor_id = ?";
        $params_dup[] = $setor_id;
    } elseif ($publico_alvo === 'cargo' && $cargo_id) {
        $where_dup[] = "cargo_id = ?";
        $params_dup[] = $cargo_id;
    }
    
    $stmt_check = $pdo->prepare("SELECT id FROM celebracoes WHERE " . implode(' AND ', $where_dup) . " LIMIT 1");
    $stmt_check->execute($params_dup);
    if ($stmt_check->fetch()) {
        if ($request_id) {
            $lockName = 'celebração_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
        }
        echo json_encode([
            'success' => true,
            'already_processed' => true,
            'message' => 'Celebração já foi registrada recentemente.'
        ]);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Verifica novamente dentro da transação com lock (double-check)
        $stmt_check2 = $pdo->prepare("SELECT id FROM celebracoes WHERE " . implode(' AND ', $where_dup) . " LIMIT 1 FOR UPDATE");
        $stmt_check2->execute($params_dup);
        if ($stmt_check2->fetch()) {
            $pdo->rollBack();
            if ($request_id) {
                $lockName = 'celebração_' . $request_id;
                $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
            }
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Celebração já foi registrada recentemente.'
            ]);
            return;
        }
        
        $celebração_ids = [];
        
        // Cria celebração para cada colaborador alvo
        foreach ($colaboradores_alvo as $colab_id) {
            $stmt = $pdo->prepare("
                INSERT INTO celebracoes (
                    remetente_id, remetente_usuario_id, destinatario_id,
                    tipo, titulo, descricao, data_celebração, status,
                    empresa_id, setor_id, cargo_id, publico_alvo, imagem
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo', ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $remetente_id, $remetente_usuario_id, $colab_id,
                $tipo, $titulo, $descricao, $data_celebração,
                $empresa_id, $setor_id, $cargo_id, $publico_alvo, $imagem
            ]);
            
            $celebração_ids[] = $pdo->lastInsertId();
        }
        
        $primeira_celebração_id = $celebração_ids[0];
        
        // Envia notificações
        $enviar_email = engajamento_enviar_email('celebracoes');
        $enviar_push = engajamento_enviar_push('celebracoes');
        
        if ($enviar_email || $enviar_push) {
            require_once __DIR__ . '/../../includes/push_notifications.php';
            require_once __DIR__ . '/../../includes/email.php';
            
            // Busca dados do remetente
            $remetente_nome = 'Sistema';
            if ($remetente_id) {
                $stmt = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
                $stmt->execute([$remetente_id]);
                $remetente = $stmt->fetch();
                if ($remetente) {
                    $remetente_nome = $remetente['nome_completo'];
                }
            } elseif ($remetente_usuario_id) {
                $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
                $stmt->execute([$remetente_usuario_id]);
                $remetente = $stmt->fetch();
                if ($remetente) {
                    $remetente_nome = $remetente['nome'];
                }
            }
            
            $base_url = get_base_url();
            
            // Notifica cada colaborador alvo
            foreach ($colaboradores_alvo as $idx => $colab_id) {
                $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
                $stmt->execute([$colab_id]);
                $destinatario = $stmt->fetch();
                
                if (!$destinatario) continue;
                
                $link_celebração = $base_url . '/pages/celebração_view.php?id=' . $celebração_ids[$idx];
                
                if ($enviar_email && !empty($destinatario['email_pessoal'])) {
                    $assunto = "🎉 Você recebeu uma celebração!";
                    $imagem_html = '';
                    if ($imagem) {
                        $imagem_url = $base_url . '/' . $imagem;
                        $imagem_html = "<p><img src='{$imagem_url}' alt='{$titulo}' style='max-width: 100%; border-radius: 8px; margin: 20px 0;'></p>";
                    }
                    $mensagem = "
                        <h2>Parabéns, {$destinatario['nome_completo']}!</h2>
                        <p><strong>{$remetente_nome}</strong> te enviou uma celebração:</p>
                        <h3>{$titulo}</h3>
                        " . ($descricao ? "<p>" . nl2br(htmlspecialchars($descricao)) . "</p>" : "") . "
                        {$imagem_html}
                        <p><a href='{$link_celebração}' style='background-color: #009ef7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>Ver Celebração</a></p>
                    ";
                    enviar_email($destinatario['email_pessoal'], $assunto, $mensagem);
                }
                
                if ($enviar_push) {
                    $titulo_push = "🎉 Nova Celebração!";
                    $mensagem_push = $titulo . " - de " . $remetente_nome;
                    enviar_push_colaborador($colab_id, $titulo_push, $mensagem_push, $link_celebração);
                }
            }
        }
        
        $pdo->commit();
        
        // Libera o lock do MySQL
        if ($request_id) {
            $lockName = 'celebração_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Celebração criada com sucesso para ' . count($colaboradores_alvo) . ' colaborador(es)!',
            'celebração_ids' => $celebração_ids,
            'total_colaboradores' => count($colaboradores_alvo)
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        if ($request_id) {
            try {
                $lockName = 'celebração_' . $request_id;
                $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
            } catch (Exception $lockEx) {
                // Ignora erro ao liberar lock
            }
        }
        throw $e;
    }
    
} catch (Exception $e) {
    // Libera o lock em caso de erro geral
    if (isset($request_id) && $request_id && isset($pdo)) {
        try {
            $lockName = 'celebração_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
        } catch (Exception $lockEx) {
            // Ignora erro ao liberar lock
        }
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

