<?php
/**
 * Gestão de Chat - RH
 */

$page_title = 'Gestão de Conversas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

require_page_permission('chat_gestao.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtros = [];
// Por padrão, mostra apenas conversas abertas (não fechadas)
$status_filtro = $_GET['status'] ?? 'aberta';
$filtros['status'] = $status_filtro; // 'aberta' será tratado como não-fechadas na função
$filtros['atribuido_para'] = $_GET['atribuido_para'] ?? '';
$filtros['prioridade'] = $_GET['prioridade'] ?? '';
$filtros['categoria_id'] = $_GET['categoria_id'] ?? (int)($_GET['categoria_id'] ?? 0);
$filtros['busca'] = $_GET['busca'] ?? '';
$filtros['limit'] = 50;

// Se não for admin, pode ver todas as conversas abertas
// O filtro de atribuição pode ser aplicado via interface se necessário
// Não aplicamos filtro automático aqui para não excluir conversas não atribuídas

// Debug: Verifica conversas no banco antes do filtro
$stmt_debug = $pdo->query("SELECT COUNT(*) as total FROM chat_conversas");
$total_geral = $stmt_debug->fetch()['total'];

$stmt_debug2 = $pdo->query("SELECT id, titulo, status FROM chat_conversas LIMIT 5");
$conversas_debug = $stmt_debug2->fetchAll();

// Carrega função de log do chat
require_once __DIR__ . '/../includes/chat_functions.php';

chat_log("Chat Gestao - Total de conversas no banco: " . $total_geral);
chat_log("Chat Gestao - Primeiras 5 conversas", $conversas_debug);
chat_log("Chat Gestao - Filtros aplicados", $filtros);
chat_log("Chat Gestao - Usuario role: " . ($usuario['role'] ?? 'N/A'));
chat_log("Chat Gestao - Usuario ID: " . ($usuario['id'] ?? 'N/A'));

// Busca conversas
$conversas = buscar_conversas_rh($filtros);

// Debug: Log para verificar conversas encontradas
chat_log("Chat Gestao - Total de conversas encontradas após filtro: " . count($conversas));
if (!empty($conversas)) {
    chat_log("Chat Gestao - Primeira conversa", $conversas[0]);
} else {
    // Testa query direta sem filtros
    $stmt_teste = $pdo->query("SELECT c.*, col.nome_completo as colaborador_nome FROM chat_conversas c INNER JOIN colaboradores col ON c.colaborador_id = col.id WHERE c.status NOT IN ('fechada', 'arquivada', 'resolvida') LIMIT 5");
    $teste = $stmt_teste->fetchAll();
    chat_log("Chat Gestao - Teste direto SQL: " . count($teste) . " conversas encontradas");
    if (!empty($teste)) {
        chat_log("Chat Gestao - Primeira conversa do teste", $teste[0]);
    }
}

// Busca categorias
$stmt = $pdo->query("SELECT id, nome, cor FROM chat_categorias WHERE ativo = TRUE ORDER BY ordem, nome");
$categorias = $stmt->fetchAll();

// Busca usuários RH para atribuição
$stmt = $pdo->query("SELECT id, nome FROM usuarios WHERE role IN ('ADMIN', 'RH') AND status = 'ativo' ORDER BY nome");
$usuarios_rh = $stmt->fetchAll();

// Estatísticas
$stmt = $pdo->query("SELECT * FROM vw_chat_metricas_gerais");
$metricas = $stmt->fetch();

// Conversa selecionada
$conversa_selecionada = null;
$conversa_id = (int)($_GET['conversa'] ?? 0);
if ($conversa_id > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, col.nome_completo as colaborador_nome, col.foto as colaborador_foto,
               col.email_pessoal as colaborador_email, col.telefone as colaborador_telefone,
               u.nome as atribuido_para_nome, cat.nome as categoria_nome
        FROM chat_conversas c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        LEFT JOIN usuarios u ON c.atribuido_para_usuario_id = u.id
        LEFT JOIN chat_categorias cat ON c.categoria_id = cat.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conversa_id]);
    $conversa_selecionada = $stmt->fetch();
    
    if ($conversa_selecionada) {
        // Busca mensagens
        $mensagens = buscar_mensagens_conversa($conversa_id, 1, 100);
    }
}
?>

