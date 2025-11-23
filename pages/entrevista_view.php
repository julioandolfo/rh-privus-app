<?php
/**
 * Detalhes da Entrevista
 */

$page_title = 'Detalhes da Entrevista';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('entrevistas.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$entrevista_id = (int)($_GET['id'] ?? 0);

if (!$entrevista_id) {
    redirect('entrevistas.php', 'Entrevista não encontrada', 'error');
}

// Busca entrevista
$stmt = $pdo->prepare("
    SELECT e.*,
           c.nome_completo as candidato_nome,
           c.email as candidato_email,
           v.titulo as vaga_titulo,
           u.nome as entrevistador_nome,
           et.nome as etapa_nome,
           cand.id as candidatura_id
    FROM entrevistas e
    INNER JOIN candidaturas cand ON e.candidatura_id = cand.id
    INNER JOIN candidatos c ON cand.candidato_id = c.id
    INNER JOIN vagas v ON cand.vaga_id = v.id
    LEFT JOIN usuarios u ON e.entrevistador_id = u.id
    LEFT JOIN processo_seletivo_etapas et ON e.etapa_id = et.id
    WHERE e.id = ?
");
$stmt->execute([$entrevista_id]);
$entrevista = $stmt->fetch();

if (!$entrevista) {
    redirect('entrevistas.php', 'Entrevista não encontrada', 'error');
}

// Verifica permissão
$stmt = $pdo->prepare("SELECT empresa_id FROM vagas WHERE id = (SELECT vaga_id FROM candidaturas WHERE id = ?)");
$stmt->execute([$entrevista['candidatura_id']]);
$vaga = $stmt->fetch();

if ($vaga && !can_access_empresa($vaga['empresa_id'])) {
    redirect('entrevistas.php', 'Sem permissão', 'error');
}
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card mb-5">
                    <div class="card-header">
                        <h2><?= htmlspecialchars($entrevista['titulo']) ?></h2>
                        <div class="card-toolbar">
                            <a href="entrevistas.php" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Candidato:</strong> <?= htmlspecialchars($entrevista['candidato_nome']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($entrevista['candidato_email']) ?></p>
                                <p><strong>Vaga:</strong> <?= htmlspecialchars($entrevista['vaga_titulo']) ?></p>
                                <?php if ($entrevista['etapa_nome']): ?>
                                <p><strong>Etapa:</strong> <?= htmlspecialchars($entrevista['etapa_nome']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Entrevistador:</strong> <?= htmlspecialchars($entrevista['entrevistador_nome']) ?></p>
                                <p><strong>Tipo:</strong> <?= ucfirst($entrevista['tipo']) ?></p>
                                <p><strong>Data/Hora:</strong> <?= date('d/m/Y H:i', strtotime($entrevista['data_agendada'])) ?></p>
                                <p><strong>Duração:</strong> <?= $entrevista['duracao_minutos'] ?> minutos</p>
                                <p><strong>Status:</strong> 
                                    <span class="badge badge-light-primary"><?= ucfirst(str_replace('_', ' ', $entrevista['status'])) ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($entrevista['link_videoconferencia']): ?>
                        <div class="mt-3">
                            <strong>Link de Videoconferência:</strong>
                            <a href="<?= htmlspecialchars($entrevista['link_videoconferencia']) ?>" target="_blank" class="btn btn-light-primary ms-2">
                                Acessar Entrevista
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($entrevista['localizacao']): ?>
                        <div class="mt-3">
                            <strong>Localização:</strong> <?= htmlspecialchars($entrevista['localizacao']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($entrevista['descricao']): ?>
                        <div class="mt-3">
                            <strong>Descrição:</strong>
                            <div><?= nl2br(htmlspecialchars($entrevista['descricao'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Avaliação -->
                <?php if ($entrevista['status'] === 'realizada' || $entrevista['status'] === 'agendada'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Avaliação da Entrevista</h3>
                    </div>
                    <div class="card-body">
                        <form id="formAvaliacao">
                            <input type="hidden" name="entrevista_id" value="<?= $entrevista_id ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Nota (0-10)</label>
                                <input type="number" name="nota_entrevistador" class="form-control" 
                                       min="0" max="10" step="0.1" value="<?= $entrevista['nota_entrevistador'] ?? '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Avaliação do Entrevistador</label>
                                <textarea name="avaliacao_entrevistador" class="form-control" rows="5"><?= htmlspecialchars($entrevista['avaliacao_entrevistador'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Feedback para o Candidato</label>
                                <textarea name="feedback_candidato" class="form-control" rows="3"><?= htmlspecialchars($entrevista['feedback_candidato'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($entrevista['observacoes'] ?? '') ?></textarea>
                            </div>
                            
                            <?php if ($entrevista['status'] === 'agendada'): ?>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="agendada" <?= $entrevista['status'] === 'agendada' ? 'selected' : '' ?>>Agendada</option>
                                    <option value="realizada" <?= $entrevista['status'] === 'realizada' ? 'selected' : '' ?>>Realizada</option>
                                    <option value="cancelada" <?= $entrevista['status'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                    <option value="reagendada" <?= $entrevista['status'] === 'reagendada' ? 'selected' : '' ?>>Reagendada</option>
                                    <option value="nao_compareceu" <?= $entrevista['status'] === 'nao_compareceu' ? 'selected' : '' ?>>Não Compareceu</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">Salvar Avaliação</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formAvaliacao')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/entrevistas/avaliar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Avaliação salva com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao salvar avaliação');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

