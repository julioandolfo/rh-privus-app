<?php
/**
 * API: Analytics da Vaga
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $vaga_id = (int)($_GET['vaga_id'] ?? 0);
    
    if (empty($vaga_id)) {
        throw new Exception('Vaga não informada');
    }
    
    // Verifica permissão
    $stmt = $pdo->prepare("SELECT empresa_id FROM vagas WHERE id = ?");
    $stmt->execute([$vaga_id]);
    $vaga = $stmt->fetch();
    
    if (!$vaga || !can_access_empresa($vaga['empresa_id'])) {
        throw new Exception('Sem permissão');
    }
    
    // Estatísticas gerais
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_candidaturas,
            COUNT(DISTINCT CASE WHEN status = 'aprovada' THEN id END) as aprovadas,
            COUNT(DISTINCT CASE WHEN status = 'rejeitada' THEN id END) as rejeitadas,
            COUNT(DISTINCT CASE WHEN status = 'pendente' THEN id END) as pendentes,
            COUNT(DISTINCT CASE WHEN status = 'em_andamento' THEN id END) as em_andamento,
            COUNT(DISTINCT CASE WHEN status = 'cancelada' THEN id END) as canceladas
        FROM candidaturas
        WHERE vaga_id = ?
    ");
    $stmt->execute([$vaga_id]);
    $stats = $stmt->fetch();
    
    // Candidaturas por etapa (Kanban)
    $stmt = $pdo->prepare("
        SELECT 
            e.nome as etapa_nome,
            e.codigo as etapa_codigo,
            e.cor_kanban,
            COUNT(c.id) as total
        FROM processo_seletivo_etapas e
        LEFT JOIN candidaturas c ON c.coluna_kanban = e.codigo AND c.vaga_id = ?
        WHERE e.vaga_id IS NULL OR e.vaga_id = ?
        GROUP BY e.id, e.nome, e.codigo, e.cor_kanban
        ORDER BY e.ordem ASC
    ");
    $stmt->execute([$vaga_id, $vaga_id]);
    $por_etapa = $stmt->fetchAll();
    
    // Candidaturas por mês
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mes,
            DATE_FORMAT(created_at, '%m/%Y') as mes_formatado,
            COUNT(*) as total
        FROM candidaturas
        WHERE vaga_id = ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY mes ASC
        LIMIT 12
    ");
    $stmt->execute([$vaga_id]);
    $por_mes = $stmt->fetchAll();
    
    // Entrevistas realizadas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_entrevistas,
            COUNT(DISTINCT CASE WHEN status = 'realizada' THEN id END) as realizadas,
            COUNT(DISTINCT CASE WHEN status = 'agendada' THEN id END) as agendadas,
            COUNT(DISTINCT CASE WHEN status = 'cancelada' THEN id END) as canceladas,
            AVG(CASE WHEN status = 'realizada' AND avaliacao_geral IS NOT NULL THEN avaliacao_geral END) as media_avaliacao
        FROM entrevistas
        WHERE vaga_id = ?
    ");
    $stmt->execute([$vaga_id]);
    $entrevistas = $stmt->fetch();
    
    // Taxa de conversão por etapa
    $stmt = $pdo->prepare("
        SELECT 
            e.nome as etapa_nome,
            COUNT(DISTINCT ce.candidatura_id) as total_passaram,
            COUNT(DISTINCT CASE WHEN ce.status = 'aprovada' THEN ce.candidatura_id END) as aprovadas_etapa,
            COUNT(DISTINCT CASE WHEN ce.status = 'rejeitada' THEN ce.candidatura_id END) as rejeitadas_etapa
        FROM processo_seletivo_etapas e
        LEFT JOIN candidaturas_etapas ce ON ce.etapa_id = e.id
        LEFT JOIN candidaturas c ON c.id = ce.candidatura_id AND c.vaga_id = ?
        WHERE e.vaga_id IS NULL OR e.vaga_id = ?
        GROUP BY e.id, e.nome
        ORDER BY e.ordem ASC
    ");
    $stmt->execute([$vaga_id, $vaga_id]);
    $conversao_etapas = $stmt->fetchAll();
    
    // Tempo médio por etapa
    $stmt = $pdo->prepare("
        SELECT 
            e.nome as etapa_nome,
            AVG(TIMESTAMPDIFF(DAY, ce.data_inicio, ce.data_conclusao)) as tempo_medio_dias
        FROM candidaturas_etapas ce
        INNER JOIN processo_seletivo_etapas e ON ce.etapa_id = e.id
        INNER JOIN candidaturas c ON c.id = ce.candidatura_id
        WHERE c.vaga_id = ? AND ce.data_conclusao IS NOT NULL
        GROUP BY e.id, e.nome
        ORDER BY e.ordem ASC
    ");
    $stmt->execute([$vaga_id]);
    $tempo_medio = $stmt->fetchAll();
    
    // Fonte de candidatos (se houver campo)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(fonte_candidato, 'Não informado') as fonte,
            COUNT(*) as total
        FROM candidaturas
        WHERE vaga_id = ?
        GROUP BY fonte_candidato
        ORDER BY total DESC
    ");
    $stmt->execute([$vaga_id]);
    $por_fonte = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'por_etapa' => $por_etapa,
        'por_mes' => $por_mes,
        'entrevistas' => $entrevistas,
        'conversao_etapas' => $conversao_etapas,
        'tempo_medio' => $tempo_medio,
        'por_fonte' => $por_fonte
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

