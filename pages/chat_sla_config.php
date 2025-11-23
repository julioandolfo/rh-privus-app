<?php
/**
 * Configuração de SLA do Chat
 */

$page_title = 'Configuração de SLA';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('chat_configuracoes.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$sla_id = (int)($_GET['id'] ?? 0);
$sla = null;
$mensagem = '';
$erro = '';

// Processa exclusão ANTES de incluir o header (para evitar erro de headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_id = (int)($_POST['id'] ?? 0);
    if ($delete_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM chat_sla_config WHERE id = ?");
            $stmt->execute([$delete_id]);
            header('Location: chat_configuracoes.php?msg=Configuração de SLA excluída com sucesso!');
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao excluir: ' . $e->getMessage();
        }
    }
}

// Busca empresas para o select
$stmt = $pdo->query("SELECT id, nome_fantasia as nome FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
$empresas = $stmt->fetchAll();

// Se tem ID, busca SLA existente
if ($sla_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM chat_sla_config WHERE id = ?");
    $stmt->execute([$sla_id]);
    $sla = $stmt->fetch();
    
    if (!$sla) {
        header('Location: chat_configuracoes.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';

// Processa salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'delete')) {
    try {
        $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
        $nome = trim($_POST['nome'] ?? '');
        $tempo_primeira_resposta = (int)($_POST['tempo_primeira_resposta_minutos'] ?? 60);
        $tempo_resolucao = (int)($_POST['tempo_resolucao_horas'] ?? 24);
        $horario_inicio = $_POST['horario_inicio'] ?? '08:00';
        $horario_fim = $_POST['horario_fim'] ?? '18:00';
        $dias_semana = isset($_POST['dias_semana']) ? json_encode($_POST['dias_semana']) : json_encode([1,2,3,4,5]);
        $aplicar_apenas_horario_comercial = isset($_POST['aplicar_apenas_horario_comercial']) ? 1 : 0;
        $alerta_antes_vencer = (int)($_POST['alerta_antes_vencer_minutos'] ?? 30);
        $aplicar_por_prioridade = isset($_POST['aplicar_por_prioridade']) ? 1 : 0;
        $sla_prioridade_alta = !empty($_POST['sla_prioridade_alta_minutos']) ? (int)$_POST['sla_prioridade_alta_minutos'] : null;
        $sla_prioridade_urgente = !empty($_POST['sla_prioridade_urgente_minutos']) ? (int)$_POST['sla_prioridade_urgente_minutos'] : null;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        if (empty($nome)) {
            throw new Exception('Nome é obrigatório');
        }
        
        if ($sla_id > 0) {
            // Atualiza
            $stmt = $pdo->prepare("
                UPDATE chat_sla_config SET
                    empresa_id = ?,
                    nome = ?,
                    tempo_primeira_resposta_minutos = ?,
                    tempo_resolucao_horas = ?,
                    horario_inicio = ?,
                    horario_fim = ?,
                    dias_semana = ?,
                    aplicar_apenas_horario_comercial = ?,
                    alerta_antes_vencer_minutos = ?,
                    aplicar_por_prioridade = ?,
                    sla_prioridade_alta_minutos = ?,
                    sla_prioridade_urgente_minutos = ?,
                    ativo = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $empresa_id, $nome, $tempo_primeira_resposta, $tempo_resolucao,
                $horario_inicio, $horario_fim, $dias_semana, $aplicar_apenas_horario_comercial,
                $alerta_antes_vencer, $aplicar_por_prioridade, $sla_prioridade_alta,
                $sla_prioridade_urgente, $ativo, $sla_id
            ]);
            $mensagem = 'Configuração de SLA atualizada com sucesso!';
        } else {
            // Cria novo
            $stmt = $pdo->prepare("
                INSERT INTO chat_sla_config (
                    empresa_id, nome, tempo_primeira_resposta_minutos, tempo_resolucao_horas,
                    horario_inicio, horario_fim, dias_semana, aplicar_apenas_horario_comercial,
                    alerta_antes_vencer_minutos, aplicar_por_prioridade, sla_prioridade_alta_minutos,
                    sla_prioridade_urgente_minutos, ativo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $empresa_id, $nome, $tempo_primeira_resposta, $tempo_resolucao,
                $horario_inicio, $horario_fim, $dias_semana, $aplicar_apenas_horario_comercial,
                $alerta_antes_vencer, $aplicar_por_prioridade, $sla_prioridade_alta,
                $sla_prioridade_urgente, $ativo
            ]);
            $mensagem = 'Configuração de SLA criada com sucesso!';
        }
        
        // Redireciona após salvar
        header('Location: chat_configuracoes.php');
        exit;
        
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Parse dias da semana se existir
$dias_selecionados = [];
if ($sla && $sla['dias_semana']) {
    $dias_selecionados = json_decode($sla['dias_semana'], true) ?: [];
}
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <?php if ($mensagem): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensagem) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($erro): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($erro) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2><?= $sla_id > 0 ? 'Editar' : 'Nova' ?> Configuração de SLA</h2>
                        </div>
                        <div class="card-toolbar">
                            <?php if ($sla_id > 0): ?>
                            <button type="button" class="btn btn-danger me-2" onclick="confirmarExclusao()">
                                <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                Excluir
                            </button>
                            <?php endif; ?>
                            <a href="chat_configuracoes.php" class="btn btn-light">
                                <i class="ki-duotone ki-arrow-left"><span class="path1"></span><span class="path2"></span></i>
                                Voltar
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <form method="POST">
                            <div class="row mb-5">
                                <div class="col-md-6">
                                    <label class="form-label required">Nome da Configuração</label>
                                    <input type="text" name="nome" class="form-control" 
                                           value="<?= htmlspecialchars($sla['nome'] ?? '') ?>" required>
                                    <div class="form-text">Ex: SLA Padrão, SLA Empresa X</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Empresa</label>
                                    <select name="empresa_id" class="form-select">
                                        <option value="">Global (Todas as empresas)</option>
                                        <?php foreach ($empresas as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" 
                                                <?= ($sla['empresa_id'] ?? null) == $emp['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['nome']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Deixe vazio para aplicar a todas as empresas</div>
                                </div>
                            </div>
                            
                            <div class="separator separator-dashed my-5"></div>
                            <h3 class="mb-5">Tempos de SLA</h3>
                            
                            <div class="row mb-5">
                                <div class="col-md-6">
                                    <label class="form-label required">Tempo para Primeira Resposta (minutos)</label>
                                    <input type="number" name="tempo_primeira_resposta_minutos" class="form-control" 
                                           value="<?= htmlspecialchars($sla['tempo_primeira_resposta_minutos'] ?? '60') ?>" 
                                           min="1" required>
                                    <div class="form-text">Tempo máximo para primeira resposta do RH</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label required">Tempo para Resolução (horas)</label>
                                    <input type="number" name="tempo_resolucao_horas" class="form-control" 
                                           value="<?= htmlspecialchars($sla['tempo_resolucao_horas'] ?? '24') ?>" 
                                           min="1" required>
                                    <div class="form-text">Tempo máximo para resolução da conversa</div>
                                </div>
                            </div>
                            
                            <div class="separator separator-dashed my-5"></div>
                            <h3 class="mb-5">Horário de Atendimento</h3>
                            
                            <div class="row mb-5">
                                <div class="col-md-4">
                                    <label class="form-label">Horário de Início</label>
                                    <input type="time" name="horario_inicio" class="form-control" 
                                           value="<?= htmlspecialchars($sla['horario_inicio'] ?? '08:00') ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Horário de Fim</label>
                                    <input type="time" name="horario_fim" class="form-control" 
                                           value="<?= htmlspecialchars($sla['horario_fim'] ?? '18:00') ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Dias da Semana</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php 
                                        $dias_nomes = [
                                            1 => 'Segunda',
                                            2 => 'Terça',
                                            3 => 'Quarta',
                                            4 => 'Quinta',
                                            5 => 'Sexta',
                                            6 => 'Sábado',
                                            7 => 'Domingo'
                                        ];
                                        foreach ($dias_nomes as $dia_num => $dia_nome): 
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="dias_semana[]" 
                                                   value="<?= $dia_num ?>" id="dia_<?= $dia_num ?>"
                                                   <?= in_array($dia_num, $dias_selecionados) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="dia_<?= $dia_num ?>">
                                                <?= $dia_nome ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-5">
                                <div class="col-md-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="aplicar_apenas_horario_comercial" 
                                               id="aplicar_horario" 
                                               <?= ($sla['aplicar_apenas_horario_comercial'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aplicar_horario">
                                            Aplicar SLA apenas em horário comercial
                                        </label>
                                        <div class="form-text">Se marcado, o SLA só conta durante o horário de atendimento configurado</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="separator separator-dashed my-5"></div>
                            <h3 class="mb-5">SLA por Prioridade</h3>
                            
                            <div class="row mb-5">
                                <div class="col-md-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="aplicar_por_prioridade" 
                                               id="aplicar_prioridade" 
                                               <?= ($sla['aplicar_por_prioridade'] ?? 0) ? 'checked' : '' ?>
                                               onchange="document.getElementById('sla_prioridade_container').style.display = this.checked ? 'block' : 'none'">
                                        <label class="form-check-label" for="aplicar_prioridade">
                                            Aplicar tempos diferentes por prioridade
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="sla_prioridade_container" style="display: <?= ($sla['aplicar_por_prioridade'] ?? 0) ? 'block' : 'none' ?>;">
                                <div class="row mb-5">
                                    <div class="col-md-6">
                                        <label class="form-label">SLA Prioridade Alta (minutos)</label>
                                        <input type="number" name="sla_prioridade_alta_minutos" class="form-control" 
                                               value="<?= htmlspecialchars($sla['sla_prioridade_alta_minutos'] ?? '') ?>" 
                                               min="1">
                                        <div class="form-text">Tempo para primeira resposta quando prioridade é Alta</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">SLA Prioridade Urgente (minutos)</label>
                                        <input type="number" name="sla_prioridade_urgente_minutos" class="form-control" 
                                               value="<?= htmlspecialchars($sla['sla_prioridade_urgente_minutos'] ?? '') ?>" 
                                               min="1">
                                        <div class="form-text">Tempo para primeira resposta quando prioridade é Urgente</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="separator separator-dashed my-5"></div>
                            <h3 class="mb-5">Alertas</h3>
                            
                            <div class="row mb-5">
                                <div class="col-md-6">
                                    <label class="form-label">Alerta Antes de Vencer (minutos)</label>
                                    <input type="number" name="alerta_antes_vencer_minutos" class="form-control" 
                                           value="<?= htmlspecialchars($sla['alerta_antes_vencer_minutos'] ?? '30') ?>" 
                                           min="0">
                                    <div class="form-text">Tempo antes do vencimento para enviar alerta</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-8">
                                        <input class="form-check-input" type="checkbox" name="ativo" id="ativo" 
                                               <?= ($sla['ativo'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ativo">
                                            Configuração Ativa
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <a href="chat_configuracoes.php" class="btn btn-light me-3">Cancelar</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ki-duotone ki-check fs-2"></i>
                                    Salvar Configuração
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Formulário oculto para exclusão -->
<form id="form-delete" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= $sla_id ?>">
</form>

<script>
function confirmarExclusao() {
    Swal.fire({
        title: 'Tem certeza?',
        text: 'Esta ação não pode ser desfeita!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('form-delete').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

