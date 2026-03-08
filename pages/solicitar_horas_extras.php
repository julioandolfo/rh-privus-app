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
        $quantidade_horas_input = $_POST['quantidade_horas'] ?? '0:00';
        $motivo = sanitize($_POST['motivo'] ?? '');
        
        // Converte formato HH:MM para horas decimais
        $quantidade_horas = 0;
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $quantidade_horas_input, $matches)) {
            $horas = intval($matches[1]);
            $minutos = intval($matches[2]);
            if ($minutos >= 60) {
                redirect('solicitar_horas_extras.php', 'Os minutos devem ser menores que 60!', 'error');
            }
            $quantidade_horas = $horas + ($minutos / 60);
        } else {
            // Tenta converter formato decimal antigo (para compatibilidade)
            $quantidade_horas = floatval(str_replace(',', '.', $quantidade_horas_input));
        }
        
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
    
    // Atualizar motivo da solicitação existente
    if ($action === 'atualizar_motivo') {
        $solicitacao_id = (int)($_POST['solicitacao_id'] ?? 0);
        $novo_motivo = sanitize($_POST['novo_motivo'] ?? '');
        
        if (empty($solicitacao_id)) {
            redirect('solicitar_horas_extras.php', 'Solicitação não encontrada!', 'error');
        }
        
        if (empty($novo_motivo)) {
            redirect('solicitar_horas_extras.php', 'O motivo é obrigatório!', 'error');
        }
        
        try {
            // Verifica se a solicitação pertence ao colaborador e está pendente
            $stmt = $pdo->prepare("
                SELECT id, colaborador_id, status FROM solicitacoes_horas_extras 
                WHERE id = ? AND colaborador_id = ? AND status = 'pendente'
            ");
            $stmt->execute([$solicitacao_id, $colaborador_id]);
            $solicitacao = $stmt->fetch();
            
            if (!$solicitacao) {
                redirect('solicitar_horas_extras.php', 'Solicitação não encontrada ou já processada!', 'error');
            }
            
            // Atualiza o motivo e registra no histórico
            $stmt = $pdo->prepare("
                UPDATE solicitacoes_horas_extras 
                SET motivo = ?,
                    observacoes_rh = CONCAT(IFNULL(observacoes_rh, ''), ?)
                WHERE id = ?
            ");
            
            $historico = '\n[ATUALIZAÇÃO EM ' . date('d/m/Y H:i') . '] Motivo atualizado pelo colaborador.';
            
            $stmt->execute([
                $novo_motivo,
                $historico,
                $solicitacao_id
            ]);
            
            redirect('solicitar_horas_extras.php', 'Motivo atualizado com sucesso! O RH será notificado.', 'success');
        } catch (PDOException $e) {
            redirect('solicitar_horas_extras.php', 'Erro ao atualizar motivo: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca solicitação específica se houver ID na URL (para editar motivo)
$solicitacao_editar = null;
if (isset($_GET['acao']) && $_GET['acao'] === 'adicionar_motivo' && isset($_GET['id'])) {
    $solicitacao_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome as aprovado_por_nome
        FROM solicitacoes_horas_extras s
        LEFT JOIN usuarios u ON s.usuario_aprovacao_id = u.id
        WHERE s.id = ? AND s.colaborador_id = ? AND s.status = 'pendente'
    ");
    $stmt->execute([$solicitacao_id, $colaborador_id]);
    $solicitacao_editar = $stmt->fetch();
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

// Função para converter decimal para HH:MM
function decimalParaHorasMinutos($valor) {
    $horas = floor($valor);
    $minutos = round(($valor - $horas) * 60);
    return sprintf('%02d:%02d', $horas, $minutos);
}
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
            
            <?php if ($solicitacao_editar): ?>
            <!-- Card para atualizar motivo -->
            <div class="card mb-5 border-warning">
                <div class="card-header bg-warning">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1 text-white">
                            <i class="ki-duotone ki-message-text-2 fs-1 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Atualizar Motivo da Solicitação
                        </span>
                        <span class="text-white mt-1 fw-semibold fs-7">O RH solicitou mais informações sobre esta solicitação</span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <strong>Atenção:</strong> O RH solicitou mais detalhes sobre o motivo das suas horas extras. 
                        Por favor, forneça uma justificativa mais completa.
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Data do Trabalho:</label>
                            <p><?= formatar_data($solicitacao_editar['data_trabalho']) ?></p>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Quantidade:</label>
                            <p><?= number_format($solicitacao_editar['quantidade_horas'], 2, ',', '.') ?>h 
                               (<?= decimalParaHorasMinutos($solicitacao_editar['quantidade_horas']) ?>)</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Motivo Atual:</label>
                            <div class="p-3 bg-light rounded">
                                <?= htmlspecialchars($solicitacao_editar['motivo']) ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($solicitacao_editar['observacoes_rh'])): ?>
                    <div class="row mb-5">
                        <div class="col-12">
                            <label class="form-label fw-bold text-warning">
                                <i class="ki-duotone ki-message-notif fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Observações do RH:
                            </label>
                            <div class="p-3 bg-light-warning rounded border border-warning border-dashed">
                                <?= nl2br(htmlspecialchars($solicitacao_editar['observacoes_rh'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="atualizar_motivo">
                        <input type="hidden" name="solicitacao_id" value="<?= $solicitacao_editar['id'] ?>">
                        
                        <div class="mb-5">
                            <label class="form-label required">Novo Motivo / Justificativa Detalhada</label>
                            <textarea name="novo_motivo" 
                                      class="form-control" 
                                      rows="6" 
                                      placeholder="Descreva detalhadamente o motivo das horas extras. Inclua informações como:
- Qual projeto ou tarefa você estava executando
- Por que era necessário fazer horas extras
- Qual a urgência ou importância
- Se houve solicuação do gestor ou foi iniciativa própria"
                                      required><?= htmlspecialchars($solicitacao_editar['motivo']) ?></textarea>
                            <div class="form-text">Seja o mais específico possível. Isso ajuda o RH a entender a necessidade.</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="solicitar_horas_extras.php" class="btn btn-light">
                                <i class="ki-duotone ki-arrow-left fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Voltar
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="ki-duotone ki-check fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Atualizar Motivo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Card de Nova Solicitação -->
            <div class="card mb-5 <?= $solicitacao_editar ? 'opacity-50' : '' ?>">
                <div class="card-header">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Nova Solicitação</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Preencha os dados das horas extras trabalhadas</span>
                    </h3>
                </div>
                <div class="card-body">
                    <form id="form_solicitar" method="POST" <?= $solicitacao_editar ? 'onsubmit="return false;"' : '' ?>>
                        <input type="hidden" name="action" value="solicitar">
                        
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="form-label required">Data do Trabalho</label>
                                <input type="date" 
                                       name="data_trabalho" 
                                       class="form-control" 
                                       max="<?= date('Y-m-d') ?>"
                                       value="<?= date('Y-m-d') ?>"
                                       <?= $solicitacao_editar ? 'disabled' : 'required' ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Quantidade de Horas</label>
                                <div class="input-group">
                                    <input type="text" 
                                           name="quantidade_horas" 
                                           id="quantidade_horas"
                                           class="form-control" 
                                           placeholder="00:00"
                                           value=""
                                           <?= $solicitacao_editar ? 'disabled' : 'required' ?>>
                                    <span class="input-group-text">HH:MM</span>
                                </div>
                                <div class="form-text">Digite até 4 números. Ex: 230 = 02:30 ou 0830 = 08:30. Máximo: 8 horas</div>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-12">
                                <label class="form-label required">Motivo / Justificativa</label>
                                <textarea name="motivo" 
                                          class="form-control" 
                                          rows="4" 
                                          placeholder="Descreva o motivo das horas extras trabalhadas..."
                                          <?= $solicitacao_editar ? 'disabled' : 'required' ?>></textarea>
                            </div>
                        </div>
                        
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" <?= $solicitacao_editar ? 'disabled' : '' ?>>
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
                                        <tr <?= ($solicitacao_editar && $solicitacao_editar['id'] == $solicitacao['id']) ? 'class="table-warning"' : '' ?>>
                                            <td><?= formatar_data($solicitacao['data_trabalho']) ?></td>
                                            <td><?php
                                                $horas_decimais = floatval($solicitacao['quantidade_horas']);
                                                $horas = floor($horas_decimais);
                                                $minutos = round(($horas_decimais - $horas) * 60);
                                                echo sprintf('%02d:%02d', $horas, $minutos);
                                            ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($solicitacao['motivo']) ?>">
                                                    <?= htmlspecialchars($solicitacao['motivo']) ?>
                                                </div>
                                                <?php if (!empty($solicitacao['observacoes_rh']) && strpos($solicitacao['observacoes_rh'], 'SOLICITAÇÃO DE MOTIVO') !== false): ?>
                                                    <span class="badge badge-light-warning fs-8 mt-1">
                                                        <i class="ki-duotone ki-message-text-2 fs-8 me-1">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Aguardando mais informações
                                                    </span>
                                                <?php endif; ?>
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
// Máscara HH:MM
const quantidadeHorasInput = document.getElementById('quantidade_horas');
let digitosDigitados = '';

if (quantidadeHorasInput) {
    quantidadeHorasInput.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' || e.key === 'Delete') {
            e.preventDefault();
            digitosDigitados = digitosDigitados.slice(0, -1);
            formatarCampo();
            return;
        }
        
        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            
            if (digitosDigitados.length >= 4) {
                return;
            }
            
            digitosDigitados += e.key;
            formatarCampo();
        }
    });
    
    quantidadeHorasInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasteData = e.clipboardData.getData('text').replace(/\D/g, '');
        digitosDigitados = pasteData.substring(0, 4);
        formatarCampo();
    });
    
    quantidadeHorasInput.addEventListener('blur', function(e) {
        const value = e.target.value;
        const pattern = /^([0-7]?[0-9]):([0-5][0-9])$/;
        
        if (!pattern.test(value)) {
            e.target.value = '00:00';
            Swal.fire({
                icon: 'warning',
                title: 'Formato Inválido',
                text: 'Use o formato HH:MM (ex: 02:30 para 2 horas e 30 minutos)',
                confirmButtonText: 'OK'
            });
        } else {
            const [horas, minutos] = value.split(':').map(Number);
            const totalHoras = horas + (minutos / 60);
            
            if (totalHoras > 8) {
                e.target.value = '08:00';
                Swal.fire({
                    icon: 'warning',
                    title: 'Limite Excedido',
                    text: 'Máximo de 8 horas por solicitação. Valor ajustado para 08:00',
                    confirmButtonText: 'OK'
                });
            }
        }
    });
}

function formatarCampo() {
    if (digitosDigitados.length === 0) {
        quantidadeHorasInput.value = '';
        return;
    }
    
    let horas = '00';
    let minutos = '00';
    
    if (digitosDigitados.length === 1) {
        quantidadeHorasInput.value = digitosDigitados;
        return;
    } else if (digitosDigitados.length === 2) {
        quantidadeHorasInput.value = digitosDigitados + ':';
        return;
    } else if (digitosDigitados.length === 3) {
        horas = digitosDigitados.substring(0, 2);
        minutos = digitosDigitados[2];
        quantidadeHorasInput.value = horas + ':' + minutos;
        return;
    } else {
        horas = digitosDigitados.substring(0, 2);
        minutos = digitosDigitados.substring(2, 4);
    }
    
    if (parseInt(horas) > 8) {
        horas = '08';
        minutos = '00';
        digitosDigitados = '0800';
    }
    
    if (parseInt(minutos) > 59) {
        minutos = '59';
        digitosDigitados = horas + '59';
    }
    
    quantidadeHorasInput.value = horas + ':' + minutos;
}

// Validação do formulário
const formSolicitar = document.getElementById('form_solicitar');
if (formSolicitar) {
    formSolicitar.addEventListener('submit', function(e) {
        const quantidadeInput = document.getElementById('quantidade_horas').value;
        const pattern = /^([0-7]?[0-9]):([0-5][0-9])$/;
        
        if (!pattern.test(quantidadeInput)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Formato Inválido',
                text: 'Use o formato HH:MM (ex: 02:30 para 2 horas e 30 minutos)',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        const [horas, minutos] = quantidadeInput.split(':').map(Number);
        const quantidade = horas + (minutos / 60);
        
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
}

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
