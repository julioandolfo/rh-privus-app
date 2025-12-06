<?php
/**
 * Script para processar alertas de cursos obrigatórios
 * Deve ser executado via cron a cada hora ou 6 horas
 * 
 * Exemplo de cron:
 * 0 * * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_alertas_lms.php
 * 
 * Ou a cada 6 horas:
 * 0 *\/6 * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_alertas_lms.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/lms_obrigatorios.php';
require_once __DIR__ . '/../includes/lms_functions.php';

// Define timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $pdo = getDB();
    
    echo "=== PROCESSAMENTO DE ALERTAS LMS ===\n";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Processa alertas agendados
    $resultado = processar_alertas_agendados();
    
    if (is_array($resultado) && isset($resultado['processados']) && isset($resultado['erros'])) {
        echo "Alertas processados: {$resultado['processados']}\n";
        echo "Erros: {$resultado['erros']}\n";
    } else {
        echo "Alertas processados: 0\n";
        echo "Erros: 0\n";
    }
    
    // Atualiza status de cursos obrigatórios baseado no progresso
    try {
        atualizar_status_cursos_obrigatorios();
    } catch (Exception $e) {
        echo "Aviso ao atualizar status de cursos: " . $e->getMessage() . "\n";
        error_log("Erro ao atualizar status de cursos obrigatórios: " . $e->getMessage());
    }
    
    echo "\nProcessamento concluído com sucesso!\n";
    
} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Atualiza status de cursos obrigatórios baseado no progresso
 */
function atualizar_status_cursos_obrigatorios() {
    $pdo = getDB();
    
    // Busca cursos obrigatórios pendentes ou em andamento
    $stmt = $pdo->prepare("
        SELECT coc.*, c.id as curso_id
        FROM cursos_obrigatorios_colaboradores coc
        INNER JOIN cursos c ON c.id = coc.curso_id
        WHERE coc.status IN ('pendente', 'em_andamento')
    ");
    $stmt->execute();
    $cursos_obrigatorios = $stmt->fetchAll();
    
    foreach ($cursos_obrigatorios as $curso_obrigatorio) {
        // Verifica se curso está completo
        $completo = verificar_curso_completo($curso_obrigatorio['colaborador_id'], $curso_obrigatorio['curso_id']);
        
        if ($completo && $curso_obrigatorio['status'] != 'concluido') {
            // Atualiza para concluído
            $stmt = $pdo->prepare("
                UPDATE cursos_obrigatorios_colaboradores 
                SET status = 'concluido',
                    data_conclusao = CURDATE()
                WHERE id = ?
            ");
            $stmt->execute([$curso_obrigatorio['id']]);
            
            echo "Curso obrigatório #{$curso_obrigatorio['id']} marcado como concluído\n";
        } elseif (!$completo && $curso_obrigatorio['status'] == 'pendente') {
            // Verifica se há progresso (mudou para em_andamento)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM progresso_colaborador 
                WHERE colaborador_id = ? AND curso_id = ? AND status != 'nao_iniciado'
            ");
            $stmt->execute([$curso_obrigatorio['colaborador_id'], $curso_obrigatorio['curso_id']]);
            $tem_progresso = $stmt->fetch()['total'] > 0;
            
            if ($tem_progresso) {
                $stmt = $pdo->prepare("
                    UPDATE cursos_obrigatorios_colaboradores 
                    SET status = 'em_andamento',
                        data_inicio = CURDATE()
                    WHERE id = ?
                ");
                $stmt->execute([$curso_obrigatorio['id']]);
            }
        }
        
        // Verifica se está vencido
        if ($curso_obrigatorio['data_limite'] < date('Y-m-d') && $curso_obrigatorio['status'] != 'vencido' && !$completo) {
            $stmt = $pdo->prepare("
                UPDATE cursos_obrigatorios_colaboradores 
                SET status = 'vencido'
                WHERE id = ?
            ");
            $stmt->execute([$curso_obrigatorio['id']]);
        }
    }
}

