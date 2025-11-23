<?php
/**
 * Configurações do Chat
 */

$page_title = 'Configurações do Chat';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

require_page_permission('chat_configuracoes.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa salvamento
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $chave => $valor) {
        if (strpos($chave, 'config_') === 0) {
            $chave_real = str_replace('config_', '', $chave);
            $stmt = $pdo->prepare("UPDATE chat_configuracoes SET valor = ? WHERE chave = ?");
            $stmt->execute([$valor, $chave_real]);
        }
    }
    $mensagem = 'Configurações salvas com sucesso!';
}

// Busca configurações
$stmt = $pdo->query("SELECT chave, valor, tipo, descricao FROM chat_configuracoes ORDER BY chave");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$configs_array = [];
foreach ($configs as $config) {
    $configs_array[$config['chave']] = $config;
}

// Busca configurações de SLA
$stmt = $pdo->query("SELECT * FROM chat_sla_config WHERE ativo = TRUE ORDER BY empresa_id, nome");
$sla_configs = $stmt->fetchAll();
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
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Configurações do Chat</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <form method="POST">
                            <!-- Configurações Gerais -->
                            <div class="mb-10">
                                <h3 class="mb-5">Configurações Gerais</h3>
                                
                                <div class="row mb-5">
                                    <div class="col-md-6">
                                        <label class="form-label">Chat Ativo</label>
                                        <select name="config_chat_ativo" class="form-select">
                                            <option value="true" <?= ($configs_array['chat_ativo']['valor'] ?? 'true') === 'true' ? 'selected' : '' ?>>Sim</option>
                                            <option value="false" <?= ($configs_array['chat_ativo']['valor'] ?? 'true') === 'false' ? 'selected' : '' ?>>Não</option>
                                        </select>
                                        <div class="form-text"><?= htmlspecialchars($configs_array['chat_ativo']['descricao'] ?? '') ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Notificações Push Ativas</label>
                                        <select name="config_notificacoes_push_ativas" class="form-select">
                                            <option value="true" <?= ($configs_array['notificacoes_push_ativas']['valor'] ?? 'true') === 'true' ? 'selected' : '' ?>>Sim</option>
                                            <option value="false" <?= ($configs_array['notificacoes_push_ativas']['valor'] ?? 'true') === 'false' ? 'selected' : '' ?>>Não</option>
                                        </select>
                                        <div class="form-text"><?= htmlspecialchars($configs_array['notificacoes_push_ativas']['descricao'] ?? '') ?></div>
                                    </div>
                                </div>
                                
                                <div class="row mb-5">
                                    <div class="col-md-6">
                                        <label class="form-label">Notificações Sonoras Ativas</label>
                                        <select name="config_notificacoes_sonoras_ativas" class="form-select">
                                            <option value="true" <?= ($configs_array['notificacoes_sonoras_ativas']['valor'] ?? 'true') === 'true' ? 'selected' : '' ?>>Sim</option>
                                            <option value="false" <?= ($configs_array['notificacoes_sonoras_ativas']['valor'] ?? 'true') === 'false' ? 'selected' : '' ?>>Não</option>
                                        </select>
                                        <div class="form-text"><?= htmlspecialchars($configs_array['notificacoes_sonoras_ativas']['descricao'] ?? '') ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Tamanho Máximo Anexo (MB)</label>
                                        <input type="number" name="config_max_tamanho_anexo_mb" class="form-control" 
                                               value="<?= htmlspecialchars($configs_array['max_tamanho_anexo_mb']['valor'] ?? '10') ?>">
                                        <div class="form-text"><?= htmlspecialchars($configs_array['max_tamanho_anexo_mb']['descricao'] ?? '') ?></div>
                                    </div>
                                </div>
                                
                                <div class="row mb-5">
                                    <div class="col-md-6">
                                        <label class="form-label">Tamanho Máximo Voz (MB)</label>
                                        <input type="number" name="config_max_tamanho_voz_mb" class="form-control" 
                                               value="<?= htmlspecialchars($configs_array['max_tamanho_voz_mb']['valor'] ?? '5') ?>">
                                        <div class="form-text"><?= htmlspecialchars($configs_array['max_tamanho_voz_mb']['descricao'] ?? '') ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Transcrição Automática de Voz</label>
                                        <select name="config_voz_transcricao_ativa" class="form-select">
                                            <option value="false" <?= ($configs_array['voz_transcricao_ativa']['valor'] ?? 'false') === 'false' ? 'selected' : '' ?>>Não</option>
                                            <option value="true" <?= ($configs_array['voz_transcricao_ativa']['valor'] ?? 'false') === 'true' ? 'selected' : '' ?>>Sim</option>
                                        </select>
                                        <div class="form-text">Requer API Key do OpenAI configurada</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configurações ChatGPT -->
                            <div class="mb-10">
                                <h3 class="mb-5">Integração com ChatGPT</h3>
                                
                                <div class="row mb-5">
                                    <div class="col-md-12">
                                        <label class="form-label">ChatGPT Ativo</label>
                                        <select name="config_chatgpt_ativo" class="form-select">
                                            <option value="false" <?= ($configs_array['chatgpt_ativo']['valor'] ?? 'false') === 'false' ? 'selected' : '' ?>>Não</option>
                                            <option value="true" <?= ($configs_array['chatgpt_ativo']['valor'] ?? 'false') === 'true' ? 'selected' : '' ?>>Sim</option>
                                        </select>
                                        <div class="form-text">Ativa integração com ChatGPT para gerar resumos</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-5">
                                    <div class="col-md-12">
                                        <label class="form-label">API Key do OpenAI</label>
                                        <input type="password" name="config_chatgpt_api_key" class="form-control" 
                                               value="<?= htmlspecialchars($configs_array['chatgpt_api_key']['valor'] ?? '') ?>" 
                                               placeholder="sk-...">
                                        <div class="form-text">Chave de API do OpenAI (mantenha em segredo)</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-5">
                                    <div class="col-md-4">
                                        <label class="form-label">Modelo</label>
                                        <select name="config_chatgpt_modelo" class="form-select">
                                            <option value="gpt-4o" <?= ($configs_array['chatgpt_modelo']['valor'] ?? 'gpt-4') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                                            <option value="gpt-4o-mini" <?= ($configs_array['chatgpt_modelo']['valor'] ?? 'gpt-4') === 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o Mini</option>
                                            <option value="gpt-4-turbo" <?= ($configs_array['chatgpt_modelo']['valor'] ?? 'gpt-4') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                                            <option value="gpt-4" <?= ($configs_array['chatgpt_modelo']['valor'] ?? 'gpt-4') === 'gpt-4' ? 'selected' : '' ?>>GPT-4</option>
                                            <option value="gpt-3.5-turbo" <?= ($configs_array['chatgpt_modelo']['valor'] ?? 'gpt-4') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
                                            <option value="gpt-5" <?= ($configs_array['chatgpt_modelo']['valor'] ?? 'gpt-4') === 'gpt-5' ? 'selected' : '' ?>>GPT-5 (Futuro)</option>
                                        </select>
                                        <div class="form-text">Modelos mais recentes recomendados: GPT-4o ou GPT-4o Mini</div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Temperatura</label>
                                        <input type="number" name="config_chatgpt_temperatura" class="form-control" 
                                               value="<?= htmlspecialchars($configs_array['chatgpt_temperatura']['valor'] ?? '0.7') ?>" 
                                               step="0.1" min="0" max="1">
                                        <div class="form-text">0.0 (determinístico) a 1.0 (criativo)</div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Máximo de Tokens</label>
                                        <input type="number" name="config_chatgpt_max_tokens" class="form-control" 
                                               value="<?= htmlspecialchars($configs_array['chatgpt_max_tokens']['valor'] ?? '500') ?>">
                                        <div class="form-text">Máximo de tokens para resumo</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configurações de Horário -->
                            <div class="mb-10">
                                <h3 class="mb-5">Horário de Atendimento</h3>
                                
                                <div class="row mb-5">
                                    <div class="col-md-6">
                                        <label class="form-label">Horário de Início</label>
                                        <input type="time" name="config_horario_atendimento_inicio" class="form-control" 
                                               value="<?= htmlspecialchars($configs_array['horario_atendimento_inicio']['valor'] ?? '08:00') ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Horário de Fim</label>
                                        <input type="time" name="config_horario_atendimento_fim" class="form-control" 
                                               value="<?= htmlspecialchars($configs_array['horario_atendimento_fim']['valor'] ?? '18:00') ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-5">
                                    <div class="col-md-12">
                                        <label class="form-label">Mensagem Automática Fora do Horário</label>
                                        <textarea name="config_mensagem_automatica_fora_horario" class="form-control" rows="3"><?= htmlspecialchars($configs_array['mensagem_automatica_fora_horario']['valor'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ki-duotone ki-check fs-2"></i>
                                    Salvar Configurações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Configurações de SLA -->
                <div class="card mt-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3>Configurações de SLA</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="chat_sla_config.php" class="btn btn-primary">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Nova Configuração SLA
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <?php if (empty($sla_configs)): ?>
                            <div class="text-center text-muted py-10">
                                <p>Nenhuma configuração de SLA encontrada</p>
                                <p class="small">Use a configuração padrão ou crie uma nova</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Primeira Resposta</th>
                                            <th>Resolução</th>
                                            <th>Horário</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sla_configs as $sla): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sla['nome']) ?></td>
                                            <td><?= $sla['tempo_primeira_resposta_minutos'] ?> min</td>
                                            <td><?= $sla['tempo_resolucao_horas'] ?> horas</td>
                                            <td><?= $sla['horario_inicio'] ?> - <?= $sla['horario_fim'] ?></td>
                                            <td>
                                                <a href="chat_sla_config.php?id=<?= $sla['id'] ?>" class="btn btn-sm btn-light">
                                                    Editar
                                                </a>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

