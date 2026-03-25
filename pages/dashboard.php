<?php
/**
 * Dashboard - Página Inicial (Metronic Theme com Gráficos)
 */

// IMPORTANTE: Garante que nenhum output seja enviado antes dos headers
ob_start();

// Headers para evitar cache e garantir resposta correta
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

// Verifica login ANTES de incluir o header
require_page_permission('dashboard.php');

// Limpa buffer antes de incluir header (que vai gerar HTML)
ob_end_clean();

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'] ?? null;

// Se for colaborador, mostra dashboard personalizado
if (is_colaborador() && !empty($colaborador_id)) {
    // Dashboard do Colaborador - Informações pessoais
    try {
        // Busca dados do colaborador
        $stmt = $pdo->prepare("
            SELECT c.*, e.nome_fantasia as empresa_nome, s.nome_setor, car.nome_cargo
            FROM colaboradores c
            LEFT JOIN empresas e ON c.empresa_id = e.id
            LEFT JOIN setores s ON c.setor_id = s.id
            LEFT JOIN cargos car ON c.cargo_id = car.id
            WHERE c.id = ?
        ");
        $stmt->execute([$colaborador_id]);
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ocorrências do colaborador no mês
        $mes_atual = date('Y-m');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM ocorrencias 
            WHERE colaborador_id = ? AND DATE_FORMAT(data_ocorrencia, '%Y-%m') = ?
        ");
        $stmt->execute([$colaborador_id, $mes_atual]);
        $ocorrencias_mes = $stmt->fetch()['total'];
        
        $colaborador_dashboard_aviso_occ = colaborador_ocorrencias_flags_sem_detalhe();
        
        // Total no modo aviso: só ocorrências ainda dentro do prazo de exibição (30 dias)
        if ($colaborador_dashboard_aviso_occ) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) as total FROM ocorrencias o WHERE o.colaborador_id = ? AND '
                . avisos_colaborador_sql_ocorrencia_dentro_prazo('o')
            );
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM ocorrencias WHERE colaborador_id = ?');
        }
        $stmt->execute([$colaborador_id]);
        $total_ocorrencias = $stmt->fetch()['total'];
        
        if ($colaborador_dashboard_aviso_occ) {
            $ocorrencias_recentes = [];
            $meses_grafico = [];
            $ocorrencias_grafico = [];
        } else {
            // Ocorrências recentes (últimas 5)
            $stmt = $pdo->prepare("
                SELECT o.*, tp.nome as tipo_nome, u.nome as usuario_nome
                FROM ocorrencias o
                LEFT JOIN tipos_ocorrencias tp ON o.tipo_ocorrencia_id = tp.id
                LEFT JOIN usuarios u ON o.usuario_id = u.id
                WHERE o.colaborador_id = ?
                ORDER BY o.data_ocorrencia DESC, o.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$colaborador_id]);
            $ocorrencias_recentes = $stmt->fetchAll();
            
            // Gráfico de ocorrências por mês (últimos 6 meses)
            $meses_grafico = [];
            $ocorrencias_grafico = [];
            for ($i = 5; $i >= 0; $i--) {
                $mes = date('Y-m', strtotime("-$i months"));
                $meses_grafico[] = date('M/Y', strtotime("-$i months"));
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM ocorrencias 
                    WHERE colaborador_id = ? AND DATE_FORMAT(data_ocorrencia, '%Y-%m') = ?
                ");
                $stmt->execute([$colaborador_id, $mes]);
                $ocorrencias_grafico[] = $stmt->fetch()['total'];
            }
        }
        
        // Pagamentos/Fechamentos do colaborador
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                f.id as fechamento_id,
                f.mes_referencia,
                f.data_fechamento,
                f.status as fechamento_status,
                e.nome_fantasia as empresa_nome,
                i.valor_total,
                i.documento_status,
                i.documento_data_envio,
                i.documento_data_aprovacao
            FROM fechamentos_pagamento f
            INNER JOIN fechamentos_pagamento_itens i ON f.id = i.fechamento_id
            LEFT JOIN empresas e ON f.empresa_id = e.id
            WHERE i.colaborador_id = ? AND f.status = 'fechado'
            ORDER BY f.mes_referencia DESC
            LIMIT 5
        ");
        $stmt->execute([$colaborador_id]);
        $pagamentos_recentes = $stmt->fetchAll();
        
        // Estatísticas de documentos de pagamento
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN i.documento_status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN i.documento_status = 'enviado' THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN i.documento_status = 'aprovado' THEN 1 ELSE 0 END) as aprovados,
                SUM(CASE WHEN i.documento_status = 'rejeitado' THEN 1 ELSE 0 END) as rejeitados
            FROM fechamentos_pagamento f
            INNER JOIN fechamentos_pagamento_itens i ON f.id = i.fechamento_id
            WHERE i.colaborador_id = ? AND f.status = 'fechado'
        ");
        $stmt->execute([$colaborador_id]);
        $stats_documentos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Total de pagamentos fechados
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT f.id) as total
            FROM fechamentos_pagamento f
            INNER JOIN fechamentos_pagamento_itens i ON f.id = i.fechamento_id
            WHERE i.colaborador_id = ? AND f.status = 'fechado'
        ");
        $stmt->execute([$colaborador_id]);
        $total_pagamentos = $stmt->fetch()['total'];
        
        // Valor total recebido (últimos 12 meses)
        $stmt = $pdo->prepare("
            SELECT SUM(i.valor_total) as total
            FROM fechamentos_pagamento f
            INNER JOIN fechamentos_pagamento_itens i ON f.id = i.fechamento_id
            WHERE i.colaborador_id = ? 
            AND f.status = 'fechado'
            AND f.mes_referencia >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        ");
        $stmt->execute([$colaborador_id]);
        $valor_total_ano = $stmt->fetch()['total'] ?? 0;
        
    } catch (PDOException $e) {
        $error = 'Erro ao carregar dados: ' . $e->getMessage();
        $colaborador = null;
        $ocorrencias_mes = 0;
        $total_ocorrencias = 0;
        $colaborador_dashboard_aviso_occ = colaborador_ocorrencias_flags_sem_detalhe();
        $ocorrencias_recentes = [];
        $ocorrencias_grafico = [];
        $meses_grafico = [];
        $pagamentos_recentes = [];
        $stats_documentos = ['total' => 0, 'pendentes' => 0, 'enviados' => 0, 'aprovados' => 0, 'rejeitados' => 0];
        $total_pagamentos = 0;
        $valor_total_ano = 0;
    }
} else {
    // Dashboard Admin/RH/GESTOR - Estatísticas gerais
    // Inicializa variáveis
    $setor_id = null;
    
    // Busca setor do gestor se necessário
    if ($usuario['role'] === 'GESTOR') {
        $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt_setor->execute([$usuario['id']]);
        $user_data = $stmt_setor->fetch();
        $setor_id = $user_data['setor_id'] ?? 0;
    }
    
    try {
        // Total de colaboradores
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM colaboradores");
        } elseif ($usuario['role'] === 'RH') {
            // RH pode ter múltiplas empresas
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id IN ($placeholders)");
                $stmt->execute($usuario['empresas_ids']);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id = ?");
                $stmt->execute([$usuario['empresa_id'] ?? 0]);
            }
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE setor_id = ?");
            $stmt->execute([$setor_id]);
        }
        
        $total_colaboradores = $stmt->fetch()['total'];
        
        // Colaboradores ativos
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM colaboradores WHERE status = 'ativo'");
        } elseif ($usuario['role'] === 'RH') {
            // RH pode ter múltiplas empresas
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id IN ($placeholders) AND status = 'ativo'");
                $stmt->execute($usuario['empresas_ids']);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id = ? AND status = 'ativo'");
                $stmt->execute([$usuario['empresa_id'] ?? 0]);
            }
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE setor_id = ? AND status = 'ativo'");
            $stmt->execute([$setor_id]);
        }
        
        $total_ativos = $stmt->fetch()['total'];
        
        // Ocorrências no mês
        $mes_atual = date('Y-m');
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ocorrencias WHERE DATE_FORMAT(data_ocorrencia, '%Y-%m') = ?");
            $stmt->execute([$mes_atual]);
        } elseif ($usuario['role'] === 'RH') {
            // RH pode ter múltiplas empresas
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM ocorrencias o
                    INNER JOIN colaboradores c ON o.colaborador_id = c.id
                    WHERE c.empresa_id IN ($placeholders) AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
                ");
                $params = array_merge($usuario['empresas_ids'], [$mes_atual]);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM ocorrencias o
                    INNER JOIN colaboradores c ON o.colaborador_id = c.id
                    WHERE c.empresa_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
                ");
                $stmt->execute([$usuario['empresa_id'] ?? 0, $mes_atual]);
            }
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM ocorrencias o
                INNER JOIN colaboradores c ON o.colaborador_id = c.id
                WHERE c.setor_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
            ");
            $stmt->execute([$setor_id, $mes_atual]);
        }
        
        $ocorrencias_mes = $stmt->fetch()['total'];
        
        // Busca última execução do cron de fechamentos recorrentes
        $ultima_execucao_cron = null;
        try {
            $stmt = $pdo->prepare("
                SELECT data_execucao, processados, erros, status, TIMESTAMPDIFF(HOUR, data_execucao, NOW()) as horas_atras
                FROM cron_execucoes 
                WHERE nome_cron = 'processar_fechamentos_recorrentes'
                ORDER BY data_execucao DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $ultima_execucao_cron = $stmt->fetch();
        } catch (PDOException $e) {
            // Tabela pode não existir ainda, ignora erro
        }
        
        // Dados para gráfico de ocorrências por mês (últimos 6 meses)
        $meses_grafico = [];
        $ocorrencias_grafico = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = date('Y-m', strtotime("-$i months"));
            $meses_grafico[] = date('M/Y', strtotime("-$i months"));
            
            if ($usuario['role'] === 'ADMIN') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ocorrencias WHERE DATE_FORMAT(data_ocorrencia, '%Y-%m') = ?");
                $stmt->execute([$mes]);
            } elseif ($usuario['role'] === 'RH') {
                // RH pode ter múltiplas empresas
                if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                    $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as total 
                        FROM ocorrencias o
                        INNER JOIN colaboradores c ON o.colaborador_id = c.id
                        WHERE c.empresa_id IN ($placeholders) AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
                    ");
                    $params = array_merge($usuario['empresas_ids'], [$mes]);
                    $stmt->execute($params);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as total 
                        FROM ocorrencias o
                        INNER JOIN colaboradores c ON o.colaborador_id = c.id
                        WHERE c.empresa_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
                    ");
                    $stmt->execute([$usuario['empresa_id'] ?? 0, $mes]);
                }
            } elseif ($usuario['role'] === 'GESTOR') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM ocorrencias o
                    INNER JOIN colaboradores c ON o.colaborador_id = c.id
                    WHERE c.setor_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
                ");
                $stmt->execute([$setor_id, $mes]);
            }
            $ocorrencias_grafico[] = $stmt->fetch()['total'];
        }
        
        // Dados para gráfico de ocorrências por tipo (últimos 30 dias)
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->prepare("
                SELECT tipo, COUNT(*) as total
                FROM ocorrencias
                WHERE data_ocorrencia >= ?
                GROUP BY tipo
                ORDER BY total DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio]);
        } elseif ($usuario['role'] === 'RH') {
            // RH pode ter múltiplas empresas
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("
                    SELECT o.tipo, COUNT(*) as total
                    FROM ocorrencias o
                    INNER JOIN colaboradores c ON o.colaborador_id = c.id
                    WHERE c.empresa_id IN ($placeholders) AND o.data_ocorrencia >= ?
                    GROUP BY o.tipo
                    ORDER BY total DESC
                    LIMIT 10
                ");
                $params = array_merge($usuario['empresas_ids'], [$data_inicio]);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare("
                    SELECT o.tipo, COUNT(*) as total
                    FROM ocorrencias o
                    INNER JOIN colaboradores c ON o.colaborador_id = c.id
                    WHERE c.empresa_id = ? AND o.data_ocorrencia >= ?
                    GROUP BY o.tipo
                    ORDER BY total DESC
                    LIMIT 10
                ");
                $stmt->execute([$usuario['empresa_id'] ?? 0, $data_inicio]);
            }
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("
                SELECT o.tipo, COUNT(*) as total
                FROM ocorrencias o
                INNER JOIN colaboradores c ON o.colaborador_id = c.id
                WHERE c.setor_id = ? AND o.data_ocorrencia >= ?
                GROUP BY o.tipo
                ORDER BY total DESC
                LIMIT 10
            ");
            $stmt->execute([$setor_id, $data_inicio]);
        }
        $ocorrencias_por_tipo = $stmt->fetchAll();
        
        // Dados para gráfico de colaboradores por status
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->query("
                SELECT status, COUNT(*) as total
                FROM colaboradores
                GROUP BY status
            ");
        } elseif ($usuario['role'] === 'RH') {
            // RH pode ter múltiplas empresas
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as total
                    FROM colaboradores
                    WHERE empresa_id IN ($placeholders)
                    GROUP BY status
                ");
                $stmt->execute($usuario['empresas_ids']);
            } else {
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as total
                    FROM colaboradores
                    WHERE empresa_id = ?
                    GROUP BY status
                ");
                $stmt->execute([$usuario['empresa_id'] ?? 0]);
            }
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("
                SELECT status, COUNT(*) as total
                FROM colaboradores
                WHERE setor_id = ?
                GROUP BY status
            ");
            $stmt->execute([$setor_id]);
        } else {
            $colaboradores_status = [];
        }
        $colaboradores_status = isset($stmt) ? $stmt->fetchAll() : [];
        
        // Ranking de ocorrências (últimos 30 dias) - com foto
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->prepare("
                SELECT c.id, c.nome_completo, c.foto, COUNT(o.id) as total_ocorrencias
                FROM colaboradores c
                LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
                WHERE c.status = 'ativo'
                GROUP BY c.id, c.nome_completo, c.foto
                ORDER BY total_ocorrencias DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio]);
        } elseif ($usuario['role'] === 'RH') {
            // RH pode ter múltiplas empresas
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("
                    SELECT c.id, c.nome_completo, c.foto, COUNT(o.id) as total_ocorrencias
                    FROM colaboradores c
                    LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
                    WHERE c.empresa_id IN ($placeholders) AND c.status = 'ativo'
                    GROUP BY c.id, c.nome_completo, c.foto
                    ORDER BY total_ocorrencias DESC
                    LIMIT 10
                ");
                $params = array_merge([$data_inicio], $usuario['empresas_ids']);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.nome_completo, c.foto, COUNT(o.id) as total_ocorrencias
                    FROM colaboradores c
                    LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
                    WHERE c.empresa_id = ? AND c.status = 'ativo'
                    GROUP BY c.id, c.nome_completo, c.foto
                    ORDER BY total_ocorrencias DESC
                    LIMIT 10
                ");
                $stmt->execute([$data_inicio, $usuario['empresa_id'] ?? 0]);
            }
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("
                SELECT c.id, c.nome_completo, c.foto, COUNT(o.id) as total_ocorrencias
                FROM colaboradores c
                LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
                WHERE c.setor_id = ? AND c.status = 'ativo'
                GROUP BY c.id, c.nome_completo, c.foto
                ORDER BY total_ocorrencias DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio, $setor_id]);
        } else {
            $ranking = [];
        }
        
        $ranking = isset($stmt) ? $stmt->fetchAll() : [];
        
        // Próximos aniversários (próximos 30 dias)
        $hoje = date('Y-m-d');
        $ano_atual = date('Y');
        $mes_dia_hoje = date('m-d');
        
        // Busca colaboradores com aniversário nos próximos 30 dias
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->query("
                SELECT c.id, c.nome_completo, c.foto, c.data_nascimento,
                       DATE_FORMAT(c.data_nascimento, '%m-%d') as mes_dia
                FROM colaboradores c
                WHERE c.status = 'ativo' 
                AND c.data_nascimento IS NOT NULL
                ORDER BY 
                    CASE 
                        WHEN DATE_FORMAT(c.data_nascimento, '%m-%d') >= '$mes_dia_hoje'
                        THEN DATE_FORMAT(c.data_nascimento, '%m-%d')
                        ELSE CONCAT('12-31-', DATE_FORMAT(c.data_nascimento, '%m-%d'))
                    END ASC
                LIMIT 10
            ");
        } elseif ($usuario['role'] === 'RH') {
            // RH pode ter múltiplas empresas
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("
                    SELECT c.id, c.nome_completo, c.foto, c.data_nascimento,
                           DATE_FORMAT(c.data_nascimento, '%m-%d') as mes_dia
                    FROM colaboradores c
                    WHERE c.empresa_id IN ($placeholders) 
                    AND c.status = 'ativo'
                    AND c.data_nascimento IS NOT NULL
                    ORDER BY 
                        CASE 
                            WHEN DATE_FORMAT(c.data_nascimento, '%m-%d') >= '$mes_dia_hoje'
                            THEN DATE_FORMAT(c.data_nascimento, '%m-%d')
                            ELSE CONCAT('12-31-', DATE_FORMAT(c.data_nascimento, '%m-%d'))
                        END ASC
                    LIMIT 10
                ");
                $stmt->execute($usuario['empresas_ids']);
            } else {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.nome_completo, c.foto, c.data_nascimento,
                           DATE_FORMAT(c.data_nascimento, '%m-%d') as mes_dia
                    FROM colaboradores c
                    WHERE c.empresa_id = ? 
                    AND c.status = 'ativo'
                    AND c.data_nascimento IS NOT NULL
                    ORDER BY 
                        CASE 
                            WHEN DATE_FORMAT(c.data_nascimento, '%m-%d') >= '$mes_dia_hoje'
                            THEN DATE_FORMAT(c.data_nascimento, '%m-%d')
                            ELSE CONCAT('12-31-', DATE_FORMAT(c.data_nascimento, '%m-%d'))
                        END ASC
                    LIMIT 10
                ");
                $stmt->execute([$usuario['empresa_id'] ?? 0]);
            }
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("
                SELECT c.id, c.nome_completo, c.foto, c.data_nascimento,
                       DATE_FORMAT(c.data_nascimento, '%m-%d') as mes_dia
                FROM colaboradores c
                WHERE c.setor_id = ? 
                AND c.status = 'ativo'
                AND c.data_nascimento IS NOT NULL
                ORDER BY 
                    CASE 
                        WHEN DATE_FORMAT(c.data_nascimento, '%m-%d') >= '$mes_dia_hoje'
                        THEN DATE_FORMAT(c.data_nascimento, '%m-%d')
                        ELSE CONCAT('12-31-', DATE_FORMAT(c.data_nascimento, '%m-%d'))
                    END ASC
                LIMIT 10
            ");
            $stmt->execute([$setor_id]);
        } else {
            $proximos_aniversarios = [];
        }
        
        $proximos_aniversarios = isset($stmt) ? $stmt->fetchAll() : [];
        
        // Processa aniversários para calcular dias até o aniversário
        foreach ($proximos_aniversarios as &$aniv) {
            $mes_dia = date('m-d', strtotime($aniv['data_nascimento']));
            $data_aniversario_ano = $ano_atual . '-' . $mes_dia;
            
            if (strtotime($data_aniversario_ano) < strtotime($hoje)) {
                $data_aniversario_ano = ($ano_atual + 1) . '-' . $mes_dia;
            }
            
            $dias_ate = (strtotime($data_aniversario_ano) - strtotime($hoje)) / (60 * 60 * 24);
            $aniv['dias_ate'] = $dias_ate;
            $aniv['data_formatada'] = date('d/m', strtotime($data_aniversario_ano));
            
            // Filtra apenas os próximos 30 dias
            if ($dias_ate > 30) {
                unset($aniv);
            }
        }
        unset($aniv);
        
        // Reindexa array após filtro
        $proximos_aniversarios = array_values($proximos_aniversarios);
        
        // Reindexa array após filtro
        $proximos_aniversarios = array_values($proximos_aniversarios);
        
    } catch (PDOException $e) {
        $error = 'Erro ao carregar dados: ' . $e->getMessage();
        $total_colaboradores = 0;
        $total_ativos = 0;
        $ocorrencias_mes = 0;
        $ocorrencias_grafico = [];
        $meses_grafico = [];
        $ocorrencias_por_tipo = [];
        $colaboradores_status = [];
        $ranking = [];
        $proximos_aniversarios = [];
    }
}
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light-primary" id="btn_personalizar_dashboard">
                <i class="ki-duotone ki-setting-3 fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                </i>
                Personalizar Dashboard
            </button>
            <button type="button" class="btn btn-sm btn-light-info d-none" id="btn_configuracoes_dashboard">
                <i class="ki-duotone ki-gear fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Configurações
            </button>
            <button type="button" class="btn btn-sm btn-success d-none" id="btn_salvar_dashboard">
                <i class="ki-duotone ki-check fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Salvar Layout
            </button>
            <button type="button" class="btn btn-sm btn-info d-none" id="btn_adicionar_cards">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Adicionar Cards
            </button>
            <button type="button" class="btn btn-sm btn-warning d-none" id="btn_limpar_layout">
                <i class="ki-duotone ki-trash fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Limpar Layout
            </button>
            <button type="button" class="btn btn-sm btn-light-primary d-none" id="btn_restaurar_layout">
                <i class="ki-duotone ki-arrow-circle-left fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Restaurar Layout
            </button>
            <button type="button" class="btn btn-sm btn-light d-none" id="btn_cancelar_dashboard">
                Cancelar
            </button>
        </div>
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Dashboard</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Dashboard</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-danger">Erro</h4>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <div id="dashboard_grid_area" style="opacity: 0; transition: opacity 0.3s;">
        <?php if (is_colaborador() && !empty($colaborador_id)): ?>
        <!-- Dashboard do Colaborador -->
        
        <!--begin::Row - Cards de Estatísticas -->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-3">
                <a href="colaborador_view.php?id=<?= $colaborador_id ?>" class="card bg-primary hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <i class="ki-duotone ki-profile-circle text-white fs-2tx ms-n1 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= htmlspecialchars($colaborador['nome_completo'] ?? 'Colaborador') ?></div>
                        <div class="fw-semibold text-white opacity-75">Meu Perfil</div>
                    </div>
                </a>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <a href="ocorrencias_list.php" class="card bg-warning hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <i class="ki-duotone ki-notepad text-white fs-2tx ms-n1 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $ocorrencias_mes ?></div>
                        <div class="fw-semibold text-white opacity-75">Ocorrências no Mês</div>
                    </div>
                </a>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <a href="meus_pagamentos.php" class="card bg-success hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <i class="ki-duotone ki-wallet text-white fs-2tx ms-n1 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $total_pagamentos ?></div>
                        <div class="fw-semibold text-white opacity-75">Pagamentos Fechados</div>
                    </div>
                </a>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <div class="card bg-info hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <i class="ki-duotone ki-dollar text-white fs-2tx ms-n1 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-white fw-bold fs-2 mb-2 mt-5">R$ <?= number_format($valor_total_ano, 2, ',', '.') ?></div>
                        <div class="fw-semibold text-white opacity-75">Total Recebido (12 meses)</div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Row - Status de Documentos -->
        <?php if ($stats_documentos['total'] > 0): ?>
        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Status dos Documentos de Pagamento</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="card bg-light-danger">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="text-muted fw-semibold d-block">Pendentes</span>
                                                <span class="text-gray-800 fw-bold fs-2"><?= $stats_documentos['pendentes'] ?></span>
                                            </div>
                                            <i class="ki-duotone ki-time fs-1 text-danger">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light-warning">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="text-muted fw-semibold d-block">Enviados</span>
                                                <span class="text-gray-800 fw-bold fs-2"><?= $stats_documentos['enviados'] ?></span>
                                            </div>
                                            <i class="ki-duotone ki-file-up fs-1 text-warning">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light-success">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="text-muted fw-semibold d-block">Aprovados</span>
                                                <span class="text-gray-800 fw-bold fs-2"><?= $stats_documentos['aprovados'] ?></span>
                                            </div>
                                            <i class="ki-duotone ki-check-circle fs-1 text-success">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light-info">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="text-muted fw-semibold d-block">Total</span>
                                                <span class="text-gray-800 fw-bold fs-2"><?= $stats_documentos['total'] ?></span>
                                            </div>
                                            <i class="ki-duotone ki-file fs-1 text-info">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!--begin::Row - Análise de Emoções e Ranking de Pontos (Colaborador) -->
        <?php
        // Verifica permissão para ver cards de emoções
        $can_see_emocao_diaria = can_see_dashboard_card('card_emocao_diaria');
        $can_see_historico_emocoes = can_see_dashboard_card('card_historico_emocoes');
        
        // Carrega dados do ranking de pontos
        require_once __DIR__ . '/../includes/pontuacao.php';
        $usuario_id_rank = $usuario['id'] ?? null;
        $colaborador_id_rank = $usuario['colaborador_id'] ?? null;
        $meus_pontos = obter_pontos($usuario_id_rank, $colaborador_id_rank);
        $periodo_ranking = $_GET['periodo_ranking'] ?? 'mes';
        $ranking = obter_ranking_pontos($periodo_ranking, 5); // Limita a 5 para caber melhor
        
        if ($can_see_emocao_diaria || $can_see_historico_emocoes): ?>
        <?php
        // Verifica se já registrou emoção hoje
        $data_hoje = date('Y-m-d');
        $ja_registrou_emocao = false;
        $emocao_hoje = null;
        
        $usuario_id_colab = $usuario['id'] ?? null;
        $colaborador_id_colab = $usuario['colaborador_id'] ?? null;
        
        if ($usuario_id_colab) {
            $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE usuario_id = ? AND data_registro = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$usuario_id_colab, $data_hoje]);
        } else if ($colaborador_id_colab) {
            $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE colaborador_id = ? AND data_registro = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$colaborador_id_colab, $data_hoje]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE 1=0");
            $stmt->execute();
        }
        
        $emocao_hoje = $stmt->fetch();
        if ($emocao_hoje) {
            $ja_registrou_emocao = true;
        }
        ?>
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col - Análise de Emoções -->
            <div class="col-xl-8" data-card-id="card_emocao_diaria" data-card-title="Como você está se sentindo?" data-card-w="8" data-card-h="6">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Como você está se sentindo?</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Registre sua emoção diária</span>
                        </h3>
                        <?php if (!$ja_registrou_emocao): ?>
                        <div class="card-toolbar">
                            <span class="badge badge-light-success fs-6 fw-bold py-3 px-4">
                                <i class="ki-duotone ki-medal-star fs-3 text-success me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                +50 pontos
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body pt-6">
                        <?php if ($ja_registrou_emocao): ?>
                            <div class="alert alert-success d-flex align-items-center p-5 mb-10">
                                <i class="ki-duotone ki-check-circle fs-2hx text-success me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-dark">Emoção já registrada hoje!</h4>
                                    <span>Você já registrou sua emoção hoje. Volte amanhã para registrar novamente.</span>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column align-items-center p-5">
                                <div class="mb-5">
                                    <?php
                                    $niveis = [
                                        1 => ['emoji' => '😢', 'nome' => 'Muito triste', 'cor' => 'danger'],
                                        2 => ['emoji' => '😔', 'nome' => 'Triste', 'cor' => 'warning'],
                                        3 => ['emoji' => '😐', 'nome' => 'Neutro', 'cor' => 'info'],
                                        4 => ['emoji' => '🙂', 'nome' => 'Feliz', 'cor' => 'success'],
                                        5 => ['emoji' => '😄', 'nome' => 'Muito feliz', 'cor' => 'success']
                                    ];
                                    $nivel = $emocao_hoje['nivel_emocao'];
                                    $emoji_info = $niveis[$nivel];
                                    ?>
                                    <div class="text-center">
                                        <div class="fs-1 mb-3"><?= $emoji_info['emoji'] ?></div>
                                        <div class="fs-3 fw-bold text-gray-800 mb-2"><?= $emoji_info['nome'] ?></div>
                                        <?php if (!empty($emocao_hoje['descricao'])): ?>
                                            <div class="text-gray-600"><?= htmlspecialchars($emocao_hoje['descricao']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <form id="form_emocao_dashboard_colab" onsubmit="return false;">
                                <div class="d-flex flex-column align-items-center mb-10">
                                    <h3 class="text-center mb-5">Selecione como você está se sentindo:</h3>
                                    
                                    <div class="d-flex gap-5 mb-10 flex-wrap justify-content-center">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="1" class="d-none" required>
                                            <div class="emocao-option" data-nivel="1">
                                                <div class="fs-1">😢</div>
                                                <div class="text-muted small">Muito triste</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="2" class="d-none" required>
                                            <div class="emocao-option" data-nivel="2">
                                                <div class="fs-1">😔</div>
                                                <div class="text-muted small">Triste</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="3" class="d-none" required>
                                            <div class="emocao-option" data-nivel="3">
                                                <div class="fs-1">😐</div>
                                                <div class="text-muted small">Neutro</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="4" class="d-none" required>
                                            <div class="emocao-option" data-nivel="4">
                                                <div class="fs-1">🙂</div>
                                                <div class="text-muted small">Feliz</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="5" class="d-none" required>
                                            <div class="emocao-option" data-nivel="5">
                                                <div class="fs-1">😄</div>
                                                <div class="text-muted small">Muito feliz</div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="w-100 mb-5" style="max-width: 600px;">
                                        <label class="form-label">Nos conte o que te faz sentir assim</label>
                                        <textarea name="descricao" class="form-control form-control-solid" rows="4" placeholder="Fique à vontade para falar o que sente. Essa informação é privada e será lida somente por alguém que quer te ver feliz!"></textarea>
                                    </div>
                                    
                                    <button type="button" class="btn btn-primary btn-lg" onclick="enviarHumorColab(); return false;">
                                        <span class="indicator-label">Enviar humor</span>
                                        <span class="indicator-progress">Enviando...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                            </form>
                            
                            <div class="notice d-flex bg-light-success rounded border-success border border-dashed p-4 mt-5">
                                <i class="ki-duotone ki-medal-star fs-2x text-success me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <div class="d-flex flex-stack flex-grow-1">
                                    <div class="fw-semibold">
                                        <div class="fs-6 text-gray-700">Ganhe <strong class="text-success">+50 pontos</strong> ao registrar como você está se sentindo hoje!</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Ranking de Pontos -->
            <div class="col-xl-4" data-card-id="card_ranking_pontos" data-card-title="Ranking de Pontos" data-card-w="4" data-card-h="5">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Ranking de Pontos</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Seus pontos: <strong><?= $meus_pontos['pontos_totais'] ?></strong></span>
                        </h3>
                        <div class="card-toolbar">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="?periodo_ranking=dia" class="btn btn-sm btn-light <?= $periodo_ranking === 'dia' ? 'active' : '' ?>">Dia</a>
                                <a href="?periodo_ranking=semana" class="btn btn-sm btn-light <?= $periodo_ranking === 'semana' ? 'active' : '' ?>">Sem</a>
                                <a href="?periodo_ranking=mes" class="btn btn-sm btn-light <?= $periodo_ranking === 'mes' ? 'active' : '' ?>">Mês</a>
                                <a href="?periodo_ranking=total" class="btn btn-sm btn-light <?= $periodo_ranking === 'total' ? 'active' : '' ?>">Total</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-6">
                        <?php if (empty($ranking)): ?>
                            <div class="text-center text-muted py-5">
                                <p class="fs-7">Nenhum ranking disponível ainda.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-2 fs-7">
                                    <thead>
                                        <tr class="text-start text-gray-500 fw-bold fs-8 text-uppercase">
                                            <th class="min-w-30px">#</th>
                                            <th class="min-w-100px">Nome</th>
                                            <th class="min-w-60px text-end">Pontos</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600">
                                        <?php 
                                        $posicao = 1;
                                        foreach ($ranking as $item): 
                                            $pontos_exibir = 0;
                                            if ($periodo_ranking === 'dia') $pontos_exibir = $item['pontos_dia'];
                                            elseif ($periodo_ranking === 'semana') $pontos_exibir = $item['pontos_semana'];
                                            elseif ($periodo_ranking === 'mes') $pontos_exibir = $item['pontos_mes'];
                                            else $pontos_exibir = $item['pontos_totais'];
                                            
                                            $is_me = false;
                                            if ($usuario_id_rank && $item['usuario_id'] == $usuario_id_rank) $is_me = true;
                                            if ($colaborador_id_rank && $item['colaborador_id'] == $colaborador_id_rank) $is_me = true;
                                        ?>
                                        <tr class="<?= $is_me ? 'table-active' : '' ?>">
                                            <td>
                                                <?php if ($posicao <= 3): ?>
                                                    <span class="badge badge-light-<?= $posicao === 1 ? 'warning' : ($posicao === 2 ? 'info' : 'success') ?> fs-8">
                                                        <?= $posicao === 1 ? '🥇' : ($posicao === 2 ? '🥈' : '🥉') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-600 fs-8"><?= $posicao ?>º</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-circle symbol-25px me-2">
                                                        <?php if (!empty($item['foto'])): ?>
                                                            <img src="../<?= htmlspecialchars($item['foto']) ?>" alt="<?= htmlspecialchars($item['nome']) ?>">
                                                        <?php else: ?>
                                                            <div class="symbol-label fs-8 fw-semibold bg-primary text-white">
                                                                <?= strtoupper(substr($item['nome'], 0, 1)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="text-gray-800 fw-bold fs-7"><?= htmlspecialchars(mb_substr($item['nome'], 0, 15)) ?><?= mb_strlen($item['nome']) > 15 ? '...' : '' ?></span>
                                                    <?php if ($is_me): ?>
                                                        <span class="badge badge-light-primary ms-1 fs-9">Você</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-800 fw-bold fs-7"><?= number_format($pontos_exibir, 0, ',', '.') ?></span>
                                            </td>
                                        </tr>
                                        <?php 
                                        $posicao++;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        <?php endif; ?>
        
        <!--begin::Row - Gráfico e Informações -->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-8">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <?php if (!empty($colaborador_dashboard_aviso_occ)): ?>
                            <span class="card-label fw-bold fs-3 mb-1">Avisos</span>
                            <span class="text-muted fw-semibold fs-7">Informações administrativas</span>
                            <?php else: ?>
                            <span class="card-label fw-bold fs-3 mb-1">Minhas Ocorrências</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 6 meses</span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php if (!empty($colaborador_dashboard_aviso_occ)): ?>
                        <div class="alert alert-primary mb-0">
                            <p class="mb-3">Detalhes de registros administrativos não são exibidos no sistema. Para saber do que se trata, <strong>procure seu gestor direto</strong>.</p>
                            <a href="ocorrencias_list.php" class="btn btn-sm btn-primary">Ver avisos</a>
                        </div>
                        <?php else: ?>
                        <canvas id="kt_chart_ocorrencias_mes" style="height: 350px;"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-4">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Meus Dados</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php if ($colaborador): ?>
                        <div class="d-flex flex-column gap-5">
                            <div>
                                <span class="text-muted fw-semibold fs-7 d-block mb-1">Empresa</span>
                                <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($colaborador['empresa_nome'] ?? '-') ?></span>
                            </div>
                            <div>
                                <span class="text-muted fw-semibold fs-7 d-block mb-1">Setor</span>
                                <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($colaborador['nome_setor'] ?? '-') ?></span>
                            </div>
                            <div>
                                <span class="text-muted fw-semibold fs-7 d-block mb-1">Cargo</span>
                                <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($colaborador['nome_cargo'] ?? '-') ?></span>
                            </div>
                            <div>
                                <?php if (!empty($colaborador_dashboard_aviso_occ)): ?>
                                <span class="text-muted fw-semibold fs-7 d-block mb-1">Avisos administrativos</span>
                                <span class="text-gray-800 fw-bold fs-6">
                                    <?php if ($total_ocorrencias > 0): ?>
                                    Há avisos — fale com seu gestor
                                    <?php else: ?>
                                    Nenhum aviso no momento
                                    <?php endif; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted fw-semibold fs-7 d-block mb-1">Total de Ocorrências</span>
                                <span class="text-gray-800 fw-bold fs-6"><?= $total_ocorrencias ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Row - Ocorrências Recentes -->
        <?php if (!empty($ocorrencias_recentes)): ?>
        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-12">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Ocorrências Recentes</span>
                            <span class="text-muted fw-semibold fs-7">Últimas 5 ocorrências</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th>Registrado por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ocorrencias_recentes as $ocorrencia): ?>
                                    <tr>
                                        <td><?= formatar_data($ocorrencia['data_ocorrencia']) ?></td>
                                        <td>
                                            <span class="badge badge-light-primary"><?= htmlspecialchars($ocorrencia['tipo_nome'] ?? $ocorrencia['tipo'] ?? '-') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars(mb_substr($ocorrencia['descricao'] ?? '', 0, 50)) ?><?= mb_strlen($ocorrencia['descricao'] ?? '') > 50 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars($ocorrencia['usuario_nome'] ?? '-') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-5">
                            <a href="ocorrencias_list.php" class="btn btn-primary">Ver Todas as Ocorrências</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!--begin::Row - Pagamentos Recentes -->
        <?php if (!empty($pagamentos_recentes)): ?>
        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-12">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Pagamentos Recentes</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 5 fechamentos</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Mês/Ano</th>
                                        <th>Valor</th>
                                        <th>Status Documento</th>
                                        <th>Data Fechamento</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagamentos_recentes as $pagamento): ?>
                                    <tr>
                                        <td><?= date('m/Y', strtotime($pagamento['mes_referencia'] . '-01')) ?></td>
                                        <td><span class="text-success fw-bold">R$ <?= number_format($pagamento['valor_total'], 2, ',', '.') ?></span></td>
                                        <td>
                                            <?php
                                            $status_doc = $pagamento['documento_status'] ?? 'pendente';
                                            $badges = [
                                                'pendente' => '<span class="badge badge-light-danger">Pendente</span>',
                                                'enviado' => '<span class="badge badge-light-warning">Enviado</span>',
                                                'aprovado' => '<span class="badge badge-light-success">Aprovado</span>',
                                                'rejeitado' => '<span class="badge badge-light-danger">Rejeitado</span>'
                                            ];
                                            echo $badges[$status_doc] ?? '<span class="badge badge-light-secondary">-</span>';
                                            ?>
                                        </td>
                                        <td><?= formatar_data($pagamento['data_fechamento']) ?></td>
                                        <td class="text-end">
                                            <a href="meus_pagamentos.php" class="btn btn-sm btn-primary">Ver Detalhes</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-5">
                            <a href="meus_pagamentos.php" class="btn btn-primary">Ver Todos os Pagamentos</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Dashboard Admin/RH/GESTOR -->
        
        <?php if (has_role(['ADMIN', 'RH', 'GESTOR'])): ?>
        <!--begin::Row-->
        <div class="row g-5 g-xl-8 mb-5" id="row_stats_cards">
            <!--begin::Col-->
            <div class="col-xl-3" data-card-id="card_total_colaboradores">
                    <!--begin::Statistics Widget 5-->
                    <a href="colaboradores.php" class="card bg-primary hoverable card-xl-stretch mb-xl-8">
                        <div class="card-body">
                            <i class="ki-duotone ki-profile-circle text-white fs-2tx ms-n1 mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $total_colaboradores ?></div>
                            <div class="fw-semibold text-white opacity-75">Total de Colaboradores</div>
                        </div>
                    </a>
                    <!--end::Statistics Widget 5-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3" data-card-id="card_colaboradores_ativos">
                    <!--begin::Statistics Widget 5-->
                    <a href="colaboradores.php?status=ativo" class="card bg-success hoverable card-xl-stretch mb-xl-8">
                        <div class="card-body">
                            <i class="ki-duotone ki-check-circle text-white fs-2tx ms-n1 mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $total_ativos ?></div>
                            <div class="fw-semibold text-white opacity-75">Colaboradores Ativos</div>
                        </div>
                    </a>
                    <!--end::Statistics Widget 5-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3" data-card-id="card_ocorrencias_mes">
                    <!--begin::Statistics Widget 5-->
                    <a href="ocorrencias_list.php" class="card bg-warning hoverable card-xl-stretch mb-xl-8">
                        <div class="card-body">
                            <i class="ki-duotone ki-notepad text-white fs-2tx ms-n1 mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $ocorrencias_mes ?></div>
                            <div class="fw-semibold text-white opacity-75">Ocorrências no Mês</div>
                        </div>
                    </a>
                    <!--end::Statistics Widget 5-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3" data-card-id="card_colaboradores_inativos">
                    <!--begin::Statistics Widget 5-->
                    <a href="colaboradores.php?status=inativo" class="card bg-danger hoverable card-xl-stretch mb-xl-8">
                        <div class="card-body">
                            <i class="ki-duotone ki-cross-circle text-white fs-2tx ms-n1 mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5"><?= $total_colaboradores - $total_ativos ?></div>
                            <div class="fw-semibold text-white opacity-75">Inativos</div>
                        </div>
                    </a>
                    <!--end::Statistics Widget 5-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        <?php endif; ?>
        
        <?php if (has_role(['ADMIN', 'RH'])): ?>
        <!--begin::Row - Status Cron Fechamentos Recorrentes -->
        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-12" data-card-id="card_status_cron_fechamentos" data-card-title="Status do Cron - Fechamentos Recorrentes" data-card-w="12" data-card-h="3">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Status do Cron - Fechamentos Recorrentes</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($ultima_execucao_cron): ?>
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="ki-duotone ki-time fs-2x text-primary me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div>
                                        <div class="fw-bold fs-4">
                                            Última Execução: 
                                            <?php
                                            $data_execucao = new DateTime($ultima_execucao_cron['data_execucao']);
                                            echo $data_execucao->format('d/m/Y H:i:s');
                                            ?>
                                        </div>
                                        <div class="text-muted fs-6 mt-1">
                                            <?php
                                            $horas_atras = (int)$ultima_execucao_cron['horas_atras'];
                                            if ($horas_atras < 1) {
                                                echo 'Executado há menos de 1 hora';
                                            } elseif ($horas_atras < 24) {
                                                echo "Executado há {$horas_atras} hora(s)";
                                            } else {
                                                $dias_atras = floor($horas_atras / 24);
                                                echo "Executado há {$dias_atras} dia(s)";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <span class="badge badge-<?= $ultima_execucao_cron['status'] === 'sucesso' ? 'success' : 'danger' ?> me-2">
                                                <?= strtoupper($ultima_execucao_cron['status']) ?>
                                            </span>
                                            <span class="text-muted">Status</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold text-success me-2"><?= $ultima_execucao_cron['processados'] ?></span>
                                            <span class="text-muted">Processados</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold text-danger me-2"><?= $ultima_execucao_cron['erros'] ?></span>
                                            <span class="text-muted">Erros</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <?php if ($horas_atras > 25): ?>
                                        <div class="alert alert-warning d-flex align-items-center p-2 mb-0">
                                            <i class="ki-duotone ki-warning-2 fs-5 me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            <small class="fw-bold">Cron pode estar parado</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="ki-duotone ki-time fs-3x text-muted mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <p class="text-muted fs-5">Nenhuma execução registrada ainda</p>
                            <p class="text-muted fs-7">O cron ainda não foi executado ou a tabela de execuções não foi criada.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row-->
        <?php endif; ?>
        
        <!--begin::Row - Análise de Emoções -->
        <?php
        // Verifica permissão para ver cards de emoções
        $can_see_emocao_diaria = can_see_dashboard_card('card_emocao_diaria');
        $can_see_historico_emocoes = can_see_dashboard_card('card_historico_emocoes');
        
        if ($can_see_emocao_diaria || $can_see_historico_emocoes): ?>
        <?php
        // Verifica se já registrou emoção hoje
        $data_hoje = date('Y-m-d');
        $ja_registrou_emocao = false;
        $emocao_hoje = null;
        
        $usuario_id = $usuario['id'] ?? null;
        $colaborador_id = $usuario['colaborador_id'] ?? null;
        
        if ($usuario_id) {
            $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE usuario_id = ? AND data_registro = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$usuario_id, $data_hoje]);
        } else if ($colaborador_id) {
            $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE colaborador_id = ? AND data_registro = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$colaborador_id, $data_hoje]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE 1=0");
            $stmt->execute();
        }
        
        $emocao_hoje = $stmt->fetch();
        if ($emocao_hoje) {
            $ja_registrou_emocao = true;
        }
        
        // Busca histórico de emoções (últimos 30 dias)
        if ($usuario_id) {
            $stmt = $pdo->prepare("
                SELECT * FROM emocoes 
                WHERE usuario_id = ? 
                AND data_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY data_registro DESC
            ");
            $stmt->execute([$usuario_id]);
        } else if ($colaborador_id) {
            $stmt = $pdo->prepare("
                SELECT * FROM emocoes 
                WHERE colaborador_id = ? 
                AND data_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY data_registro DESC
            ");
            $stmt->execute([$colaborador_id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM emocoes WHERE 1=0");
            $stmt->execute();
        }
        $historico_emocoes = $stmt->fetchAll();
        ?>
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col - Análise de Emoções -->
            <div class="col-xl-8" data-card-id="card_emocao_diaria" data-card-title="Como você está se sentindo?" data-card-w="8" data-card-h="6">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Como você está se sentindo?</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Registre sua emoção diária</span>
                        </h3>
                        <?php if (!$ja_registrou_emocao): ?>
                        <div class="card-toolbar">
                            <span class="badge badge-light-success fs-6 fw-bold py-3 px-4">
                                <i class="ki-duotone ki-medal-star fs-3 text-success me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                +50 pontos
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body pt-6">
                        <?php if ($ja_registrou_emocao): ?>
                            <div class="alert alert-success d-flex align-items-center p-5 mb-10">
                                <i class="ki-duotone ki-check-circle fs-2hx text-success me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-dark">Emoção já registrada hoje!</h4>
                                    <span>Você já registrou sua emoção hoje. Volte amanhã para registrar novamente.</span>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column align-items-center p-5">
                                <div class="mb-5">
                                    <?php
                                    $niveis = [
                                        1 => ['emoji' => '😢', 'nome' => 'Muito triste', 'cor' => 'danger'],
                                        2 => ['emoji' => '😔', 'nome' => 'Triste', 'cor' => 'warning'],
                                        3 => ['emoji' => '😐', 'nome' => 'Neutro', 'cor' => 'info'],
                                        4 => ['emoji' => '🙂', 'nome' => 'Feliz', 'cor' => 'success'],
                                        5 => ['emoji' => '😄', 'nome' => 'Muito feliz', 'cor' => 'success']
                                    ];
                                    $nivel = $emocao_hoje['nivel_emocao'];
                                    $emoji_info = $niveis[$nivel];
                                    ?>
                                    <div class="text-center">
                                        <div class="fs-1 mb-3"><?= $emoji_info['emoji'] ?></div>
                                        <div class="fs-3 fw-bold text-gray-800 mb-2"><?= $emoji_info['nome'] ?></div>
                                        <?php if (!empty($emocao_hoje['descricao'])): ?>
                                            <div class="text-gray-600"><?= htmlspecialchars($emocao_hoje['descricao']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <form id="form_emocao_dashboard" onsubmit="return false;">
                                <div class="d-flex flex-column align-items-center mb-10">
                                    <h3 class="text-center mb-5">Selecione como você está se sentindo:</h3>
                                    
                                    <div class="d-flex gap-5 mb-10 flex-wrap justify-content-center">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="1" class="d-none" required>
                                            <div class="emocao-option" data-nivel="1">
                                                <div class="fs-1">😢</div>
                                                <div class="text-muted small">Muito triste</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="2" class="d-none" required>
                                            <div class="emocao-option" data-nivel="2">
                                                <div class="fs-1">😔</div>
                                                <div class="text-muted small">Triste</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="3" class="d-none" required>
                                            <div class="emocao-option" data-nivel="3">
                                                <div class="fs-1">😐</div>
                                                <div class="text-muted small">Neutro</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="4" class="d-none" required>
                                            <div class="emocao-option" data-nivel="4">
                                                <div class="fs-1">🙂</div>
                                                <div class="text-muted small">Feliz</div>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="nivel_emocao" value="5" class="d-none" required>
                                            <div class="emocao-option" data-nivel="5">
                                                <div class="fs-1">😄</div>
                                                <div class="text-muted small">Muito feliz</div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="w-100 mb-5" style="max-width: 600px;">
                                        <label class="form-label">Nos conte o que te faz sentir assim</label>
                                        <textarea name="descricao" class="form-control form-control-solid" rows="4" placeholder="Fique à vontade para falar o que sente. Essa informação é privada e será lida somente por alguém que quer te ver feliz!"></textarea>
                                    </div>
                                    
                                    <button type="button" class="btn btn-primary btn-lg" onclick="enviarHumorAdmin(); return false;">
                                        <span class="indicator-label">Enviar humor</span>
                                        <span class="indicator-progress">Enviando...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                            </form>
                            
                            <div class="notice d-flex bg-light-success rounded border-success border border-dashed p-4 mt-5">
                                <i class="ki-duotone ki-medal-star fs-2x text-success me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <div class="d-flex flex-stack flex-grow-1">
                                    <div class="fw-semibold">
                                        <div class="fs-6 text-gray-700">Ganhe <strong class="text-success">+50 pontos</strong> ao registrar como você está se sentindo hoje!</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Histórico de Emoções -->
            <div class="col-xl-4" data-card-id="card_historico_emocoes" data-card-title="Histórico de Emoções" data-card-w="4" data-card-h="5">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Histórico</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Últimos 30 dias</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php if (empty($historico_emocoes)): ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhuma emoção registrada ainda.</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php
                                $niveis_emoji = [1 => '😢', 2 => '😔', 3 => '😐', 4 => '🙂', 5 => '😄'];
                                foreach ($historico_emocoes as $emocao):
                                    $data_formatada = date('d/m/Y', strtotime($emocao['data_registro']));
                                ?>
                                    <div class="timeline-item mb-5">
                                        <div class="timeline-line w-40px"></div>
                                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                                            <div class="symbol-label bg-light">
                                                <span class="fs-2"><?= $niveis_emoji[$emocao['nivel_emocao']] ?></span>
                                            </div>
                                        </div>
                                        <div class="timeline-content mb-0 mt-n1">
                                            <div class="pe-3 mb-5">
                                                <div class="fs-5 fw-semibold mb-2"><?= $data_formatada ?></div>
                                                <?php if (!empty($emocao['descricao'])): ?>
                                                    <div class="text-gray-600 fs-7"><?= htmlspecialchars($emocao['descricao']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        <?php endif; ?>
        
        <?php
        // Para usuários (não colaboradores): média das emoções e últimas 10 emoções registradas
        // Mostra apenas para ADMIN, RH e GESTOR que não são colaboradores
        $can_see_media_emocoes = can_see_dashboard_card('card_media_emocoes');
        $can_see_ultimas_emocoes = can_see_dashboard_card('card_ultimas_emocoes');
        
        // Calcula média das emoções registradas pelo usuário (se tiver permissão)
        if (has_role(['ADMIN', 'RH', 'GESTOR']) && !is_colaborador() && !empty($usuario_id) && ($can_see_media_emocoes || $can_see_ultimas_emocoes)) {
            $stmt = $pdo->prepare("
                SELECT AVG(nivel_emocao) as media_emocao, COUNT(*) as total_registros
                FROM emocoes 
                WHERE usuario_id = ?
            ");
            $stmt->execute([$usuario_id]);
            $media_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $media_emocao = $media_data['media_emocao'] ?? null;
            $total_registros = $media_data['total_registros'] ?? 0;
            
            // Busca últimas 10 emoções registradas (todas, não apenas do usuário atual)
            // Para ADMIN/RH/GESTOR mostra todas as emoções do sistema
            $stmt = $pdo->prepare("
                SELECT e.*,
                       u.nome as usuario_nome,
                       c.nome_completo as colaborador_nome,
                       c.foto as colaborador_foto,
                       u.colaborador_id as usuario_colaborador_id,
                       s.nome_setor,
                       car.nome_cargo
                FROM emocoes e
                LEFT JOIN usuarios u ON e.usuario_id = u.id
                LEFT JOIN colaboradores c ON (e.colaborador_id = c.id OR u.colaborador_id = c.id)
                LEFT JOIN setores s ON c.setor_id = s.id
                LEFT JOIN cargos car ON c.cargo_id = car.id
                ORDER BY e.data_registro DESC, e.created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $ultimas_emocoes = $stmt->fetchAll();
            
            // Define emoji e cor baseado na média
            $niveis_emoji = [1 => '😢', 2 => '😔', 3 => '😐', 4 => '🙂', 5 => '😄'];
            $emoji_media = null;
            $cor_media = 'info';
            if ($media_emocao !== null) {
                $nivel_arredondado = round($media_emocao);
                $emoji_media = $niveis_emoji[$nivel_arredondado] ?? '😐';
                if ($media_emocao >= 4) {
                    $cor_media = 'success';
                } elseif ($media_emocao >= 3) {
                    $cor_media = 'info';
                } elseif ($media_emocao >= 2) {
                    $cor_media = 'warning';
                } else {
                    $cor_media = 'danger';
                }
            }
        }
        ?>
        <!--begin::Row - Estatísticas de Emoções para Usuários -->
        <?php if (has_role(['ADMIN', 'RH', 'GESTOR']) && !is_colaborador() && !empty($usuario_id) && ($can_see_media_emocoes || $can_see_ultimas_emocoes)): ?>
        <div class="row g-5 g-xl-8 mb-5">
            <?php if ($can_see_media_emocoes): ?>
            <!--begin::Col - Média de Emoções -->
            <div class="col-xl-<?= $can_see_ultimas_emocoes ? '4' : '12' ?>" data-card-id="card_media_emocoes" data-card-title="Média das Emoções" data-card-w="<?= $can_see_ultimas_emocoes ? '4' : '12' ?>" data-card-h="4">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Média das Emoções</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Todas as suas emoções registradas</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6">
                        <?php if ($total_registros > 0): ?>
                            <div class="d-flex flex-column align-items-center p-5">
                                <div class="text-center mb-5">
                                    <div class="fs-1 mb-3"><?= $emoji_media ?></div>
                                    <div class="fs-2 fw-bold text-gray-800 mb-2">
                                        <?= number_format($media_emocao, 2) ?> / 5.0
                                    </div>
                                    <div class="badge badge-<?= $cor_media ?> fs-6 mb-8">
                                        <?= $total_registros ?> registro(s)
                                    </div>
                                    <div class="w-100 mt-4" style="max-width: 250px;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-gray-600 fs-7 fw-semibold">Nível Médio</span>
                                            <span class="text-gray-800 fs-7 fw-bold"><?= number_format(($media_emocao / 5) * 100, 0) ?>%</span>
                                        </div>
                                        <div class="progress h-15px w-100" style="border-radius: 10px; overflow: hidden;">
                                            <div class="progress-bar bg-<?= $cor_media ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= ($media_emocao / 5) * 100 ?>%"
                                                 aria-valuenow="<?= $media_emocao ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="5"
                                                 title="Média de <?= number_format($media_emocao, 2) ?> em uma escala de 0 a 5">
                                            </div>
                                        </div>
                                        <div class="text-muted fs-8 mt-2">
                                            Representação visual da sua média em relação ao máximo (5.0)
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhuma emoção registrada ainda.</p>
                                <small>Registre suas emoções diárias para ver sua média aqui.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            <?php endif; ?>
            
            <?php if ($can_see_ultimas_emocoes): ?>
            <!--begin::Col - Últimas 10 Emoções -->
            <div class="col-xl-<?= $can_see_media_emocoes ? '8' : '12' ?>" data-card-id="card_ultimas_emocoes" data-card-title="Últimas Emoções Registradas" data-card-w="<?= $can_see_media_emocoes ? '8' : '12' ?>" data-card-h="5">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <div class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Últimas Emoções Registradas</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Últimas 10 emoções do sistema</span>
                        </div>
                        <div class="card-toolbar">
                            <a href="emocoes_analise.php" class="btn btn-sm btn-primary">
                                Ver Mais
                                <i class="ki-duotone ki-arrow-right fs-2 ms-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-6">
                        <?php if (empty($ultimas_emocoes)): ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhuma emoção registrada ainda.</p>
                                <small>Registre suas emoções diárias para ver o histórico aqui.</small>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-100px">Data</th>
                                            <th class="min-w-150px">Colaborador/Usuário</th>
                                            <th class="min-w-80px text-center">Emoção</th>
                                            <th class="min-w-100px text-center">Nível</th>
                                            <th>Descrição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimas_emocoes as $emocao): 
                                            $data_formatada = date('d/m/Y', strtotime($emocao['data_registro']));
                                            $nivel = $emocao['nivel_emocao'];
                                            $emoji = $niveis_emoji[$nivel] ?? '😐';
                                            $nomes_nivel = [1 => 'Muito triste', 2 => 'Triste', 3 => 'Neutro', 4 => 'Feliz', 5 => 'Muito feliz'];
                                            $nome_nivel = $nomes_nivel[$nivel] ?? 'Neutro';
                                            
                                            // Define cor do badge
                                            $cor_badge = 'info';
                                            if ($nivel >= 4) {
                                                $cor_badge = 'success';
                                            } elseif ($nivel >= 3) {
                                                $cor_badge = 'info';
                                            } elseif ($nivel >= 2) {
                                                $cor_badge = 'warning';
                                            } else {
                                                $cor_badge = 'danger';
                                            }
                                            
                                            // Nome do colaborador/usuário
                                            $nome_pessoa = $emocao['colaborador_nome'] ?? $emocao['usuario_nome'] ?? 'N/A';
                                            $foto_pessoa = $emocao['colaborador_foto'] ?? null;
                                            $setor_cargo = '';
                                            if (!empty($emocao['nome_setor'])) {
                                                $setor_cargo = $emocao['nome_setor'];
                                                if (!empty($emocao['nome_cargo'])) {
                                                    $setor_cargo .= ' / ' . $emocao['nome_cargo'];
                                                }
                                            }
                                            // Inicial para avatar padrão
                                            $inicial = strtoupper(substr($nome_pessoa, 0, 1));
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="text-gray-800 fw-bold"><?= $data_formatada ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <!--begin::Avatar-->
                                                    <div class="symbol symbol-circle symbol-40px me-3">
                                                        <?php if (!empty($foto_pessoa)): ?>
                                                            <img alt="<?= htmlspecialchars($nome_pessoa) ?>" src="../<?= htmlspecialchars($foto_pessoa) ?>" />
                                                        <?php else: ?>
                                                            <div class="symbol-label fs-2 fw-semibold bg-primary text-white">
                                                                <?= $inicial ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <!--end::Avatar-->
                                                    <!--begin::Name-->
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($nome_pessoa) ?></span>
                                                        <?php if (!empty($setor_cargo)): ?>
                                                            <small class="text-muted"><?= htmlspecialchars($setor_cargo) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <!--end::Name-->
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="fs-2"><?= $emoji ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-<?= $cor_badge ?> fs-7">
                                                    <?= $nome_nivel ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($emocao['descricao'])): ?>
                                                    <span class="text-gray-600"><?= htmlspecialchars($emocao['descricao']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">Sem descrição</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            <?php endif; ?>
        </div>
        <!--end::Row-->
        <?php endif; ?>
        
        <!--begin::Row-->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-8" data-card-id="card_grafico_ocorrencias_mes">
                <!--begin::Charts Widget 1-->
                <div class="card card-xl-stretch mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Ocorrências por Mês</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 6 meses</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body pt-5">
                        <?php if (empty($meses_grafico) || empty($ocorrencias_grafico) || count($meses_grafico) === 0): ?>
                            <div class="text-center text-muted py-10">
                                <i class="ki-duotone ki-chart fs-3x text-muted mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <p class="fs-5 mb-0">Nenhuma ocorrência registrada nos últimos 6 meses</p>
                                <p class="fs-7">Os dados aparecerão aqui quando houver ocorrências</p>
                            </div>
                        <?php else: ?>
                            <canvas id="kt_chart_ocorrencias_mes" style="height: 350px;"></canvas>
                        <?php endif; ?>
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Charts Widget 1-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-4" data-card-id="card_grafico_colaboradores_status">
                <!--begin::Charts Widget 2-->
                <div class="card card-xl-stretch mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Colaboradores por Status</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body pt-5">
                        <?php if (empty($colaboradores_status) || count($colaboradores_status) === 0): ?>
                            <div class="text-center text-muted py-10">
                                <i class="ki-duotone ki-chart-pie fs-3x text-muted mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <p class="fs-5 mb-0">Nenhum colaborador encontrado</p>
                                <p class="fs-7">Os dados aparecerão aqui quando houver colaboradores cadastrados</p>
                            </div>
                        <?php else: ?>
                            <canvas id="kt_chart_colaboradores_status" style="height: 350px;"></canvas>
                        <?php endif; ?>
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Charts Widget 2-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Row-->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-12" data-card-id="card_grafico_ocorrencias_tipo">
                <!--begin::Charts Widget 3-->
                <div class="card card-xl-stretch mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Ocorrências por Tipo</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 30 dias</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body pt-5">
                        <?php if (empty($ocorrencias_por_tipo)): ?>
                            <div class="text-center text-muted py-10">
                                <i class="ki-duotone ki-chart-bar fs-3x text-muted mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <p class="fs-5 mb-0">Nenhuma ocorrência registrada nos últimos 30 dias</p>
                                <p class="fs-7">Os dados aparecerão aqui quando houver ocorrências</p>
                            </div>
                        <?php else: ?>
                            <canvas id="kt_chart_ocorrencias_tipo" style="height: 300px;"></canvas>
                        <?php endif; ?>
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Charts Widget 3-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Row - Ranking e Aniversários lado a lado -->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col - Ranking de Ocorrências -->
            <?php if (!empty($ranking)): ?>
            <div class="col-xl-6" data-card-id="card_ranking_ocorrencias">
                <!--begin::Tables Widget 9-->
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Ranking de Ocorrências</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 30 dias</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body py-3">
                        <!--begin::Table container-->
                        <div class="table-responsive">
                            <!--begin::Table-->
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                <!--begin::Table head-->
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-50px">Posição</th>
                                        <th class="min-w-200px">Colaborador</th>
                                        <th class="min-w-150px text-end">Total de Ocorrências</th>
                                    </tr>
                                </thead>
                                <!--end::Table head-->
                                <!--begin::Table body-->
                                <tbody>
                                    <?php foreach ($ranking as $index => $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index === 0): ?>
                                                <span class="badge badge-warning fs-7">🥇 1º</span>
                                            <?php elseif ($index === 1): ?>
                                                <span class="badge badge-secondary fs-7">🥈 2º</span>
                                            <?php elseif ($index === 2): ?>
                                                <span class="badge badge-info fs-7">🥉 3º</span>
                                            <?php else: ?>
                                                <span class="text-muted fw-semibold"><?= $index + 1 ?>º</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <!--begin::Avatar-->
                                                <div class="symbol symbol-circle symbol-40px me-3">
                                                    <?php if (!empty($item['foto'])): ?>
                                                        <img alt="<?= htmlspecialchars($item['nome_completo']) ?>" src="../<?= htmlspecialchars($item['foto']) ?>" />
                                                    <?php else: ?>
                                                        <div class="symbol-label fs-2 fw-semibold bg-primary text-white">
                                                            <?= strtoupper(substr($item['nome_completo'], 0, 1)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <!--end::Avatar-->
                                                <!--begin::Name-->
                                                <span class="text-gray-900 fw-bold fs-6"><?= htmlspecialchars($item['nome_completo']) ?></span>
                                                <!--end::Name-->
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <?php 
                                            $badge_class = $item['total_ocorrencias'] > 5 ? 'badge-danger' : ($item['total_ocorrencias'] > 2 ? 'badge-warning' : 'badge-primary');
                                            ?>
                                            <span class="badge <?= $badge_class ?> fs-7"><?= $item['total_ocorrencias'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <!--end::Table body-->
                            </table>
                            <!--end::Table-->
                        </div>
                        <!--end::Table container-->
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Tables Widget 9-->
            </div>
            <!--end::Col-->
            <?php endif; ?>
            
            <!--begin::Col - Próximos Aniversários -->
            <?php if (!empty($proximos_aniversarios)): ?>
            <div class="col-xl-6" data-card-id="card_proximos_aniversarios">
                <!--begin::Tables Widget 10-->
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <!--begin::Header-->
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Próximos Aniversários</span>
                            <span class="text-muted fw-semibold fs-7">Próximos 30 dias</span>
                        </h3>
                        <div class="card-toolbar">
                            <a href="aniversariantes.php" class="btn btn-sm btn-primary">
                                Ver Todos
                                <i class="ki-duotone ki-arrow-right fs-2 ms-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </a>
                        </div>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body py-3">
                        <!--begin::Table container-->
                        <div class="table-responsive">
                            <!--begin::Table-->
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                <!--begin::Table head-->
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-200px">Colaborador</th>
                                        <th class="min-w-100px">Data</th>
                                        <th class="min-w-100px text-end">Dias</th>
                                    </tr>
                                </thead>
                                <!--end::Table head-->
                                <!--begin::Table body-->
                                <tbody>
                                    <?php foreach ($proximos_aniversarios as $aniv): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <!--begin::Avatar-->
                                                <div class="symbol symbol-circle symbol-40px me-3">
                                                    <?php if (!empty($aniv['foto'])): ?>
                                                        <img alt="<?= htmlspecialchars($aniv['nome_completo']) ?>" src="../<?= htmlspecialchars($aniv['foto']) ?>" />
                                                    <?php else: ?>
                                                        <div class="symbol-label fs-2 fw-semibold bg-success text-white">
                                                            <?= strtoupper(substr($aniv['nome_completo'], 0, 1)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <!--end::Avatar-->
                                                <!--begin::Name-->
                                                <span class="text-gray-900 fw-bold fs-6"><?= htmlspecialchars($aniv['nome_completo']) ?></span>
                                                <!--end::Name-->
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-gray-800 fw-semibold"><?= $aniv['data_formatada'] ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($aniv['dias_ate'] == 0): ?>
                                                <span class="badge badge-success fs-7">Hoje! 🎉</span>
                                            <?php elseif ($aniv['dias_ate'] == 1): ?>
                                                <span class="badge badge-warning fs-7">Amanhã</span>
                                            <?php else: ?>
                                                <span class="badge badge-light-primary fs-7"><?= $aniv['dias_ate'] ?> dias</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <!--end::Table body-->
                            </table>
                            <!--end::Table-->
                        </div>
                        <!--end::Table container-->
                    </div>
                    <!--end::Body-->
                </div>
                <!--end::Tables Widget 10-->
            </div>
            <!--end::Col-->
            <?php endif; ?>
        </div>
        <!--end::Row-->
        
        <!--begin::Row - Anotações e Histórico -->
        <?php if (has_role(['ADMIN', 'RH', 'GESTOR'])): ?>
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col - Anotações -->
            <div class="col-xl-6" data-card-id="card_anotacoes">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Anotações</span>
                            <span class="text-muted fw-semibold fs-7">Anotações gerais do sistema</span>
                        </h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal_nova_anotacao">
                                <i class="ki-duotone ki-plus fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Nova Anotação
                            </button>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <div class="mb-5">
                            <div class="d-flex gap-2 mb-3">
                                <select id="filtro_status_anotacoes" class="form-select form-select-sm" style="width: auto;">
                                    <option value="ativa">Ativas</option>
                                    <option value="todas">Todas</option>
                                    <option value="concluida">Concluídas</option>
                                    <option value="arquivada">Arquivadas</option>
                                </select>
                                <select id="filtro_prioridade_anotacoes" class="form-select form-select-sm" style="width: auto;">
                                    <option value="">Todas Prioridades</option>
                                    <option value="urgente">Urgente</option>
                                    <option value="alta">Alta</option>
                                    <option value="media">Média</option>
                                    <option value="baixa">Baixa</option>
                                </select>
                                <button type="button" id="btn_fixadas_anotacoes" class="btn btn-sm btn-light">
                                    <i class="ki-duotone ki-pin fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Fixadas
                                </button>
                            </div>
                        </div>
                        <div id="lista_anotacoes">
                            <div class="text-center text-muted py-5">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Carregando anotações...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Histórico de Cargos/Salários -->
            <div class="col-xl-6" data-card-id="card_historico_promocoes">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Histórico de Promoções</span>
                            <span class="text-muted fw-semibold fs-7">Últimas promoções e alterações salariais</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php
                        // Busca histórico de promoções
                        if ($usuario['role'] === 'ADMIN') {
                            $stmt = $pdo->prepare("
                                SELECT p.*, c.nome_completo as colaborador_nome, c.foto as colaborador_foto,
                                       u.nome as usuario_nome
                                FROM promocoes p
                                INNER JOIN colaboradores c ON p.colaborador_id = c.id
                                LEFT JOIN usuarios u ON p.usuario_id = u.id
                                ORDER BY p.data_promocao DESC, p.created_at DESC
                                LIMIT 10
                            ");
                            $stmt->execute();
                        } elseif ($usuario['role'] === 'RH') {
                            // RH pode ter múltiplas empresas
                            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                                $stmt = $pdo->prepare("
                                    SELECT p.*, c.nome_completo as colaborador_nome, c.foto as colaborador_foto,
                                           u.nome as usuario_nome
                                    FROM promocoes p
                                    INNER JOIN colaboradores c ON p.colaborador_id = c.id
                                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                                    WHERE c.empresa_id IN ($placeholders)
                                    ORDER BY p.data_promocao DESC, p.created_at DESC
                                    LIMIT 10
                                ");
                                $stmt->execute($usuario['empresas_ids']);
                            } else {
                                $stmt = $pdo->prepare("
                                    SELECT p.*, c.nome_completo as colaborador_nome, c.foto as colaborador_foto,
                                           u.nome as usuario_nome
                                    FROM promocoes p
                                    INNER JOIN colaboradores c ON p.colaborador_id = c.id
                                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                                    WHERE c.empresa_id = ?
                                    ORDER BY p.data_promocao DESC, p.created_at DESC
                                    LIMIT 10
                                ");
                                $stmt->execute([$usuario['empresa_id'] ?? 0]);
                            }
                        } elseif ($usuario['role'] === 'GESTOR') {
                            $stmt = $pdo->prepare("
                                SELECT p.*, c.nome_completo as colaborador_nome, c.foto as colaborador_foto,
                                       u.nome as usuario_nome
                                FROM promocoes p
                                INNER JOIN colaboradores c ON p.colaborador_id = c.id
                                LEFT JOIN usuarios u ON p.usuario_id = u.id
                                WHERE c.setor_id = ?
                                ORDER BY p.data_promocao DESC, p.created_at DESC
                                LIMIT 10
                            ");
                            $stmt->execute([$setor_id]);
                        } else {
                            $historico_promocoes = [];
                        }
                        
                        $historico_promocoes = isset($stmt) ? $stmt->fetchAll() : [];
                        ?>
                        
                        <?php if (empty($historico_promocoes)): ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhuma promoção registrada ainda.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-150px">Colaborador</th>
                                            <th class="min-w-100px">Data</th>
                                            <th class="min-w-120px">Salário Anterior</th>
                                            <th class="min-w-120px">Novo Salário</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historico_promocoes as $promo): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-circle symbol-30px me-3">
                                                        <?php if (!empty($promo['colaborador_foto'])): ?>
                                                            <img alt="<?= htmlspecialchars($promo['colaborador_nome']) ?>" src="../<?= htmlspecialchars($promo['colaborador_foto']) ?>" />
                                                        <?php else: ?>
                                                            <div class="symbol-label fs-7 fw-semibold bg-primary text-white">
                                                                <?= strtoupper(substr($promo['colaborador_nome'], 0, 1)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="text-gray-800 fw-semibold fs-7"><?= htmlspecialchars($promo['colaborador_nome']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-gray-600 fs-7"><?= formatar_data($promo['data_promocao']) ?></span>
                                            </td>
                                            <td>
                                                <span class="text-gray-600 fs-7">R$ <?= number_format($promo['salario_anterior'], 2, ',', '.') ?></span>
                                            </td>
                                            <td>
                                                <span class="text-success fw-bold fs-7">R$ <?= number_format($promo['salario_novo'], 2, ',', '.') ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-5">
                                <a href="promocoes.php" class="btn btn-sm btn-primary">Ver Todas</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
<?php endif; ?>
<?php endif; ?>
        </div>
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal Adicionar Cards-->
<div class="modal fade" id="modal_adicionar_cards" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Cards ao Dashboard</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <div class="mb-5">
                    <input type="text" class="form-control form-control-solid" id="buscar_cards" placeholder="Buscar cards...">
                </div>
                <div class="row g-3" id="lista_cards_disponiveis">
                    <!-- Será preenchido via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Adicionar Cards-->

<!--begin::Modal Configurações do Dashboard-->
<div class="modal fade" id="modal_configuracoes_dashboard" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Configurações do Dashboard</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="form_configuracoes_dashboard">
                    <!--begin::Margem entre Cards-->
                    <div class="mb-8">
                        <label class="form-label fw-bold fs-6 mb-2">Margem entre Cards</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="range" class="form-range flex-grow-1" min="0" max="48" step="4" value="16" id="config_margin" name="margin">
                            <span class="badge badge-light-primary fs-5 fw-bold" style="min-width: 60px;" id="config_margin_value">16px</span>
                        </div>
                        <div class="form-text">Espaçamento entre os cards do dashboard</div>
                    </div>
                    <!--end::Margem entre Cards-->
                    
                    <!--begin::Altura das Células-->
                    <div class="mb-8">
                        <label class="form-label fw-bold fs-6 mb-2">Altura das Células</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="range" class="form-range flex-grow-1" min="50" max="120" step="10" value="70" id="config_cell_height" name="cell_height">
                            <span class="badge badge-light-info fs-5 fw-bold" style="min-width: 60px;" id="config_cell_height_value">70px</span>
                        </div>
                        <div class="form-text">Define a altura base das células do grid</div>
                    </div>
                    <!--end::Altura das Células-->
                    
                    <!--begin::Densidade do Layout-->
                    <div class="mb-8">
                        <label class="form-label fw-bold fs-6 mb-2">Densidade do Layout</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="densidade" id="densidade_compacto" value="compacto">
                            <label class="btn btn-outline btn-outline-primary" for="densidade_compacto">
                                <i class="ki-duotone ki-grid fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Compacto
                            </label>
                            
                            <input type="radio" class="btn-check" name="densidade" id="densidade_padrao" value="padrao" checked>
                            <label class="btn btn-outline btn-outline-primary" for="densidade_padrao">
                                <i class="ki-duotone ki-element-11 fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                Padrão
                            </label>
                            
                            <input type="radio" class="btn-check" name="densidade" id="densidade_espacado" value="espacado">
                            <label class="btn btn-outline btn-outline-primary" for="densidade_espacado">
                                <i class="ki-duotone ki-maximize fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Espaçado
                            </label>
                        </div>
                        <div class="form-text">Define o espaçamento geral do dashboard</div>
                    </div>
                    <!--end::Densidade do Layout-->
                    
                    <!--begin::Tema do Grid-->
                    <div class="mb-8">
                        <label class="form-label fw-bold fs-6 mb-2">Tema do Grid (Modo Edição)</label>
                        <div class="row g-3">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tema_grid" id="tema_azul" value="azul" checked>
                                <label class="btn btn-outline btn-outline-dashed btn-outline-primary w-100 p-4" for="tema_azul">
                                    <span class="d-block fw-bold mb-2">Azul</span>
                                    <span class="d-block" style="height: 4px; background: #0d6efd;"></span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tema_grid" id="tema_verde" value="verde">
                                <label class="btn btn-outline btn-outline-dashed btn-outline-success w-100 p-4" for="tema_verde">
                                    <span class="d-block fw-bold mb-2">Verde</span>
                                    <span class="d-block" style="height: 4px; background: #50cd89;"></span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tema_grid" id="tema_roxo" value="roxo">
                                <label class="btn btn-outline btn-outline-dashed btn-outline-info w-100 p-4" for="tema_roxo">
                                    <span class="d-block fw-bold mb-2">Roxo</span>
                                    <span class="d-block" style="height: 4px; background: #7239ea;"></span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tema_grid" id="tema_laranja" value="laranja">
                                <label class="btn btn-outline btn-outline-dashed btn-outline-warning w-100 p-4" for="tema_laranja">
                                    <span class="d-block fw-bold mb-2">Laranja</span>
                                    <span class="d-block" style="height: 4px; background: #ffc700;"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <!--end::Tema do Grid-->
                    
                    <!--begin::Animações-->
                    <div class="mb-8">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="config_animate" name="animate" checked>
                            <label class="form-check-label fw-bold" for="config_animate">
                                Habilitar Animações
                            </label>
                        </div>
                        <div class="form-text">Animações suaves ao reorganizar cards</div>
                    </div>
                    <!--end::Animações-->
                    
                    <!--begin::Restaurar Padrão-->
                    <div class="separator separator-dashed my-8"></div>
                    <div class="alert alert-dismissible bg-light-warning d-flex flex-column flex-sm-row p-5 mb-5">
                        <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4 mb-5 mb-sm-0">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column pe-0 pe-sm-10">
                            <h5 class="mb-1">Restaurar Configurações</h5>
                            <span>Clique abaixo para restaurar as configurações padrão</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-light-warning ms-auto" id="btn_restaurar_config">
                            <i class="ki-duotone ki-arrows-circle fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Restaurar Padrão
                        </button>
                    </div>
                    <!--end::Restaurar Padrão-->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_aplicar_config">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Aplicar Configurações
                    </span>
                    <span class="indicator-progress">Aplicando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Configurações do Dashboard-->

<!--begin::Modal Configurar Carrossel-->
<div class="modal fade" id="modal_configurar_carrossel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Configurar Carrossel de Cards</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <input type="hidden" id="carousel_card_id">
                
                <!--begin::Configurações do Carrossel-->
                <div class="mb-8">
                    <h3 class="fw-bold mb-5">Configurações</h3>
                    
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cards por Slide</label>
                            <select class="form-select form-select-solid" id="carousel_slides_per_view">
                                <option value="1">1 Card</option>
                                <option value="2">2 Cards</option>
                                <option value="3" selected>3 Cards</option>
                                <option value="4">4 Cards</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Velocidade (ms)</label>
                            <input type="number" class="form-control form-control-solid" id="carousel_speed" value="500" min="300" max="2000" step="100">
                        </div>
                    </div>
                    
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="carousel_autoplay" checked>
                                <label class="form-check-label fw-bold" for="carousel_autoplay">
                                    Auto-play
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6" id="carousel_autoplay_delay_container">
                            <label class="form-label fw-bold">Intervalo Auto-play (ms)</label>
                            <input type="number" class="form-control form-control-solid" id="carousel_autoplay_delay" value="3000" min="1000" max="10000" step="500">
                        </div>
                    </div>
                    
                    <div class="row g-5">
                        <div class="col-md-6">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="carousel_loop" checked>
                                <label class="form-check-label fw-bold" for="carousel_loop">
                                    Loop Contínuo
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="carousel_pagination" checked>
                                <label class="form-check-label fw-bold" for="carousel_pagination">
                                    Mostrar Navegação (dots)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Configurações do Carrossel-->
                
                <div class="separator separator-dashed my-8"></div>
                
                <!--begin::Cards do Carrossel-->
                <div class="mb-8">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h3 class="fw-bold mb-0">Cards no Carrossel</h3>
                        <button type="button" class="btn btn-sm btn-primary" id="btn_adicionar_card_carrossel">
                            <i class="ki-duotone ki-plus fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Adicionar Card
                        </button>
                    </div>
                    
                    <div id="carousel_cards_list" class="min-h-200px">
                        <div class="text-center text-muted py-10">
                            <i class="ki-duotone ki-slider fs-3x text-gray-400 mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <p>Nenhum card adicionado ainda</p>
                            <small>Clique em "Adicionar Card" para começar</small>
                        </div>
                    </div>
                </div>
                <!--end::Cards do Carrossel-->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_salvar_carrossel">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-check fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Salvar Carrossel
                    </span>
                    <span class="indicator-progress">Salvando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Configurar Carrossel-->

<!--begin::Modal Adicionar Card ao Carrossel-->
<div class="modal fade" id="modal_adicionar_card_carrossel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Card ao Carrossel</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <!--begin::Tipo de Card-->
                <div class="mb-8">
                    <label class="form-label fw-bold fs-5 mb-5">Escolha o Tipo de Card</label>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="radio" class="btn-check" name="tipo_card_carrossel" id="tipo_card_personalizado" value="personalizado" checked>
                            <label class="btn btn-outline btn-outline-dashed btn-outline-primary w-100 p-5 text-start" for="tipo_card_personalizado">
                                <i class="ki-duotone ki-colors-square fs-3x text-primary mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <div class="fw-bold fs-4 mb-1">Card Personalizado</div>
                                <div class="text-muted fs-7">Crie um mini-card customizado com título, valor e ícone</div>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <input type="radio" class="btn-check" name="tipo_card_carrossel" id="tipo_card_existente" value="existente">
                            <label class="btn btn-outline btn-outline-dashed btn-outline-success w-100 p-5 text-start" for="tipo_card_existente">
                                <i class="ki-duotone ki-element-11 fs-3x text-success mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <div class="fw-bold fs-4 mb-1">Card Existente</div>
                                <div class="text-muted fs-7">Adicione um card já criado do dashboard</div>
                            </label>
                        </div>
                    </div>
                </div>
                <!--end::Tipo de Card-->
                
                <div class="separator separator-dashed my-8"></div>
                
                <!--begin::Formulário Card Personalizado-->
                <form id="form_card_carrossel" style="display: block;">
                    <div class="mb-5">
                        <label class="form-label fw-bold required">Título do Card</label>
                        <input type="text" name="titulo" class="form-control form-control-solid" placeholder="Ex: Total de Vendas" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-bold">Valor/Número</label>
                        <input type="text" name="valor" class="form-control form-control-solid" placeholder="Ex: 1.234 ou R$ 15.000">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-bold">Ícone</label>
                        <select name="icone" class="form-select form-select-solid">
                            <option value="">Sem ícone</option>
                            <option value="ki-chart-simple">Gráfico</option>
                            <option value="ki-dollar">Dinheiro</option>
                            <option value="ki-profile-circle">Usuários</option>
                            <option value="ki-notepad">Notas</option>
                            <option value="ki-calendar">Calendário</option>
                            <option value="ki-check-circle">Check</option>
                            <option value="ki-cross-circle">Cruz</option>
                            <option value="ki-time">Relógio</option>
                            <option value="ki-chart-pie">Pizza</option>
                            <option value="ki-wallet">Carteira</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-bold">Cor do Card</label>
                        <div class="d-flex gap-3">
                            <label class="btn btn-outline btn-outline-dashed btn-outline-primary w-100">
                                <input type="radio" name="cor" value="primary" class="d-none" checked>
                                <div class="text-center py-3">
                                    <div class="mb-2" style="height: 20px; background: #009ef7; border-radius: 4px;"></div>
                                    <span>Azul</span>
                                </div>
                            </label>
                            <label class="btn btn-outline btn-outline-dashed btn-outline-success w-100">
                                <input type="radio" name="cor" value="success" class="d-none">
                                <div class="text-center py-3">
                                    <div class="mb-2" style="height: 20px; background: #50cd89; border-radius: 4px;"></div>
                                    <span>Verde</span>
                                </div>
                            </label>
                            <label class="btn btn-outline btn-outline-dashed btn-outline-warning w-100">
                                <input type="radio" name="cor" value="warning" class="d-none">
                                <div class="text-center py-3">
                                    <div class="mb-2" style="height: 20px; background: #ffc700; border-radius: 4px;"></div>
                                    <span>Amarelo</span>
                                </div>
                            </label>
                            <label class="btn btn-outline btn-outline-dashed btn-outline-danger w-100">
                                <input type="radio" name="cor" value="danger" class="d-none">
                                <div class="text-center py-3">
                                    <div class="mb-2" style="height: 20px; background: #f1416c; border-radius: 4px;"></div>
                                    <span>Vermelho</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-bold">Descrição/Subtítulo</label>
                        <input type="text" name="descricao" class="form-control form-control-solid" placeholder="Ex: Últimos 30 dias">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-bold">Link (opcional)</label>
                        <input type="text" name="link" class="form-control form-control-solid" placeholder="Ex: relatorios.php">
                    </div>
                </form>
                <!--end::Formulário Card Personalizado-->
                
                <!--begin::Seleção Card Existente-->
                <div id="selecao_card_existente" style="display: none;">
                    <div class="mb-5">
                        <label class="form-label fw-bold fs-5 mb-3">Selecione um Card Existente</label>
                        <input type="text" class="form-control form-control-solid mb-4" id="buscar_cards_existentes" placeholder="Buscar cards...">
                    </div>
                    <div class="row g-3" id="lista_cards_existentes" style="max-height: 400px; overflow-y: auto;">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                </div>
                <!--end::Seleção Card Existente-->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_confirmar_card_carrossel">
                    <span class="indicator-label">Adicionar Card</span>
                    <span class="indicator-progress">Adicionando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Adicionar Card ao Carrossel-->

<!--begin::Dashboard Personalization Scripts-->
<link href="https://cdn.jsdelivr.net/npm/gridstack@9.0.0/dist/gridstack.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/gridstack@9.0.0/dist/gridstack-all.js"></script>
<!-- Swiper CSS para carrossel -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<style id="dynamic_grid_styles"></style>
<style>
.grid-stack {
    min-height: 100vh;
    padding: 0 !important;
    margin: 0 !important;
}
.grid-stack-item {
    padding: 0 !important;
    margin: 0 !important;
}
.dashboard-edit-mode .grid-stack-item {
    cursor: move !important;
    pointer-events: auto !important;
}
.dashboard-edit-mode .grid-stack-item:not(.ui-draggable-disabled):not(.ui-resizable-disabled) {
    cursor: move !important;
}
.grid-stack-item.ui-draggable-disabled {
    cursor: default !important;
}
.dashboard-edit-mode .grid-stack-item .ui-resizable-handle {
    background: rgba(13, 110, 253, 0.3) !important;
    border: 1px solid rgba(13, 110, 253, 0.6) !important;
    z-index: 1001 !important;
}
.dashboard-edit-mode .grid-stack-item .ui-resizable-handle:hover {
    background: rgba(13, 110, 253, 0.6) !important;
}
.dashboard-edit-mode .grid-stack-item .ui-resizable-se {
    width: 20px !important;
    height: 20px !important;
    right: 0 !important;
    bottom: 0 !important;
}
.dashboard-edit-mode .grid-stack-item .ui-resizable-e {
    width: 8px !important;
    right: 0 !important;
    top: 0 !important;
    bottom: 0 !important;
}
.dashboard-edit-mode .grid-stack-item .ui-resizable-s {
    height: 8px !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
}
.dashboard-edit-mode .grid-stack-item .ui-resizable-sw {
    width: 20px !important;
    height: 20px !important;
    left: 0 !important;
    bottom: 0 !important;
}
.dashboard-edit-mode .grid-stack-item .ui-resizable-w {
    width: 8px !important;
    left: 0 !important;
    top: 0 !important;
    bottom: 0 !important;
}
.grid-stack-item-content {
    overflow: hidden !important;
    inset: 0 !important;
    position: absolute !important;
    /* Padding interno padrão - será sobrescrito dinamicamente */
    padding: 12px !important;
    box-sizing: border-box !important;
    transition: padding 0.2s ease;
}
.dashboard-edit-mode .grid-stack-item {
    border: 2px dashed #0d6efd !important;
    border-radius: 8px !important;
    background: rgba(13, 110, 253, 0.05) !important;
}
.dashboard-edit-mode .grid-stack-item:hover {
    border-color: #0a58ca !important;
    box-shadow: 0 0 15px rgba(13, 110, 253, 0.4) !important;
    background: rgba(13, 110, 253, 0.1) !important;
}
/* Botão de remover card no modo de edição */
.dashboard-edit-mode .btn-remover-card {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 1002;
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.95);
    color: #F1416C;
    border: 1px solid rgba(241, 65, 108, 0.3);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    cursor: pointer;
    opacity: 0;
    transition: all 0.25s ease;
    pointer-events: auto;
    margin: 0 !important;
    backdrop-filter: blur(10px);
}
.dashboard-edit-mode .grid-stack-item:hover .btn-remover-card {
    opacity: 1;
}
.dashboard-edit-mode .btn-remover-card:hover {
    background: #F1416C;
    color: white;
    border-color: #F1416C;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(241, 65, 108, 0.4);
}
.dashboard-edit-mode .btn-remover-card:active {
    transform: translateY(0);
}
.dashboard-edit-mode .btn-remover-card i {
    font-size: 18px;
    pointer-events: none;
}
.grid-stack-item.ui-resizable-resizing {
    opacity: 0.9;
    z-index: 1000 !important;
}
.grid-stack-item.ui-draggable-dragging {
    opacity: 0.9;
    z-index: 1000 !important;
    transform: rotate(2deg);
}
/* Remove padding/margin que pode interferir */
.grid-stack > .row,
.grid-stack .row {
    margin: 0 !important;
    padding: 0 !important;
}
.grid-stack-item .col-xl-3,
.grid-stack-item .col-xl-4,
.grid-stack-item .col-xl-6,
.grid-stack-item .col-xl-8,
.grid-stack-item .col-xl-12,
.grid-stack-item [class*="col-"] {
    padding: 0 !important;
    margin: 0 !important;
}
/* Garante que os cards dentro do grid item ocupem 100% do espaço disponível */
.grid-stack-item-content > .card,
.grid-stack-item-content > a.card {
    height: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    box-sizing: border-box !important;
    border-radius: 8px !important;
}
/* Desabilita links dos cards no modo de edição para evitar redirecionamento ao arrastar */
.dashboard-edit-mode .grid-stack-item a[href],
.dashboard-edit-mode .grid-stack-item a.card {
    pointer-events: none !important;
    cursor: move !important;
}
.dashboard-edit-mode .grid-stack-item a[href]::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1;
}
/* Remove TODAS as classes de margem e padding do Bootstrap dos elementos internos */
.grid-stack-item [class*="mb-"],
.grid-stack-item [class*="mt-"],
.grid-stack-item [class*="ml-"],
.grid-stack-item [class*="mr-"],
.grid-stack-item [class*="mx-"],
.grid-stack-item [class*="my-"],
.grid-stack-item [class*="m-"] {
    margin: 0 !important;
}
/* Mantém padding interno dos cards */
.grid-stack-item .card-body {
    padding: 1.5rem !important;
}
/* Remove padding de classes específicas do Bootstrap que interferem */
.grid-stack-item.mb-5,
.grid-stack-item.mb-xl-8,
.grid-stack-item.mb-8 {
    margin-bottom: 0 !important;
}

/* ========== ESTILOS DO CARROSSEL DE CARDS ========== */
.carousel-card-container {
    position: relative;
    height: 100%;
    overflow: hidden;
}
.swiper-carousel-cards {
    width: 100%;
    height: 100%;
    padding: 10px 0;
}
/* Desabilita interação do Swiper no modo de edição para não interferir com GridStack */
.dashboard-edit-mode .swiper {
    pointer-events: none !important;
}
.dashboard-edit-mode .swiper-wrapper {
    pointer-events: none !important;
}
.dashboard-edit-mode .swiper-slide {
    pointer-events: none !important;
}
.dashboard-edit-mode .swiper-button-next,
.dashboard-edit-mode .swiper-button-prev {
    pointer-events: none !important;
    display: none !important;
}
.dashboard-edit-mode .swiper-pagination {
    pointer-events: none !important;
    display: none !important;
}
.swiper-carousel-cards .swiper-slide {
    display: flex;
    justify-content: center;
    align-items: center;
}
.swiper-carousel-cards .swiper-slide .mini-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 1.5rem;
    width: 100%;
    height: auto;
    min-height: 180px;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s, box-shadow 0.3s;
}
.swiper-carousel-cards .swiper-slide .mini-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
.swiper-carousel-cards .swiper-button-next,
.swiper-carousel-cards .swiper-button-prev {
    color: #009ef7;
    width: 35px;
    height: 35px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.swiper-carousel-cards .swiper-button-next:after,
.swiper-carousel-cards .swiper-button-prev:after {
    font-size: 16px;
    font-weight: bold;
}
.swiper-carousel-cards .swiper-pagination-bullet {
    background: #009ef7;
    opacity: 0.4;
}
.swiper-carousel-cards .swiper-pagination-bullet-active {
    opacity: 1;
}
.carousel-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 2rem;
    text-align: center;
}
.carousel-empty-state i {
    font-size: 4rem;
    color: #e4e6ef;
    margin-bottom: 1rem;
}
.carousel-config-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
}
.carousel-card-wrapper {
    width: 100%;
    height: 100%;
}
.carousel-card-wrapper .card {
    height: 100%;
    margin: 0 !important;
}
/* Ajuste para cards existentes dentro do carrossel */
.swiper-carousel-cards .swiper-slide .carousel-card-wrapper .card {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}
.swiper-carousel-cards .swiper-slide .carousel-card-wrapper .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
/* Responsividade para cards no carrossel */
@media (max-width: 768px) {
    .swiper-carousel-cards {
        padding: 10px 0;
    }
    .swiper-carousel-cards .swiper-button-next,
    .swiper-carousel-cards .swiper-button-prev {
        width: 30px;
        height: 30px;
    }
}
/* Estilos para seleção de cards */
.card-hoverable {
    transition: all 0.3s ease;
}
.card-hoverable:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15) !important;
}
.border-primary {
    border-color: #009ef7 !important;
}
.bg-light-primary {
    background-color: #f1faff !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const GRID_CONTAINER_ID = 'dashboard_grid_area';
    let grid = null;
    let gridEstatico = null; // Grid estático (somente leitura) usado fora do modo de edição
    let editMode = false;
    let originalConfig = null;
    let cardsDisponiveis = [];
    let cardsAdicionados = new Set();
    const cardTemplates = new Map();
    
    // Configurações do Dashboard (padrão)
    let dashboardConfig = {
        margin: 16,
        cellHeight: 70,
        densidade: 'padrao',
        temaGrid: 'azul',
        animate: true
    };
    
    // Temas de cores
    const temasCores = {
        azul: { primary: '#0d6efd', bg: 'rgba(13, 110, 253, 0.05)', bgHover: 'rgba(13, 110, 253, 0.1)' },
        verde: { primary: '#50cd89', bg: 'rgba(80, 205, 137, 0.05)', bgHover: 'rgba(80, 205, 137, 0.1)' },
        roxo: { primary: '#7239ea', bg: 'rgba(114, 57, 234, 0.05)', bgHover: 'rgba(114, 57, 234, 0.1)' },
        laranja: { primary: '#ffc700', bg: 'rgba(255, 199, 0, 0.05)', bgHover: 'rgba(255, 199, 0, 0.1)' }
    };
    
    garantirCardIdsBase();
    
    function slugify(text) {
        return text
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .substring(0, 60) || 'card';
    }

    function gerarIdParaCard(elemento, indiceFallback) {
        if (!elemento) {
            return `card_auto_${indiceFallback}`;
        }

        if (elemento.dataset.cardId) {
            return elemento.dataset.cardId;
        }

        if (elemento.id) {
            return `card_${slugify(elemento.id)}`;
        }

        const label = elemento.querySelector('[data-card-title], .card-title .card-label, .card-title span, h3, h4, h5');
        if (label && label.textContent.trim().length > 0) {
            return `card_${slugify(label.textContent.trim())}`;
        }

        return `card_auto_${indiceFallback}`;
    }

    function garantirCardIdsBase() {
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) {
            console.warn('Container não encontrado em garantirCardIdsBase');
            return;
        }

        // PRIMEIRO: Garante que elementos que já têm data-card-id também tenham data-gs-id
        container.querySelectorAll('[data-card-id]').forEach(elemento => {
            const cardId = elemento.getAttribute('data-card-id');
            if (!elemento.hasAttribute('data-gs-id')) {
                elemento.setAttribute('data-gs-id', cardId);
            }
        });

        // SEGUNDO: Busca apenas elementos SEM data-card-id que são cards válidos
        // Busca por colunas Bootstrap que contêm cards mas não têm data-card-id
        let candidatos = Array.from(container.querySelectorAll('.row > div[class*="col-"]:not([data-card-id])'));
        
        // Filtra apenas os que realmente têm cards dentro
        candidatos = candidatos.filter(el => {
            const temCard = el.querySelector('.card, a.card');
            // Ignora wrappers como .row, .g-4, etc.
            const ehWrapper = el.classList.contains('row') || el.id === 'row_stats_cards';
            return temCard && !ehWrapper;
        });
        
        // Se não encontrou em .row > div[class*="col-"], tenta outras estruturas
        if (candidatos.length === 0) {
            // Busca diretamente por cards sem data-card-id que não são filhos de outros cards
            const cardsSemId = Array.from(container.querySelectorAll('.card:not([data-card-id]), a.card:not([data-card-id])'));
            candidatos = cardsSemId.filter(card => {
                // Verifica se não é filho de outro elemento com data-card-id
                const paiComId = card.closest('[data-card-id]');
                return !paiComId || paiComId === container;
            });
        }
        
        let autoIndex = 0;

        candidatos.forEach(elemento => {
            // Verifica se tem card dentro (se não for o próprio card)
            const temCard = elemento.querySelector('.card, a.card') || elemento.classList.contains('card') || elemento.classList.contains('a.card');
            if (!temCard) {
                return;
            }

            // Ignora wrappers
            if (elemento.classList.contains('row') || elemento.id === 'row_stats_cards') {
                return;
            }

            let idGerado = gerarIdParaCard(elemento, autoIndex++);
            let sufixo = 1;
            while (document.querySelector(`[data-card-id="${idGerado}"]`)) {
                idGerado = `${idGerado}_${sufixo++}`;
            }

            elemento.setAttribute('data-card-id', idGerado);
            // Também adiciona data-gs-id se não tiver
            if (!elemento.hasAttribute('data-gs-id')) {
                elemento.setAttribute('data-gs-id', idGerado);
            }
            
            console.log('ID atribuído em garantirCardIdsBase:', idGerado);
        });
        
        console.log('garantirCardIdsBase concluído. Cards processados:', candidatos.length);
    }

    function limparEspacamentosCard(elemento) {
        if (!elemento || !elemento.classList) {
            return;
        }
        const classes = Array.from(elemento.classList);
        classes.forEach(cls => {
            // Remove todas as classes de margem e padding do Bootstrap
            if (/^m[trblxy]?-/i.test(cls) || 
                /^my-/i.test(cls) || 
                /^mx-/i.test(cls) ||
                /^p[trblxy]?-/i.test(cls) ||
                /^py-/i.test(cls) ||
                /^px-/i.test(cls)) {
                elemento.classList.remove(cls);
            }
        });
        elemento.style.margin = '0';
        elemento.style.padding = '';
        
        // Também limpa do elemento pai se for grid-stack-item
        const parent = elemento.closest('.grid-stack-item');
        if (parent && parent !== elemento) {
            const parentClasses = Array.from(parent.classList);
            parentClasses.forEach(cls => {
                if (/^m[trblxy]?-/i.test(cls) || 
                    /^my-/i.test(cls) || 
                    /^mx-/i.test(cls) ||
                    /^p[trblxy]?-/i.test(cls) ||
                    /^py-/i.test(cls) ||
                    /^px-/i.test(cls)) {
                    parent.classList.remove(cls);
                }
            });
            parent.style.margin = '0';
            parent.style.padding = '0';
        }
    }

    function inferirLarguraGrid(elemento) {
        if (!elemento) {
            return 6;
        }
        if (elemento.dataset.cardW) {
            const valor = parseInt(elemento.dataset.cardW, 10);
            if (!isNaN(valor) && valor > 0) {
                return Math.min(12, Math.max(1, valor));
            }
        }
        const classe = Array.from(elemento.classList || []).find(cls => cls.startsWith('col-xl-'));
        if (classe) {
            const valor = parseInt(classe.replace('col-xl-', ''), 10);
            if (!isNaN(valor)) {
                return Math.min(12, Math.max(1, valor));
            }
        }
        return 6;
    }

    function inferirAlturaGrid(elemento) {
        if (!elemento) {
            return 4;
        }
        const datasetValor = elemento.dataset.cardH || elemento.dataset.gridH;
        if (datasetValor) {
            const valor = parseInt(datasetValor, 10);
            if (!isNaN(valor) && valor > 0) {
                return Math.min(12, Math.max(2, valor));
            }
        }
        const card = elemento.querySelector('.card');
        if (card) {
            const alturaPx = card.offsetHeight || card.scrollHeight || 280;
            return Math.min(12, Math.max(3, Math.round(alturaPx / 90)));
        }
        return 4;
    }

    function extrairMetadadosCard(elemento) {
        const id = elemento?.dataset?.cardId || `card_auto_${Date.now()}`;
        const tituloAttr = elemento?.dataset?.cardTitle;
        const tituloElemento = elemento?.querySelector('[data-card-title], .card-title .card-label, .card-title span, h3, h4, h5');
        const nome = (tituloAttr || (tituloElemento ? tituloElemento.textContent.trim() : '') || id).trim();
        const descricao = elemento?.dataset?.cardDesc || 'Card do dashboard';
        const icone = elemento?.dataset?.cardIcon || 'ki-element';
        const w = inferirLarguraGrid(elemento);
        const h = inferirAlturaGrid(elemento);
        return { id, nome, descricao, icone, w, h };
    }

    // Permissões dos cards (PHP -> JavaScript)
    const cardPermissions = {
        <?php
        // Lista de cards para verificar permissões
        $all_cards = [
            'card_total_colaboradores',
            'card_colaboradores_ativos',
            'card_ocorrencias_mes',
            'card_colaboradores_inativos',
            'card_status_cron_fechamentos',
            'card_proximos_aniversarios',
            'card_ranking_ocorrencias',
            'card_grafico_ocorrencias_mes',
            'card_grafico_colaboradores_status',
            'card_grafico_ocorrencias_tipo',
            'card_anotacoes',
            'card_historico_promocoes',
            'card_emocao_diaria',
            'card_ranking_pontos',
            'card_historico_emocoes',
            'card_media_emocoes',
            'card_ultimas_emocoes',
            'card_carrossel'
        ];
        
        $permissions_array = [];
        foreach ($all_cards as $card_id) {
            $can_see = can_see_dashboard_card($card_id);
            $permissions_array[] = "'$card_id': " . ($can_see ? 'true' : 'false');
        }
        echo implode(",\n        ", $permissions_array);
        ?>
    };

    const catalogoBaseCards = [
            { id: 'card_total_colaboradores', nome: 'Total de Colaboradores', descricao: 'Mostra o total de colaboradores', icone: 'ki-profile-circle', w: 3, h: 3 },
            { id: 'card_colaboradores_ativos', nome: 'Colaboradores Ativos', descricao: 'Mostra colaboradores ativos', icone: 'ki-check-circle', w: 3, h: 3 },
            { id: 'card_ocorrencias_mes', nome: 'Ocorrências no Mês', descricao: 'Ocorrências registradas no mês', icone: 'ki-notepad', w: 3, h: 3 },
            { id: 'card_colaboradores_inativos', nome: 'Colaboradores Inativos', descricao: 'Mostra colaboradores inativos', icone: 'ki-cross-circle', w: 3, h: 3 },
            { id: 'card_status_cron_fechamentos', nome: 'Status Cron - Fechamentos Recorrentes', descricao: 'Última execução do cron de fechamentos recorrentes', icone: 'ki-time', w: 12, h: 3 },
            { id: 'card_proximos_aniversarios', nome: 'Próximos Aniversários', descricao: 'Aniversários dos próximos 30 dias', icone: 'ki-cake', w: 6, h: 5 },
            { id: 'card_ranking_ocorrencias', nome: 'Ranking de Ocorrências', descricao: 'Ranking de colaboradores por ocorrências', icone: 'ki-chart-simple', w: 6, h: 5 },
            { id: 'card_grafico_ocorrencias_mes', nome: 'Gráfico de Ocorrências', descricao: 'Gráfico de ocorrências por mês', icone: 'ki-chart', w: 8, h: 4 },
            { id: 'card_grafico_colaboradores_status', nome: 'Colaboradores por Status', descricao: 'Gráfico de colaboradores por status', icone: 'ki-chart-pie', w: 4, h: 4 },
            { id: 'card_grafico_ocorrencias_tipo', nome: 'Ocorrências por Tipo', descricao: 'Gráfico de ocorrências por tipo', icone: 'ki-chart-bar', w: 12, h: 4 },
            { id: 'card_anotacoes', nome: 'Anotações', descricao: 'Anotações do sistema', icone: 'ki-note-edit', w: 6, h: 6 },
            { id: 'card_historico_promocoes', nome: 'Histórico de Promoções', descricao: 'Últimas promoções registradas', icone: 'ki-upgrade', w: 6, h: 6 },
            { id: 'card_emocao_diaria', nome: 'Como você está se sentindo?', descricao: 'Formulário de emoção diária', icone: 'ki-heart', w: 8, h: 6 },
            { id: 'card_ranking_pontos', nome: 'Ranking de Pontos', descricao: 'Ranking gamificado dos colaboradores', icone: 'ki-medal-star', w: 4, h: 5 },
            { id: 'card_historico_emocoes', nome: 'Histórico de Emoções', descricao: 'Linha do tempo das emoções', icone: 'ki-calendar', w: 4, h: 5 },
            { id: 'card_media_emocoes', nome: 'Média das Emoções', descricao: 'Indicador médio de humor', icone: 'ki-graph-up', w: 4, h: 4 },
            { id: 'card_ultimas_emocoes', nome: 'Últimas Emoções Registradas', descricao: 'Tabela das últimas emoções registradas', icone: 'ki-tablet-text-up', w: 8, h: 5 },
            { id: 'card_carrossel', nome: '🎠 Carrossel de Cards', descricao: 'Carrossel com múltiplos cards deslizantes', icone: 'ki-slider', w: 12, h: 5, tipo: 'carrossel' }
        ];

    // Define cards disponíveis
    function definirCardsDisponiveis() {
        cardsDisponiveis = [...catalogoBaseCards];
        const idsRegistrados = new Set(cardsDisponiveis.map(card => card.id));

        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) {
            return;
        }

        container.querySelectorAll('[data-card-id]').forEach(elemento => {
            const id = elemento.dataset.cardId;
            if (!id || idsRegistrados.has(id)) {
                return;
            }
            const info = extrairMetadadosCard(elemento);
            cardsDisponiveis.push(info);
            idsRegistrados.add(info.id);
        });
    }
    
    // Gera layout padrão baseado nos cards disponíveis
    function gerarLayoutPadrao() {
        const layout = [];
        let currentX = 0;
        let currentY = 0;
        let maxHeightInRow = 0;
        
        // Primeira linha: cards de estatísticas (3x3 cada)
        const cardsStats = [
            { id: 'card_total_colaboradores', w: 3, h: 3 },
            { id: 'card_colaboradores_ativos', w: 3, h: 3 },
            { id: 'card_ocorrencias_mes', w: 3, h: 3 },
            { id: 'card_colaboradores_inativos', w: 3, h: 3 }
        ];
        
        cardsStats.forEach(card => {
            layout.push({
                id: card.id,
                x: currentX,
                y: currentY,
                w: card.w,
                h: card.h,
                visible: true
            });
            currentX += card.w;
            maxHeightInRow = Math.max(maxHeightInRow, card.h);
        });
        
        // Segunda linha: gráficos
        currentX = 0;
        currentY += maxHeightInRow + 1;
        maxHeightInRow = 0;
        
        const cardsGraficos = [
            { id: 'card_grafico_ocorrencias_mes', w: 8, h: 4 },
            { id: 'card_grafico_colaboradores_status', w: 4, h: 4 }
        ];
        
        cardsGraficos.forEach(card => {
            layout.push({
                id: card.id,
                x: currentX,
                y: currentY,
                w: card.w,
                h: card.h,
                visible: true
            });
            currentX += card.w;
            maxHeightInRow = Math.max(maxHeightInRow, card.h);
        });
        
        // Terceira linha: gráfico de tipos (largura total)
        currentX = 0;
        currentY += maxHeightInRow + 1;
        maxHeightInRow = 0;
        
        layout.push({
            id: 'card_grafico_ocorrencias_tipo',
            x: currentX,
            y: currentY,
            w: 12,
            h: 4,
            visible: true
        });
        currentY += 4 + 1;
        
        // Quarta linha: ranking e aniversários lado a lado
        currentX = 0;
        maxHeightInRow = 0;
        
        const cardsInfo = [
            { id: 'card_ranking_ocorrencias', w: 6, h: 5 },
            { id: 'card_proximos_aniversarios', w: 6, h: 5 }
        ];
        
        cardsInfo.forEach(card => {
            layout.push({
                id: card.id,
                x: currentX,
                y: currentY,
                w: card.w,
                h: card.h,
                visible: true
            });
            currentX += card.w;
            maxHeightInRow = Math.max(maxHeightInRow, card.h);
        });
        
        // Quinta linha: anotações e histórico
        currentX = 0;
        currentY += maxHeightInRow + 1;
        
        const cardsFinais = [
            { id: 'card_anotacoes', w: 6, h: 6 },
            { id: 'card_historico_promocoes', w: 6, h: 6 }
        ];
        
        cardsFinais.forEach(card => {
            layout.push({
                id: card.id,
                x: currentX,
                y: currentY,
                w: card.w,
                h: card.h,
                visible: true
            });
            currentX += card.w;
            maxHeightInRow = Math.max(maxHeightInRow, card.h);
        });

        currentX = 0;
        currentY += maxHeightInRow + 1;
        maxHeightInRow = 0;

        const cardsEmocaoLinha1 = [
            { id: 'card_emocao_diaria', w: 8, h: 5 },
            { id: 'card_ranking_pontos', w: 4, h: 5 }
        ];

        cardsEmocaoLinha1.forEach(card => {
            layout.push({
                id: card.id,
                x: currentX,
                y: currentY,
                w: card.w,
                h: card.h,
                visible: true
            });
            currentX += card.w;
            maxHeightInRow = Math.max(maxHeightInRow, card.h);
        });

        currentX = 0;
        currentY += maxHeightInRow + 1;
        maxHeightInRow = 0;

        const cardsEmocaoLinha2 = [
            { id: 'card_historico_emocoes', w: 4, h: 5 },
            { id: 'card_media_emocoes', w: 4, h: 4 }
        ];

        cardsEmocaoLinha2.forEach(card => {
            layout.push({
                id: card.id,
                x: currentX,
                y: currentY,
                w: card.w,
                h: card.h,
                visible: true
            });
            currentX += card.w;
            maxHeightInRow = Math.max(maxHeightInRow, card.h);
        });

        currentX = 0;
        currentY += maxHeightInRow + 1;
        maxHeightInRow = 0;

        layout.push({
            id: 'card_ultimas_emocoes',
            x: currentX,
            y: currentY,
            w: 12,
            h: 5,
            visible: true
        });

        return layout;
    }
    
    // Carrega configuração salva
    function carregarConfiguracao() {
        return fetch('../api/dashboard/carregar_config.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.cards && data.cards.length > 0) {
                    // Atualiza cards adicionados
                    data.cards.forEach(card => {
                        if (card.visible !== false) {
                            cardsAdicionados.add(card.id);
                        }
                    });
                    
                    // Carrega configurações do dashboard se existirem
                    if (data.config) {
                        dashboardConfig = {
                            margin: parseInt(data.config.margin) || 16,
                            cellHeight: parseInt(data.config.cellHeight) || 70,
                            densidade: data.config.densidade || 'padrao',
                            temaGrid: data.config.temaGrid || 'azul',
                            animate: data.config.animate !== false
                        };
                        aplicarEstilosTema();
                    }
                    
                    // Carrega configurações dos carrosséis se existirem
                    if (data.carrosselConfigs) {
                        Object.assign(carrosselConfigs, data.carrosselConfigs);
                        console.log('Configurações de carrosséis carregadas:', Object.keys(carrosselConfigs));
                    }
                    
                    // Retorna no formato esperado pelo GridStack
                    return data.cards.map(card => ({
                        id: card.id,
                        x: card.x || 0,
                        y: card.y || 0,
                        w: card.w || 3,
                        h: card.h || 3,
                        content: '', // GridStack precisa disso
                        noResize: false,
                        noMove: false
                    }));
                }
                return null;
            })
            .catch(error => {
                console.error('Erro ao carregar configuração:', error);
                return null;
            });
    }
    
    // Carrega apenas configurações do dashboard
    function carregarConfiguracoesDashboard() {
        return fetch('../api/dashboard/carregar_config.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.config) {
                    dashboardConfig = {
                        margin: parseInt(data.config.margin) || 16,
                        cellHeight: parseInt(data.config.cellHeight) || 70,
                        densidade: data.config.densidade || 'padrao',
                        temaGrid: data.config.temaGrid || 'azul',
                        animate: data.config.animate !== false
                    };
                    aplicarEstilosTema();
                }
                
                // Carrega configurações dos carrosséis se existirem
                if (data.carrosselConfigs) {
                    Object.assign(carrosselConfigs, data.carrosselConfigs);
                    console.log('Configurações de carrosséis carregadas:', Object.keys(carrosselConfigs));
                }
                
                // Retorna TANTO as configurações quanto os cards salvos
                return {
                    config: dashboardConfig,
                    cards: data.cards || []
                };
            })
            .catch(error => {
                console.error('Erro ao carregar configurações:', error);
                return {
                    config: dashboardConfig,
                    cards: []
                };
            });
    }
    
    // Aplica estilos do tema dinamicamente
    function aplicarEstilosTema() {
        const tema = temasCores[dashboardConfig.temaGrid] || temasCores.azul;
        const styleElement = document.getElementById('dynamic_grid_styles');
        
        // Calcula padding interno (12px fixo para manter espaçamento visual consistente)
        // Isso garante que os cards tenham margem visual semelhante ao modo normal
        const paddingInterno = 12;
        
        if (styleElement) {
            styleElement.innerHTML = `
                .grid-stack-item-content {
                    padding: ${paddingInterno}px !important;
                }
                .dashboard-edit-mode .grid-stack-item {
                    border-color: ${tema.primary} !important;
                    background: ${tema.bg} !important;
                }
                .dashboard-edit-mode .grid-stack-item:hover {
                    border-color: ${tema.primary} !important;
                    box-shadow: 0 0 15px ${tema.primary}40 !important;
                    background: ${tema.bgHover} !important;
                }
            `;
        }
    }
    
    // Aplica densidade ao layout
    function aplicarDensidade() {
        const densidades = {
            compacto: { margin: 8, cellHeight: 60 },
            padrao: { margin: 16, cellHeight: 70 },
            espacado: { margin: 24, cellHeight: 80 }
        };
        
        const densidade = densidades[dashboardConfig.densidade] || densidades.padrao;
        dashboardConfig.margin = densidade.margin;
        dashboardConfig.cellHeight = densidade.cellHeight;
    }
    
    // Salva configurações do dashboard
    function salvarConfiguracoesDashboard() {
        return fetch('../api/dashboard/salvar_config.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                config: dashboardConfig
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return true;
            } else {
                throw new Error(data.message || 'Erro ao salvar configurações');
            }
            });
    }
    
    // Salva configuração
    function salvarConfiguracao() {
        if (!grid) {
            return Promise.reject(new Error('Grid não inicializado'));
        }
        
        try {
            const items = grid.save();
            console.log('Items do grid.save():', items);
            
            // Filtra e mapeia items válidos
            const cards = items
                .map((item, index) => {
                    // Tenta pegar o ID de várias formas
                    let cardId = item.id || item['gs-id'];
                    
                    // Se não tem ID, tenta pegar do elemento DOM
                    if (!cardId && item.el) {
                        cardId = item.el.getAttribute('data-gs-id') || 
                                 item.el.getAttribute('data-card-id') ||
                                 item.el.getAttribute('gs-id');
                    }
                    
                    // Gera ID temporário se ainda não tiver
                    if (!cardId) {
                        cardId = `card_temp_${index}_${Date.now()}`;
                        console.warn('Card sem ID, gerando temporário:', cardId);
                    }
                    
                    return {
                        id: cardId,
                    x: parseInt(item.x) || 0,
                    y: parseInt(item.y) || 0,
                    w: parseInt(item.w) || 3,
                    h: parseInt(item.h) || 3,
                    visible: true
                    };
                })
                .filter(card => {
                    // IMPORTANTE: Confia no grid.save() - se está no grid, deve ser salvo
                    // Não filtra por existência no DOM porque alguns cards podem não estar
                    // renderizados por condições PHP, mas ainda devem ser salvos
                    
                    // Valida apenas se o ID é válido
                    if (!card.id || card.id.trim() === '') {
                        console.warn('Card sem ID válido, ignorando:', card);
                        return false;
                    }
                    
                    // Verifica se o ID não é temporário inválido
                    if (card.id.startsWith('card_temp_') && !card.id.match(/card_temp_\d+_\d+$/)) {
                        console.warn('Card com ID temporário inválido, ignorando:', card.id);
                        return false;
                    }
                    
                    // Se chegou aqui, o card é válido para salvar
                    console.log('Card válido para salvar:', card.id);
                    return true;
                });
            
            console.log('Cards válidos para salvar:', cards);
            
            if (cards.length === 0) {
                throw new Error('Nenhum card válido para salvar. Verifique se os cards possuem data-gs-id ou data-card-id.');
            }
            
            // Salva também as configurações dos carrosséis
            const carrosseisConfig = {};
            cards.forEach(card => {
                if (card.id && card.id.startsWith('card_carrossel_') && carrosselConfigs[card.id]) {
                    carrosseisConfig[card.id] = carrosselConfigs[card.id];
                }
            });
            
            console.log('Configurações de carrosséis para salvar:', carrosseisConfig);
            
            return fetch('../api/dashboard/salvar_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    cards: cards,
                    config: dashboardConfig,
                    carrosselConfigs: carrosseisConfig
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || 'Erro ao salvar configuração');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        text: 'Layout salvo com sucesso!',
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: 'Ok',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                    // Atualiza cards adicionados
                    cardsAdicionados.clear();
                    cards.forEach(card => cardsAdicionados.add(card.id));
                    return true;
                } else {
                    throw new Error(data.message || 'Erro ao salvar');
                }
            });
        } catch (error) {
            console.error('Erro ao salvar configuração:', error);
            return Promise.reject(error);
        }
    }
    
    // Limpa o layout (remove todos os cards)
    function limparLayout() {
        Swal.fire({
            text: 'Tem certeza que deseja remover todos os cards do dashboard?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-light'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (!grid) {
                    Swal.fire({
                        text: 'Grid não inicializado',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'Ok',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                    return;
                }
                
                grid.batchUpdate();
                const nodes = grid.engine && grid.engine.nodes ? [...grid.engine.nodes] : [];
                nodes.forEach(node => {
                    if (node?.el) {
                        try {
                            grid.removeWidget(node.el, true);
                        } catch (err) {
                            console.warn('Erro ao remover card', node.id, err);
                        }
                    }
                });
                grid.commit();
                cardsAdicionados.clear();
                
                fetch('../api/dashboard/salvar_config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ cards: [] })
                }).catch(err => console.error('Erro ao limpar configuração no servidor:', err));
                
                Swal.fire({
                    text: 'Todos os cards foram removidos. Adicione novos cards para montar o layout.',
                    icon: 'info',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }
        });
    }
    
    // Restaura o layout padrão
    function restaurarLayout() {
        Swal.fire({
            text: 'Deseja restaurar o layout padrão com todos os cards?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, restaurar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-light'
            }
        }).then(result => {
            if (!result.isConfirmed) {
                return;
            }
            
            if (!grid) {
                Swal.fire({
                    text: 'Grid não inicializado',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
                return;
            }
            
            aplicarLayoutPadrao();
            
            Swal.fire({
                text: 'Layout padrão aplicado. Salve para manter as alterações.',
                icon: 'info',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        });
    }
    
    function montarCardAPartirDoTemplate(cardId) {
        // Se for um carrossel, cria dinamicamente
        if (cardId && cardId.startsWith('card_carrossel_')) {
            console.log('Criando card carrossel dinamicamente:', cardId);
            const carrosselCard = document.createElement('div');
            carrosselCard.className = 'grid-stack-item';
            carrosselCard.setAttribute('data-gs-id', cardId);
            carrosselCard.setAttribute('data-card-id', cardId);
            carrosselCard.setAttribute('gs-id', cardId);
            carrosselCard.innerHTML = `
                <div class="grid-stack-item-content">
                    ${criarCardCarrossel(cardId)}
                </div>
            `;
            
            // Salva o template do carrossel para uso futuro
            if (!cardTemplates.has(cardId)) {
                cardTemplates.set(cardId, carrosselCard.cloneNode(true));
                console.log('Template do carrossel salvo:', cardId);
            }
            
            // Inicializa o carrossel após um curto delay
            setTimeout(() => {
                inicializarCarrossel(cardId);
            }, 500);
            
            console.log('Card carrossel criado:', cardId);
            return carrosselCard;
        }
        
        let cardFonte = null;
        
        if (cardTemplates.has(cardId)) {
            cardFonte = cardTemplates.get(cardId).cloneNode(true);
        }
        
        if (!cardFonte) {
            const domOriginal = document.querySelector(`[data-card-id="${cardId}"]`) || document.querySelector(`[data-gs-id="${cardId}"]`);
            if (domOriginal) {
                cardFonte = domOriginal.cloneNode(true);
            }
        }
        
        if (!cardFonte) {
            console.warn('⚠️ Template não encontrado para card:', cardId);
            console.log('Templates disponíveis:', Array.from(cardTemplates.keys()));
            console.log('Tentando buscar no DOM com diferentes seletores...');
            
            // Tenta buscar por diferentes formas em todo o documento
            const selectors = [
                `[data-card-id="${cardId}"]`,
                `[data-gs-id="${cardId}"]`,
                `#${cardId}`,
                `.${cardId}`
            ];
            
            for (const selector of selectors) {
                const el = document.querySelector(selector);
                if (el) {
                    console.log('✅ Card encontrado com seletor:', selector);
                    cardFonte = el.cloneNode(true);
                    // Salva o template para uso futuro
                    if (!cardTemplates.has(cardId)) {
                        cardTemplates.set(cardId, cardFonte.cloneNode(true));
                        console.log('Template salvo após encontrar no DOM:', cardId);
                    }
                    break;
                }
            }
            
            // Se ainda não encontrou, tenta criar um card placeholder baseado no catálogo
            if (!cardFonte) {
                console.warn('⚠️ Card não encontrado no DOM nem nos templates. Tentando criar placeholder...');
                const cardInfo = catalogoBaseCards.find(card => card.id === cardId);
                
                if (cardInfo) {
                    console.log('✅ Informações do card encontradas no catálogo, criando placeholder:', cardId);
                    // Cria um card placeholder básico
                    cardFonte = document.createElement('div');
                    cardFonte.className = 'grid-stack-item';
                    cardFonte.setAttribute('data-gs-id', cardId);
                    cardFonte.setAttribute('data-card-id', cardId);
                    cardFonte.innerHTML = `
                        <div class="grid-stack-item-content">
                            <div class="card card-xl-stretch">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <span class="card-label fw-bold">${cardInfo.nome}</span>
                                    </h3>
                                </div>
                                <div class="card-body text-center py-10">
                                    <i class="ki-duotone ${cardInfo.icone} fs-3x text-primary mb-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <p class="text-muted">${cardInfo.descricao}</p>
                                    <p class="text-muted small">Este card será carregado quando a página for recarregada</p>
                                </div>
                            </div>
                        </div>
                    `;
                    console.log('✅ Placeholder criado para card:', cardId);
                } else {
                    console.error('❌ Não foi possível encontrar ou criar o card:', cardId);
                    return null;
                }
            }
        }
        
        // Remove atributos do GridStack
        cardFonte.removeAttribute('data-gs-x');
        cardFonte.removeAttribute('data-gs-y');
        cardFonte.removeAttribute('data-gs-w');
        cardFonte.removeAttribute('data-gs-h');
        cardFonte.removeAttribute('gs-x');
        cardFonte.removeAttribute('gs-y');
        cardFonte.removeAttribute('gs-w');
        cardFonte.removeAttribute('gs-h');
        
        // Remove classes Bootstrap de grid e margem
        cardFonte.classList.remove('grid-stack-item');
        cardFonte.classList.remove('col-xl-3', 'col-xl-4', 'col-xl-6', 'col-xl-8', 'col-xl-12', 'col-md-3', 'col-md-4', 'col-md-6', 'col-md-8', 'col-md-12');
        
        // Remove classes de margem do Bootstrap
        const classesToRemove = Array.from(cardFonte.classList).filter(cls => 
            /^m[trblxy]?-/.test(cls) || 
            /^my-/.test(cls) || 
            /^mx-/.test(cls) ||
            /^p[trblxy]?-/.test(cls) ||
            /^py-/.test(cls) ||
            /^px-/.test(cls)
        );
        classesToRemove.forEach(cls => cardFonte.classList.remove(cls));
        
        if (!cardFonte.querySelector('.grid-stack-item-content')) {
            const content = document.createElement('div');
            content.className = 'grid-stack-item-content';
            while (cardFonte.firstChild) {
                content.appendChild(cardFonte.firstChild);
            }
            cardFonte.appendChild(content);
        }
        
        cardFonte.classList.add('grid-stack-item');
        cardFonte.setAttribute('data-gs-id', cardId);
        cardFonte.setAttribute('data-card-id', cardId);
        cardFonte.setAttribute('gs-id', cardId);
        
        // Limpa espaçamentos do card interno
        const cardInterno = cardFonte.querySelector('.card, a.card');
        if (cardInterno) {
            limparEspacamentosCard(cardInterno);
        }
        
        console.log('Card montado com ID:', cardId);
        
        return cardFonte;
    }
    
    // Adiciona um card ao dashboard
    function adicionarCardAoDashboard(cardInfo) {
        if (!grid) {
            console.error('Grid não inicializado');
            return;
        }
        
        // Verifica se o card já existe
        const existingItems = grid.save();
        const jaExiste = existingItems.some(item => item.id === cardInfo.id);
        
        if (jaExiste) {
            Swal.fire({
                text: 'Este card já está no dashboard!',
                icon: 'info',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
            return;
        }
        
        let novoCard = null;
        
        // Se for um carrossel, cria um card especial
        if (cardInfo.tipo === 'carrossel') {
            // Gera ID único para o carrossel
            const carrosselId = cardInfo.id + '_' + Date.now();
            
            // Inicializa configuração padrão
            carrosselConfigs[carrosselId] = {
                slidesPerView: 3,
                speed: 500,
                autoplay: true,
                autoplayDelay: 3000,
                loop: true,
                pagination: true,
                cards: []
            };
            
            novoCard = document.createElement('div');
            novoCard.className = 'grid-stack-item';
            novoCard.setAttribute('data-gs-id', carrosselId);
            novoCard.setAttribute('data-card-id', carrosselId);
            novoCard.innerHTML = `
                <div class="grid-stack-item-content">
                    ${criarCardCarrossel(carrosselId)}
                </div>
            `;
            
            // Salva o template do carrossel
            if (!cardTemplates.has(carrosselId)) {
                cardTemplates.set(carrosselId, novoCard.cloneNode(true));
                console.log('Template do carrossel salvo ao adicionar:', carrosselId);
            }
            
            cardInfo.id = carrosselId; // Atualiza o ID
        } else {
            novoCard = montarCardAPartirDoTemplate(cardInfo.id);
        
        if (!novoCard) {
            // Cria um card placeholder se não encontrar o original
            novoCard = document.createElement('div');
            novoCard.className = 'grid-stack-item';
            novoCard.innerHTML = `
                <div class="grid-stack-item-content">
                    <div class="card card-xl-stretch">
                        <div class="card-body text-center py-10">
                            <i class="ki-duotone ${cardInfo.icone} fs-2tx text-primary mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <h5 class="fw-bold">${cardInfo.nome}</h5>
                            <p class="text-muted">${cardInfo.descricao}</p>
                        </div>
                    </div>
                </div>
            `;
            }
        }
        
        novoCard.classList.add('grid-stack-item');
        novoCard.setAttribute('data-gs-id', cardInfo.id);
        novoCard.setAttribute('data-card-id', cardInfo.id);
        limparEspacamentosCard(novoCard.querySelector('.card, a.card'));
        
        // Encontra posição vazia
        const items = grid.save();
        let posX = 0;
        let posY = 0;
        let encontrouPosicao = false;
        
        // Tenta encontrar uma posição vazia
        for (let y = 0; y < 20 && !encontrouPosicao; y++) {
            for (let x = 0; x <= 12 - cardInfo.w && !encontrouPosicao; x++) {
                const ocupado = items.some(item => {
                    const itemX = item.x || 0;
                    const itemY = item.y || 0;
                    const itemW = item.w || 3;
                    const itemH = item.h || 3;
                    
                    return !(x + cardInfo.w <= itemX || x >= itemX + itemW || 
                            y + cardInfo.h <= itemY || y >= itemY + itemH);
                });
                
                if (!ocupado) {
                    posX = x;
                    posY = y;
                    encontrouPosicao = true;
                }
            }
        }
        
        // Garante que o card tem os atributos corretos antes de adicionar ao grid
        novoCard.setAttribute('data-gs-id', cardInfo.id);
        novoCard.setAttribute('data-card-id', cardInfo.id);
        novoCard.setAttribute('gs-id', cardInfo.id);
        
        // Adiciona o card ao grid
        grid.addWidget(novoCard, {
            id: cardInfo.id,
            x: posX,
            y: posY,
            w: cardInfo.w,
            h: cardInfo.h
        });
        
        // Adiciona ao conjunto de cards adicionados
        cardsAdicionados.add(cardInfo.id);
        
        console.log('Card adicionado ao grid com ID:', cardInfo.id);
        console.log('Atributos do card:', {
            'data-gs-id': novoCard.getAttribute('data-gs-id'),
            'data-card-id': novoCard.getAttribute('data-card-id'),
            'gs-id': novoCard.getAttribute('gs-id')
        });
        
        // Reinicializa gráficos se o card adicionado contém gráficos
        if (cardInfo.id.includes('grafico')) {
            setTimeout(() => {
                console.log('🔄 Tentando reinicializar gráficos após adicionar card:', cardInfo.id);
                console.log('Função reinicializarGraficos existe:', typeof reinicializarGraficos === 'function');
                console.log('Chart.js disponível:', typeof Chart !== 'undefined');
                if (typeof reinicializarGraficos === 'function') {
                    reinicializarGraficos();
                } else {
                    console.error('❌ Função reinicializarGraficos não encontrada!');
                }
            }, 500);
        }
        
        cardsAdicionados.add(cardInfo.id);
        
        // Aplica estilos após adicionar
        aplicarEstilosTema();
        
                // Se estiver em modo de edição, desabilita links do novo card e adiciona botão de remover
        if (editMode) {
            setTimeout(() => {
                const links = novoCard.querySelectorAll('a[href], a.card');
                links.forEach(link => {
                    link.addEventListener('click', prevenirCliqueLink, true);
                    link.addEventListener('mousedown', prevenirCliqueLink, true);
                    link.classList.add('link-desabilitado-edicao');
                });
                
                // Adiciona botão de remover no novo card
                if (!novoCard.querySelector('.btn-remover-card')) {
                    const btnRemover = document.createElement('button');
                    btnRemover.className = 'btn btn-remover-card';
                    btnRemover.type = 'button';
                    btnRemover.title = 'Remover card';
                    btnRemover.innerHTML = '<i class="ki-duotone ki-trash fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>';
                    
                    btnRemover.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        removerCard(novoCard);
                    });
                    
                    novoCard.appendChild(btnRemover);
                }
            }, 100);
        }
        
        // Se for carrossel, inicializa após adicionar
        if (cardInfo.tipo === 'carrossel') {
            setTimeout(() => {
                inicializarCarrossel(cardInfo.id);
            }, 300);
        }
        
        // Fecha o modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modal_adicionar_cards'));
        if (modal) modal.hide();
        
        // Atualiza lista de cards disponíveis
        setTimeout(() => {
            carregarListaCardsDisponiveis();
        }, 300);
    }
    
    // Carrega lista de cards disponíveis no modal
    function carregarListaCardsDisponiveis() {
        const container = document.getElementById('lista_cards_disponiveis');
        if (!container) return;
        
        const filtro = document.getElementById('buscar_cards')?.value.toLowerCase() || '';
        const cardsFiltrados = cardsDisponiveis.filter(card => {
            // Verifica permissão do card
            if (card.id && cardPermissions[card.id] === false) {
                return false;
            }
            // Aplica filtro de busca
            return card.nome.toLowerCase().includes(filtro) || 
                   card.descricao.toLowerCase().includes(filtro);
        });
        
        container.innerHTML = '';
        
        if (cardsFiltrados.length === 0) {
            container.innerHTML = '<div class="col-12 text-center text-muted py-5">Nenhum card encontrado</div>';
            return;
        }
        
        cardsFiltrados.forEach(card => {
            const jaAdicionado = cardsAdicionados.has(card.id);
            const cardHtml = `
                <div class="col-md-6 col-lg-4">
                    <div class="card card-hoverable ${jaAdicionado ? 'border-success' : ''}" style="cursor: pointer;" data-card-info='${JSON.stringify(card)}'>
                        <div class="card-body text-center p-5">
                            <i class="ki-duotone ${card.icone} fs-2tx text-primary mb-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <h5 class="fw-bold mb-2">${card.nome}</h5>
                            <p class="text-muted small mb-3">${card.descricao}</p>
                            ${jaAdicionado ? '<span class="badge badge-success">Já adicionado</span>' : '<span class="badge badge-primary">Clique para adicionar</span>'}
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', cardHtml);
        });
        
        // Adiciona event listeners
        container.querySelectorAll('[data-card-info]').forEach(element => {
            element.addEventListener('click', function() {
                const cardInfo = JSON.parse(this.getAttribute('data-card-info'));
                if (!cardsAdicionados.has(cardInfo.id)) {
                    adicionarCardAoDashboard(cardInfo);
                }
            });
        });
    }
    
    // Converte cards existentes para GridStack items
    function converterCardsParaGrid(configuracao = null) {
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) {
            console.error('Container não encontrado');
            return [];
        }
        
        // NÃO limpa cardsAdicionados aqui se já temos uma configuração
        // Isso preserva a informação de quais cards foram salvos
        if (!configuracao || configuracao.length === 0) {
        cardsAdicionados.clear();
        }
        
        if (!container.classList.contains('grid-stack')) {
            container.classList.add('grid-stack');
        }
        
        let layoutParaUsar = configuracao;
        if (!layoutParaUsar || layoutParaUsar.length === 0) {
            console.log('Usando layout padrão');
            layoutParaUsar = gerarLayoutPadrao();
        }
        
        const configMap = {};
        const idsNoLayout = new Set();
        if (Array.isArray(layoutParaUsar)) {
            layoutParaUsar.forEach(cfg => {
                if (cfg.id) {
                    configMap[cfg.id] = cfg;
                    idsNoLayout.add(cfg.id);
                }
            });
        }
        
        // Garante que todos os cards têm data-card-id antes de processar
        garantirCardIdsBase();
        
        // Busca cards de múltiplas formas para garantir que encontra todos
        // IMPORTANTE: Busca apenas elementos diretos do container, não filhos de outros cards
        let cards = Array.from(container.children).filter(el => {
            // Ignora elementos que são filhos de outros cards processados
            const temId = el.hasAttribute('data-card-id') || el.hasAttribute('data-gs-id');
            const temCard = el.querySelector('.card, a.card') || el.classList.contains('card') || el.classList.contains('a.card');
            
            // Se tem ID, é um card válido
            if (temId) {
                // Verifica se não é filho de outro card com ID
                const paiComId = el.parentElement?.closest('[data-card-id], [data-gs-id]');
                return !paiComId || paiComId === container;
            }
            
            // Se tem card mas não tem ID, pode ser um card válido
            if (temCard) {
                // Verifica se não é filho de outro card
                const paiComId = el.parentElement?.closest('[data-card-id], [data-gs-id]');
                return !paiComId || paiComId === container;
            }
            
            return false;
        });
        
        // Se não encontrou nos filhos diretos, tenta buscar por data-card-id (mas filtra duplicatas)
        if (cards.length === 0) {
            const todosComId = Array.from(container.querySelectorAll('[data-card-id], [data-gs-id]'));
            // Remove duplicatas - se um elemento é filho de outro com ID, mantém apenas o pai mais próximo do container
            cards = todosComId.filter(el => {
                // Verifica se é filho direto do container
                if (el.parentElement === container) {
                    return true;
                }
                // Verifica se tem um pai com ID que não seja o container
                const paiComId = el.parentElement?.closest('[data-card-id], [data-gs-id]');
                return !paiComId || paiComId === container;
            });
        }
        
        // Se ainda não encontrou, busca por elementos com card dentro de colunas Bootstrap
        if (cards.length === 0) {
            console.log('Nenhum card com data-card-id encontrado, tentando outras formas...');
            const colunasComCard = Array.from(container.children).filter(col => {
                return col.classList.toString().match(/col-(xl|md|lg|sm)-/) && col.querySelector('.card, a.card');
            });
            cards = colunasComCard;
            
            // Atribui IDs aos cards encontrados
            cards.forEach((card, index) => {
                if (!card.hasAttribute('data-card-id')) {
                    const cardId = gerarIdParaCard(card, index);
                    card.setAttribute('data-card-id', cardId);
                    if (!card.hasAttribute('data-gs-id')) {
                        card.setAttribute('data-gs-id', cardId);
                    }
                    console.log('ID atribuído ao card:', cardId);
                }
            });
        }
        
        if (cards.length === 0) {
            console.warn('Nenhum card encontrado no container após todas as tentativas');
            console.log('Container HTML:', container.innerHTML.substring(0, 500));
            return [];
        }
        
        console.log('Cards encontrados no container:', cards.length);
        console.log('Cards no layout salvo:', idsNoLayout.size, Array.from(idsNoLayout));
        
        let globalIndex = 0;
        const cardsProcessados = [];
        const idsProcessados = new Set(); // Evita processar o mesmo card duas vezes
        
        cards.forEach(card => {
            // Ignora se já é um grid-stack-item (já foi processado)
            if (card.classList.contains('grid-stack-item')) {
                return;
            }
            
            let cardId = card.getAttribute('data-card-id') || card.getAttribute('data-gs-id') || card.getAttribute('gs-id');
            
            if (!cardId) {
                const link = card.querySelector('a[href]');
                const title = card.querySelector('.card-label, h3, h4, h5, .card-title');
                if (link) {
                    const href = link.getAttribute('href');
                    cardId = 'card_' + href.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                } else if (title) {
                    cardId = 'card_' + slugify(title.textContent.trim());
                } else {
                    cardId = 'card_' + globalIndex + '_' + Date.now();
                }
                
                // Atribui o ID gerado
                card.setAttribute('data-card-id', cardId);
                if (!card.hasAttribute('data-gs-id')) {
                    card.setAttribute('data-gs-id', cardId);
                }
                console.log('ID gerado e atribuído ao card:', cardId);
            }
            
            // Evita processar o mesmo card duas vezes
            if (idsProcessados.has(cardId)) {
                console.log('⚠️ Card já foi processado, ignorando duplicata:', cardId);
                return;
            }
            idsProcessados.add(cardId);
            
            // Se há configuração salva E o card NÃO está nela, remove este card
            if (configuracao && configuracao.length > 0 && !idsNoLayout.has(cardId)) {
                console.log('❌ Card não está no layout salvo, removendo:', cardId);
                card.remove(); // Remove do DOM
                idsProcessados.delete(cardId); // Remove do set pois foi removido
                return;
            } else if (configuracao && configuracao.length > 0) {
                console.log('✅ Card está no layout salvo, processando:', cardId);
            }
            
            if (!cardTemplates.has(cardId)) {
                const templateClone = card.cloneNode(true);
                cardTemplates.set(cardId, templateClone);
            }
            
            const originalClasses = card.className;
            const configCard = configMap[cardId];
            if (configCard && configCard.visible === false) {
                card.style.display = 'none';
                return;
            }
            
            // Remove classes Bootstrap de grid
            card.classList.remove('col-xl-3', 'col-xl-4', 'col-xl-6', 'col-xl-8', 'col-xl-12', 'col-md-3', 'col-md-4', 'col-md-6', 'col-md-8', 'col-md-12');
            
            // Remove classes de margem/padding do Bootstrap
            const classesToRemove = Array.from(card.classList).filter(cls => 
                /^m[trblxy]?-/.test(cls) || 
                /^my-/.test(cls) || 
                /^mx-/.test(cls) ||
                /^p[trblxy]?-/.test(cls) ||
                /^py-/.test(cls) ||
                /^px-/.test(cls)
            );
            classesToRemove.forEach(cls => card.classList.remove(cls));
            
            // Limpa espaçamentos do card interno
            limparEspacamentosCard(card.querySelector('.card, a.card'));
            
            let width = 3;
            let height = 5;
            let posX = 0;
            let posY = 0;
            
            if (configCard) {
                width = parseInt(configCard.w) || 3;
                height = parseInt(configCard.h) || 5;
                posX = parseInt(configCard.x) || 0;
                posY = parseInt(configCard.y) || 0;
            } else {
                if (originalClasses.includes('col-xl-12')) width = 12;
                else if (originalClasses.includes('col-xl-8')) width = 8;
                else if (originalClasses.includes('col-xl-6')) width = 6;
                else if (originalClasses.includes('col-xl-4')) width = 4;
                else if (originalClasses.includes('col-xl-3')) width = 3;
                
                const cardElement = card.querySelector('.card');
                if (cardElement) {
                    const tempHeight = cardElement.offsetHeight || cardElement.scrollHeight || 350;
                    height = Math.max(5, Math.ceil(tempHeight / 70));
                }
            }
            
            card.classList.add('grid-stack-item');
            card.setAttribute('data-gs-id', cardId);
            card.setAttribute('gs-id', cardId);
            card.dataset.gsId = cardId;
            card.setAttribute('data-gs-x', posX);
            card.setAttribute('data-gs-y', posY);
            card.setAttribute('data-gs-w', width);
            card.setAttribute('data-gs-h', height);
            
            if (!card.querySelector('.grid-stack-item-content')) {
                const content = document.createElement('div');
                content.className = 'grid-stack-item-content';
                content.style.width = '100%';
                content.style.height = '100%';
                while (card.firstChild) {
                    content.appendChild(card.firstChild);
                }
                card.appendChild(content);
            }
            
            container.appendChild(card);
            cardsProcessados.push({ id: cardId, element: card });
            cardsAdicionados.add(cardId);
            
            globalIndex++;
        });
        
        console.log('Conversão concluída:', cardsProcessados.length, 'cards convertidos');
        console.log('Cards no grid:', Array.from(cardsAdicionados));
        return cardsProcessados;
    }
    
    // Inicializa GridStack
    function inicializarGrid(configuracao) {
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) {
            console.error('Container kt_content_container não encontrado');
            return;
        }
        
        console.log('Iniciando conversão de cards...');
        
        // Converte cards para grid items primeiro (passa configuração para aplicar posições)
        const cardsProcessados = converterCardsParaGrid(configuracao);
        
        if (cardsProcessados.length === 0) {
            console.error('Nenhum card foi processado na conversão');
            return;
        }
        
        console.log('Cards processados:', cardsProcessados.length);
        
        // Aguarda um pouco para garantir que a conversão foi feita
        setTimeout(() => {
            // Remove grid anterior se existir (NÃO DEVERIA EXISTIR, mas por segurança)
            if (grid) {
                console.warn('Grid ainda existe, destruindo...');
                try {
                    grid.destroy(false);
                } catch (e) {
                    console.warn('Erro ao destruir grid anterior:', e);
                }
                grid = null;
            }
            
            // Verifica se há grid items
            const gridItems = container.querySelectorAll('.grid-stack-item');
            if (gridItems.length === 0) {
                console.error('Nenhum grid item encontrado após conversão');
                return;
            }
            
            console.log('Inicializando GridStack com', gridItems.length, 'itens');
            console.log('editMode:', editMode);
            console.log('Configurações do grid:', {
                disableResize: !editMode,
                disableDrag: !editMode
            });
            
            // Adiciona classe grid-stack ao container
            container.classList.add('grid-stack');
            
            // Inicializa GridStack com configurações personalizadas
            // Em modo de edição, permite drag e resize
            const gridOptions = {
                column: 12,
                cellHeight: dashboardConfig.cellHeight,
                margin: dashboardConfig.margin,
                animate: dashboardConfig.animate,
                float: false,
                minRow: 1,
                acceptWidgets: true,
                removable: false
            };
            
            // Em modo de edição, habilita drag e resize
            if (editMode) {
                gridOptions.disableResize = false;
                gridOptions.disableDrag = false;
                gridOptions.resizable = {
                    handles: 'e, se, s, sw, w'
                };
                gridOptions.draggable = {
                    appendTo: 'body',
                    scroll: true
                };
            } else {
                gridOptions.disableResize = true;
                gridOptions.disableDrag = true;
                gridOptions.staticGrid = true;
            }
            
            grid = GridStack.init(gridOptions, container);
            
            if (!grid) {
                console.error('Falha ao inicializar GridStack');
                return;
            }
            
            console.log('✅ GridStack inicializado com sucesso');
            console.log('Grid opts:', {
                disableDrag: grid.opts.disableDrag,
                disableResize: grid.opts.disableResize,
                staticGrid: grid.opts.staticGrid
            });
            
            // Garante que todos os elementos existentes sejam widgets do GridStack
            // IMPORTANTE: O GridStack.init() automaticamente converte elementos .grid-stack-item
            // que já existem no DOM em widgets, mas precisamos garantir que isso aconteceu
            console.log('Elementos encontrados para converter em widgets:', gridItems.length);
            
            // Aguarda um pouco para o GridStack processar os elementos automaticamente
            setTimeout(() => {
                gridItems.forEach(item => {
                    try {
                        // Verifica se o elemento já é um widget
                        const node = grid.engine.nodes.find(n => n.el === item);
                        if (!node) {
                            // Se não foi convertido automaticamente, converte manualmente
                            console.log('Convertendo elemento em widget:', item.getAttribute('data-gs-id'));
                            grid.makeWidget(item);
                        } else {
                            console.log('Widget já existe:', item.getAttribute('data-gs-id'));
                        }
                        // Remove classes que podem desabilitar drag/resize
                        item.classList.remove('ui-draggable-disabled', 'ui-resizable-disabled');
                        
                        // Força a habilitação do drag e resize no widget
                        if (editMode && grid) {
                            try {
                                // Remove atributos que desabilitam
                                item.removeAttribute('gs-no-move');
                                item.removeAttribute('gs-no-resize');
                                item.removeAttribute('gs-locked');
                                
                                // Força a atualização do widget
                                if (node) {
                                    node.noMove = false;
                                    node.noResize = false;
                                    node.locked = false;
                                }
                            } catch (e) {
                                console.warn('Erro ao habilitar drag/resize no widget:', e);
                            }
                        }
                    } catch (err) {
                        console.warn('Erro ao criar widget:', err);
                    }
                });
            }, 100);
            
            // Aplica estilos do tema
            aplicarEstilosTema();
            
            // Se estiver em modo de edição, desabilita links dos cards
            if (editMode) {
                setTimeout(() => {
                    desabilitarLinksCards();
                }, 200);
            }
            
            // Aplica configuração se existir, senão usa layout padrão
            setTimeout(() => {
                if (configuracao && configuracao.length > 0) {
                    console.log('Carregando configuração salva:', configuracao);
                    aplicarConfiguracao(configuracao);
                } else {
                    console.log('Aplicando layout padrão');
                    aplicarLayoutPadrao();
                }
                
                // Garante que o grid está habilitado após aplicar a configuração
                if (editMode && grid) {
                    setTimeout(() => {
                        try {
                            // IMPORTANTE: Desabilita primeiro para garantir reset completo
                            grid.disable();
                            
                            // Força a atualização das opções do grid ANTES de reabilitar
                            if (grid.opts) {
                                grid.opts.disableDrag = false;
                                grid.opts.disableResize = false;
                                grid.opts.staticGrid = false;
                            }
                            
                            // Reabilita o grid com as novas opções
                            grid.enable();
                            
                            // Garante que todos os widgets estão habilitados
                            const allItems = container.querySelectorAll('.grid-stack-item');
                            allItems.forEach(item => {
                                // Remove classes que desabilitam drag/resize
                                item.classList.remove('ui-draggable-disabled', 'ui-resizable-disabled');
                                
                                // Remove atributos que desabilitam
                                item.removeAttribute('gs-no-move');
                                item.removeAttribute('gs-no-resize');
                                item.removeAttribute('gs-locked');
                                
                                // Garante que o widget está registrado
                                const node = grid.engine.nodes.find(n => n.el === item);
                                if (node) {
                                    // Força a habilitação no node
                                    node.noMove = false;
                                    node.noResize = false;
                                    node.locked = false;
                                } else {
                                    try {
                                        grid.makeWidget(item);
                                    } catch (err) {
                                        console.warn('Erro ao criar widget:', err);
                                    }
                                }
                            });
                            
                            // Força a re-inicialização do drag e resize para todos os widgets
                            if (grid.engine && grid.engine.nodes) {
                                grid.engine.nodes.forEach(node => {
                                    if (node.el) {
                                        // Remove classes de desabilitado
                                        node.el.classList.remove('ui-draggable-disabled', 'ui-resizable-disabled');
                                        // Remove atributos
                                        node.el.removeAttribute('gs-no-move');
                                        node.el.removeAttribute('gs-no-resize');
                                        node.el.removeAttribute('gs-locked');
                                        // Atualiza o node
                                        node.noMove = false;
                                        node.noResize = false;
                                        node.locked = false;
                                    }
                                });
                            }
                            
                            console.log('✅ GridStack habilitado para edição');
                            console.log('Widgets habilitados:', allItems.length);
                            console.log('Grid opts após habilitação:', {
                                disableDrag: grid.opts?.disableDrag,
                                disableResize: grid.opts?.disableResize,
                                staticGrid: grid.opts?.staticGrid
                            });
                            
                            // Força uma segunda passagem para garantir que os handlers estão ativos
                            setTimeout(() => {
                                // Desabilita e reabilita novamente para forçar recriação dos handlers
                                grid.disable();
                                setTimeout(() => {
                                    grid.enable();
                                    
                                    // Verifica se os widgets têm os handlers corretos
                                    allItems.forEach(item => {
                                        const hasDraggable = item.classList.contains('ui-draggable') || item.classList.contains('ui-draggable-handle');
                                        const hasResizable = item.classList.contains('ui-resizable');
                                        const node = grid.engine.nodes.find(n => n.el === item);
                                        console.log('Widget', item.getAttribute('data-gs-id'), {
                                            hasDraggable,
                                            hasResizable,
                                            disabled: item.classList.contains('ui-draggable-disabled') || item.classList.contains('ui-resizable-disabled'),
                                            nodeNoMove: node?.noMove,
                                            nodeNoResize: node?.noResize
                                        });
                                    });
                                    
                                    console.log('✅ GridStack reabilitado para garantir handlers');
                                    
                                    // Adiciona botões de remover após grid estar pronto
                                    if (editMode) {
                                        setTimeout(() => {
                                            adicionarBotoesRemover();
                                        }, 100);
                                    }
                                }, 100);
                            }, 200);
                        } catch (e) {
                            console.error('Erro ao habilitar grid:', e);
                        }
                    }, 300);
                }
            }, 300);
        }, 200);
    }
    
    // Busca node pelo ID
    function getNodeById(id) {
        if (!grid || !grid.engine || !grid.engine.nodes) return null;
        return grid.engine.nodes.find(node => node.id === id) || null;
    }
    
    // Aplica layout padrão aos cards existentes
    function aplicarLayoutPadrao() {
        if (!grid) {
            console.warn('Grid não inicializado, não é possível aplicar layout padrão');
            return;
        }
        
        const layoutPadrao = gerarLayoutPadrao();
        if (!layoutPadrao.length) {
            console.warn('Layout padrão vazio');
            return;
        }
        
        const idsPadrao = new Set(layoutPadrao.map(card => card.id));
        grid.batchUpdate();
        
        const nodesExistentes = grid.engine && grid.engine.nodes ? [...grid.engine.nodes] : [];
        nodesExistentes.forEach(node => {
            if (!node?.id || idsPadrao.has(node.id)) {
                return;
            }
            try {
                grid.removeWidget(node.el, true);
            } catch (err) {
                console.warn('Erro ao remover card fora do layout padrão', node.id, err);
            }
        });
        
        layoutPadrao.forEach(card => {
            const node = getNodeById(card.id);
            let element = node?.el || document.querySelector(`[data-gs-id="${card.id}"]`);
            
            if (!element) {
                const novoCard = montarCardAPartirDoTemplate(card.id);
                if (novoCard) {
                    try {
                        grid.addWidget(novoCard, {
                            id: card.id,
                            x: card.x,
                            y: card.y,
                            w: card.w,
                            h: card.h
                        });
                        element = novoCard;
                    } catch (err) {
                        console.warn('Erro ao recriar card', card.id, err);
                    }
                } else {
                    console.warn('Template não encontrado para card', card.id);
                }
            } else {
                try {
                    grid.update(element, {
                        x: card.x,
                        y: card.y,
                        w: card.w,
                        h: card.h
                    });
                } catch (err) {
                    console.warn('Erro ao aplicar layout padrão ao card', card.id, err);
                }
            }
        });
        grid.commit();
        
        cardsAdicionados.clear();
        idsPadrao.forEach(id => cardsAdicionados.add(id));
    }
    
    // Aplica configuração salva
    function aplicarConfiguracao(config) {
        if (!grid || !Array.isArray(config) || config.length === 0) {
            console.warn('Configuração inválida para aplicar');
            return;
        }
        
        grid.batchUpdate();
        config.forEach(card => {
            if (!card.id) return;
            const node = getNodeById(card.id);
            const element = node?.el || document.querySelector(`[data-gs-id="${card.id}"]`);
            if (element) {
                try {
                    grid.update(element, {
                        x: parseInt(card.x) || 0,
                        y: parseInt(card.y) || 0,
                        w: parseInt(card.w) || 3,
                        h: parseInt(card.h) || 3
                    });
                    // Garante que o widget não está desabilitado após atualização
                    if (editMode) {
                        element.classList.remove('ui-draggable-disabled', 'ui-resizable-disabled');
                    }
                } catch (err) {
                    console.warn('Erro ao aplicar configuração ao card', card.id, err);
                }
            }
        });
        grid.commit();
        
        // Após aplicar configuração, garante que o grid está habilitado se estiver em modo de edição
        if (editMode) {
            try {
                grid.enable();
            } catch (e) {
                console.warn('Erro ao reabilitar grid após aplicar configuração:', e);
            }
        }
    }
    
    // Limpa e reseta o container para estado inicial
    function resetarContainer() {
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) {
            console.error('Container não encontrado para resetar');
            return;
        }
        
        console.log('Resetando container para modo de edição...');
        console.log('Templates salvos:', cardTemplates.size);
        console.log('Cards adicionados (salvos):', cardsAdicionados.size, Array.from(cardsAdicionados));
        
        // Se não há layout salvo (cardsAdicionados vazio), preserva os cards do DOM
        // Isso acontece quando o usuário entra em modo de edição pela primeira vez
        if (cardsAdicionados.size === 0) {
            console.log('Nenhum card adicionado ainda - preservando cards do DOM');
            // Garante que todos os cards têm data-card-id antes de continuar
            garantirCardIdsBase();
            
            // Remove apenas classes do GridStack, mas mantém os cards
            container.classList.remove('grid-stack', 'grid-stack-rtl', 'grid-stack-animate');
            container.removeAttribute('gs-current-row');
            container.style.height = '';
            
            // Remove atributos do GridStack dos cards existentes
            container.querySelectorAll('[data-gs-id], [gs-id]').forEach(card => {
                card.removeAttribute('gs-x');
                card.removeAttribute('gs-y');
                card.removeAttribute('gs-w');
                card.removeAttribute('gs-h');
                card.removeAttribute('gs-id');
                card.removeAttribute('data-gs-x');
                card.removeAttribute('data-gs-y');
                card.removeAttribute('data-gs-w');
                card.removeAttribute('data-gs-h');
                card.classList.remove('grid-stack-item', 'ui-draggable', 'ui-resizable', 'ui-draggable-dragging', 'ui-resizable-resizing');
                card.style.position = '';
                card.style.left = '';
                card.style.top = '';
                card.style.width = '';
                card.style.height = '';
            });
            
            console.log('Cards do DOM preservados para conversão');
            return;
        }
        
        // Limpa o container apenas se temos layout salvo para restaurar
        container.innerHTML = '';
        
        // Restaura APENAS os cards que estão no cardsAdicionados (layout salvo)
        if (cardTemplates.size > 0 && cardsAdicionados.size > 0) {
            console.log('Restaurando apenas cards do layout salvo...');
            cardsAdicionados.forEach(cardId => {
                const template = cardTemplates.get(cardId);
                if (!template) {
                    console.warn('Template não encontrado para:', cardId);
                    return;
                }
                
                const cardClone = template.cloneNode(true);
            
            // Remove atributos do GridStack (posição e tamanho)
                cardClone.removeAttribute('gs-x');
                cardClone.removeAttribute('gs-y');
                cardClone.removeAttribute('gs-w');
                cardClone.removeAttribute('gs-h');
                cardClone.removeAttribute('gs-id');
                cardClone.removeAttribute('data-gs-x');
                cardClone.removeAttribute('data-gs-y');
                cardClone.removeAttribute('data-gs-w');
                cardClone.removeAttribute('data-gs-h');
                // NÃO remove data-gs-id e data-card-id - são necessários para identificação
            
            // Garante que o card tem data-card-id e data-gs-id corretos
                if (!cardClone.hasAttribute('data-card-id')) {
                    cardClone.setAttribute('data-card-id', cardId);
                }
                if (!cardClone.hasAttribute('data-gs-id')) {
                    cardClone.setAttribute('data-gs-id', cardId);
                }
            
            // Garante que os canvas dentro do card têm IDs únicos
                const canvasElements = cardClone.querySelectorAll('canvas[id]');
                canvasElements.forEach(canvas => {
                    const originalId = canvas.getAttribute('id');
                    // Mantém o ID original se não houver conflito, senão adiciona sufixo
                    const existingCanvas = document.getElementById(originalId);
                    if (existingCanvas && existingCanvas !== canvas) {
                        // Se já existe um canvas com esse ID, cria um novo ID único
                        canvas.setAttribute('id', originalId + '_' + Date.now());
                    }
                });
            
            // Remove classes do GridStack
                cardClone.classList.remove('grid-stack-item', 'ui-draggable', 'ui-resizable', 'ui-draggable-dragging', 'ui-resizable-resizing');
                
                // Limpa estilos inline do GridStack
                cardClone.style.position = '';
                cardClone.style.left = '';
                cardClone.style.top = '';
                cardClone.style.width = '';
                cardClone.style.height = '';
            
            // Remove wrapper grid-stack-item-content se existir
                // EXCETO para carrosséis, que precisam do wrapper
                if (!cardId.startsWith('card_carrossel_')) {
                    const content = cardClone.querySelector('.grid-stack-item-content');
            if (content) {
                while (content.firstChild) {
                            cardClone.appendChild(content.firstChild);
                }
                content.remove();
                    }
                }
                
                // Adiciona ao container
                container.appendChild(cardClone);
                console.log('Card restaurado:', cardId);
                
                // Se for carrossel, inicializa após adicionar ao DOM
                if (cardId.startsWith('card_carrossel_')) {
                    setTimeout(() => {
                        inicializarCarrossel(cardId);
                    }, 500);
                }
            });
            
            // Reinicializa gráficos após restaurar cards
            setTimeout(() => {
                console.log('🔄 Tentando reinicializar gráficos após restaurar cards...');
                console.log('Função reinicializarGraficos existe:', typeof reinicializarGraficos === 'function');
                console.log('Chart.js disponível:', typeof Chart !== 'undefined');
                if (typeof reinicializarGraficos === 'function') {
                    reinicializarGraficos();
                } else {
                    console.error('❌ Função reinicializarGraficos não encontrada!');
                }
            }, 800);
        } else if (cardTemplates.size === 0) {
            console.warn('Nenhum template disponível - cards serão preservados do DOM');
        } else if (cardsAdicionados.size === 0) {
            console.log('Nenhum card foi adicionado ainda (layout vazio)');
        }
        
        // Remove todas as classes relacionadas ao GridStack
        container.classList.remove('grid-stack', 'grid-stack-rtl', 'grid-stack-animate');
        container.removeAttribute('gs-current-row');
        
        // Limpa qualquer estilo inline que possa ter sido adicionado
        container.style.height = '';
        
        // NÃO limpa cardsAdicionados aqui! Precisamos dele para saber quais cards restaurar
        
        console.log('Container resetado com', cardsAdicionados.size, 'cards do layout salvo');
    }
    
    // Entra no modo de edição
    function entrarModoEdicao() {
        editMode = true;
        document.body.classList.add('dashboard-edit-mode');
        document.getElementById('btn_personalizar_dashboard').classList.add('d-none');
        document.getElementById('btn_salvar_dashboard').classList.remove('d-none');
        document.getElementById('btn_adicionar_cards').classList.remove('d-none');
        document.getElementById('btn_limpar_layout').classList.remove('d-none');
        document.getElementById('btn_restaurar_layout').classList.remove('d-none');
        document.getElementById('btn_configuracoes_dashboard').classList.remove('d-none');
        document.getElementById('btn_cancelar_dashboard').classList.remove('d-none');
        
        // Mostra botões que só aparecem em modo de edição
        document.querySelectorAll('.edit-mode-only').forEach(btn => {
            btn.style.display = 'inline-block';
        });
        
        // Atualiza botões dos carrosséis
        atualizarBotoesCarrossel();
        
        // Desabilita links dos cards no modo de edição
        desabilitarLinksCards();
        
        // Garante que o container está visível
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (container) {
            container.style.opacity = '1';
        }
        
        // IMPORTANTE: Salva templates dos cards ANTES de limpar o container
        // Isso garante que os cards atuais sejam preservados mesmo se não tiverem data-card-id
        console.log('Salvando templates dos cards antes de entrar em modo de edição...');
        const containerTemp = document.getElementById(GRID_CONTAINER_ID);
        if (containerTemp) {
            // Garante que todos os cards têm data-card-id
            garantirCardIdsBase();
            
            // Busca cards válidos: elementos com data-card-id dentro do container
            // IMPORTANTE: Busca em toda a estrutura, incluindo dentro de .row
            let cardsValidos = Array.from(containerTemp.querySelectorAll('[data-card-id]'));
            
            // Filtra apenas cards válidos (não wrappers e não filhos de outros cards)
            cardsValidos = cardsValidos.filter(el => {
                // Ignora wrappers
                const ehWrapper = el.classList.contains('row') || el.classList.contains('g-4') || el.id === 'row_stats_cards';
                if (ehWrapper) return false;
                
                // Verifica se não é filho de outro elemento com data-card-id (evita duplicatas)
                const paiComId = el.parentElement?.closest('[data-card-id]');
                if (paiComId && paiComId !== containerTemp) {
                    return false; // É filho de outro card, não é um card válido por si só
                }
                
                // Deve ter card dentro ou ser um card
                const temCard = el.querySelector('.card, a.card') || el.classList.contains('card') || el.classList.contains('a.card');
                return temCard !== null;
            });
            
            // Se ainda não encontrou, busca por colunas Bootstrap com cards (mesmo sem data-card-id)
            if (cardsValidos.length === 0) {
                const colunasComCard = Array.from(containerTemp.querySelectorAll('.row > div[class*="col-"]'));
                cardsValidos = colunasComCard.filter(col => {
                    const temCard = col.querySelector('.card, a.card');
                    const ehWrapper = col.classList.contains('row') || col.id === 'row_stats_cards';
                    return temCard && !ehWrapper;
                });
            }
            
            console.log('Cards válidos encontrados para salvar templates:', cardsValidos.length);
            
            // Salva templates apenas dos cards válidos
            cardsValidos.forEach(cardContainer => {
                // Gera ou obtém o ID do card
                let cardId = cardContainer.getAttribute('data-card-id') || 
                            cardContainer.getAttribute('data-gs-id') ||
                            cardContainer.getAttribute('gs-id');
                
                if (!cardId) {
                    // Tenta gerar ID baseado no conteúdo
                    const link = cardContainer.querySelector('a[href]');
                    const title = cardContainer.querySelector('.card-label, h3, h4, h5, .card-title');
                    if (link) {
                        const href = link.getAttribute('href');
                        cardId = 'card_' + href.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                    } else if (title) {
                        cardId = 'card_' + slugify(title.textContent.trim());
                    } else {
                        // Se não conseguir gerar ID válido, pula este elemento
                        console.warn('Não foi possível gerar ID para card, pulando:', cardContainer);
                        return;
                    }
                    
                    // Atribui o ID gerado
                    cardContainer.setAttribute('data-card-id', cardId);
                    if (!cardContainer.hasAttribute('data-gs-id')) {
                        cardContainer.setAttribute('data-gs-id', cardId);
                    }
                }
                
                // Ignora IDs inválidos (como card_row_stats_cards, card_auto_*, etc.)
                if (cardId.startsWith('card_row_') || cardId.startsWith('card_auto_') && !cardId.match(/card_auto_\d+$/)) {
                    console.warn('Ignorando card com ID inválido:', cardId);
                    return;
                }
                
                // Salva o template se ainda não foi salvo
                if (cardId && !cardTemplates.has(cardId)) {
                    const templateClone = cardContainer.cloneNode(true);
                    cardTemplates.set(cardId, templateClone);
                    console.log('Template salvo antes de entrar em modo de edição:', cardId);
                }
            });
        }
        
        // Adiciona botões de remover nos cards após entrar no modo de edição
        setTimeout(() => {
            adicionarBotoesRemover();
        }, 500);
        
        // Aguarda um pouco para garantir que o DOM está pronto
        setTimeout(() => {
            // Destrói grid estático se existir
            if (gridEstatico) {
                console.log('Destruindo grid estático para entrar em modo de edição');
                try {
                    gridEstatico.destroy(false);
                } catch (e) {
                    console.warn('Erro ao destruir grid estático:', e);
                }
                gridEstatico = null;
            }
            
            // Destrói grid editável se existir
            if (grid) {
                console.log('Destruindo grid existente para reiniciar');
                try {
                    grid.destroy(false);
                } catch (e) {
                    console.warn('Erro ao destruir grid:', e);
                }
                grid = null;
            }
            
            // Reseta container para estado inicial
            resetarContainer();
            
            console.log('Inicializando novo grid');
            carregarConfiguracao().then(config => {
                // Aplica estilos do tema antes de inicializar grid
                aplicarEstilosTema();
                
                // Se não há configuração, usa layout padrão
                if (!config || config.length === 0) {
                    inicializarGrid(null); // null força uso do layout padrão
                } else {
                    inicializarGrid(config);
                }
                
                // Atualiza botões dos carrosséis após inicializar o grid
                setTimeout(() => {
                    atualizarBotoesCarrossel();
                }, 800);
            });
        }, 200);
    }
    
    // Sai do modo de edição
    function sairModoEdicao(salvar = false) {
        if (salvar && grid) {
            salvarConfiguracao().then(() => {
                Swal.fire({
                    text: 'Configuração salva com sucesso!',
                    icon: 'success',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                }).then(() => {
                    // Recarrega a página para aplicar o layout salvo
                    location.reload();
                });
            }).catch(() => {
                Swal.fire({
                    text: 'Erro ao salvar configuração',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            });
        } else {
            // Cancelou - recarrega página para restaurar layout original
            Swal.fire({
                text: 'Deseja recarregar a página para restaurar o layout original?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, recarregar',
                cancelButtonText: 'Não',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                } else {
                    finalizarModoEdicao();
                }
            });
        }
    }
    
    // Finaliza modo de edição
    function finalizarModoEdicao() {
        editMode = false;
        document.body.classList.remove('dashboard-edit-mode');
        document.getElementById('btn_personalizar_dashboard').classList.remove('d-none');
        document.getElementById('btn_salvar_dashboard').classList.add('d-none');
        document.getElementById('btn_adicionar_cards').classList.add('d-none');
        document.getElementById('btn_limpar_layout').classList.add('d-none');
        document.getElementById('btn_restaurar_layout').classList.add('d-none');
        document.getElementById('btn_configuracoes_dashboard').classList.add('d-none');
        document.getElementById('btn_cancelar_dashboard').classList.add('d-none');
        
        // Oculta botões que só aparecem em modo de edição
        document.querySelectorAll('.edit-mode-only').forEach(btn => {
            btn.style.display = 'none';
        });
        
        // Atualiza botões dos carrosséis
        atualizarBotoesCarrossel();
        
        // Reabilita links dos cards ao sair do modo de edição
        habilitarLinksCards();
        
        // Remove botões de remover ao sair do modo de edição
        removerBotoesRemover();
        
        if (grid) {
            grid.disable();
        }
    }
    
    // Desabilita links dos cards no modo de edição
    function desabilitarLinksCards() {
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) return;
        
        // Encontra todos os links dentro dos grid items
        const links = container.querySelectorAll('.grid-stack-item a[href], .grid-stack-item a.card');
        
        links.forEach(link => {
            // Armazena o href original se ainda não foi armazenado
            if (!link.dataset.originalHref) {
                link.dataset.originalHref = link.getAttribute('href');
            }
            
            // Previne o comportamento padrão do link
            link.addEventListener('click', prevenirCliqueLink, true);
            link.addEventListener('mousedown', prevenirCliqueLink, true);
            
            // Adiciona classe para indicar que está desabilitado
            link.classList.add('link-desabilitado-edicao');
        });
    }
    
    // Reabilita links dos cards ao sair do modo de edição
    function habilitarLinksCards() {
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) return;
        
        // Encontra todos os links dentro dos grid items
        const links = container.querySelectorAll('.grid-stack-item a[href], .grid-stack-item a.card');
        
        links.forEach(link => {
            // Remove os event listeners
            link.removeEventListener('click', prevenirCliqueLink, true);
            link.removeEventListener('mousedown', prevenirCliqueLink, true);
            
            // Remove classe de desabilitado
            link.classList.remove('link-desabilitado-edicao');
        });
    }
    
    // Função para prevenir clique no link durante arraste
    let isDragging = false;
    let dragStartTime = 0;
    
    function prevenirCliqueLink(e) {
        // Se estamos arrastando (GridStack está ativo), previne o clique
        if (isDragging || document.body.classList.contains('dashboard-edit-mode')) {
            // Verifica se o elemento está sendo arrastado pelo GridStack
            const gridItem = e.target.closest('.grid-stack-item');
            if (gridItem && (gridItem.classList.contains('ui-draggable-dragging') || 
                             gridItem.classList.contains('ui-resizable-resizing'))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Se passou menos de 300ms desde o mousedown, provavelmente é um arraste
            const timeSinceMouseDown = Date.now() - dragStartTime;
            if (timeSinceMouseDown < 300) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }
    }
    
    // Detecta quando o arraste começa
    document.addEventListener('mousedown', function(e) {
        if (editMode && e.target.closest('.grid-stack-item')) {
            dragStartTime = Date.now();
            isDragging = false;
            
            // Detecta movimento do mouse para determinar se é arraste
            const onMouseMove = function() {
                isDragging = true;
            };
            
            const onMouseUp = function() {
                setTimeout(() => {
                    isDragging = false;
                }, 100);
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            };
            
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        }
    }, true);
    
    // Adiciona botões de remover nos cards no modo de edição
    function adicionarBotoesRemover() {
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) return;
        
        const gridItems = container.querySelectorAll('.grid-stack-item');
        
        gridItems.forEach(item => {
            // Verifica se já tem botão de remover
            if (item.querySelector('.btn-remover-card')) {
                return;
            }
            
            // Cria o botão de remover
            const btnRemover = document.createElement('button');
            btnRemover.className = 'btn btn-remover-card';
            btnRemover.type = 'button';
            btnRemover.title = 'Remover card';
            btnRemover.innerHTML = '<i class="ki-duotone ki-trash fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>';
            
            // Adiciona event listener para remover o card
            btnRemover.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                removerCard(item);
            });
            
            // Adiciona o botão ao card
            item.appendChild(btnRemover);
        });
    }
    
    // Remove botões de remover dos cards
    function removerBotoesRemover() {
        const container = document.getElementById(GRID_CONTAINER_ID);
        if (!container) return;
        
        const botoesRemover = container.querySelectorAll('.btn-remover-card');
        botoesRemover.forEach(btn => btn.remove());
    }
    
    // Remove um card individual
    function removerCard(cardElement) {
        const cardId = cardElement.getAttribute('data-gs-id') || cardElement.getAttribute('data-card-id');
        
        if (!cardId) {
            console.warn('Card sem ID, não é possível remover');
            return;
        }
        
        Swal.fire({
            text: 'Deseja remover este card do dashboard?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-light'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Remove do grid
                if (grid) {
                    try {
                        grid.removeWidget(cardElement, true);
                    } catch (e) {
                        console.warn('Erro ao remover widget do grid:', e);
                        cardElement.remove();
                    }
                } else {
                    cardElement.remove();
                }
                
                // Remove do conjunto de cards adicionados
                cardsAdicionados.delete(cardId);
                
                // Remove configurações de carrossel se for um carrossel
                if (cardId.startsWith('card_carrossel_')) {
                    if (carrosselInstances[cardId]) {
                        carrosselInstances[cardId].destroy(true, true);
                        delete carrosselInstances[cardId];
                    }
                    delete carrosselConfigs[cardId];
                }
                
                console.log('Card removido:', cardId);
                
                // Atualiza lista de cards disponíveis
                setTimeout(() => {
                    carregarListaCardsDisponiveis();
                }, 300);
            }
        });
    }
    
    // Event listeners
    document.getElementById('btn_personalizar_dashboard')?.addEventListener('click', function() {
        definirCardsDisponiveis();
        carregarConfiguracao().then(config => {
            originalConfig = config;
            entrarModoEdicao();
        });
    });
    
    document.getElementById('btn_salvar_dashboard')?.addEventListener('click', function() {
        sairModoEdicao(true);
    });
    
    document.getElementById('btn_cancelar_dashboard')?.addEventListener('click', function() {
        sairModoEdicao(false);
    });
    
    document.getElementById('btn_limpar_layout')?.addEventListener('click', function() {
        limparLayout();
    });
    
    document.getElementById('btn_restaurar_layout')?.addEventListener('click', function() {
        restaurarLayout();
    });
    
    document.getElementById('btn_adicionar_cards')?.addEventListener('click', function() {
        definirCardsDisponiveis();
        carregarListaCardsDisponiveis();
        const modal = new bootstrap.Modal(document.getElementById('modal_adicionar_cards'));
        modal.show();
    });
    
    // Busca de cards no modal
    document.getElementById('buscar_cards')?.addEventListener('input', function() {
        carregarListaCardsDisponiveis();
    });
    
    // Atualiza lista quando modal é aberto
    document.getElementById('modal_adicionar_cards')?.addEventListener('shown.bs.modal', function() {
        definirCardsDisponiveis();
        carregarListaCardsDisponiveis();
    });
    
    // Event listeners para Configurações do Dashboard
    document.getElementById('btn_configuracoes_dashboard')?.addEventListener('click', function() {
        abrirModalConfiguracoes();
    });
    
    // Abre modal de configurações
    function abrirModalConfiguracoes() {
        // Preenche valores atuais
        document.getElementById('config_margin').value = dashboardConfig.margin;
        document.getElementById('config_margin_value').textContent = dashboardConfig.margin + 'px';
        document.getElementById('config_cell_height').value = dashboardConfig.cellHeight;
        document.getElementById('config_cell_height_value').textContent = dashboardConfig.cellHeight + 'px';
        document.getElementById('densidade_' + dashboardConfig.densidade).checked = true;
        document.getElementById('tema_' + dashboardConfig.temaGrid).checked = true;
        document.getElementById('config_animate').checked = dashboardConfig.animate;
        
        const modal = new bootstrap.Modal(document.getElementById('modal_configuracoes_dashboard'));
        modal.show();
    }
    
    // Atualiza valor ao mover slider de margem
    document.getElementById('config_margin')?.addEventListener('input', function() {
        document.getElementById('config_margin_value').textContent = this.value + 'px';
    });
    
    // Atualiza valor ao mover slider de altura
    document.getElementById('config_cell_height')?.addEventListener('input', function() {
        document.getElementById('config_cell_height_value').textContent = this.value + 'px';
    });
    
    // Quando seleciona densidade, atualiza sliders automaticamente
    document.querySelectorAll('input[name="densidade"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const densidades = {
                compacto: { margin: 8, cellHeight: 60 },
                padrao: { margin: 16, cellHeight: 70 },
                espacado: { margin: 24, cellHeight: 80 }
            };
            
            const densidade = densidades[this.value];
            if (densidade) {
                document.getElementById('config_margin').value = densidade.margin;
                document.getElementById('config_margin_value').textContent = densidade.margin + 'px';
                document.getElementById('config_cell_height').value = densidade.cellHeight;
                document.getElementById('config_cell_height_value').textContent = densidade.cellHeight + 'px';
            }
        });
    });
    
    // Aplica configurações
    document.getElementById('btn_aplicar_config')?.addEventListener('click', function() {
        const btn = this;
        const indicator = btn.querySelector('.indicator-label');
        const progress = btn.querySelector('.indicator-progress');
        
        // Captura valores do formulário
        dashboardConfig.margin = parseInt(document.getElementById('config_margin').value);
        dashboardConfig.cellHeight = parseInt(document.getElementById('config_cell_height').value);
        dashboardConfig.densidade = document.querySelector('input[name="densidade"]:checked').value;
        dashboardConfig.temaGrid = document.querySelector('input[name="tema_grid"]:checked').value;
        dashboardConfig.animate = document.getElementById('config_animate').checked;
        
        btn.setAttribute('data-kt-indicator', 'on');
        indicator.style.display = 'none';
        progress.style.display = 'inline-block';
        
        // Aplica estilos do tema e padding interno
        aplicarEstilosTema();
        
        // Reinicia o grid com novas configurações
        if (grid) {
            grid.cellHeight(dashboardConfig.cellHeight);
            grid.margin(dashboardConfig.margin);
            grid.opts.animate = dashboardConfig.animate;
            
            // Força re-render do grid para aplicar mudanças
            setTimeout(() => {
                grid.batchUpdate();
                grid.commit();
            }, 100);
        }
        
        // Salva configurações (junto com o layout)
        salvarConfiguracao()
            .then(() => {
                btn.removeAttribute('data-kt-indicator');
                indicator.style.display = 'inline-block';
                progress.style.display = 'none';
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('modal_configuracoes_dashboard'));
                modal.hide();
                
                Swal.fire({
                    text: 'Configurações aplicadas com sucesso!',
                    icon: 'success',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            })
            .catch(error => {
                btn.removeAttribute('data-kt-indicator');
                indicator.style.display = 'inline-block';
                progress.style.display = 'none';
                
                Swal.fire({
                    text: 'Erro ao salvar configurações: ' + error.message,
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            });
    });
    
    // Restaurar configurações padrão
    document.getElementById('btn_restaurar_config')?.addEventListener('click', function() {
        Swal.fire({
            text: 'Deseja restaurar as configurações padrão do dashboard?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, restaurar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-light'
            }
        }).then(result => {
            if (result.isConfirmed) {
                // Restaura valores padrão
                dashboardConfig = {
                    margin: 16,
                    cellHeight: 70,
                    densidade: 'padrao',
                    temaGrid: 'azul',
                    animate: true
                };
                
                // Atualiza formulário
                document.getElementById('config_margin').value = 16;
                document.getElementById('config_margin_value').textContent = '16px';
                document.getElementById('config_cell_height').value = 70;
                document.getElementById('config_cell_height_value').textContent = '70px';
                document.getElementById('densidade_padrao').checked = true;
                document.getElementById('tema_azul').checked = true;
                document.getElementById('config_animate').checked = true;
                
                Swal.fire({
                    text: 'Configurações restauradas! Clique em "Aplicar" para salvar.',
                    icon: 'info',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }
        });
    });
    
    // Inicializa cards disponíveis
    definirCardsDisponiveis();
    
    // Carrega configurações e aplica layout salvo ao iniciar
    carregarConfiguracoesDashboard().then(resultado => {
        if (resultado.cards && resultado.cards.length > 0) {
            aplicarLayoutSalvo(resultado.cards);
        } else {
            // Se não há layout salvo, torna o dashboard visível (usa layout padrão)
            const container = document.getElementById(GRID_CONTAINER_ID);
            if (container) {
                setTimeout(() => {
                    container.style.opacity = '1';
                }, 100);
            }
        }
    });
    
    // Função para aplicar layout salvo ao carregar a página (fora do modo de edição)
    function aplicarLayoutSalvo(layoutSalvo) {
        console.log('Aplicando layout salvo:', layoutSalvo);
        
        // Verifica se há layout para aplicar
        if (!layoutSalvo || layoutSalvo.length === 0) {
            console.log('Nenhum layout salvo para aplicar');
            return;
        }
        
        // Aguarda um pouco para garantir que o DOM está completamente carregado
        setTimeout(() => {
            // Obtém a área de cards do dashboard usando o ID correto
            const dashboardCards = document.getElementById(GRID_CONTAINER_ID);
            if (!dashboardCards) {
                console.error('Área de cards não encontrada:', GRID_CONTAINER_ID);
                console.log('O dashboard personalizável pode não estar disponível para este usuário');
                return;
            }
            
            console.log('Container encontrado, aplicando layout...');
            
            // Salva os templates dos cards ANTES de limpar o container
            dashboardCards.querySelectorAll('[data-card-id]').forEach(cardElement => {
                const cardId = cardElement.getAttribute('data-card-id');
                if (cardId && !cardTemplates.has(cardId)) {
                    const templateClone = cardElement.cloneNode(true);
                    cardTemplates.set(cardId, templateClone);
                    console.log('Template salvo:', cardId);
                }
            });
            
            // IMPORTANTE: Também busca cards que podem estar em blocos condicionais PHP
            // que não foram renderizados no DOM inicial, mas estão no layout salvo
            const cardsNoLayout = layoutSalvo.map(card => card.id).filter(id => !cardTemplates.has(id));
            console.log('Cards no layout que não têm template:', cardsNoLayout);
            
            // Tenta encontrar esses cards no DOM mesmo que não estejam visíveis
            cardsNoLayout.forEach(cardId => {
                // Busca em todo o documento, não apenas no container
                const cardElement = document.querySelector(`[data-card-id="${cardId}"]`) || 
                                  document.querySelector(`[data-gs-id="${cardId}"]`);
                if (cardElement && !cardTemplates.has(cardId)) {
                    const templateClone = cardElement.cloneNode(true);
                    cardTemplates.set(cardId, templateClone);
                    console.log('Template encontrado e salvo (card condicional):', cardId);
                } else if (!cardElement) {
                    console.warn('⚠️ Card não encontrado no DOM (pode estar em bloco condicional PHP):', cardId);
                }
            });
            
            // Limpa todos os cards existentes
            dashboardCards.innerHTML = '';
            
            // Remove classes Bootstrap do container
            dashboardCards.classList.remove('row', 'g-4');
            
            // Adiciona classe do GridStack
            dashboardCards.classList.add('grid-stack');
            
            // Oculta todos os cards disponíveis
            document.querySelectorAll('.card-disponivel').forEach(cardDisp => {
                cardDisp.style.display = 'none';
            });
            
            // Aplica os cards conforme o layout salvo
            layoutSalvo.forEach(cardInfo => {
                if (!cardInfo.visible) return; // Pula cards invisíveis
                
                console.log('Aplicando card:', cardInfo.id);
                
                // Cria o card a partir do ID (montarCardAPartirDoTemplate espera o ID, não o objeto)
                const novoCard = montarCardAPartirDoTemplate(cardInfo.id);
                if (!novoCard) {
                    console.warn('Não foi possível montar card:', cardInfo.id);
                    return;
                }
                
                // Define posição e tamanho do GridStack
                novoCard.setAttribute('gs-x', cardInfo.x);
                novoCard.setAttribute('gs-y', cardInfo.y);
                novoCard.setAttribute('gs-w', cardInfo.w);
                novoCard.setAttribute('gs-h', cardInfo.h);
                novoCard.setAttribute('gs-id', cardInfo.id);
                
                // Adiciona ao dashboard
                dashboardCards.appendChild(novoCard);
                console.log('Card adicionado:', cardInfo.id);
            });
        
            // Inicializa GridStack em modo somente leitura (não editável)
            if (window.GridStack) {
                // Destrói grid estático anterior se existir
                if (gridEstatico) {
                    try {
                        gridEstatico.destroy(false);
                    } catch (e) {
                        console.warn('Erro ao destruir grid estático anterior:', e);
                    }
                    gridEstatico = null;
                }
                
                gridEstatico = GridStack.init({
                    margin: dashboardConfig.margin,
                    cellHeight: dashboardConfig.cellHeight,
                    animate: false,
                    float: false,
                    disableDrag: true,   // Desabilita arrastar
                    disableResize: true, // Desabilita redimensionar
                    staticGrid: true     // Grid estático (não editável)
                }, dashboardCards);
                
                console.log('Layout salvo aplicado com sucesso');
                
                // Torna o dashboard visível após aplicar o layout
                dashboardCards.style.opacity = '1';
            } else {
                // Se não tem GridStack, torna visível mesmo assim
                dashboardCards.style.opacity = '1';
            }
        }, 300); // Aguarda 300ms para garantir que o DOM está pronto
    }
    
    // ========== FUNÇÕES DO CARROSSEL DE CARDS ==========
    
    let carrosselConfigs = {}; // Armazena configurações de cada carrossel
    let carrosselInstances = {}; // Armazena instâncias do Swiper
    
    // Cria HTML do card de carrossel
    function criarCardCarrossel(cardId) {
        const config = carrosselConfigs[cardId] || {
            slidesPerView: 3,
            speed: 500,
            autoplay: true,
            autoplayDelay: 3000,
            loop: true,
            pagination: true,
            cards: []
        };
        
        const carrosselId = 'swiper_' + cardId.replace(/[^a-z0-9]/gi, '_');
        
        let html = `
            <div class="card h-100">
                <div class="card-header pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">🎠 Carrossel de Cards</span>
                        <span class="text-muted fw-semibold fs-7">${config.cards.length} card(s)</span>
                    </h3>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-icon btn-light-primary carousel-config-btn edit-mode-only" onclick="configurarCarrossel('${cardId}')" style="display: ${editMode ? 'inline-block' : 'none'};">
                            <i class="ki-duotone ki-setting-3 fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                    </div>
                </div>
                <div class="card-body carousel-card-container">
        `;
        
        if (config.cards.length === 0) {
            html += `
                <div class="carousel-empty-state">
                    <i class="ki-duotone ki-slider fs-3x">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <p class="text-gray-600 fw-bold">Nenhum card adicionado</p>
                    <small class="text-muted">Configure o carrossel para adicionar cards</small>
                </div>
            `;
        } else {
            html += `
                <div class="swiper swiper-carousel-cards" id="${carrosselId}">
                    <div class="swiper-wrapper">
            `;
            
            config.cards.forEach(card => {
                if (card.tipo === 'existente') {
                    // Card existente do dashboard
                    // Tenta buscar do cardTemplates primeiro, depois do DOM
                    let cardOriginal = null;
                    if (cardTemplates.has(card.cardId)) {
                        cardOriginal = cardTemplates.get(card.cardId);
                        console.log('Card encontrado no cardTemplates:', card.cardId);
                    } else {
                        cardOriginal = document.querySelector(`[data-card-id="${card.cardId}"]`);
                        if (cardOriginal) {
                            console.log('Card encontrado no DOM:', card.cardId);
                        }
                    }
                    
                    let cardHtml = '';
                    
                    if (cardOriginal) {
                        // Clona o card original
                        const clone = cardOriginal.cloneNode(true);
                        // Remove atributos do grid
                        clone.removeAttribute('data-gs-x');
                        clone.removeAttribute('data-gs-y');
                        clone.removeAttribute('data-gs-w');
                        clone.removeAttribute('data-gs-h');
                        clone.classList.remove('grid-stack-item');
                        
                        // Pega apenas o conteúdo do card
                        const cardContent = clone.querySelector('.card, a.card');
                        if (cardContent) {
                            cardHtml = cardContent.outerHTML;
                        } else {
                            cardHtml = clone.innerHTML;
                        }
                    } else {
                        // Fallback se card não for encontrado
                        console.warn('Card não encontrado:', card.cardId);
                        cardHtml = `
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="ki-duotone ${card.icone || 'ki-information'} fs-3x text-primary mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <h5 class="fw-bold">${card.nome || 'Card não encontrado'}</h5>
                                    <p class="text-muted small">${card.descricao || 'Este card não está disponível'}</p>
                                </div>
                            </div>
                        `;
                    }
                    
                    html += `
                        <div class="swiper-slide">
                            <div class="carousel-card-wrapper" style="height: 100%;">
                                ${cardHtml}
                            </div>
                        </div>
                    `;
                } else {
                    // Card personalizado (mini-card)
                    const corClass = `bg-${card.cor || 'primary'}`;
                    html += `
                        <div class="swiper-slide">
                            <div class="mini-card ${corClass} text-white">
                                ${card.icone ? `
                                    <i class="ki-duotone ${card.icone} fs-2tx mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                ` : ''}
                                ${card.valor ? `<div class="fs-2x fw-bold mb-2">${card.valor}</div>` : ''}
                                <div class="fw-bold fs-5 mb-1">${card.titulo}</div>
                                ${card.descricao ? `<div class="opacity-75 fs-7">${card.descricao}</div>` : ''}
                            </div>
                        </div>
                    `;
                }
            });
            
            html += `
                    </div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                    ${config.pagination ? '<div class="swiper-pagination"></div>' : ''}
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
        
        return html;
    }
    
    // Atualiza visibilidade dos botões de configuração dos carrosséis
    function atualizarBotoesCarrossel() {
        document.querySelectorAll('.carousel-config-btn').forEach(btn => {
            if (editMode) {
                btn.style.display = 'inline-block';
            } else {
                btn.style.display = 'none';
            }
        });
    }
    
    // Inicializa carrossel Swiper
    function inicializarCarrossel(cardId) {
        console.log('Inicializando carrossel:', cardId);
        const config = carrosselConfigs[cardId];
        if (!config) {
            console.warn('Configuração do carrossel não encontrada:', cardId);
            return;
        }
        if (config.cards.length === 0) {
            console.warn('Carrossel sem cards:', cardId);
            return;
        }
        
        const carrosselId = 'swiper_' + cardId.replace(/[^a-z0-9]/gi, '_');
        const elemento = document.getElementById(carrosselId);
        
        if (!elemento) {
            console.warn('Elemento do carrossel não encontrado no DOM:', carrosselId);
            return;
        }
        
        console.log('Carrossel encontrado, criando Swiper...');
        
        // Destroi instância anterior se existir
        if (carrosselInstances[cardId]) {
            carrosselInstances[cardId].destroy(true, true);
        }
        
        // Detecta se há cards existentes (que são maiores)
        const temCardsExistentes = config.cards.some(card => card.tipo === 'existente');
        const slidesPerViewPadrao = temCardsExistentes ? 
            Math.min(2, parseInt(config.slidesPerView) || 2) : 
            parseInt(config.slidesPerView) || 3;
        
        const swiperConfig = {
            slidesPerView: slidesPerViewPadrao,
            spaceBetween: 20,
            speed: parseInt(config.speed) || 500,
            loop: config.loop !== false && config.cards.length > slidesPerViewPadrao,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            // Desabilita drag/swipe no modo de edição para não interferir com GridStack
            allowTouchMove: !editMode,
            allowSlideNext: !editMode,
            allowSlidePrev: !editMode,
            breakpoints: {
                320: { slidesPerView: 1, spaceBetween: 15 },
                768: { 
                    slidesPerView: temCardsExistentes ? 1 : 2,
                    spaceBetween: 20
                },
                1024: { 
                    slidesPerView: temCardsExistentes ? 
                        Math.min(2, slidesPerViewPadrao) : 
                        Math.min(parseInt(config.slidesPerView) || 3, 3),
                    spaceBetween: 20
                }
            }
        };
        
        if (config.pagination !== false) {
            swiperConfig.pagination = {
                el: '.swiper-pagination',
                clickable: true
            };
        }
        
        if (config.autoplay) {
            swiperConfig.autoplay = {
                delay: parseInt(config.autoplayDelay) || 3000,
                disableOnInteraction: false
            };
        }
        
        carrosselInstances[cardId] = new Swiper('#' + carrosselId, swiperConfig);
        console.log('✅ Carrossel inicializado com sucesso:', cardId, `(${config.cards.length} cards)`);
        
        // Atualiza visibilidade do botão de configuração
        atualizarBotoesCarrossel();
    }
    
    // Abre modal para configurar carrossel
    window.configurarCarrossel = function(cardId) {
        document.getElementById('carousel_card_id').value = cardId;
        
        const config = carrosselConfigs[cardId] || {
            slidesPerView: 3,
            speed: 500,
            autoplay: true,
            autoplayDelay: 3000,
            loop: true,
            pagination: true,
            cards: []
        };
        
        // Preenche formulário
        document.getElementById('carousel_slides_per_view').value = config.slidesPerView;
        document.getElementById('carousel_speed').value = config.speed;
        document.getElementById('carousel_autoplay').checked = config.autoplay;
        document.getElementById('carousel_autoplay_delay').value = config.autoplayDelay;
        document.getElementById('carousel_loop').checked = config.loop;
        document.getElementById('carousel_pagination').checked = config.pagination;
        
        // Renderiza lista de cards
        renderizarCardsCarrossel(config.cards);
        
        const modal = new bootstrap.Modal(document.getElementById('modal_configurar_carrossel'));
        modal.show();
    };
    
    // Renderiza lista de cards do carrossel
    function renderizarCardsCarrossel(cards) {
        const container = document.getElementById('carousel_cards_list');
        
        if (!cards || cards.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-10">
                    <i class="ki-duotone ki-slider fs-3x text-gray-400 mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <p>Nenhum card adicionado ainda</p>
                    <small>Clique em "Adicionar Card" para começar</small>
                </div>
            `;
            return;
        }
        
        let html = '<div class="d-flex flex-column gap-3">';
        cards.forEach((card, index) => {
            if (card.tipo === 'existente') {
                // Card existente
                html += `
                    <div class="card card-dashed border-2 border-success">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <span class="badge badge-success">Card Existente</span>
                                        ${card.icone ? `<i class="ki-duotone ${card.icone} fs-2x text-success"><span class="path1"></span><span class="path2"></span></i>` : ''}
                                        <div>
                                            <h5 class="mb-0">${card.nome}</h5>
                                        </div>
                                    </div>
                                    ${card.descricao ? `<p class="text-muted small mb-0">${card.descricao}</p>` : ''}
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removerCardCarrossel(${index})">
                                        <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Card personalizado
                html += `
                    <div class="card card-dashed border-2 border-primary">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <span class="badge badge-primary">Card Personalizado</span>
                                        ${card.icone ? `<i class="ki-duotone ${card.icone} fs-2x text-${card.cor}"><span class="path1"></span><span class="path2"></span></i>` : ''}
                                        <div>
                                            <h5 class="mb-0">${card.titulo}</h5>
                                            ${card.valor ? `<div class="text-${card.cor} fw-bold">${card.valor}</div>` : ''}
                                        </div>
                                    </div>
                                    ${card.descricao ? `<p class="text-muted small mb-0">${card.descricao}</p>` : ''}
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removerCardCarrossel(${index})">
                                        <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
        html += '</div>';
        container.innerHTML = html;
    }
    
    // Adicionar card ao carrossel
    document.getElementById('btn_adicionar_card_carrossel')?.addEventListener('click', function() {
        // Reseta para modo personalizado
        document.getElementById('tipo_card_personalizado').checked = true;
        document.getElementById('form_card_carrossel').style.display = 'block';
        document.getElementById('selecao_card_existente').style.display = 'none';
        
        // Carrega cards disponíveis
        carregarCardsExistentesParaCarrossel();
        
        const modal = new bootstrap.Modal(document.getElementById('modal_adicionar_card_carrossel'));
        modal.show();
    });
    
    // Toggle entre card personalizado e existente
    document.querySelectorAll('input[name="tipo_card_carrossel"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'personalizado') {
                document.getElementById('form_card_carrossel').style.display = 'block';
                document.getElementById('selecao_card_existente').style.display = 'none';
            } else {
                document.getElementById('form_card_carrossel').style.display = 'none';
                document.getElementById('selecao_card_existente').style.display = 'block';
            }
        });
    });
    
    // Carrega cards existentes para seleção
    function carregarCardsExistentesParaCarrossel() {
        const container = document.getElementById('lista_cards_existentes');
        const filtro = document.getElementById('buscar_cards_existentes')?.value.toLowerCase() || '';
        
        // Filtra cards (exclui o próprio carrossel)
        const cardsFiltrados = cardsDisponiveis.filter(card => {
            // Não permite adicionar carrossel dentro de carrossel
            if (card.id === 'card_carrossel' || card.tipo === 'carrossel') {
                return false;
            }
            // Verifica permissão do card
            if (card.id && cardPermissions[card.id] === false) {
                return false;
            }
            // Aplica filtro de busca
            return card.nome.toLowerCase().includes(filtro) || 
                   card.descricao.toLowerCase().includes(filtro);
        });
        
        container.innerHTML = '';
        
        if (cardsFiltrados.length === 0) {
            container.innerHTML = '<div class="col-12 text-center text-muted py-5">Nenhum card encontrado</div>';
            return;
        }
        
        cardsFiltrados.forEach(card => {
            const cardHtml = `
                <div class="col-md-6">
                    <div class="card card-hoverable border-2 border-dashed border-gray-300 cursor-pointer h-100" 
                         data-card-existente='${JSON.stringify(card)}'
                         onclick="selecionarCardExistente(this)">
                        <div class="card-body p-4 text-center">
                            <i class="ki-duotone ${card.icone} fs-3x text-primary mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <h5 class="fw-bold mb-2">${card.nome}</h5>
                            <p class="text-muted small mb-0">${card.descricao}</p>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', cardHtml);
        });
    }
    
    // Busca de cards existentes
    document.getElementById('buscar_cards_existentes')?.addEventListener('input', function() {
        carregarCardsExistentesParaCarrossel();
    });
    
    // Seleciona card existente
    let cardExistenteSelecionado = null;
    window.selecionarCardExistente = function(elemento) {
        // Remove seleção anterior
        document.querySelectorAll('[data-card-existente]').forEach(el => {
            el.classList.remove('border-primary', 'bg-light-primary');
            el.classList.add('border-gray-300');
        });
        
        // Adiciona seleção atual
        elemento.classList.remove('border-gray-300');
        elemento.classList.add('border-primary', 'bg-light-primary');
        
        cardExistenteSelecionado = JSON.parse(elemento.getAttribute('data-card-existente'));
    };
    
    // Confirmar adição de card
    document.getElementById('btn_confirmar_card_carrossel')?.addEventListener('click', function() {
        const tipoCard = document.querySelector('input[name="tipo_card_carrossel"]:checked').value;
        const cardId = document.getElementById('carousel_card_id').value;
        
        if (!carrosselConfigs[cardId]) {
            carrosselConfigs[cardId] = { cards: [] };
        }
        
        let novoCard = null;
        
        if (tipoCard === 'personalizado') {
            // Card personalizado
            const form = document.getElementById('form_card_carrossel');
            const formData = new FormData(form);
            
            if (!formData.get('titulo')) {
                Swal.fire({
                    text: 'Preencha o título do card!',
                    icon: 'warning',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
                return;
            }
            
            novoCard = {
                tipo: 'personalizado',
                titulo: formData.get('titulo'),
                valor: formData.get('valor'),
                icone: formData.get('icone'),
                cor: formData.get('cor'),
                descricao: formData.get('descricao'),
                link: formData.get('link')
            };
            
            form.reset();
        } else {
            // Card existente
            if (!cardExistenteSelecionado) {
                Swal.fire({
                    text: 'Selecione um card!',
                    icon: 'warning',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
                return;
            }
            
            novoCard = {
                tipo: 'existente',
                cardId: cardExistenteSelecionado.id,
                nome: cardExistenteSelecionado.nome,
                descricao: cardExistenteSelecionado.descricao,
                icone: cardExistenteSelecionado.icone,
                w: cardExistenteSelecionado.w || 6,
                h: cardExistenteSelecionado.h || 4
            };
            
            cardExistenteSelecionado = null;
        }
        
        carrosselConfigs[cardId].cards.push(novoCard);
        renderizarCardsCarrossel(carrosselConfigs[cardId].cards);
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('modal_adicionar_card_carrossel'));
        modal.hide();
    });
    
    // Remover card do carrossel
    window.removerCardCarrossel = function(index) {
        const cardId = document.getElementById('carousel_card_id').value;
        if (carrosselConfigs[cardId] && carrosselConfigs[cardId].cards) {
            carrosselConfigs[cardId].cards.splice(index, 1);
            renderizarCardsCarrossel(carrosselConfigs[cardId].cards);
        }
    };
    
    // Salvar configurações do carrossel
    document.getElementById('btn_salvar_carrossel')?.addEventListener('click', function() {
        const cardId = document.getElementById('carousel_card_id').value;
        
        if (!carrosselConfigs[cardId]) {
            carrosselConfigs[cardId] = {};
        }
        
        carrosselConfigs[cardId].slidesPerView = document.getElementById('carousel_slides_per_view').value;
        carrosselConfigs[cardId].speed = document.getElementById('carousel_speed').value;
        carrosselConfigs[cardId].autoplay = document.getElementById('carousel_autoplay').checked;
        carrosselConfigs[cardId].autoplayDelay = document.getElementById('carousel_autoplay_delay').value;
        carrosselConfigs[cardId].loop = document.getElementById('carousel_loop').checked;
        carrosselConfigs[cardId].pagination = document.getElementById('carousel_pagination').checked;
        
        // Atualiza o card no grid
        const elemento = document.querySelector(`[data-gs-id="${cardId}"]`);
        if (elemento) {
            const content = elemento.querySelector('.grid-stack-item-content');
            if (content) {
                content.innerHTML = criarCardCarrossel(cardId);
                inicializarCarrossel(cardId);
            }
        }
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('modal_configurar_carrossel'));
        modal.hide();
        
        Swal.fire({
            text: 'Carrossel configurado com sucesso!',
            icon: 'success',
            buttonsStyling: false,
            confirmButtonText: 'Ok',
            customClass: {
                confirmButton: 'btn btn-primary'
            }
        });
    });
    
    // Toggle do campo de intervalo do autoplay
    document.getElementById('carousel_autoplay')?.addEventListener('change', function() {
        const container = document.getElementById('carousel_autoplay_delay_container');
        if (this.checked) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    });
});
</script>
<!--end::Dashboard Personalization Scripts-->

<!--begin::Chart Scripts-->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Ocorrências por Mês
const ctxOcorrenciasMes = document.getElementById('kt_chart_ocorrencias_mes');
if (ctxOcorrenciasMes) {
    new Chart(ctxOcorrenciasMes, {
        type: 'line',
        data: {
            labels: <?= json_encode($meses_grafico) ?>,
            datasets: [{
                label: 'Ocorrências',
                data: <?= json_encode($ocorrencias_grafico) ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

<?php if (!is_colaborador() || empty($colaborador_id)): ?>
// Gráfico de Colaboradores por Status (apenas Admin/RH/GESTOR)
const ctxColaboradoresStatus = document.getElementById('kt_chart_colaboradores_status');
if (ctxColaboradoresStatus && <?= json_encode(!empty($colaboradores_status)) ?>) {
    const statusData = <?= json_encode($colaboradores_status) ?>;
    new Chart(ctxColaboradoresStatus, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
            datasets: [{
                data: statusData.map(item => item.total),
                backgroundColor: [
                    'rgb(40, 167, 69)',
                    'rgb(220, 53, 69)',
                    'rgb(255, 193, 7)',
                    'rgb(108, 117, 125)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Gráfico de Ocorrências por Tipo (apenas Admin/RH/GESTOR)
const ctxOcorrenciasTipo = document.getElementById('kt_chart_ocorrencias_tipo');
if (ctxOcorrenciasTipo && <?= json_encode(!empty($ocorrencias_por_tipo)) ?>) {
    const tipoData = <?= json_encode($ocorrencias_por_tipo) ?>;
    new Chart(ctxOcorrenciasTipo, {
        type: 'bar',
        data: {
            labels: tipoData.map(item => item.tipo.charAt(0).toUpperCase() + item.tipo.slice(1)),
            datasets: [{
                label: 'Quantidade',
                data: tipoData.map(item => item.total),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Função para reinicializar gráficos após cards serem restaurados
function reinicializarGraficos() {
    console.log('🔄 Iniciando reinicialização de gráficos...');
    console.log('Chart.js disponível:', typeof Chart !== 'undefined');
    
    // Verifica se Chart.js está disponível
    if (typeof Chart === 'undefined') {
        console.error('❌ Chart.js não está disponível! Verifique se o script foi carregado.');
        return;
    }
    
    // Gráfico de Ocorrências por Mês
    const ctxOcorrenciasMes = document.getElementById('kt_chart_ocorrencias_mes');
    console.log('📊 Canvas Ocorrências por Mês encontrado:', ctxOcorrenciasMes !== null);
    console.log('📊 Canvas já tem gráfico:', ctxOcorrenciasMes?.chart ? 'Sim' : 'Não');
    
    if (ctxOcorrenciasMes && !ctxOcorrenciasMes.chart) {
        const mesesData = <?= json_encode($meses_grafico ?? []) ?>;
        const ocorrenciasData = <?= json_encode($ocorrencias_grafico ?? []) ?>;
        
        console.log('📊 Dados Ocorrências por Mês:', {
            meses: mesesData,
            ocorrencias: ocorrenciasData,
            mesesLength: mesesData?.length,
            ocorrenciasLength: ocorrenciasData?.length,
            mesesType: typeof mesesData,
            ocorrenciasType: typeof ocorrenciasData
        });
        
        if (mesesData && ocorrenciasData && mesesData.length > 0) {
            try {
                ctxOcorrenciasMes.chart = new Chart(ctxOcorrenciasMes, {
                type: 'line',
                data: {
                    labels: mesesData,
                    datasets: [{
                        label: 'Ocorrências',
                        data: ocorrenciasData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            console.log('✅ Gráfico de Ocorrências por Mês reinicializado com sucesso');
            } catch (error) {
                console.error('❌ Erro ao criar gráfico de Ocorrências por Mês:', error);
            }
        } else {
            console.warn('⚠️ Dados insuficientes para gráfico de Ocorrências por Mês:', {
                mesesData: mesesData,
                ocorrenciasData: ocorrenciasData
            });
            
            // Mostra mensagem no card se não houver dados
            const cardBody = ctxOcorrenciasMes.closest('.card-body');
            if (cardBody && !cardBody.querySelector('.text-center')) {
                ctxOcorrenciasMes.style.display = 'none';
                const mensagem = document.createElement('div');
                mensagem.className = 'text-center text-muted py-10';
                mensagem.innerHTML = `
                    <i class="ki-duotone ki-chart fs-3x text-muted mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <p class="fs-5 mb-0">Nenhuma ocorrência registrada nos últimos 6 meses</p>
                    <p class="fs-7">Os dados aparecerão aqui quando houver ocorrências</p>
                `;
                cardBody.appendChild(mensagem);
            }
        }
    } else if (ctxOcorrenciasMes && ctxOcorrenciasMes.chart) {
        console.log('ℹ️ Gráfico de Ocorrências por Mês já existe, pulando...');
    } else if (!ctxOcorrenciasMes) {
        console.warn('⚠️ Canvas kt_chart_ocorrencias_mes não encontrado no DOM');
        console.log('ℹ️ Isso é normal se não houver dados ou se o card não foi renderizado ainda');
        
        // Verifica se há uma mensagem de "sem dados" no lugar do canvas
        const cardBody = document.querySelector('[data-card-id="card_grafico_ocorrencias_mes"] .card-body');
        if (cardBody) {
            const temMensagem = cardBody.querySelector('.text-center');
            if (temMensagem) {
                console.log('ℹ️ Mensagem de "sem dados" encontrada, não é necessário criar gráfico');
            }
        }
    }
    
    <?php if (!is_colaborador() || empty($colaborador_id)): ?>
    // Gráfico de Colaboradores por Status
    const ctxColaboradoresStatus = document.getElementById('kt_chart_colaboradores_status');
    console.log('📊 Canvas Colaboradores por Status encontrado:', ctxColaboradoresStatus !== null);
    console.log('📊 Canvas já tem gráfico:', ctxColaboradoresStatus?.chart ? 'Sim' : 'Não');
    
    if (ctxColaboradoresStatus && !ctxColaboradoresStatus.chart) {
        const statusData = <?= json_encode($colaboradores_status ?? []) ?>;
        
        console.log('📊 Dados Colaboradores por Status:', {
            statusData: statusData,
            length: statusData?.length
        });
        
        if (statusData && statusData.length > 0) {
            try {
                ctxColaboradoresStatus.chart = new Chart(ctxColaboradoresStatus, {
                type: 'doughnut',
                data: {
                    labels: statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                    datasets: [{
                        data: statusData.map(item => item.total),
                        backgroundColor: [
                            'rgb(40, 167, 69)',
                            'rgb(220, 53, 69)',
                            'rgb(255, 193, 7)',
                            'rgb(108, 117, 125)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            console.log('✅ Gráfico de Colaboradores por Status reinicializado com sucesso');
            } catch (error) {
                console.error('❌ Erro ao criar gráfico de Colaboradores por Status:', error);
            }
        } else {
            console.warn('⚠️ Dados insuficientes para gráfico de Colaboradores por Status:', statusData);
            
            // Mostra mensagem no card se não houver dados
            const cardBody = ctxColaboradoresStatus.closest('.card-body');
            if (cardBody && !cardBody.querySelector('.text-center')) {
                ctxColaboradoresStatus.style.display = 'none';
                const mensagem = document.createElement('div');
                mensagem.className = 'text-center text-muted py-10';
                mensagem.innerHTML = `
                    <i class="ki-duotone ki-chart-pie fs-3x text-muted mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <p class="fs-5 mb-0">Nenhum colaborador encontrado</p>
                    <p class="fs-7">Os dados aparecerão aqui quando houver colaboradores cadastrados</p>
                `;
                cardBody.appendChild(mensagem);
            }
        }
    } else if (ctxColaboradoresStatus && ctxColaboradoresStatus.chart) {
        console.log('ℹ️ Gráfico de Colaboradores por Status já existe, pulando...');
    } else if (!ctxColaboradoresStatus) {
        console.warn('⚠️ Canvas kt_chart_colaboradores_status não encontrado no DOM');
        console.log('ℹ️ Isso é normal se não houver dados ou se o card não foi renderizado ainda');
        
        // Verifica se há uma mensagem de "sem dados" no lugar do canvas
        const cardBody = document.querySelector('[data-card-id="card_grafico_colaboradores_status"] .card-body');
        if (cardBody) {
            const temMensagem = cardBody.querySelector('.text-center');
            if (temMensagem) {
                console.log('ℹ️ Mensagem de "sem dados" encontrada, não é necessário criar gráfico');
            }
        }
    }
    
    // Gráfico de Ocorrências por Tipo
    const ctxOcorrenciasTipo = document.getElementById('kt_chart_ocorrencias_tipo');
    console.log('📊 Canvas Ocorrências por Tipo encontrado:', ctxOcorrenciasTipo !== null);
    console.log('📊 Canvas já tem gráfico:', ctxOcorrenciasTipo?.chart ? 'Sim' : 'Não');
    
    if (ctxOcorrenciasTipo && !ctxOcorrenciasTipo.chart) {
        const tipoData = <?= json_encode($ocorrencias_por_tipo ?? []) ?>;
        
        console.log('📊 Dados Ocorrências por Tipo:', {
            tipoData: tipoData,
            length: tipoData?.length,
            tipoDataRaw: JSON.stringify(tipoData)
        });
        
        if (tipoData && Array.isArray(tipoData) && tipoData.length > 0) {
            try {
                // Verifica se os dados têm a estrutura esperada
                const dadosValidos = tipoData.every(item => item && (item.tipo || item.tipo_ocorrencia_id) && typeof item.total !== 'undefined');
                console.log('📊 Dados têm estrutura válida:', dadosValidos);
                
                if (!dadosValidos) {
                    console.warn('⚠️ Estrutura de dados inválida. Esperado: [{tipo: string, total: number}]');
                    console.warn('⚠️ Dados recebidos:', tipoData);
                }
                
                ctxOcorrenciasTipo.chart = new Chart(ctxOcorrenciasTipo, {
                type: 'bar',
                data: {
                    labels: tipoData.map(item => {
                        // Tenta diferentes campos possíveis
                        const tipoNome = item.tipo || item.nome || item.tipo_ocorrencia || 'Sem nome';
                        return tipoNome.charAt(0).toUpperCase() + tipoNome.slice(1);
                    }),
                    datasets: [{
                        label: 'Quantidade',
                        data: tipoData.map(item => parseInt(item.total) || 0),
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            console.log('✅ Gráfico de Ocorrências por Tipo reinicializado com sucesso');
            } catch (error) {
                console.error('❌ Erro ao criar gráfico de Ocorrências por Tipo:', error);
            }
        } else {
            console.warn('⚠️ Dados insuficientes para gráfico de Ocorrências por Tipo:', tipoData);
            console.warn('⚠️ Isso pode significar que não há ocorrências nos últimos 30 dias ou a query não retornou dados');
            
            // Mostra mensagem no card se não houver dados
            const cardBody = ctxOcorrenciasTipo.closest('.card-body');
            if (cardBody && !cardBody.querySelector('.text-center')) {
                ctxOcorrenciasTipo.style.display = 'none';
                const mensagem = document.createElement('div');
                mensagem.className = 'text-center text-muted py-10';
                mensagem.innerHTML = `
                    <i class="ki-duotone ki-chart-bar fs-3x text-muted mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <p class="fs-5 mb-0">Nenhuma ocorrência registrada nos últimos 30 dias</p>
                    <p class="fs-7">Os dados aparecerão aqui quando houver ocorrências</p>
                `;
                cardBody.appendChild(mensagem);
            }
        }
    } else if (ctxOcorrenciasTipo && ctxOcorrenciasTipo.chart) {
        console.log('ℹ️ Gráfico de Ocorrências por Tipo já existe, pulando...');
    } else if (!ctxOcorrenciasTipo) {
        // Verifica se há uma mensagem de "sem dados" no lugar do canvas
        const cardBody = document.querySelector('[data-card-id="card_grafico_ocorrencias_tipo"] .card-body');
        if (cardBody) {
            const temMensagem = cardBody.querySelector('.text-center');
            if (temMensagem) {
                console.log('ℹ️ Canvas não encontrado - mensagem de "sem dados" está sendo exibida (comportamento esperado)');
            } else {
                console.warn('⚠️ Canvas kt_chart_ocorrencias_tipo não encontrado no DOM');
                console.log('ℹ️ Card body encontrado mas sem canvas nem mensagem - pode ser que o card ainda não foi renderizado');
            }
        } else {
            console.warn('⚠️ Canvas kt_chart_ocorrencias_tipo não encontrado no DOM');
            console.log('ℹ️ Isso é normal se não houver dados ou se o card não foi renderizado ainda');
        }
    }
    <?php endif; ?>
    
    console.log('✅ Reinicialização de gráficos concluída');
}

// Reinicializa gráficos após um pequeno delay para garantir que o DOM está pronto
setTimeout(() => {
    console.log('🔄 Reinicializando gráficos no carregamento inicial...');
    console.log('Chart.js disponível:', typeof Chart !== 'undefined');
    console.log('Função reinicializarGraficos existe:', typeof reinicializarGraficos === 'function');
    
    // Verifica se os canvas existem no DOM
    const canvasOcorrenciasMes = document.getElementById('kt_chart_ocorrencias_mes');
    const canvasColaboradoresStatus = document.getElementById('kt_chart_colaboradores_status');
    const canvasOcorrenciasTipo = document.getElementById('kt_chart_ocorrencias_tipo');
    
    console.log('Canvas encontrados no DOM:', {
        ocorrenciasMes: canvasOcorrenciasMes !== null,
        colaboradoresStatus: canvasColaboradoresStatus !== null,
        ocorrenciasTipo: canvasOcorrenciasTipo !== null
    });
    
    if (typeof reinicializarGraficos === 'function') {
        reinicializarGraficos();
    } else {
        console.error('❌ Função reinicializarGraficos não encontrada no carregamento inicial!');
    }
}, 500);
</script>
<!--end::Chart Scripts-->

<!--begin::Emoções Script-->
<script>
// Função global para enviar humor (Colaborador)
window.enviarHumorColab = function() {
    console.log('enviarHumorColab chamado');
    const form = document.getElementById('form_emocao_dashboard_colab');
    if (!form) {
        console.error('Formulário não encontrado');
        return false;
    }
    
    const nivelEmocao = form.querySelector('input[name="nivel_emocao"]:checked');
    if (!nivelEmocao) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                text: "Por favor, selecione como você está se sentindo",
                icon: "warning",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        } else {
            alert("Por favor, selecione como você está se sentindo");
        }
        return false;
    }
    
    const formData = new FormData(form);
    const btn = form.querySelector('button.btn-primary');
    const indicator = btn ? btn.querySelector('.indicator-label') : null;
    const progress = btn ? btn.querySelector('.indicator-progress') : null;
    
    if (btn) {
        btn.setAttribute('data-kt-indicator', 'on');
        btn.disabled = true;
        if (indicator) indicator.style.display = 'none';
        if (progress) progress.style.display = 'inline-block';
    }
    
    console.log('Enviando para API...');
    fetch('../api/registrar_emocao.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Resposta recebida:', response.status);
        if (!response.ok) {
            throw new Error('Erro na resposta do servidor: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Dados:', data);
        if (data.success) {
            // Mostra toast de pontos se ganhou
            if (data.pontos_ganhos && window.processarRespostaPontos) {
                window.processarRespostaPontos(data, 'registrar_emocao');
            }
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: data.message,
                    icon: "success",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                }).then(function() {
                    location.reload();
                });
            } else {
                alert(data.message);
                location.reload();
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: data.message || "Erro ao registrar emoção",
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
            } else {
                alert(data.message || "Erro ao registrar emoção");
            }
            if (btn) {
                btn.removeAttribute('data-kt-indicator');
                btn.disabled = false;
                if (indicator) indicator.style.display = 'inline-block';
                if (progress) progress.style.display = 'none';
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                text: "Erro ao registrar emoção. Tente novamente.",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        } else {
            alert("Erro ao registrar emoção. Tente novamente.");
        }
        if (btn) {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            if (indicator) indicator.style.display = 'inline-block';
            if (progress) progress.style.display = 'none';
        }
    });
    
    return false;
};

// Função global para enviar humor (Admin/RH/GESTOR)
window.enviarHumorAdmin = function() {
    console.log('enviarHumorAdmin chamado');
    const form = document.getElementById('form_emocao_dashboard');
    if (!form) {
        console.error('Formulário não encontrado');
        return false;
    }
    
    const nivelEmocao = form.querySelector('input[name="nivel_emocao"]:checked');
    if (!nivelEmocao) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                text: "Por favor, selecione como você está se sentindo",
                icon: "warning",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        } else {
            alert("Por favor, selecione como você está se sentindo");
        }
        return false;
    }
    
    const formData = new FormData(form);
    const btn = form.querySelector('button.btn-primary');
    const indicator = btn ? btn.querySelector('.indicator-label') : null;
    const progress = btn ? btn.querySelector('.indicator-progress') : null;
    
    if (btn) {
        btn.setAttribute('data-kt-indicator', 'on');
        btn.disabled = true;
        if (indicator) indicator.style.display = 'none';
        if (progress) progress.style.display = 'inline-block';
    }
    
    console.log('Enviando para API...');
    fetch('../api/registrar_emocao.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Resposta recebida:', response.status);
        if (!response.ok) {
            throw new Error('Erro na resposta do servidor: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Dados:', data);
        if (data.success) {
            // Mostra toast de pontos se ganhou
            if (data.pontos_ganhos && window.processarRespostaPontos) {
                window.processarRespostaPontos(data, 'registrar_emocao');
            }
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: data.message,
                    icon: "success",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                }).then(function() {
                    location.reload();
                });
            } else {
                alert(data.message);
                location.reload();
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: data.message || "Erro ao registrar emoção",
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
            } else {
                alert(data.message || "Erro ao registrar emoção");
            }
            if (btn) {
                btn.removeAttribute('data-kt-indicator');
                btn.disabled = false;
                if (indicator) indicator.style.display = 'inline-block';
                if (progress) progress.style.display = 'none';
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                text: "Erro ao registrar emoção. Tente novamente.",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        } else {
            alert("Erro ao registrar emoção. Tente novamente.");
        }
        if (btn) {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            if (indicator) indicator.style.display = 'inline-block';
            if (progress) progress.style.display = 'none';
        }
    });
    
    return false;
};

(function() {
    console.log('Inicializando scripts de emoção...');
    
    // Função para inicializar seleção visual de emoção
    function initSelecaoVisualEmocao() {
        console.log('Inicializando seleção visual de emoções');
        document.querySelectorAll('.emocao-option').forEach(function(option) {
            option.addEventListener('click', function() {
                const radio = this.previousElementSibling;
                if (radio) {
                    radio.checked = true;
                }
                
                // Remove seleção anterior no mesmo formulário
                const form = this.closest('form');
                if (form) {
                    form.querySelectorAll('.emocao-option').forEach(function(opt) {
                        opt.style.borderColor = 'transparent';
                        opt.style.backgroundColor = '';
                    });
                } else {
                    // Fallback: remove de todos se não encontrar form
                    document.querySelectorAll('.emocao-option').forEach(function(opt) {
                        opt.style.borderColor = 'transparent';
                        opt.style.backgroundColor = '';
                    });
                }
                
                // Marca selecionado
                this.style.borderColor = '#009ef7';
                this.style.backgroundColor = '#f1faff';
            });
        });
    }
    
    // Função para inicializar os formulários de emoção
    function initFormulariosEmocao() {
        console.log('Inicializando formulários de emoção...');
        
        // Submit do formulário de emoção (Admin/RH/GESTOR)
        const formEmocao = document.getElementById('form_emocao_dashboard');
        console.log('Formulário Admin/RH encontrado:', formEmocao !== null);
        
        if (formEmocao) {
            // Garante que não há action que cause redirecionamento
            formEmocao.removeAttribute('action');
            formEmocao.onsubmit = function() { return false; };
            
            formEmocao.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Formulário de emoção submetido');
                
                const nivelEmocao = this.querySelector('input[name="nivel_emocao"]:checked');
                if (!nivelEmocao) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            text: "Por favor, selecione como você está se sentindo",
                            icon: "warning",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                    } else {
                        alert("Por favor, selecione como você está se sentindo");
                    }
                    return false;
                }
                
                const formData = new FormData(this);
                const btn = this.querySelector('button[type="submit"]');
                const indicator = btn ? btn.querySelector('.indicator-label') : null;
                const progress = btn ? btn.querySelector('.indicator-progress') : null;
                
                if (btn) {
                    btn.setAttribute('data-kt-indicator', 'on');
                    btn.disabled = true;
                    if (indicator) indicator.style.display = 'none';
                    if (progress) progress.style.display = 'inline-block';
                }
                
                fetch('../api/registrar_emocao.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta do servidor: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Resposta da API:', data);
                    if (data.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                text: data.message,
                                icon: "success",
                                buttonsStyling: false,
                                confirmButtonText: "Ok",
                                customClass: {
                                    confirmButton: "btn btn-primary"
                                }
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            alert(data.message);
                            location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                text: data.message || "Erro ao registrar emoção",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok",
                                customClass: {
                                    confirmButton: "btn btn-primary"
                                }
                            });
                        } else {
                            alert(data.message || "Erro ao registrar emoção");
                        }
                        if (btn) {
                            btn.removeAttribute('data-kt-indicator');
                            btn.disabled = false;
                            if (indicator) indicator.style.display = 'inline-block';
                            if (progress) progress.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao registrar emoção:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            text: "Erro ao registrar emoção. Tente novamente.",
                            icon: "error",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                    } else {
                        alert("Erro ao registrar emoção. Tente novamente.");
                    }
                    if (btn) {
                        btn.removeAttribute('data-kt-indicator');
                        btn.disabled = false;
                        if (indicator) indicator.style.display = 'inline-block';
                        if (progress) progress.style.display = 'none';
                    }
                });
                
                return false;
            });
        }
        
        // Submit do formulário de emoção (Colaborador)
        const formEmocaoColab = document.getElementById('form_emocao_dashboard_colab');
        console.log('Formulário Colaborador encontrado:', formEmocaoColab !== null);
        
        if (formEmocaoColab) {
            // Garante que não há action que cause redirecionamento
            formEmocaoColab.removeAttribute('action');
            formEmocaoColab.onsubmit = function() { return false; };
            
            formEmocaoColab.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Formulário de emoção (colab) submetido');
                
                const nivelEmocao = this.querySelector('input[name="nivel_emocao"]:checked');
                if (!nivelEmocao) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            text: "Por favor, selecione como você está se sentindo",
                            icon: "warning",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                    } else {
                        alert("Por favor, selecione como você está se sentindo");
                    }
                    return false;
                }
                
                const formData = new FormData(this);
                const btn = this.querySelector('button[type="submit"]');
                const indicator = btn ? btn.querySelector('.indicator-label') : null;
                const progress = btn ? btn.querySelector('.indicator-progress') : null;
                
                if (btn) {
                    btn.setAttribute('data-kt-indicator', 'on');
                    btn.disabled = true;
                    if (indicator) indicator.style.display = 'none';
                    if (progress) progress.style.display = 'inline-block';
                }
                
                fetch('../api/registrar_emocao.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta do servidor: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Resposta da API:', data);
                    if (data.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                text: data.message,
                                icon: "success",
                                buttonsStyling: false,
                                confirmButtonText: "Ok",
                                customClass: {
                                    confirmButton: "btn btn-primary"
                                }
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            alert(data.message);
                            location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                text: data.message || "Erro ao registrar emoção",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok",
                                customClass: {
                                    confirmButton: "btn btn-primary"
                                }
                            });
                        } else {
                            alert(data.message || "Erro ao registrar emoção");
                        }
                        if (btn) {
                            btn.removeAttribute('data-kt-indicator');
                            btn.disabled = false;
                            if (indicator) indicator.style.display = 'inline-block';
                            if (progress) progress.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao registrar emoção:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            text: "Erro ao registrar emoção. Tente novamente.",
                            icon: "error",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                    } else {
                        alert("Erro ao registrar emoção. Tente novamente.");
                    }
                    if (btn) {
                        btn.removeAttribute('data-kt-indicator');
                        btn.disabled = false;
                        if (indicator) indicator.style.display = 'inline-block';
                        if (progress) progress.style.display = 'none';
                    }
                });
                
                return false;
            });
        }
    }
    
    // Executa quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        console.log('DOM ainda carregando, aguardando DOMContentLoaded...');
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded disparado');
            initSelecaoVisualEmocao();
            initFormulariosEmocao();
        });
    } else {
        // DOM já está carregado, executa imediatamente
        console.log('DOM já carregado, executando imediatamente');
        initSelecaoVisualEmocao();
        initFormulariosEmocao();
    }
    
    console.log('Script de emoções inicializado com sucesso');
})();
</script>
<style>
.emocao-option {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    transition: all 0.3s;
    cursor: pointer;
    border: 2px solid transparent;
}

.emocao-option:hover {
    background-color: #f5f8fa;
    transform: scale(1.1);
}

input[type="radio"]:checked + .emocao-option {
    border-color: #009ef7;
    background-color: #f1faff;
}

.anotacao-item {
    border-left: 4px solid #e4e6ef;
    transition: all 0.3s;
}

.anotacao-item.urgente {
    border-left-color: #f1416c;
}

.anotacao-item.alta {
    border-left-color: #ffc700;
}

.anotacao-item.media {
    border-left-color: #009ef7;
}

.anotacao-item.baixa {
    border-left-color: #50cd89;
}

.anotacao-item.fixada {
    background-color: #f8f9fa;
}
</style>
<!--end::Emoções Script-->

<?php if (has_role(['ADMIN', 'RH', 'GESTOR'])): ?>
<!--begin::Modal - Nova Anotação-->
<div class="modal fade" id="modal_nova_anotacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_anotacao_titulo">Nova Anotação</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="form_nova_anotacao">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <input type="hidden" name="id" id="anotacao_id">
                    
                    <div class="mb-5">
                        <label class="form-label required">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-solid" placeholder="Digite o título da anotação" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Conteúdo</label>
                        <textarea name="conteudo" class="form-control form-control-solid" rows="5" placeholder="Digite o conteúdo da anotação" required></textarea>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select form-select-solid">
                                <option value="geral">Geral</option>
                                <option value="lembrete">Lembrete</option>
                                <option value="importante">Importante</option>
                                <option value="urgente">Urgente</option>
                                <option value="informacao">Informação</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select form-select-solid">
                                <option value="baixa">Baixa</option>
                                <option value="media" selected>Média</option>
                                <option value="alta">Alta</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <input type="text" name="categoria" class="form-control form-control-solid" placeholder="Ex: RH, Financeiro, etc">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data de Vencimento</label>
                            <input type="date" name="data_vencimento" class="form-control form-control-solid">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Tags (separadas por vírgula)</label>
                        <input type="text" name="tags_input" class="form-control form-control-solid" placeholder="Ex: importante, urgente, reunião">
                        <div class="form-text">Separe as tags por vírgula</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Público Alvo</label>
                        <select name="publico_alvo" id="publico_alvo_anotacao" class="form-select form-select-solid">
                            <option value="atribuir_mim" selected>Atribuir a Mim</option>
                            <option value="especifico">Específico</option>
                            <option value="todos">Todos</option>
                            <option value="empresa">Empresa</option>
                            <option value="setor">Setor</option>
                            <option value="cargo">Cargo</option>
                        </select>
                    </div>
                    
                    <div id="destinatarios_especificos" class="mb-5" style="display: none;">
                        <label class="form-label">Destinatários</label>
                        <div class="form-text mb-3">Selecione usuários ou colaboradores específicos</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Usuários</label>
                                <div class="form-control form-control-solid" style="min-height: 200px; max-height: 300px; overflow-y: auto; padding: 10px;">
                                    <div id="checkboxes_usuarios">
                                        <div class="text-center text-muted py-3">
                                            <span class="spinner-border spinner-border-sm me-2"></span>
                                            Carregando...
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Colaboradores</label>
                                <div class="form-control form-control-solid" style="min-height: 200px; max-height: 300px; overflow-y: auto; padding: 10px;">
                                    <div id="checkboxes_colaboradores">
                                        <div class="text-center text-muted py-3">
                                            <span class="spinner-border spinner-border-sm me-2"></span>
                                            Carregando...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="campo_empresa_anotacao" class="mb-5" style="display: none;">
                        <label class="form-label required">Empresas</label>
                        <div class="form-text mb-2">Selecione uma ou mais empresas</div>
                        <div class="form-control form-control-solid" style="min-height: 200px; max-height: 300px; overflow-y: auto; padding: 10px;">
                            <div id="checkboxes_empresas">
                                <?php
                                // Busca empresas disponíveis
                                if ($usuario['role'] === 'ADMIN') {
                                    $stmt_emp = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
                                    $empresas_anotacao = $stmt_emp->fetchAll();
                                } elseif ($usuario['role'] === 'RH') {
                                    // RH pode ter múltiplas empresas
                                    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                                        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                                        $stmt_emp = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) AND status = 'ativo' ORDER BY nome_fantasia");
                                        $stmt_emp->execute($usuario['empresas_ids']);
                                        $empresas_anotacao = $stmt_emp->fetchAll();
                                    } else {
                                        // Fallback para compatibilidade
                                        $stmt_emp = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo' ORDER BY nome_fantasia");
                                        $stmt_emp->execute([$usuario['empresa_id'] ?? 0]);
                                        $empresas_anotacao = $stmt_emp->fetchAll();
                                    }
                                } else {
                                    $empresas_anotacao = [];
                                }
                                if (empty($empresas_anotacao)): ?>
                                    <div class="text-muted small">Nenhuma empresa disponível</div>
                                <?php else:
                                    foreach ($empresas_anotacao as $emp): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="empresas[]" value="<?= $emp['id'] ?>" id="empresa_<?= $emp['id'] ?>">
                                            <label class="form-check-label" for="empresa_<?= $emp['id'] ?>">
                                                <?= htmlspecialchars($emp['nome_fantasia']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="campo_setor_anotacao" class="mb-5" style="display: none;">
                        <label class="form-label required">Setores</label>
                        <div class="form-text mb-2">Selecione um ou mais setores</div>
                        <div class="form-control form-control-solid" style="min-height: 200px; max-height: 300px; overflow-y: auto; padding: 10px;">
                            <div id="checkboxes_setores">
                                <?php
                                // Busca setores disponíveis
                                if ($usuario['role'] === 'ADMIN') {
                                    $stmt_set = $pdo->query("SELECT id, nome_setor FROM setores WHERE status = 'ativo' ORDER BY nome_setor");
                                    $setores_anotacao = $stmt_set->fetchAll();
                                } elseif ($usuario['role'] === 'RH') {
                                    // RH pode ter múltiplas empresas
                                    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                                        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                                        $stmt_set = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id IN ($placeholders) AND status = 'ativo' ORDER BY nome_setor");
                                        $stmt_set->execute($usuario['empresas_ids']);
                                        $setores_anotacao = $stmt_set->fetchAll();
                                    } else {
                                        $stmt_set = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
                                        $stmt_set->execute([$usuario['empresa_id'] ?? 0]);
                                        $setores_anotacao = $stmt_set->fetchAll();
                                    }
                                } elseif ($usuario['role'] === 'GESTOR') {
                                    $stmt_set = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE id = ? AND status = 'ativo' ORDER BY nome_setor");
                                    $stmt_set->execute([$setor_id]);
                                    $setores_anotacao = $stmt_set->fetchAll();
                                } else {
                                    $setores_anotacao = [];
                                }
                                if (empty($setores_anotacao)): ?>
                                    <div class="text-muted small">Nenhum setor disponível</div>
                                <?php else:
                                    foreach ($setores_anotacao as $setor): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="setores[]" value="<?= $setor['id'] ?>" id="setor_<?= $setor['id'] ?>">
                                            <label class="form-check-label" for="setor_<?= $setor['id'] ?>">
                                                <?= htmlspecialchars($setor['nome_setor']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="campo_cargo_anotacao" class="mb-5" style="display: none;">
                        <label class="form-label required">Cargos</label>
                        <div class="form-text mb-2">Selecione um ou mais cargos</div>
                        <div class="form-control form-control-solid" style="min-height: 200px; max-height: 300px; overflow-y: auto; padding: 10px;">
                            <div id="checkboxes_cargos">
                                <?php
                                // Busca cargos disponíveis
                                if ($usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
                                    $stmt_car = $pdo->query("SELECT id, nome_cargo FROM cargos WHERE status = 'ativo' ORDER BY nome_cargo");
                                    $cargos_anotacao = $stmt_car->fetchAll();
                                } else {
                                    $cargos_anotacao = [];
                                }
                                if (empty($cargos_anotacao)): ?>
                                    <div class="text-muted small">Nenhum cargo disponível</div>
                                <?php else:
                                    foreach ($cargos_anotacao as $cargo): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="cargos[]" value="<?= $cargo['id'] ?>" id="cargo_<?= $cargo['id'] ?>">
                                            <label class="form-check-label" for="cargo_<?= $cargo['id'] ?>">
                                                <?= htmlspecialchars($cargo['nome_cargo']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="fixada" id="anotacao_fixada" value="1">
                            <label class="form-check-label" for="anotacao_fixada">
                                Fixar no topo
                            </label>
                        </div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <h4 class="fw-bold mb-5">Notificações</h4>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="notificar_email" id="notificar_email_anotacao" value="1">
                            <label class="form-check-label" for="notificar_email_anotacao">
                                Enviar notificação por Email
                            </label>
                        </div>
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="notificar_push" id="notificar_push_anotacao" value="1">
                            <label class="form-check-label" for="notificar_push_anotacao">
                                Enviar notificação Push
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-5" id="campo_data_notificacao" style="display: none;">
                        <label class="form-label">Data/Hora da Notificação</label>
                        <input type="datetime-local" name="data_notificacao" class="form-control form-control-solid">
                        <div class="form-text">Deixe em branco para enviar imediatamente</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Salvando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Script Anotações-->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carrega anotações ao abrir a página
    carregarAnotacoes();
    
    // Variáveis globais para destinatários
    let usuariosDisponiveis = [];
    let colaboradoresDisponiveis = [];
    
    // Carrega destinatários disponíveis
    function carregarDestinatarios() {
        return fetch('../api/anotacoes/get_destinatarios.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    usuariosDisponiveis = data.usuarios || [];
                    colaboradoresDisponiveis = data.colaboradores || [];
                    popularSelectsDestinatarios();
                }
                return data;
            })
            .catch(error => {
                console.error('Erro ao carregar destinatários:', error);
                return { success: false };
            });
    }
    
    // Popula os checkboxes de destinatários
    function popularSelectsDestinatarios() {
        const usuariosContainer = document.getElementById('checkboxes_usuarios');
        const colabsContainer = document.getElementById('checkboxes_colaboradores');
        
        if (usuariosContainer) {
            if (usuariosDisponiveis.length === 0) {
                usuariosContainer.innerHTML = '<div class="text-muted small">Nenhum usuário disponível</div>';
            } else {
                let html = '';
                usuariosDisponiveis.forEach(usuario => {
                    const fotoUrl = usuario.foto ? '../' + usuario.foto : null;
                    const inicial = usuario.display_name ? usuario.display_name.charAt(0).toUpperCase() : '?';
                    html += `
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="destinatarios_usuarios[]" value="${usuario.id}" id="usuario_${usuario.id}">
                            <label class="form-check-label d-flex align-items-center" for="usuario_${usuario.id}">
                                ${fotoUrl ? `<img src="${fotoUrl}" class="rounded-circle me-2" width="24" height="24" style="object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />` : ''}
                                <span class="symbol symbol-circle symbol-24px me-2" ${fotoUrl ? 'style="display:none;"' : ''}>
                                    <span class="symbol-label fs-7 fw-semibold bg-primary text-white">${inicial}</span>
                                </span>
                                <span>${usuario.display_name || usuario.nome} <small class="text-muted">(${usuario.role})</small></span>
                            </label>
                        </div>
                    `;
                });
                usuariosContainer.innerHTML = html;
            }
        }
        
        if (colabsContainer) {
            if (colaboradoresDisponiveis.length === 0) {
                colabsContainer.innerHTML = '<div class="text-muted small">Nenhum colaborador disponível</div>';
            } else {
                let html = '';
                colaboradoresDisponiveis.forEach(colab => {
                    const fotoUrl = colab.foto ? '../' + colab.foto : null;
                    const inicial = colab.nome_completo ? colab.nome_completo.charAt(0).toUpperCase() : '?';
                    html += `
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="destinatarios_colaboradores[]" value="${colab.id}" id="colab_${colab.id}">
                            <label class="form-check-label d-flex align-items-center" for="colab_${colab.id}">
                                ${fotoUrl ? `<img src="${fotoUrl}" class="rounded-circle me-2" width="24" height="24" style="object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />` : ''}
                                <span class="symbol symbol-circle symbol-24px me-2" ${fotoUrl ? 'style="display:none;"' : ''}>
                                    <span class="symbol-label fs-7 fw-semibold bg-success text-white">${inicial}</span>
                                </span>
                                <span>${colab.nome_completo}</span>
                            </label>
                        </div>
                    `;
                });
                colabsContainer.innerHTML = html;
            }
        }
    }
    
    // Controla visibilidade do campo de destinatários específicos
    const publicoAlvoSelect = document.getElementById('publico_alvo_anotacao');
    const destinatariosEspecificos = document.getElementById('destinatarios_especificos');
    
    function atualizarVisibilidadeDestinatarios() {
        if (!publicoAlvoSelect) return;
        
        const valor = publicoAlvoSelect.value;
        const campoEmpresa = document.getElementById('campo_empresa_anotacao');
        const campoSetor = document.getElementById('campo_setor_anotacao');
        const campoCargo = document.getElementById('campo_cargo_anotacao');
        
        // Mostra/oculta campos baseado no público alvo
        if (valor === 'atribuir_mim') {
            // Atribuir a mim: oculta todos os campos de seleção
            if (destinatariosEspecificos) destinatariosEspecificos.style.display = 'none';
            if (campoEmpresa) campoEmpresa.style.display = 'none';
            if (campoSetor) campoSetor.style.display = 'none';
            if (campoCargo) campoCargo.style.display = 'none';
        } else if (valor === 'especifico') {
            if (destinatariosEspecificos) destinatariosEspecificos.style.display = 'block';
            if (campoEmpresa) campoEmpresa.style.display = 'none';
            if (campoSetor) campoSetor.style.display = 'none';
            if (campoCargo) campoCargo.style.display = 'none';
        } else if (valor === 'empresa') {
            if (destinatariosEspecificos) destinatariosEspecificos.style.display = 'none';
            if (campoEmpresa) campoEmpresa.style.display = 'block';
            if (campoSetor) campoSetor.style.display = 'none';
            if (campoCargo) campoCargo.style.display = 'none';
        } else if (valor === 'setor') {
            if (destinatariosEspecificos) destinatariosEspecificos.style.display = 'none';
            if (campoEmpresa) campoEmpresa.style.display = 'none';
            if (campoSetor) campoSetor.style.display = 'block';
            if (campoCargo) campoCargo.style.display = 'none';
        } else if (valor === 'cargo') {
            if (destinatariosEspecificos) destinatariosEspecificos.style.display = 'none';
            if (campoEmpresa) campoEmpresa.style.display = 'none';
            if (campoSetor) campoSetor.style.display = 'none';
            if (campoCargo) campoCargo.style.display = 'block';
        } else { // todos
            if (destinatariosEspecificos) destinatariosEspecificos.style.display = 'none';
            if (campoEmpresa) campoEmpresa.style.display = 'none';
            if (campoSetor) campoSetor.style.display = 'none';
            if (campoCargo) campoCargo.style.display = 'none';
        }
    }
    
    publicoAlvoSelect?.addEventListener('change', atualizarVisibilidadeDestinatarios);
    
    // Carrega destinatários quando o modal é aberto
    const modalAnotacao = document.getElementById('modal_nova_anotacao');
    if (modalAnotacao) {
        modalAnotacao.addEventListener('shown.bs.modal', function() {
            carregarDestinatarios();
            atualizarVisibilidadeDestinatarios();
        });
    }
    
    // Filtros
    document.getElementById('filtro_status_anotacoes')?.addEventListener('change', carregarAnotacoes);
    document.getElementById('filtro_prioridade_anotacoes')?.addEventListener('change', carregarAnotacoes);
    document.getElementById('btn_fixadas_anotacoes')?.addEventListener('click', function() {
        const url = new URL(window.location);
        url.searchParams.set('fixadas', '1');
        window.location = url;
    });
    
    // Mostra/oculta campo de data de notificação
    const checkEmail = document.getElementById('notificar_email_anotacao');
    const checkPush = document.getElementById('notificar_push_anotacao');
    const campoDataNotif = document.getElementById('campo_data_notificacao');
    
    function atualizarCampoDataNotif() {
        if (checkEmail?.checked || checkPush?.checked) {
            campoDataNotif.style.display = 'block';
        } else {
            campoDataNotif.style.display = 'none';
        }
    }
    
    checkEmail?.addEventListener('change', atualizarCampoDataNotif);
    checkPush?.addEventListener('change', atualizarCampoDataNotif);
    
    // Submit do formulário
    const formAnotacao = document.getElementById('form_nova_anotacao');
    if (formAnotacao) {
        formAnotacao.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const indicator = btn.querySelector('.indicator-label');
            const progress = btn.querySelector('.indicator-progress');
            
            // Processa tags
            const tagsInput = formData.get('tags_input');
            if (tagsInput) {
                const tags = tagsInput.split(',').map(t => t.trim()).filter(t => t);
                formData.delete('tags_input');
                formData.append('tags', JSON.stringify(tags));
            }
            
            // Processa público alvo
            const publicoAlvo = formData.get('publico_alvo');
            
            // Processa destinatários (checkboxes) apenas se não for "atribuir_mim"
            if (publicoAlvo !== 'atribuir_mim') {
                // Usuários e colaboradores específicos
                if (publicoAlvo === 'especifico') {
                    const usuariosCheckboxes = document.querySelectorAll('input[name="destinatarios_usuarios[]"]:checked');
                    const colabsCheckboxes = document.querySelectorAll('input[name="destinatarios_colaboradores[]"]:checked');
                    const usuariosIds = Array.from(usuariosCheckboxes).map(cb => parseInt(cb.value));
                    const colabsIds = Array.from(colabsCheckboxes).map(cb => parseInt(cb.value));
                    
                    if (usuariosIds.length > 0) {
                        formData.append('destinatarios_usuarios', JSON.stringify(usuariosIds));
                    }
                    if (colabsIds.length > 0) {
                        formData.append('destinatarios_colaboradores', JSON.stringify(colabsIds));
                    }
                }
                
                // Empresas (múltiplas)
                if (publicoAlvo === 'empresa') {
                    const empresasCheckboxes = document.querySelectorAll('input[name="empresas[]"]:checked');
                    const empresasIds = Array.from(empresasCheckboxes).map(cb => parseInt(cb.value));
                    if (empresasIds.length > 0) {
                        formData.append('empresas_ids', JSON.stringify(empresasIds));
                    }
                }
                
                // Setores (múltiplos)
                if (publicoAlvo === 'setor') {
                    const setoresCheckboxes = document.querySelectorAll('input[name="setores[]"]:checked');
                    const setoresIds = Array.from(setoresCheckboxes).map(cb => parseInt(cb.value));
                    if (setoresIds.length > 0) {
                        formData.append('setores_ids', JSON.stringify(setoresIds));
                    }
                }
                
                // Cargos (múltiplos)
                if (publicoAlvo === 'cargo') {
                    const cargosCheckboxes = document.querySelectorAll('input[name="cargos[]"]:checked');
                    const cargosIds = Array.from(cargosCheckboxes).map(cb => parseInt(cb.value));
                    if (cargosIds.length > 0) {
                        formData.append('cargos_ids', JSON.stringify(cargosIds));
                    }
                }
            }
            
            btn.setAttribute('data-kt-indicator', 'on');
            indicator.style.display = 'none';
            progress.style.display = 'inline-block';
            
            const anotacaoId = formData.get('id');
            const url = anotacaoId ? '../api/anotacoes/editar.php' : '../api/anotacoes/criar.php';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        text: data.message,
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    }).then(function() {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modal_nova_anotacao'));
                        modal.hide();
                        formAnotacao.reset();
                        carregarAnotacoes();
                    });
                } else {
                    Swal.fire({
                        text: data.message,
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    text: "Erro ao salvar anotação",
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
            })
            .finally(() => {
                btn.removeAttribute('data-kt-indicator');
                indicator.style.display = 'inline-block';
                progress.style.display = 'none';
            });
        });
    }
    
    // Carrega anotações
    function carregarAnotacoes() {
        const status = document.getElementById('filtro_status_anotacoes')?.value || 'ativa';
        const prioridade = document.getElementById('filtro_prioridade_anotacoes')?.value || '';
        const fixadas = new URLSearchParams(window.location.search).get('fixadas') || '0';
        
        const params = new URLSearchParams({
            status: status,
            limite: 20
        });
        
        if (prioridade) params.append('prioridade', prioridade);
        if (fixadas === '1') params.append('fixadas', '1');
        
        fetch('../api/anotacoes/listar.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderizarAnotacoes(data.anotacoes);
                } else {
                    document.getElementById('lista_anotacoes').innerHTML = 
                        '<div class="text-center text-muted py-5"><p>Erro ao carregar anotações.</p></div>';
                }
            })
            .catch(error => {
                document.getElementById('lista_anotacoes').innerHTML = 
                    '<div class="text-center text-muted py-5"><p>Erro ao carregar anotações.</p></div>';
            });
    }
    
    // Renderiza anotações
    function renderizarAnotacoes(anotacoes) {
        const container = document.getElementById('lista_anotacoes');
        
        if (!anotacoes || anotacoes.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-5"><p>Nenhuma anotação encontrada.</p></div>';
            return;
        }
        
        let html = '<div class="d-flex flex-column gap-4">';
        
        anotacoes.forEach(anotacao => {
            const prioridadeClass = anotacao.prioridade || 'media';
            const fixadaClass = anotacao.fixada ? 'fixada' : '';
            const statusBadge = {
                'ativa': '<span class="badge badge-success">Ativa</span>',
                'concluida': '<span class="badge badge-info">Concluída</span>',
                'arquivada': '<span class="badge badge-secondary">Arquivada</span>'
            }[anotacao.status] || '';
            
            html += `
                <div class="card anotacao-item ${prioridadeClass} ${fixadaClass}" id="anotacao_${anotacao.id}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-1">
                                    ${anotacao.fixada ? '<i class="ki-duotone ki-pin fs-5 text-warning me-2"><span class="path1"></span><span class="path2"></span></i>' : ''}
                                    ${anotacao.titulo || 'Sem título'}
                                </h5>
                                <div class="d-flex gap-2 align-items-center mb-2">
                                    ${statusBadge}
                                    <span class="badge badge-light-${prioridadeClass === 'urgente' ? 'danger' : prioridadeClass === 'alta' ? 'warning' : prioridadeClass === 'media' ? 'primary' : 'success'}">${anotacao.prioridade || 'Média'}</span>
                                    ${anotacao.tipo ? `<span class="badge badge-light">${anotacao.tipo}</span>` : ''}
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown">
                                    <i class="ki-duotone ki-dots-vertical fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="editarAnotacao(${anotacao.id}); return false;">Editar</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="marcarVisualizada(${anotacao.id}); return false;">Marcar como visualizada</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="excluirAnotacao(${anotacao.id}); return false;">Excluir</a></li>
                                </ul>
                            </div>
                        </div>
                        <p class="text-gray-700 mb-3">${anotacao.conteudo || ''}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                <span>Criado por: ${anotacao.usuario_nome || 'Sistema'}</span>
                                ${anotacao.data_notificacao_formatada ? `<br>Notificar em: ${anotacao.data_notificacao_formatada}` : ''}
                                ${anotacao.data_vencimento_formatada ? `<br>Vencimento: ${anotacao.data_vencimento_formatada}` : ''}
                            </div>
                            <div class="text-muted small">
                                ${anotacao.total_visualizacoes || 0} visualizações
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    // Funções globais
    window.editarAnotacao = function(id) {
        fetch('../api/anotacoes/detalhes.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.anotacao) {
                    const anotacao = data.anotacao;
                    document.getElementById('anotacao_id').value = anotacao.id;
                    document.querySelector('[name="titulo"]').value = anotacao.titulo || '';
                    document.querySelector('[name="conteudo"]').value = anotacao.conteudo || '';
                    document.querySelector('[name="tipo"]').value = anotacao.tipo || 'geral';
                    document.querySelector('[name="prioridade"]').value = anotacao.prioridade || 'media';
                    document.querySelector('[name="categoria"]').value = anotacao.categoria || '';
                    document.querySelector('[name="data_vencimento"]').value = anotacao.data_vencimento || '';
                    document.querySelector('[name="tags_input"]').value = (anotacao.tags || []).join(', ');
                    document.getElementById('anotacao_fixada').checked = anotacao.fixada == 1;
                    document.getElementById('notificar_email_anotacao').checked = anotacao.notificar_email == 1;
                    document.getElementById('notificar_push_anotacao').checked = anotacao.notificar_push == 1;
                    document.querySelector('[name="publico_alvo"]').value = anotacao.publico_alvo || 'especifico';
                    
                    if (anotacao.data_notificacao) {
                        const dt = new Date(anotacao.data_notificacao.replace(' ', 'T'));
                        document.querySelector('[name="data_notificacao"]').value = dt.toISOString().slice(0, 16);
                        atualizarCampoDataNotif();
                    }
                    
                    // Atualiza visibilidade dos campos
                    atualizarVisibilidadeDestinatarios();
                    
                    // Aguarda um pouco para garantir que os campos foram renderizados
                    setTimeout(() => {
                        // Seleciona empresas, setores ou cargos se aplicável (múltiplos)
                        if (anotacao.empresas_ids && Array.isArray(anotacao.empresas_ids) && anotacao.empresas_ids.length > 0) {
                            anotacao.empresas_ids.forEach(eid => {
                                const checkbox = document.getElementById('empresa_' + eid);
                                if (checkbox) checkbox.checked = true;
                            });
                        } else if (anotacao.empresa_id) {
                            const checkbox = document.getElementById('empresa_' + anotacao.empresa_id);
                            if (checkbox) checkbox.checked = true;
                        }
                        
                        if (anotacao.setores_ids && Array.isArray(anotacao.setores_ids) && anotacao.setores_ids.length > 0) {
                            anotacao.setores_ids.forEach(sid => {
                                const checkbox = document.getElementById('setor_' + sid);
                                if (checkbox) checkbox.checked = true;
                            });
                        } else if (anotacao.setor_id) {
                            const checkbox = document.getElementById('setor_' + anotacao.setor_id);
                            if (checkbox) checkbox.checked = true;
                        }
                        
                        if (anotacao.cargos_ids && Array.isArray(anotacao.cargos_ids) && anotacao.cargos_ids.length > 0) {
                            anotacao.cargos_ids.forEach(cid => {
                                const checkbox = document.getElementById('cargo_' + cid);
                                if (checkbox) checkbox.checked = true;
                            });
                        } else if (anotacao.cargo_id) {
                            const checkbox = document.getElementById('cargo_' + anotacao.cargo_id);
                            if (checkbox) checkbox.checked = true;
                        }
                    }, 300);
                    
                    // Carrega destinatários e depois seleciona os corretos
                    carregarDestinatarios().then(() => {
                        // Seleciona destinatários (checkboxes)
                        if (anotacao.destinatarios_usuarios && anotacao.destinatarios_usuarios.length > 0) {
                            anotacao.destinatarios_usuarios.forEach(uid => {
                                const checkbox = document.getElementById('usuario_' + uid);
                                if (checkbox) checkbox.checked = true;
                            });
                        }
                        
                        if (anotacao.destinatarios_colaboradores && anotacao.destinatarios_colaboradores.length > 0) {
                            anotacao.destinatarios_colaboradores.forEach(cid => {
                                const checkbox = document.getElementById('colab_' + cid);
                                if (checkbox) checkbox.checked = true;
                            });
                        }
                        
                        // Verifica se é "atribuir a mim" (apenas o usuário atual como destinatário)
                        if (anotacao.publico_alvo === 'especifico' && 
                            anotacao.destinatarios_usuarios && 
                            anotacao.destinatarios_usuarios.length === 1 &&
                            anotacao.destinatarios_usuarios[0] == <?= isset($usuario['id']) ? intval($usuario['id']) : 'null' ?>) {
                            document.querySelector('[name="publico_alvo"]').value = 'atribuir_mim';
                            atualizarVisibilidadeDestinatarios();
                        }
                    });
                    
                    document.getElementById('modal_anotacao_titulo').textContent = 'Editar Anotação';
                    const modal = new bootstrap.Modal(document.getElementById('modal_nova_anotacao'));
                    modal.show();
                } else {
                    Swal.fire({
                        text: data.message || 'Erro ao carregar anotação',
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    text: "Erro ao carregar anotação",
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
            });
    };
    
    window.excluirAnotacao = function(id) {
        Swal.fire({
            text: "Tem certeza que deseja excluir esta anotação?",
            icon: "warning",
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: "Sim, excluir",
            cancelButtonText: "Cancelar",
            customClass: {
                confirmButton: "btn btn-danger",
                cancelButton: "btn btn-light"
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('id', id);
                
                fetch('../api/anotacoes/excluir.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            text: data.message,
                            icon: "success",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                        carregarAnotacoes();
                    } else {
                        Swal.fire({
                            text: data.message,
                            icon: "error",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                    }
                });
            }
        });
    };
    
    window.marcarVisualizada = function(id) {
        const formData = new FormData();
        formData.append('id', id);
        
        fetch('../api/anotacoes/marcar_visualizada.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                carregarAnotacoes();
            }
        });
    };
    
    // Limpa formulário ao fechar modal
    document.getElementById('modal_nova_anotacao')?.addEventListener('hidden.bs.modal', function() {
        document.getElementById('form_nova_anotacao').reset();
        document.getElementById('anotacao_id').value = '';
        document.getElementById('modal_anotacao_titulo').textContent = 'Nova Anotação';
        document.getElementById('campo_data_notificacao').style.display = 'none';
        document.getElementById('publico_alvo_anotacao').value = 'atribuir_mim';
        atualizarVisibilidadeDestinatarios();
        
        // Limpa checkboxes
        document.querySelectorAll('input[name="destinatarios_usuarios[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name="destinatarios_colaboradores[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name="empresas[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name="setores[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name="cargos[]"]').forEach(cb => cb.checked = false);
    });
    
    // Inicializa visibilidade ao carregar
    atualizarVisibilidadeDestinatarios();
});
</script>
<!--end::Script Anotações-->
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
