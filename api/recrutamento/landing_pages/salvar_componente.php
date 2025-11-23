<?php
/**
 * API: Salvar Componente da Landing Page
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $vaga_id = (int)($_POST['vaga_id'] ?? 0);
    $componente_id = !empty($_POST['componente_id']) ? (int)$_POST['componente_id'] : null;
    
    if (empty($vaga_id)) {
        throw new Exception('Vaga é obrigatória');
    }
    
    // Verifica se vaga existe e permissão
    $stmt = $pdo->prepare("SELECT empresa_id FROM vagas WHERE id = ?");
    $stmt->execute([$vaga_id]);
    $vaga = $stmt->fetch();
    
    if (!$vaga) {
        throw new Exception('Vaga não encontrada');
    }
    
    if (!can_access_empresa($vaga['empresa_id'])) {
        throw new Exception('Sem permissão');
    }
    
    // Busca ou cria landing page
    $stmt = $pdo->prepare("SELECT id FROM vagas_landing_pages WHERE vaga_id = ?");
    $stmt->execute([$vaga_id]);
    $landing_page = $stmt->fetch();
    
    if (!$landing_page) {
        $stmt = $pdo->prepare("INSERT INTO vagas_landing_pages (vaga_id, ativo) VALUES (?, 1)");
        $stmt->execute([$vaga_id]);
        $landing_page_id = $pdo->lastInsertId();
    } else {
        $landing_page_id = $landing_page['id'];
    }
    
    // Processa upload de imagem (se houver)
    $imagem_path = null;
    if (!empty($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/landing_pages/' . $vaga_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($extensao, $tipos_permitidos)) {
            throw new Exception('Formato de imagem não permitido');
        }
        
        $nome_arquivo = 'componente_' . time() . '.' . $extensao;
        $caminho_completo = $upload_dir . $nome_arquivo;
        
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho_completo)) {
            $imagem_path = '/uploads/landing_pages/' . $vaga_id . '/' . $nome_arquivo;
        }
    }
    
    // Processa configuração JSON
    $configuracao = null;
    if (!empty($_POST['configuracao'])) {
        $configuracao = json_encode(json_decode($_POST['configuracao'], true));
    }
    
    if ($componente_id) {
        // Atualiza componente existente
        $stmt = $pdo->prepare("
            UPDATE vagas_landing_page_componentes 
            SET tipo_componente = ?, titulo = ?, conteudo = ?, 
                ordem = ?, visivel = ?, configuracao = ?
                " . ($imagem_path ? ", imagem = ?" : "") . "
            WHERE id = ? AND landing_page_id = ?
        ");
        
        $params = [
            $_POST['tipo_componente'],
            $_POST['titulo'] ?? null,
            $_POST['conteudo'] ?? null,
            (int)($_POST['ordem'] ?? 0),
            isset($_POST['visivel']) ? (int)$_POST['visivel'] : 1,
            $configuracao
        ];
        
        if ($imagem_path) {
            $params[] = $imagem_path;
        }
        
        $params[] = $componente_id;
        $params[] = $landing_page_id;
        
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Componente atualizado com sucesso',
            'componente_id' => $componente_id
        ]);
    } else {
        // Cria novo componente
        $stmt = $pdo->prepare("
            INSERT INTO vagas_landing_page_componentes 
            (landing_page_id, tipo_componente, titulo, conteudo, imagem, ordem, visivel, configuracao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $landing_page_id,
            $_POST['tipo_componente'],
            $_POST['titulo'] ?? null,
            $_POST['conteudo'] ?? null,
            $imagem_path,
            (int)($_POST['ordem'] ?? 0),
            isset($_POST['visivel']) ? (int)$_POST['visivel'] : 1,
            $configuracao
        ]);
        
        $componente_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Componente criado com sucesso',
            'componente_id' => $componente_id
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

