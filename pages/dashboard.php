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
        
        // Total de ocorrências do colaborador
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ocorrencias WHERE colaborador_id = ?");
        $stmt->execute([$colaborador_id]);
        $total_ocorrencias = $stmt->fetch()['total'];
        
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
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id = ?");
            $stmt->execute([$usuario['empresa_id']]);
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE setor_id = ?");
            $stmt->execute([$setor_id]);
        }
        
        $total_colaboradores = $stmt->fetch()['total'];
        
        // Colaboradores ativos
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM colaboradores WHERE status = 'ativo'");
        } elseif ($usuario['role'] === 'RH') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id = ? AND status = 'ativo'");
            $stmt->execute([$usuario['empresa_id']]);
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
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM ocorrencias o
                INNER JOIN colaboradores c ON o.colaborador_id = c.id
                WHERE c.empresa_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
            ");
            $stmt->execute([$usuario['empresa_id'], $mes_atual]);
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
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM ocorrencias o
                    INNER JOIN colaboradores c ON o.colaborador_id = c.id
                    WHERE c.empresa_id = ? AND DATE_FORMAT(o.data_ocorrencia, '%Y-%m') = ?
                ");
                $stmt->execute([$usuario['empresa_id'], $mes]);
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
            $stmt = $pdo->prepare("
                SELECT o.tipo, COUNT(*) as total
                FROM ocorrencias o
                INNER JOIN colaboradores c ON o.colaborador_id = c.id
                WHERE c.empresa_id = ? AND o.data_ocorrencia >= ?
                GROUP BY o.tipo
                ORDER BY total DESC
                LIMIT 10
            ");
            $stmt->execute([$usuario['empresa_id'], $data_inicio]);
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
            $stmt = $pdo->prepare("
                SELECT status, COUNT(*) as total
                FROM colaboradores
                WHERE empresa_id = ?
                GROUP BY status
            ");
            $stmt->execute([$usuario['empresa_id']]);
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
            $stmt = $pdo->prepare("
                SELECT c.id, c.nome_completo, c.foto, COUNT(o.id) as total_ocorrencias
                FROM colaboradores c
                LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
                WHERE c.empresa_id = ? AND c.status = 'ativo'
                GROUP BY c.id, c.nome_completo, c.foto
                ORDER BY total_ocorrencias DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio, $usuario['empresa_id']]);
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
            $stmt->execute([$usuario['empresa_id']]);
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
            <div class="col-xl-8">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Como você está se sentindo?</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Registre sua emoção diária e ganhe 50 pontos!</span>
                        </h3>
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
                            <form id="form_emocao_dashboard_colab">
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
                                    
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <span class="indicator-label">Enviar humor</span>
                                        <span class="indicator-progress">Enviando...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center text-muted small">
                                <i class="ki-duotone ki-information-5 fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <span class="ms-2">Ganhe 50 pontos ao registrar sua emoção!</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Ranking de Pontos -->
            <div class="col-xl-4">
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
                            <span class="card-label fw-bold fs-3 mb-1">Minhas Ocorrências</span>
                            <span class="text-muted fw-semibold fs-7">Últimos 6 meses</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <canvas id="kt_chart_ocorrencias_mes" style="height: 350px;"></canvas>
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
                                <span class="text-muted fw-semibold fs-7 d-block mb-1">Total de Ocorrências</span>
                                <span class="text-gray-800 fw-bold fs-6"><?= $total_ocorrencias ?></span>
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
            <div class="col-xl-8">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Como você está se sentindo?</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Registre sua emoção diária e ganhe 50 pontos!</span>
                        </h3>
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
                            <form id="form_emocao_dashboard">
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
                                    
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <span class="indicator-label">Enviar humor</span>
                                        <span class="indicator-progress">Enviando...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center text-muted small">
                                <i class="ki-duotone ki-information-5 fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <span class="ms-2">Ganhe 50 pontos ao registrar sua emoção!</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Histórico de Emoções -->
            <div class="col-xl-4">
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
            <div class="col-xl-<?= $can_see_ultimas_emocoes ? '4' : '12' ?>">
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
                                    <div class="badge badge-<?= $cor_media ?> fs-6 mb-3">
                                        <?= $total_registros ?> registro(s)
                                    </div>
                                    <div class="progress h-10px w-100" style="max-width: 200px;">
                                        <div class="progress-bar bg-<?= $cor_media ?>" 
                                             role="progressbar" 
                                             style="width: <?= ($media_emocao / 5) * 100 ?>%"
                                             aria-valuenow="<?= $media_emocao ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="5">
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
            <div class="col-xl-<?= $can_see_media_emocoes ? '8' : '12' ?>">
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
                                                            <img alt="<?= htmlspecialchars($nome_pessoa) ?>" src="../uploads/fotos/<?= htmlspecialchars($foto_pessoa) ?>" />
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
                        <canvas id="kt_chart_ocorrencias_mes" style="height: 350px;"></canvas>
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
                        <canvas id="kt_chart_colaboradores_status" style="height: 350px;"></canvas>
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
                        <canvas id="kt_chart_ocorrencias_tipo" style="height: 300px;"></canvas>
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
                            $stmt->execute([$usuario['empresa_id']]);
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

<!--begin::Dashboard Personalization Scripts-->
<link href="https://cdn.jsdelivr.net/npm/gridstack@9.0.0/dist/gridstack.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/gridstack@9.0.0/dist/gridstack-all.js"></script>
<style>
.grid-stack {
    min-height: 100vh;
    padding: 10px 0;
}
.grid-stack-item {
    cursor: move !important;
    padding: 5px !important;
}
.grid-stack-item.ui-draggable-disabled {
    cursor: default !important;
}
.grid-stack-item-content {
    overflow: visible !important;
    height: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
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
.grid-stack > .row {
    margin: 0 !important;
    padding: 0 !important;
}
.grid-stack-item .col-xl-3,
.grid-stack-item .col-xl-4,
.grid-stack-item .col-xl-6,
.grid-stack-item .col-xl-8,
.grid-stack-item .col-xl-12 {
    padding: 0 !important;
    margin: 0 !important;
}
/* Garante que os cards dentro do grid item ocupem 100% */
.grid-stack-item-content > .card {
    height: 100% !important;
    margin-bottom: 0 !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let grid = null;
    let editMode = false;
    let originalConfig = null;
    let cardsDisponiveis = [];
    let cardsAdicionados = new Set();
    
    // Define cards disponíveis
    function definirCardsDisponiveis() {
        cardsDisponiveis = [
            { id: 'card_total_colaboradores', nome: 'Total de Colaboradores', descricao: 'Mostra o total de colaboradores', icone: 'ki-profile-circle', w: 3, h: 3 },
            { id: 'card_colaboradores_ativos', nome: 'Colaboradores Ativos', descricao: 'Mostra colaboradores ativos', icone: 'ki-check-circle', w: 3, h: 3 },
            { id: 'card_ocorrencias_mes', nome: 'Ocorrências no Mês', descricao: 'Ocorrências registradas no mês', icone: 'ki-notepad', w: 3, h: 3 },
            { id: 'card_colaboradores_inativos', nome: 'Colaboradores Inativos', descricao: 'Mostra colaboradores inativos', icone: 'ki-cross-circle', w: 3, h: 3 },
            { id: 'card_proximos_aniversarios', nome: 'Próximos Aniversários', descricao: 'Aniversários dos próximos 30 dias', icone: 'ki-cake', w: 6, h: 5 },
            { id: 'card_ranking_ocorrencias', nome: 'Ranking de Ocorrências', descricao: 'Ranking de colaboradores por ocorrências', icone: 'ki-chart-simple', w: 6, h: 5 },
            { id: 'card_grafico_ocorrencias_mes', nome: 'Gráfico de Ocorrências', descricao: 'Gráfico de ocorrências por mês', icone: 'ki-chart', w: 8, h: 4 },
            { id: 'card_grafico_colaboradores_status', nome: 'Colaboradores por Status', descricao: 'Gráfico de colaboradores por status', icone: 'ki-chart-pie', w: 4, h: 4 },
            { id: 'card_grafico_ocorrencias_tipo', nome: 'Ocorrências por Tipo', descricao: 'Gráfico de ocorrências por tipo', icone: 'ki-chart-bar', w: 12, h: 4 },
            { id: 'card_anotacoes', nome: 'Anotações', descricao: 'Anotações do sistema', icone: 'ki-note-edit', w: 6, h: 6 },
            { id: 'card_historico_promocoes', nome: 'Histórico de Promoções', descricao: 'Últimas promoções registradas', icone: 'ki-upgrade', w: 6, h: 6 }
        ];
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
    
    // Salva configuração
    function salvarConfiguracao() {
        if (!grid) {
            return Promise.reject(new Error('Grid não inicializado'));
        }
        
        try {
            const items = grid.save();
            
            // Filtra apenas items válidos que existem no DOM
            const cards = items
                .filter(item => {
                    // Verifica se o elemento ainda existe no DOM
                    const el = document.querySelector(`[data-gs-id="${item.id}"]`);
                    return el !== null && item.id;
                })
                .map((item, index) => ({
                    id: item.id,
                    x: parseInt(item.x) || 0,
                    y: parseInt(item.y) || 0,
                    w: parseInt(item.w) || 3,
                    h: parseInt(item.h) || 3,
                    visible: true
                }));
            
            if (cards.length === 0) {
                throw new Error('Nenhum card válido para salvar');
            }
            
            return fetch('../api/dashboard/salvar_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ cards: cards })
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
            text: 'Tem certeza que deseja limpar todo o layout? Todos os cards serão removidos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, limpar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-light'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (grid) {
                    // Remove todos os items do grid
                    const items = grid.save();
                    items.forEach(item => {
                        try {
                            grid.removeWidget(item.el, true); // true = remove do DOM
                        } catch (e) {
                            console.warn('Erro ao remover widget:', e);
                            // Força remoção do DOM se grid.removeWidget falhar
                            if (item.el && item.el.parentNode) {
                                item.el.remove();
                            }
                        }
                    });
                    
                    // Remove também qualquer card que possa ter ficado no container
                    const container = document.getElementById('kt_content_container');
                    if (container) {
                        const remainingItems = container.querySelectorAll('.grid-stack-item');
                        remainingItems.forEach(item => {
                            item.remove();
                        });
                    }
                    
                    cardsAdicionados.clear();
                    
                    // Limpa configuração no servidor também
                    fetch('../api/dashboard/salvar_config.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ cards: [] })
                    }).catch(err => console.error('Erro ao limpar configuração no servidor:', err));
                    
                    Swal.fire({
                        text: 'Layout limpo! Adicione cards usando o botão "Adicionar Cards".',
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: 'Ok',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                } else {
                    Swal.fire({
                        text: 'Grid não inicializado',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'Ok',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                }
            }
        });
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
        
        // Encontra o card original no DOM para clonar
        // Primeiro tenta encontrar por data-card-id (no HTML original)
        let cardOriginal = document.querySelector(`[data-card-id="${cardInfo.id}"]`);
        
        // Se não encontrou, tenta encontrar por data-gs-id (já convertido)
        if (!cardOriginal) {
            cardOriginal = document.querySelector(`[data-gs-id="${cardInfo.id}"]`);
        }
        
        let novoCard;
        if (cardOriginal) {
            // Clona o card original
            novoCard = cardOriginal.cloneNode(true);
            
            // Remove atributos do GridStack se existirem
            novoCard.removeAttribute('data-gs-x');
            novoCard.removeAttribute('data-gs-y');
            novoCard.removeAttribute('data-gs-w');
            novoCard.removeAttribute('data-gs-h');
            
            // Remove classes que podem interferir
            novoCard.classList.remove('grid-stack-item');
            novoCard.classList.remove('col-xl-3', 'col-xl-4', 'col-xl-6', 'col-xl-8', 'col-xl-12');
            
            // Garante que tem o wrapper grid-stack-item-content
            if (!novoCard.querySelector('.grid-stack-item-content')) {
                const content = document.createElement('div');
                content.className = 'grid-stack-item-content';
                content.style.width = '100%';
                content.style.height = '100%';
                while (novoCard.firstChild) {
                    content.appendChild(novoCard.firstChild);
                }
                novoCard.appendChild(content);
            }
        } else {
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
        
        novoCard.classList.add('grid-stack-item');
        novoCard.setAttribute('data-gs-id', cardInfo.id);
        novoCard.style.padding = '0';
        novoCard.style.margin = '0';
        
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
        
        // Adiciona o card ao grid
        grid.addWidget(novoCard, {
            x: posX,
            y: posY,
            w: cardInfo.w,
            h: cardInfo.h
        });
        
        cardsAdicionados.add(cardInfo.id);
        
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
        const cardsFiltrados = cardsDisponiveis.filter(card => 
            card.nome.toLowerCase().includes(filtro) || 
            card.descricao.toLowerCase().includes(filtro)
        );
        
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
        const container = document.getElementById('kt_content_container');
        if (!container) return [];
        
        // Adiciona classe grid-stack ao container se não tiver
        if (!container.classList.contains('grid-stack')) {
            container.classList.add('grid-stack');
        }
        
        // Se não há configuração, usa layout padrão
        let layoutParaUsar = configuracao;
        if (!layoutParaUsar || layoutParaUsar.length === 0) {
            layoutParaUsar = gerarLayoutPadrao();
        }
        
        // Cria mapa de configuração
        const configMap = {};
        if (layoutParaUsar && Array.isArray(layoutParaUsar)) {
            layoutParaUsar.forEach(cfg => {
                if (cfg.id) {
                    configMap[cfg.id] = cfg;
                }
            });
        }
        
        // Encontra todos os cards principais (col-xl-* dentro de rows)
        const rows = container.querySelectorAll('.row');
        let globalIndex = 0;
        const cardsProcessados = [];
        
        rows.forEach(row => {
            const cards = row.querySelectorAll(':scope > .col-xl-3, :scope > .col-xl-4, :scope > .col-xl-6, :scope > .col-xl-8, :scope > .col-xl-12');
            
            cards.forEach((card, index) => {
                // Verifica se já é um grid item
                if (card.classList.contains('grid-stack-item')) return;
                
                // Salva classes originais antes de remover
                const originalClasses = card.className;
                
                // Remove classes Bootstrap que podem interferir
                card.classList.remove('col-xl-3', 'col-xl-4', 'col-xl-6', 'col-xl-8', 'col-xl-12', 'col-md-3', 'col-md-4', 'col-md-6', 'col-md-8', 'col-md-12');
                
                // Remove padding/margin do Bootstrap
                card.style.padding = '0';
                card.style.margin = '0';
                
                // Cria ID único se não tiver
                let cardId = card.getAttribute('data-gs-id') || card.getAttribute('data-card-id');
                if (!cardId) {
                    // Tenta encontrar um link ou título para identificar
                    const link = card.querySelector('a[href]');
                    const title = card.querySelector('.card-label, h3, h4, h5');
                    if (link) {
                        const href = link.getAttribute('href');
                        cardId = 'card_' + href.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                    } else if (title) {
                        cardId = 'card_' + title.textContent.trim().replace(/[^a-z0-9]/gi, '_').toLowerCase().substring(0, 50);
                    } else {
                        cardId = 'card_' + globalIndex;
                    }
                }
                
                // Verifica se há configuração para este card
                const configCard = configMap[cardId];
                
                // Se há configuração e o card não está visível, oculta
                if (configCard && configCard.visible === false) {
                    card.style.display = 'none';
                    return; // Não processa cards invisíveis
                }
                
                // Calcula tamanho e posição baseado na configuração ou layout padrão
                let width = 3;
                let height = 5;
                let posX = 0;
                let posY = 0;
                
                if (configCard) {
                    // Usa configuração (salva ou padrão)
                    width = parseInt(configCard.w) || 3;
                    height = parseInt(configCard.h) || 5;
                    posX = parseInt(configCard.x) || 0;
                    posY = parseInt(configCard.y) || 0;
                } else {
                    // Fallback: calcula baseado na classe original
                    if (originalClasses.includes('col-xl-12')) width = 12;
                    else if (originalClasses.includes('col-xl-8')) width = 8;
                    else if (originalClasses.includes('col-xl-6')) width = 6;
                    else if (originalClasses.includes('col-xl-4')) width = 4;
                    else if (originalClasses.includes('col-xl-3')) width = 3;
                    
                    // Calcula altura baseada no conteúdo
                    const cardElement = card.querySelector('.card');
                    if (cardElement) {
                        const tempHeight = cardElement.offsetHeight || cardElement.scrollHeight || 350;
                        height = Math.max(5, Math.ceil(tempHeight / 70));
                    }
                }
                
                // Adiciona classes e atributos do GridStack
                card.classList.add('grid-stack-item');
                card.setAttribute('data-gs-id', cardId);
                card.setAttribute('data-gs-x', posX);
                card.setAttribute('data-gs-y', posY);
                card.setAttribute('data-gs-w', width);
                card.setAttribute('data-gs-h', height);
                
                // Envolve o conteúdo se necessário
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
                
                // Move o card para fora da row e diretamente no container
                container.appendChild(card);
                cardsProcessados.push({ id: cardId, element: card });
                
                globalIndex++;
            });
            
            // Remove row vazia após processar todos os cards
            setTimeout(() => {
                if (row.querySelectorAll(':scope > .col-xl-3, :scope > .col-xl-4, :scope > .col-xl-6, :scope > .col-xl-8, :scope > .col-xl-12').length === 0) {
                    row.remove();
                }
            }, 100);
        });
        
        console.log('Conversão concluída:', cardsProcessados.length, 'cards convertidos');
        return cardsProcessados;
    }
    
    // Inicializa GridStack
    function inicializarGrid(configuracao) {
        const container = document.getElementById('kt_content_container');
        if (!container) {
            console.error('Container kt_content_container não encontrado');
            return;
        }
        
        // Converte cards para grid items primeiro (passa configuração para aplicar posições)
        const cardsProcessados = converterCardsParaGrid(configuracao);
        
        // Aguarda um pouco para garantir que a conversão foi feita
        setTimeout(() => {
            // Remove grid anterior se existir
            if (grid) {
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
                console.warn('Nenhum grid item encontrado');
                return;
            }
            
            console.log('Inicializando GridStack com', gridItems.length, 'itens');
            
            // Adiciona classe grid-stack ao container
            container.classList.add('grid-stack');
            
            // Inicializa GridStack
            grid = GridStack.init({
                column: 12,
                cellHeight: 70,
                margin: 15,
                disableResize: !editMode,
                disableDrag: !editMode,
                animate: true,
                float: false,
                resizable: {
                    handles: 'e, se, s, sw, w'
                },
                draggable: {
                    handle: '.grid-stack-item',
                    appendTo: 'body',
                    scroll: false
                },
                minRow: 1
            }, container);
            
            // Aplica configuração se existir, senão usa layout padrão
            setTimeout(() => {
                if (configuracao && configuracao.length > 0) {
                    console.log('Carregando configuração salva:', configuracao);
                    try {
                        // GridStack precisa que os elementos já tenham os atributos data-gs-*
                        // A conversão já fez isso, então apenas força atualização
                        grid.load(configuracao, false); // false = não remove items não na lista
                    } catch (e) {
                        console.error('Erro ao carregar configuração:', e);
                        // Se falhar, tenta usar layout padrão
                        setTimeout(() => aplicarLayoutPadrao(), 200);
                    }
                } else {
                    // Se não tem configuração, aplica layout padrão
                    console.log('Aplicando layout padrão');
                    aplicarLayoutPadrao();
                }
            }, 300);
            
            // Força habilitação se estiver em modo de edição
            if (editMode && grid) {
                setTimeout(() => {
                    try {
                        grid.enable();
                        console.log('GridStack habilitado para edição');
                    } catch (e) {
                        console.error('Erro ao habilitar grid:', e);
                    }
                }, 300);
            }
        }, 200);
    }
    
    // Aplica layout padrão aos cards existentes
    function aplicarLayoutPadrao() {
        if (!grid) {
            console.warn('Grid não inicializado, não é possível aplicar layout padrão');
            return;
        }
        
        const layoutPadrao = gerarLayoutPadrao();
        const items = grid.save();
        const itemsMap = {};
        
        // Cria mapa dos items existentes
        items.forEach(item => {
            if (item.id && item.el) {
                itemsMap[item.id] = item;
            }
        });
        
        // Aplica layout padrão apenas aos cards que existem
        const layoutAplicar = layoutPadrao.filter(card => itemsMap[card.id]);
        
        if (layoutAplicar.length > 0) {
            console.log('Aplicando layout padrão a', layoutAplicar.length, 'cards');
            
            // Usa grid.load para aplicar todas as posições de uma vez
            try {
                grid.load(layoutAplicar.map(card => ({
                    id: card.id,
                    x: card.x,
                    y: card.y,
                    w: card.w,
                    h: card.h
                })), false);
            } catch (e) {
                console.error('Erro ao aplicar layout padrão via grid.load:', e);
                // Fallback: atualiza um por um
                layoutAplicar.forEach(card => {
                    const item = itemsMap[card.id];
                    if (item && item.el) {
                        try {
                            grid.update(item.el, {
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
            }
        } else {
            console.warn('Nenhum card encontrado para aplicar layout padrão');
        }
    }
    
    // Entra no modo de edição
    function entrarModoEdicao() {
        editMode = true;
        document.body.classList.add('dashboard-edit-mode');
        document.getElementById('btn_personalizar_dashboard').classList.add('d-none');
        document.getElementById('btn_salvar_dashboard').classList.remove('d-none');
        document.getElementById('btn_adicionar_cards').classList.remove('d-none');
        document.getElementById('btn_limpar_layout').classList.remove('d-none');
        document.getElementById('btn_cancelar_dashboard').classList.remove('d-none');
        
        // Aguarda um pouco para garantir que o DOM está pronto
        setTimeout(() => {
            if (grid) {
                console.log('Habilitando grid existente');
                grid.enable();
            } else {
                console.log('Inicializando novo grid');
                carregarConfiguracao().then(config => {
                    // Se não há configuração, usa layout padrão
                    if (!config || config.length === 0) {
                        inicializarGrid(null); // null força uso do layout padrão
                    } else {
                        inicializarGrid(config);
                    }
                });
            }
        }, 200);
    }
    
    // Sai do modo de edição
    function sairModoEdicao(salvar = false) {
        if (salvar && grid) {
            salvarConfiguracao().then(() => {
                editMode = false;
                document.body.classList.remove('dashboard-edit-mode');
                document.getElementById('btn_personalizar_dashboard').classList.remove('d-none');
                document.getElementById('btn_salvar_dashboard').classList.add('d-none');
                document.getElementById('btn_adicionar_cards').classList.add('d-none');
                document.getElementById('btn_limpar_layout').classList.add('d-none');
                document.getElementById('btn_cancelar_dashboard').classList.add('d-none');
                
                if (grid) {
                    grid.disable();
                }
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
        editMode = false;
        document.body.classList.remove('dashboard-edit-mode');
        document.getElementById('btn_personalizar_dashboard').classList.remove('d-none');
        document.getElementById('btn_salvar_dashboard').classList.add('d-none');
        document.getElementById('btn_adicionar_cards').classList.add('d-none');
        document.getElementById('btn_limpar_layout').classList.add('d-none');
        document.getElementById('btn_cancelar_dashboard').classList.add('d-none');
            
            if (grid) {
                grid.disable();
                // Restaura configuração original se cancelou
                if (originalConfig) {
                    grid.load(originalConfig);
                }
            }
        }
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
    
    // Inicializa cards disponíveis
    definirCardsDisponiveis();
    
    // Carrega configuração ao iniciar (sem modo de edição)
    // Não inicializa grid automaticamente - só quando entrar em modo de edição
    // Isso mantém o layout Bootstrap normal quando não está editando
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
</script>
<!--end::Chart Scripts-->

<!--begin::Emoções Script-->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seleção visual de emoção (funciona para ambos os formulários)
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
    
    // Submit do formulário de emoção (Admin/RH/GESTOR)
    const formEmocao = document.getElementById('form_emocao_dashboard');
    if (formEmocao) {
        formEmocao.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const indicator = btn.querySelector('.indicator-label');
            const progress = btn.querySelector('.indicator-progress');
            
            btn.setAttribute('data-kt-indicator', 'on');
            indicator.style.display = 'none';
            progress.style.display = 'inline-block';
            
            fetch('../api/registrar_emocao.php', {
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
                        location.reload();
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
                    text: "Erro ao registrar emoção",
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
    
    // Submit do formulário de emoção (Colaborador)
    const formEmocaoColab = document.getElementById('form_emocao_dashboard_colab');
    if (formEmocaoColab) {
        formEmocaoColab.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const indicator = btn.querySelector('.indicator-label');
            const progress = btn.querySelector('.indicator-progress');
            
            btn.setAttribute('data-kt-indicator', 'on');
            indicator.style.display = 'none';
            progress.style.display = 'inline-block';
            
            fetch('../api/registrar_emocao.php', {
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
                        location.reload();
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
                    text: "Erro ao registrar emoção",
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
});
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
                                    $stmt_emp = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo' ORDER BY nome_fantasia");
                                    $stmt_emp->execute([$usuario['empresa_id']]);
                                    $empresas_anotacao = $stmt_emp->fetchAll();
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
                                    $stmt_set = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
                                    $stmt_set->execute([$usuario['empresa_id']]);
                                    $setores_anotacao = $stmt_set->fetchAll();
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
                            anotacao.destinatarios_usuarios[0] == <?= $usuario['id'] ?>) {
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
