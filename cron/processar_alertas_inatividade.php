<?php
/**
 * Script para processar alertas de inatividade
 * Envia email e push para colaboradores que não acessam o sistema há X dias
 * 
 * Deve ser executado via cron diariamente
 * 
 * Exemplo de cron (executa diariamente às 9h):
 * 0 9 * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_alertas_inatividade.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email_templates.php';
require_once __DIR__ . '/../includes/push_notifications.php';

// Define timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações
$DIAS_INATIVIDADE = 7; // Dias sem acessar para enviar alerta
$LIMITE_ALERTAS_POR_EXECUCAO = 50; // Limite de emails por execução
$ENVIAR_PUSH = true; // Envia notificação push via OneSignal

try {
    $pdo = getDB();
    
    echo "=== PROCESSAMENTO DE ALERTAS DE INATIVIDADE ===\n";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
    echo "Dias de inatividade: {$DIAS_INATIVIDADE}\n";
    echo "Limite de alertas: {$LIMITE_ALERTAS_POR_EXECUCAO}\n\n";
    
    // Busca URL do sistema
    $sistema_url = get_base_url();
    
    // Busca usuários inativos
    // Considera último login OU última atividade na tabela acessos
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            u.id as usuario_id,
            u.nome,
            u.email,
            u.colaborador_id,
            u.ultimo_login,
            c.nome_completo,
            c.email_pessoal,
            e.nome_fantasia as empresa_nome,
            GREATEST(
                COALESCE(u.ultimo_login, '2000-01-01'),
                COALESCE(
                    (SELECT MAX(data_acesso) FROM acessos WHERE usuario_id = u.id),
                    '2000-01-01'
                )
            ) as ultimo_acesso
        FROM usuarios u
        LEFT JOIN colaboradores c ON u.colaborador_id = c.id
        LEFT JOIN empresas e ON u.empresa_id = e.id
        WHERE u.status = 'ativo'
        AND DATEDIFF(CURDATE(), GREATEST(
            COALESCE(u.ultimo_login, '2000-01-01'),
            COALESCE(
                (SELECT MAX(data_acesso) FROM acessos WHERE usuario_id = u.id),
                '2000-01-01'
            )
        )) >= ?
        ORDER BY ultimo_acesso ASC
        LIMIT ?
    ");
    $stmt->execute([$DIAS_INATIVIDADE, $LIMITE_ALERTAS_POR_EXECUCAO]);
    $usuarios_inativos = $stmt->fetchAll();
    
    // Busca colaboradores inativos (sem usuário vinculado)
    $stmt_colab = $pdo->prepare("
        SELECT 
            c.id as colaborador_id,
            NULL as usuario_id,
            c.nome_completo,
            c.email_pessoal as email,
            e.nome_fantasia as empresa_nome,
            COALESCE(
                (SELECT MAX(data_acesso) FROM acessos WHERE colaborador_id = c.id),
                c.created_at
            ) as ultimo_acesso
        FROM colaboradores c
        LEFT JOIN empresas e ON c.empresa_id = e.id
        WHERE c.status = 'ativo'
        AND c.usuario_id IS NULL
        AND c.email_pessoal IS NOT NULL
        AND c.email_pessoal != ''
        AND DATEDIFF(CURDATE(), COALESCE(
            (SELECT MAX(data_acesso) FROM acessos WHERE colaborador_id = c.id),
            c.created_at
        )) >= ?
        ORDER BY ultimo_acesso ASC
        LIMIT ?
    ");
    $stmt_colab->execute([$DIAS_INATIVIDADE, $LIMITE_ALERTAS_POR_EXECUCAO]);
    $colaboradores_inativos = $stmt_colab->fetchAll();
    
    // Combina os resultados
    $inativos = array_merge($usuarios_inativos, $colaboradores_inativos);
    
    echo "Usuários/Colaboradores inativos encontrados: " . count($inativos) . "\n\n";
    
    if (empty($inativos)) {
        echo "Nenhum usuário inativo encontrado.\n";
        exit(0);
    }
    
    // Cria tabela de controle de alertas se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alertas_enviados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo_alerta VARCHAR(50) NOT NULL,
            usuario_id INT NULL,
            colaborador_id INT NULL,
            data_envio DATETIME NOT NULL,
            INDEX idx_tipo_usuario (tipo_alerta, usuario_id),
            INDEX idx_tipo_colaborador (tipo_alerta, colaborador_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    $alertas_enviados = 0;
    $alertas_erro = 0;
    $push_enviados = 0;
    $push_erros = 0;
    
    foreach ($inativos as $inativo) {
        $usuario_id = $inativo['usuario_id'] ?? null;
        $colaborador_id = $inativo['colaborador_id'] ?? null;
        $nome = $inativo['nome_completo'] ?? $inativo['nome'];
        $email = $inativo['email_pessoal'] ?? $inativo['email'];
        $empresa_nome = $inativo['empresa_nome'] ?? 'Sistema RH';
        $ultimo_acesso = $inativo['ultimo_acesso'];
        
        // Verifica se já foi enviado alerta nos últimos 7 dias
        $stmt_check = $pdo->prepare("
            SELECT id FROM alertas_enviados 
            WHERE tipo_alerta = 'inatividade'
            AND " . ($usuario_id ? "usuario_id = ?" : "colaborador_id = ?") . "
            AND data_envio >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt_check->execute([$usuario_id ?? $colaborador_id]);
        
        if ($stmt_check->rowCount() > 0) {
            echo "  [SKIP] {$nome} - Alerta já enviado nos últimos 7 dias\n";
            continue;
        }
        
        // Calcula dias de inatividade
        $dias_inativo = floor((strtotime(date('Y-m-d')) - strtotime($ultimo_acesso)) / 86400);
        
        // Prepara variáveis para o template
        $variaveis = [
            'nome_completo' => $nome,
            'dias_inativo' => $dias_inativo,
            'data_ultimo_acesso' => formatar_data($ultimo_acesso, 'd/m/Y'),
            'sistema_url' => $sistema_url,
            'empresa_nome' => $empresa_nome
        ];
        
        // Envia email
        echo "  [ENVIANDO] {$nome} ({$email}) - {$dias_inativo} dias inativo\n";
        
        $resultado = enviar_email_template('alerta_inatividade', $email, $variaveis);
        
        if ($resultado['success']) {
            // Registra alerta enviado
            $stmt_log = $pdo->prepare("
                INSERT INTO alertas_enviados (tipo_alerta, usuario_id, colaborador_id, data_envio)
                VALUES ('inatividade', ?, ?, NOW())
            ");
            $stmt_log->execute([$usuario_id, $colaborador_id]);
            
            $alertas_enviados++;
            echo "  [OK] Email enviado com sucesso\n";
        } else {
            $alertas_erro++;
            echo "  [ERRO] Falha ao enviar email: " . ($resultado['message'] ?? 'Erro desconhecido') . "\n";
        }
        
        // Envia push notification via OneSignal
        if ($ENVIAR_PUSH) {
            $titulo_push = "Sentimos sua falta!";
            $mensagem_push = "Você não acessa o sistema há {$dias_inativo} dias. Volte para conferir as novidades!";
            $url_push = $sistema_url . '/pages/dashboard.php';
            
            $resultado_push = null;
            if ($usuario_id) {
                $resultado_push = enviar_push_usuario($usuario_id, $titulo_push, $mensagem_push, $url_push);
            } elseif ($colaborador_id) {
                $resultado_push = enviar_push_colaborador($colaborador_id, $titulo_push, $mensagem_push, $url_push);
            }
            
            if ($resultado_push && $resultado_push['success']) {
                $push_enviados++;
                echo "  [OK] Push enviado com sucesso\n";
            } else {
                $push_erros++;
                $msg_erro = $resultado_push['message'] ?? 'Usuário sem dispositivo registrado';
                echo "  [PUSH] Não enviado: {$msg_erro}\n";
            }
        }
        
        echo "\n";
    }
    
    echo "\n=== RESUMO ===\n";
    echo "Emails enviados: {$alertas_enviados}\n";
    echo "Emails com erro: {$alertas_erro}\n";
    echo "Push enviados: {$push_enviados}\n";
    echo "Push não enviados: {$push_erros}\n";
    echo "\nProcessamento concluído com sucesso!\n";
    
} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
