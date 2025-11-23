<?php
/**
 * Configuração do Portal Público de Vagas
 */

$page_title = 'Configuração do Portal de Vagas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('vagas.php'); // Usa mesma permissão de vagas

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca configuração atual do portal
$stmt = $pdo->query("
    SELECT * FROM portal_vagas_config 
    LIMIT 1
");
$config = $stmt->fetch();

// Se não existe, cria configuração padrão
if (!$config) {
    $stmt = $pdo->prepare("
        INSERT INTO portal_vagas_config (
            titulo_pagina, 
            descricao_pagina, 
            cor_primaria, 
            cor_secundaria,
            logo_url,
            imagem_hero_url,
            texto_hero,
            texto_cta,
            mostrar_filtros,
            itens_por_pagina,
            ordem_exibicao,
            ativo
        ) VALUES (
            'Trabalhe Conosco',
            'Encontre a oportunidade perfeita para sua carreira',
            '#009ef7',
            '#50cd89',
            NULL,
            NULL,
            'Venha fazer parte do nosso time!',
            'Ver Vagas',
            1,
            12,
            'data_criacao',
            1
        )
    ");
    $stmt->execute();
    $config = $pdo->query("SELECT * FROM portal_vagas_config LIMIT 1")->fetch();
}

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo_pagina = $_POST['titulo_pagina'] ?? '';
    $descricao_pagina = $_POST['descricao_pagina'] ?? '';
    $cor_primaria = $_POST['cor_primaria'] ?? '#009ef7';
    $cor_secundaria = $_POST['cor_secundaria'] ?? '#50cd89';
    $logo_url = $_POST['logo_url'] ?? null;
    $imagem_hero_url = $_POST['imagem_hero_url'] ?? null;
    $texto_hero = $_POST['texto_hero'] ?? '';
    $texto_cta = $_POST['texto_cta'] ?? '';
    $mostrar_filtros = isset($_POST['mostrar_filtros']) ? 1 : 0;
    $itens_por_pagina = (int)($_POST['itens_por_pagina'] ?? 12);
    $ordem_exibicao = $_POST['ordem_exibicao'] ?? 'data_criacao';
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    try {
        if ($config) {
            $stmt = $pdo->prepare("
                UPDATE portal_vagas_config SET
                    titulo_pagina = ?,
                    descricao_pagina = ?,
                    cor_primaria = ?,
                    cor_secundaria = ?,
                    logo_url = ?,
                    imagem_hero_url = ?,
                    texto_hero = ?,
                    texto_cta = ?,
                    mostrar_filtros = ?,
                    itens_por_pagina = ?,
                    ordem_exibicao = ?,
                    ativo = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $titulo_pagina,
                $descricao_pagina,
                $cor_primaria,
                $cor_secundaria,
                $logo_url,
                $imagem_hero_url,
                $texto_hero,
                $texto_cta,
                $mostrar_filtros,
                $itens_por_pagina,
                $ordem_exibicao,
                $ativo,
                $config['id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO portal_vagas_config (
                    titulo_pagina, descricao_pagina, cor_primaria, cor_secundaria,
                    logo_url, imagem_hero_url, texto_hero, texto_cta,
                    mostrar_filtros, itens_por_pagina, ordem_exibicao, ativo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $titulo_pagina, $descricao_pagina, $cor_primaria, $cor_secundaria,
                $logo_url, $imagem_hero_url, $texto_hero, $texto_cta,
                $mostrar_filtros, $itens_por_pagina, $ordem_exibicao, $ativo
            ]);
        }
        
        $mensagem = 'Configurações salvas com sucesso!';
        $tipo_mensagem = 'success';
        
        // Recarrega configuração
        $config = $pdo->query("SELECT * FROM portal_vagas_config LIMIT 1")->fetch();
    } catch (Exception $e) {
        $mensagem = 'Erro ao salvar: ' . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipo_mensagem === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensagem) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Configuração do Portal Público de Vagas</h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="../portal_vagas.php" target="_blank" class="btn btn-light-primary">
                                <i class="ki-duotone ki-eye fs-2"></i>
                                Ver Portal Público
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <form method="POST" id="formConfig">
                            <div class="row">
                                <!-- Informações Básicas -->
                                <div class="col-md-12 mb-5">
                                    <h3 class="mb-4">Informações Básicas</h3>
                                    
                                    <div class="mb-5">
                                        <label class="form-label">Título da Página</label>
                                        <input type="text" name="titulo_pagina" class="form-control" 
                                               value="<?= htmlspecialchars($config['titulo_pagina'] ?? 'Trabalhe Conosco') ?>" required>
                                    </div>
                                    
                                    <div class="mb-5">
                                        <label class="form-label">Descrição da Página</label>
                                        <textarea name="descricao_pagina" class="form-control" rows="3"><?= htmlspecialchars($config['descricao_pagina'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                
                                <!-- Cores -->
                                <div class="col-md-12 mb-5">
                                    <h3 class="mb-4">Cores</h3>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-5">
                                            <label class="form-label">Cor Primária</label>
                                            <input type="color" name="cor_primaria" class="form-control form-control-color" 
                                                   value="<?= htmlspecialchars($config['cor_primaria'] ?? '#009ef7') ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-5">
                                            <label class="form-label">Cor Secundária</label>
                                            <input type="color" name="cor_secundaria" class="form-control form-control-color" 
                                                   value="<?= htmlspecialchars($config['cor_secundaria'] ?? '#50cd89') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Imagens -->
                                <div class="col-md-12 mb-5">
                                    <h3 class="mb-4">Imagens</h3>
                                    
                                    <div class="mb-5">
                                        <label class="form-label">URL do Logo</label>
                                        <input type="url" name="logo_url" class="form-control" 
                                               value="<?= htmlspecialchars($config['logo_url'] ?? '') ?>" 
                                               placeholder="https://exemplo.com/logo.png">
                                        <div class="form-text">URL completa da imagem do logo</div>
                                    </div>
                                    
                                    <div class="mb-5">
                                        <label class="form-label">URL da Imagem Hero (Banner Principal)</label>
                                        <input type="url" name="imagem_hero_url" class="form-control" 
                                               value="<?= htmlspecialchars($config['imagem_hero_url'] ?? '') ?>" 
                                               placeholder="https://exemplo.com/banner.jpg">
                                        <div class="form-text">URL completa da imagem de destaque</div>
                                    </div>
                                </div>
                                
                                <!-- Textos -->
                                <div class="col-md-12 mb-5">
                                    <h3 class="mb-4">Textos</h3>
                                    
                                    <div class="mb-5">
                                        <label class="form-label">Texto do Hero (Banner Principal)</label>
                                        <textarea name="texto_hero" class="form-control" rows="2"><?= htmlspecialchars($config['texto_hero'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="mb-5">
                                        <label class="form-label">Texto do Botão CTA</label>
                                        <input type="text" name="texto_cta" class="form-control" 
                                               value="<?= htmlspecialchars($config['texto_cta'] ?? 'Ver Vagas') ?>">
                                    </div>
                                </div>
                                
                                <!-- Configurações -->
                                <div class="col-md-12 mb-5">
                                    <h3 class="mb-4">Configurações</h3>
                                    
                                    <div class="mb-5">
                                        <label class="form-label">Itens por Página</label>
                                        <input type="number" name="itens_por_pagina" class="form-control" 
                                               value="<?= htmlspecialchars($config['itens_por_pagina'] ?? 12) ?>" min="1" max="50">
                                    </div>
                                    
                                    <div class="mb-5">
                                        <label class="form-label">Ordem de Exibição</label>
                                        <select name="ordem_exibicao" class="form-select">
                                            <option value="data_criacao" <?= ($config['ordem_exibicao'] ?? 'data_criacao') === 'data_criacao' ? 'selected' : '' ?>>Data de Criação (Mais Recentes)</option>
                                            <option value="titulo" <?= ($config['ordem_exibicao'] ?? '') === 'titulo' ? 'selected' : '' ?>>Título (A-Z)</option>
                                            <option value="empresa" <?= ($config['ordem_exibicao'] ?? '') === 'empresa' ? 'selected' : '' ?>>Empresa</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-5">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="mostrar_filtros" 
                                                   id="mostrar_filtros" <?= ($config['mostrar_filtros'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="mostrar_filtros">
                                                Mostrar Filtros de Busca
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-5">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="ativo" 
                                                   id="ativo" <?= ($config['ativo'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="ativo">
                                                Portal Ativo
                                            </label>
                                        </div>
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
                
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

