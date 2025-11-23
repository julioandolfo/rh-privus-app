<?php
/**
 * API: Salvar Configuração da Landing Page
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
    
    if (empty($vaga_id)) {
        throw new Exception('Vaga é obrigatória');
    }
    
    // Verifica permissão
    $stmt = $pdo->prepare("SELECT empresa_id FROM vagas WHERE id = ?");
    $stmt->execute([$vaga_id]);
    $vaga = $stmt->fetch();
    
    if (!$vaga || !can_access_empresa($vaga['empresa_id'])) {
        throw new Exception('Sem permissão');
    }
    
    // Busca ou cria landing page
    $stmt = $pdo->prepare("SELECT id FROM vagas_landing_pages WHERE vaga_id = ?");
    $stmt->execute([$vaga_id]);
    $landing_page = $stmt->fetch();
    
    // Processa upload de logo (se houver)
    $logo_path = null;
    if (!empty($_FILES['logo_empresa']) && $_FILES['logo_empresa']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/landing_pages/' . $vaga_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extensao = strtolower(pathinfo($_FILES['logo_empresa']['name'], PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        
        if (!in_array($extensao, $tipos_permitidos)) {
            throw new Exception('Formato de logo não permitido');
        }
        
        $nome_arquivo = 'logo_' . time() . '.' . $extensao;
        $caminho_completo = $upload_dir . $nome_arquivo;
        
        if (move_uploaded_file($_FILES['logo_empresa']['tmp_name'], $caminho_completo)) {
            $logo_path = '/uploads/landing_pages/' . $vaga_id . '/' . $nome_arquivo;
        }
    }
    
    // Processa upload de imagem hero (se houver)
    $hero_path = null;
    if (!empty($_FILES['imagem_hero']) && $_FILES['imagem_hero']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/landing_pages/' . $vaga_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extensao = strtolower(pathinfo($_FILES['imagem_hero']['name'], PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($extensao, $tipos_permitidos)) {
            throw new Exception('Formato de imagem não permitido');
        }
        
        $nome_arquivo = 'hero_' . time() . '.' . $extensao;
        $caminho_completo = $upload_dir . $nome_arquivo;
        
        if (move_uploaded_file($_FILES['imagem_hero']['tmp_name'], $caminho_completo)) {
            $hero_path = '/uploads/landing_pages/' . $vaga_id . '/' . $nome_arquivo;
        }
    }
    
    if ($landing_page) {
        // Atualiza
        $sql = "UPDATE vagas_landing_pages SET 
                titulo_pagina = ?, meta_descricao = ?, 
                cor_primaria = ?, cor_secundaria = ?, layout = ?";
        $params = [
            $_POST['titulo_pagina'] ?? null,
            $_POST['meta_descricao'] ?? null,
            $_POST['cor_primaria'] ?? '#009ef7',
            $_POST['cor_secundaria'] ?? '#f1416c',
            $_POST['layout'] ?? 'padrao'
        ];
        
        if ($logo_path) {
            $sql .= ", logo_empresa = ?";
            $params[] = $logo_path;
        }
        
        if ($hero_path) {
            $sql .= ", imagem_hero = ?";
            $params[] = $hero_path;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $landing_page['id'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuração atualizada com sucesso'
        ]);
    } else {
        // Cria nova
        $stmt = $pdo->prepare("
            INSERT INTO vagas_landing_pages 
            (vaga_id, titulo_pagina, meta_descricao, logo_empresa, imagem_hero, 
             cor_primaria, cor_secundaria, layout, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $vaga_id,
            $_POST['titulo_pagina'] ?? null,
            $_POST['meta_descricao'] ?? null,
            $logo_path,
            $hero_path,
            $_POST['cor_primaria'] ?? '#009ef7',
            $_POST['cor_secundaria'] ?? '#f1416c',
            $_POST['layout'] ?? 'padrao'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuração criada com sucesso'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

