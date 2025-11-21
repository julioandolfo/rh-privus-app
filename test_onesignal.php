<?php
/**
 * Página de Teste - OneSignal
 * Acesse: http://localhost/rh-privus/test_onesignal.php
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/push_notifications.php';

// Simula usuário ADMIN logado (ajuste conforme necessário)
if (!isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = [
        'id' => 1,
        'role' => 'ADMIN'
    ];
}

$pdo = getDB();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste OneSignal - RH Privus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { margin-bottom: 20px; }
        .status-ok { color: #28a745; }
        .status-erro { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h2>🧪 Teste de Notificação OneSignal</h2>
            </div>
            <div class="card-body">
                <?php
                // Verifica configuração
                $stmt_config = $pdo->query("SELECT * FROM onesignal_config ORDER BY id DESC LIMIT 1");
                $config = $stmt_config->fetch();
                
                // Verifica se tabela existe, se não, cria
                try {
                    $pdo->query("SELECT 1 FROM onesignal_subscriptions LIMIT 1");
                } catch (PDOException $e) {
                    // Tabela não existe, cria
                    echo '<div class="alert alert-warning">';
                    echo '<strong>⚠️ Tabelas não criadas ainda!</strong><br>';
                    echo 'Execute a migração: <a href="executar_migracao_onesignal.php" class="btn btn-sm btn-primary">Executar Migração</a>';
                    echo '</div>';
                    exit;
                }
                
                // Verifica subscriptions
                $stmt_subs = $pdo->query("SELECT COUNT(*) as total FROM onesignal_subscriptions");
                $total_subs = $stmt_subs->fetch()['total'];
                
                // Status
                $config_ok = $config && !empty($config['app_id']) && !empty($config['rest_api_key']);
                ?>
                
                <h3>Status da Configuração</h3>
                <ul>
                    <li class="<?= $config_ok ? 'status-ok' : 'status-erro' ?>">
                        <?= $config_ok ? '✅' : '❌' ?> Configuração: 
                        <?= $config_ok ? 'OK' : 'Não configurado' ?>
                    </li>
                    <li class="<?= $total_subs > 0 ? 'status-ok' : 'status-warning' ?>">
                        <?= $total_subs > 0 ? '✅' : '⚠️' ?> Subscriptions: 
                        <?= $total_subs > 0 ? "$total_subs dispositivo(s)" : 'Nenhum dispositivo' ?>
                    </li>
                </ul>
                
                <?php if ($config_ok && $total_subs > 0): ?>
                    <hr>
                    <h3>Enviar Notificação de Teste</h3>
                    
                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_teste'])) {
                        // Busca primeiro colaborador com subscription
                        $stmt = $pdo->query("
                            SELECT colaborador_id 
                            FROM onesignal_subscriptions 
                            WHERE colaborador_id IS NOT NULL 
                            LIMIT 1
                        ");
                        $sub = $stmt->fetch();
                        
                        if ($sub) {
                            $result = enviar_push_colaborador(
                                $sub['colaborador_id'],
                                $_POST['titulo'] ?? 'Teste OneSignal',
                                $_POST['mensagem'] ?? 'Esta é uma notificação de teste!',
                                '/rh-privus/pages/dashboard.php'
                            );
                            
                            if ($result['success']) {
                                echo '<div class="alert alert-success">';
                                echo '<strong>✅ Notificação enviada com sucesso!</strong><br>';
                                echo "Enviadas: {$result['enviadas']}<br>";
                                echo 'Verifique seu dispositivo em alguns segundos.';
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-danger">';
                                echo '<strong>❌ Erro ao enviar:</strong> ' . htmlspecialchars($result['message']);
                                echo '</div>';
                            }
                        }
                    }
                    ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Título:</label>
                            <input type="text" name="titulo" class="form-control" value="Teste OneSignal" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mensagem:</label>
                            <textarea name="mensagem" class="form-control" rows="3" required>Esta é uma notificação de teste! Se você recebeu isso, o OneSignal está funcionando! 🎉</textarea>
                        </div>
                        <button type="submit" name="enviar_teste" class="btn btn-primary">
                            📤 Enviar Notificação de Teste
                        </button>
                    </form>
                    
                    <hr>
                    <h4>Dispositivos Registrados:</h4>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Player ID</th>
                                <th>Colaborador</th>
                                <th>Dispositivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt_list = $pdo->query("
                                SELECT os.*, c.nome_completo
                                FROM onesignal_subscriptions os
                                LEFT JOIN colaboradores c ON os.colaborador_id = c.id
                                ORDER BY os.created_at DESC
                                LIMIT 5
                            ");
                            foreach ($stmt_list->fetchAll() as $sub):
                            ?>
                            <tr>
                                <td><small><?= substr($sub['player_id'], 0, 30) ?>...</small></td>
                                <td><?= htmlspecialchars($sub['nome_completo'] ?? '-') ?></td>
                                <td><span class="badge bg-info"><?= $sub['device_type'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                <?php elseif (!$config_ok): ?>
                    <div class="alert alert-danger">
                        <strong>❌ OneSignal não está configurado!</strong><br>
                        Acesse: <a href="pages/configuracoes_onesignal.php">Configurações OneSignal</a> e configure as credenciais.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Nenhum dispositivo registrado ainda!</strong><br>
                        Faça login no sistema e permita notificações para registrar o primeiro dispositivo.
                    </div>
                <?php endif; ?>
                
                <hr>
                <h4>Como Verificar:</h4>
                <ol>
                    <li>Abra o sistema em outra aba</li>
                    <li>Faça login</li>
                    <li>Permita notificações quando solicitado</li>
                    <li>Volte aqui e envie uma notificação de teste</li>
                    <li>Verifique se a notificação aparece!</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>

