<?php
/**
 * API: Criar Candidatura (Portal Público)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/recrutamento_functions.php';

// Permite acesso público (sem login)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    
    // Validações
    $vaga_id = (int)($_POST['vaga_id'] ?? 0);
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($vaga_id) || empty($nome_completo) || empty($email)) {
        throw new Exception('Vaga, nome e email são obrigatórios');
    }
    
    // Valida email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    // Verifica se vaga existe e está aberta
    $stmt = $pdo->prepare("
        SELECT * FROM vagas 
        WHERE id = ? AND status = 'aberta' AND publicar_portal = 1
    ");
    $stmt->execute([$vaga_id]);
    $vaga = $stmt->fetch();
    
    if (!$vaga) {
        throw new Exception('Vaga não encontrada ou não está aberta para candidaturas');
    }
    
    // Busca ou cria candidato
    $stmt = $pdo->prepare("SELECT id FROM candidatos WHERE email = ?");
    $stmt->execute([$email]);
    $candidato_existente = $stmt->fetch();
    
    if ($candidato_existente) {
        $candidato_id = $candidato_existente['id'];
        
        // Atualiza dados do candidato
        $stmt = $pdo->prepare("
            UPDATE candidatos 
            SET nome_completo = ?, telefone = ?, linkedin = ?, portfolio = ?, instagram = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nome_completo,
            $_POST['telefone'] ?? null,
            $_POST['linkedin'] ?? null,
            $_POST['portfolio'] ?? null,
            $_POST['instagram'] ?? null,
            $candidato_id
        ]);
    } else {
        // Cria novo candidato
        $stmt = $pdo->prepare("
            INSERT INTO candidatos 
            (nome_completo, email, telefone, linkedin, portfolio, instagram, origem)
            VALUES (?, ?, ?, ?, ?, ?, 'portal')
        ");
        $stmt->execute([
            $nome_completo,
            $email,
            $_POST['telefone'] ?? null,
            $_POST['linkedin'] ?? null,
            $_POST['portfolio'] ?? null,
            $_POST['instagram'] ?? null
        ]);
        $candidato_id = $pdo->lastInsertId();
    }
    
    // Verifica se já existe candidatura para esta vaga
    $stmt = $pdo->prepare("
        SELECT id FROM candidaturas 
        WHERE vaga_id = ? AND candidato_id = ?
    ");
    $stmt->execute([$vaga_id, $candidato_id]);
    if ($stmt->fetch()) {
        throw new Exception('Você já se candidatou para esta vaga');
    }
    
    // Gera token de acompanhamento
    $token = gerar_token_acompanhamento();
    
    // Cria candidatura
    $stmt = $pdo->prepare("
        INSERT INTO candidaturas 
        (vaga_id, candidato_id, status, coluna_kanban, token_acompanhamento, prioridade)
        VALUES (?, ?, 'nova', 'novos_candidatos', ?, 'media')
    ");
    $stmt->execute([$vaga_id, $candidato_id, $token]);
    $candidatura_id = $pdo->lastInsertId();
    
    // Processa upload de currículo
    if (!empty($_FILES['curriculo']) && $_FILES['curriculo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/candidaturas/' . $candidatura_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extensao = strtolower(pathinfo($_FILES['curriculo']['name'], PATHINFO_EXTENSION));
        $tipos_permitidos = ['pdf', 'doc', 'docx'];
        
        if (!in_array($extensao, $tipos_permitidos)) {
            throw new Exception('Formato de arquivo não permitido. Use PDF, DOC ou DOCX');
        }
        
        $nome_arquivo = 'curriculo_' . time() . '.' . $extensao;
        $caminho_completo = $upload_dir . $nome_arquivo;
        
        if (move_uploaded_file($_FILES['curriculo']['tmp_name'], $caminho_completo)) {
            // Salva apenas o caminho relativo SEM /rh/ pois get_base_url() já inclui
            $caminho_relativo = '/uploads/candidaturas/' . $candidatura_id . '/' . $nome_arquivo;
            
            $stmt = $pdo->prepare("
                INSERT INTO candidaturas_anexos 
                (candidatura_id, tipo, nome_arquivo, caminho_arquivo, tipo_mime, tamanho_bytes)
                VALUES (?, 'curriculo', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $candidatura_id,
                $_FILES['curriculo']['name'],
                $caminho_relativo,
                $_FILES['curriculo']['type'],
                $_FILES['curriculo']['size']
            ]);
        }
    }
    
    // Cria etapas iniciais
    criar_etapas_iniciais_candidatura($candidatura_id, $vaga_id);
    
    // Executa automações da coluna "novos_candidatos"
    executar_automatizacoes_kanban($candidatura_id, 'novos_candidatos');
    
    // Registra histórico
    registrar_historico_candidatura($candidatura_id, 'criada', null);
    
    echo json_encode([
        'success' => true,
        'message' => 'Candidatura realizada com sucesso!',
        'token_acompanhamento' => $token,
        'link_acompanhamento' => get_base_url() . '/acompanhar.php?token=' . $token
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