<link href="../assets/css/chat-gestao.css" rel="stylesheet" type="text/css" />

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-fluid">
                
                <!-- Header com Estatísticas -->
                <div class="row g-5 g-xl-8 mb-5">
                    <div class="col-xl-3">
                        <div class="card card-flush h-xl-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <span class="text-gray-500 fw-semibold fs-6">Conversas Abertas</span>
                                    <h2 class="fw-bold mt-2"><?= $metricas['conversas_abertas'] ?? 0 ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3">
                        <div class="card card-flush h-xl-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <span class="text-gray-500 fw-semibold fs-6">Não Lidas</span>
                                    <h2 class="fw-bold mt-2 text-warning"><?= $metricas['conversas_nao_lidas_rh'] ?? 0 ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3">
                        <div class="card card-flush h-xl-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <span class="text-gray-500 fw-semibold fs-6">Tempo Médio Resposta</span>
                                    <h2 class="fw-bold mt-2">
                                        <?php
                                        $tempo = $metricas['tempo_medio_resposta_segundos'] ?? 0;
                                        if ($tempo > 0) {
                                            $minutos = floor($tempo / 60);
                                            echo $minutos . ' min';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3">
                        <div class="card card-flush h-xl-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <span class="text-gray-500 fw-semibold fs-6">SLA Cumprido</span>
                                    <h2 class="fw-bold mt-2 text-success">
                                        <?php
                                        $total = ($metricas['sla_primeira_resposta_cumprido'] ?? 0) + ($metricas['sla_primeira_resposta_nao_cumprido'] ?? 0);
                                        if ($total > 0) {
                                            $percent = round((($metricas['sla_primeira_resposta_cumprido'] ?? 0) / $total) * 100);
                                            echo $percent . '%';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Sidebar Esquerda: Lista de Conversas -->
                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-header border-0 pt-6">
                                <div class="card-title">
                                    <h3>Conversas</h3>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <!-- Filtros -->
                                <div class="mb-5">
                                    <form method="GET" id="filtros-form">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <input type="text" name="busca" class="form-control" 
                                                       placeholder="Buscar..." value="<?= htmlspecialchars($filtros['busca']) ?>">
                                            </div>
                                            <div class="col-6">
                                                <select name="status" class="form-select">
                                                    <option value="aberta" <?= ($filtros['status'] ?? '') === 'aberta' ? 'selected' : '' ?>>Abertas</option>
                                                    <option value="">Todos Status</option>
                                                    <option value="nova" <?= ($filtros['status'] ?? '') === 'nova' ? 'selected' : '' ?>>Nova</option>
                                                    <option value="em_atendimento" <?= ($filtros['status'] ?? '') === 'em_atendimento' ? 'selected' : '' ?>>Em Atendimento</option>
                                                    <option value="aguardando_colaborador" <?= ($filtros['status'] ?? '') === 'aguardando_colaborador' ? 'selected' : '' ?>>Aguardando Colaborador</option>
                                                    <option value="fechada" <?= ($filtros['status'] ?? '') === 'fechada' ? 'selected' : '' ?>>Fechada</option>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <select name="prioridade" class="form-select">
                                                    <option value="">Todas Prioridades</option>
                                                    <option value="urgente" <?= $filtros['prioridade'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                                                    <option value="alta" <?= $filtros['prioridade'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                                                    <option value="normal" <?= $filtros['prioridade'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                                    <option value="baixa" <?= $filtros['prioridade'] === 'baixa' ? 'selected' : '' ?>>Baixa</option>
                                                </select>
                                            </div>
                                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                                            <div class="col-12">
                                                <select name="atribuido_para" class="form-select">
                                                    <option value="">Todos RHs</option>
                                                    <option value="nao_atribuido">Não Atribuído</option>
                                                    <?php foreach ($usuarios_rh as $rh): ?>
                                                    <option value="<?= $rh['id'] ?>" <?= $filtros['atribuido_para'] == $rh['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($rh['nome']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <?php endif; ?>
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
                                            <p class="small">Filtros aplicados: <?= htmlspecialchars(json_encode($filtros)) ?></p>
                                            <?php if (isset($total_geral)): ?>
                                            <p class="small">Total no banco: <?= $total_geral ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($conversas_debug)): ?>
                                            <div class="mt-3 p-3 bg-light rounded">
                                                <p class="small fw-bold">Status das primeiras conversas:</p>
                                                <ul class="small text-start">
                                                    <?php foreach ($conversas_debug as $c): ?>
                                                    <li>ID: <?= $c['id'] ?> - Status: <strong><?= $c['status'] ?></strong> - Título: <?= htmlspecialchars($c['titulo']) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($conversas as $conv): 
                                            $avatar_colab = $conv['colaborador_foto'] ?? null;
                                            $nome_colab = $conv['colaborador_nome'] ?? 'Colaborador';
                                            $inicial_colab = mb_substr(mb_strtoupper($nome_colab), 0, 1);
                                        ?>
                                        <div class="chat-conversa-item <?= $conv['id'] == $conversa_id ? 'active' : '' ?> 
                                                                        <?= ($conv['total_mensagens_nao_lidas_rh'] ?? 0) > 0 ? 'nao-lida' : '' ?>"
                                             onclick="window.location.href='?conversa=<?= $conv['id'] ?>&<?= http_build_query($filtros) ?>'">
                                            <div class="d-flex align-items-center gap-3">
                                                <!-- Avatar -->
                                                <?php if ($avatar_colab): ?>
                                                <img src="../<?= htmlspecialchars($avatar_colab) ?>" 
                                                     alt="<?= htmlspecialchars($nome_colab) ?>" 
                                                     class="rounded-circle" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border: 2px solid #e4e6ef;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <?php endif; ?>
                                                <div class="chat-avatar-inicial rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" 
                                                     data-inicial="<?= htmlspecialchars($inicial_colab) ?>"
                                                     style="width: 50px; height: 50px; border: 2px solid #e4e6ef; <?= $avatar_colab ? 'display: none;' : '' ?>">
                                                    <?= htmlspecialchars($inicial_colab) ?>
                                                </div>
                                                
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?= htmlspecialchars($conv['titulo'] ?? 'Sem título') ?></div>
                                                    <div class="text-muted small">
                                                        <?= htmlspecialchars($conv['colaborador_nome'] ?? 'Colaborador') ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?= $conv['ultima_mensagem_at'] ? date('d/m/Y H:i', strtotime($conv['ultima_mensagem_at'])) : 'N/A' ?>
                                                    </div>
                                                </div>
                                                <?php if (($conv['total_mensagens_nao_lidas_rh'] ?? 0) > 0): ?>
                                                <span class="badge badge-danger"><?= $conv['total_mensagens_nao_lidas_rh'] ?></span>
                                                <?php endif; ?>
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
                                        <div class="d-flex align-items-center gap-2 text-muted mb-1">
                                            <i class="ki-duotone ki-user fs-6"><span class="path1"></span><span class="path2"></span></i>
                                            <span class="fw-semibold"><?= htmlspecialchars($conversa_selecionada['colaborador_nome']) ?></span>
                                        </div>
                                    </div>
                                    <div class="card-toolbar">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                                <i class="ki-duotone ki-setting-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="atribuirConversa(<?= $conversa_id ?>)">Atribuir</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="gerarResumoIA(<?= $conversa_id ?>)">Gerar Resumo IA</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="fecharConversa(<?= $conversa_id ?>)">Fechar Conversa</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Mensagens -->
                                    <div class="chat-mensagens-container" id="chat-mensagens">
                                        <?php 
                                        $ultimo_remetente = null;
                                        $ultima_data = null;
                                        $usuario_logado_id = $usuario['id'] ?? null;
                                        
                                        foreach ($mensagens as $index => $msg): 
                                            $eh_rh = !empty($msg['enviado_por_usuario_id']);
                                            $remetente_atual = $eh_rh ? 'rh' : 'colaborador';
                                            $data_atual = date('d/m/Y', strtotime($msg['created_at']));
                                            $consecutiva = ($ultimo_remetente === $remetente_atual && $ultima_data === $data_atual);
                                            
                                            // Verifica se a mensagem foi enviada pelo usuário RH logado (minha mensagem = direita)
                                            $eh_minha_mensagem = false;
                                            if ($eh_rh && !empty($msg['enviado_por_usuario_id']) && !empty($usuario_logado_id)) {
                                                $eh_minha_mensagem = ((int)$msg['enviado_por_usuario_id'] === (int)$usuario_logado_id);
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
                                        
                                        <div class="chat-mensagem-wrapper <?= $eh_minha_mensagem ? 'mensagem-rh' : 'mensagem-colaborador' ?>" data-msg-id="<?= $msg['id'] ?>">
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
                                                            <audio controls style="max-width: 250px;">
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
                                                <button type="button" class="btn btn-sm btn-light rounded-circle d-flex align-items-center justify-content-center" onclick="iniciarGravacaoVoz(<?= $conversa_selecionada['id'] ?>)" title="Mensagem de voz" style="width: 40px; height: 40px;">
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
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
    // Define ID do usuário logado para o JavaScript
    window.CHAT_USUARIO_ID = <?= $usuario['id'] ?? 'null' ?>;
</script>
<script src="../assets/js/chat-gestao.js"></script>
<script src="../assets/js/chat-audio.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

