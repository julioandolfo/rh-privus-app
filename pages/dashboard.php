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
    try {
        // Total de colaboradores
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM colaboradores");
        } elseif ($usuario['role'] === 'RH') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE empresa_id = ?");
            $stmt->execute([$usuario['empresa_id']]);
        } elseif ($usuario['role'] === 'GESTOR') {
            // Busca setor do gestor
            $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            $user_data = $stmt->fetch();
            $setor_id = $user_data['setor_id'] ?? 0;
            
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
        
        // Ranking de ocorrências (últimos 30 dias)
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->prepare("
                SELECT c.nome_completo, COUNT(o.id) as total_ocorrencias
                FROM colaboradores c
                LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
                GROUP BY c.id, c.nome_completo
                ORDER BY total_ocorrencias DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio]);
        } elseif ($usuario['role'] === 'RH') {
            $stmt = $pdo->prepare("
                SELECT c.nome_completo, COUNT(o.id) as total_ocorrencias
                FROM colaboradores c
                LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
                WHERE c.empresa_id = ?
                GROUP BY c.id, c.nome_completo
                ORDER BY total_ocorrencias DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio, $usuario['empresa_id']]);
        } elseif ($usuario['role'] === 'GESTOR') {
            $stmt = $pdo->prepare("
                SELECT c.nome_completo, COUNT(o.id) as total_ocorrencias
                FROM colaboradores c
                LEFT JOIN ocorrencias o ON c.id = o.colaborador_id AND o.data_ocorrencia >= ?
                WHERE c.setor_id = ?
                GROUP BY c.id, c.nome_completo
                ORDER BY total_ocorrencias DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio, $setor_id]);
        } else {
            $ranking = [];
        }
        
        $ranking = isset($stmt) ? $stmt->fetchAll() : [];
        
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
    }
}
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
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
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-3">
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
            <div class="col-xl-3">
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
            <div class="col-xl-3">
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
            <div class="col-xl-3">
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
        
        <!--begin::Row-->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-8">
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
            <div class="col-xl-4">
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
            <div class="col-xl-12">
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
        
        <!--begin::Row-->
        <?php if (!empty($ranking)): ?>
        <div class="row g-5 g-xl-8">
            <div class="col-xl-12">
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
                                            <span class="text-gray-900 fw-bold d-block fs-6"><?= htmlspecialchars($item['nome_completo']) ?></span>
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
        </div>
        <!--end::Row-->
        <?php endif; ?>
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
