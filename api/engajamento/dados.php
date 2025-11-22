<?php
/**
 * API para buscar dados do Painel de Engajamento
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    // Filtros
    $empresa_id = !empty($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
    $setor_id = !empty($_GET['setor_id']) ? (int)$_GET['setor_id'] : null;
    $lider_id = !empty($_GET['lider_id']) ? (int)$_GET['lider_id'] : null;
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês
    $data_fim = $_GET['data_fim'] ?? date('Y-m-t'); // Último dia do mês
    $status_colaboradores = $_GET['status_colaboradores'] ?? ''; // '', '0' (ativos), '2' (desligados)
    
    // Monta condições WHERE
    $where_colaboradores = ["1=1"];
    $params = [];
    
    if ($empresa_id) {
        $where_colaboradores[] = "c.empresa_id = ?";
        $params[] = $empresa_id;
    }
    
    if ($setor_id) {
        $where_colaboradores[] = "c.setor_id = ?";
        $params[] = $setor_id;
    }
    
    if ($lider_id) {
        $where_colaboradores[] = "c.lider_id = ?";
        $params[] = $lider_id;
    }
    
    if ($status_colaboradores === '0') {
        $where_colaboradores[] = "c.status = 'ativo'";
    } elseif ($status_colaboradores === '2') {
        $where_colaboradores[] = "c.status = 'desligado'";
    }
    
    $where_sql = implode(' AND ', $where_colaboradores);
    
    // Total de colaboradores
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores c WHERE $where_sql");
    $stmt->execute($params);
    $total_colaboradores = $stmt->fetch()['total'];
    
    // Colaboradores que acessaram no período
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as total
        FROM colaboradores c
        INNER JOIN acessos_historico ah ON (c.id = ah.colaborador_id OR EXISTS (
            SELECT 1 FROM usuarios u WHERE u.id = ah.usuario_id AND u.colaborador_id = c.id
        ))
        WHERE $where_sql AND ah.data_acesso BETWEEN ? AND ?
    ");
    $params_acesso = array_merge($params, [$data_inicio, $data_fim]);
    $stmt->execute($params_acesso);
    $colaboradores_acessaram = $stmt->fetch()['total'];
    
    // Feedbacks enviados no período (colaboradores que receberam pelo menos 1 feedback)
    $params_feedback = array_merge($params, [$data_inicio, $data_fim]);
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT f.destinatario_colaborador_id) as total
        FROM feedbacks f
        INNER JOIN colaboradores c ON f.destinatario_colaborador_id = c.id
        WHERE $where_sql AND f.status = 'ativo' AND DATE(f.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute($params_feedback);
    $feedbacks_eficiencia = $stmt->fetch()['total'];
    
    // Reuniões 1:1 realizadas no período
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT r.liderado_id) as total
        FROM reunioes_1on1 r
        INNER JOIN colaboradores c ON r.liderado_id = c.id
        WHERE $where_sql AND r.status = 'realizada' AND DATE(r.data_reuniao) BETWEEN ? AND ?
    ");
    $stmt->execute(array_merge($params, [$data_inicio, $data_fim]));
    $reunioes_eficiencia = $stmt->fetch()['total'];
    
    // Celebrações enviadas no período (colaboradores que receberam pelo menos 1 celebração)
    $params_celebração = array_merge($params, [$data_inicio, $data_fim]);
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT cel.destinatario_id) as total
        FROM celebracoes cel
        INNER JOIN colaboradores c ON cel.destinatario_id = c.id
        WHERE $where_sql AND cel.status = 'ativo' AND DATE(cel.data_celebração) BETWEEN ? AND ?
    ");
    $stmt->execute($params_celebração);
    $celebracoes_eficiencia = $stmt->fetch()['total'];
    
    // PDIs ativos
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.colaborador_id) as total
        FROM pdis p
        INNER JOIN colaboradores c ON p.colaborador_id = c.id
        WHERE $where_sql AND p.status = 'ativo'
    ");
    $stmt->execute($params);
    $pdis_eficiencia = $stmt->fetch()['total'];
    
    // Humores respondidos
    $params_humores = array_merge($params, [$data_inicio, $data_fim]);
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, COUNT(DISTINCT COALESCE(e.colaborador_id, u.colaborador_id)) as colaboradores
        FROM emocoes e
        LEFT JOIN usuarios u ON e.usuario_id = u.id
        LEFT JOIN colaboradores c ON COALESCE(e.colaborador_id, u.colaborador_id) = c.id
        WHERE $where_sql AND DATE(e.data_registro) BETWEEN ? AND ?
    ");
    $stmt->execute($params_humores);
    $humores_data = $stmt->fetch();
    
    // Feedbacks totais (enviados)
    $params_feedback_total = array_merge($params, [$data_inicio, $data_fim]);
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, COUNT(DISTINCT f.remetente_colaborador_id) as colaboradores
        FROM feedbacks f
        INNER JOIN colaboradores c ON f.remetente_colaborador_id = c.id
        WHERE $where_sql AND f.status = 'ativo' AND DATE(f.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute($params_feedback_total);
    $feedbacks_data = $stmt->fetch();
    
    // Celebrações totais (enviadas)
    $params_celebração_total = array_merge($params, [$data_inicio, $data_fim]);
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, COUNT(DISTINCT COALESCE(cel.remetente_id, cel.remetente_usuario_id)) as colaboradores
        FROM celebracoes cel
        INNER JOIN colaboradores c ON cel.destinatario_id = c.id
        WHERE $where_sql AND cel.status = 'ativo' AND DATE(cel.data_celebração) BETWEEN ? AND ?
    ");
    $stmt->execute($params_celebração_total);
    $celebracoes_data = $stmt->fetch();
    
    // Pesquisas de satisfação respondidas
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT psr.colaborador_id) as colaboradores,
               COUNT(*) as total_respostas
        FROM pesquisas_satisfacao_respostas psr
        INNER JOIN pesquisas_satisfacao ps ON psr.pesquisa_id = ps.id
        INNER JOIN colaboradores c ON psr.colaborador_id = c.id
        WHERE $where_sql AND ps.status = 'ativa' AND DATE(psr.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute(array_merge($params, [$data_inicio, $data_fim]));
    $pesquisas_satisfacao_data = $stmt->fetch();
    
    // Pesquisas rápidas respondidas
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT prr.colaborador_id) as colaboradores,
               COUNT(*) as total_respostas
        FROM pesquisas_rapidas_respostas prr
        INNER JOIN pesquisas_rapidas pr ON prr.pesquisa_id = pr.id
        INNER JOIN colaboradores c ON prr.colaborador_id = c.id
        WHERE $where_sql AND pr.status = 'ativa' AND DATE(prr.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute(array_merge($params, [$data_inicio, $data_fim]));
    $pesquisas_rapidas_data = $stmt->fetch();
    
    // Calcula porcentagens
    $eficiencia_feedbacks = $total_colaboradores > 0 ? round(($feedbacks_eficiencia / $total_colaboradores) * 100, 1) : 0;
    $eficiencia_1on1 = $total_colaboradores > 0 ? round(($reunioes_eficiencia / $total_colaboradores) * 100, 1) : 0;
    $eficiencia_celebracoes = $total_colaboradores > 0 ? round(($celebracoes_eficiencia / $total_colaboradores) * 100, 1) : 0;
    $eficiencia_pdi = $total_colaboradores > 0 ? round(($pdis_eficiencia / $total_colaboradores) * 100, 1) : 0;
    $engajamento_acessos = $total_colaboradores > 0 ? round(($colaboradores_acessaram / $total_colaboradores) * 100, 1) : 0;
    
    // Engajamento por módulo
    $dias_periodo = (strtotime($data_fim) - strtotime($data_inicio)) / 86400 + 1;
    $humores_esperados = $total_colaboradores * $dias_periodo; // 1 por dia por colaborador
    $eficiencia_humores = $humores_esperados > 0 ? round(($humores_data['total'] / $humores_esperados) * 100, 1) : 0;
    
    // Feedbacks enviados (não recebidos)
    $eficiencia_feedbacks_enviados = $total_colaboradores > 0 ? round(($feedbacks_data['colaboradores'] / $total_colaboradores) * 100, 1) : 0;
    
    // Celebrações enviadas
    $eficiencia_celebracoes_enviadas = $total_colaboradores > 0 ? round(($celebracoes_data['colaboradores'] / $total_colaboradores) * 100, 1) : 0;
    
    // Pesquisas de satisfação
    $eficiencia_pesquisas_satisfacao = $total_colaboradores > 0 ? round(($pesquisas_satisfacao_data['colaboradores'] / $total_colaboradores) * 100, 1) : 0;
    
    // Pesquisas rápidas
    $eficiencia_pesquisas_rapidas = $total_colaboradores > 0 ? round(($pesquisas_rapidas_data['colaboradores'] / $total_colaboradores) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_colaboradores' => $total_colaboradores,
            'eficiencia' => [
                'feedbacks' => $eficiencia_feedbacks,
                '1on1' => $eficiencia_1on1,
                'celebracoes' => $eficiencia_celebracoes,
                'pdi' => $eficiencia_pdi
            ],
            'cards' => [
                'humores' => [
                    'total' => (int)$humores_data['total'],
                    'colaboradores' => (int)$humores_data['colaboradores']
                ],
                'feedbacks' => [
                    'total' => (int)$feedbacks_data['total'],
                    'colaboradores' => (int)$feedbacks_data['colaboradores']
                ],
                'celebracoes' => [
                    'total' => (int)$celebracoes_data['total'],
                    'colaboradores' => (int)$celebracoes_data['colaboradores']
                ],
                'engajados' => [
                    'percentual' => $engajamento_acessos,
                    'colaboradores' => $colaboradores_acessaram
                ]
            ],
            'modulos' => [
                'acessos' => $engajamento_acessos,
                'feedbacks' => $eficiencia_feedbacks_enviados,
                'celebracoes' => $eficiencia_celebracoes_enviadas,
                '1on1' => $eficiencia_1on1,
                'humores' => $eficiencia_humores,
                'pesquisas_satisfacao' => $eficiencia_pesquisas_satisfacao,
                'pesquisas_rapidas' => $eficiencia_pesquisas_rapidas,
                'pdi' => $eficiencia_pdi
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

