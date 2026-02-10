<?php
/**
 * API: Editar Vaga
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
        throw new Exception('Vaga não informada');
    }
    
    // Busca vaga e verifica permissão
    $stmt = $pdo->prepare("SELECT empresa_id FROM vagas WHERE id = ?");
    $stmt->execute([$vaga_id]);
    $vaga = $stmt->fetch();
    
    if (!$vaga || !can_access_empresa($vaga['empresa_id'])) {
        throw new Exception('Sem permissão');
    }
    
    // Validações
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    
    if (empty($titulo) || empty($descricao)) {
        throw new Exception('Título e descrição são obrigatórios');
    }
    
    // Processa benefícios
    $beneficios = [];
    if (!empty($_POST['beneficios']) && is_array($_POST['beneficios'])) {
        $beneficios = $_POST['beneficios'];
    }
    
    // Atualiza vaga
    $stmt = $pdo->prepare("
        UPDATE vagas SET
        empresa_id = ?, setor_id = ?, cargo_id = ?, titulo = ?, descricao = ?,
        requisitos_obrigatorios = ?, requisitos_desejaveis = ?,
        competencias_tecnicas = ?, competencias_comportamentais = ?,
        tipo_contrato = ?, modalidade = ?, salario_min = ?, salario_max = ?,
        beneficios = ?, localizacao = ?, horario_trabalho = ?, dias_trabalho = ?,
        quantidade_vagas = ?, publicar_portal = ?, usar_landing_page_customizada = ?, status = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        (int)$_POST['empresa_id'],
        !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null,
        !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null,
        $titulo,
        $descricao,
        $_POST['requisitos_obrigatorios'] ?? null,
        $_POST['requisitos_desejaveis'] ?? null,
        $_POST['competencias_tecnicas'] ?? null,
        $_POST['competencias_comportamentais'] ?? null,
        $_POST['tipo_contrato'] ?? 'CLT',
        $_POST['modalidade'] ?? 'Presencial',
        !empty($_POST['salario_min']) ? (float)$_POST['salario_min'] : null,
        !empty($_POST['salario_max']) ? (float)$_POST['salario_max'] : null,
        json_encode($beneficios),
        $_POST['localizacao'] ?? null,
        $_POST['horario_trabalho'] ?? null,
        $_POST['dias_trabalho'] ?? null,
        (!empty($_POST['quantidade_ilimitada']) || empty($_POST['quantidade_vagas'])) ? null : (int)$_POST['quantidade_vagas'],
        isset($_POST['publicar_portal']) ? (int)$_POST['publicar_portal'] : 0,
        isset($_POST['usar_landing_page_customizada']) ? (int)$_POST['usar_landing_page_customizada'] : 0,
        $_POST['status'] ?? 'aberta',
        $vaga_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Vaga atualizada com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

