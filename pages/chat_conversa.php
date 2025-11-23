<?php
/**
 * Visualização de Conversa Individual (para colaboradores)
 */

$page_title = 'Conversa';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

// Apenas colaboradores podem acessar
if (!is_colaborador() || empty($_SESSION['usuario']['colaborador_id'])) {
    header('Location: dashboard.php');
    exit;
}

$conversa_id = (int)($_GET['id'] ?? 0);
if (empty($conversa_id)) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca conversa
$stmt = $pdo->prepare("
    SELECT c.*, col.nome_completo as colaborador_nome, col.foto as colaborador_foto
    FROM chat_conversas c
    INNER JOIN colaboradores col ON c.colaborador_id = col.id
    WHERE c.id = ? AND c.colaborador_id = ?
");
$stmt->execute([$conversa_id, $usuario['colaborador_id']]);
$conversa = $stmt->fetch();

if (!$conversa) {
    header('Location: dashboard.php');
    exit;
}

// Busca mensagens com informações completas
$stmt = $pdo->prepare("
    SELECT m.*,
           u.nome as usuario_nome,
           u.foto as usuario_foto,
           col.nome_completo as colaborador_nome,
           col.foto as colaborador_foto
    FROM chat_mensagens m
    LEFT JOIN usuarios u ON m.enviado_por_usuario_id = u.id
    LEFT JOIN colaboradores col ON m.enviado_por_colaborador_id = col.id
    WHERE m.conversa_id = ? AND m.deletada = FALSE
    ORDER BY m.created_at ASC
    LIMIT 100
");
$stmt->execute([$conversa_id]);
$mensagens = $stmt->fetchAll();

// Busca categorias
$stmt = $pdo->query("SELECT id, nome FROM chat_categorias WHERE ativo = TRUE ORDER BY ordem, nome");
$categorias = $stmt->fetchAll();
?>

<link href="../assets/css/chat-gestao.css" rel="stylesheet" type="text/css" />

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0">
                        <div class="card-title d-flex flex-column">
                            <h3 class="mb-2"><?= htmlspecialchars($conversa['titulo']) ?></h3>
                            <div class="d-flex align-items-center gap-2 text-muted mb-1">
                                <i class="ki-duotone ki-user fs-6"><span class="path1"></span><span class="path2"></span></i>
                                <span class="fw-semibold"><?= htmlspecialchars($conversa['colaborador_nome']) ?></span>
                            </div>
                            <div class="text-muted small">
                                Status: <span class="badge badge-<?= $conversa['status'] === 'fechada' ? 'secondary' : 'primary' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $conversa['status'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <a href="dashboard.php" class="btn btn-light">
                                <i class="ki-duotone ki-arrow-left"><span class="path1"></span><span class="path2"></span></i>
                                Voltar
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Mensagens -->
                        <div class="chat-mensagens-container" id="chat-mensagens">
                            <?php 
                            $ultimo_remetente = null;
                            $ultima_data = null;
                            $colaborador_logado_id = $usuario['colaborador_id'] ?? null;
                            
                            foreach ($mensagens as $index => $msg): 
                                $eh_rh = !empty($msg['enviado_por_usuario_id']);
                                $remetente_atual = $eh_rh ? 'rh' : 'colaborador';
                                $data_atual = date('d/m/Y', strtotime($msg['created_at']));
                                
                                // Verifica se a mensagem foi enviada pelo colaborador logado
                                $eh_minha_mensagem = false;
                                if (!$eh_rh && !empty($msg['enviado_por_colaborador_id']) && !empty($colaborador_logado_id)) {
                                    $eh_minha_mensagem = ((int)$msg['enviado_por_colaborador_id'] === (int)$colaborador_logado_id);
                                }
                                
                                // Avatar
                                $avatar = $eh_rh 
                                    ? ($msg['usuario_foto'] ?? null)
                                    : ($msg['colaborador_foto'] ?? null);
                                
                                // Nome do remetente
                                $nome_remetente = $eh_rh 
                                    ? ($msg['usuario_nome'] ?? 'RH')
                                    : ($msg['colaborador_nome'] ?? 'Colaborador');
                                
                                $inicial_remetente = mb_substr(mb_strtoupper($nome_remetente), 0, 1);
                                
                                // Mostra separador de data se mudou
                                if ($ultima_data !== $data_atual):
                            ?>
                                <div class="chat-data-separator">
                                    <span><?= date('d/m/Y', strtotime($msg['created_at'])) == date('d/m/Y') ? 'Hoje' : date('d/m/Y', strtotime($msg['created_at'])) ?></span>
                                </div>
                            <?php endif; ?>
                            
                                <div class="chat-mensagem-wrapper <?= $eh_minha_mensagem ? 'mensagem-rh' : 'mensagem-colaborador' ?>" data-msg-id="<?= $msg['id'] ?>" data-msg-date="<?= $data_atual ?>">
                                <!-- Avatar -->
                                <?php if ($avatar): ?>
                                <img src="../<?= htmlspecialchars($avatar) ?>" 
                                     alt="<?= htmlspecialchars($nome_remetente) ?>" 
                                     class="chat-mensagem-avatar"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <?php endif; ?>
                                <div class="chat-mensagem-avatar chat-avatar-inicial d-flex align-items-center justify-content-center fw-bold text-white" 
                                     data-inicial="<?= htmlspecialchars($inicial_remetente) ?>"
                                     style="<?= $avatar ? 'display: none;' : '' ?>">
                                    <?= htmlspecialchars($inicial_remetente) ?>
                                </div>
                                
                                <!-- Container da Mensagem -->
                                <div class="chat-mensagem">
                                    <!-- Enviado por -->
                                    <div class="chat-mensagem-enviado-por">
                                        <?= $eh_minha_mensagem ? 'Enviado por mim' : 'Enviado por: ' . htmlspecialchars($nome_remetente) ?>
                                    </div>
                                    
                                    <!-- Conteúdo -->
                                    <div class="chat-mensagem-conteudo">
                                        <?php if ($msg['tipo'] === 'voz' && $msg['voz_caminho']): ?>
                                            <div class="chat-mensagem-voz">
                                                <audio controls>
                                                    <source src="../<?= htmlspecialchars($msg['voz_caminho']) ?>" type="audio/mpeg">
                                                </audio>
                                                <?php if ($msg['voz_transcricao']): ?>
                                                <div class="chat-voz-transcricao">
                                                    <small><?= htmlspecialchars($msg['voz_transcricao']) ?></small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($msg['tipo'] === 'anexo' && $msg['anexo_caminho']): ?>
                                            <div class="chat-mensagem-anexo">
                                                <a href="../<?= htmlspecialchars($msg['anexo_caminho']) ?>" target="_blank" class="d-inline-flex align-items-center gap-2 p-2 bg-light rounded text-decoration-none">
                                                    <i class="ki-duotone ki-file-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                                                    <span><?= htmlspecialchars($msg['anexo_nome_original']) ?></span>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="chat-mensagem-texto">
                                                <?= nl2br(htmlspecialchars($msg['mensagem'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Timestamp -->
                                    <div class="chat-mensagem-timestamp">
                                        <span><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></span>
                                        <?php if ($eh_rh && $msg['lida_por_colaborador']): ?>
                                        <i class="ki-duotone ki-check fs-8 text-primary"><span class="path1"></span><span class="path2"></span></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php 
                                $ultimo_remetente = $remetente_atual;
                                $ultima_data = $data_atual;
                            endforeach; 
                            ?>
                        </div>
                        
                        <?php if ($conversa['status'] !== 'fechada'): ?>
                        <!-- Formulário de Resposta - Estilo WhatsApp -->
                        <div class="chat-resposta-container">
                            <form id="chat-form-resposta" enctype="multipart/form-data">
                                <input type="hidden" name="conversa_id" value="<?= $conversa_id ?>">
                                <div class="d-flex align-items-end gap-2">
                                    <button type="button" class="btn btn-sm btn-light rounded-circle d-flex align-items-center justify-content-center" onclick="document.getElementById('chat-anexo').click()" title="Anexar arquivo" style="width: 40px; height: 40px;">
                                        <i class="ki-duotone ki-file-up" style="font-size: 1.25rem !important; display: inline-flex;"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                    <input type="file" id="chat-anexo" name="anexo" style="display: none;" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <div class="flex-grow-1">
                                        <textarea name="mensagem" class="form-control" rows="1" 
                                                  placeholder="Digite uma mensagem..." 
                                                  style="resize: none; border-radius: 24px; padding: 10px 20px;"
                                                  onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); document.getElementById('chat-form-resposta').dispatchEvent(new Event('submit')); }"
                                                  required></textarea>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light rounded-circle d-flex align-items-center justify-content-center" onclick="iniciarGravacaoVoz(<?= $conversa_id ?>)" title="Mensagem de voz" style="width: 40px; height: 40px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                            <line x1="12" y1="19" x2="12" y2="23"></line>
                                            <line x1="8" y1="23" x2="16" y2="23"></line>
                                        </svg>
                                    </button>
                                    <button type="submit" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="ki-duotone ki-send" style="font-size: 1.25rem !important; display: inline-flex;"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mt-5">
                            <i class="ki-duotone ki-information-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Esta conversa foi fechada. Você pode criar uma nova conversa se necessário.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
    // Define ID do colaborador logado para o JavaScript
    window.CHAT_COLABORADOR_ID = <?= $usuario['colaborador_id'] ?? 'null' ?>;
</script>
<script src="../assets/js/chat-conversa.js"></script>
<script src="../assets/js/chat-audio.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

