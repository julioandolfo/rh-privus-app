<?php
/**
 * API: Gerar Vaga com IA
 * Endpoint para processar geração de vagas usando OpenAI
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/openai_service.php';

// Verifica autenticação
if (!isset($_SESSION['usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Não autenticado'
    ]);
    exit;
}

// Verifica método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    // Busca dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    $acao = $input['acao'] ?? '';
    
    // Ação: Gerar nova vaga
    if ($acao === 'gerar') {
        $descricao_entrada = trim($input['descricao'] ?? '');
        $empresa_id = intval($input['empresa_id'] ?? 0);
        $template_codigo = $input['template'] ?? 'vaga_generica';
        
        // Validações
        if (empty($descricao_entrada)) {
            echo json_encode([
                'success' => false,
                'message' => 'Descrição é obrigatória'
            ]);
            exit;
        }
        
        if (strlen($descricao_entrada) < 20) {
            echo json_encode([
                'success' => false,
                'message' => 'Descrição muito curta. Mínimo 20 caracteres.'
            ]);
            exit;
        }
        
        if (strlen($descricao_entrada) > 1000) {
            echo json_encode([
                'success' => false,
                'message' => 'Descrição muito longa. Máximo 1000 caracteres.'
            ]);
            exit;
        }
        
        if ($empresa_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Empresa não selecionada'
            ]);
            exit;
        }
        
        // Verifica se empresa existe e usuário tem acesso
        require_once __DIR__ . '/../../includes/select_colaborador.php';
        $empresas = get_empresas_disponiveis($pdo, $usuario);
        $empresa_valida = false;
        foreach ($empresas as $emp) {
            if ($emp['id'] == $empresa_id) {
                $empresa_valida = true;
                break;
            }
        }
        
        if (!$empresa_valida) {
            echo json_encode([
                'success' => false,
                'message' => 'Empresa não encontrada ou sem permissão'
            ]);
            exit;
        }
        
        // Gera vaga com IA
        $resultado = gerar_vaga_com_ia($descricao_entrada, $empresa_id, $template_codigo);
        
        if ($resultado['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Vaga gerada com sucesso!',
                'data' => $resultado['data'],
                'meta' => $resultado['meta']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $resultado['message']
            ]);
        }
        
    // Ação: Refinar vaga existente
    } elseif ($acao === 'refinar') {
        $vaga_data_atual = $input['vaga_atual'] ?? [];
        $instrucao = trim($input['instrucao'] ?? '');
        $empresa_id = intval($input['empresa_id'] ?? 0);
        
        if (empty($vaga_data_atual)) {
            echo json_encode([
                'success' => false,
                'message' => 'Dados da vaga não fornecidos'
            ]);
            exit;
        }
        
        if (empty($instrucao)) {
            echo json_encode([
                'success' => false,
                'message' => 'Instrução de refinamento é obrigatória'
            ]);
            exit;
        }
        
        // Refina vaga
        $resultado = refinar_vaga_com_ia($vaga_data_atual, $instrucao, $empresa_id);
        
        if ($resultado['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Vaga refinada com sucesso!',
                'data' => $resultado['data']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $resultado['message']
            ]);
        }
        
    // Ação: Buscar templates disponíveis
    } elseif ($acao === 'listar_templates') {
        $stmt = $pdo->query("
            SELECT codigo, nome, descricao, categoria, exemplo
            FROM openai_prompt_templates
            WHERE ativo = 1
            ORDER BY ordem ASC, nome ASC
        ");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'templates' => $templates
        ]);
        
    // Ação: Verificar limite diário
    } elseif ($acao === 'verificar_limite') {
        $usuario_id = $usuario['id'];
        $hoje = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT quantidade, limite_diario 
            FROM openai_rate_limit 
            WHERE usuario_id = ? AND data_uso = ?
        ");
        $stmt->execute([$usuario_id, $hoje]);
        $registro = $stmt->fetch();
        
        $quantidade_usada = $registro ? $registro['quantidade'] : 0;
        $limite = $registro ? $registro['limite_diario'] : 10;
        $restante = $limite - $quantidade_usada;
        
        echo json_encode([
            'success' => true,
            'limite' => $limite,
            'usado' => $quantidade_usada,
            'restante' => max(0, $restante),
            'pode_gerar' => $restante > 0
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Ação não reconhecida'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor: ' . $e->getMessage()
    ]);
}
