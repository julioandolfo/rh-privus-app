<?php
/**
 * API para buscar histórico mensal de engajamento
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
    
    $empresa_id = !empty($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
    $setor_id = !empty($_GET['setor_id']) ? (int)$_GET['setor_id'] : null;
    $lider_id = !empty($_GET['lider_id']) ? (int)$_GET['lider_id'] : null;
    $meses = (int)($_GET['meses'] ?? 12);
    
    $historico = [];
    
    for ($i = $meses - 1; $i >= 0; $i--) {
        $mes = date('Y-m', strtotime("-$i months"));
        $mes_inicio = date('Y-m-01', strtotime("-$i months"));
        $mes_fim = date('Y-m-t', strtotime("-$i months"));
        
        // Monta condições WHERE
        $where = ["c.status = 'ativo'"];
        $params = [];
        
        if ($empresa_id) {
            $where[] = "c.empresa_id = ?";
            $params[] = $empresa_id;
        }
        if ($setor_id) {
            $where[] = "c.setor_id = ?";
            $params[] = $setor_id;
        }
        if ($lider_id) {
            $where[] = "c.lider_id = ?";
            $params[] = $lider_id;
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Total de colaboradores
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores c WHERE $where_sql");
        $stmt->execute($params);
        $total_colab = $stmt->fetch()['total'];
        
        // Colaboradores que acessaram
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT c.id) as total
            FROM colaboradores c
            INNER JOIN acessos_historico ah ON (c.id = ah.colaborador_id OR EXISTS (
                SELECT 1 FROM usuarios u WHERE u.id = ah.usuario_id AND u.colaborador_id = c.id
            ))
            WHERE $where_sql AND ah.data_acesso BETWEEN ? AND ?
        ");
        $params_acesso = array_merge($params, [$mes_inicio, $mes_fim]);
        $stmt->execute($params_acesso);
        $acessaram = $stmt->fetch()['total'];
        
        $percentual = $total_colab > 0 ? round(($acessaram / $total_colab) * 100, 1) : 0;
        
        $historico[] = [
            'mes' => date('M/Y', strtotime("-$i months")),
            'mes_codigo' => $mes,
            'percentual' => $percentual,
            'acessaram' => $acessaram,
            'total' => $total_colab
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $historico
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

