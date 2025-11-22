<?php
/**
 * Página Pública para Responder Pesquisa via Link (Token)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/engajamento.php';

$token = $_GET['token'] ?? null;

if (!$token) {
    die('Token inválido');
}

$pdo = getDB();

// Busca pelo token_resposta (token específico do colaborador)
$stmt = $pdo->prepare("
    SELECT pse.*, ps.*, 'satisfacao' as tipo, pse.colaborador_id
    FROM pesquisas_satisfacao_envios pse
    INNER JOIN pesquisas_satisfacao ps ON pse.pesquisa_id = ps.id
    WHERE pse.token_resposta = ? AND ps.status = 'ativa'
");
$stmt->execute([$token]);
$envio_satisfacao = $stmt->fetch();

if ($envio_satisfacao) {
    $pesquisa = $envio_satisfacao;
    $tipo = 'satisfacao';
    $colaborador_id = $envio_satisfacao['colaborador_id'];
    $anonima = $envio_satisfacao['anonima'] == 1;
    
    // Busca campos
    $stmt = $pdo->prepare("SELECT * FROM pesquisas_satisfacao_campos WHERE pesquisa_id = ? ORDER BY ordem ASC");
    $stmt->execute([$pesquisa['pesquisa_id']]);
    $campos = $stmt->fetchAll();
    
} else {
    // Tenta pesquisa rápida
    $stmt = $pdo->prepare("
        SELECT pre.*, pr.*, 'rapida' as tipo, pre.colaborador_id
        FROM pesquisas_rapidas_envios pre
        INNER JOIN pesquisas_rapidas pr ON pre.pesquisa_id = pr.id
        WHERE pre.token_resposta = ? AND pr.status = 'ativa'
    ");
    $stmt->execute([$token]);
    $envio_rapida = $stmt->fetch();
    
    if ($envio_rapida) {
        $pesquisa = $envio_rapida;
        $tipo = 'rapida';
        $colaborador_id = $envio_rapida['colaborador_id'];
        $anonima = $envio_rapida['anonima'] == 1;
        $campos = [];
    } else {
        // Fallback: link público genérico (precisa identificar se não for anônima)
        $stmt = $pdo->prepare("SELECT * FROM pesquisas_satisfacao WHERE link_token = ? AND status = 'ativa'");
        $stmt->execute([$token]);
        $pesquisa_satisfacao = $stmt->fetch();
        
        if ($pesquisa_satisfacao) {
            $pesquisa = $pesquisa_satisfacao;
            $tipo = 'satisfacao';
            $anonima = $pesquisa_satisfacao['anonima'] == 1;
            $colaborador_id = null;
            
            $stmt = $pdo->prepare("SELECT * FROM pesquisas_satisfacao_campos WHERE pesquisa_id = ? ORDER BY ordem ASC");
            $stmt->execute([$pesquisa['id']]);
            $campos = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT * FROM pesquisas_rapidas WHERE link_token = ? AND status = 'ativa'");
            $stmt->execute([$token]);
            $pesquisa_rapida = $stmt->fetch();
            
            if ($pesquisa_rapida) {
                $pesquisa = $pesquisa_rapida;
                $tipo = 'rapida';
                $anonima = $pesquisa_rapida['anonima'] == 1;
                $colaborador_id = null;
                $campos = [];
            } else {
                die('Pesquisa não encontrada ou inativa');
            }
        }
    }
}

// Verifica se está dentro do prazo
$data_hoje = date('Y-m-d');
if ($pesquisa['data_inicio'] > $data_hoje) {
    die('Pesquisa ainda não está disponível');
}

if ($pesquisa['data_fim'] && $pesquisa['data_fim'] < $data_hoje) {
    die('Pesquisa já expirou');
}

// Se já tem colaborador_id identificado pelo token, não precisa pedir identificação
$precisa_identificar = !$anonima && !isset($colaborador_id);
$identificador = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($precisa_identificar) {
        $identificador = $_POST['identificador'] ?? '';
        
        if (empty($identificador)) {
            $erro = 'Por favor, identifique-se (email ou CPF)';
        } else {
            // Identifica colaborador
            $cpf_limpo = preg_replace('/[^0-9]/', '', $identificador);
            $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE (cpf = ? OR email_pessoal = ?) AND status = 'ativo'");
            $stmt->execute([$cpf_limpo, $identificador]);
            $colab = $stmt->fetch();
            if ($colab) {
                $colaborador_id = $colab['id'];
                $precisa_identificar = false;
            } else {
                $erro = 'Colaborador não encontrado';
            }
        }
    }
    
    if (!$precisa_identificar || !empty($erro)) {
        if (empty($erro)) {
        // Envia resposta via AJAX
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Responder Pesquisa</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #f5f5f5; padding: 20px; }
                .container { max-width: 800px; margin: 0 auto; }
                .card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card p-4">
                    <h2 class="mb-4">Enviando resposta...</h2>
                    <div class="spinner-border" role="status"></div>
                </div>
            </div>
            <script>
                const formData = new FormData();
                formData.append('token', '<?= htmlspecialchars($token) ?>');
                <?php if ($precisa_identificar && !empty($identificador)): ?>
                formData.append('identificador', '<?= htmlspecialchars($identificador) ?>');
                <?php endif; ?>
                
                <?php if ($tipo === 'satisfacao'): ?>
                const respostas = {};
                <?php foreach ($campos as $campo): ?>
                const campo_<?= $campo['id'] ?> = document.querySelector('[name="campo_<?= $campo['id'] ?>"]');
                if (campo_<?= $campo['id'] ?>) {
                    if (campo_<?= $campo['id'] ?>.type === 'checkbox') {
                        const checked = document.querySelectorAll('[name="campo_<?= $campo['id'] ?>"]:checked');
                        respostas[<?= $campo['id'] ?>] = Array.from(checked).map(c => c.value);
                    } else {
                        respostas[<?= $campo['id'] ?>] = campo_<?= $campo['id'] ?>.value;
                    }
                }
                <?php endforeach; ?>
                formData.append('respostas', JSON.stringify(respostas));
                <?php else: ?>
                formData.append('resposta', document.querySelector('[name="resposta"]').value);
                <?php endif; ?>
                
                fetch('<?= get_base_url() ?>/api/pesquisas/responder.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector('.card').innerHTML = '<h2 class="text-success">✓ Resposta enviada com sucesso!</h2><p>Obrigado por participar!</p>';
                    } else {
                        document.querySelector('.card').innerHTML = '<h2 class="text-danger">Erro</h2><p>' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    document.querySelector('.card').innerHTML = '<h2 class="text-danger">Erro</h2><p>Ocorreu um erro ao enviar a resposta.</p>';
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responder Pesquisa - <?= htmlspecialchars($pesquisa['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <h2 class="mb-3"><?= htmlspecialchars($pesquisa['titulo']) ?></h2>
            
            <?php if (!empty($pesquisa['descricao'])): ?>
            <p class="text-muted mb-4"><?= nl2br(htmlspecialchars($pesquisa['descricao'])) ?></p>
            <?php endif; ?>
            
            <?php if ($erro): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>
            
            <form method="POST" id="form-pesquisa">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <?php if ($precisa_identificar): ?>
                <div class="mb-3">
                    <label class="form-label">Email ou CPF *</label>
                    <input type="text" name="identificador" class="form-control" value="<?= htmlspecialchars($identificador) ?>" required>
                    <small class="text-muted">Identifique-se para responder esta pesquisa</small>
                </div>
                <?php elseif (isset($colaborador_id)): ?>
                <?php
                // Busca nome do colaborador
                $stmt = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
                $stmt->execute([$colaborador_id]);
                $colab_nome = $stmt->fetch();
                ?>
                <div class="alert alert-info">
                    <strong>Olá, <?= htmlspecialchars($colab_nome['nome_completo']) ?>!</strong>
                    <p class="mb-0">Você foi identificado automaticamente. Pode responder a pesquisa abaixo.</p>
                </div>
                <?php endif; ?>
                
                <?php if ($tipo === 'satisfacao'): ?>
                    <?php foreach ($campos as $campo): ?>
                    <div class="mb-3">
                        <label class="form-label">
                            <?= htmlspecialchars($campo['label']) ?>
                            <?php if ($campo['obrigatorio']): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>
                        
                        <?php if (!empty($campo['descricao'])): ?>
                        <small class="text-muted d-block mb-2"><?= htmlspecialchars($campo['descricao']) ?></small>
                        <?php endif; ?>
                        
                        <?php
                        $tipo_campo = $campo['tipo'];
                        $opcoes = !empty($campo['opcoes']) ? json_decode($campo['opcoes'], true) : [];
                        ?>
                        
                        <?php if ($tipo_campo === 'texto'): ?>
                            <input type="text" name="campo_<?= $campo['id'] ?>" class="form-control" 
                                   placeholder="<?= htmlspecialchars($campo['placeholder'] ?? '') ?>"
                                   value="<?= htmlspecialchars($campo['valor_padrao'] ?? '') ?>"
                                   <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                        
                        <?php elseif ($tipo_campo === 'textarea'): ?>
                            <textarea name="campo_<?= $campo['id'] ?>" class="form-control" rows="4"
                                      placeholder="<?= htmlspecialchars($campo['placeholder'] ?? '') ?>"
                                      <?= $campo['obrigatorio'] ? 'required' : '' ?>><?= htmlspecialchars($campo['valor_padrao'] ?? '') ?></textarea>
                        
                        <?php elseif ($tipo_campo === 'multipla_escolha' && !empty($opcoes)): ?>
                            <?php foreach ($opcoes as $opcao): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="campo_<?= $campo['id'] ?>" 
                                       value="<?= htmlspecialchars($opcao) ?>" id="campo_<?= $campo['id'] ?>_<?= md5($opcao) ?>"
                                       <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                                <label class="form-check-label" for="campo_<?= $campo['id'] ?>_<?= md5($opcao) ?>">
                                    <?= htmlspecialchars($opcao) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        
                        <?php elseif ($tipo_campo === 'checkbox_multiplo' && !empty($opcoes)): ?>
                            <?php foreach ($opcoes as $opcao): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="campo_<?= $campo['id'] ?>[]" 
                                       value="<?= htmlspecialchars($opcao) ?>" id="campo_<?= $campo['id'] ?>_<?= md5($opcao) ?>">
                                <label class="form-check-label" for="campo_<?= $campo['id'] ?>_<?= md5($opcao) ?>">
                                    <?= htmlspecialchars($opcao) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        
                        <?php elseif ($tipo_campo === 'escala_1_5'): ?>
                            <select name="campo_<?= $campo['id'] ?>" class="form-select" <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                                <option value="">Selecione...</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        
                        <?php elseif ($tipo_campo === 'escala_1_10'): ?>
                            <select name="campo_<?= $campo['id'] ?>" class="form-select" <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                                <option value="">Selecione...</option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        
                        <?php elseif ($tipo_campo === 'sim_nao'): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="campo_<?= $campo['id'] ?>" value="sim" id="campo_<?= $campo['id'] ?>_sim" <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                                <label class="form-check-label" for="campo_<?= $campo['id'] ?>_sim">Sim</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="campo_<?= $campo['id'] ?>" value="nao" id="campo_<?= $campo['id'] ?>_nao">
                                <label class="form-check-label" for="campo_<?= $campo['id'] ?>_nao">Não</label>
                            </div>
                        
                        <?php else: ?>
                            <input type="text" name="campo_<?= $campo['id'] ?>" class="form-control" 
                                   <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                
                <?php else: ?>
                    <!-- Pesquisa Rápida -->
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($pesquisa['pergunta']) ?></label>
                        
                        <?php
                        $tipo_resposta = $pesquisa['tipo_resposta'];
                        $opcoes = !empty($pesquisa['opcoes']) ? json_decode($pesquisa['opcoes'], true) : [];
                        ?>
                        
                        <?php if ($tipo_resposta === 'sim_nao'): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resposta" value="sim" id="resposta_sim" required>
                                <label class="form-check-label" for="resposta_sim">Sim</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resposta" value="nao" id="resposta_nao">
                                <label class="form-check-label" for="resposta_nao">Não</label>
                            </div>
                        
                        <?php elseif ($tipo_resposta === 'multipla_escolha' && !empty($opcoes)): ?>
                            <?php foreach ($opcoes as $opcao): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resposta" value="<?= htmlspecialchars($opcao) ?>" id="resposta_<?= md5($opcao) ?>" required>
                                <label class="form-check-label" for="resposta_<?= md5($opcao) ?>"><?= htmlspecialchars($opcao) ?></label>
                            </div>
                            <?php endforeach; ?>
                        
                        <?php elseif ($tipo_resposta === 'escala_1_5'): ?>
                            <select name="resposta" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        
                        <?php elseif ($tipo_resposta === 'escala_1_10'): ?>
                            <select name="resposta" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        
                        <?php else: ?>
                            <input type="text" name="resposta" class="form-control" required>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">Enviar Resposta</button>
            </form>
        </div>
    </div>
</body>
</html>

