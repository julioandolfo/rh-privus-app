<?php
/**
 * Chat - Colaborador
 * Tela completa de chat para colaboradores verem suas conversas
 */

$page_title = 'Meu Chat';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

// Apenas colaboradores podem acessar
if (!is_colaborador() || empty($_SESSION['usuario']['colaborador_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = $usuario['colaborador_id'];

// Filtros
$filtros = [];
$status_filtro = $_GET['status'] ?? 'aberta';
$filtros['status'] = $status_filtro;
$filtros['busca'] = $_GET['busca'] ?? '';
$filtros['limit'] = 50;

// Busca conversas do colaborador
$conversas = buscar_conversas_colaborador($colaborador_id, $filtros['status'] === 'aberta' ? 'aberta' : ($filtros['status'] ?: null), $filtros['limit']);

// Busca categorias
$stmt = $pdo->query("SELECT id, nome, cor FROM chat_categorias WHERE ativo = TRUE ORDER BY ordem, nome");
$categorias = $stmt->fetchAll();

// Conversa selecionada
$conversa_selecionada = null;
$conversa_id = (int)($_GET['conversa'] ?? 0);
if ($conversa_id > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, col.nome_completo as colaborador_nome, col.foto as colaborador_foto,
               col.email_pessoal as colaborador_email, col.telefone as colaborador_telefone,
               u.nome as atribuido_para_nome, cat.nome as categoria_nome, cat.cor as categoria_cor
        FROM chat_conversas c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        LEFT JOIN usuarios u ON c.atribuido_para_usuario_id = u.id
        LEFT JOIN chat_categorias cat ON c.categoria_id = cat.id
        WHERE c.id = ? AND c.colaborador_id = ?
    ");
    $stmt->execute([$conversa_id, $colaborador_id]);
    $conversa_selecionada = $stmt->fetch();
    
    if (!$conversa_selecionada) {
        header('Location: chat_colaborador.php');
        exit;
    }
    
    if ($conversa_selecionada) {
        // Busca mensagens
        $mensagens = buscar_mensagens_conversa($conversa_id, 1, 100);
        
        // Marca mensagens como lidas quando a conversa é aberta
        marcar_mensagens_lidas($conversa_id, null, $colaborador_id);
        
        // Busca resumos IA se existirem
        $stmt_resumos = $pdo->prepare("SELECT * FROM chat_resumos_ia WHERE conversa_id = ? ORDER BY created_at DESC");
        $stmt_resumos->execute([$conversa_id]);
        $resumos_ia = $stmt_resumos->fetchAll();
    }
}
?>

<link href="../assets/css/chat-gestao.css" rel="stylesheet" type="text/css" />

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-fluid">
                
                <div class="row">
                    <!-- Sidebar Esquerda: Lista de Conversas -->
                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-header border-0 pt-6">
                                <div class="card-title">
                                    <h3>Minhas Conversas</h3>
                                </div>
                                <div class="card-toolbar">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalNovaConversa()">
                                        <i class="ki-duotone ki-plus fs-2"><span class="path1"></span><span class="path2"></span></i>
                                        Nova Conversa
                                    </button>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <!-- Filtros -->
                                <div class="mb-5">
                                    <form method="GET" id="filtros-form">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <input type="text" name="busca" class="form-control" 
                                                       placeholder="Buscar conversas..." value="<?= htmlspecialchars($filtros['busca']) ?>">
                                            </div>
                                            <div class="col-12">
                                                <select name="status" class="form-select">
                                                    <option value="aberta" <?= ($filtros['status'] ?? '') === 'aberta' ? 'selected' : '' ?>>Abertas</option>
                                                    <option value="">Todas</option>
                                                    <option value="fechada" <?= ($filtros['status'] ?? '') === 'fechada' ? 'selected' : '' ?>>Fechadas</option>
                                                    <option value="resolvida" <?= ($filtros['status'] ?? '') === 'resolvida' ? 'selected' : '' ?>>Resolvidas</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Lista de Conversas -->
                                <div class="chat-conversas-list">
                                    <?php if (empty($conversas)): ?>
                                        <div class="text-center text-muted py-10">
                                            <p>Nenhuma conversa encontrada</p>
                                            <?php if (isset($_GET['nova'])): ?>
                                            <a href="../api/chat/conversas/criar.php" class="btn btn-primary mt-3">Criar Nova Conversa</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($conversas as $conv): 
                                            $avatar_rh = null; // Colaborador não tem avatar do RH na listagem
                                            $nome_rh = $conv['atribuido_para_nome'] ?? 'RH';
                                            $inicial_rh = mb_substr(mb_strtoupper($nome_rh), 0, 1);
                                            $is_active = $conv['id'] == $conversa_id;
                                            $nao_lidas = ($conv['total_mensagens_nao_lidas_colaborador'] ?? 0);
                                            
                                            // Preview da mensagem (limita a 60 caracteres)
                                            $preview = '';
                                            if (!empty($conv['ultima_mensagem_preview'])) {
                                                $preview = mb_substr(strip_tags($conv['ultima_mensagem_preview']), 0, 60);
                                                if (mb_strlen($conv['ultima_mensagem_preview']) > 60) {
                                                    $preview .= '...';
                                                }
                                            } else {
                                                $preview = 'Nenhuma mensagem ainda';
                                            }
                                            
                                            // Formata timestamp
                                            $timestamp = '';
                                            if ($conv['ultima_mensagem_at']) {
                                                $data_msg = new DateTime($conv['ultima_mensagem_at']);
                                                $agora = new DateTime();
                                                $diff = $agora->diff($data_msg);
                                                
                                                if ($diff->days == 0) {
                                                    if ($diff->h > 0) {
                                                        $timestamp = $diff->h . 'h';
                                                    } elseif ($diff->i > 0) {
                                                        $timestamp = $diff->i . 'min';
                                                    } else {
                                                        $timestamp = 'Agora';
                                                    }
                                                } elseif ($diff->days == 1) {
                                                    $timestamp = 'Ontem';
                                                } else {
                                                    $timestamp = $diff->days . 'd';
                                                }
                                            }
                                            
                                            // Indicador de prioridade
                                            $prioridade_icon = '';
                                            if ($conv['prioridade'] === 'urgente') {
                                                $prioridade_icon = '<span class="chat-prioridade-indicator" style="color: #f1416c;">!!!</span>';
                                            } elseif ($conv['prioridade'] === 'alta') {
                                                $prioridade_icon = '<span class="chat-prioridade-indicator" style="color: #ffc700;">!!</span>';
                                            } elseif ($conv['prioridade'] === 'normal') {
                                                $prioridade_icon = '<span class="chat-prioridade-indicator" style="color: #009ef7;">!</span>';
                                            }
                                        ?>
                                        <div class="chat-conversa-item-modern <?= $is_active ? 'active' : '' ?> 
                                                                        <?= $nao_lidas > 0 ? 'nao-lida' : '' ?>"
                                             onclick="window.location.href='?conversa=<?= $conv['id'] ?>&<?= http_build_query($filtros) ?>'">
                                            <?php if ($is_active): ?>
                                            <div class="chat-conversa-active-bar"></div>
                                            <?php endif; ?>
                                            
                                            <div class="chat-conversa-content">
                                                <!-- Ícone de canal -->
                                                <div class="chat-conversa-channel-icon">
                                                    <i class="ki-duotone ki-message-text-2 fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                </div>
                                                
                                                <!-- Avatar (do RH atribuído ou inicial) -->
                                                <div class="chat-conversa-avatar-wrapper">
                                                    <div class="chat-conversa-avatar chat-avatar-inicial d-flex align-items-center justify-content-center fw-bold text-white" 
                                                         data-inicial="<?= htmlspecialchars($inicial_rh) ?>">
                                                        <?= htmlspecialchars($inicial_rh) ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Conteúdo -->
                                                <div class="chat-conversa-info flex-grow-1">
                                                    <div class="chat-conversa-header">
                                                        <span class="chat-conversa-nome fw-bold"><?= htmlspecialchars($conv['titulo'] ?? 'Sem título') ?></span>
                                                        <?php if ($prioridade_icon): ?>
                                                        <span class="chat-prioridade-wrapper"><?= $prioridade_icon ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($timestamp): ?>
                                                        <span class="chat-conversa-time"><?= htmlspecialchars($timestamp) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="chat-conversa-preview">
                                                        <?= htmlspecialchars($preview) ?>
                                                    </div>
                                                    
                                                    <div class="chat-conversa-footer">
                                                        <?php if ($conv['categoria_id']): ?>
                                                        <?php 
                                                        $categoria = array_filter($categorias, function($cat) use ($conv) {
                                                            return $cat['id'] == $conv['categoria_id'];
                                                        });
                                                        $cat = !empty($categoria) ? reset($categoria) : null;
                                                        ?>
                                                        <?php if ($cat): ?>
                                                        <span class="chat-conversa-tag" style="background-color: <?= htmlspecialchars($cat['cor'] ?? '#6c757d') ?>20; color: <?= htmlspecialchars($cat['cor'] ?? '#6c757d') ?>;">
                                                            <?= htmlspecialchars($cat['nome']) ?>
                                                        </span>
                                                        <?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if ($nao_lidas > 0): ?>
                                                        <span class="chat-conversa-badge"><?= $nao_lidas > 99 ? '99+' : $nao_lidas ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Área Central: Conversa -->
                    <div class="col-xl-8">
                        <?php if ($conversa_selecionada): ?>
                            <!-- Conversa Aberta -->
                            <div class="card">
                                <div class="card-header border-0">
                                    <div class="card-title d-flex flex-column">
                                        <h3 class="mb-2"><?= htmlspecialchars($conversa_selecionada['titulo']) ?></h3>
                                        <?php if ($conversa_selecionada['atribuido_para_nome']): ?>
                                        <div class="d-flex align-items-center gap-2 text-muted mb-1">
                                            <i class="ki-duotone ki-user fs-6"><span class="path1"></span><span class="path2"></span></i>
                                            <span class="fw-semibold">Atendido por: <?= htmlspecialchars($conversa_selecionada['atribuido_para_nome']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-toolbar">
                                        <span class="badge badge-<?= $conversa_selecionada['status'] === 'fechada' ? 'secondary' : 'success' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $conversa_selecionada['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Resumos IA se existirem -->
                                    <?php if (!empty($resumos_ia)): ?>
                                    <div class="mb-5">
                                        <h5 class="mb-3">Resumos IA</h5>
                                        <?php foreach ($resumos_ia as $resumo): ?>
                                        <div class="alert alert-info">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <strong>Resumo gerado em <?= date('d/m/Y H:i', strtotime($resumo['created_at'])) ?></strong>
                                                <small class="text-muted">Modelo: <?= htmlspecialchars($resumo['modelo_usado'] ?? 'N/A') ?></small>
                                            </div>
                                            <div class="mb-2"><?= nl2br(htmlspecialchars($resumo['resumo'])) ?></div>
                                            <?php if ($resumo['tokens_usados']): ?>
                                            <small class="text-muted">Tokens: <?= $resumo['tokens_usados'] ?> | Custo estimado: R$ <?= number_format($resumo['custo_estimado'] ?? 0, 4, ',', '.') ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Mensagens -->
                                    <div class="chat-mensagens-container" id="chat-mensagens">
                                        <?php 
                                        $ultimo_remetente = null;
                                        $ultima_data = null;
                                        $colaborador_logado_id = $colaborador_id;
                                        
                                        foreach ($mensagens as $index => $msg): 
                                            $eh_rh = !empty($msg['enviado_por_usuario_id']);
                                            $remetente_atual = $eh_rh ? 'rh' : 'colaborador';
                                            $data_atual = date('d/m/Y', strtotime($msg['created_at']));
                                            
                                            // Verifica se a mensagem foi enviada pelo colaborador logado (minha mensagem = direita)
                                            $eh_minha_mensagem = false;
                                            if (!$eh_rh && !empty($msg['enviado_por_colaborador_id']) && !empty($colaborador_logado_id)) {
                                                $eh_minha_mensagem = ((int)$msg['enviado_por_colaborador_id'] === (int)$colaborador_logado_id);
                                            }
                                            
                                            // Avatar e nome do remetente
                                            $avatar = $eh_rh 
                                                ? ($msg['usuario_foto'] ?? null)
                                                : ($msg['colaborador_foto'] ?? null);
                                            
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
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php 
                                            $ultimo_remetente = $remetente_atual;
                                            $ultima_data = $data_atual;
                                        endforeach; 
                                        ?>
                                    </div>
                                    
                                    <!-- Formulário de Resposta -->
                                    <?php if ($conversa_selecionada['status'] !== 'fechada'): ?>
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
                        <?php else: ?>
                            <!-- Nenhuma conversa selecionada -->
                            <div class="card">
                                <div class="card-body text-center py-20">
                                    <i class="ki-duotone ki-message-text-2 fs-3x text-muted mb-5">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                    </i>
                                    <h3 class="text-muted">Selecione uma conversa</h3>
                                    <p class="text-muted">Escolha uma conversa da lista ao lado para visualizar as mensagens</p>
                                    <button type="button" class="btn btn-primary mt-3" onclick="abrirModalNovaConversa()">
                                        <i class="ki-duotone ki-plus"><span class="path1"></span><span class="path2"></span></i>
                                        Criar Nova Conversa
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Conversa -->
<div class="modal fade" id="modalNovaConversa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Nova Conversa</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formNovaConversa">
                <div class="modal-body">
                    <div class="mb-5">
                        <label class="form-label required">Título</label>
                        <input type="text" name="titulo" class="form-control" required placeholder="Ex: Dúvida sobre férias">
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Categoria</label>
                        <select name="categoria_id" id="selectCategoria" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Prioridade</label>
                        <select name="prioridade" class="form-select">
                            <option value="normal">Normal</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                            <option value="baixa">Baixa</option>
                        </select>
                    </div>
                    <div class="mb-5">
                        <label class="form-label required">Mensagem</label>
                        <textarea name="mensagem" class="form-control" rows="4" required placeholder="Descreva sua solicitação..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-duotone ki-send"><span class="path1"></span><span class="path2"></span></i>
                        Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Define ID do colaborador logado para o JavaScript
    window.CHAT_COLABORADOR_ID = <?= $colaborador_id ?? 'null' ?>;
    
    // Função para abrir modal de nova conversa
    function abrirModalNovaConversa() {
        const modal = new bootstrap.Modal(document.getElementById('modalNovaConversa'));
        modal.show();
    }
    
    // Abre modal automaticamente se ?nova=1 estiver na URL
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('nova') === '1') {
            abrirModalNovaConversa();
            // Remove o parâmetro da URL sem recarregar
            window.history.replaceState({}, '', window.location.pathname);
        }
        
        // Atualiza badge do widget quando a conversa é visualizada
        setTimeout(() => {
            if (typeof ChatWidget !== 'undefined' && ChatWidget.carregarConversas) {
                ChatWidget.carregarConversas();
            }
        }, 1000);
    });
    
    // Submit do formulário de nova conversa
    document.getElementById('formNovaConversa').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
        
        fetch('../api/chat/conversas/criar.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error('Resposta inválida do servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: 'Conversa criada com sucesso!',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Fecha modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovaConversa'));
                    if (modal) modal.hide();
                    
                    // Redireciona para a nova conversa
                    if (data.conversa_id) {
                        window.location.href = 'chat_colaborador.php?conversa=' + data.conversa_id;
                    } else {
                        window.location.reload();
                    }
                });
            } else {
                throw new Error(data.message || 'Erro ao criar conversa');
            }
        })
        .catch(error => {
            console.error('Erro ao criar conversa:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: error.message || 'Erro ao criar conversa. Tente novamente.'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
</script>
<script src="../assets/js/chat-conversa.js"></script>
<script src="../assets/js/chat-audio.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

