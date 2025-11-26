<?php
/**
 * API: Buscar Cursos Obrigatórios do Colaborador
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lms_functions.php';

require_login();

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    if (!$colaborador_id) {
        throw new Exception('Colaborador não encontrado');
    }
    
    $status = $_GET['status'] ?? null; // pendente, em_andamento, vencido, concluido
    
    $where = ["coc.colaborador_id = ?"];
    $params = [$colaborador_id];
    
    if ($status) {
        $where[] = "coc.status = ?";
        $params[] = $status;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Busca cursos obrigatórios
    $stmt = $pdo->prepare("
        SELECT coc.*,
               c.titulo, c.descricao, c.imagem_capa, c.duracao_estimada,
               DATEDIFF(coc.data_limite, CURDATE()) as dias_restantes,
               (SELECT COUNT(*) FROM aulas a WHERE a.curso_id = c.id AND a.status = 'publicado') as total_aulas,
               (SELECT COUNT(*) FROM progresso_colaborador pc 
                WHERE pc.colaborador_id = ? AND pc.curso_id = c.id AND pc.status = 'concluido') as aulas_concluidas
        FROM cursos_obrigatorios_colaboradores coc
        INNER JOIN cursos c ON c.id = coc.curso_id
        WHERE $where_sql
        ORDER BY 
            CASE 
                WHEN coc.status = 'vencido' THEN 1
                WHEN coc.status = 'pendente' THEN 2
                WHEN coc.status = 'em_andamento' THEN 3
                ELSE 4
            END,
            coc.data_limite ASC
    ");
    $params = array_merge([$colaborador_id], $params);
    $stmt->execute($params);
    $cursos = $stmt->fetchAll();
    
    // Calcula percentual de conclusão
    foreach ($cursos as &$curso) {
        if ($curso['total_aulas'] > 0) {
            $curso['percentual_conclusao'] = round(($curso['aulas_concluidas'] / $curso['total_aulas']) * 100, 2);
        } else {
            $curso['percentual_conclusao'] = 0;
        }
    }
    
    // Conta totais
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'vencido' THEN 1 ELSE 0 END) as vencidos,
            SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos
        FROM cursos_obrigatorios_colaboradores
        WHERE colaborador_id = ?
    ");
    $stmt->execute([$colaborador_id]);
    $totais = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'cursos' => $cursos,
        'totais' => $totais
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

