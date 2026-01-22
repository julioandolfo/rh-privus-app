<?php
/**
 * Script para processar alertas de emoções não registradas
 * Envia email para colaboradores que não registram emoções há X dias
 * 
 * Deve ser executado via cron diariamente
 * 
 * Exemplo de cron (executa diariamente às 9h):
 * 0 9 * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_alertas_emocoes.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email_templates.php';

// Define timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações
$DIAS_SEM_EMOCAO = 7; // Dias sem registrar emoção para enviar alerta
$LIMITE_ALERTAS_POR_EXECUCAO = 50; // Limite de emails por execução

try {
    $pdo = getDB();
    
    echo "=== PROCESSAMENTO DE ALERTAS DE EMOÇÕES ===\n";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
    echo "Dias sem registro: {$DIAS_SEM_EMOCAO}\n";
    echo "Limite de alertas: {$LIMITE_ALERTAS_POR_EXECUCAO}\n\n";
    
    // Busca URL do sistema
    $sistema_url = get_base_url();
    
    // Busca usuários ativos que não registram emoções há X dias
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            u.id as usuario_id,
            u.nome,
            u.email,
            u.colaborador_id,
            c.nome_completo,
            c.email_pessoal,
            e.nome_fantasia as empresa_nome,
            (
                SELECT MAX(data_registro) 
                FROM emocoes 
                WHERE usuario_id = u.id OR colaborador_id = u.colaborador_id
            ) as ultimo_registro_emocao
        FROM usuarios u
        LEFT JOIN colaboradores c ON u.colaborador_id = c.id
        LEFT JOIN empresas e ON u.empresa_id = e.id
        WHERE u.status = 'ativo'
        AND u.role = 'COLABORADOR'
        AND (
            -- Nunca registrou emoção e foi criado há mais de X dias
            (
                (SELECT COUNT(*) FROM emocoes WHERE usuario_id = u.id OR colaborador_id = u.colaborador_id) = 0
                AND DATEDIFF(CURDATE(), u.created_at) >= ?
            )
            OR
            -- Já registrou mas há mais de X dias
            (
                (SELECT MAX(data_registro) FROM emocoes WHERE usuario_id = u.id OR colaborador_id = u.colaborador_id) IS NOT NULL
                AND DATEDIFF(CURDATE(), (SELECT MAX(data_registro) FROM emocoes WHERE usuario_id = u.id OR colaborador_id = u.colaborador_id)) >= ?
            )
        )
        ORDER BY ultimo_registro_emocao ASC
        LIMIT ?
    ");
    $stmt->execute([$DIAS_SEM_EMOCAO, $DIAS_SEM_EMOCAO, $LIMITE_ALERTAS_POR_EXECUCAO]);
    $usuarios_sem_emocao = $stmt->fetchAll();
    
    // Busca colaboradores ativos sem usuário vinculado
    $stmt_colab = $pdo->prepare("
        SELECT 
            c.id as colaborador_id,
            NULL as usuario_id,
            c.nome_completo,
            c.email_pessoal as email,
            e.nome_fantasia as empresa_nome,
            (
                SELECT MAX(data_registro) 
                FROM emocoes 
                WHERE colaborador_id = c.id
            ) as ultimo_registro_emocao
        FROM colaboradores c
        LEFT JOIN empresas e ON c.empresa_id = e.id
        WHERE c.status = 'ativo'
        AND c.usuario_id IS NULL
        AND c.email_pessoal IS NOT NULL
        AND c.email_pessoal != ''
        AND (
            -- Nunca registrou emoção e foi criado há mais de X dias
            (
                (SELECT COUNT(*) FROM emocoes WHERE colaborador_id = c.id) = 0
                AND DATEDIFF(CURDATE(), c.created_at) >= ?
            )
            OR
            -- Já registrou mas há mais de X dias
            (
                (SELECT MAX(data_registro) FROM emocoes WHERE colaborador_id = c.id) IS NOT NULL
                AND DATEDIFF(CURDATE(), (SELECT MAX(data_registro) FROM emocoes WHERE colaborador_id = c.id)) >= ?
            )
        )
        ORDER BY ultimo_registro_emocao ASC
        LIMIT ?
    ");
    $stmt_colab->execute([$DIAS_SEM_EMOCAO, $DIAS_SEM_EMOCAO, $LIMITE_ALERTAS_POR_EXECUCAO]);
    $colaboradores_sem_emocao = $stmt_colab->fetchAll();
    
    // Combina os resultados
    $sem_emocao = array_merge($usuarios_sem_emocao, $colaboradores_sem_emocao);
    
    echo "Usuários/Colaboradores sem registro de emoção: " . count($sem_emocao) . "\n\n";
    
    if (empty($sem_emocao)) {
        echo "Nenhum usuário sem registro de emoção encontrado.\n";
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
    
    foreach ($sem_emocao as $pessoa) {
        $usuario_id = $pessoa['usuario_id'] ?? null;
        $colaborador_id = $pessoa['colaborador_id'] ?? null;
        $nome = $pessoa['nome_completo'] ?? $pessoa['nome'];
        $email = $pessoa['email_pessoal'] ?? $pessoa['email'];
        $empresa_nome = $pessoa['empresa_nome'] ?? 'Sistema RH';
        $ultimo_registro = $pessoa['ultimo_registro_emocao'];
        
        // Verifica se já foi enviado alerta nos últimos 7 dias
        $stmt_check = $pdo->prepare("
            SELECT id FROM alertas_enviados 
            WHERE tipo_alerta = 'emocoes'
            AND " . ($usuario_id ? "usuario_id = ?" : "colaborador_id = ?") . "
            AND data_envio >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt_check->execute([$usuario_id ?? $colaborador_id]);
        
        if ($stmt_check->rowCount() > 0) {
            echo "  [SKIP] {$nome} - Alerta já enviado nos últimos 7 dias\n";
            continue;
        }
        
        // Calcula dias sem registro
        if ($ultimo_registro) {
            $dias_sem_registro = floor((strtotime(date('Y-m-d')) - strtotime($ultimo_registro)) / 86400);
            $data_ultimo_registro = formatar_data($ultimo_registro, 'd/m/Y');
        } else {
            $dias_sem_registro = 'mais de 30';
            $data_ultimo_registro = 'Nunca';
        }
        
        // Prepara variáveis para o template
        $variaveis = [
            'nome_completo' => $nome,
            'dias_sem_registro' => $dias_sem_registro,
            'data_ultimo_registro' => $data_ultimo_registro,
            'sistema_url' => $sistema_url,
            'empresa_nome' => $empresa_nome
        ];
        
        // Envia email
        echo "  [ENVIANDO] {$nome} ({$email}) - {$dias_sem_registro} dias sem registro\n";
        
        $resultado = enviar_email_template('alerta_emocoes', $email, $variaveis);
        
        if ($resultado['success']) {
            // Registra alerta enviado
            $stmt_log = $pdo->prepare("
                INSERT INTO alertas_enviados (tipo_alerta, usuario_id, colaborador_id, data_envio)
                VALUES ('emocoes', ?, ?, NOW())
            ");
            $stmt_log->execute([$usuario_id, $colaborador_id]);
            
            $alertas_enviados++;
            echo "  [OK] Email enviado com sucesso\n";
        } else {
            $alertas_erro++;
            echo "  [ERRO] Falha ao enviar email: " . ($resultado['message'] ?? 'Erro desconhecido') . "\n";
        }
        
        echo "\n";
    }
    
    echo "\n=== RESUMO ===\n";
    echo "Alertas enviados: {$alertas_enviados}\n";
    echo "Erros: {$alertas_erro}\n";
    echo "\nProcessamento concluído com sucesso!\n";
    
} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
