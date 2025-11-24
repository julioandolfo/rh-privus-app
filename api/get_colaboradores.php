<?php
/**
 * API para buscar colaboradores
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit;
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$empresa_id = $_GET['empresa_id'] ?? 0;
$lider_id = $_GET['lider_id'] ?? 0;
$status = $_GET['status'] ?? '';
$com_salario = $_GET['com_salario'] ?? '0';
$q = trim($_GET['q'] ?? ''); // Parâmetro de busca

// Se tem lider_id, busca liderados desse líder
if ($lider_id) {
    $where = ["lider_id = ?", "status = 'ativo'"];
    $params = [$lider_id];
    
    if ($q) {
        $where[] = "(nome_completo LIKE ? OR cpf LIKE ?)";
        $search_term = "%{$q}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $sql = "SELECT id, nome_completo, cpf, foto FROM colaboradores WHERE " . implode(' AND ', $where) . " ORDER BY nome_completo LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($colaboradores);
    exit;
}

// Monta condições de acesso baseado no role
$where = [];
$params = [];

// ADMIN pode ver todos
if ($usuario['role'] === 'ADMIN') {
    // Não adiciona filtro de empresa
} 
// RH pode ver colaboradores das empresas associadas
elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    } elseif (isset($usuario['empresa_id']) && $usuario['empresa_id']) {
        $where[] = "empresa_id = ?";
        $params[] = $usuario['empresa_id'];
    } else {
        // Sem empresas associadas, retorna vazio
        echo json_encode([]);
        exit;
    }
}
// GESTOR pode ver apenas do seu setor
elseif ($usuario['role'] === 'GESTOR') {
    if (isset($usuario['setor_id']) && $usuario['setor_id']) {
        $where[] = "setor_id = ?";
        $params[] = $usuario['setor_id'];
    } else {
        echo json_encode([]);
        exit;
    }
}
// Outros roles não têm acesso
else {
    echo json_encode([]);
    exit;
}

// Se empresa_id foi especificado, filtra por ela (e verifica permissão)
if ($empresa_id > 0) {
    if (!can_access_empresa($empresa_id)) {
        echo json_encode([]);
        exit;
    }
    // Remove filtro anterior de empresas e adiciona específico
    $where = array_filter($where, function($w) {
        return strpos($w, 'empresa_id') === false;
    });
    $params = array_filter($params, function($p) use ($usuario) {
        if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
            return !in_array($p, $usuario['empresas_ids']);
        }
        return $p != ($usuario['empresa_id'] ?? null);
    });
    $where[] = "empresa_id = ?";
    $params[] = $empresa_id;
    $params = array_values($params); // Reindexa
}

// Filtro de status
if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
} else {
    // Por padrão, mostra apenas ativos
    $where[] = "status = 'ativo'";
}

// Filtro de salário
if ($com_salario === '1') {
    $where[] = "salario IS NOT NULL AND salario > 0";
}

// Busca por nome ou CPF
if ($q) {
    $where[] = "(nome_completo LIKE ? OR cpf LIKE ?)";
    $search_term = "%{$q}%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql = "SELECT id, nome_completo, cpf, salario, empresa_id FROM colaboradores WHERE " . implode(' AND ', $where) . " ORDER BY nome_completo LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($colaboradores);

