<?php
/**
 * Gestão de Eventos - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('eventos.php');

$pdo = getDB();

// Verifica e cria as tabelas se não existirem
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'eventos'");
    if ($stmt->rowCount() == 0) {
        // Cria tabela de eventos
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS eventos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(255) NOT NULL,
                descricao TEXT NULL,
                local VARCHAR(255) NULL,
                link_virtual VARCHAR(500) NULL,
                data_evento DATE NOT NULL,
                hora_inicio TIME NOT NULL,
                hora_fim TIME NULL,
                tipo ENUM('reuniao', 'treinamento', 'confraternizacao', 'palestra', 'workshop', 'outro') NOT NULL DEFAULT 'reuniao',
                status ENUM('agendado', 'em_andamento', 'concluido', 'cancelado') NOT NULL DEFAULT 'agendado',
                confirmacao_obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
                empresa_id INT NULL,
                criado_por_usuario_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_eventos_data (data_evento),
                INDEX idx_eventos_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Cria tabela de participantes
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS eventos_participantes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                evento_id INT NOT NULL,
                colaborador_id INT NOT NULL,
                status_confirmacao ENUM('pendente', 'confirmado', 'recusado', 'talvez') NOT NULL DEFAULT 'pendente',
                motivo_recusa TEXT NULL,
                token_confirmacao VARCHAR(64) NULL,
                presente TINYINT(1) NULL,
                data_confirmacao DATETIME NULL,
                data_convite_enviado DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_evento_colaborador (evento_id, colaborador_id),
                FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (PDOException $e) {
    // Ignora erro se as tabelas já existirem
}

// Processa ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $titulo = sanitize($_POST['titulo'] ?? '');
        $descricao = $_POST['descricao'] ?? '';
        $local = sanitize($_POST['local'] ?? '');
        $link_virtual = sanitize($_POST['link_virtual'] ?? '');
        $data_evento = $_POST['data_evento'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fim = $_POST['hora_fim'] ?? '';
        $tipo = $_POST['tipo'] ?? 'reuniao';
        $confirmacao_obrigatoria = isset($_POST['confirmacao_obrigatoria']) ? 1 : 0;
        $participantes = $_POST['participantes'] ?? [];
        
        if (empty($titulo) || empty($data_evento) || empty($hora_inicio)) {
            redirect('eventos.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            $pdo->beginTransaction();
            
            if ($id > 0) {
                // Atualiza
                $stmt = $pdo->prepare("
                    UPDATE eventos 
                    SET titulo = ?, descricao = ?, local = ?, link_virtual = ?, 
                        data_evento = ?, hora_inicio = ?, hora_fim = ?, tipo = ?, 
                        confirmacao_obrigatoria = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $titulo, $descricao, $local, $link_virtual,
                    $data_evento, $hora_inicio, $hora_fim ?: null, $tipo,
                    $confirmacao_obrigatoria, $id
                ]);
                $evento_id = $id;
            } else {
                // Insere
                $stmt = $pdo->prepare("
                    INSERT INTO eventos 
                    (titulo, descricao, local, link_virtual, data_evento, hora_inicio, hora_fim, 
                     tipo, confirmacao_obrigatoria, empresa_id, criado_por_usuario_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $titulo, $descricao, $local, $link_virtual,
                    $data_evento, $hora_inicio, $hora_fim ?: null, $tipo,
                    $confirmacao_obrigatoria, 
                    $_SESSION['usuario']['empresa_id'] ?? null,
                    $_SESSION['usuario']['id']
                ]);
                $evento_id = $pdo->lastInsertId();
            }
            
            // Gerencia participantes
            if (!empty($participantes)) {
                // Busca participantes atuais
                $stmt = $pdo->prepare("SELECT colaborador_id FROM eventos_participantes WHERE evento_id = ?");
                $stmt->execute([$evento_id]);
                $atuais = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $novos = array_diff($participantes, $atuais);
                $removidos = array_diff($atuais, $participantes);
                
                // Remove participantes desmarcados
                if (!empty($removidos)) {
                    $placeholders = implode(',', array_fill(0, count($removidos), '?'));
                    $stmt = $pdo->prepare("DELETE FROM eventos_participantes WHERE evento_id = ? AND colaborador_id IN ($placeholders)");
                    $stmt->execute(array_merge([$evento_id], $removidos));
                }
                
                // Adiciona novos participantes
                $stmt_insert = $pdo->prepare("
                    INSERT INTO eventos_participantes (evento_id, colaborador_id, token_confirmacao)
                    VALUES (?, ?, ?)
                ");
                
                foreach ($novos as $colab_id) {
                    $token = bin2hex(random_bytes(32));
                    $stmt_insert->execute([$evento_id, $colab_id, $token]);
                }
            }
            
            $pdo->commit();
            
            // Envia emails para novos participantes se solicitado
            if (isset($_POST['enviar_convites']) && !empty($novos)) {
                require_once __DIR__ . '/../includes/email_templates.php';
                $enviados = enviar_convites_evento($evento_id, $novos);
                redirect('eventos.php', "Evento salvo! Convites enviados: {$enviados['enviados']}, Erros: {$enviados['erros']}", 'success');
            } else {
                redirect('eventos.php', 'Evento salvo com sucesso!', 'success');
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            redirect('eventos.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
        
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ?");
            $stmt->execute([$id]);
            redirect('eventos.php', 'Evento excluído com sucesso!', 'success');
        } catch (PDOException $e) {
            redirect('eventos.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
        
    } elseif ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("UPDATE eventos SET status = 'cancelado' WHERE id = ?");
            $stmt->execute([$id]);
            redirect('eventos.php', 'Evento cancelado!', 'success');
        } catch (PDOException $e) {
            redirect('eventos.php', 'Erro ao cancelar: ' . $e->getMessage(), 'error');
        }
        
    } elseif ($action === 'enviar_convites') {
        $id = (int)($_POST['id'] ?? 0);
        
        require_once __DIR__ . '/../includes/email_templates.php';
        
        // Busca participantes que ainda não receberam convite
        $stmt = $pdo->prepare("
            SELECT colaborador_id FROM eventos_participantes 
            WHERE evento_id = ? AND data_convite_enviado IS NULL
        ");
        $stmt->execute([$id]);
        $pendentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($pendentes)) {
            redirect('eventos.php', 'Todos os convites já foram enviados!', 'info');
        }
        
        $resultado = enviar_convites_evento($id, $pendentes);
        redirect('eventos.php', "Convites enviados: {$resultado['enviados']}, Erros: {$resultado['erros']}", 
                 $resultado['erros'] > 0 ? 'warning' : 'success');
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_data = $_GET['data'] ?? '';

// Monta query com filtros
$where = [];
$params = [];

if ($filtro_status) {
    $where[] = "e.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_tipo) {
    $where[] = "e.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_data) {
    $where[] = "e.data_evento = ?";
    $params[] = $filtro_data;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Busca eventos
$sql = "
    SELECT e.*, 
           u.nome as criador_nome,
           (SELECT COUNT(*) FROM eventos_participantes WHERE evento_id = e.id) as total_convidados,
           (SELECT COUNT(*) FROM eventos_participantes WHERE evento_id = e.id AND status_confirmacao = 'confirmado') as total_confirmados,
           (SELECT COUNT(*) FROM eventos_participantes WHERE evento_id = e.id AND status_confirmacao = 'recusado') as total_recusados,
           (SELECT COUNT(*) FROM eventos_participantes WHERE evento_id = e.id AND status_confirmacao = 'pendente') as total_pendentes
    FROM eventos e
    LEFT JOIN usuarios u ON e.criado_por_usuario_id = u.id
    {$where_sql}
    ORDER BY e.data_evento DESC, e.hora_inicio DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$eventos = $stmt->fetchAll();

// Busca colaboradores para o select
require_once __DIR__ . '/../includes/select_colaborador.php';
$colaboradores = get_colaboradores_disponiveis($pdo, $_SESSION['usuario']);

// Labels de tipos
$tipos_evento = [
    'reuniao' => 'Reunião',
    'treinamento' => 'Treinamento',
    'confraternizacao' => 'Confraternização',
    'palestra' => 'Palestra',
    'workshop' => 'Workshop',
    'outro' => 'Outro'
];

// Labels de status
$status_evento = [
    'agendado' => ['label' => 'Agendado', 'class' => 'primary'],
    'em_andamento' => ['label' => 'Em Andamento', 'class' => 'info'],
    'concluido' => ['label' => 'Concluído', 'class' => 'success'],
    'cancelado' => ['label' => 'Cancelado', 'class' => 'danger']
];

$page_title = 'Gestão de Eventos';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Gestão de Eventos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Comunicação</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Eventos</li>
            </ul>
        </div>
        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_evento">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Evento
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card - Filtros-->
        <div class="card mb-5">
            <div class="card-body py-5">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($status_evento as $key => $info): ?>
                            <option value="<?= $key ?>" <?= $filtro_status === $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($tipos_evento as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $filtro_tipo === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" class="form-control form-control-solid" value="<?= htmlspecialchars($filtro_data) ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                        <a href="eventos.php" class="btn btn-light">Limpar</a>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bold">Lista de Eventos</h3>
                </div>
                <div class="card-toolbar">
                    <span class="badge badge-light-primary fs-7"><?= count($eventos) ?> eventos</span>
                </div>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_eventos_table">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Evento</th>
                                <th class="min-w-100px">Data/Hora</th>
                                <th class="min-w-100px">Tipo</th>
                                <th class="min-w-120px">Participantes</th>
                                <th class="min-w-80px">Status</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php if (empty($eventos)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">
                                    <i class="ki-duotone ki-calendar fs-3x text-gray-300 mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <p class="mb-0">Nenhum evento encontrado</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($eventos as $evento): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <a href="#" class="text-gray-800 text-hover-primary mb-1 fw-bold" 
                                           onclick="verEvento(<?= $evento['id'] ?>); return false;">
                                            <?= htmlspecialchars($evento['titulo']) ?>
                                        </a>
                                        <?php if ($evento['local']): ?>
                                        <small class="text-muted">
                                            <i class="ki-duotone ki-geolocation fs-7 me-1">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <?= htmlspecialchars($evento['local']) ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800"><?= date('d/m/Y', strtotime($evento['data_evento'])) ?></span>
                                        <small class="text-muted">
                                            <?= date('H:i', strtotime($evento['hora_inicio'])) ?>
                                            <?php if ($evento['hora_fim']): ?>
                                            - <?= date('H:i', strtotime($evento['hora_fim'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-info"><?= $tipos_evento[$evento['tipo']] ?? $evento['tipo'] ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800"><?= $evento['total_convidados'] ?> convidados</span>
                                        <div class="d-flex gap-1 mt-1">
                                            <?php if ($evento['total_confirmados'] > 0): ?>
                                            <span class="badge badge-light-success"><?= $evento['total_confirmados'] ?> ✓</span>
                                            <?php endif; ?>
                                            <?php if ($evento['total_recusados'] > 0): ?>
                                            <span class="badge badge-light-danger"><?= $evento['total_recusados'] ?> ✗</span>
                                            <?php endif; ?>
                                            <?php if ($evento['total_pendentes'] > 0): ?>
                                            <span class="badge badge-light-warning"><?= $evento['total_pendentes'] ?> ?</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php $st = $status_evento[$evento['status']] ?? ['label' => $evento['status'], 'class' => 'secondary']; ?>
                                    <span class="badge badge-light-<?= $st['class'] ?>"><?= $st['label'] ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-light btn-active-light-primary dropdown-toggle" data-bs-toggle="dropdown">
                                            Ações
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="verEvento(<?= $evento['id'] ?>); return false;">
                                                <i class="ki-duotone ki-eye fs-5 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                Ver Detalhes
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="editarEvento(<?= $evento['id'] ?>); return false;">
                                                <i class="ki-duotone ki-pencil fs-5 me-2"><span class="path1"></span><span class="path2"></span></i>
                                                Editar
                                            </a></li>
                                            <?php if ($evento['total_pendentes'] > 0): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="enviar_convites">
                                                    <input type="hidden" name="id" value="<?= $evento['id'] ?>">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ki-duotone ki-sms fs-5 me-2"><span class="path1"></span><span class="path2"></span></i>
                                                        Enviar Convites Pendentes
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($evento['status'] === 'agendado'): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja cancelar este evento?');">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="id" value="<?= $evento['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="ki-duotone ki-cross-circle fs-5 me-2"><span class="path1"></span><span class="path2"></span></i>
                                                        Cancelar Evento
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este evento?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $evento['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="ki-duotone ki-trash fs-5 me-2"><span class="path1"></span><span class="path2"></span></i>
                                                        Excluir
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Novo/Editar Evento-->
<div class="modal fade" id="kt_modal_evento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_evento_title">Novo Evento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_evento_form" method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="evento_id" value="0">
                    
                    <div class="row mb-7">
                        <div class="col-md-8">
                            <label class="required fw-semibold fs-6 mb-2">Título do Evento</label>
                            <input type="text" name="titulo" id="evento_titulo" class="form-control form-control-solid" required placeholder="Ex: Reunião de Equipe">
                        </div>
                        <div class="col-md-4">
                            <label class="required fw-semibold fs-6 mb-2">Tipo</label>
                            <select name="tipo" id="evento_tipo" class="form-select form-select-solid" required>
                                <?php foreach ($tipos_evento as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-4">
                            <label class="required fw-semibold fs-6 mb-2">Data</label>
                            <input type="date" name="data_evento" id="evento_data" class="form-control form-control-solid" required>
                        </div>
                        <div class="col-md-4">
                            <label class="required fw-semibold fs-6 mb-2">Hora Início</label>
                            <input type="time" name="hora_inicio" id="evento_hora_inicio" class="form-control form-control-solid" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-semibold fs-6 mb-2">Hora Fim</label>
                            <input type="time" name="hora_fim" id="evento_hora_fim" class="form-control form-control-solid">
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Local</label>
                            <input type="text" name="local" id="evento_local" class="form-control form-control-solid" placeholder="Ex: Sala de Reuniões 1">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Link Virtual (Meet, Zoom, etc)</label>
                            <input type="url" name="link_virtual" id="evento_link_virtual" class="form-control form-control-solid" placeholder="https://meet.google.com/xxx-xxxx-xxx">
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" id="evento_descricao" class="form-control form-control-solid" rows="4" placeholder="Descreva o evento, pauta, objetivos..."></textarea>
                    </div>
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Participantes</label>
                        <select name="participantes[]" id="evento_participantes" class="form-select form-select-solid" multiple data-control="select2" data-placeholder="Selecione os participantes..." data-allow-clear="true">
                            <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= $colab['id'] ?>"><?= htmlspecialchars($colab['nome_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Selecione um ou mais colaboradores para convidar</small>
                    </div>
                    
                    <div class="mb-7">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="confirmacao_obrigatoria" id="evento_confirmacao" value="1" checked>
                            <label class="form-check-label" for="evento_confirmacao">
                                Solicitar confirmação de presença
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="enviar_convites" id="evento_enviar_convites" value="1" checked>
                            <label class="form-check-label" for="evento_enviar_convites">
                                Enviar convites por email imediatamente
                            </label>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar Evento</span>
                            <span class="indicator-progress">Salvando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Ver Evento-->
<div class="modal fade" id="kt_modal_ver_evento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-700px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Detalhes do Evento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="evento_detalhes_content"></div>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
"use strict";

// Dados dos eventos para JavaScript
var eventosData = <?= json_encode($eventos) ?>;

// Abre modal para novo evento
document.querySelector('[data-bs-target="#kt_modal_evento"]').addEventListener('click', function() {
    document.getElementById('modal_evento_title').textContent = 'Novo Evento';
    document.getElementById('kt_evento_form').reset();
    document.getElementById('evento_id').value = '0';
    
    // Limpa Select2
    if (typeof $ !== 'undefined') {
        $('#evento_participantes').val([]).trigger('change');
    }
});

// Inicializa Select2
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#evento_participantes').select2({
            dropdownParent: $('#kt_modal_evento')
        });
    }
});

// Editar evento
function editarEvento(id) {
    // Busca dados via AJAX
    fetch('eventos_api.php?action=get&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                var ev = data.evento;
                document.getElementById('modal_evento_title').textContent = 'Editar Evento';
                document.getElementById('evento_id').value = ev.id;
                document.getElementById('evento_titulo').value = ev.titulo;
                document.getElementById('evento_tipo').value = ev.tipo;
                document.getElementById('evento_data').value = ev.data_evento;
                document.getElementById('evento_hora_inicio').value = ev.hora_inicio;
                document.getElementById('evento_hora_fim').value = ev.hora_fim || '';
                document.getElementById('evento_local').value = ev.local || '';
                document.getElementById('evento_link_virtual').value = ev.link_virtual || '';
                document.getElementById('evento_descricao').value = ev.descricao || '';
                document.getElementById('evento_confirmacao').checked = ev.confirmacao_obrigatoria == 1;
                
                // Atualiza Select2 com participantes
                if (typeof $ !== 'undefined' && data.participantes) {
                    $('#evento_participantes').val(data.participantes).trigger('change');
                }
                
                var modal = new bootstrap.Modal(document.getElementById('kt_modal_evento'));
                modal.show();
            } else {
                alert('Erro ao carregar evento: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(err => {
            alert('Erro ao carregar evento');
            console.error(err);
        });
}

// Ver detalhes do evento
function verEvento(id) {
    fetch('eventos_api.php?action=get&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                var ev = data.evento;
                var html = `
                    <div class="mb-5">
                        <h3 class="text-gray-900">${escapeHtml(ev.titulo)}</h3>
                        <span class="badge badge-light-info">${escapeHtml(ev.tipo_label || ev.tipo)}</span>
                        <span class="badge badge-light-${ev.status_class || 'secondary'}">${escapeHtml(ev.status_label || ev.status)}</span>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-6">
                            <strong>Data:</strong><br>
                            ${ev.data_evento_formatada || ev.data_evento}
                        </div>
                        <div class="col-6">
                            <strong>Horário:</strong><br>
                            ${ev.hora_inicio}${ev.hora_fim ? ' - ' + ev.hora_fim : ''}
                        </div>
                    </div>
                    
                    ${ev.local ? `<div class="mb-5"><strong>Local:</strong><br>${escapeHtml(ev.local)}</div>` : ''}
                    ${ev.link_virtual ? `<div class="mb-5"><strong>Link Virtual:</strong><br><a href="${escapeHtml(ev.link_virtual)}" target="_blank">${escapeHtml(ev.link_virtual)}</a></div>` : ''}
                    ${ev.descricao ? `<div class="mb-5"><strong>Descrição:</strong><br>${escapeHtml(ev.descricao)}</div>` : ''}
                    
                    <hr>
                    
                    <h5 class="mb-3">Participantes (${data.participantes_detalhes ? data.participantes_detalhes.length : 0})</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Status</th>
                                    <th>Data Confirmação</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                if (data.participantes_detalhes && data.participantes_detalhes.length > 0) {
                    data.participantes_detalhes.forEach(function(p) {
                        var statusClass = {
                            'pendente': 'warning',
                            'confirmado': 'success',
                            'recusado': 'danger',
                            'talvez': 'info'
                        };
                        html += `
                            <tr>
                                <td>${escapeHtml(p.nome_completo)}</td>
                                <td><span class="badge badge-light-${statusClass[p.status_confirmacao] || 'secondary'}">${escapeHtml(p.status_confirmacao)}</span></td>
                                <td>${p.data_confirmacao || '-'}</td>
                            </tr>
                        `;
                    });
                } else {
                    html += '<tr><td colspan="3" class="text-muted text-center">Nenhum participante</td></tr>';
                }
                
                html += '</tbody></table></div>';
                
                document.getElementById('evento_detalhes_content').innerHTML = html;
                
                var modal = new bootstrap.Modal(document.getElementById('kt_modal_ver_evento'));
                modal.show();
            } else {
                alert('Erro ao carregar evento');
            }
        })
        .catch(err => {
            alert('Erro ao carregar evento');
            console.error(err);
        });
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Loading no formulário
document.getElementById('kt_evento_form').addEventListener('submit', function() {
    var btn = this.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
