<?php
/**
 * Confirmação de Presença em Evento via Link
 * Página pública (não requer login)
 */

require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

$token = $_GET['token'] ?? '';
$acao = $_GET['acao'] ?? ''; // confirmar, recusar

if (empty($token)) {
    $erro = 'Token inválido ou não informado.';
} else {
    // Busca participante pelo token
    $stmt = $pdo->prepare("
        SELECT ep.*, e.titulo, e.data_evento, e.hora_inicio, e.hora_fim, e.local, 
               e.link_virtual, e.tipo, e.status as evento_status, e.descricao,
               c.nome_completo
        FROM eventos_participantes ep
        INNER JOIN eventos e ON ep.evento_id = e.id
        INNER JOIN colaboradores c ON ep.colaborador_id = c.id
        WHERE ep.token_confirmacao = ?
    ");
    $stmt->execute([$token]);
    $participante = $stmt->fetch();
    
    if (!$participante) {
        $erro = 'Convite não encontrado. O link pode ter expirado ou já foi utilizado.';
    } elseif ($participante['evento_status'] === 'cancelado') {
        $erro = 'Este evento foi cancelado.';
    } elseif ($participante['data_evento'] < date('Y-m-d')) {
        $erro = 'Este evento já ocorreu.';
    } else {
        // Processa ação se informada
        if ($acao === 'confirmar' || $acao === 'recusar' || $acao === 'talvez') {
            $status = [
                'confirmar' => 'confirmado',
                'recusar' => 'recusado',
                'talvez' => 'talvez'
            ][$acao];
            
            $motivo = isset($_POST['motivo']) ? sanitize($_POST['motivo']) : null;
            
            try {
                // Verifica se já estava confirmado antes
                $ja_confirmado = ($participante['status_confirmacao'] === 'confirmado');
                
                $stmt = $pdo->prepare("
                    UPDATE eventos_participantes 
                    SET status_confirmacao = ?, 
                        motivo_recusa = ?,
                        data_confirmacao = NOW()
                    WHERE token_confirmacao = ?
                ");
                $stmt->execute([$status, $motivo, $token]);
                
                // Adiciona pontos se está confirmando e não estava confirmado antes
                if ($status === 'confirmado' && !$ja_confirmado) {
                    require_once __DIR__ . '/../includes/pontuacao.php';
                    adicionar_pontos('confirmar_evento', null, $participante['colaborador_id'], $participante['evento_id'], 'evento');
                }
                
                $sucesso = [
                    'confirmado' => 'Sua presença foi confirmada com sucesso!',
                    'recusado' => 'Sua resposta foi registrada. Sentiremos sua falta!',
                    'talvez' => 'Sua resposta "Talvez" foi registrada.'
                ][$status];
                
                // Atualiza dados do participante
                $participante['status_confirmacao'] = $status;
                
            } catch (PDOException $e) {
                $erro = 'Erro ao processar sua resposta. Tente novamente.';
            }
        }
    }
}

// Labels
$tipos_evento = [
    'reuniao' => 'Reunião',
    'treinamento' => 'Treinamento',
    'confraternizacao' => 'Confraternização',
    'palestra' => 'Palestra',
    'workshop' => 'Workshop',
    'outro' => 'Outro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmação de Presença - RH Privus</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #009ef7 0%, #0073c4 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .card-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .evento-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .evento-titulo {
            font-size: 20px;
            font-weight: 600;
            color: #181c32;
            margin-bottom: 15px;
        }
        
        .evento-tipo {
            display: inline-block;
            background: #e8f4ff;
            color: #009ef7;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .evento-detail {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #5e6278;
            font-size: 14px;
        }
        
        .evento-detail svg {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            color: #009ef7;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .status-pendente { background: #fff8dd; color: #f6c000; }
        .status-confirmado { background: #e8fff3; color: #50cd89; }
        .status-recusado { background: #fff5f8; color: #f1416c; }
        .status-talvez { background: #f8f5ff; color: #7239ea; }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-success {
            background: #50cd89;
            color: white;
        }
        
        .btn-success:hover {
            background: #47be7d;
        }
        
        .btn-danger {
            background: #f1416c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d9214e;
        }
        
        .btn-warning {
            background: #ffc700;
            color: #181c32;
        }
        
        .btn-warning:hover {
            background: #f1bc00;
        }
        
        .btn-light {
            background: #f5f8fa;
            color: #5e6278;
        }
        
        .btn-light:hover {
            background: #e4e6ef;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #e8fff3;
            color: #50cd89;
            border: 1px solid #50cd89;
        }
        
        .alert-error {
            background: #fff5f8;
            color: #f1416c;
            border: 1px solid #f1416c;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #181c32;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e4e6ef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #009ef7;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #a1a5b7;
            font-size: 13px;
        }
        
        .link-virtual {
            display: inline-block;
            background: #e8f4ff;
            color: #009ef7;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .link-virtual:hover {
            background: #cce5ff;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h1>Confirmação de Presença</h1>
            <p>RH Privus - Sistema de Gestão</p>
        </div>
        
        <div class="card-body">
            <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($erro) ?>
            </div>
            <div class="text-center">
                <a href="login.php" class="btn btn-light">Ir para o Sistema</a>
            </div>
            
            <?php elseif (isset($sucesso)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($sucesso) ?>
            </div>
            
            <div class="evento-info">
                <span class="evento-tipo"><?= $tipos_evento[$participante['tipo']] ?? $participante['tipo'] ?></span>
                <div class="evento-titulo"><?= htmlspecialchars($participante['titulo']) ?></div>
                
                <div class="evento-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <?= date('d/m/Y', strtotime($participante['data_evento'])) ?>
                </div>
                
                <div class="evento-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <?= date('H:i', strtotime($participante['hora_inicio'])) ?>
                    <?php if ($participante['hora_fim']): ?>
                    - <?= date('H:i', strtotime($participante['hora_fim'])) ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($participante['local']): ?>
                <div class="evento-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <?= htmlspecialchars($participante['local']) ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center">
                <p class="text-muted">Seu status atual:</p>
                <?php
                $statusClass = [
                    'pendente' => 'status-pendente',
                    'confirmado' => 'status-confirmado',
                    'recusado' => 'status-recusado',
                    'talvez' => 'status-talvez'
                ][$participante['status_confirmacao']] ?? 'status-pendente';
                $statusLabel = [
                    'pendente' => 'Pendente',
                    'confirmado' => 'Confirmado',
                    'recusado' => 'Recusado',
                    'talvez' => 'Talvez'
                ][$participante['status_confirmacao']] ?? 'Pendente';
                ?>
                <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                
                <?php if ($participante['link_virtual']): ?>
                <div style="margin-top: 20px;">
                    <a href="<?= htmlspecialchars($participante['link_virtual']) ?>" target="_blank" class="link-virtual">
                        🔗 Acessar Reunião Virtual
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- Mostrar evento e opções -->
            <p style="margin-bottom: 15px;">Olá <strong><?= htmlspecialchars($participante['nome_completo']) ?></strong>,</p>
            <p style="margin-bottom: 20px; color: #5e6278;">Você foi convidado(a) para o seguinte evento:</p>
            
            <div class="evento-info">
                <span class="evento-tipo"><?= $tipos_evento[$participante['tipo']] ?? $participante['tipo'] ?></span>
                <div class="evento-titulo"><?= htmlspecialchars($participante['titulo']) ?></div>
                
                <div class="evento-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <?= date('d/m/Y', strtotime($participante['data_evento'])) ?>
                </div>
                
                <div class="evento-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <?= date('H:i', strtotime($participante['hora_inicio'])) ?>
                    <?php if ($participante['hora_fim']): ?>
                    - <?= date('H:i', strtotime($participante['hora_fim'])) ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($participante['local']): ?>
                <div class="evento-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <?= htmlspecialchars($participante['local']) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($participante['descricao']): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e4e6ef;">
                    <p style="color: #5e6278; font-size: 14px;"><?= nl2br(htmlspecialchars($participante['descricao'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($participante['status_confirmacao'] === 'pendente'): ?>
            <p style="font-weight: 500; margin-bottom: 15px;">Por favor, confirme sua presença:</p>
            
            <div class="btn-group">
                <a href="?token=<?= urlencode($token) ?>&acao=confirmar" class="btn btn-success">
                    ✓ Confirmar
                </a>
                <a href="?token=<?= urlencode($token) ?>&acao=talvez" class="btn btn-warning">
                    ? Talvez
                </a>
                <a href="?token=<?= urlencode($token) ?>&acao=recusar" class="btn btn-danger">
                    ✗ Recusar
                </a>
            </div>
            
            <?php else: ?>
            <div class="text-center">
                <p class="text-muted">Seu status atual:</p>
                <?php
                $statusClass = [
                    'pendente' => 'status-pendente',
                    'confirmado' => 'status-confirmado',
                    'recusado' => 'status-recusado',
                    'talvez' => 'status-talvez'
                ][$participante['status_confirmacao']] ?? 'status-pendente';
                $statusLabel = [
                    'pendente' => 'Pendente',
                    'confirmado' => 'Confirmado',
                    'recusado' => 'Recusado',
                    'talvez' => 'Talvez'
                ][$participante['status_confirmacao']] ?? 'Pendente';
                ?>
                <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                
                <p style="margin-top: 20px;">Deseja alterar sua resposta?</p>
                <div class="btn-group">
                    <a href="?token=<?= urlencode($token) ?>&acao=confirmar" class="btn btn-success">
                        ✓ Confirmar
                    </a>
                    <a href="?token=<?= urlencode($token) ?>&acao=talvez" class="btn btn-warning">
                        ? Talvez
                    </a>
                    <a href="?token=<?= urlencode($token) ?>&acao=recusar" class="btn btn-danger">
                        ✗ Recusar
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
