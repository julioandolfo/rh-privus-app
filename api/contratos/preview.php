<?php
/**
 * API para gerar preview do contrato
 */

// Desabilita exibição de erros para não quebrar o JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/contratos_functions.php';

// Define header JSON antes de qualquer output
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Lê body JSON uma única vez
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = [];
}

$colaborador_id = intval($_GET['colaborador_id'] ?? $input['colaborador_id'] ?? 0);
$template_id = intval($_GET['template_id'] ?? $input['template_id'] ?? 0);

if ($colaborador_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do colaborador inválido']);
    exit;
}

try {
    $pdo = getDB();
    
    // Busca colaborador
    $colaborador = buscar_dados_colaborador_completos($colaborador_id);
    if (!$colaborador) {
        throw new Exception('Colaborador não encontrado');
    }
    
    // Busca template ou usa conteúdo customizado
    $template_html = '';
    if ($template_id > 0) {
        $stmt = $pdo->prepare("SELECT conteudo_html FROM contratos_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();
        if ($template) {
            $template_html = $template['conteudo_html'];
        }
    }
    
    // Se não tem template, usa conteúdo customizado do POST
    if (empty($template_html)) {
        $template_html = $input['conteudo_customizado'] ?? '';
    }
    
    if (empty($template_html)) {
        throw new Exception('Template ou conteúdo não encontrado. Selecione um template ou preencha o conteúdo customizado.');
    }
    
    // Dados do contrato do POST
    $contrato_data = [
        'titulo' => $input['titulo'] ?? '',
        'descricao_funcao' => $input['descricao_funcao'] ?? '',
        'data_criacao' => $input['data_criacao'] ?? date('Y-m-d'),
        'data_vencimento' => $input['data_vencimento'] ?? null,
        'observacoes' => $input['observacoes'] ?? ''
    ];
    
    // Campos manuais preenchidos pelo usuário
    $campos_manuais = $input['campos_manuais'] ?? [];
    
    // Verifica campos faltantes
    $campos_faltantes = verificar_campos_faltantes_contrato($template_html, $colaborador, $contrato_data, $campos_manuais);
    
    // Substitui variáveis (usando campos manuais quando disponíveis)
    $html = substituir_variaveis_contrato_com_manuais($template_html, $colaborador, $contrato_data, $campos_manuais);
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'campos_faltantes' => array_values($campos_faltantes),
        'pode_enviar' => empty($campos_faltantes),
        'colaborador' => [
            'nome_completo' => $colaborador['nome_completo'] ?? '',
            'descricao_funcao' => $colaborador['descricao_funcao'] ?? '',
            'cargo_nome' => $colaborador['cargo_nome'] ?? ''
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getFile() . ':' . $e->getLine()
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro fatal: ' . $e->getMessage(),
        'error' => $e->getFile() . ':' . $e->getLine()
    ]);
}

