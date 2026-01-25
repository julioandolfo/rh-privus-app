<?php
/**
 * Meus Eventos - Visão do Colaborador - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('meus_eventos.php');

$pdo = getDB();

// Pega o colaborador_id do usuário logado
$colaborador_id = $_SESSION['usuario']['colaborador_id'] ?? null;

if (!$colaborador_id) {
    redirect('dashboard.php', 'Você não está vinculado a um colaborador.', 'error');
}

// Processa confirmação/recusa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $evento_id = (int)($_POST['evento_id'] ?? 0);
    
    if ($action === 'confirmar' || $action === 'recusar' || $action === 'talvez') {
        $status = [
            'confirmar' => 'confirmado',
            'recusar' => 'recusado',
            'talvez' => 'talvez'
        ][$action];
        
        $motivo = sanitize($_POST['motivo'] ?? '');
        
        try {
            // Verifica status atual
            $stmt_check = $pdo->prepare("SELECT status_confirmacao FROM eventos_participantes WHERE evento_id = ? AND colaborador_id = ?");
            $stmt_check->execute([$evento_id, $colaborador_id]);
            $status_atual = $stmt_check->fetch();
            $ja_confirmado = ($status_atual && $status_atual['status_confirmacao'] === 'confirmado');
            
            $stmt = $pdo->prepare("
                UPDATE eventos_participantes 
                SET status_confirmacao = ?, 
                    motivo_recusa = ?,
                    data_confirmacao = NOW()
                WHERE evento_id = ? AND colaborador_id = ?
            ");
            $stmt->execute([$status, $motivo ?: null, $evento_id, $colaborador_id]);
            
            // Adiciona pontos se está confirmando e não estava confirmado antes
            if ($status === 'confirmado' && !$ja_confirmado) {
                require_once __DIR__ . '/../includes/pontuacao.php';
                adicionar_pontos('confirmar_evento', null, $colaborador_id, $evento_id, 'evento');
            }
            
            $msg = [
                'confirmado' => 'Presença confirmada com sucesso!',
                'recusado' => 'Evento recusado.',
                'talvez' => 'Resposta registrada como "Talvez".'
            ][$status];
            
            redirect('meus_eventos.php', $msg, 'success');
        } catch (PDOException $e) {
            redirect('meus_eventos.php', 'Erro ao processar: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca eventos do colaborador
$stmt = $pdo->prepare("
    SELECT e.*, ep.status_confirmacao, ep.data_confirmacao, ep.motivo_recusa,
           u.nome as criador_nome
    FROM eventos e
    INNER JOIN eventos_participantes ep ON e.id = ep.evento_id
    LEFT JOIN usuarios u ON e.criado_por_usuario_id = u.id
    WHERE ep.colaborador_id = ?
    ORDER BY e.data_evento ASC, e.hora_inicio ASC
");
$stmt->execute([$colaborador_id]);
$eventos = $stmt->fetchAll();

// Separa em próximos e passados
$proximos = [];
$passados = [];
$hoje = date('Y-m-d');

foreach ($eventos as $evento) {
    if ($evento['data_evento'] >= $hoje && $evento['status'] !== 'cancelado') {
        $proximos[] = $evento;
    } else {
        $passados[] = $evento;
    }
}

// Labels
$tipos_evento = [
    'reuniao' => 'Reunião',
    'treinamento' => 'Treinamento',
    'confraternizacao' => 'Confraternização',
    'palestra' => 'Palestra',
    'workshop' => 'Workshop',
    'outro' => 'Outro'
];

$status_confirmacao = [
    'pendente' => ['label' => 'Pendente', 'class' => 'warning'],
    'confirmado' => ['label' => 'Confirmado', 'class' => 'success'],
    'recusado' => ['label' => 'Recusado', 'class' => 'danger'],
    'talvez' => ['label' => 'Talvez', 'class' => 'info']
];

$status_evento = [
    'agendado' => ['label' => 'Agendado', 'class' => 'primary'],
    'em_andamento' => ['label' => 'Em Andamento', 'class' => 'info'],
    'concluido' => ['label' => 'Concluído', 'class' => 'success'],
    'cancelado' => ['label' => 'Cancelado', 'class' => 'danger']
];

$page_title = 'Meus Eventos';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Meus Eventos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Meus Eventos</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Próximos Eventos-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <i class="ki-duotone ki-calendar fs-2 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <h3 class="fw-bold">Próximos Eventos</h3>
                </div>
                <div class="card-toolbar">
                    <span class="badge badge-light-primary fs-7"><?= count($proximos) ?> eventos</span>
                </div>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($proximos)): ?>
                <div class="text-center text-muted py-10">
                    <i class="ki-duotone ki-calendar fs-3x text-gray-300 mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <p class="mb-0">Você não tem eventos próximos</p>
                </div>
                <?php else: ?>
                <div class="row g-5">
                    <?php foreach ($proximos as $evento): ?>
                    <?php $conf = $status_confirmacao[$evento['status_confirmacao']] ?? ['label' => 'Pendente', 'class' => 'warning']; ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-bordered h-100">
                            <div class="card-header border-0 pt-5">
                                <div class="card-title flex-column">
                                    <span class="badge badge-light-info mb-2"><?= $tipos_evento[$evento['tipo']] ?? $evento['tipo'] ?></span>
                                    <h4 class="fw-bold text-gray-800 mb-0"><?= htmlspecialchars($evento['titulo']) ?></h4>
                                </div>
                            </div>
                            <div class="card-body pt-2">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="ki-duotone ki-calendar fs-4 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="text-gray-600"><?= date('d/m/Y', strtotime($evento['data_evento'])) ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="ki-duotone ki-time fs-4 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="text-gray-600">
                                        <?= date('H:i', strtotime($evento['hora_inicio'])) ?>
                                        <?php if ($evento['hora_fim']): ?>
                                        - <?= date('H:i', strtotime($evento['hora_fim'])) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($evento['local']): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="ki-duotone ki-geolocation fs-4 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="text-gray-600"><?= htmlspecialchars($evento['local']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($evento['descricao']): ?>
                                <p class="text-gray-600 fs-7 mt-3"><?= nl2br(htmlspecialchars(mb_substr($evento['descricao'], 0, 150))) ?>...</p>
                                <?php endif; ?>
                                
                                <?php if ($evento['link_virtual']): ?>
                                <div class="mt-3">
                                    <a href="<?= htmlspecialchars($evento['link_virtual']) ?>" target="_blank" class="btn btn-sm btn-light-primary">
                                        <i class="ki-duotone ki-screen fs-5 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                        </i>
                                        Acessar Reunião Virtual
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer border-top pt-5">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge badge-<?= $conf['class'] ?>"><?= $conf['label'] ?></span>
                                    
                                    <?php if ($evento['status_confirmacao'] === 'pendente'): ?>
                                    <div class="btn-group">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="confirmar">
                                            <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="ki-duotone ki-check fs-5"></i> Confirmar
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="recusarEvento(<?= $evento['id'] ?>, '<?= htmlspecialchars($evento['titulo'], ENT_QUOTES) ?>')">
                                            <i class="ki-duotone ki-cross fs-5"></i> Recusar
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-light" onclick="alterarResposta(<?= $evento['id'] ?>, '<?= htmlspecialchars($evento['titulo'], ENT_QUOTES) ?>')">
                                        Alterar Resposta
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Próximos Eventos-->
        
        <!--begin::Eventos Passados-->
        <?php if (!empty($passados)): ?>
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <i class="ki-duotone ki-calendar fs-2 text-gray-500 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <h3 class="fw-bold text-gray-600">Eventos Anteriores</h3>
                </div>
                <div class="card-toolbar">
                    <span class="badge badge-light fs-7"><?= count($passados) ?> eventos</span>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th>Evento</th>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Sua Resposta</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($passados as $evento): ?>
                            <?php $conf = $status_confirmacao[$evento['status_confirmacao']] ?? ['label' => 'Pendente', 'class' => 'warning']; ?>
                            <?php $st = $status_evento[$evento['status']] ?? ['label' => $evento['status'], 'class' => 'secondary']; ?>
                            <tr>
                                <td><?= htmlspecialchars($evento['titulo']) ?></td>
                                <td><?= date('d/m/Y', strtotime($evento['data_evento'])) ?></td>
                                <td><span class="badge badge-light-info"><?= $tipos_evento[$evento['tipo']] ?? $evento['tipo'] ?></span></td>
                                <td><span class="badge badge-light-<?= $conf['class'] ?>"><?= $conf['label'] ?></span></td>
                                <td><span class="badge badge-light-<?= $st['class'] ?>"><?= $st['label'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!--end::Eventos Passados-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Recusar/Alterar-->
<div class="modal fade" id="kt_modal_resposta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_resposta_title">Recusar Evento</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="form_resposta" method="POST">
                    <input type="hidden" name="action" id="resposta_action" value="recusar">
                    <input type="hidden" name="evento_id" id="resposta_evento_id">
                    
                    <p id="resposta_evento_nome" class="fw-bold fs-5 mb-5"></p>
                    
                    <div id="opcoes_resposta" class="mb-5" style="display:none;">
                        <label class="form-label fw-bold">Sua resposta:</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success flex-grow-1" onclick="setResposta('confirmar')">
                                <i class="ki-duotone ki-check fs-3"></i> Confirmar
                            </button>
                            <button type="button" class="btn btn-warning flex-grow-1" onclick="setResposta('talvez')">
                                <i class="ki-duotone ki-question fs-3"></i> Talvez
                            </button>
                            <button type="button" class="btn btn-danger flex-grow-1" onclick="setResposta('recusar')">
                                <i class="ki-duotone ki-cross fs-3"></i> Recusar
                            </button>
                        </div>
                    </div>
                    
                    <div id="motivo_container" class="mb-5">
                        <label class="form-label">Motivo (opcional)</label>
                        <textarea name="motivo" class="form-control form-control-solid" rows="3" placeholder="Informe o motivo..."></textarea>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btn_enviar_resposta">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
function recusarEvento(id, titulo) {
    document.getElementById('modal_resposta_title').textContent = 'Recusar Evento';
    document.getElementById('resposta_evento_id').value = id;
    document.getElementById('resposta_evento_nome').textContent = titulo;
    document.getElementById('resposta_action').value = 'recusar';
    document.getElementById('opcoes_resposta').style.display = 'none';
    document.getElementById('motivo_container').style.display = 'block';
    document.getElementById('btn_enviar_resposta').textContent = 'Confirmar Recusa';
    
    var modal = new bootstrap.Modal(document.getElementById('kt_modal_resposta'));
    modal.show();
}

function alterarResposta(id, titulo) {
    document.getElementById('modal_resposta_title').textContent = 'Alterar Resposta';
    document.getElementById('resposta_evento_id').value = id;
    document.getElementById('resposta_evento_nome').textContent = titulo;
    document.getElementById('opcoes_resposta').style.display = 'block';
    document.getElementById('motivo_container').style.display = 'none';
    document.getElementById('btn_enviar_resposta').textContent = 'Enviar';
    
    var modal = new bootstrap.Modal(document.getElementById('kt_modal_resposta'));
    modal.show();
}

function setResposta(action) {
    document.getElementById('resposta_action').value = action;
    if (action === 'recusar') {
        document.getElementById('motivo_container').style.display = 'block';
    } else {
        document.getElementById('motivo_container').style.display = 'none';
    }
    document.getElementById('form_resposta').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
