<?php
/**
 * API de Eventos
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');

$pdo = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Labels
$tipos_evento = [
    'reuniao' => 'Reunião',
    'treinamento' => 'Treinamento',
    'confraternizacao' => 'Confraternização',
    'palestra' => 'Palestra',
    'workshop' => 'Workshop',
    'outro' => 'Outro'
];

$status_evento = [
    'agendado' => ['label' => 'Agendado', 'class' => 'primary'],
    'em_andamento' => ['label' => 'Em Andamento', 'class' => 'info'],
    'concluido' => ['label' => 'Concluído', 'class' => 'success'],
    'cancelado' => ['label' => 'Cancelado', 'class' => 'danger']
];

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID não informado']);
        exit;
    }
    
    try {
        // Busca evento
        $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?");
        $stmt->execute([$id]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evento) {
            echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
            exit;
        }
        
        // Adiciona labels
        $evento['tipo_label'] = $tipos_evento[$evento['tipo']] ?? $evento['tipo'];
        $evento['status_label'] = $status_evento[$evento['status']]['label'] ?? $evento['status'];
        $evento['status_class'] = $status_evento[$evento['status']]['class'] ?? 'secondary';
        $evento['data_evento_formatada'] = date('d/m/Y', strtotime($evento['data_evento']));
        
        // Busca participantes (IDs)
        $stmt = $pdo->prepare("SELECT colaborador_id FROM eventos_participantes WHERE evento_id = ?");
        $stmt->execute([$id]);
        $participantes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Busca detalhes dos participantes
        $stmt = $pdo->prepare("
            SELECT ep.*, c.nome_completo
            FROM eventos_participantes ep
            INNER JOIN colaboradores c ON ep.colaborador_id = c.id
            WHERE ep.evento_id = ?
            ORDER BY c.nome_completo
        ");
        $stmt->execute([$id]);
        $participantes_detalhes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formata datas
        foreach ($participantes_detalhes as &$p) {
            if ($p['data_confirmacao']) {
                $p['data_confirmacao'] = date('d/m/Y H:i', strtotime($p['data_confirmacao']));
            }
        }
        
        echo json_encode([
            'success' => true,
            'evento' => $evento,
            'participantes' => $participantes,
            'participantes_detalhes' => $participantes_detalhes
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}
