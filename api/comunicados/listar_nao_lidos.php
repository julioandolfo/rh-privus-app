<?php
/**
 * API para listar comunicados não lidos (para o modal)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado', 'comunicados' => []]);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Busca comunicados publicados que ainda não foram lidos
    $stmt = $pdo->prepare("
        SELECT c.*, u.nome as criado_por_nome,
               cl.lido,
               cl.data_leitura,
               cl.data_visualizacao,
               CASE 
                   WHEN cl.data_visualizacao IS NOT NULL 
                   THEN TIMESTAMPDIFF(HOUR, cl.data_visualizacao, NOW())
                   ELSE NULL
               END as horas_desde_visualizacao
        FROM comunicados c
        LEFT JOIN usuarios u ON c.criado_por_usuario_id = u.id
        LEFT JOIN comunicados_leitura cl ON c.id = cl.comunicado_id 
            AND (
                (cl.usuario_id = ? AND ? IS NOT NULL)
                OR (cl.colaborador_id = ? AND ? IS NOT NULL)
            )
        WHERE c.status = 'publicado'
        AND (c.data_publicacao IS NULL OR c.data_publicacao <= NOW())
        AND (c.data_expiracao IS NULL OR c.data_expiracao > NOW())
        AND (
            cl.id IS NULL -- Nunca foi visualizado
            OR (cl.lido = 0) -- Não foi marcado como lido
        )
        ORDER BY c.data_publicacao DESC, c.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$usuario_id, $usuario_id, $colaborador_id, $colaborador_id]);
    $comunicados = $stmt->fetchAll();
    
    // Formata dados
    $resultado = [];
    foreach ($comunicados as $comunicado) {
        $resultado[] = [
            'id' => $comunicado['id'],
            'titulo' => $comunicado['titulo'],
            'conteudo' => $comunicado['conteudo'],
            'imagem' => $comunicado['imagem'],
            'criado_por_nome' => $comunicado['criado_por_nome'],
            'data_publicacao' => $comunicado['data_publicacao'],
            'lido' => (bool)($comunicado['lido'] ?? false),
            'horas_desde_visualizacao' => intval($comunicado['horas_desde_visualizacao'] ?? 0)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'comunicados' => $resultado,
        'total' => count($resultado)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar comunicados: ' . $e->getMessage(),
        'comunicados' => []
    ]);
}

