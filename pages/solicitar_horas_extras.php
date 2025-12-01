<?php
/**
 * Solicitar Horas Extras - Colaboradores
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$usuario = $_SESSION['usuario'];

// Verifica se é colaborador
if ($usuario['role'] !== 'COLABORADOR' || !isset($usuario['colaborador_id'])) {
    redirect('dashboard.php', 'Acesso negado! Apenas colaboradores podem solicitar horas extras.', 'error');
}

$pdo = getDB();
$colaborador_id = $usuario['colaborador_id'];

// Busca dados do colaborador
$stmt = $pdo->prepare("
    SELECT c.*, e.nome_fantasia as empresa_nome
    FROM colaboradores c
    LEFT JOIN empresas e ON c.empresa_id = e.id
    WHERE c.id = ?
");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch();

if (!$colaborador) {
    redirect('dashboard.php', 'Colaborador não encontrado!', 'error');
}

// Processa formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'solicitar') {
        $data_trabalho = $_POST['data_trabalho'] ?? '';
        $quantidade_horas = str_replace(',', '.', $_POST['quantidade_horas'] ?? '0');
        $motivo = sanitize($_POST['motivo'] ?? '');
        
        // Validações
        if (empty($data_trabalho)) {
            redirect('solicitar_horas_extras.php', 'Data do trabalho é obrigatória!', 'error');
        }
        
        if (empty($quantidade_horas) || $quantidade_horas <= 0) {
            redirect('solicitar_horas_extras.php', 'Quantidade de horas deve ser maior que zero!', 'error');
        }
        
        if ($quantidade_horas > 8) {
            redirect('solicitar_horas_extras.php', 'Máximo de 8 horas por solicitação!', 'error');
        }
        
        if (empty($motivo)) {
            redirect('solicitar_horas_extras.php', 'Motivo é obrigatório!', 'error');
        }
        
        // Verifica se a data não é futura
        if (strtotime($data_trabalho) > strtotime('today')) {
            redirect('solicitar_horas_extras.php', 'Não é possível solicitar horas extras para datas futuras!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO solicitacoes_horas_extras (
                    colaborador_id, data_trabalho, quantidade_horas, motivo, status
                ) VALUES (?, ?, ?, ?, 'pendente')
            ");
            $stmt->execute([
                $colaborador_id,
                $data_trabalho,
                $quantidade_horas,
                $motivo
            ]);
            
            redirect('solicitar_horas_extras.php', 'Solicitação de horas extras enviada com sucesso! Aguarde aprovação do RH.', 'success');
        } catch (PDOException $e) {
            redirect('solicitar_horas_extras.php', 'Erro ao enviar solicitação: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca solicitações do colaborador
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.nome as aprovado_por_nome
    FROM solicitacoes_horas_extras s
    LEFT JOIN usuarios u ON s.usuario_aprovacao_id = u.id
    WHERE s.colaborador_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$colaborador_id]);
$solicitacoes = $stmt->fetchAll();

$page_title = 'Solicitar Horas Extras';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    Solicitar Horas Extras
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="dashboard.php" class="text-muted text-hover-primary">Início</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-400 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">Solicitar Horas Extras</li>
                </ul>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">
            
            <!-- Card de Solicitação -->
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Nova Solicitação</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Preencha os dados das horas extras trabalhadas</span>
                    </h3>
                </div>
                <div class="card-body">
                    <form id="form_solicitar" method="POST">
                        <input type="hidden" name="action" value="solicitar">
                        
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="form-label required">Data do Trabalho</label>
                                <input type="date" 
                                       name="data_trabalho" 
                                       class="form-control" 
                                       max="<?= date('Y-m-d') ?>"
                                       value="<?= date('Y-m-d') ?>"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Quantidade de Horas</label>
                                <div class="input-group">
                                    <input type="number" 
                                           name="quantidade_horas" 
                                           id="quantidade_horas"
                                           class="form-control" 
                                           step="0.01"
                                           min="0.01"
                                           max="8"
                                           placeholder="0.00"
                                           required>
                                    <span class="input-group-text">horas</span>
                                </div>
                                <div class="form-text">Máximo de 8 horas por solicitação</div>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-12">
                                <label class="form-label required">Motivo / Justificativa</label>
                                <textarea name="motivo" 
                                          class="form-control" 
                                          rows="4" 
                                          placeholder="Descreva o motivo das horas extras trabalhadas..."
                                          required></textarea>
                            </div>
                        </div>
                        
                        <!-- Cronômetro -->
                        <div class="row mb-5">
                            <div class="col-12">
                                <div class="card bg-light-primary">
                                    <div class="card-body">
                                        <h4 class="mb-4">Cronômetro</h4>
                                        <div class="text-center mb-4">
                                            <div class="display-1 fw-bold text-primary" id="timer_display">00:00:00</div>
                                            <div class="text-muted fs-6 mt-2">
                                                <span id="timer_horas_display">0.00 horas</span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-center gap-3">
                                            <button type="button" 
                                                    class="btn btn-success btn-lg" 
                                                    id="btn_start"
                                                    onclick="startTimer()">
                                                <i class="ki-duotone ki-play fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Iniciar
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-danger btn-lg" 
                                                    id="btn_stop"
                                                    onclick="stopTimer()"
                                                    disabled>
                                                <i class="ki-duotone ki-pause fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Parar
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-warning btn-lg" 
                                                    id="btn_reset"
                                                    onclick="resetTimer()">
                                                <i class="ki-duotone ki-arrows-circle fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Resetar
                                            </button>
                                        </div>
                                        <div class="alert alert-info mt-4 mb-0">
                                            <i class="ki-duotone ki-information-5 fs-2 me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            O cronômetro tem limite máximo de 8 horas. Ao parar, o tempo será automaticamente preenchido no campo de quantidade de horas.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-duotone ki-check fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Enviar Solicitação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Histórico de Solicitações -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Minhas Solicitações</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Histórico de solicitações enviadas</span>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($solicitacoes)): ?>
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-clock fs-3x text-muted mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            <p class="text-muted fs-5">Nenhuma solicitação encontrada</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-100px">Data do Trabalho</th>
                                        <th class="min-w-100px">Quantidade</th>
                                        <th class="min-w-150px">Motivo</th>
                                        <th class="min-w-100px">Status</th>
                                        <th class="min-w-150px">Data Solicitação</th>
                                        <th class="min-w-150px">Aprovado Por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitacoes as $solicitacao): ?>
                                        <tr>
                                            <td><?= formatar_data($solicitacao['data_trabalho']) ?></td>
                                            <td><?= number_format($solicitacao['quantidade_horas'], 2, ',', '.') ?>h</td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($solicitacao['motivo']) ?>">
                                                    <?= htmlspecialchars($solicitacao['motivo']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($solicitacao['status'] === 'pendente'): ?>
                                                    <span class="badge badge-light-warning">Pendente</span>
                                                <?php elseif ($solicitacao['status'] === 'aprovada'): ?>
                                                    <span class="badge badge-light-success">Aprovada</span>
                                                <?php else: ?>
                                                    <span class="badge badge-light-danger">Rejeitada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatar_data($solicitacao['created_at'], 'd/m/Y H:i') ?></td>
                                            <td>
                                                <?php if ($solicitacao['aprovado_por_nome']): ?>
                                                    <?= htmlspecialchars($solicitacao['aprovado_por_nome']) ?>
                                                    <br>
                                                    <small class="text-muted"><?= formatar_data($solicitacao['data_aprovacao'], 'd/m/Y H:i') ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
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
    </div>
</div>

<script>
let timerInterval = null;
let timerSeconds = 0;
const MAX_HOURS = 8;
const MAX_SECONDS = MAX_HOURS * 3600; // 8 horas em segundos

function formatTime(seconds) {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function formatHours(seconds) {
    return (seconds / 3600).toFixed(2);
}

function updateDisplay() {
    document.getElementById('timer_display').textContent = formatTime(timerSeconds);
    document.getElementById('timer_horas_display').textContent = formatHours(timerSeconds) + ' horas';
    
    // Atualiza campo de quantidade de horas
    document.getElementById('quantidade_horas').value = formatHours(timerSeconds);
    
    // Verifica limite de 8 horas
    if (timerSeconds >= MAX_SECONDS) {
        stopTimer();
        Swal.fire({
            icon: 'warning',
            title: 'Limite Atingido',
            text: 'Você atingiu o limite máximo de 8 horas. O cronômetro foi pausado automaticamente.',
            confirmButtonText: 'OK'
        });
    }
}

function startTimer() {
    if (timerSeconds >= MAX_SECONDS) {
        Swal.fire({
            icon: 'warning',
            title: 'Limite Atingido',
            text: 'Você já atingiu o limite máximo de 8 horas.',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    document.getElementById('btn_start').disabled = true;
    document.getElementById('btn_stop').disabled = false;
    
    timerInterval = setInterval(() => {
        timerSeconds++;
        updateDisplay();
        
        if (timerSeconds >= MAX_SECONDS) {
            stopTimer();
        }
    }, 1000);
}

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    
    document.getElementById('btn_start').disabled = false;
    document.getElementById('btn_stop').disabled = true;
}

function resetTimer() {
    stopTimer();
    timerSeconds = 0;
    updateDisplay();
}

// Validação do formulário
document.getElementById('form_solicitar').addEventListener('submit', function(e) {
    const quantidade = parseFloat(document.getElementById('quantidade_horas').value);
    
    if (quantidade <= 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'A quantidade de horas deve ser maior que zero!',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    if (quantidade > 8) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Máximo de 8 horas por solicitação!',
            confirmButtonText: 'OK'
        });
        return false;
    }
});

// Inicializa display
updateDisplay();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

